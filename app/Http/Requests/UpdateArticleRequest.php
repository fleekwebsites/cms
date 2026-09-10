<?php

namespace App\Http\Requests;

use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof Article
            && ($this->user()?->can('update', $article) ?? false);
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
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')->where('is_active', true)],
            'site_category_id' => [
                'required',
                'integer',
                Rule::exists('site_categories', 'id')->where(fn ($query) => $query->where('site_id', $this->integer('site_id'))),
            ],
            'author_id' => [
                'required',
                'integer',
                Rule::exists('authors', 'id')->where(fn ($query) => $query->where('site_id', $this->integer('site_id'))),
            ],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'featured_image_url' => ['nullable', 'url', 'max:2048'],
            'content' => ['required', 'string'],
            'layout' => ['required_if:type,faq', Rule::enum(ArticleLayout::class)],
            'status' => ['required', Rule::enum(ArticleStatus::class)],
        ];
    }
}
