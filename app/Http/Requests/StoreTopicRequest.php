<?php

namespace App\Http\Requests;

use App\Enums\RemoteResource;
use App\Models\Site;
use App\Models\Topic;
use App\Support\RemoteIdMapper;
use App\Support\RemoteTaxonomyValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site
            && ($this->user()?->can('create', [Topic::class, $site]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_category_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
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

            $categoryId = (int) $validator->getData()['site_category_id'];
            $resolvedCategoryId = app(RemoteIdMapper::class)->resolveRemoteId(
                $site,
                RemoteResource::Categories,
                $categoryId,
            );

            if (! in_array($resolvedCategoryId, app(RemoteTaxonomyValidator::class)->categoryIds($site), true)) {
                $validator->errors()->add('site_category_id', 'The selected category is invalid for this site.');
            }
        });
    }
}
