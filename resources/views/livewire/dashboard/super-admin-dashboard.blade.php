<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">Pusat Kendali Super Administrator</flux:heading>
            <flux:subheading>Tata kelola wewenang, parameter sistem, dan diagnostik multi-portal SIMLOG RS.</flux:subheading>
        </div>

        {{-- Emulasi Konteks Toolbar --}}
        <div class="flex flex-wrap items-center gap-2">
            <flux:modal.trigger name="modal-gudang">
                <flux:button variant="subtle" size="sm" icon="building-storefront">
                    Emulasi Gudang
                </flux:button>
            </flux:modal.trigger>

            <flux:modal.trigger name="modal-unit">
                <flux:button variant="subtle" size="sm" icon="building-office">
                    Emulasi Ruangan
                </flux:button>
            </flux:modal.trigger>

            @if ($contextManager->isEmulating())
                <flux:button wire:click="resetKonteksGlobal" variant="danger" size="sm">
                    Reset Mode Global
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Kartu Metrik Ringkasan --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-1 p-5">
            <flux:text size="sm" class="text-zinc-500">Status Sistem</flux:text>
            <div class="text-xl font-bold text-emerald-600 flex items-center gap-2">
                <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Normal & Siap</span>
            </div>
            <flux:text size="xs" class="text-zinc-400">PostgreSQL 16 & Redis 7 Online</flux:text>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text size="sm" class="text-zinc-500">Wewenang Terdaftar</flux:text>
            <div class="text-xl font-bold text-blue-600">275 Permissions</div>
            <flux:text size="xs" class="text-zinc-400">100% Zero-Hardcode Standard</flux:text>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text size="sm" class="text-zinc-500">Portal Kerja Aktif</flux:text>
            <div class="text-xl font-bold text-indigo-600">10 Portal Peran</div>
            <flux:text size="xs" class="text-zinc-400">Isolasi Hak Akses via Middleware</flux:text>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text size="sm" class="text-zinc-500">Integritas Persediaan</flux:text>
            <div class="text-xl font-bold text-emerald-600">Non-Negative Check</div>
            <flux:text size="xs" class="text-zinc-400">Pessimistic FEFO Lock Aktif</flux:text>
        </flux:card>
    </div>

    {{-- Akses Cepat Modul --}}
    <flux:card class="space-y-4 p-6">
        <flux:heading size="md">Akses Cepat Pengaturan Sistem</flux:heading>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            <a href="/super-admin/roles" class="p-4 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-start gap-3">
                <div class="p-2 bg-blue-100 dark:bg-blue-900/50 text-blue-600 rounded-lg">
                    <flux:icon icon="shield-check" class="w-5 h-5" />
                </div>
                <div>
                    <div class="font-semibold text-xs text-zinc-900 dark:text-zinc-100">Matriks Wewenang (RBAC)</div>
                    <flux:text size="xs" class="text-zinc-400">Konfigurasi dinamis permission peran sistem 5 kolom.</flux:text>
                </div>
            </a>
        </div>
    </flux:card>

    {{-- Modal Pilihan Emulasi Gudang --}}
    <flux:modal name="modal-gudang" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Pilih Gudang Emulasi</flux:heading>
                <flux:subheading>Simulasikan sudut pandang gudang logistik.</flux:subheading>
            </div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
                @forelse ($daftarGudang as $gudang)
                    <button
                        wire:click="setGudangEmulasi({{ $gudang->id }})"
                        x-on:click="$flux.modal('modal-gudang').close()"
                        type="button"
                        class="w-full text-left p-2.5 text-xs font-medium rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-between transition-colors"
                    >
                        <span>{{ $gudang->nama }}</span>
                        <span class="font-mono text-zinc-400">{{ $gudang->kode }}</span>
                    </button>
                @empty
                    <flux:text size="xs" class="text-center py-4 text-zinc-400">Belum ada gudang terdaftar.</flux:text>
                @endforelse
            </div>
        </div>
    </flux:modal>

    {{-- Modal Pilihan Emulasi Ruangan --}}
    <flux:modal name="modal-unit" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Pilih Ruangan / Unit Emulasi</flux:heading>
                <flux:subheading>Simulasikan sudut pandang ruangan rumah sakit.</flux:subheading>
            </div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
                @forelse ($daftarUnit as $unit)
                    <button
                        wire:click="setUnitEmulasi({{ $unit->id }})"
                        x-on:click="$flux.modal('modal-unit').close()"
                        type="button"
                        class="w-full text-left p-2.5 text-xs font-medium rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-between transition-colors"
                    >
                        <span>{{ $unit->nama }}</span>
                        <span class="font-mono text-zinc-400">{{ $unit->kode }}</span>
                    </button>
                @empty
                    <flux:text size="xs" class="text-center py-4 text-zinc-400">Belum ada unit terdaftar.</flux:text>
                @endforelse
            </div>
        </div>
    </flux:modal>
</div>
