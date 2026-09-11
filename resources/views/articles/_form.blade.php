@php
    $article ??= null;
    $selectedCategoryId = old('site_category_id', $article?->int('site_category_id'));
    $selectedAuthorId = old('author_id', $article?->int('author_id'));
    $selectedType = old('type', $article?->type()?->value ?? 'blog');
    $canCreateTaxonomy ??= false;
@endphp

<div class="grid gap-6 xl:grid-cols-12">
    <aside class="space-y-6 xl:col-span-4">
        <section class="card space-y-5">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">This site</h2>
                <p class="mt-1 text-sm text-slate-600">Categories, topics, and authors are loaded from the remote site.</p>
            </div>

            <div>
                <p class="label">Site</p>
                <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-900">{{ $site->name }}</p>
            </div>

            <div>
                <label for="site_category_id" class="label">Category</label>
                <select
                    id="site_category_id"
                    name="site_category_id"
                    class="input"
                    required
                    data-create-url="{{ route('sites.categories.store', $site) }}"
                    data-topics-url="{{ str_replace('999999', '__CATEGORY__', route('sites.topics.options', [$site, 999999])) }}"
                    data-can-create="{{ $canCreateTaxonomy ? '1' : '0' }}"
                >
                    @if ($siteCategories->isEmpty())
                        <option value="">No categories on remote site</option>
                    @else
                        <option value="">Select a category</option>
                        @foreach ($siteCategories as $category)
                            <option value="{{ $category->int('id') }}" @selected((string) $selectedCategoryId === (string) $category->int('id'))>
                                {{ $category->string('name') }}
                            </option>
                        @endforeach
                    @endif
                </select>
                @if ($canCreateTaxonomy)
                    <div class="mt-2 flex gap-2">
                        <input id="new_category_name" type="text" maxlength="120" class="input" placeholder="New category name">
                        <button type="button" class="btn-secondary shrink-0" data-add-category>Add</button>
                    </div>
                @endif
            </div>

            <div id="topic-field" @class(['hidden' => $selectedType === 'faq'])>
                <label for="topic_id" class="label">Topic</label>
                <select
                    id="topic_id"
                    name="topic_id"
                    class="input"
                    data-selected="{{ old('topic_id', $article?->int('topic_id')) }}"
                    data-create-url="{{ route('sites.topics.store', $site) }}"
                    data-can-create="{{ $canCreateTaxonomy ? '1' : '0' }}"
                >
                    @if ($selectedType === 'faq')
                        <option value="">Not used for FAQs</option>
                    @elseif (! $selectedCategoryId)
                        <option value="">Select a category first</option>
                    @elseif ($siteTopics->isEmpty())
                        <option value="">No topics for this category</option>
                    @else
                        <option value="">Select a topic</option>
                        @foreach ($siteTopics as $topic)
                            <option value="{{ $topic->int('id') }}" @selected((string) old('topic_id', $article?->int('topic_id')) === (string) $topic->int('id'))>
                                {{ $topic->string('name') }}
                            </option>
                        @endforeach
                    @endif
                </select>
                @if ($canCreateTaxonomy)
                    <div class="mt-2 flex gap-2">
                        <input id="new_topic_name" type="text" maxlength="120" class="input" placeholder="New topic name">
                        <button type="button" class="btn-secondary shrink-0" data-add-topic>Add</button>
                    </div>
                @endif
                <p class="mt-1 text-xs text-slate-500">Topics belong to the selected category and apply to blogs.</p>
            </div>

            <div>
                <label for="author_id" class="label">Author byline</label>
                <select
                    id="author_id"
                    name="author_id"
                    class="input"
                    required
                >
                    @if ($siteAuthors->isEmpty())
                        <option value="">No authors on remote site</option>
                    @else
                        <option value="">Select an author</option>
                        @foreach ($siteAuthors as $author)
                            <option value="{{ $author->int('id') }}" @selected((string) $selectedAuthorId === (string) $author->int('id'))>
                                {{ $author->displayLine() }}
                            </option>
                        @endforeach
                    @endif
                </select>
                <p class="mt-1 text-xs text-slate-500">You are the editor. The author is the published byline.</p>
            </div>
        </section>

        <section class="card space-y-5">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Article details</h2>
            </div>

            <div>
                <label for="type" class="label">Article type</label>
                <select id="type" name="type" class="input" required>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected($selectedType === $type->value)>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="title" class="label">Title</label>
                <input id="title" name="title" type="text" value="{{ old('title', $article?->string('title')) }}" maxlength="60" required class="input">
                <p class="mt-1 text-xs text-slate-500">Maximum 60 characters.</p>
            </div>

            <div>
                <label for="excerpt" class="label">Excerpt</label>
                <textarea id="excerpt" name="excerpt" rows="3" maxlength="2000" class="input">{{ old('excerpt', $article?->string('excerpt')) }}</textarea>
            </div>

            <div>
                <label for="keywords" class="label">Keywords</label>
                <textarea id="keywords" name="keywords" rows="2" maxlength="5000" class="input" placeholder="nursing, exams, study tips">{{ old('keywords', $article?->string('keywords')) }}</textarea>
            </div>

            <div>
                <label for="featured_image" class="label">Featured image</label>
                @if ($article?->string('featured_image_url'))
                    <img src="{{ $article->string('featured_image_url') }}" alt="Featured image" class="mb-3 max-h-40 rounded-lg border border-slate-200">
                @endif
                <input id="featured_image" name="featured_image" type="file" accept="image/*" class="input">
                <input id="featured_image_url" name="featured_image_url" type="url" value="{{ old('featured_image_url', $article?->string('featured_image_url')) }}" class="input mt-2" placeholder="Or paste image URL">
                <p class="mt-1 text-xs text-slate-500">Uploaded to the receiving site when saved (not stored on the CMS).</p>
            </div>

            <div id="faq-layout-field" @class(['hidden' => $selectedType === 'blog'])>
                <label for="layout" class="label">Layout</label>
                <select id="layout" name="layout" class="input">
                    @foreach ($layouts as $layout)
                        <option value="{{ $layout->value }}" @selected(old('layout', $article?->layout()?->value ?? 'default') === $layout->value)>
                            {{ $layout->label() }} — {{ $layout->description() }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Blogs use the default layout on the receiving site.</p>
            </div>
        </section>

        <section class="card space-y-5">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Publishing</h2>
            </div>

            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $article?->status()?->value ?? 'draft') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                <p id="status-help" class="mt-2 text-xs text-slate-500">
                    @foreach ($statuses as $status)
                        <span @class(['hidden' => old('status', $article?->status()?->value ?? 'draft') !== $status->value]) data-status-help="{{ $status->value }}">
                            {{ $status->description() }}
                        </span>
                    @endforeach
                </p>
            </div>

            @if ($article)
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    Estimated reading time: <strong>{{ $article->readingTimeMinutes() }} min</strong>
                </p>
            @else
                <p class="text-xs text-slate-500">Reading time is calculated automatically from the article length when saved.</p>
            @endif
        </section>
    </aside>

    <section class="card xl:col-span-8">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Content</h2>
            <p class="mt-1 text-sm text-slate-600">Saved directly to the remote site when it is reachable.</p>
        </div>

        <textarea
            id="content"
            name="content"
            class="hidden"
            data-upload-url="{{ route('articles.images.store') }}"
            required
        >{{ $editorContent ?? '' }}</textarea>
        <div id="content-editor" class="min-h-[27rem] rounded-lg border border-slate-300 bg-white"></div>
    </section>
</div>
