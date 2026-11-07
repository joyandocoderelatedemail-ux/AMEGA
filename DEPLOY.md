# Deploying to production

The site is on shared cPanel hosting (InMotion). Deploying is the cPanel "Deploy HEAD Commit" button, then a few
commands by hand. `scripts/post-deploy.sh` does those commands for you, with a database backup first.

## Before you deploy (on your computer)

1. `composer test` passes.
2. `npm run build`, then commit the new files in `public/build/` along with the code. The live site serves the
   committed build, so a view that uses a style missing from it renders unstyled.
3. Everything is committed and merged to `main`. Note the commit you are replacing (`git log -1 --format=%h origin/main`):
   that is your way back.
4. Check `database/migrations`: every migration here only adds tables or columns, so it is safe to run on live data.
   Be suspicious of any migration that drops, renames or rewrites existing data and test it on a copy first.

## Deploy

1. cPanel > **Git Version Control** > your repository > **Deploy HEAD Commit**.
   (`.cpanel.yml` copies `public/` into `public_html`, so new images and the new CSS go live with this step.)
2. In cPanel **Terminal** (or SSH):

   ```bash
   cd ~/repositories/amegatravelandtour
   bash scripts/post-deploy.sh --dry-run        # optional: see what it will do
   bash scripts/post-deploy.sh                  # backup, migrate, clear caches
   ```

   Add `--seed-cruise` **only on the release that introduces the cruise content** (see below).

The script: backs up the database to `~/backups` (keeps the newest 10, refuses to go on if the backup fails),
runs `php artisan migrate --force`, and runs `php artisan optimize:clear`.

## Never run on the live site

- `php artisan db:seed` with no class, or `--class=ServiceSeeder` / `TravelPackageSeeder`: they re-save the
  core services and sample packages and would **overwrite what you edited in the admin**. They are for a fresh
  install only.
- `php artisan migrate:fresh` or `migrate:refresh`: they wipe the database.

## One-off items for the release that adds cruises, commissions, the cashier SOA and the visa waiver

- `bash scripts/post-deploy.sh --seed-cruise` adds the 6 cruise packages and the "Cruise Services" card.
  Both seeders only create what is missing and never change something that exists, so running them twice is safe.
- Slots on each cruise date are a placeholder of 10: set the real numbers in **Admin > Travel Packages > Edit**.
- This release adds these tables or columns: package departures, inquiry follow-up and phone apps, the ticket
  passenger's frequent flyer number, the signed visa waiver on a booking, payment terms and the card charge on
  payments (existing payments are numbered automatically), and agent commissions.

## Smoke checks after deploying (2 minutes)

- Home page loads with its styling; **Our Core Services** shows 8 cards; **Cruise Packages** is in the menu.
- A package page shows its dates and slots.
- Sign in as admin: **Approvals** open, the approve form shows **Agent commission (₱)**, **Reports > Agent Commissions** opens.
- Sign in as the cashier: a booking's payment page shows **Payment type** (Full payment / Terms) and **Statement of Account**.
- Send a test inquiry from the contact form; it appears under **Inquiries**.
- The daily scheduler still runs: the cPanel cron `php artisan schedule:run` every minute is in place.

## If something goes wrong

1. Put the previous commit back: cPanel Git, check out the previous commit (the one you noted), **Deploy HEAD Commit**.
2. If the database changed in a way that must be undone, restore the backup the script made:

   ```bash
   gunzip -c ~/backups/<file>.sql.gz | mysql -u <user> -p <database>
   ```

   Restoring replaces **everything entered since the backup**, so use it only when the data itself is wrong.
   Migrations here only add things, so after a code rollback the extra tables and columns are harmless and can stay.
3. `php artisan optimize:clear` after any rollback.
