@props(['active' => 'aset'])

@php
    $portalKey = \App\Support\Portal\PortalResolver::resolve();
    $user = auth()->user();

    // Gudang & Admin Sentral boleh memutasi barang dan aset; Unit Ruangan hanya boleh aset
    $isGudangOrAdmin = $user && (
        $user->can('view_any_gudang_stok::pengalihan::barang') ||
        $user->can('view_gudang_stok::pengalihan::barang') ||
        false
    );

    $canBarang = $isGudangOrAdmin;
    $canAset = $user && ($user->can('view_any_layanan_unit::pengalihan::aset') || $user->can('view_layanan_unit::pengalihan::aset'));

    $totalTabs = ($canBarang ? 1 : 0) + ($canAset ? 1 : 0);
@endphp

@if ($totalTabs > 1)
    <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60 mb-2">
        @if ($canBarang)
            <a
                href="/{{ $portalKey }}/pengalihan-barang"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'barang' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="cube" class="size-3.5" />
                <span>Pengalihan Barang (BHP)</span>
            </a>
        @endif

        @if ($canAset)
            <a
                href="/{{ $portalKey }}/pengalihan-aset"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'aset' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="computer-desktop" class="size-3.5" />
                <span>Pengalihan Aset & Sarana</span>
            </a>
        @endif
    </div>
@endif
