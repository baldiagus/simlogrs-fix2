@props(['active' => 'anggaran'])

@php
    $portalKey = \App\Support\Portal\PortalResolver::resolve();
    $user = auth()->user();

    $canAnggaran = $user?->can('view_any_pengadaan::realisasi::anggaran');
    $canRekanan = $user?->can('view_any_pengadaan::master::rekanan');

    $totalTabs = ($canAnggaran ? 1 : 0) + ($canRekanan ? 1 : 0);
@endphp

@if ($totalTabs > 1)
    <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60 mb-2">
        @if ($canAnggaran)
            <a
                href="/{{ $portalKey }}/realisasi-anggaran"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'anggaran' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="banknotes" class="size-3.5" />
                <span>Pagu & Realisasi</span>
            </a>
        @endif

        @if ($canRekanan)
            <a
                href="/{{ $portalKey }}/master-rekanan"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'rekanan' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="building-storefront" class="size-3.5" />
                <span>Evaluasi Rekanan</span>
            </a>
        @endif
    </div>
@endif
