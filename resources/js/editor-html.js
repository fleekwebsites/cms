const QUILL_MANAGED_STYLE_PROPERTIES = new Set([
    'color',
    'background-color',
    'background',
    'font-size',
    'font-family',
    'text-align',
    'direction',
]);

const BLOCK_TAGS = new Set([
    'P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
    'UL', 'OL', 'LI', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TD', 'TH',
    'BLOCKQUOTE', 'PRE', 'HR', 'IFRAME', 'VIDEO', 'AUDIO', 'PICTURE', 'FIGURE',
    'DL', 'DT', 'DD', 'DETAILS', 'SECTION', 'ARTICLE', 'ASIDE', 'HEADER', 'FOOTER', 'MAIN', 'NAV',
]);

export function decodeEscapedHtml(html) {
    const patterns = [
        /&lt;((?:\/\s*)?[\w-]+(?:\s+(?:[^<&]|&(?:quot|amp|lt|gt|#\d+);)*)?\s*(?:\/\s*)?)&gt;/gi,
        /&#(?:x3c|60);((?:\/\s*)?[\w-]+(?:\s+(?:[^<&]|&(?:quot|amp|lt|gt|#\d+);)*)?\s*(?:\/\s*)?)&#(?:x3e|62);/gi,
    ];

    let previous = null;

    while (previous !== html) {
        previous = html;

        for (const pattern of patterns) {
            html = html.replace(pattern, (_, tag) => `<${tag}>`);
        }
    }

    return html;
}

function stylePropertyNames(style) {
    return style
        .split(';')
        .map((declaration) => declaration.trim())
        .filter((declaration) => declaration !== '')
        .map((declaration) => declaration.split(':')[0]?.trim().toLowerCase())
        .filter((property) => property !== undefined && property !== '');
}

export function hasOnlyQuillManagedStyles(element) {
    const style = (element.getAttribute('style') || '').trim();

    if (style === '') {
        return false;
    }

    const properties = stylePropertyNames(style);

    if (properties.length === 0) {
        return false;
    }

    return properties.every((property) => QUILL_MANAGED_STYLE_PROPERTIES.has(property));
}

function isSeparatorText(text) {
    return /^[\s|\\/,;·•\-–—]*$/u.test(text.trim());
}

function mergeEmptyStyledSpans(root) {
    const spans = [...root.querySelectorAll('span[style]')].filter((span) => span.textContent.trim() === '');

    spans.forEach((span) => {
        if (span.textContent.trim() !== '') {
            return;
        }

        let next = span.nextSibling;

        while (next && next.nodeType === Node.TEXT_NODE && next.textContent.trim() === '') {
            next = next.nextSibling;
        }

        if (next && next.nodeType === Node.TEXT_NODE && ! isSeparatorText(next.textContent)) {
            span.appendChild(next);

            return;
        }

        if (next && next.nodeType === Node.ELEMENT_NODE && next.textContent.trim() !== '' && ! isSeparatorText(next.textContent)) {
            while (next.firstChild) {
                span.appendChild(next.firstChild);
            }

            next.remove();
        }
    });
}

function unwrapNoiseSpans(root) {
    const spans = [...root.querySelectorAll('span[class*="qwen-markdown"]')];

    spans
        .sort((left, right) => {
            const depth = (node) => {
                let count = 0;
                let current = node.parentElement;

                while (current) {
                    count++;
                    current = current.parentElement;
                }

                return count;
            };

            return depth(right) - depth(left);
        })
        .forEach((span) => {
            const fragment = document.createDocumentFragment();

            while (span.firstChild) {
                fragment.appendChild(span.firstChild);
            }

            span.replaceWith(fragment);
        });
}

function stripDefaultCanvasStyles(root) {
    const defaultColors = new Set(['#222', '#222222', 'rgb(34, 34, 34)', 'rgb(34,34,34)']);
    const defaultBackgrounds = new Set(['#fff', '#ffffff', 'white', 'rgb(255, 255, 255)', 'rgb(255,255,255)']);

    root.querySelectorAll('[style]').forEach((element) => {
        const kept = (element.getAttribute('style') || '')
            .split(';')
            .map((declaration) => declaration.trim())
            .filter((declaration) => declaration !== '')
            .filter((declaration) => {
                const [property, value] = declaration.split(':').map((part) => part.trim().toLowerCase());

                if (property === 'color' && defaultColors.has(value.replace(/\s+/g, ''))) {
                    return false;
                }

                if ((property === 'background' || property === 'background-color') && defaultBackgrounds.has(value.replace(/\s+/g, ''))) {
                    return false;
                }

                return true;
            });

        if (kept.length === 0) {
            element.removeAttribute('style');
        } else {
            element.setAttribute('style', kept.join('; '));
        }

        if (element.tagName === 'SPAN' && ! element.hasAttribute('style') && ! element.hasAttribute('class') && element.attributes.length === 0) {
            const fragment = document.createDocumentFragment();

            while (element.firstChild) {
                fragment.appendChild(element.firstChild);
            }

            element.replaceWith(fragment);
        }
    });
}

function normalizeInlineMarkup(root) {
    unwrapNoiseSpans(root);
    mergeEmptyStyledSpans(root);
    stripDefaultCanvasStyles(root);
}

function unwrapNativeStyledInlines(html) {
    const doc = new DOMParser().parseFromString(`<div id="editor-root">${html}</div>`, 'text/html');
    const root = doc.getElementById('editor-root');

    if (! root) {
        return html;
    }

    root.querySelectorAll('span.ql-html-inline[data-raw-html]').forEach((wrapper) => {
        const rawHtml = wrapper.getAttribute('data-raw-html') || '';
        const template = doc.createElement('template');
        template.innerHTML = rawHtml.trim();
        const inner = template.content.firstElementChild;

        if (
            inner instanceof HTMLElement
            && ['SPAN', 'STRONG', 'B', 'EM', 'I', 'U', 'S'].includes(inner.tagName)
            && hasOnlyQuillManagedStyles(inner)
        ) {
            wrapper.replaceWith(inner.cloneNode(true));
        }
    });

    return root.innerHTML;
}

export function prepareHtmlForEditor(html) {
    html = decodeEscapedHtml(html.trim());

    if (html === '') {
        return html;
    }

    html = unwrapNativeStyledInlines(html);

    const doc = new DOMParser().parseFromString(`<div id="editor-root">${html}</div>`, 'text/html');
    const root = doc.getElementById('editor-root');

    if (! root) {
        return html;
    }

    normalizeInlineMarkup(root);

    const nodes = Array.from(root.childNodes);

    while (root.firstChild) {
        root.removeChild(root.firstChild);
    }

    const groups = [];
    let current = [];

    const flush = () => {
        if (current.length > 0) {
            groups.push(current);
            current = [];
        }
    };

    for (const node of nodes) {
        if (node.nodeType === Node.ELEMENT_NODE && BLOCK_TAGS.has(node.tagName)) {
            flush();
            groups.push([node]);
            continue;
        }

        if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() === '') {
            continue;
        }

        current.push(node);
    }

    flush();

    for (const group of groups) {
        if (group.length === 1 && group[0].nodeType === Node.ELEMENT_NODE && BLOCK_TAGS.has(group[0].tagName)) {
            root.appendChild(group[0]);
            continue;
        }

        const paragraph = doc.createElement('p');

        for (const node of group) {
            paragraph.appendChild(node);
        }

        root.appendChild(paragraph);
    }

    return root.innerHTML;
}
