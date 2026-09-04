@php
    $article ??= null;
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <div class="card space-y-5">
        <div>
            <label for="type" class="label">Article type</label>
            <select id="type" name="type" class="input" required>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $article?->type?->value) === $type->value)>
                        {{ $type->label() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="title" class="label">Title</label>
            <input id="title" name="title" type="text" value="{{ old('title', $article?->title) }}" maxlength="60" required class="input">
            <p class="mt-1 text-xs text-slate-500">Maximum 60 characters for remote site compatibility.</p>
        </div>

        <div>
            <label for="excerpt" class="label">Excerpt</label>
            <textarea id="excerpt" name="excerpt" rows="4" maxlength="2000" class="input">{{ old('excerpt', $article?->excerpt) }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Meta description, up to 2000 characters.</p>
        </div>

        <div>
            <label for="keywords" class="label">Keywords</label>
            <textarea id="keywords" name="keywords" rows="3" maxlength="5000" class="input" placeholder="nursing, exams, study tips">{{ old('keywords', $article?->keywords) }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Comma-separated SEO keywords, up to 5000 characters.</p>
        </div>

        <div>
            <label for="author_name_id" class="label">Author name</label>
            <select id="author_name_id" name="author_name_id" class="input">
                <option value="">Use account name ({{ $article?->user->name ?? auth()->user()->name }})</option>
                @foreach ($authorNames as $authorName)
                    <option
                        value="{{ $authorName->id }}"
                        @selected((string) old('author_name_id', $article?->author_name_id) === (string) $authorName->id)
                    >
                        {{ $authorName->name }}
                    </option>
                @endforeach
            </select>
            <label for="new_author_name" class="label mt-3">Or add a new author name</label>
            <input
                id="new_author_name"
                name="new_author_name"
                type="text"
                value="{{ old('new_author_name') }}"
                class="input"
                placeholder="Carol Smith"
            >
            <p class="mt-1 text-xs text-slate-500">Pen names are saved to your list for reuse on future articles.</p>
        </div>

        <div>
            <label for="featured_image" class="label">Featured image</label>
            @if ($article?->featured_image_url)
                <img src="{{ $article->featured_image_url }}" alt="Featured image" class="mb-3 max-h-40 rounded-lg border border-slate-200">
            @endif
            <input id="featured_image" name="featured_image" type="file" accept="image/*" class="input">
            <p class="mt-2 text-xs text-slate-500">Or provide an image URL:</p>
            <input id="featured_image_url" name="featured_image_url" type="url" value="{{ old('featured_image_url', $article?->featured_image_url) }}" class="input mt-2" placeholder="https://example.com/image.jpg">
        </div>

        <div>
            <label for="layout" class="label">Layout</label>
            <select id="layout" name="layout" class="input" required>
                @foreach ($layouts as $layout)
                    <option value="{{ $layout->value }}" @selected(old('layout', $article?->layout?->value) === $layout->value)>
                        {{ $layout->label() }} - {{ $layout->description() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="label">Status</label>
            <select id="status" name="status" class="input" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $article?->status?->value ?? 'draft') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card space-y-5">
        <div>
            <label for="content" class="label">Content</label>
            <textarea
                id="content"
                name="content"
                class="hidden"
                data-upload-url="{{ route('articles.images.store') }}"
                required
            >{{ old('content', $article?->content) }}</textarea>
            <div id="content-editor" class="min-h-80 rounded-lg border border-slate-300 bg-white"></div>
            <p class="mt-2 text-xs text-slate-500">Format text with the toolbar. Content is saved as HTML markup.</p>
        </div>

        @if ($sites->isNotEmpty())
            <div>
                <p class="label">Publish to sites when status is Published</p>
                <div class="space-y-2">
                    @foreach ($sites as $site)
                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3">
                            <input
                                type="checkbox"
                                name="site_ids[]"
                                value="{{ $site->id }}"
                                @checked(collect(old('site_ids', []))->contains($site->id))
                                class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            >
                            <span>
                                <span class="block font-medium text-slate-900">{{ $site->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $site->api_endpoint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
