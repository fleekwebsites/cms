<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleImageRequest;
use Illuminate\Http\JsonResponse;

class ArticleImageController extends Controller
{
    public function __invoke(StoreArticleImageRequest $request): JsonResponse
    {
        $path = $request->file('file')->store('article-images', 'public');

        return response()->json([
            'location' => asset('storage/'.$path),
        ]);
    }
}
