{{--
    On-page messages for a form with data-inline-validation (and novalidate): the browser's own warning
    bubble is often lost (smooth scrolling drops it, and a required field in a hidden section cannot show
    one at all), so a field that is missing or wrong says why right under it, the form lists every
    problem at the top, and the first visible one is scrolled to. Included once by each staff layout.
--}}
@once
<script>
    (function () {
        const errorClass = ['!border-rose-400', '!bg-rose-50/60'];

        function labelFor(field) {
            const label = field.id && field.form.querySelector('label[for="' + CSS.escape(field.id) + '"]');
            const text = (label ? label.textContent : (field.getAttribute('aria-label') || field.name || 'This field'))
                .replace(/\*/g, '').replace(/\s+/g, ' ').trim();
            return text || 'This field';
        }

        function clearField(field) {
            field.classList.remove(...errorClass);
            field.removeAttribute('aria-invalid');
            const note = field.parentElement && field.parentElement.querySelector(':scope > [data-inline-error="' + CSS.escape(field.name) + '"]');
            if (note) note.remove();
        }

        function showField(field) {
            clearField(field);
            field.classList.add(...errorClass);
            field.setAttribute('aria-invalid', 'true');
            const note = document.createElement('p');
            note.dataset.inlineError = field.name;
            note.className = 'mt-1 text-[11px] font-semibold text-rose-600';
            note.textContent = field.validationMessage;
            field.insertAdjacentElement('afterend', note);
        }

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.matches('[data-inline-validation]')) return;

            form.querySelectorAll('[data-inline-summary]').forEach(node => node.remove());
            const invalid = Array.from(form.elements).filter(field => field.willValidate && !field.checkValidity());
            Array.from(form.elements).forEach(field => { if (field.willValidate) clearField(field); });
            if (!invalid.length) return;

            event.preventDefault();

            // One note per field (radio groups share a name), and a list at the top that also covers hidden ones.
            const seen = new Set();
            const problems = [];
            invalid.forEach(field => {
                if (seen.has(field.name)) return;
                seen.add(field.name);
                if (field.type !== 'radio' && field.type !== 'checkbox') showField(field);
                problems.push(labelFor(field) + ': ' + field.validationMessage);
            });

            const summary = document.createElement('div');
            summary.dataset.inlineSummary = '';
            summary.setAttribute('role', 'alert');
            summary.className = 'p-4 rounded-xl bg-rose-50 text-rose-800 text-sm border border-rose-200';
            const heading = document.createElement('p');
            heading.className = 'font-bold';
            heading.textContent = 'Please check the following before saving:';
            const list = document.createElement('ul');
            list.className = 'mt-1 list-disc list-inside';
            problems.forEach(text => { const item = document.createElement('li'); item.textContent = text; list.appendChild(item); });
            summary.append(heading, list);
            form.prepend(summary);

            const first = invalid.find(field => field.offsetParent !== null);
            (first || summary).scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (first) first.focus({ preventScroll: true });
        }, true);

        // A field put right loses its message as the staff member types.
        ['input', 'change'].forEach(type => document.addEventListener(type, function (event) {
            const field = event.target;
            if (field.form && field.form.matches('[data-inline-validation]') && field.willValidate && field.checkValidity()) clearField(field);
        }));
    })();
</script>
@endonce
