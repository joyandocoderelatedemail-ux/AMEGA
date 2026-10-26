/*
 * Polaroid line carousel: instant photos pegged to a sagging string. Drag the line (or use the
 * arrows, a print, ←/→) and the prints slide along it, swinging on their pegs and settling back;
 * at rest they sway in a light breeze. The one in the middle is the one being looked at.
 *
 * A plain-JavaScript port of the "Polaroid Line Carousel" React component, so the public site needs
 * no framework for it. It works on markup rendered by Blade (partials/gallery.blade.php):
 *
 *   <div data-polaroid-carousel data-autoplay="4500">
 *     <svg class="pl-string"><path/></svg>
 *     <div class="pl-card" data-title="…" data-caption="…"><div class="pl-print">…<img data-src="…"></div>…</div>
 *     <div class="pl-bar">… [data-pl-title] [data-pl-caption] [data-pl-index] [data-pl-prev] [data-pl-next]</div>
 *   </div>
 *
 * Kept light: the animation runs only while the carousel is on screen, photos load only when their
 * print comes near the middle, and with reduced motion the prints hold still.
 */

const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
const pad2 = (n) => (n < 10 ? '0' + n : String(n));

/** Height of a string sagging by `sag` between (0, y0) and (w, y0), at x. */
const stringY = (x, w, y0, sag) => {
    const t = clamp(x / w, 0, 1);
    return y0 + 4 * sag * t * (1 - t);
};

/** One step of a critically damped spring toward the target. */
const springStep = (x, v, target, dt, k = 70) => {
    const c = 2 * Math.sqrt(k);
    const nv = v + (k * (target - x) - c * v) * dt;
    return [x + nv * dt, nv];
};

/** One step of a print's swing: it lags behind the line's motion, gravity pulls it back, it is damped. */
const swingStep = (a, w, lineVel, dt, gain) => {
    const nw = w + (-38 * a - 4.2 * w + lineVel * 0.0034 * gain) * dt;
    return [clamp(a + nw * dt, -0.6, 0.6), nw];
};

const nearestAt = (off, spacing, n) => clamp(Math.round(off / spacing), 0, Math.max(0, n - 1));

/** Ease in and out, so a glide starts and lands gently. */
const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

/** How many prints either side of the middle get their photo loaded. */
const LOAD_AHEAD = 3;

function mount(root) {
    const cards = Array.from(root.querySelectorAll('.pl-card'));
    const n = cards.length;
    if (!n) return;

    const path = root.querySelector('.pl-string path');
    const titleEl = root.querySelector('[data-pl-title]');
    const captionEl = root.querySelector('[data-pl-caption]');
    const indexEl = root.querySelector('[data-pl-index]');
    const liveEl = root.querySelector('[data-pl-live]');
    const autoplay = Number(root.dataset.autoplay || 0);
    const baseCardWidth = Number(root.dataset.cardWidth || 260);
    const sag = Number(root.dataset.sag || 46);
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const swing = reduce ? 0 : Number(root.dataset.swing || 1);
    // How long a move to another photo (arrows, autoplay, a click) takes, in ms.
    const glideMs = reduce ? 400 : Number(root.dataset.glide || 3500);

    const sim = { off: 0, vel: 0, target: 0, glide: null, a: new Array(n).fill(0), w: new Array(n).fill(0) };
    let size = { w: 1200, h: 600, cw: baseCardWidth };
    let spacing = size.cw * 1.08;
    let active = -1;
    let drag = null;
    let lastTouch = 0;
    let visible = false;
    let raf = 0;
    let prev = performance.now();

    function start() {
        if (!raf && visible) {
            prev = performance.now();
            raf = requestAnimationFrame(frame);
        }
    }

    // Glide to a photo over glideMs; after a drag (glide false) the line springs there instead.
    const goTo = (i, glide = true) => {
        sim.target = clamp(i, 0, n - 1) * spacing;
        sim.glide = glide && Math.abs(sim.target - sim.off) > 0.5
            ? { from: sim.off, start: performance.now() }
            : null;
        start();
    };

    const loadNear = (center) => {
        for (let i = Math.max(0, center - LOAD_AHEAD); i <= Math.min(n - 1, center + LOAD_AHEAD); i++) {
            const img = cards[i].querySelector('img[data-src]');
            if (img) {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            }
        }
    };

    const setActive = (i) => {
        if (i === active) return;
        active = i;
        cards.forEach((card, k) => {
            card.dataset.on = k === i ? '1' : '0';
            card.setAttribute('aria-hidden', k === i ? 'false' : 'true');
        });
        const card = cards[i];
        if (titleEl) titleEl.textContent = card.dataset.title || '';
        if (captionEl) captionEl.textContent = card.dataset.caption || '';
        if (indexEl) indexEl.textContent = pad2(i + 1);
        if (liveEl) liveEl.textContent = 'Photo ' + (i + 1) + ' of ' + n + (card.dataset.title ? ': ' + card.dataset.title : '');
        // Restart the caption's slide-in.
        const cap = root.querySelector('.pl-cap');
        if (cap) {
            cap.classList.remove('pl-cap-in');
            void cap.offsetWidth;
            cap.classList.add('pl-cap-in');
        }
        loadNear(i);
    };

    const measure = () => {
        const r = root.getBoundingClientRect();
        const cw = Math.round(Math.max(150, Math.min(baseCardWidth, r.width * 0.42, r.height * 0.42)));
        size = { w: r.width, h: r.height, cw };
        root.style.setProperty('--pl-cw', cw + 'px');
        const nextSpacing = cw * 1.08;
        // Only a change of print size moves the line (onto the photo in the middle); a resize that
        // keeps the size, such as a phone's address bar, leaves a glide to finish.
        if (nextSpacing === spacing && active >= 0) return;
        const at = Math.max(0, active);
        spacing = nextSpacing;
        sim.target = at * spacing;
        sim.off = at * spacing;
        sim.glide = null;
    };

    const frame = (now) => {
        raf = 0;
        if (!visible) return;
        const dt = Math.min(0.033, (now - prev) / 1000);
        prev = now;
        const { w, h, cw } = size;
        const y0 = Math.max(40, h * 0.16);
        let lineVel;
        if (drag && drag.moved) {
            lineVel = drag.v * 1000;
        } else if (sim.glide) {
            const before = sim.off;
            const t = clamp((now - sim.glide.start) / glideMs, 0, 1);
            sim.off = sim.glide.from + (sim.target - sim.glide.from) * easeInOut(t);
            sim.vel = 0;
            lineVel = -(sim.off - before) / Math.max(dt, 1e-3);
            if (t >= 1) sim.glide = null;
        } else {
            const before = sim.off;
            [sim.off, sim.vel] = springStep(sim.off, sim.vel, sim.target, dt);
            lineVel = -(sim.off - before) / Math.max(dt, 1e-3);
        }
        const near = nearestAt(sim.off, spacing, n);
        for (let i = 0; i < n; i++) {
            const el = cards[i];
            const x = w / 2 + i * spacing - sim.off;
            if (x < -cw * 1.5 || x > w + cw * 1.5) {
                el.style.visibility = 'hidden';
                continue;
            }
            el.style.visibility = 'visible';
            let [a, av] = swingStep(sim.a[i], sim.w[i], lineVel, dt, swing);
            if (swing) a += Math.sin(now / 1300 + i * 1.7) * 0.0009 * swing;
            sim.a[i] = a;
            sim.w[i] = av;
            const y = stringY(x, w, y0, sag) - 6;
            el.style.transform = 'translate(' + (x - cw / 2).toFixed(1) + 'px,' + y.toFixed(1) + 'px) rotate(' + a.toFixed(4) + 'rad)';
            el.style.zIndex = String(i === near ? n + 1 : n - Math.abs(i - near));
        }
        if (path) path.setAttribute('d', 'M0 ' + y0 + ' Q' + w / 2 + ' ' + (y0 + 2 * sag) + ' ' + w + ' ' + y0);
        setActive(near);
        raf = requestAnimationFrame(frame);
    };

    // Only animate while on screen.
    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        start();
    }, { rootMargin: '100px' }).observe(root);
    new ResizeObserver(measure).observe(root);
    measure();
    setActive(0);

    // Autoplay, back to the first after the last; it waits while someone is interacting.
    if (autoplay && n > 1) {
        window.setInterval(() => {
            if (!visible || document.hidden || drag || sim.glide || performance.now() - lastTouch < autoplay) return;
            goTo(active >= n - 1 ? 0 : active + 1);
        }, autoplay);
    }

    // Drag the line, or click a print to bring it to the middle (clicking the middle one opens it).
    root.addEventListener('pointerdown', (e) => {
        if (e.target.closest('.pl-bar')) return;
        lastTouch = performance.now();
        drag = { x: e.clientX, off: sim.off, lx: e.clientX, lt: e.timeStamp, v: 0, moved: false };
        sim.glide = null;
    });
    root.addEventListener('pointermove', (e) => {
        if (!drag) return;
        const dx = e.clientX - drag.x;
        if (!drag.moved && Math.abs(dx) > 5) {
            drag.moved = true;
            root.dataset.drag = '1';
            root.setPointerCapture(e.pointerId);
        }
        if (!drag.moved) return;
        const dt = Math.max(1, e.timeStamp - drag.lt);
        drag.v = 0.7 * ((e.clientX - drag.lx) / dt) + 0.3 * drag.v;
        drag.lx = e.clientX;
        drag.lt = e.timeStamp;
        const max = (n - 1) * spacing;
        let off = drag.off - dx;
        if (off < 0) off *= 0.35;
        if (off > max) off = max + (off - max) * 0.35;
        sim.off = off;
        start();
    });
    const release = (e) => {
        const d = drag;
        drag = null;
        lastTouch = performance.now();
        if (!d) return;
        if (d.moved) {
            root.dataset.drag = '0';
            sim.vel = -d.v * 1000;
            goTo(nearestAt(sim.off - d.v * 180, spacing, n), false);
            return;
        }
        const card = e.target.closest('.pl-card');
        if (!card) return;
        const i = cards.indexOf(card);
        if (i === active && card.dataset.full && typeof window.previewImage === 'function') {
            window.previewImage(card.dataset.full, card.dataset.title || '');
        } else {
            goTo(i);
        }
    };
    root.addEventListener('pointerup', release);
    root.addEventListener('pointercancel', release);

    const step = (dir) => {
        lastTouch = performance.now();
        goTo(clamp(active + dir, 0, n - 1));
    };
    root.querySelector('[data-pl-prev]')?.addEventListener('click', () => step(-1));
    root.querySelector('[data-pl-next]')?.addEventListener('click', () => step(1));
    root.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') step(1);
        else if (e.key === 'ArrowLeft') step(-1);
        else if (e.key === 'Enter' && cards[active]?.dataset.full && typeof window.previewImage === 'function') {
            window.previewImage(cards[active].dataset.full, cards[active].dataset.title || '');
        } else return;
        e.preventDefault();
    });
}

export function initPolaroidCarousels() {
    document.querySelectorAll('[data-polaroid-carousel]').forEach((root) => {
        if (root.dataset.plReady) return;
        root.dataset.plReady = '1';
        mount(root);
    });
}
