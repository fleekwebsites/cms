<?php

namespace App\Http\Requests;

use App\Models\Author;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $author = $this->route('author');

        return $author instanceof Author
            && ($this->user()?->can('update', $author) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Author $author */
        $author = $this->route('author');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('authors', 'name')
                    ->where('site_id', $author->site_id)
                    ->ignore($author->id),
            ],
            'credentials' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
