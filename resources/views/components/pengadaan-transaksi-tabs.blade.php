@props(['active' => 'pr'])

@php
    $portalKey = \App\Support\Portal\PortalResolver::resolve();
    $user = auth()->user();

    $canPr = $user?->can('view_any_pengadaan::permintaan::pengadaan');
    $canPo = $user?->can('view_any_pengadaan::pesanan::pembelian');
    $canRcv = $user?->can('view_any_gudang_stok::penerimaan::po');

    $pendingPrCount = $canPo ? \App\Models\PermintaanPengadaan::where('status', \App\Models\PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN)->doesntHave('pesananPembelian')->count() : 0;
    $pendingInboundCount = $canRcv ? \App\Models\PesananPembelian::whereIn('status', ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])->count() : 0;

    $totalTabs = ($canPr ? 1 : 0) + ($canPo ? 1 : 0) + ($canRcv ? 1 : 0);
@endphp

@if ($totalTabs > 1)
    <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60 mb-2">
        @if ($canPr)
            <a
                href="/{{ $portalKey }}/permintaan-pengadaan"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'pr' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="document-text" class="size-3.5" />
                <span>Permintaan (PR)</span>
            </a>
        @endif

        @if ($canPo)
            <a
                href="/{{ $portalKey }}/pesanan-pembelian"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'po' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="clipboard-document-list" class="size-3.5" />
                <span>Pesanan (PO)</span>
                @if ($pendingPrCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        {{ $pendingPrCount }}
                    </span>
                @endif
            </a>
        @endif

        @if ($canRcv)
            <a
                href="/{{ $portalKey }}/penerimaan-po"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'rcv' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="inbox-arrow-down" class="size-3.5" />
                <span>Penerimaan (Inbound)</span>
                @if ($pendingInboundCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                        {{ $pendingInboundCount }}
                    </span>
                @endif
            </a>
        @endif
    </div>
@endif
