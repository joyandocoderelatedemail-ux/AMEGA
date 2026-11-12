# Figma → code rules (AMEGA)

How to turn a Figma design (via the Figma MCP server) into code in this repo. The output of
`get_design_context` is React + Tailwind **reference code**: never paste it. Translate it into a
Blade view with Tailwind v3 utilities and Alpine.js, reusing the tokens and components below.

## 1. Stack (what the output must be)

| Concern | What this repo uses |
|---|---|
| Templates | Laravel Blade (`resources/views/**`), no React/Vue/JSX |
| Interactivity | Alpine.js 3 (`x-data`, `x-show`, `x-model`, `@click`), set up in `resources/js/app.js` |
| Styling | Tailwind CSS **v3** via PostCSS (`tailwind.config.js`, `postcss.config.js`). `@tailwindcss/vite` v4 is installed but **not wired**, so ignore v4 syntax (`@theme`, `bg-(--var)`, `size-*` is fine in v3.4) |
| Plugins | `@tailwindcss/forms` only |
| Bundler | Vite 7 + `laravel-vite-plugin`; entry points `resources/css/app.css`, `resources/js/app.js`, loaded with `@vite([...])` in each layout |
| Build output | `public/build/` is **committed**. New Tailwind classes only exist after `npm run build`; commit the build |
| Storybook / component docs | None. `resources/views/ui/diagonal-marquee-preview.blade.php` is the only preview page |

## 2. Tokens

There are no design-token files (no JSON, no Style Dictionary). Tokens live in two places:

**`tailwind.config.js` (`theme.extend`)**: map Figma colours to these, never to hex literals.

```js
primary / navy   // the same blue ramp under two names: 50 #EEF3FB … 500 #005ADA, 600 #0049B0,
                 // 700 #003B95 (DEFAULT), 800 #002F76, 900 #002250, 950 #001733
accent           // olive 50 … 500 #A9BD00 (DEFAULT) … 900. Accent ONLY: active marks, focus, logo
brand.red        // #D51C00 / red-dark #951000 / red-deep #5A0600: public-site prices and badges
dark             // #1E293B
boxShadow.card   // '0 1px 2px rgba(15,23,42,.05), 0 4px 16px rgba(15,23,42,.06)'
fontFamily       // heading: Montserrat, body/subheading/sans: Open Sans
```

- Write numeric steps in new code: `bg-navy-700`, `text-navy-600`, `ring-accent-500`, not `bg-primary`.
- Neutrals are stock Tailwind `slate`/`gray`. Semantic colour is capped at **emerald** (success),
  **amber** (needs action) and **rose** (failed/error). If Figma uses other hues for status, map them to these three.

**`resources/css/ticketing-theme.css`** (CSS custom properties scoped to a body class):

```css
.portal-minimal {           /* Ticketing, Visa Assistance, Cashier */
  --page:#f9fafb; --foreground:#333; --card:#fff; --muted:#f9fafb;
  --muted-foreground:#6b7280; --border:#e5e7eb;
  --radius:.375rem;          /* rounded-lg and rounded-xl are re-mapped to this */
  --radius-lg:.5rem;         /* rounded-2xl and rounded-3xl are re-mapped to this */
  --font-sans: Inter, 'Open Sans', …;
}
html.dark .portal-minimal { … }   /* dark mode re-maps bg-white, bg-gray-50, text-slate-* etc. */
.staff-type { --font-sans: Inter, … }  /* admin, Immigration, SRRV, staff logins: font only */
```

Don't put `dark:` variants in portal views: dark mode works by re-mapping the light utilities in
this file. If a Figma design needs a neutral the file doesn't re-map yet, add the dark rule there.

## 3. Which surface is the design for?

Decide this first: it sets the fonts, radii and layout.

| Surface | Layout | `<body>` class | Type | Cards |
|---|---|---|---|---|
| Public website | `components/layout.blade.php`, `partials/*` | none | Montserrat headings, Open Sans text | free |
| Ticketing / Visa / Cashier | `layouts/ticketing`, `visa-assistance`, `cashier` | `portal-minimal` | Inter, headings 700 | `rounded-xl` |
| Admin, Immigration, SRRV, Local Tours | `layouts/admin`, `immigration`, `srrv`, `local-tours` | `staff-type` | Inter, headings 700 | `rounded-2xl`, controls `rounded-lg` |

- **New staff pages use the ticketing ("Modern Minimal") look**: reference
  `resources/views/ticketing/airlines/index.blade.php`.
- Public site trap: `app.css` forces `font-weight: 900` on `h1.font-heading, h2.font-heading`, so leave
  `font-heading` off public h1/h2 when the Figma weight is not Black.
- A new staff layout gets `staff-type` on `<body>` and only the Inter Google Fonts link.
- **Never ship placeholder numbers** from a Figma mock (dashboard figures, counts, charts). Each
  value must come from a real query; otherwise show an empty state.
- Step-by-step forms use the ticket wizard's stepper ("Step N - Title", round badge, olive/blue/grey bars).

## 4. Components

Blade anonymous components in `resources/views/components/` (used as `<x-name>`), plus `@include`d
partials. Check these before writing new markup:

- Forms: `text-input`, `input-label`, `input-error`, `country-select`, `ph-address` (PSGC address
  dropdowns), `date-range`, `place-picker`, `destination-picker`, `ticketing/field-error` (Alpine wizard errors)
- Buttons: `primary-button`, `secondary-button`, `danger-button` (Breeze defaults, gray/indigo; most staff
  pages write navy buttons inline instead)
- Overlays / navigation: `modal`, `dropdown`, `dropdown-link`, `nav-link`, `responsive-nav-link`
- Domain: `file-owner`, `ticketing/scan-panel`, `ticketing/extra-pricing`, `diagonal-marquee`, `v-card-17`
- Layout chrome: `layouts/admin-nav`, `admin-topnav`, `desk-switcher`, `desk-bell`, `admin-bell`
- Public sections: `partials/hero`, `services`, `tours`, `destinations`, `gallery`, `testimonials`, `cta`, `faq`, `footer`, `nav`

Pages repeat class strings through a local `@php` block rather than through new components:

```blade
@php
    $input = 'w-full h-10 px-3 rounded-lg bg-white border border-slate-300 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600';
    $fieldLabel = 'block text-xs font-semibold text-slate-600 mb-1';
@endphp

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <p class="px-5 py-10 text-center text-sm text-slate-500">No airlines yet. Add the first one below.</p>
</div>
```

Make a new `<x-…>` component only when the same Figma component appears on several pages. Use
`@props` and `$attributes->merge(['class' => '…'])`, as `primary-button.blade.php` does.

## 5. Icons

There are two icon sets, both plain markup with no imports:

```blade
{{-- Lucide: the main set (~900 uses). Loaded from unpkg in each layout; lucide.createIcons() runs on load --}}
<i data-lucide="arrow-left" class="w-4 h-4"></i>

{{-- Font Awesome Free 7 (self-hosted through Vite, see .font-awesome.md) --}}
<i class="fa-regular fa-calendar" aria-hidden="true"></i>
```

- Map a Figma icon to a Lucide name (kebab-case, e.g. `check-circle`, `log-out`) first. Use Font
  Awesome only for brands or icons Lucide lacks, and prefer `fa-regular` because it matches the outline style.
- Size with `w-4 h-4` / `w-5 h-5` (Lucide) or `text-sm` / `text-base` (FA). Colour with `text-*`
  because icons use `currentColor`.
- Don't export Figma icons as SVG/PNG assets when a Lucide/FA equivalent exists. Inline `<svg>` is
  used for one-off illustrations only.
- Decorative icons get `aria-hidden="true"`; icon-only buttons need `aria-label` or `sr-only` text.
- Alpine views that add icons after load must call `lucide.createIcons()` again.

## 6. Assets

- Static images live under `public/` and are referenced with `asset()`:
  `public/images/{hero,tours,gallery,services,visa,logoapproved,…}`, and brand/marketing files in
  `public/newassets/` (logo: `newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png`; the brand guide is
  `public/images/AMEGA BRAND GUIDE.jpg`). Paths contain spaces; `asset()` handles them.
- Photos uploaded through the admin panel go to the `uploads` disk (`public/uploads`, URL `/uploads`).
- **Show public photos through `App\Support\WebImage::url($path, $width)`**: it makes a cached WebP
  at `public/thumbs/{width}/` (widths `CARD` 800, `LOGO` 240, `MARQUEE` 640, `WIDE` 1600). Add
  `loading="lazy"` and `object-cover`.
  ```blade
  <img src="{{ \App\Support\WebImage::url($pkg->image) }}" alt="{{ $pkg->title }}" loading="lazy" class="w-full h-full object-cover">
  ```
- Images downloaded from Figma (`download_assets`) go in the right `public/images/<section>/` folder
  with a descriptive kebab-case name, not a hash.
- Client documents (passports, IDs, scans) are **never** public assets: they go through
  `App\Support\DocumentStorage` on the private disk.
- No CDN. Only Google Fonts and Lucide (unpkg) load from third parties. Vite emits relative CSS asset
  URLs (`renderBuiltUrl`) so the build works both from the local sub-folder and from the domain root.

## 7. Styling approach and responsiveness

- Tailwind utility classes in the markup; no CSS Modules, no CSS-in-JS. Custom CSS only in
  `resources/css/app.css` (global: fonts, `[x-cloak]`, navbar, `.animate-on-scroll`, brand-red helpers),
  `ticketing-theme.css` (portal tokens and dark mode) and `polaroid-carousel.css`.
- Mobile-first breakpoints are stock Tailwind (`sm` 640, `md` 768, `lg` 1024, `xl` 1280). The public
  navbar is `h-16`, and `lg:h-20` from `lg` up (`scroll-padding-top` follows it).
- Translate Figma auto-layout to `flex`/`grid` + `gap-*`, and fixed frame widths to
  `max-w-5xl mx-auto` style containers, not pixel widths. Arbitrary values (`text-[11px]`) are fine
  when no scale step fits.
- Scroll-in animation: add `animate-on-scroll`. Hide Alpine-controlled content until init with `x-cloak`.
- In the ticket wizard, never put `x-transition` on step panels.

## 8. Project structure

```
resources/views/
  layouts/            one shell per portal (admin, ticketing, visa-assistance, immigration, srrv, local-tours, cashier, app)
  components/         <x-…> Blade components (+ ticketing/)
  partials/           public-site sections and shared form partials
  admin/ ticketing/ visa-assistance/ srrv/ local-tours/ cashier/   one folder per desk, then per feature
  pages/ packages/ bookings/ blog/   public site
  emails/ mail/       notification templates (inline styles, no Tailwind build)
resources/css/        app.css (entry), ticketing-theme.css, polaroid-carousel.css
resources/js/         app.js (entry), bootstrap.js (axios), polaroid-carousel.js
public/images, public/newassets, public/uploads, public/thumbs, public/build (committed)
```

Features are organised by portal and then by resource (`admin/expiring-documents/index.blade.php`),
routes by portal in `routes/web.php`. Feature-local partials are prefixed `_` (`_form.blade.php`).

## 9. Checklist for a Figma implementation

1. Fetch with `get_design_context` + `get_screenshot`, and decide which surface it belongs to (§3).
2. Map colours to `navy-*` / `accent-*` / slate / emerald-amber-rose, fonts to the surface's type, radii to the surface's card radius.
3. Reuse components (§4) and icons (§5) before adding new ones.
4. Write Blade + Alpine and replace mock data with real queries or empty states.
5. `npm run build`, check the page (auth pages: see "Previewing auth-gated pages" in CLAUDE.md), commit `public/build/`.
