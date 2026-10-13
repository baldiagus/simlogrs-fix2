@props(['active' => 'opname'])

@php
    $portalKey = \App\Support\Portal\PortalResolver::resolve();
    $user = auth()->user();

    $canOpname = $user?->can('view_any_gudang_stok::stok::opname') || $user?->can('view_gudang_stok::stok::opname');
    $canPenyesuaian = $user?->can('view_any_gudang_stok::otorisasi::penyesuaian')
        || $user?->can('view_any_audit_manajemen::selisih::opname')
        || $user?->can('view_gudang_stok::otorisasi::penyesuaian');

    $hasGlobalRead = $user && $user->hasRole(['super_admin', 'admin_logistik', 'auditor', 'manajemen']);

    $pendingPenyesuaianCount = 0;
    if ($canPenyesuaian) {
        $pendingPenyesuaianCount = \App\Models\Persediaan\PenyesuaianStok::where('status', \App\Enums\Persediaan\StatusPenyesuaianStok::Menunggu->value)
            ->when(! $hasGlobalRead, fn ($q) => $q->where('gudang_id', $user?->gudang_id))
            ->count();
    }

    $totalTabs = ($canOpname ? 1 : 0) + ($canPenyesuaian ? 1 : 0);
@endphp

@if ($totalTabs > 1)
    <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60 mb-2">
        @if ($canOpname)
            <a
                href="/{{ $portalKey }}/stok-opname"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'opname' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="clipboard-document-check" class="size-3.5" />
                <span>Sesi Stock Opname</span>
            </a>
        @endif

        @if ($canPenyesuaian)
            <a
                href="/{{ $portalKey }}/otorisasi-penyesuaian"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'penyesuaian' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="check-badge" class="size-3.5" />
                <span>Otorisasi Penyesuaian</span>
                @if ($pendingPenyesuaianCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        {{ $pendingPenyesuaianCount }}
                    </span>
                @endif
            </a>
        @endif
    </div>
@endif
