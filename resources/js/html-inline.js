import Quill from 'quill';
import Embed from 'quill/blots/embed.js';

class HtmlInline extends Embed {
    static blotName = 'html-inline';

    static className = 'ql-html-inline';

    static tagName = 'SPAN';

    static create(value) {
        const node = super.create();
        const html = typeof value === 'string' ? value : '';

        node.setAttribute('data-raw-html', html);

        const template = document.createElement('template');
        template.innerHTML = html.trim();

        const rendered = template.content.firstChild;

        if (rendered) {
            node.appendChild(rendered);
        }

        return node;
    }

    static value(domNode) {
        return domNode.getAttribute('data-raw-html') || domNode.textContent || '';
    }

    html() {
        return HtmlInline.value(this.domNode);
    }
}

Quill.register(HtmlInline);

export default HtmlInline;
