{{--
    Forms marked data-uppercase take their text in capitals as it is typed, so every record reads the
    same (names, passport and ID numbers, addresses, remarks). Emails, passwords, dates, numbers and
    file inputs are left as they are, and so is any field marked data-keep-case (e.g. the part of a
    staff email typed before the @).
--}}
@once
    <style>
        form[data-uppercase] input[type="text"]:not([data-keep-case]), form[data-uppercase] input:not([type]):not([data-keep-case]), form[data-uppercase] textarea:not([data-keep-case]) { text-transform: uppercase; }
        form[data-uppercase] input[type="text"]::placeholder, form[data-uppercase] textarea::placeholder { text-transform: none; }
    </style>
    <script>
        document.addEventListener('input', (event) => {
            const field = event.target;
            if (!field.closest || !field.closest('form[data-uppercase]')) return;
            const isText = field.tagName === 'TEXTAREA' || (field.tagName === 'INPUT' && (field.type === 'text' || !field.getAttribute('type')));
            if (!isText || field.dataset.keepCase !== undefined) return;
            const upper = field.value.toUpperCase();
            if (upper === field.value) return;
            const start = field.selectionStart, end = field.selectionEnd;
            field.value = upper;
            try { field.setSelectionRange(start, end); } catch (e) { /* not every input keeps a caret */ }
        }, true);

        // What was already filled in (a copied profile, or a form sent back with errors) is set in capitals too.
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!form.matches || !form.matches('form[data-uppercase]')) return;
            form.querySelectorAll('input[type="text"], input:not([type]), textarea').forEach((field) => {
                if (field.dataset.keepCase === undefined) field.value = field.value.toUpperCase();
            });
        }, true);
    </script>
@endonce
