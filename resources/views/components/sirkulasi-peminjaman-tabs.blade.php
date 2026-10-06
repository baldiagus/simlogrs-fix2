@props(['active' => 'pinjam'])

@php
    $portalKey = \App\Support\Portal\PortalResolver::resolve();
    $user = auth()->user();

    $canPinjam = $user?->can('view_any_layanan_unit::peminjaman::aset');
    $canApproval = $user?->can('view_any_layanan_unit::approval::peminjaman');
    $canPengalihan = $user?->can('view_any_layanan_unit::pengalihan::aset') || $user?->can('page_PengalihanAsetIndex') || $user?->hasRole(['super_admin', 'admin_logistik']);

    $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard') || $user->hasRole(['super_admin', 'admin_logistik']));

    $pendingApprovalCount = 0;
    if ($canApproval) {
        $pendingApprovalCount = \App\Models\PermintaanPeminjaman::where('status', 'DIAJUKAN')
            ->when(! $isGlobalAdmin && $user?->unit_id, fn ($q) => $q->where('unit_penyedia_id', $user->unit_id))
            ->when(! $isGlobalAdmin && $user?->gudang_id, fn ($q) => $q->where('gudang_penyedia_id', $user->gudang_id))
            ->count();
    }

    $totalTabs = ($canPinjam ? 1 : 0) + ($canApproval ? 1 : 0) + ($canPengalihan ? 1 : 0);
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
        @if ($canPengalihan)
            <a
                href="/{{ $portalKey }}/pengalihan-aset"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'pengalihan' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="arrows-right-left" class="size-3.5" />
                <span>Pengalihan & Mutasi</span>
            </a>
        @endif
    </div>
@endif
