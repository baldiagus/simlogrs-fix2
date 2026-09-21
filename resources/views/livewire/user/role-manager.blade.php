<div class="space-y-6">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">Manajemen Peran & Hak Akses (RBAC)</flux:heading>
            <flux:subheading>Pilih peran di panel kiri, lalu konfigurasikan wewenang Lihat Data, Tambah, Ubah, dan Hapus.</flux:subheading>
        </div>

        @if ($selectedRole)
            <flux:button wire:click="save" variant="primary" icon="check" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Simpan Perubahan Wewenang</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </flux:button>
        @endif
    </div>

    {{-- Layout Grid: Daftar Peran (1 Kolom) & Matriks Wewenang (3 Kolom) --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        {{-- Panel Kiri: Pilihan Peran Sistem --}}
        <flux:card class="space-y-3 p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <flux:heading size="sm" class="uppercase text-zinc-400 tracking-wider">Pilih Peran Sistem</flux:heading>
            <flux:navlist>
                @foreach ($roles as $role)
                    <flux:navlist.item
                        wire:click="selectRole({{ $role->id }})"
                        href="#"
                        :current="$selectedRoleId === $role->id"
                    >
                        <span>{{ $role->name }}</span>
                        @if ($role->name === 'super_admin')
                            <flux:badge size="sm" color="amber" class="ml-auto">Admin</flux:badge>
                        @endif
                    </flux:navlist.item>
                @endforeach
            </flux:navlist>
        </flux:card>

        {{-- Panel Kanan: Matriks Wewenang 4 Kolom --}}
        <div class="lg:col-span-3 space-y-6">
            @if ($selectedRole)
                <flux:card class="p-6 space-y-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
                    {{-- Header Peran Terpilih --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                        <div>
                            <flux:text size="xs" class="text-zinc-400 uppercase font-semibold">Konfigurasi Hak Akses Peran:</flux:text>
                            <div class="flex items-center gap-3 mt-0.5">
                                <flux:heading size="lg">{{ $selectedRole->name }}</flux:heading>
                                <flux:badge size="sm" color="blue">
                                    {{ $selectedRole->permissions->count() }} Wewenang Aktif
                                </flux:badge>
                            </div>
                        </div>

                        @if ($selectedRole->name === 'super_admin')
                            <flux:badge color="amber" icon="lock-closed">
                                Anti Self-Lockout Aktif (Role Manager Terkunci Penuh)
                            </flux:badge>
                        @endif
                    </div>

                    {{-- Panduan Otomatisasi (Cascade) --}}
                    <div class="p-3.5 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/80 dark:border-blue-900/60 text-xs text-blue-900 dark:text-blue-200 flex items-start gap-2.5">
                        <svg class="size-4 shrink-0 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                        <div>
                            <strong>Otomatisasi Akses Halaman:</strong> Mencengang <strong>Lihat Data</strong> otomatis mengaktifkan <em>Akses Halaman</em> ke modul bersangkutan. Mematikan <em>Lihat Data</em> otomatis mencabut izin Tambah, Ubah, dan Hapus pada modul tersebut.
                        </div>
                    </div>

                    {{-- BAGIAN 1: Akses Dashboard Portal (10 Portal) --}}
                    <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 space-y-4 bg-zinc-50/50 dark:bg-zinc-950/20">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                            <div>
                                <flux:heading size="sm">Akses Dashboard Portal</flux:heading>
                                <flux:text size="xs">Hak akses masuk ke masing-masing portal dari 10 ruang kerja peran.</flux:text>
                            </div>
                            <div class="flex gap-2">
                                <flux:button size="xs" variant="ghost" wire:click="selectAllDomain('dashboard_portal')">Pilih Semua</flux:button>
                                <flux:button size="xs" variant="ghost" wire:click="clearDomain('dashboard_portal')">Kosongkan</flux:button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                            @foreach ($domains['dashboard_portal']['modules'] as $modKey => $mod)
                                @php
                                    $permName = $mod['permission'];
                                    $isSuperAdminLocked = ($selectedRole->name === 'super_admin' && $permName === 'page_Dashboard');
                                @endphp
                                <div class="p-2.5 rounded-lg border border-zinc-200/70 dark:border-zinc-800/70 bg-white dark:bg-zinc-900">
                                    <flux:checkbox
                                        wire:model="state.dashboards.{{ $permName }}"
                                        :label="$mod['label']"
                                        :description="$permName"
                                        :disabled="$isSuperAdminLocked"
                                    />
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- BAGIAN 2: Matriks 4 Kolom Modul Operasional (Domain 2 s.d. 7) --}}
                    <div class="space-y-6">
                        @foreach ($domains as $domainKey => $domain)
                            @if ($domainKey === 'dashboard_portal')
                                @continue
                            @endif

                            <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden bg-white dark:bg-zinc-900 shadow-xs">
                                {{-- Domain Card Header --}}
                                <div class="p-4 bg-zinc-50/80 dark:bg-zinc-800/40 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div>
                                        <flux:heading size="sm">{{ $domain['label'] }}</flux:heading>
                                        <flux:text size="xs">{{ $domain['deskripsi'] }}</flux:text>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <flux:button size="xs" variant="ghost" wire:click="selectAllDomain('{{ $domainKey }}')">Semua Akses</flux:button>
                                        <span class="text-zinc-300">|</span>
                                        <flux:button size="xs" variant="ghost" wire:click="selectViewOnlyDomain('{{ $domainKey }}')">Lihat Saja</flux:button>
                                        <span class="text-zinc-300">|</span>
                                        <flux:button size="xs" variant="ghost" wire:click="clearDomain('{{ $domainKey }}')">Kosongkan</flux:button>
                                    </div>
                                </div>

                                {{-- Tabel 4 Kolom --}}
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs text-left">
                                        <thead>
                                            <tr class="border-b border-zinc-200 dark:border-zinc-800 text-zinc-500 uppercase tracking-wider font-semibold text-[11px] bg-zinc-50/30 dark:bg-zinc-900">
                                                <th class="py-3 px-4">Modul Operasional</th>
                                                <th class="py-3 px-4 text-center w-36">Lihat Data (Akses Halaman)*</th>
                                                <th class="py-3 px-4 text-center w-28">Tambah Data</th>
                                                <th class="py-3 px-4 text-center w-28">Ubah Data</th>
                                                <th class="py-3 px-4 text-center w-28">Hapus Data</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                                            @foreach ($domain['modules'] as $modKey => $mod)
                                                @php
                                                    $entityKey = $mod['entity'];
                                                    $isSuperAdminLocked = ($selectedRole->name === 'super_admin' && $modKey === 'user::role');
                                                    $hasViewAccess = ! empty($state['view_any'][$entityKey]);
                                                @endphp
                                                <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition-colors {{ $isSuperAdminLocked ? 'bg-amber-50/20 dark:bg-amber-950/10' : '' }}">
                                                    {{-- Nama Modul --}}
                                                    <td class="py-3 px-4">
                                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $mod['label'] }}</div>
                                                        <div class="font-mono text-[11px] text-zinc-400">{{ $entityKey }}</div>
                                                        @if ($isSuperAdminLocked)
                                                            <span class="inline-block text-[10px] text-amber-700 dark:text-amber-400 font-semibold mt-0.5">Terkunci (Anti Self-Lockout)</span>
                                                        @endif
                                                    </td>

                                                    {{-- 1. Lihat Data (Akses Halaman Otomatis) --}}
                                                    <td class="py-3 px-4 text-center">
                                                        <div class="flex justify-center">
                                                            <flux:checkbox
                                                                wire:click="toggleViewAny('{{ $entityKey }}')"
                                                                :checked="$hasViewAccess"
                                                                :disabled="$isSuperAdminLocked"
                                                            />
                                                        </div>
                                                    </td>

                                                    {{-- 2. Tambah Data --}}
                                                    <td class="py-3 px-4 text-center">
                                                        <div class="flex justify-center">
                                                            <flux:checkbox
                                                                wire:model="state.create.{{ $entityKey }}"
                                                                :disabled="$isSuperAdminLocked || ! $hasViewAccess"
                                                            />
                                                        </div>
                                                    </td>

                                                    {{-- 3. Ubah Data --}}
                                                    <td class="py-3 px-4 text-center">
                                                        <div class="flex justify-center">
                                                            <flux:checkbox
                                                                wire:model="state.update.{{ $entityKey }}"
                                                                :disabled="$isSuperAdminLocked || ! $hasViewAccess"
                                                            />
                                                        </div>
                                                    </td>

                                                    {{-- 4. Hapus Data --}}
                                                    <td class="py-3 px-4 text-center">
                                                        <div class="flex justify-center">
                                                            <flux:checkbox
                                                                wire:model="state.delete.{{ $entityKey }}"
                                                                :disabled="$isSuperAdminLocked || ! $hasViewAccess"
                                                            />
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @else
                <flux:card class="text-center py-16 text-zinc-400 border border-zinc-200 dark:border-zinc-800">
                    <flux:icon icon="shield-check" class="size-10 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                    <flux:heading size="md">Pilih Peran Sistem</flux:heading>
                    <flux:text size="sm">Silakan pilih salah satu peran di panel kiri untuk mengatur matriks wewenangnya.</flux:text>
                </flux:card>
            @endif
        </div>
    </div>
</div>
