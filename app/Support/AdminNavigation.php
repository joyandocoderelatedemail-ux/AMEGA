<?php

namespace App\Support;

use App\Models\User;

/**
 * The admin panel's primary navigation, shared by the desktop top bar and the
 * mobile menu so the two can never drift apart.
 *
 * Five entries, in this order: Services (the four counters), Clients, Users,
 * Reports & Analytics, and Contents (packages, bookings and the website).
 * A 'menu' opens a dropdown; a 'link' is a single button. Each item's `page`
 * is checked against User::canAccessPage(), and `admin` limits it to admins,
 * so an agent only ever sees what they may open. Empty groups are dropped.
 */
class AdminNavigation
{
    /**
     * @return list<array{key: string, label: string, icon: string, type: string, active: bool, items: list<array<string, mixed>>}>
     */
    public static function for(User $user): array
    {
        $notifications = AdminNotifications::for($user);

        $groups = [
            [
                'key' => 'services',
                'label' => 'Services',
                'icon' => 'briefcase',
                'type' => 'menu',
                'items' => [
                    self::item('Ticketing System', 'ticket', route('ticketing.tickets.create'), 'ticketing', request()->is('ticketing*')),
                    self::item('Immigration Counter', 'stamp', route('admin.immigration.dashboard'), 'immigration', request()->routeIs('admin.immigration.*', 'admin.client-sheets.*', 'admin.immigration-pricing.*', 'admin.immigration-categories.*')),
                    self::item('Visa Assistance', 'globe', route('visa.dashboard'), 'visa_assistance', request()->routeIs('visa.*')),
                    self::item('SRRV Desk', 'landmark', route('srrv.dashboard'), 'srrv', request()->routeIs('srrv.*')),
                    self::item('Ticket Approvals', 'badge-check', route('admin.ticket-approvals.index'), null, request()->routeIs('admin.ticket-approvals.*'), adminOnly: true),
                    self::item('Immigration Approvals', 'badge-check', route('admin.immigration-approvals.index'), null, request()->routeIs('admin.immigration-approvals.*'), adminOnly: true),
                    self::item('Visa Approvals', 'badge-check', route('admin.visa-approvals.index'), null, request()->routeIs('admin.visa-approvals.*'), adminOnly: true),
                    self::item('SRRV Approvals', 'badge-check', route('admin.srrv-approvals.index'), null, request()->routeIs('admin.srrv-approvals.*'), adminOnly: true),
                    self::item('Cashier', 'wallet', route('cashier.dashboard'), null, request()->is('cashier*'), adminOnly: true),
                ],
            ],
            [
                'key' => 'clients',
                'label' => 'Clients',
                'icon' => 'users',
                'type' => 'link',
                'items' => [
                    self::item('Clients', 'users', route('admin.users.index'), 'users', request()->routeIs('admin.users.*')),
                ],
            ],
            [
                'key' => 'users',
                'label' => 'Users',
                'icon' => 'user-check',
                'type' => 'link',
                'items' => [
                    self::item('Staff Accounts', 'user-check', route('admin.agents.index'), 'staff', request()->routeIs('admin.agents.*')),
                ],
            ],
            [
                'key' => 'reports',
                'label' => 'Reports & Analytics',
                'icon' => 'chart-column',
                'type' => 'menu',
                'items' => [
                    self::item('Dashboard & Analytics', 'layout-dashboard', route('admin.dashboard'), 'dashboard', request()->routeIs('admin.dashboard')),
                    self::item('Audit Logs', 'activity', route('admin.activity-logs.index'), null, request()->routeIs('admin.activity-logs.*'), adminOnly: true),
                ],
            ],
            [
                'key' => 'contents',
                'label' => 'Contents',
                'icon' => 'layout-template',
                'type' => 'menu',
                'items' => [
                    self::item('Travel Packages', 'package', route('admin.packages.index'), 'packages', request()->routeIs('admin.packages.index', 'admin.packages.create', 'admin.packages.edit')),
                    self::item('Package Configurator', 'sliders', route('admin.packages.configurator'), 'packages', request()->routeIs('admin.packages.configurator*', 'admin.packages.custom-inquiries.*')),
                    self::item('Bookings', 'calendar', route('admin.bookings.index'), 'bookings', request()->routeIs('admin.bookings.*'), badge: $notifications['bookings'], notify: 'bookings'),
                    self::item('Travel Insurance', 'shield-check', route('admin.insurance-plans.index'), null, request()->routeIs('admin.insurance-plans.*'), adminOnly: true),
                    self::item('Destinations', 'map-pin', route('admin.destinations.index'), 'destinations', request()->routeIs('admin.destinations.*')),
                    self::item('Inquiries', 'inbox', route('admin.inquiries.index'), 'inquiries', request()->routeIs('admin.inquiries.*'), badge: $notifications['inquiries'], notify: 'inquiries'),
                    self::item('CRM Pipeline', 'kanban', route('admin.crm.index'), 'crm', request()->routeIs('admin.crm.*')),
                    self::item('Live Guest Chats', 'messages-square', route('admin.chats.index'), 'chats', request()->routeIs('admin.chats.*'), live: true),
                    self::item('Website Services', 'briefcase', route('admin.services.index'), 'services', request()->routeIs('admin.services.*')),
                    self::item('Testimonials', 'message-square', route('admin.testimonials.index'), 'testimonials', request()->routeIs('admin.testimonials.*')),
                ],
            ],
        ];

        $visible = [];

        foreach ($groups as $group) {
            $group['items'] = array_values(array_filter(
                $group['items'],
                fn (array $item): bool => ! ($item['admin'] && ! $user->isAdmin())
                    && ($item['page'] === null || $user->canAccessPage($item['page']))
            ));

            if ($group['items'] === []) {
                continue;
            }

            $group['active'] = (bool) array_filter($group['items'], fn (array $item): bool => $item['active']);
            // The group's red badge adds up its items' counts.
            $group['badge'] = array_sum(array_column($group['items'], 'badge'));
            $group['notify'] = array_values(array_filter(array_column($group['items'], 'notify')));
            $visible[] = $group;
        }

        return $visible;
    }

    /**
     * @return array{label: string, icon: string, url: string, page: ?string, active: bool, admin: bool, live: bool, badge: int, notify: ?string}
     */
    private static function item(string $label, string $icon, string $url, ?string $page, bool $active, bool $adminOnly = false, bool $live = false, int $badge = 0, ?string $notify = null): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'url' => $url,
            'page' => $page,
            'active' => $active,
            'admin' => $adminOnly,
            'live' => $live,
            // A red count of what is new there, and the key the top bar's poll updates it by.
            'badge' => $badge,
            'notify' => $notify,
        ];
    }
}
