<div>
    {{-- Modul Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Events
    </div>

    {{-- Abschnitt: Allgemein --}}
    <x-ui-sidebar-list label="Allgemein">
        <x-ui-sidebar-item :href="route('events.dashboard')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Abschnitt: Planung --}}
    <x-ui-sidebar-list label="Planung">
        <x-ui-sidebar-item :href="route('events.manage')">
            @svg('heroicon-o-calendar-days', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Veranstaltungen</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Abschnitt: Vorlagen --}}
    <x-ui-sidebar-list label="Vorlagen">
        <x-ui-sidebar-item :href="route('events.articles')">
            @svg('heroicon-o-archive-box', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Pakete</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Abschnitt: Verwaltung --}}
    <x-ui-sidebar-list label="Verwaltung">
        <x-ui-sidebar-item :href="route('events.settings')">
            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Einstellungen</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('events.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Dashboard">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('events.manage') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Veranstaltungen">
                @svg('heroicon-o-calendar-days', 'w-5 h-5')
            </a>
            <a href="{{ route('events.articles') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Pakete">
                @svg('heroicon-o-archive-box', 'w-5 h-5')
            </a>
            <a href="{{ route('events.settings') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Einstellungen">
                @svg('heroicon-o-cog-6-tooth', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
