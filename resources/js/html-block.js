import Quill from 'quill';
import { BlockEmbed } from 'quill/blots/block';

class HtmlBlock extends BlockEmbed {
    static blotName = 'html-block';

    static className = 'ql-html-block';

    static tagName = 'DIV';

    static create(value) {
        const node = super.create();
        const html = typeof value === 'string' ? value : '';

        node.setAttribute('contenteditable', 'false');
        node.setAttribute('data-raw-html', html);
        node.innerHTML = html;

        return node;
    }

    static value(domNode) {
        return domNode.getAttribute('data-raw-html') || domNode.innerHTML;
    }

    html() {
        return HtmlBlock.value(this.domNode);
    }
}

Quill.register(HtmlBlock);

export default HtmlBlock;
