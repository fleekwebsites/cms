function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }

    return new Promise((resolve, reject) => {
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.top = '0';
        field.style.left = '0';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.focus();
        field.select();
        field.setSelectionRange(0, text.length);

        try {
            const copied = document.execCommand('copy');
            document.body.removeChild(field);

            if (copied) {
                resolve();
            } else {
                reject(new Error('Copy command was rejected.'));
            }
        } catch (error) {
            document.body.removeChild(field);
            reject(error);
        }
    });
}

document.querySelectorAll('[data-copy-target]').forEach((button) => {
    button.addEventListener('click', async (event) => {
        event.preventDefault();
        event.stopPropagation();

        const target = document.getElementById(button.getAttribute('data-copy-target') ?? '');

        if (! target) {
            return;
        }

        const label = button.getAttribute('aria-label') ?? 'Copy';

        try {
            await copyText(target.textContent.trim());
            button.setAttribute('aria-label', 'Copied');
            button.classList.add('border-emerald-300', 'text-emerald-700');
            window.setTimeout(() => {
                button.setAttribute('aria-label', label);
                button.classList.remove('border-emerald-300', 'text-emerald-700');
            }, 1500);
        } catch (error) {
            window.alert('Could not copy the API key. Select it and copy manually.');
            console.error(error);
        }
    });
});
