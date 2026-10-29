<?php

namespace App\Support;

/**
 * The rule book: every action a staff member can be allowed, by module. A permission is
 * "module.action" (e.g. "immigration.edit", "srrv.approve", "prices.manage"). Roles (StaffRole) are
 * named sets of these; a staff member's access is their role's set plus any extra grants
 * (User::effectivePermissions). Admins pass everything.
 *
 * Deleting for good is not a permission: only admins do it. Everyone else cancels.
 */
class Permissions
{
    /** The four desks. Each keeps its files private to whoever opened them unless granted view_all. */
    public const DESKS = ['ticketing', 'immigration', 'visa_assistance', 'srrv'];

    /** What each action means, for the staff screens. */
    public const ACTIONS = [
        'view' => 'Open',
        'create' => 'Create',
        'edit' => 'Edit',
        'view_all' => "See everyone's files",
        'approve' => 'Approve',
        'refund' => 'Refund & cancel paid',
        'record_payment' => 'Record payments',
        'manage' => 'Manage',
        'activity_logs' => 'Activity logs',
        'message_templates' => 'Message templates',
    ];

    /**
     * Module => [label, icon, group, actions].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: list<string>}>
     */
    public const MODULES = [
        // Services: the desks
        'ticketing' => ['Ticketing', 'ticket', 'Services', ['view', 'create', 'edit', 'view_all', 'approve', 'refund']],
        'immigration' => ['Immigration Counter', 'stamp', 'Services', ['view', 'create', 'edit', 'view_all', 'approve']],
        'visa_assistance' => ['Visa Assistance', 'globe', 'Services', ['view', 'create', 'edit', 'view_all', 'approve']],
        'srrv' => ['SRRV Desk', 'landmark', 'Services', ['view', 'create', 'edit', 'view_all', 'approve']],
        'cashier' => ['Cashier', 'wallet', 'Services', ['view', 'record_payment']],
        'bookings' => ['Online Bookings', 'calendar', 'Services', ['view', 'create', 'edit']],
        // Clients
        'users' => ['Clients', 'users', 'Clients', ['view', 'create', 'edit']],
        'corporates' => ['Corporate Accounts', 'building-2', 'Clients', ['view', 'create', 'edit']],
        'crm' => ['CRM Pipeline', 'kanban', 'Clients', ['view', 'create', 'edit']],
        'inquiries' => ['Inquiries', 'inbox', 'Clients', ['view', 'edit']],
        'chats' => ['Live Guest Chats', 'messages-square', 'Clients', ['view', 'edit']],
        // Staff
        'staff' => ['Staff Directory', 'user-check', 'Staff / Users', ['view']],
        // Reports
        'dashboard' => ['Dashboard & Analytics', 'layout-dashboard', 'Reports & Analytics', ['view']],
        'reports' => ['Reports', 'bar-chart-3', 'Reports & Analytics', ['activity_logs', 'message_templates']],
        // Contents
        'packages' => ['Travel Packages', 'package', 'Contents', ['view', 'create', 'edit']],
        'destinations' => ['Destinations', 'map-pin', 'Contents', ['view', 'create', 'edit']],
        'services' => ['Website Services', 'briefcase', 'Contents', ['view', 'create', 'edit']],
        'testimonials' => ['Testimonials', 'message-square', 'Contents', ['view', 'create', 'edit']],
        'faqs' => ['FAQs', 'help-circle', 'Contents', ['view', 'create', 'edit']],
        // Money settings
        'prices' => ['Prices & Catalogs', 'tags', 'Settings', ['manage']],
    ];

    /** Every permission key there is. */
    public static function all(): array
    {
        $keys = [];
        foreach (self::MODULES as $module => [, , , $actions]) {
            foreach ($actions as $action) {
                $keys[] = "{$module}.{$action}";
            }
        }

        return $keys;
    }

    public static function exists(string $key): bool
    {
        return in_array($key, self::all(), true);
    }

    /** Keep only real permission keys, each once, in catalog order. */
    public static function clean(iterable $keys): array
    {
        $given = collect($keys)->map(fn ($key) => (string) $key)->all();

        return array_values(array_filter(self::all(), fn (string $key) => in_array($key, $given, true)));
    }

    /** The day-to-day work of a desk or page: open it, create and edit (own files only for desks). */
    public static function work(string $module): array
    {
        $actions = self::MODULES[$module][3] ?? [];

        return array_values(array_map(fn ($action) => "{$module}.{$action}", array_intersect($actions, ['view', 'create', 'edit'])));
    }

    /**
     * What an account set up the old way (ticked pages, or a desk role) can do: exactly what it could
     * do before roles and permissions existed, so nobody gains or loses access when this ships.
     *
     * @param  list<string>|null  $allowedPages
     */
    public static function fromLegacy(string $role, ?array $allowedPages): array
    {
        $deskRoles = ['ticketing' => 'ticketing', 'visa_assistance' => 'visa_assistance', 'srrv' => 'srrv'];

        if (isset($deskRoles[$role])) {
            return self::clean(self::forPages([$deskRoles[$role]]));
        }

        if ($role === 'cashier') {
            return ['cashier.view', 'cashier.record_payment'];
        }

        if ($role !== 'agent') {
            return [];
        }

        // An agent made before the pages were ticked one by one had this set. (The CRM is its own page
        // now, no longer implied by the inquiries or bookings.)
        $pages = $allowedPages ?? ['dashboard', 'bookings', 'inquiries', 'crm', 'users', 'packages', 'destinations', 'chats'];

        return self::clean(self::forPages($pages));
    }

    /**
     * The permissions a ticked page carries: open, create and edit what is there (a desk: their own
     * files). Seeing everyone's files, approving, refunding and managing prices are never implied by
     * a page; an admin grants them. The client directory comes with the corporate accounts.
     */
    private static function forPages(array $pages): array
    {
        $keys = [];
        foreach ($pages as $page) {
            $keys = [...$keys, ...self::work($page)];
        }

        if (in_array('users', $pages, true)) {
            $keys = [...$keys, ...self::work('corporates')];
        }

        return $keys;
    }

    /**
     * The roles every install starts with. Admins can edit their permissions (not delete them) and add more.
     *
     * @return array<string, array{description: string, permissions: list<string>}>
     */
    public static function systemRoles(): array
    {
        $desk = fn (string $d) => self::work($d);

        return [
            'Ticketing Agent' => ['description' => 'Books flights and tour packages; sees only the bookings they made.', 'permissions' => $desk('ticketing')],
            'Immigration Agent' => ['description' => 'Works the BI counter; sees only the client folders they opened.', 'permissions' => $desk('immigration')],
            'Visa Assistance Agent' => ['description' => 'Visit visas, e-visas and passporting; sees only their own files.', 'permissions' => $desk('visa_assistance')],
            'SRRV Officer' => ['description' => 'PRA retiree visas and ID renewals; sees only their own files.', 'permissions' => $desk('srrv')],
            'Cashier' => ['description' => 'Records payments on every approved booking.', 'permissions' => ['cashier.view', 'cashier.record_payment']],
            'Front Desk' => ['description' => 'Answers inquiries and chats, handles online bookings and looks up clients.', 'permissions' => [
                ...self::work('inquiries'), ...self::work('chats'), ...self::work('bookings'), ...self::work('crm'), 'users.view',
            ]],
            'Supervisor' => ['description' => 'Sees every desk file and approves them (never their own).', 'permissions' => collect(self::DESKS)
                ->flatMap(fn (string $d) => [...self::work($d), "{$d}.view_all", "{$d}.approve"])->push('dashboard.view')->all()],
            'Content Editor' => ['description' => 'Keeps the website up to date: packages, destinations, services, reviews and FAQs.', 'permissions' => collect(['packages', 'destinations', 'services', 'testimonials', 'faqs'])
                ->flatMap(fn (string $m) => self::work($m))->all()],
        ];
    }
}
