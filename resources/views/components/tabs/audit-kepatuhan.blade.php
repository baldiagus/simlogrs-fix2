@props(['active' => 'trail'])

@php
    $portalKey = \App\Support\Portal\PortalResolver::resolve();
    $user = auth()->user();

    $canTrail = $user?->can('view_any_audit_manajemen::audit::trail');
    $canKepatuhan = $user?->can('view_any_audit_manajemen::kepatuhan::pengadaan');
    $canSelisih = $user?->can('view_any_audit_manajemen::selisih::opname');

    $totalTabs = ($canTrail ? 1 : 0) + ($canKepatuhan ? 1 : 0) + ($canSelisih ? 1 : 0);
@endphp

@if ($totalTabs > 1)
    <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60 mb-2">
        @if ($canTrail)
            <a
                href="/{{ $portalKey }}/audit-trail"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'trail' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="document-magnifying-glass" class="size-3.5" />
                <span>Audit Trail (Forensik)</span>
            </a>
        @endif

        @if ($canKepatuhan)
            <a
                href="/{{ $portalKey }}/kepatuhan-pengadaan"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'kepatuhan' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="shield-exclamation" class="size-3.5" />
                <span>Kepatuhan Pengadaan</span>
            </a>
        @endif

        @if ($canSelisih)
            <a
                href="/{{ $portalKey }}/selisih-opname"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'selisih' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="scale" class="size-3.5" />
                <span>Selisih Opname (Audit)</span>
            </a>
        @endif
    </div>
@endif
