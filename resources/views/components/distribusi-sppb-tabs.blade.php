@props(['active' => 'picking'])

@php
    $portalKey = request()->segment(1) ?? 'super-admin';
    $user = auth()->user();
    $canPicking = $user?->can('view_any_gudang_stok::picking::distribusi');
    $canTerima = $user?->can('view_any_layanan_unit::penerimaan::barang');

    $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard') || $user->hasRole(['super_admin', 'admin_logistik']));

    $siapPickingCount = 0;
    if ($canPicking) {
        $siapPickingCount = \App\Models\PermintaanDistribusi::where('status', 'DISETUJUI_GUDANG')
            ->when(! $isGlobalAdmin && $user?->gudang_id, fn ($q) => $q->where('gudang_id', $user->gudang_id))
            ->count();
    }

    $sedangDikirimCount = 0;
    if ($canTerima) {
        $sedangDikirimCount = \App\Models\PengirimanBarang::where('status', 'DIKIRIM')
            ->when(! $isGlobalAdmin && $user?->unit_id, fn ($q) => $q->where('unit_id', $user->unit_id))
            ->count();
    }

    $totalTabs = ($canPicking ? 1 : 0) + ($canTerima ? 1 : 0);
@endphp

@if ($totalTabs > 1)
    <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60 mb-2">
        @if ($canPicking)
            <a
                href="/{{ $portalKey }}/picking-distribusi"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'picking' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="queue-list" class="size-3.5" />
                <span>Picking FEFO (SPPB)</span>
                @if ($siapPickingCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        {{ $siapPickingCount }}
                    </span>
                @endif
            </a>
        @endif

        @if ($canTerima)
            <a
                href="/{{ $portalKey }}/penerimaan-barang"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'terima' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="inbox" class="size-3.5" />
                <span>Terima di Ruangan</span>
                @if ($sedangDikirimCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                        {{ $sedangDikirimCount }}
                    </span>
                @endif
            </a>
        @endif
    </div>
@endif
