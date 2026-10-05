@props(['active' => 'preventif'])

@php
    $portalKey = \App\Support\Portal\PortalResolver::resolve();
    $user = auth()->user();

    $canPreventif = $user?->can('view_any_pemeliharaan::pemeliharaan::berkala');
    $canRekamMedis = $user?->can('view_any_pemeliharaan::rekam::medis::aset');

    $duePreventifCount = 0;
    if ($canPreventif) {
        $duePreventifCount = \App\Models\JadwalMaintenance::where('status', 'AKTIF')
            ->where('tanggal_berikutnya', '<=', now()->toDateString())
            ->count();
    }

    $totalTabs = ($canPreventif ? 1 : 0) + ($canRekamMedis ? 1 : 0);
@endphp

@if ($totalTabs > 1)
    <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60 mb-2">
        @if ($canPreventif)
            <a
                href="/{{ $portalKey }}/jadwal-preventif"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'preventif' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="calendar-days" class="size-3.5" />
                <span>Jadwal Preventif</span>
                @if ($duePreventifCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        {{ $duePreventifCount }}
                    </span>
                @endif
            </a>
        @endif

        @if ($canRekamMedis)
            <a
                href="/{{ $portalKey }}/rekam-medis-aset"
                wire:navigate
                class="px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $active === 'rekam-medis' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                <flux:icon name="book-open" class="size-3.5" />
                <span>Rekam Medis Aset</span>
            </a>
        @endif
    </div>
@endif
