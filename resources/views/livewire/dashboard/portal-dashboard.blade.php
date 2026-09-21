<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $portalTitle }}</h1>
            <p class="text-xs sm:text-sm text-zinc-500">{{ $portalSubtitle }}</p>
        </div>
        <div>
            <x-status-badge status="AKTIF" />
        </div>
    </div>

    {{-- Kartu Metrik Operasional --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-sm space-y-1">
            <span class="text-xs font-medium text-zinc-500">Status Wewenang</span>
            <div class="text-lg font-bold text-emerald-600">Terverifikasi</div>
            <p class="text-[11px] text-zinc-400">100% Dynamic RBAC Active</p>
        </div>
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-sm space-y-1">
            <span class="text-xs font-medium text-zinc-500">Sistem Logistik</span>
            <div class="text-lg font-bold text-blue-600">Operasional</div>
            <p class="text-[11px] text-zinc-400">SIMLOG RS v2 Livewire</p>
        </div>
    </div>
</div>
