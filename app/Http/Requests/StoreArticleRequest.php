<?php

namespace App\Http\Requests;

use App\Enums\ArticleLayout;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\Site;
use App\Support\RemoteTaxonomyValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site
            && ($this->user()?->can('create', [Article::class, $site]) ?? false);
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
            'site_category_id' => ['required', 'integer'],
            'topic_id' => ['required_if:type,blog', 'nullable', 'integer'],
            'author_id' => ['required', 'integer'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'featured_image_url' => ['nullable', 'url', 'max:2048'],
            'content' => ['required', 'string'],
            'layout' => ['required_if:type,faq', Rule::enum(ArticleLayout::class)],
            'status' => ['required', Rule::enum(ArticleStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $site = $this->route('site');

        if (! $site instanceof Site) {
            return;
        }

        $validator->after(function (Validator $validator) use ($site): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            app(RemoteTaxonomyValidator::class)->validateArticleTaxonomy($validator, $site);
        });
    }
}
