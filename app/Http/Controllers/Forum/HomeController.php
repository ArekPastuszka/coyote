<?php

namespace Coyote\Http\Controllers\Forum;

use Coyote\Http\Factories\FlagFactory;
use Coyote\Http\Factories\GateFactory;
use Coyote\Repositories\Contracts\ForumRepositoryInterface as ForumRepository;
use Coyote\Repositories\Contracts\TopicRepositoryInterface as TopicRepository;
use Coyote\Repositories\Contracts\PostRepositoryInterface as PostRepository;
use Coyote\Repositories\Contracts\UserRepositoryInterface;
use Coyote\Repositories\Criteria\Topic\OnlyMine;
use Coyote\Repositories\Criteria\Topic\Subscribes;
use Coyote\Repositories\Criteria\Topic\Unanswered;
use Coyote\Repositories\Criteria\Topic\OnlyThoseWithAccess;
use Coyote\Repositories\Criteria\Topic\WithTag;
use Illuminate\Http\Request;
use Lavary\Menu\Item;
use Lavary\Menu\Menu;
use Lavary\Menu\Builder;

class HomeController extends BaseController
{
    use GateFactory, FlagFactory;

    /**
     * @var Builder
     */
    private $tabs;

    /**
     * @param ForumRepository $forum
     * @param TopicRepository $topic
     * @param PostRepository $post
     */
    public function __construct(ForumRepository $forum, TopicRepository $topic, PostRepository $post)
    {
        parent::__construct($forum, $topic, $post);

        $this->tabs = app(Menu::class)->make('_forum', function (Builder $menu) {
            foreach (config('laravel-menu._forum') as $title => $row) {
                $data = array_pull($row, 'data');
                $menu->add($title, $row)->data($data);
            }
        })
        ->filter(function (Item $item) {
            if ($item->data('role') === true) {
                return $this->userId !== null;
            }

            return true;
        });

        // currently selected tab
        list(, $suffix) = explode('.', $this->getRouter()->currentRouteName());

        if (in_array($suffix, ['categories', 'all', 'unanswered', 'subscribes', 'mine'])) {
            $this->setSetting('forum.tab', $suffix);
        }
    }

    /**
     * @param string $view
     * @param array $data
     * @return \Illuminate\View\View
     */
    protected function view($view = null, $data = [])
    {
        list(, $suffix) = explode('.', $this->getRouter()->currentRouteName());

        $currentTab = $suffix == 'home' ? $this->getSetting('forum.tab', 'categories') : $suffix;
        $title = null;

        foreach ($this->tabs->all() as $tab) {
            if ("forum.$currentTab" == $tab->link->path['route']) {
                $tab->activate();

                $title = $tab->title;
            }
        }

        return parent::view($view, $data)->with(['tabs' => $this->tabs, 'title' => $title]);
    }

    /**
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $tab = $this->getSetting('forum.tab', 'categories');

        return $this->{$tab}();
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function preview(Request $request)
    {
        $parser = app('parser.post');
        $parser->cache->setEnable(false);

        return response($parser->parse($request->get('text')));
    }

    /**
     * @return \Illuminate\View\View
     */
    public function categories()
    {
        $this->pushForumCriteria();
        // execute query: get all categories that user can has access
        $sections = $this->forum->groupBySections($this->userId, $this->sessionId);
        // get categories collapse
        $collapse = $this->collapse();

        return $this->view('forum.home')->with(compact('sections', 'collapse'));
    }

    /**
     * @return \Illuminate\View\View
     */
    public function all()
    {
        return $this->loadAndRender();
    }

    /**
     * @return \Illuminate\View\View
     */
    public function unanswered()
    {
        $this->topic->pushCriteria(new Unanswered());
        return $this->loadAndRender();
    }

    /**
     * @return \Illuminate\View\View
     */
    public function mine()
    {
        return $this->user($this->userId);
    }

    /**
     * @param int $userId
     * @return \Illuminate\View\View
     */
    public function user($userId)
    {
        $this->topic->pushCriteria(new OnlyMine($userId));
        $topics = $this->load();

        if ($topics->total() > 0) {
            $topics->load(['posts' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }]);
        }

        $user = app(UserRepositoryInterface::class)->find($userId);
        abort_if(is_null($user), 404);

        if ($this->getRouter()->currentRouteName() == 'forum.user') {
            $this
                ->tabs
                ->add('Posty: ' . $user->name, [
                    'route' => [
                        'forum.user', $userId
                    ]
                ])
                ->activate();
        }

        return $this->render($topics)->with('user_id', $userId);
    }

    /**
     * @return \Illuminate\View\View
     */
    public function subscribes()
    {
        $this->topic->pushCriteria(new Subscribes($this->userId));
        return $this->loadAndRender();
    }

    /**
     * @param string $name
     * @return \Illuminate\View\View
     */
    public function tag($name)
    {
        $request = $this->getRouter()->getCurrentRequest();

        $this
            ->tabs
            ->add('Wątki z: ' . $request->route('tag'), [
                'route' => [
                    'forum.tag', urlencode($request->route('tag'))
                ]
            ])
            ->activate();

        $this->topic->pushCriteria(new WithTag($name));
        return $this->loadAndRender();
    }

    /**
     * Mark ALL categories as READ
     */
    public function mark()
    {
        $forums = $this->forum->all(['id']);
        foreach ($forums as $forum) {
            $this->forum->markAsRead($forum->id, $this->userId, $this->sessionId);
        }
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function load()
    {
        $this->topic->pushCriteria(new OnlyThoseWithAccess($this->auth));

        return $this
            ->topic
            ->paginate(
                $this->userId,
                $this->sessionId,
                'topics.last_post_id',
                'DESC',
                $this->topicsPerPage($this->getRouter()->getCurrentRequest())
            )
            ->appends(request()->except('page'));
    }

    /**
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $topics
     * @return \Illuminate\View\View
     */
    private function render($topics)
    {
        // we need to get an information about flagged topics. that's how moderators can notice
        // that's something's wrong with posts.
        if ($topics->total() && $this->getGateFactory()->allows('forum-delete')) {
            $flags = $this->getFlagFactory()->takeForTopics($topics->groupBy('id')->keys()->toArray());
        }

        $postsPerPage = $this->postsPerPage($this->getRouter()->getCurrentRequest());

        return $this->view('forum.topics')->with(compact('topics', 'flags', 'postsPerPage'));
    }

    /**
     * @return \Illuminate\View\View
     */
    private function loadAndRender()
    {
        return $this->render($this->load());
    }
}
