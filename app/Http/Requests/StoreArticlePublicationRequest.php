<?php

namespace App\Http\Requests;

use App\Models\Article;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

class StoreArticlePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site
            && ($this->user()?->can('publish', [Article::class, $site]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
