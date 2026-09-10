import Quill, { Delta } from 'quill';
import 'quill/dist/quill.snow.css';
import { hasOnlyQuillManagedStyles, prepareHtmlForEditor } from './editor-html';
import './html-block';
import './html-inline';

const AlignStyle = Quill.import('attributors/style/align');
const BackgroundStyle = Quill.import('attributors/style/background');
const ColorStyle = Quill.import('attributors/style/color');
const DirectionStyle = Quill.import('attributors/style/direction');
const FontStyle = Quill.import('attributors/style/font');
const SizeStyle = Quill.import('attributors/style/size');

FontStyle.whitelist = null;
SizeStyle.whitelist = null;

Quill.register(AlignStyle, true);
Quill.register(BackgroundStyle, true);
Quill.register(ColorStyle, true);
Quill.register(DirectionStyle, true);
Quill.register(FontStyle, true);
Quill.register(SizeStyle, true);

const FONT_FAMILIES = [false, 'serif', 'monospace', 'arial', 'georgia', 'tahoma', 'impact'];
const FONT_SIZES = ['10px', '12px', '14px', false, '16px', '18px', '24px', '32px', '48px'];
const PICKER_FORMATS = ['color', 'background', 'font', 'size', 'header', 'align'];

const contentInput = document.querySelector('#content');
const editorElement = document.querySelector('#content-editor');

if (contentInput instanceof HTMLTextAreaElement && editorElement instanceof HTMLElement) {
    const quill = new Quill(editorElement, {
        theme: 'snow',
        modules: {
            table: true,
            toolbar: {
                container: [
                    [{ header: [1, 2, 3, 4, 5, 6, false] }],
                    [{ font: FONT_FAMILIES }, { size: FONT_SIZES }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ color: [] }, { background: [] }],
                    [{ script: 'sub' }, { script: 'super' }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'code-block', 'table'],
                    [{ indent: '-1' }, { indent: '+1' }],
                    [{ align: [] }, { direction: 'rtl' }],
                    ['link', 'image'],
                    ['clean'],
                ],
                handlers: {
                    image: imageHandler,
                    table: tableHandler,
                },
            },
            clipboard: {
                matchVisual: false,
            },
        },
        placeholder: 'Write your article content here...',
    });

    preserveEmbedTags(quill);
    preserveUnsupportedInlineTags(quill);

    function syncContent() {
        contentInput.value = quill.getSemanticHTML();
    }

    preserveToolbarSelection(quill, syncContent);

    if (contentInput.value.trim() !== '') {
        const delta = quill.clipboard.convert({ html: prepareHtmlForEditor(contentInput.value) });
        quill.setContents(delta, 'silent');
    }

    quill.on('text-change', syncContent);

    const form = contentInput.closest('form');

    if (form) {
        form.addEventListener('submit', syncContent);
    }

    function preserveEmbedTags(editor) {
        const embedTags = [
            'iframe', 'video', 'audio', 'picture', 'table', 'figure',
            'dl', 'details', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav',
        ];

        embedTags.forEach((tag) => {
            if (tag === 'table') {
                editor.clipboard.addMatcher(tag, (node, delta) => {
                    if (tableHasCustomStyles(node)) {
                        return new Delta().insert({ 'html-block': node.outerHTML });
                    }

                    return delta;
                });

                return;
            }

            editor.clipboard.addMatcher(tag, (node) => {
                return new Delta().insert({ 'html-block': node.outerHTML });
            });
        });
    }

    function tableHasCustomStyles(table) {
        if (table.hasAttribute('style')) {
            return true;
        }

        return table.querySelector('[style]') !== null;
    }

    function preserveUnsupportedInlineTags(editor) {
        const preserveInline = (node) => new Delta().insert({ 'html-inline': node.outerHTML });

        editor.clipboard.addMatcher('span[style]', (node, delta) => {
            if (shouldPreserveStyledSpan(node)) {
                return preserveInline(node);
            }

            return delta;
        });

        editor.clipboard.addMatcher('span[class]', (node, delta) => {
            if (shouldPreserveStyledSpan(node)) {
                return preserveInline(node);
            }

            return delta;
        });

        editor.clipboard.addMatcher('font[color]', preserveInline);
        editor.clipboard.addMatcher('font[size]', preserveInline);

        ['MARK', 'SMALL', 'KBD', 'ABBR', 'CITE', 'DFN'].forEach((tag) => {
            editor.clipboard.addMatcher(tag, preserveInline);
        });

        ['STRONG', 'B', 'EM', 'I', 'U', 'S'].forEach((tag) => {
            editor.clipboard.addMatcher(tag, (node, delta) => {
                if (node instanceof HTMLElement && hasOnlyQuillManagedStyles(node)) {
                    return delta;
                }

                if (shouldPreserveStyledSpan(node)) {
                    return preserveInline(node);
                }

                return delta;
            });
        });
    }

    function shouldPreserveStyledSpan(node) {
        if (!(node instanceof HTMLElement)) {
            return false;
        }

        if (node.classList.contains('ql-html-inline')) {
            return false;
        }

        if (hasOnlyQuillManagedStyles(node)) {
            return false;
        }

        if (node.hasAttribute('color') || node.hasAttribute('size')) {
            return true;
        }

        const className = node.getAttribute('class') || '';

        if (className !== '' && !/(^|\s)ql-/.test(className)) {
            return true;
        }

        const style = (node.getAttribute('style') || '').trim();

        return style !== '';
    }

    function preserveToolbarSelection(editor, onFormatted) {
        const toolbar = editor.getModule('toolbar');

        if (! toolbar?.container) {
            return;
        }

        let savedRange = editor.getSelection();

        editor.on('selection-change', (range) => {
            if (range) {
                savedRange = range;
            }
        });

        toolbar.container.addEventListener('mousedown', (event) => {
            const currentRange = editor.getSelection();

            if (currentRange) {
                savedRange = currentRange;
            }

            if (event.target.closest('.ql-picker, button, select')) {
                event.preventDefault();
            }
        }, true);

        PICKER_FORMATS.forEach((format) => {
            toolbar.addHandler(format, (value) => {
                applyFormatToSavedRange(editor, format, value, savedRange, onFormatted);
            });
        });
    }

    function resolveFormattingRange(editor, savedRange) {
        const currentRange = editor.getSelection();

        if (currentRange && currentRange.length > 0) {
            return currentRange;
        }

        if (savedRange && savedRange.length > 0) {
            return savedRange;
        }

        return currentRange || savedRange;
    }

    function applyFormatToSavedRange(editor, format, value, savedRange, onFormatted) {
        const range = resolveFormattingRange(editor, savedRange);
        const appliedValue = value === false || value == null || value === '' ? false : value;

        if (range) {
            editor.setSelection(range.index, range.length, Quill.sources.SILENT);
        }

        editor.format(format, appliedValue, Quill.sources.USER);
        onFormatted();
    }

    function tableHandler() {
        const input = window.prompt('Table size (rows x columns):', '3x3');

        if (! input) {
            return;
        }

        const match = input.trim().match(/^(\d+)\s*[x×]\s*(\d+)$/i);

        if (! match) {
            window.alert('Enter the table size like 3x4.');

            return;
        }

        const rows = Number(match[1]);
        const columns = Number(match[2]);

        if (! Number.isInteger(rows) || ! Number.isInteger(columns) || rows < 1 || columns < 1 || rows > 20 || columns > 10) {
            window.alert('Choose between 1 and 20 rows and 1 and 10 columns.');

            return;
        }

        quill.getModule('table').insertTable(rows, columns);
        syncContent();
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
            syncContent();
        };
    }
}
