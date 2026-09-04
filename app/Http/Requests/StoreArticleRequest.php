<?php

namespace App\Http\Requests;

use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\AuthorName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Article::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ArticleType::class)],
            'title' => ['required', 'string', 'max:60'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'keywords' => ['nullable', 'string', 'max:5000'],
            'author_name_id' => [
                'nullable',
                'integer',
                Rule::exists(AuthorName::class, 'id')->where('user_id', $this->user()?->id),
            ],
            'new_author_name' => ['nullable', 'string', 'max:255'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'featured_image_url' => ['nullable', 'url', 'max:2048'],
            'content' => ['required', 'string'],
            'layout' => ['required', Rule::enum(ArticleLayout::class)],
            'status' => ['required', Rule::enum(ArticleStatus::class)],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => ['integer', Rule::exists('sites', 'id')->where('is_active', true)],
        ];
    }
}
