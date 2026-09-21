<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Pusat Kendali Super Administrator</h1>
            <p class="text-xs sm:text-sm text-zinc-500">Tata kelola wewenang, parameter sistem, dan diagnostik multi-portal SIMLOG RS.</p>
        </div>

        {{-- Emulasi Konteks Toolbar --}}
        <div class="flex flex-wrap items-center gap-2">
            <button
                wire:click="$set('showGudangModal', true)"
                type="button"
                class="px-3 py-1.5 text-xs font-medium bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded-lg transition-colors flex items-center gap-1.5"
            >
                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.25A2.25 2.25 0 010 18.75V8.813a2.25 2.25 0 01.879-1.767l8.25-6.375a2.25 2.25 0 012.742 0l8.25 6.375A2.25 2.25 0 0121 8.813V18.75A2.25 2.25 0 0118.75 21H13.5z" />
                </svg>
                <span>Emulasi Gudang</span>
            </button>

            <button
                wire:click="$set('showUnitModal', true)"
                type="button"
                class="px-3 py-1.5 text-xs font-medium bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded-lg transition-colors flex items-center gap-1.5"
            >
                <svg class="w-3.5 h-3.5 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.75a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21" />
                </svg>
                <span>Emulasi Ruangan</span>
            </button>

            @if ($contextManager->isEmulating())
                <button
                    wire:click="resetKonteksGlobal"
                    type="button"
                    class="px-3 py-1.5 text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors flex items-center gap-1.5 shadow-sm"
                >
                    <span>Reset Global</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Kartu Metrik Ringkasan --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-sm space-y-1">
            <span class="text-xs font-medium text-zinc-500">Status Sistem</span>
            <div class="text-xl font-bold text-emerald-600 flex items-center gap-2">
                <span class="size-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Normal & Siap</span>
            </div>
            <p class="text-[11px] text-zinc-400">PostgreSQL 16 & Redis 7 Online</p>
        </div>

        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-sm space-y-1">
            <span class="text-xs font-medium text-zinc-500">Wewenang Terdaftar</span>
            <div class="text-xl font-bold text-blue-600">275 Permissions</div>
            <p class="text-[11px] text-zinc-400">100% Zero-Hardcode Standard</p>
        </div>

        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-sm space-y-1">
            <span class="text-xs font-medium text-zinc-500">Portal Kerja Aktif</span>
            <div class="text-xl font-bold text-indigo-600">10 Portal Peran</div>
            <p class="text-[11px] text-zinc-400">Isolasi Hak Akses via Middleware</p>
        </div>

        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-sm space-y-1">
            <span class="text-xs font-medium text-zinc-500">Integritas Persediaan</span>
            <div class="text-xl font-bold text-emerald-600">Non-Negative Check</div>
            <p class="text-[11px] text-zinc-400">Pessimistic FEFO Lock Aktif</p>
        </div>
    </div>

    {{-- Akses Cepat Modul --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-sm space-y-4">
        <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Akses Cepat Pengaturan Sistem</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            <a href="/super-admin/roles" class="p-4 border border-zinc-200 dark:border-zinc-800 rounded-lg hover:border-blue-500 hover:bg-blue-50/30 dark:hover:bg-blue-950/20 transition-all flex items-start gap-3">
                <div class="p-2 bg-blue-100 dark:bg-blue-900/50 text-blue-600 rounded-md">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-xs text-zinc-900 dark:text-zinc-100">Matriks Wewenang (RBAC)</div>
                    <p class="text-[11px] text-zinc-500">Konfigurasi dinamis permission peran sistem 5 kolom.</p>
                </div>
            </a>
        </div>
    </div>

    {{-- Modal Pilihan Emulasi Gudang --}}
    @if ($showGudangModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-900/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl max-w-md w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Pilih Gudang Emulasi</h3>
                    <button wire:click="$set('showGudangModal', false)" type="button" class="text-zinc-400 hover:text-zinc-600">&times;</button>
                </div>
                <div class="space-y-1 max-h-60 overflow-y-auto">
                    @forelse ($daftarGudang as $gudang)
                        <button
                            wire:click="setGudangEmulasi({{ $gudang->id }})"
                            type="button"
                            class="w-full text-left p-2.5 text-xs font-medium rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 flex items-center justify-between transition-colors"
                        >
                            <span>{{ $gudang->nama }}</span>
                            <span class="text-[10px] font-mono text-zinc-400">{{ $gudang->kode }}</span>
                        </button>
                    @empty
                        <p class="text-xs text-zinc-400 text-center py-4">Belum ada gudang terdaftar.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Pilihan Emulasi Ruangan --}}
    @if ($showUnitModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-900/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl max-w-md w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Pilih Ruangan / Unit Emulasi</h3>
                    <button wire:click="$set('showUnitModal', false)" type="button" class="text-zinc-400 hover:text-zinc-600">&times;</button>
                </div>
                <div class="space-y-1 max-h-60 overflow-y-auto">
                    @forelse ($daftarUnit as $unit)
                        <button
                            wire:click="setUnitEmulasi({{ $unit->id }})"
                            type="button"
                            class="w-full text-left p-2.5 text-xs font-medium rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 flex items-center justify-between transition-colors"
                        >
                            <span>{{ $unit->nama }}</span>
                            <span class="text-[10px] font-mono text-zinc-400">{{ $unit->kode }}</span>
                        </button>
                    @empty
                        <p class="text-xs text-zinc-400 text-center py-4">Belum ada unit terdaftar.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
