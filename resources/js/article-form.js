const siteSelect = document.querySelector('#site_id');
const categorySelect = document.querySelector('#site_category_id');
const authorSelect = document.querySelector('#author_id');
const typeSelect = document.querySelector('#type');
const faqLayoutField = document.querySelector('#faq-layout-field');
const layoutSelect = document.querySelector('#layout');

function replaceSitePlaceholder(template, siteId) {
    return template.replace('__SITE__', String(siteId));
}

function setSelectOptions(select, placeholder, items, labelKey = 'name', selectedId = null) {
    select.innerHTML = '';

    const placeholderOption = document.createElement('option');
    placeholderOption.value = '';
    placeholderOption.textContent = placeholder;
    select.appendChild(placeholderOption);

    items.forEach((item) => {
        const option = document.createElement('option');
        option.value = String(item.id);
        option.textContent = item[labelKey];

        if (selectedId !== null && String(selectedId) === String(item.id)) {
            option.selected = true;
        }

        select.appendChild(option);
    });

    select.disabled = items.length === 0;
}

async function loadCategories(siteId, categorySelect, selectedCategoryId = null) {
    if (! siteId) {
        setSelectOptions(categorySelect, 'Select a site first', []);
        return;
    }

    categorySelect.disabled = true;

    try {
        const url = replaceSitePlaceholder(categorySelect.dataset.categoriesUrl ?? '', siteId);
        const response = await window.axios.get(url);
        const selected = selectedCategoryId ?? categorySelect.dataset.selected ?? null;
        const placeholder = response.data.length === 0 ? 'No categories for this site' : 'Select a category';

        setSelectOptions(categorySelect, placeholder, response.data, 'name', selected);
    } catch (error) {
        setSelectOptions(categorySelect, 'Unable to load categories', []);
        console.error(error);
    }
}

async function loadAuthors(siteId, authorSelect, selectedAuthorId = null) {
    if (! siteId) {
        setSelectOptions(authorSelect, 'Select a site first', []);
        return;
    }

    authorSelect.disabled = true;

    try {
        const url = replaceSitePlaceholder(authorSelect.dataset.authorsUrl ?? '', siteId);
        const response = await window.axios.get(url);
        const selected = selectedAuthorId ?? authorSelect.dataset.selected ?? null;
        const placeholder = response.data.length === 0 ? 'No authors for this site' : 'Select an author';

        setSelectOptions(authorSelect, placeholder, response.data, 'name', selected);
    } catch (error) {
        setSelectOptions(authorSelect, 'Unable to load authors', []);
        console.error(error);
    }
}

async function loadSiteOptions(siteId, { categorySelect, authorSelect }, resetSelection = false) {
    if (resetSelection) {
        categorySelect.dataset.selected = '';
        authorSelect.dataset.selected = '';
    }

    await Promise.all([
        loadCategories(siteId, categorySelect),
        loadAuthors(siteId, authorSelect),
    ]);
}

function toggleFaqFields() {
    if (! typeSelect || ! faqLayoutField) {
        return;
    }

    const isFaq = typeSelect.value === 'faq';
    faqLayoutField.classList.toggle('hidden', ! isFaq);

    if (layoutSelect instanceof HTMLSelectElement) {
        layoutSelect.required = isFaq;
        layoutSelect.disabled = ! isFaq;
    }
}

if (siteSelect instanceof HTMLSelectElement && categorySelect instanceof HTMLSelectElement && authorSelect instanceof HTMLSelectElement) {
    siteSelect.addEventListener('change', () => {
        loadSiteOptions(siteSelect.value, { categorySelect, authorSelect }, true);
    });

    if (siteSelect.value) {
        loadSiteOptions(siteSelect.value, { categorySelect, authorSelect });
    }
}

if (typeSelect instanceof HTMLSelectElement) {
    typeSelect.addEventListener('change', toggleFaqFields);
    toggleFaqFields();
}
