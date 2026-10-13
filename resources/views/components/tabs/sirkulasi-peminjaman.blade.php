@props(['active' => 'pinjam'])

@php
    $portalKey = \App\Support\Portal\PortalResolver::resolve();
    $user = auth()->user();

    // Visibilitas tab = guard halaman: page_* rute DAN view_any_* / view_* modul.
    $canPinjam = $user?->can('page_PeminjamanAsetIndex')
        && ($user->can('view_any_layanan_unit::peminjaman::aset') || $user->can('view_layanan_unit::peminjaman::aset'));
    $canApproval = $user?->can('page_ApprovalPeminjamanIndex')
        && ($user->can('view_any_layanan_unit::approval::peminjaman') || $user->can('view_layanan_unit::approval::peminjaman'));

    // Hanya role global yang melihat antrean lintas unit/gudang; page_*Dashboard bukan bypass scope.
    $hasGlobalRead = $user && $user->hasRole(['super_admin', 'admin_logistik', 'auditor', 'manajemen']);

    $pendingApprovalCount = 0;
    if ($canApproval) {
        $pendingApprovalCount = \App\Models\Aset\PermintaanPeminjaman::where('status', 'DIAJUKAN')
            ->when(! $hasGlobalRead, function ($q) use ($user) {
                $q->where(function ($scope) use ($user) {
                    $scope->when($user?->unit_id, fn ($s) => $s->orWhere('unit_penyedia_id', $user->unit_id))
                        ->when($user?->gudang_id, fn ($s) => $s->orWhere('gudang_penyedia_id', $user->gudang_id))
                        ->when(! $user?->unit_id && ! $user?->gudang_id, fn ($s) => $s->whereRaw('1 = 0'));
                });
            })
            ->count();
    }

    $totalTabs = ($canPinjam ? 1 : 0) + ($canApproval ? 1 : 0);
@endphp

@if ($totalTabs > 1)
    <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60 mb-2">
        @if ($canPinjam)
            <a
                href="/{{ $portalKey }}/peminjaman-aset"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'pinjam' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="arrow-path" class="size-3.5" />
                <span>Permohonan Pinjam</span>
            </a>
        @endif

        @if ($canApproval)
            <a
                href="/{{ $portalKey }}/approval-peminjaman"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'approval' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="check-badge" class="size-3.5" />
                <span>Otorisasi Peminjaman</span>
                @if ($pendingApprovalCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        {{ $pendingApprovalCount }}
                    </span>
                @endif
            </a>
        @endif
    </div>
@endif
