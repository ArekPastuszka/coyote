<?php

namespace Coyote\Http\Controllers\User;

use Coyote\Events\PmCreated;
use Coyote\Http\Factories\MediaFactory;
use Coyote\Http\Requests\PmRequest;
use Coyote\Http\Resources\PmResource;
use Coyote\Notifications\PmCreatedNotification;
use Coyote\Pm;
use Coyote\Repositories\Contracts\NotificationRepositoryInterface as NotificationRepository;
use Coyote\Repositories\Contracts\PmRepositoryInterface as PmRepository;
use Coyote\Repositories\Contracts\UserRepositoryInterface as UserRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;

/**
 * Class PmController
 * @package Coyote\Http\Controllers\User
 */
class PmController extends BaseController
{
    use HomeTrait, MediaFactory;

    /**
     * @var UserRepository
     */
    private $user;

    /**
     * @var NotificationRepository
     */
    private $notification;

    /**
     * @var PmRepository
     */
    private $pm;

    /**
     * @param UserRepository $user
     * @param NotificationRepository $notification
     * @param PmRepository $pm
     */
    public function __construct(UserRepository $user, NotificationRepository $notification, PmRepository $pm)
    {
        parent::__construct();

        $this->user = $user;
        $this->notification = $notification;
        $this->pm = $pm;
    }

    /**
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->breadcrumb->push('Wiadomości prywatne', route('user.pm'));

        $pm = $this->pm->lengthAwarePaginate($this->userId);
        $parser = $this->getParser();

        foreach ($pm as &$row) {
            $row->text = $parser->parse($row->text);
        }

        return $this->view('user.pm.home')->with(compact('pm'));
    }

    /**
     * @param Pm $pm
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(Pm $pm, Request $request)
    {
        $this->breadcrumb->push('Wiadomości prywatne', route('user.pm'));

        $this->authorize('show', $pm);

        $talk = $this->pm->talk($this->userId, $pm->author_id, 10, (int) $request->query('offset', 0));

        $messages = PmResource::collection($talk);

        $recipient = $this->user->find($pm->author_id, ['id', 'name']);

        return $this->view('user.pm.show')->with(compact('pm', 'messages', 'recipient'));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function infinity(Request $request)
    {
        $talk = $this->pm->talk($this->userId, (int) $request->input('author_id'), 10, (int) $request->query('offset', 0));

        return PmResource::collection($talk);
    }

    /**
     * Get last 10 conversations
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function inbox()
    {
        $pm = $this->pm->groupByAuthor($this->userId);

        return response()->json([
            'pm' => PmResource::collection($pm)
        ]);
    }

    /**
     * @return \Illuminate\View\View
     */
    public function submit()
    {
        $this->breadcrumb->push('Wiadomości prywatne', route('user.pm'));
        $this->breadcrumb->push('Napisz wiadomość', route('user.pm.submit'));

        return $this->view('user.pm.submit');
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function preview(Request $request)
    {
        return response($this->getParser()->parse((string) $request->get('text')));
    }

    /**
     * @param PmRequest $request
     * @return PmResource
     */
    public function save(PmRequest $request)
    {
        $recipient = $this->user->findByName($request->get('recipient'));

        $pm = $this->transaction(function () use ($request, $recipient) {
            return $this->pm->submit($this->auth, $request->all() + ['author_id' => $recipient->id]);
        });

        event(new PmCreated($pm[Pm::INBOX]));

        $recipient->notify(new PmCreatedNotification($pm[Pm::INBOX]));

        PmResource::withoutWrapping();

        return new PmResource($pm[Pm::SENTBOX]);
    }

    /**
     * @param Pm $pm
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function delete(Pm $pm)
    {
        $this->authorize('show', $pm);

        $pm->delete();
    }

    /**
     * @param Pm $pm
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function markAsRead(Pm $pm)
    {
        $this->authorize('show', $pm);

        if (!$pm->read_at) {
            // database trigger will decrease pm counter in "users" table.
            $this->pm->markAsRead($pm->text_id);

            // IF we have unread alert that is connected with that message... then we also have to mark it as read
//                if ($this->auth->notifications_unread) {
//                    $this->notification->markAsReadByUrl($this->userId, route('user.pm.show', [$row['id']], false));
//                }
        }
    }

    /**
     * @param int $authorId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function trash($authorId)
    {
        $pm = $this->pm->findWhere(['user_id' => $this->userId, 'author_id' => $authorId]);
        abort_if($pm->count() == 0, 404);

        $this->pm->trash($this->userId, $authorId);

        return redirect()->route('user.pm')->with('success', 'Wątek został bezpowrotnie usunięty.');
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function paste()
    {
        $input = file_get_contents("php://input");

        $validator = $this->getValidationFactory()->make(
            ['length' => strlen($input)],
            ['length' => 'max:' . config('filesystems.upload_max_size') * 1024 * 1024]
        );

        $this->validateWith($validator);

        $media = $this->getMediaFactory()->make('screenshot')->put(file_get_contents('data://' . substr($input, 7)));
        $mime = MimeTypeGuesser::getInstance();

        return response()->json([
            'size'      => $media->size(),
            'suffix'    => 'png',
            'name'      => $media->getName(),
            'file'      => $media->getFilename(),
            'mime'      => $mime->guess($media->path()),
            'url'       => (string) $media->url()
        ]);
    }

    /**
     * @return \Coyote\Services\Parser\Factories\PmFactory
     */
    private function getParser()
    {
        return app('parser.pm');
    }
}
