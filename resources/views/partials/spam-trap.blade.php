{{-- Spam traps (App\Support\SpamTrap): people never see or fill "website"; bots fill it, or post seconds after loading the page. --}}
<div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
    <label for="trap-website">Website</label>
    <input type="text" id="trap-website" name="website" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="form_started" value="{{ encrypt(now()->timestamp) }}">
{{-- JS proof-of-work: SHA-256(csrf + '|amega-shield') truncated to 16 hex chars.
     Bots that don't execute the page's JS cannot produce this value. --}}
<input type="hidden" name="_shield" value="">
<script>
(function () {
    var script = document.currentScript;
    var form = script ? script.closest('form') : null;
    var el = form ? form.querySelector('input[name="_shield"]') : null;
    if (!el) return;

    var tokenEl = form.querySelector('input[name="_token"]') || document.querySelector('meta[name="csrf-token"]');
    var token = tokenEl ? (tokenEl.value || tokenEl.content || '') : '';
    if (!token) return;

    function sha256(ascii) {
        function rightRotate(v, a) { return (v >>> a) | (v << (32 - a)); }
        var maxWord = Math.pow(2, 32), i, j, result = '';
        var words = [], asciiBitLength = ascii.length * 8;
        var hash = sha256.h = sha256.h || [], k = sha256.k = sha256.k || [], primeCounter = k.length;
        var isComposite = {};
        for (var candidate = 2; primeCounter < 64; candidate++) {
            if (!isComposite[candidate]) {
                for (i = 0; i < 313; i += candidate) isComposite[i] = candidate;
                hash[primeCounter] = (Math.pow(candidate, 0.5) * maxWord) | 0;
                k[primeCounter++] = (Math.pow(candidate, 1 / 3) * maxWord) | 0;
            }
        }
        ascii += '\x80';
        while (ascii.length % 64 - 56) ascii += '\x00';
        for (i = 0; i < ascii.length; i++) {
            j = ascii.charCodeAt(i);
            words[i >> 2] |= j << ((3 - i) % 4) * 8;
        }
        words[words.length] = (asciiBitLength / maxWord) | 0;
        words[words.length] = asciiBitLength;
        for (j = 0; j < words.length;) {
            var w = words.slice(j, j += 16), oldHash = hash;
            hash = hash.slice(0, 8);
            for (i = 0; i < 64; i++) {
                var w15 = w[i - 15], w2 = w[i - 2];
                var a = hash[0], e = hash[4];
                var temp1 = hash[7] + (rightRotate(e, 6) ^ rightRotate(e, 11) ^ rightRotate(e, 25)) + ((e & hash[5]) ^ ((~e) & hash[6])) + k[i]
                    + (w[i] = (i < 16) ? w[i] : (w[i - 16] + (rightRotate(w15, 7) ^ rightRotate(w15, 18) ^ (w15 >>> 3)) + w[i - 7] + (rightRotate(w2, 17) ^ rightRotate(w2, 19) ^ (w2 >>> 10))) | 0);
                var temp2 = (rightRotate(a, 2) ^ rightRotate(a, 13) ^ rightRotate(a, 22)) + ((a & hash[1]) ^ (a & hash[2]) ^ (hash[1] & hash[2]));
                hash = [(temp1 + temp2) | 0].concat(hash);
                hash[4] = (hash[4] + temp1) | 0;
            }
            for (i = 0; i < 8; i++) hash[i] = (hash[i] + oldHash[i]) | 0;
        }
        for (i = 0; i < 8; i++) {
            for (j = 3; j + 1; j--) {
                var b = (hash[i] >> (j * 8)) & 255;
                result += ((b < 16) ? 0 : '') + b.toString(16);
            }
        }
        return result;
    }

    var shield = sha256(token + '|amega-shield').substring(0, 16);
    el.defaultValue = shield;
    el.value = shield;
})();
</script>
