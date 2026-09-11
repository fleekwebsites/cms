const typeSelect = document.querySelector('#type');
const faqLayoutField = document.querySelector('#faq-layout-field');
const layoutSelect = document.querySelector('#layout');
const categorySelect = document.querySelector('#site_category_id');
const topicSelect = document.querySelector('#topic_id');
const topicField = document.querySelector('#topic-field');
const addCategoryButton = document.querySelector('[data-add-category]');
const addTopicButton = document.querySelector('[data-add-topic]');
const newCategoryInput = document.querySelector('#new_category_name');
const newTopicInput = document.querySelector('#new_topic_name');

function toggleFaqFields() {
    if (! typeSelect || ! faqLayoutField) {
        return;
    }

    const isFaq = typeSelect.value === 'faq';
    faqLayoutField.classList.toggle('hidden', ! isFaq);

    if (topicField instanceof HTMLElement) {
        topicField.classList.toggle('hidden', isFaq);
    }

    if (layoutSelect instanceof HTMLSelectElement) {
        layoutSelect.required = isFaq;
        layoutSelect.disabled = ! isFaq;
    }

    if (topicSelect instanceof HTMLSelectElement) {
        topicSelect.required = ! isFaq;
        topicSelect.disabled = isFaq;
    }
}

function errorMessage(error) {
    const data = error.response?.data;

    if (data?.errors) {
        const first = Object.values(data.errors)[0];

        if (Array.isArray(first) && first[0]) {
            return first[0];
        }
    }

    return data?.message ?? 'Unable to save that name.';
}

function appendOption(select, item, selected = true) {
    const option = document.createElement('option');
    option.value = String(item.id);
    option.textContent = item.name;
    option.selected = selected;

    const placeholder = select.querySelector('option[value=""]');

    if (placeholder && select.options.length === 1) {
        placeholder.textContent = 'Select an option';
    }

    select.appendChild(option);
    select.value = String(item.id);
}

function setTopicOptions(placeholder, items, selectedId = null) {
    if (! (topicSelect instanceof HTMLSelectElement)) {
        return;
    }

    topicSelect.innerHTML = '';

    const placeholderOption = document.createElement('option');
    placeholderOption.value = '';
    placeholderOption.textContent = placeholder;
    topicSelect.appendChild(placeholderOption);

    items.forEach((item) => {
        const option = document.createElement('option');
        option.value = String(item.id);
        option.textContent = item.name;

        if (selectedId !== null && String(selectedId) === String(item.id)) {
            option.selected = true;
        }

        topicSelect.appendChild(option);
    });
}

async function loadTopics(categoryId, selectedId = null) {
    if (! (topicSelect instanceof HTMLSelectElement)) {
        return;
    }

    if (! categoryId || (typeSelect instanceof HTMLSelectElement && typeSelect.value === 'faq')) {
        setTopicOptions('Select a category first', []);
        topicSelect.required = false;
        return;
    }

    const template = categorySelect?.dataset.topicsUrl ?? '';
    const url = template.replace('__CATEGORY__', String(categoryId));

    try {
        const response = await window.axios.get(url);
        const placeholder = response.data.length === 0 ? 'No topics for this category' : 'Select a topic';
        const selected = selectedId ?? topicSelect.dataset.selected ?? null;
        setTopicOptions(placeholder, response.data, selected);
        topicSelect.required = typeSelect instanceof HTMLSelectElement ? typeSelect.value !== 'faq' : true;
    } catch (error) {
        setTopicOptions('Unable to load topics', []);
        console.error(error);
    }
}

async function addCategory() {
    if (! (categorySelect instanceof HTMLSelectElement) || ! (newCategoryInput instanceof HTMLInputElement)) {
        return;
    }

    const name = newCategoryInput.value.trim();

    if (name === '') {
        newCategoryInput.focus();
        return;
    }

    try {
        const created = await window.axios.post(categorySelect.dataset.createUrl ?? '', { name });
        appendOption(categorySelect, created.data);
        newCategoryInput.value = '';
        if (topicSelect instanceof HTMLSelectElement) {
            topicSelect.dataset.selected = '';
        }
        await loadTopics(created.data.id);
    } catch (error) {
        window.alert(errorMessage(error));
        console.error(error);
    }
}

async function addTopic() {
    if (! (topicSelect instanceof HTMLSelectElement) || ! (categorySelect instanceof HTMLSelectElement) || ! (newTopicInput instanceof HTMLInputElement)) {
        return;
    }

    if (! categorySelect.value) {
        window.alert('Select a category before adding a topic.');
        return;
    }

    const name = newTopicInput.value.trim();

    if (name === '') {
        newTopicInput.focus();
        return;
    }

    try {
        const created = await window.axios.post(topicSelect.dataset.createUrl ?? '', {
            name,
            site_category_id: categorySelect.value,
        });
        appendOption(topicSelect, created.data);
        newTopicInput.value = '';
    } catch (error) {
        window.alert(errorMessage(error));
        console.error(error);
    }
}

if (typeSelect instanceof HTMLSelectElement) {
    typeSelect.addEventListener('change', toggleFaqFields);
    toggleFaqFields();
}

if (categorySelect instanceof HTMLSelectElement) {
    categorySelect.addEventListener('change', () => {
        if (topicSelect instanceof HTMLSelectElement) {
            topicSelect.dataset.selected = '';
        }

        loadTopics(categorySelect.value);
    });

    if (categorySelect.value) {
        loadTopics(categorySelect.value, topicSelect instanceof HTMLSelectElement ? topicSelect.dataset.selected : null);
    }
}

addCategoryButton?.addEventListener('click', (event) => {
    event.preventDefault();
    addCategory();
});

addTopicButton?.addEventListener('click', (event) => {
    event.preventDefault();
    addTopic();
});

newCategoryInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        addCategory();
    }
});

newTopicInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        addTopic();
    }
});

const statusSelect = document.querySelector('#status');
const statusHelpItems = document.querySelectorAll('[data-status-help]');

function toggleStatusHelp() {
    if (! statusSelect || statusHelpItems.length === 0) {
        return;
    }

    statusHelpItems.forEach((item) => {
        item.classList.toggle('hidden', item.getAttribute('data-status-help') !== statusSelect.value);
    });
}

statusSelect?.addEventListener('change', toggleStatusHelp);
toggleStatusHelp();
