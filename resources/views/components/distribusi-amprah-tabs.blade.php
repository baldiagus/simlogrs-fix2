@use('App\Enums\StatusPermintaanDistribusi')
@props(['active' => 'permintaan'])

@php
    $portalKey = \App\Support\Portal\PortalResolver::resolve();
    $user = auth()->user();
    // Visibilitas tab memakai permission yang sama dengan guard halaman
    $canPermintaan = (bool) $user?->canAny(['view_any_layanan_unit::permintaan::barang', 'view_layanan_unit::permintaan::barang']);
    $canApprovalKaru = (bool) $user?->canAny(['view_any_layanan_unit::approval::karu', 'view_layanan_unit::approval::karu']);
    $canApprovalLogistik = (bool) $user?->canAny(['view_any_gudang_stok::distribusi::approval', 'view_gudang_stok::distribusi::approval']);

    // Scope ADR 0001: hanya super_admin/admin_logistik global; selain itu unit/gudang miliknya (termasuk yang dikepalai)
    $isGlobalAdmin = (bool) $user?->hasRole(['super_admin', 'admin_logistik']);
    $ownUnitIds = $user ? \App\Models\Unit::where('kepala_unit_id', $user->id)->pluck('id')->push($user->unit_id)->filter()->unique()->values()->all() : [];
    $ownGudangIds = $user ? \App\Models\Gudang::where('kepala_gudang_id', $user->id)->pluck('id')->push($user->gudang_id)->filter()->unique()->values()->all() : [];
    $applyScope = function ($q, $unitColumn = 'unit_id', $gudangColumn = 'gudang_id') use ($isGlobalAdmin, $ownUnitIds, $ownGudangIds) {
        if ($isGlobalAdmin) {
            return $q;
        }
        if ($unitColumn && $ownUnitIds) {
            return $q->whereIn($unitColumn, $ownUnitIds);
        }
        if ($gudangColumn && $ownGudangIds) {
            return $q->whereIn($gudangColumn, $ownGudangIds);
        }

        return ($ownUnitIds || $ownGudangIds) ? $q->whereRaw('1 = 0') : $q;
    };

    // Hitung antrean tertunda jika berhak
    $pendingKaruCount = 0;
    if ($canApprovalKaru) {
        $pendingKaruCount = $applyScope(\App\Models\PermintaanDistribusi::where('status', StatusPermintaanDistribusi::Diajukan->value))->count();
    }

    $pendingLogistikCount = 0;
    if ($canApprovalLogistik) {
        $pendingLogistikCount = $applyScope(\App\Models\PermintaanDistribusi::where('status', StatusPermintaanDistribusi::DisetujuiKaru->value))->count();
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
