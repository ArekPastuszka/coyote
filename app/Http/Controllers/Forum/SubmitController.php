<?php

namespace Coyote\Http\Controllers\Forum;

use Coyote\Http\Controllers\Controller;
use Coyote\Http\Forms\Forum\SubjectForm;
use Coyote\Repositories\Contracts\PollRepositoryInterface;
use Coyote\Services\UrlBuilder\UrlBuilder;
use Illuminate\Http\Request;
use Coyote\Services\Stream\Activities\Create as Stream_Create;
use Coyote\Services\Stream\Activities\Update as Stream_Update;
use Coyote\Services\Stream\Objects\Topic as Stream_Topic;
use Coyote\Services\Stream\Objects\Post as Stream_Post;
use Coyote\Services\Stream\Objects\Forum as Stream_Forum;
use Coyote\Services\Stream\Actor as Stream_Actor;
use Coyote\Services\Parser\Helpers\Login as LoginHelper;
use Coyote\Events\PostWasSaved;
use Coyote\Events\TopicWasSaved;
use Coyote\Services\Alert\Container;
use Coyote\Post\Log;

class SubmitController extends BaseController
{
    /**
     * Show new post/edit form
     *
     * @param Request $request
     * @param \Coyote\Forum $forum
     * @param \Coyote\Topic $topic
     * @param \Coyote\Post|null $post
     * @return \Illuminate\View\View
     */
    public function index(Request $request, $forum, $topic, $post = null)
    {
        $this->breadcrumb($forum);

        if (!empty($topic->id)) {
            $this->breadcrumb->push([
                $topic->subject => route('forum.topic', [$forum->slug, $topic->id, $topic->slug]),
                $post === null ? 'Odpowiedz' : 'Edycja' => url($request->path())
            ]);
        } else {
            $this->breadcrumb->push('Nowy wątek', route('forum.topic.submit', [$forum->slug]));
        }

        if (!empty($post)) {
            // make sure user can edit this post
            $this->authorize('update', [$post, $forum]);
        }

        $form = $this->getForm($forum, $topic, $post);
        $form->text->setValue($form->text->getValue() ?: ($topic ? $this->getDefaultText($request, $topic) : ''));

        return Controller::view('forum.submit')->with(compact('forum', 'form', 'topic', 'post'));
    }

    /**
     * Show new post/edit form
     *
     * @param \Coyote\Forum $forum
     * @param \Coyote\Topic $topic
     * @param \Coyote\Post|null $post
     * @return mixed
     */
    public function save($forum, $topic, $post = null)
    {
        if (is_null($post)) {
            $post = $this->post->makeModel();
        }

        $form = $this->getForm($forum, $topic, $post);
        $form->validate();

        $request = $form->getRequest();

        return $this->transaction(function () use ($request, $forum, $topic, $post) {
            $actor = new Stream_Actor(auth()->user());
            if (auth()->guest()) {
                $actor->displayName = $request->get('user_name');
            }

            $poll = $this->savePoll($request, $topic->poll_id);

            $activity = $post->id ? new Stream_Update($actor) : new Stream_Create($actor);
            // saving post through repository... we need to pass few object to save relationships
            $this->post->save($request, auth()->user(), $forum, $topic, $post, $poll);

            // url to the post
            $url = UrlBuilder::post($post);

            if ($post->wasRecentlyCreated) {
                $alert = new Container();
                $notification = [
                    'sender_id' => $this->userId,
                    'sender_name' => $request->get('user_name', $this->userId ? auth()->user()->name : ''),
                    'subject' => str_limit($topic->subject, 84),
                    'excerpt' => excerpt($post->html),
                    'url' => $url,
                    'text' => $post->html
                ];

                // $subscribersId can be int or array. we need to cast to array type
                $subscribersId = $forum->onlyUsersWithAccess($topic->subscribers()->lists('user_id')->toArray());
                if ($subscribersId) {
                    $alert->attach(
                        app('alert.topic.subscriber')->with($notification)->setUsersId($subscribersId)
                    );
                }

                // get id of users that were mentioned in the text
                $subscribersId = $forum->onlyUsersWithAccess((new LoginHelper())->grab($post->html));
                if ($subscribersId) {
                    $alert->attach(app('alert.post.login')->with($notification)->setUsersId($subscribersId));
                }

                $alert->notify();
            }

            if ($topic->wasRecentlyCreated || $post->id === $topic->first_post_id) {
                $object = (new Stream_Topic)->map($topic, $post->html);
                $target = (new Stream_Forum)->map($forum);
            } else {
                $object = (new Stream_Post(['url' => $url]))->map($post);
                $target = (new Stream_Topic())->map($topic);
            }

            stream($activity, $object, $target);

            // fire the event. it can be used to index a content and/or add page path to "pages" table
            event(new TopicWasSaved($topic));
            // add post to elasticsearch
            event(new PostWasSaved($post));

            $request->attributes->set('url', $url);
            return $post;
        });
    }

    /**
     * @param Request $request
     * @param int $pollId
     * @return \Coyote\Poll|null
     */
    private function savePoll(Request $request, $pollId)
    {
        if ($request->input('poll.remove')) {
            $this->getPollRepository()->delete($pollId);
        } elseif ($request->has('poll.title')) {
            return $this->getPollRepository()->updateOrCreate($pollId, $request->input('poll'));
        } elseif ($pollId) {
            return $this->getPollRepository()->find($pollId);
        }

        return null;
    }

    /**
     * @return PollRepositoryInterface
     */
    private function getPollRepository()
    {
        return app(PollRepositoryInterface::class);
    }

    /**
     * Ajax request. Display edit form
     *
     * @param $forum
     * @param $topic
     * @param $post
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($forum, $topic, $post)
    {
        $form = $this->getForm($forum, $topic, $post);

        return view('forum.partials.edit')->with('form', $form);
    }

    /**
     * @param \Coyote\Topic $topic
     * @param SubjectForm $form
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @todo moze jakas refaktoryzacja? przeniesienie do repozytorium? na pewno logowanie o tym, ze zostal zmieniony
     * tytul a nie tresc posta (jak to jest obecnie)
     */
    public function subject($topic, SubjectForm $form)
    {
        /** @var \Coyote\Forum $forum */
        $forum = $topic->forum()->first();
        $this->authorize('update', $forum);

        $request = $form->getRequest();

        $url = $this->transaction(function () use ($request, $forum, $topic) {
            $topic->fill(['subject' => $request->get('subject')]);

            /** @var \Coyote\Post $post */
            $post = $topic->firstPost()->first();
            $url = route('forum.topic', [$forum->slug, $topic->id, $topic->slug], false);

            if ($topic->isDirty()) {
                $original = $topic->getOriginal();

                $topic->save();
                $tags = $topic->getTagNames();

                // save it in log...
                (new Log)
                    ->fillWithPost($post)
                    ->fill(['user_id' => $this->userId, 'subject' => $topic->subject, 'tags' => $tags])
                    ->save();

                $post->fill([
                    'edit_count' => $post->edit_count + 1, 'editor_id' => $this->userId
                ])
                ->save();

                if ($post->user_id) {
                    app('alert.topic.subject')->with([
                        'users_id'    => $forum->onlyUsersWithAccess([$post->user_id]),
                        'sender_id'   => $this->userId,
                        'sender_name' => $this->auth->name,
                        'subject'     => str_limit($original['subject'], 84),
                        'excerpt'     => str_limit($topic->subject, 84),
                        'url'         => $url
                    ])->notify();
                }

                // fire the event. it can be used to index a content and/or add page path to "pages" table
                event(new TopicWasSaved($topic));
                // add post to elasticsearch
                event(new PostWasSaved($post));
            }

            // get text from cache to put excerpt in stream activity
            $post->text = app('parser.post')->parse($post->text);

            // put action into activity stream
            stream(
                Stream_Update::class,
                (new Stream_Topic)->map($topic, $post->text),
                (new Stream_Forum)->map($forum)
            );

            return $url;
        });

        if ($request->ajax()) {
            return response(url($url));
        } else {
            return redirect()->to($url);
        }
    }

    /**
     * Format post text in case of quoting
     *
     * @param Request $request
     * @param \Coyote\Topic $topic
     * @return string
     */
    protected function getDefaultText(Request $request, $topic)
    {
        $text = '';

        // IDs of posts that user want to quote...
        $postsId = [];
        $cookie = isset($_COOKIE['mqid' . $topic->id]) ? $_COOKIE['mqid' . $topic->id] : null;

        if ($cookie) {
            $postsId = array_map('intval', explode(',', $cookie));
            // I used raw PHP function because I don't want to use laravel encryption in this case
            setcookie('mqid' . $topic->id, null, time() - 3600, '/');
        }

        if ($request->input('quote')) {
            $postsId[] = $request->input('quote');
        }

        if (!empty($postsId)) {
            $posts = $this->post->findPosts(array_unique($postsId), $topic->id);

            // builds text with quoted posts
            foreach ($posts as $post) {
                $text .= '> ##### [' .
                    ($post->name ?: $post->user_name) .
                    ' napisał(a)](' . route('forum.share', [$post->id]) . '):';

                $text .= "\n> " . str_replace("\n", "\n> ", $post->text);
                $text .= "\n\n";
            }
        }

        return $text;
    }
}
