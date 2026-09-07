@props([
    'default' => 'profile',
    'sections' => [
        [
            'id' => 'profile',
            'label' => 'Profile',
            'icon' => 'user',
            'title' => 'Profile Settings',
            'description' => 'Update your display name, avatar, and contact information.',
        ],
        [
            'id' => 'notifications',
            'label' => 'Notifications',
            'icon' => 'bell',
            'title' => 'Notification Preferences',
            'description' => 'Control how and when you receive alerts and digest emails.',
        ],
        [
            'id' => 'billing',
            'label' => 'Billing',
            'icon' => 'credit-card',
            'title' => 'Billing & Plans',
            'description' => 'Manage your subscription, invoices, and payment methods.',
        ],
        [
            'id' => 'security',
            'label' => 'Security',
            'icon' => 'shield',
            'title' => 'Security Settings',
            'description' => 'Set a strong password and enable two-factor authentication.',
        ],
    ]
])

<div x-data="{ active: '{{ $default }}' }" class="w-full max-w-sm rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden font-body">
    <div class="flex">
        <!-- Vertical Tab Navigation -->
        <nav class="flex w-36 shrink-0 flex-col gap-1 border-r border-gray-100 p-2.5 bg-gray-50/60">
            @foreach($sections as $section)
                <button
                    type="button"
                    @click="active = '{{ $section['id'] }}'"
                    :class="active === '{{ $section['id'] }}' 
                        ? 'bg-primary text-white shadow-sm font-semibold' 
                        : 'text-gray-600 hover:bg-gray-100 hover:text-dark font-medium'"
                    class="flex items-center gap-2 rounded-xl px-3 py-2 text-left text-xs transition-all">
                    <i data-lucide="{{ $section['icon'] }}" class="w-3.5 h-3.5 shrink-0"></i>
                    <span>{{ $section['label'] }}</span>
                </button>
            @endforeach
        </nav>

        <!-- Dynamic Panel Content -->
        <div class="flex-1 p-4 flex flex-col justify-center">
            @foreach($sections as $section)
                <div x-show="active === '{{ $section['id'] }}'" x-transition.opacity.duration.200ms class="space-y-1">
                    <h3 class="font-heading font-bold text-sm text-dark">{{ $section['title'] }}</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">{{ $section['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</div>
