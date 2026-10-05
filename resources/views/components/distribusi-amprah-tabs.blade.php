@props(['active' => 'permintaan'])

@php
    $portalKey = request()->segment(1) ?? 'super-admin';
    $user = auth()->user();
    $canPermintaan = $user?->can('view_any_layanan_unit::permintaan::barang');
    $canApprovalKaru = $user?->can('view_any_layanan_unit::approval::karu');
    $canApprovalLogistik = $user?->can('view_any_gudang_stok::distribusi::approval');

    $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard') || $user->hasRole(['super_admin', 'admin_logistik']));

    // Hitung antrean tertunda jika berhak
    $pendingKaruCount = 0;
    if ($canApprovalKaru) {
        $pendingKaruCount = \App\Models\PermintaanDistribusi::where('status', 'DIAJUKAN')
            ->when(! $isGlobalAdmin && $user?->unit_id, fn ($q) => $q->where('unit_id', $user->unit_id))
            ->count();
    }

    $pendingLogistikCount = 0;
    if ($canApprovalLogistik) {
        $pendingLogistikCount = \App\Models\PermintaanDistribusi::where('status', 'DISETUJUI_KARU')->count();
    }

    $totalTabs = ($canPermintaan ? 1 : 0) + ($canApprovalKaru ? 1 : 0) + ($canApprovalLogistik ? 1 : 0);
@endphp

@if ($totalTabs > 1)
    <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60 mb-2">
        @if ($canPermintaan)
            <a
                href="/{{ $portalKey }}/permintaan-barang"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'permintaan' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="arrow-up-tray" class="size-3.5" />
                <span>Permintaan Amprah</span>
            </a>
        @endif

        @if ($canApprovalKaru)
            <a
                href="/{{ $portalKey }}/approval-permintaan"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'approval-karu' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="check-badge" class="size-3.5" />
                <span>Otorisasi Karu</span>
                @if ($pendingKaruCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        {{ $pendingKaruCount }}
                    </span>
                @endif
            </a>
        @endif

        @if ($canApprovalLogistik)
            <a
                href="/{{ $portalKey }}/distribusi-approval"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'approval-logistik' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="clipboard-document-check" class="size-3.5" />
                <span>Otorisasi Logistik</span>
                @if ($pendingLogistikCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                        {{ $pendingLogistikCount }}
                    </span>
                @endif
            </a>
        @endif
    </div>
@endif
