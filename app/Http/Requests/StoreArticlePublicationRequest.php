<?php

namespace App\Http\Requests;

use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticlePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof Article
            && ($this->user()?->can('publish', $article) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_ids' => ['required', 'array', 'min:1'],
            'site_ids.*' => ['integer', Rule::exists('sites', 'id')->where('is_active', true)],
        ];
    }
}
