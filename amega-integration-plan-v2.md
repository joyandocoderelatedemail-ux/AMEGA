# AMEGA Travel & Tours — Booking Rules Integration Plan
### Integrating Ticketing, Visa/Document, Fee & Workflow Rules into amegatravelandtour.com + Internal CRM

**Confirmed setup:**
- Public site: amegatravelandtour.com (packages, inquiry form, live chat/FAQ bot) — lead capture layer
- Internal admin/CRM (behind login) — where ~100 agents actually process bookings
- You/your team have direct code access on both
- Departments: Ticketing, Visa Assistance, Marketing, General Inquiry (separate contact routing already exists)

---

## 1. Two-Layer Integration

Your rules split naturally across the two systems already in place:

| Layer | What it should do | Source rules that apply |
|---|---|---|
| **Public site** (amegatravelandtour.com) | Capture better lead data, answer basic visa/doc FAQs instantly, route to correct department | Client info checklist, basic visa-free/visa-required FAQ, department routing |
| **Internal CRM** | Enforce the actual 9-step workflow, calculate fees, validate documents, generate quotations/agreements | Full workflow, fee schedule, document validation, booking agreement, rebooking rules |

This means **most of the heavy logic (fees, workflow gating, document validation) belongs in the CRM**, not the public site. The public site's job is just to capture a clean, complete lead and set expectations.

---

## 2. Public Site (amegatravelandtour.com) Changes

### 2.1 Inquiry Form — upgrade fields
Current form: Category, First/Last Name, Email, Phone, Message.
Add (as conditional fields based on Category selection):
- Destination(s)
- Travel dates (departure/return) — already exists on the homepage search widget, but not on the actual inquiry form; unify these
- Number of pax + breakdown (adult/child/infant/senior/PWD)
- One-way / round trip
- Nationality (for visa-rule matching later in CRM)

This directly feeds the CRM's Client Information Checklist so agents don't have to re-ask basics.

### 2.2 Live Chat / FAQ Bot — add auto-reply rules
Your chat widget already supports "Auto-Reply FAQs." Add entries sourced from your visa tables:
- "Do I need a visa for [country]?" → auto-lookup against visa-free/visa-required list
- "How long is my passport required to be valid?" → 6-month rule
- "What's the travel tax?" → PHP 1,620 + exemption note, refer to agent for exemption confirmation
- Anything destination-specific or nuanced → hand off to live agent (don't let the bot guess)

### 2.3 Department Routing Logic
Category field already exists — map it directly to your department contacts:
- "Visa Processing Assistance" → routes to Visa Assistance (0917 626 4181 / visas@)
- General ticketing/tour inquiries → Ticketing (0917 626 4925 / ticketing@)
- This routing should also tag the lead in the CRM with the correct department on creation, so it lands in the right agent's queue automatically.

---

## 3. Internal CRM Changes (where the real workflow logic lives)

### 3.1 Data Model Additions
- `destination_visa_rules` — visa-free/on-arrival/required + max stay, by nationality (from Images 2–4)
- `fee_schedule` — service_type → fee amount (from Image 5), versioned with effective dates
- `booking_documents` — per-booking document status (passport, visa, gov ID, ECC), expiry tracking
- `booking` record extended with: current_step (1–9), trip_type, pax_breakdown, special_requests
- `booking_agreement` — template version, rebookable flag, transferable (always false), signature status

### 3.2 Workflow Engine (9 Steps, Gated)
Build this as a status field on each booking record that only moves forward when the prior step's required data is present:
1. Client requirements captured (auto-filled from web lead if it came from the site)
2. Documents verified — CRM checks passport validity (6mo rule), visa requirement flag, ECC flag if >6mo stay
3. Quotation generated — auto-pulls from `fee_schedule` based on service type + destination
4. Booking agreement — agent sets rebookable/non-rebookable, non-transferable is fixed
5. Review/double-check — agent confirmation logged with timestamp + agent ID
6. Payment/deposit collected
7. Tickets/vouchers issued — locks the quotation
8. Itinerary + reminders sent (can trigger via email using existing contact info already collected)
9. After-sales support — opens a linked support case

Block progression to step 6 (payment) if step 2 (documents) has unresolved flags — this was explicit in your source notes ("verify... before issuing advice").

### 3.3 Fee & Tax Automation
- Fee schedule fields exactly as in Image 5 (domestic/international × ticketing/package/rebooking, plus reconfirmation)
- Travel tax (PHP 1,620) auto-added unless agent marks passenger as exempt, with exemption reason required as a field (auditable — finance will want this)

### 3.4 Anti-Spam / Booking Integrity
- Since leads originate from the public form, add basic rate-limiting on repeated submissions from the same email/phone in a short window before they hit the CRM as duplicate bookings
- Require e-signature only for the online/website-originated bookings; walk-in bookings use physical/onsite verification (per your notes)

---

## 4. Phased Rollout

**Phase 1 (Week 1–2): Data + Vendor Coordination**
- Digitize `fee_schedule` and `destination_visa_rules` tables — 100% spot-check against Images 2–5
- Confirm with your team what backend the CRM runs on (this determines migration syntax — get this from whoever built/maintains the CRM, even if it's you)

**Phase 2 (Week 2–3): Public Site Updates**
- Expand inquiry form fields
- Add visa/document FAQ auto-replies to live chat
- Confirm department routing writes correctly into CRM lead queue

**Phase 3 (Week 3–5): CRM Core — Workflow + Documents**
- Build 9-step gated status on booking records
- Build document validation logic (passport 6-month rule, ECC trigger, visa flag)

**Phase 4 (Week 5–6): CRM — Fees, Quotation, Agreement**
- Fee auto-calculation
- Quotation generation (customer copy + internal "Amega copy")
- Agreement template with rebooking/non-transferable terms

**Phase 5 (Week 6–7): Pilot**
- Small group of agents (5–10) test end-to-end: web lead → CRM → quotation → agreement → payment
- Watch for: fee mismatches, false document flags, step-skip attempts

**Phase 6 (Week 8): Full Rollout to all ~100 daily users**
- Internal training using the updated manual
- Monitoring: leads/day from site vs. CRM entries, step drop-off, fee errors flagged

---

## 5. QA Checklist Before Go-Live
- [ ] Fee schedule in CRM matches Image 5 exactly — this drives revenue, verify line by line
- [ ] Visa rule table cross-checked against current DFA/embassy sources (Thailand entry already flagged "subject to current regulations" — treat the whole table as needing periodic re-verification, not a one-time load)
- [ ] Inquiry form → CRM lead creation tested for all Category types
- [ ] Live chat FAQ answers don't give definitive visa advice beyond general free/required status — nuanced cases route to a human
- [ ] 9-step gating tested: cannot skip forward, can go back with logged reason
- [ ] Department routing confirmed against current contact list (note: site already shows the old 0917 119 4909 number is deprecated — make sure CRM/site aren't referencing retired contact info anywhere else)

---

## 6. Ownership
- **Visa/document data**: one staff member owns quarterly review
- **Fee schedule**: finance/ops owns changes, CRM should log fee change history (no silent edits)
- **Public site + CRM code**: your team, since you have direct access — but confirm whether RNZ needs to be looped in for deployment/hosting even if you can edit code (e.g., do they manage the server/deploy pipeline?)

---

## 7. Still Need From You
- What's the CRM's tech stack (this determines exact implementation — database, backend language)?
- Is there an existing payment gateway connected anywhere yet, or does Step 6 need to be built new?
- Does RNZ manage deployment/hosting even though you can edit code, or is that also fully in your team's hands?
