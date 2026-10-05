@props(['active' => 'fefo'])

@php
    $portalKey = \App\Support\Portal\PortalResolver::resolve();
    $user = auth()->user();

    $canFefo = $user?->can('view_any_gudang_stok::monitoring::fefo');
    $canMutasi = $user?->can('view_any_gudang_stok::buku::mutasi');

    $totalTabs = ($canFefo ? 1 : 0) + ($canMutasi ? 1 : 0);
@endphp

@if ($totalTabs > 1)
    <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60 mb-2">
        @if ($canFefo)
            <a
                href="/{{ $portalKey }}/monitoring-fefo"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'fefo' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="clock" class="size-3.5" />
                <span>Monitoring Saldo & FEFO</span>
            </a>
        @endif

        @if ($canMutasi)
            <a
                href="/{{ $portalKey }}/buku-mutasi"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'mutasi' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="document-magnifying-glass" class="size-3.5" />
                <span>Kartu Mutasi Stok</span>
            </a>
        @endif
    </div>
@endif
