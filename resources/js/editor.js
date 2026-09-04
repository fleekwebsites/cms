import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const contentInput = document.querySelector('#content');
const editorElement = document.querySelector('#content-editor');

if (contentInput instanceof HTMLTextAreaElement && editorElement instanceof HTMLElement) {
    const quill = new Quill(editorElement, {
        theme: 'snow',
        modules: {
            toolbar: {
                container: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }],
                    ['link', 'image'],
                    ['clean'],
                ],
                handlers: {
                    image: imageHandler,
                },
            },
        },
        placeholder: 'Write your article content here...',
    });

    if (contentInput.value.trim() !== '') {
        quill.root.innerHTML = contentInput.value;
    }

    quill.on('text-change', () => {
        contentInput.value = quill.root.innerHTML;
    });

    const form = contentInput.closest('form');

    if (form) {
        form.addEventListener('submit', () => {
            contentInput.value = quill.root.innerHTML;
        });
    }

    function imageHandler() {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/*');
        input.click();

        input.onchange = async () => {
            const file = input.files?.[0];

            if (! file || ! contentInput.dataset.uploadUrl) {
                return;
            }

            const formData = new FormData();
            formData.append('file', file);

            const response = await window.axios.post(contentInput.dataset.uploadUrl, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', response.data.location);
            quill.setSelection(range.index + 1);
            contentInput.value = quill.root.innerHTML;
        };
    }
}
