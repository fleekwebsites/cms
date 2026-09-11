<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Models\SiteCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreSiteCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site
            && ($this->user()?->can('create', [SiteCategory::class, $site]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
        ];
    }
}
