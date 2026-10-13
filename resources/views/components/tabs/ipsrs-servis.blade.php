@props(['active' => 'lapor'])

@php
    $portalKey = \App\Support\Portal\PortalResolver::resolve();
    $user = auth()->user();

    $canLapor = $user?->can('view_any_layanan_unit::lapor::servis') || $user?->can('view_layanan_unit::lapor::servis');
    $canSpk = $user?->can('view_any_pemeliharaan::spk::perbaikan') || $user?->can('view_pemeliharaan::spk::perbaikan');
    $canLk = $user?->can('view_any_pemeliharaan::lembar::kerja') || $user?->can('view_pemeliharaan::lembar::kerja');

    $isDepartmentWide = $user && $user->hasRole(['super_admin', 'admin_logistik', 'kepala_teknisi', 'auditor', 'manajemen']);

    $pendingTiketCount = 0;
    if ($canSpk) {
        $pendingTiketCount = \App\Models\Ipsrs\PermintaanMaintenance::where('status', \App\Enums\Ipsrs\StatusMaintenance::Dilaporkan->value)->count();
    }

    $spkAktifCount = 0;
    if ($canLk) {
        $spkAktifQuery = \App\Models\Ipsrs\PenugasanMaintenance::where('status', \App\Enums\Ipsrs\StatusSpk::Ditugaskan->value);
        if (! $isDepartmentWide) {
            $spkAktifQuery->where('teknisi_id', $user->id);
        }
        $spkAktifCount = $spkAktifQuery->count();
    }

    $totalTabs = ($canLapor ? 1 : 0) + ($canSpk ? 1 : 0) + ($canLk ? 1 : 0);
@endphp

@if ($totalTabs > 1)
    <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60 mb-2">
        @if ($canLapor)
            <a
                href="/{{ $portalKey }}/lapor-servis"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'lapor' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="wrench" class="size-3.5" />
                <span>Lapor Kerusakan</span>
            </a>
        @endif

        @if ($canSpk)
            <a
                href="/{{ $portalKey }}/spk"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'spk' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="clipboard-document-check" class="size-3.5" />
                <span>Perintah Kerja (SPK)</span>
                @if ($pendingTiketCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        {{ $pendingTiketCount }}
                    </span>
                @endif
            </a>
        @endif

        @if ($canLk)
            <a
                href="/{{ $portalKey }}/lembar-kerja"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'lk' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="wrench-screwdriver" class="size-3.5" />
                <span>Lembar Kerja Servis</span>
                @if ($spkAktifCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                        {{ $spkAktifCount }}
                    </span>
                @endif
            </a>
        @endif
    </div>
@endif
