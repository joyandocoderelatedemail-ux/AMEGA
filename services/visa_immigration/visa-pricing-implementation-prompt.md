# Prompt: Build the Visa/Immigration Pricing & Process Module for AMEGA

Paste this whole document to a coding agent (or yourself) working inside the
`AMEGA` Laravel repo (`C:\xampp\htdocs\AMEGA`). It contains the project
analysis, the source data to implement, and the execution plan. It assumes
no other context is available — everything needed is below.

---

## 0. How this connects to the AMEGA codebase (analysis)

AMEGA is a Laravel 12 + Blade/Tailwind/Alpine travel-agency site (see
`AGENTS.md` at repo root for full conventions — read it first, it governs
this work: Pint formatting, Pest testing, `search-docs` before code changes,
`ActivityLogger` on admin mutations, resource routing, etc.).

Relevant existing pieces:

- **`app/Models/Service.php`** + `services` table (`title`,
  `short_description`, `full_description`, `icon`, `image`, `badge`,
  `order`, `is_active`) — powers the public "Our Core Services" homepage
  cards (`resources/views/partials/services.blade.php`) and the
  `/services` page (`resources/views/pages/services.blade.php`).
  `database/seeders/ServiceSeeder.php` already seeds **four** services that
  this pricing sheet is the backing detail for:
  - "Immigration Service Request" (BI processing: visa extensions, ECC,
    ACR I-Card, SSP, dual citizenship)
  - "Passport & Visa Processing"
  - "Tourist Visa Extensions" (this is the one the attached sheet is
    mostly about)
  - "PRA Retirement Visa (SRRV)"

  **`Service` is deliberately shallow** — a marketing teaser card. It is
  the wrong place to store the sheet's ~90 conditional price points; its
  `full_description` is literally exploded on `.` into a bullet list
  (see `partials/services.blade.php` line ~29), so it can't hold a
  pricing table. Do not try to cram this data into `Service`.

- **Admin CRUD pattern to mirror**: `app/Http/Controllers/Admin/AdminServiceController.php`
  + `resources/views/admin/services/{index,create,edit}.blade.php` +
  route group in `routes/web.php` (`Route::resource('services', AdminServiceController::class)`
  plus a `toggle-status` route) + `ActivityLogger::log(...)` calls on every
  mutation + `tests/Feature/AdminServiceTest.php` for the Pest coverage
  pattern. The new feature should follow this exact shape.

- **Guest chat widget** (`resources/views/partials/chat-widget.blade.php`):
  has a hardcoded `quickQuestions` JS array of canned "Auto-Reply FAQs"
  (e.g. "What visa assistance services do you offer?"). This is a good,
  *lightweight* place to point guests at the new pricing page — it is
  **not** a place to paste full pricing tables (replies are one-liners).
  There is no real AI/LLM behind this chat — replies are static strings
  plus a live-agent handoff (`ChatService.php`, `Conversation`/`Message`
  models). Don't build an "AI" pricing assistant; just add/update 1-2
  `quickQuestions` entries with a short answer + link once the pricing
  page exists.

- Why this needs its own admin-manageable data model, not hardcoded Blade
  content: **the source sheet itself has handwritten corrections on top
  of the printed prices** (annotations like "depends on the stamper",
  "add 300 pesos", clarifying notes on SRRN/ACR conditions). These fees
  are Bureau of Immigration figures that change over time and are
  clearly maintained by hand today. The implementation must let
  non-developer staff edit prices/steps/requirements from `/admin`,
  the same way they already edit `Service` rows — not require a code
  deploy every time BI adjusts a fee.

## 1. Objective

Implement a new **Immigration Pricing & Process Guide** feature:

1. Admin-manageable data model for: process categories (Visa Extension,
   Exit Clearance, Re-Stamping, Certificate of Residence for Temporary
   Visitors/CRTV, nationality-specific variants), their step-by-step
   requirements, and their tiered/conditional prices.
2. A public page presenting this clearly (grouped, filterable/collapsible
   by category and extension number), linked from the existing
   "Tourist Visa Extensions" / "Immigration Service Request" service
   cards and from `/services`.
3. Admin CRUD screens under `/admin` to manage it, matching existing
   conventions.
4. A short, linked mention added to the chat widget's auto-reply FAQs.
5. Pest feature tests for the admin CRUD and the public page.

## 2. IMPORTANT — verify data before shipping

The source is a scanned handwritten/printed reference sheet with several
illegible or ambiguous spots. The transcription in Section 3 is a
best-effort, faithful reading, but **do not treat it as ground truth for
a client-facing price list without a human (the AMEGA staff/owner)
confirming the flagged items first.** Flagged items are marked `⚠️`
inline. Getting a fee wrong is a real business/legal problem for a BI
accredited agency — do not silently "correct" or guess at illegible
numbers; store what's confirmed, leave flagged rows as drafts
(`is_active = false` or a `needs_review` flag) until confirmed.

Also: build the admin UI so **every** price, label, and requirement is
editable text/decimal fields — never hardcode the numbers below into a
Blade view. The numbers below are seed data, not permanent content.

## 3. Source data — full transcription

Currency is Philippine Pesos (₱). "Express" = 1-day process unless noted.
"Regular" = 7–10 working days unless noted.

### 3.1 Visa Extension Process — cash payment (Section 1 of sheet)

**1st Extension**
- Regular process (7–10 working days): if visa is valid 8 days or more.
- Express process (1 day): if visa validity is less than 7 days, or visa
  already expired.
- Visa Waiver (29 days) upon arrival, if client has 30 days additional
  validity:
  | Price | Condition |
  |---|---|
  | ₱2,930 | Regular — visa validity is 8 days or more |
  | ₱5,080 | Express — visa validity is less than 8 days |
  | ₱6,090 | Visa expired — express + penalties |

**2nd Extension** — applies when client had a 9(a) visa and already did
1 extension, or client has a Balik-Bayan visa. If the extension falls
with I-Card processing, it's done express.
| Price | Duration | Condition |
|---|---|---|
| ₱10,400 | 2 months | No valid ACR I-Card |
| ₱11,880 | 2 months | Visa expired (express) |
| ₱10,000 | 1 month | No valid ACR I-Card |
| ₱11,380 | 1 month | Visa expired (express) |
| ₱9,500 | 2 months | I-Card needs renewal |
| ₱10,680 | 2 months | Visa expired (express) |
| ₱9,000 | 1 month | I-Card needs renewal |
| ₱10,180 | 1 month | Visa expired (express) |
| ₱3,250 | 2 months | Regular, valid ACR I-Card |
| ₱5,600 | 2 months | Express, valid ACR I-Card |
| ₱6,610 | 2 months | Valid ACR, visa expired (express) |
| ₱2,750 | 1 month | Regular, valid ACR I-Card |
| ₱5,100 | 1 month | Express, valid ACR I-Card |
| ₱6,110 | 1 month | Valid ACR, visa expired (express) |

**3rd Extension**
| Price | Duration | Condition |
|---|---|---|
| ₱2,730 | 2 months | Regular |
| ₱4,880 | 2 months | Express |
| ₱5,890 | 2 months | Visa expired (express) |
| ₱2,230 | 1 month | Regular |
| ₱4,380 | 1 month | Express |
| ₱5,390 | 1 month | Visa expired (express) |
| ₱9,000 | 2 months | Plus I-Card renewal |

⚠️ Top of the next page continues with figures that appear to belong
here (cash, not card-payment) but the sheet's section boundary is
ambiguous — confirm before entering:
| Price | Duration | Condition |
|---|---|---|
| ₱10,000 | 2 months | Plus I-Card renewal, visa expired |
| ₱8,500 | 1 month | Plus I-Card renewal |
| ₱9,500 | 1 month | Plus I-Card renewal, visa expired |

**Certificate of Residence for Temporary Visitors (CRTV)** — required if
client exceeds more than 6 months. ⚠️ The sheet has a "1410" figure next
to this heading that doesn't match any line item below — confirm what it
refers to (possibly OCR/handwriting noise, or a separate small fee) before
using it.
| Price | Duration | Condition |
|---|---|---|
| ₱4,140 | 2 months | Regular |
| ₱6,290 | 2 months | Express |
| ₱7,300 | 2 months | Visa already expired |
| ₱3,640 | 1 month | Regular |
| ₱5,790 | 1 month | Express |
| ₱6,800 | 1 month | Visa already expired |

**Hong Kong passport holder (Chinese, visa type 9(g)) — 1st Extension**
| Price | Duration |
|---|---|
| ₱4,380 | 7 days (always express) |
| ₱5,090 | 38 days (always express) |

**Indian passport holder note**: add ₱300 to every extension. Handwritten
annotation clarifies this "depends on the stamp[er]" and applies
specifically to 9(g) visas — confirm exact scope with staff before
encoding as a blanket +₱300 rule.

### 3.2 Exit Clearance Process (Section 2)

- Required if client has stayed/exceeded more than 6 months and wants to
  leave the country.
- Must be started 2 weeks before departure date; the clearance itself is
  a **1-day process**.
- If it's the client's first time exceeding 6 months and they want to
  leave, they need to personally appear at Immigration for biometrics.
- No appearance required if the client has an SSRN ⚠️ number (transcribed
  as written — confirm whether this is "SRRN"/"SSRN" and what it stands
  for; handwritten annotations on the sheet distinguish "with appearance,
  no SRRN #" vs "no appearance, with SRRN").
- Processed same day.

**Requirements checklist:**
1. Fill up the Exit Clearance form
2. 4 pcs 2×2 photo, white background, no eyeglasses
3. Copy of client's ticket
4. Photocopy of passport biopage
5. Photocopy of latest arrival stamp
6. Photocopy of last extension receipt
7. Photocopy of ACR I-Card (front and back)

**Price:** ₱4,000 for the Exit Clearance.

### 3.3 Re-Stamping Process (Section 3)

Transfers an old visa stamp to a new passport. Processing time: 7–10
working days.

**Requirements checklist:**
1. Re-Stamping form
2. Old and new passport
3. Latest extension receipt — optional if tourist visa

**Price:** ⚠️ ₱3,800 — the printed digits are unclear in the scan
(rendered as "3,ll00" / "3,II00"); confirm the exact figure with staff
before entering it (most likely ₱3,800, but do not guess in production
data).

### 3.4 Visa Extension — Card Payment (separate pricing sheet)

This is a **distinct table** from the cash pricing in 3.1 — same
extension tiers, different (generally higher) prices for card payment,
and card-payment entries are express-only.

**1st Extension**
| Price | Note |
|---|---|
| ₱3,048 | Visa waiver, regular process |
| ₱5,284 | Visa waiver, express process |
| ₱6,334 | Visa waiver, visa expired |

**2nd Extension**
| Price | Duration | Condition |
|---|---|---|
| ₱3,380 | 2 months | Regular, valid ACR |
| ₱5,824 | 2 months | Express, valid ACR |
| ₱6,875 | 2 months | Valid ACR, visa expired |
| ₱2,860 | 1 month | Regular, valid ACR |
| ₱5,304 | 1 month | Express, valid ACR |
| ₱6,355 | 1 month | Valid ACR, visa expired |
| ₱10,816 | 2 months | Plus I-Card NEW, express |
| ₱12,355 | 2 months | Plus I-Card NEW, visa expired, express |
| ₱10,400 | 1 month | Plus I-Card NEW, express |
| ₱11,835 | 1 month | Plus I-Card NEW, visa expired, express |
| ₱9,880 | 2 months | Plus I-Card RENEWAL, express |
| ₱11,107 | 2 months | Plus I-Card RENEWAL, visa expired, express |
| ₱9,360 | 1 month | Plus I-Card RENEWAL, express |
| ₱10,588 | 1 month | Plus I-Card RENEWAL, visa expired, express |

**3rd Extension**
| Price | Duration | Condition |
|---|---|---|
| ₱2,840 | 2 months | Regular |
| ₱5,075 | 2 months | Express |
| ₱6,126 | 2 months | Visa expired, express |
| ₱2,320 | 1 month | Regular |
| ₱4,555 | 1 month | Express |
| ₱5,606 | 1 month | Visa expired, express |
| ₱9,360 | 2 months | Plus I-Card renewal, express |
| ₱10,400 | 2 months | Plus I-Card renewal, visa expired, express |
| ₱8,840 | 1 month | Plus I-Card renewal, express |
| ₱9,880 | 1 month | Plus I-Card renewal, visa expired, express |

**4th Extension**
| Price | Duration | Condition |
|---|---|---|
| ₱4,306 | 2 months | Plus CRTV, regular |
| ₱6,542 | 2 months | Plus CRTV, express |
| ₱7,592 | 2 months | Plus CRTV, express, visa expired |
| ₱3,786 | 1 month | Plus CRTV, regular |
| ₱6,022 | 1 month | Plus CRTV, express |
| ₱7,072 | 1 month | Plus CRTV, express, visa expired |

## 4. Proposed technical design

Follow the `Service`/`AdminServiceController` pattern already in the repo.
Use `search-docs` (Laravel Boost MCP tool) for anything version-specific
before writing migrations/models. Run `php artisan make:model`,
`make:migration`, `make:controller`, `make:test --pest` rather than
hand-rolling files, per `AGENTS.md`.

Suggested schema (adjust names to fit repo conventions if a better fit
emerges while exploring, but keep the shape — category → line items):

```
immigration_categories
  id, slug, name, description, sort_order, is_active, timestamps
  -- e.g. "visa-extension-cash", "visa-extension-card", "exit-clearance",
  --      "re-stamping", "crtv", "hongkong-9g"

immigration_requirements
  id, immigration_category_id (FK), label, sort_order, timestamps
  -- the document checklists (4.2 Exit Clearance requirements, etc.)

immigration_pricing_tiers
  id, immigration_category_id (FK), extension_label (nullable string,
    e.g. "1st Extension", "2nd Extension"), duration_label (e.g.
    "2 months", "1 month", "29 days"), process_type (enum: regular,
    express), payment_method (enum: cash, card), condition_notes
    (string, e.g. "valid ACR I-Card, visa expired"), price (decimal 10,2),
    processing_time (string, e.g. "7-10 working days", "1 day"),
    needs_review (boolean, default false — for the ⚠️ flagged rows),
    is_active, sort_order, timestamps
```

Alternative if the team prefers less normalization: a single
`immigration_pricing_items` table with a `category` string column
instead of a separate categories table, plus a `process_notes` text
column on a small `immigration_processes` table for the free-text
process rules (appearance/biometrics rules, SSRN exemption, nationality
surcharge notes) that don't fit a price-row shape. Pick whichever keeps
the admin form simplest to use — staff will be editing this by hand
regularly.

**Model layer**: one Eloquent model per table, `casts()` method per
Laravel 12 convention (see other models for the pattern), factories +
seeder (`ImmigrationPricingSeeder.php` following `ServiceSeeder.php`'s
`updateOrCreate` idempotent pattern) seeded from Section 3 above —
mark the ⚠️ flagged rows with `needs_review = true` and lower/omit
`is_active` until confirmed.

**Admin controllers/views**: `AdminImmigrationPricingController`
(or split into `AdminImmigrationCategoryController` +
`AdminImmigrationPricingController` if that's cleaner) under
`app/Http/Controllers/Admin/`, views under
`resources/views/admin/immigration-pricing/`, routes registered next to
the existing `Route::resource('services', ...)` block in
`routes/web.php` inside the admin-guarded group, `ActivityLogger::log()`
calls on create/update/delete/toggle mirroring
`AdminServiceController`. Add a nav link in
`resources/views/layouts/admin.blade.php` alongside the existing
Services/Packages/Destinations links.

**Public page**: new route/controller action (e.g. extend
`PageController` or add a dedicated `ImmigrationPricingController`) and
a Blade view, grouped by category with collapsible/tabbed sections per
extension number and a clear cash-vs-card toggle, styled consistently
with `resources/views/partials/services.blade.php`. Link to it from the
"Tourist Visa Extensions" and "Immigration Service Request" cards
(the `action` button currently goes to the contact form — either add a
secondary "View Pricing" link, or link the card image/title to this
page while keeping "Inquire Now" as-is).

**Chat widget**: in `resources/views/partials/chat-widget.blade.php`,
update the existing "📄 What visa assistance services do you offer?"
`quickQuestions` reply (or add one more entry) to mention the new
pricing page with a short line + link, e.g. "See our full visa
extension, exit clearance, and re-stamping price list here: [link]." Do
not paste tables into the chat reply.

## 5. Tests

Add Pest feature tests mirroring `tests/Feature/AdminServiceTest.php`
for: admin index/create/store/edit/update/destroy on the new
controller(s) (auth-gated to admin role, per `AdminMiddleware`), and a
test that the public pricing page renders active categories/tiers and
excludes inactive/needs-review rows. Run `php artisan test --compact
--filter=Immigration` while iterating, then the full suite
(`composer test`) before finishing.

## 6. Execution checklist

1. Read `AGENTS.md` in full for conventions not restated above.
2. Confirm the ⚠️ flagged data points with the AMEGA owner/staff (or ask
   the user driving this task) before marking those rows active.
3. `search-docs` for any Eloquent/migration patterns you're unsure of.
4. Create migrations, models, factories via `artisan make:*`.
5. Build `ImmigrationPricingSeeder`, register it in
   `database/seeders/DatabaseSeeder.php`.
6. Build admin controller(s) + views + routes + nav link, following
   `AdminServiceController` 1:1 where possible.
7. Build the public page + route + link-in from the two relevant
   service cards.
8. Add the short chat-widget FAQ update.
9. Write Pest tests; run `php artisan test --compact`.
10. `vendor/bin/pint --dirty --format agent`.
11. `npm run build` if any Blade/CSS class changes aren't reflected.
12. Manually click through `/admin` CRUD and the public page before
    calling it done.
