<?php

namespace Coyote\Http\Controllers\Wiki;

use Coyote\Events\WikiWasSaved;
use Coyote\Http\Forms\Wiki\MoveForm;
use Coyote\Services\Stream\Objects\Wiki as Stream_Wiki;
use Coyote\Services\Stream\Activities\Move as Stream_Move;

class MoveController extends BaseController
{
    /**
     * @param \Coyote\Wiki $wiki
     * @return \Illuminate\View\View
     */
    public function index($wiki)
    {
        return $this->view('wiki.move', [
            'form' => $this->createForm(MoveForm::class, $wiki, [
                'url' => route('wiki.move', [$wiki->id])
            ])
        ]);
    }

    /**
     * @param \Coyote\Wiki $wiki
     * @param MoveForm $form
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save($wiki, MoveForm $form)
    {
        $path = $this->transaction(function () use ($wiki, $form) {
            // move page to new location
            $path = $this->wiki->move($wiki->id, $wiki->wiki_id, $form->get('parent_id')->getValue());
            $wiki->forceFill($path->toArray());

            $wiki->id = $wiki->path_id;

            stream(Stream_Move::class, (new Stream_Wiki())->map($wiki));
            event(new WikiWasSaved($wiki));

            return $path->path;
        });

        return redirect()->to($path)->with('success', 'Strona poprawnie przeniesiona.');
    }
}
