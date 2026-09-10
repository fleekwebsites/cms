@php
    $article ??= null;
    $selectedSiteId = old('site_id', $article?->site_id);
    $selectedCategoryId = old('site_category_id', $article?->site_category_id);
    $selectedAuthorId = old('author_id', $article?->author_id);
    $selectedType = old('type', $article?->type?->value ?? 'blog');
    $categoriesUrlTemplate = route('sites.categories.index', ['site' => '__SITE__']);
    $authorsUrlTemplate = route('sites.authors.index', ['site' => '__SITE__']);
@endphp

<div class="grid gap-6 xl:grid-cols-12">
    <aside class="space-y-6 xl:col-span-4">
        <section class="card space-y-5">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Destination</h2>
                <p class="mt-1 text-sm text-slate-600">Choose where this article will be published.</p>
            </div>

            <div>
                <label for="site_id" class="label">Target site</label>
                <select id="site_id" name="site_id" class="input" required>
                    <option value="">Select a site</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected((string) $selectedSiteId === (string) $site->id)>
                            {{ $site->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="site_category_id" class="label">Category</label>
                <select
                    id="site_category_id"
                    name="site_category_id"
                    class="input"
                    data-selected="{{ $selectedCategoryId }}"
                    data-categories-url="{{ $categoriesUrlTemplate }}"
                    required
                >
                    @if ($siteCategories->isEmpty())
                        <option value="">Select a site first</option>
                    @else
                        <option value="">Select a category</option>
                        @foreach ($siteCategories as $category)
                            <option value="{{ $category->id }}" @selected((string) $selectedCategoryId === (string) $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>

            <div>
                <label for="author_id" class="label">Author byline</label>
                <select
                    id="author_id"
                    name="author_id"
                    class="input"
                    data-selected="{{ $selectedAuthorId }}"
                    data-authors-url="{{ $authorsUrlTemplate }}"
                    required
                >
                    @if ($siteAuthors->isEmpty())
                        <option value="">Select a site first</option>
                    @else
                        <option value="">Select an author</option>
                        @foreach ($siteAuthors as $author)
                            <option value="{{ $author->id }}" @selected((string) $selectedAuthorId === (string) $author->id)>
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
                <input id="title" name="title" type="text" value="{{ old('title', $article?->title) }}" maxlength="60" required class="input">
                <p class="mt-1 text-xs text-slate-500">Maximum 60 characters.</p>
            </div>

            <div>
                <label for="excerpt" class="label">Excerpt</label>
                <textarea id="excerpt" name="excerpt" rows="3" maxlength="2000" class="input">{{ old('excerpt', $article?->excerpt) }}</textarea>
            </div>

            <div>
                <label for="keywords" class="label">Keywords</label>
                <textarea id="keywords" name="keywords" rows="2" maxlength="5000" class="input" placeholder="nursing, exams, study tips">{{ old('keywords', $article?->keywords) }}</textarea>
            </div>

            <div>
                <label for="featured_image" class="label">Featured image</label>
                @if ($article?->featured_image_url)
                    <img src="{{ $article->featured_image_url }}" alt="Featured image" class="mb-3 max-h-40 rounded-lg border border-slate-200">
                @endif
                <input id="featured_image" name="featured_image" type="file" accept="image/*" class="input">
                <input id="featured_image_url" name="featured_image_url" type="url" value="{{ old('featured_image_url', $article?->featured_image_url) }}" class="input mt-2" placeholder="Or paste image URL">
                <p class="mt-1 text-xs text-slate-500">Uploaded to the receiving site when published (not linked to the CMS).</p>
            </div>

            <div id="faq-layout-field" @class(['hidden' => $selectedType === 'blog'])>
                <label for="layout" class="label">Layout</label>
                <select id="layout" name="layout" class="input">
                    @foreach ($layouts as $layout)
                        <option value="{{ $layout->value }}" @selected(old('layout', $article?->layout?->value ?? 'default') === $layout->value)>
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
                        <option value="{{ $status->value }}" @selected(old('status', $article?->status?->value ?? 'draft') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if ($article)
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    Estimated reading time: <strong>{{ $article->readingTimeMinutes() }} min</strong> (auto-calculated on save)
                </p>
            @else
                <p class="text-xs text-slate-500">Reading time is calculated automatically from the article length when saved.</p>
            @endif
        </section>
    </aside>

    <section class="card xl:col-span-8">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Content</h2>
            <p class="mt-1 text-sm text-slate-600">Use headings, fonts, sizes, lists, quotes, tables, text color, highlights, subscripts, links, and images. Click the table button and enter a size like 3x4. Images are embedded when published.</p>
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
