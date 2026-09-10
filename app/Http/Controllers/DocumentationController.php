<?php

namespace App\Http\Controllers;

use App\Support\Documentation\DocumentationPages;
use App\Support\Documentation\MarkdownDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class DocumentationController extends Controller
{
    public function index(MarkdownDocument $markdown): View
    {
        return $this->page('overview', $markdown);
    }

    public function show(string $page, MarkdownDocument $markdown): View|Response
    {
        if (DocumentationPages::find($page) === null) {
            abort(404);
        }

        return $this->page($page, $markdown);
    }

    private function page(string $slug, MarkdownDocument $markdown): View
    {
        $page = DocumentationPages::find($slug);

        if ($page === null) {
            abort(404);
        }

        return view('documentation.show', [
            'title' => $page['title'],
            'slug' => $slug,
            'pages' => DocumentationPages::all(),
            'content' => $markdown->render($page['file']),
        ]);
    }
}
