{{--
    On a form printed from an Immigration folder, every fill-in space left empty reads "N/A", so
    the BI sees it was answered rather than missed. Included only on filled prints, never on blank
    forms. A space for the signer, the notary or the BI's own use is marked data-na-skip (on the
    space or on a block around it) and stays empty.

    It knows each way the forms draw a space: a row of character boxes (.cells), a date in boxes
    (.date-cells, N/A goes in the month), and an underline or framed field (.blank, .fill, .fillin,
    .fill-in, .ln, .boxline, .val, .na).
--}}
<script>
    (function () {
        const LINES = '.blank, .fill, .fillin, .fill-in, .ln, .boxline, .val, .na';
        // A tick box is a choice, not a field: it stays as it is.
        const skipped = (el) => el.closest('[data-na-skip], .tick') !== null;
        const empty = (el) => el.textContent.replace(/[\s ]/g, '') === '' && ! el.querySelector('img, .tick, input');

        function fill() {
            // A date in boxes: N/A in the month boxes.
            document.querySelectorAll('.date-cells').forEach((date) => {
                if (skipped(date) || ! empty(date)) return;
                const groups = date.querySelectorAll('.cells');
                const month = groups[1] || groups[0];
                if (month) ['N', '/', 'A'].forEach((ch, i) => { const cell = month.querySelectorAll('.cell')[i]; if (cell) cell.textContent = ch; });
                date.dataset.naDone = '1';
            });

            // A row of character boxes: one letter per box.
            document.querySelectorAll('.cells').forEach((row) => {
                if (skipped(row) || row.closest('[data-na-done]') || ! empty(row)) return;
                const cells = row.querySelectorAll('.cell');
                if (cells.length < 2) return; // a single box is a tick or a mark, not a field
                ['N', '/', 'A'].forEach((ch, i) => { if (cells[i]) cells[i].textContent = ch; });
            });

            // An underline or a framed field: the innermost one, so a field around a line is filled once.
            document.querySelectorAll(LINES).forEach((line) => {
                if (skipped(line) || line.querySelector(LINES) || line.closest('.cells') || ! empty(line)) return;
                line.textContent = 'N/A';
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fill);
        } else {
            fill();
        }
    })();
</script>
