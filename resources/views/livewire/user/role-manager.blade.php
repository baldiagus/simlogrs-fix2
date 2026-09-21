<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Manajemen Peran & Hak Akses (RBAC)</h1>
            <p class="text-sm text-zinc-500">Konfigurasi dinamis hak akses halaman dan tindakan CRUD 100% bebas hardcode role.</p>
        </div>

        @if ($selectedRole)
            <div class="flex items-center gap-3">
                <button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    type="button"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-lg shadow-sm transition-colors"
                >
                    <span wire:loading.remove wire:target="save">Simpan Perubahan</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
            </div>
        @endif
    </div>

    {{-- Layout Grid: Daftar Role di Kiri, Matriks di Kanan --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        {{-- Kolom Kiri: Pilihan Role --}}
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-sm space-y-2">
            <h2 class="text-xs font-semibold text-zinc-400 uppercase tracking-wider px-2 mb-2">Pilih Peran Sistem</h2>
            <div class="space-y-1">
                @foreach ($roles as $role)
                    <button
                        wire:click="selectRole({{ $role->id }})"
                        type="button"
                        class="w-full text-left px-3 py-2 text-sm rounded-lg font-medium transition-colors flex items-center justify-between {{ $selectedRoleId === $role->id ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 border border-blue-200 dark:border-blue-900' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}"
                    >
                        <span>{{ $role->name }}</span>
                        @if ($role->name === 'super_admin')
                            <span class="text-[10px] bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 px-1.5 py-0.5 rounded font-bold">Admin</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Kolom Kanan: Matriks Wewenang 5 Tab --}}
        <div class="lg:col-span-3 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-sm space-y-6">
            @if ($selectedRole)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <span class="text-xs text-zinc-400 uppercase">Mengatur Wewenang:</span>
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $selectedRole->name }}</h2>
                    </div>

                    @if ($selectedRole->name === 'super_admin')
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold text-amber-700 bg-amber-50 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-900 rounded-full">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <span>Anti Self-Lockout Aktif</span>
                        </div>
                    @endif
                </div>

                {{-- Tab Navigasi 5 Kolom --}}
                <div class="flex border-b border-zinc-200 dark:border-zinc-800 overflow-x-auto gap-2">
                    @php
                    $tabs = [
                        'pages' => 'Akses Halaman',
                        'view_any' => 'Lihat Data',
                        'create' => 'Tambah Data',
                        'update' => 'Ubah Data',
                        'delete' => 'Hapus Data',
                    ];
                    @endphp
                    @foreach ($tabs as $tabKey => $tabLabel)
                        <button
                            wire:click="setTab('{{ $tabKey }}')"
                            type="button"
                            class="px-4 py-2 text-sm font-medium border-b-2 whitespace-nowrap transition-colors {{ $activeTab === $tabKey ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}"
                        >
                            {{ $tabLabel }}
                        </button>
                    @endforeach
                </div>

                {{-- Konten Matriks per Domain --}}
                <div class="space-y-8">
                    @foreach ($domains as $domainKey => $domain)
                        @if ($activeTab !== 'pages' && $domainKey === 'dashboard_portal')
                            @continue {{-- Dashboard portal hanya memiliki izin akses halaman --}}
                        @endif

                        <div class="border border-zinc-200 dark:border-zinc-800 rounded-lg p-4 space-y-3 bg-zinc-50/50 dark:bg-zinc-950/20">
                            <div class="flex items-center justify-between pb-2 border-b border-zinc-200 dark:border-zinc-800">
                                <div>
                                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $domain['label'] }}</h3>
                                    <p class="text-xs text-zinc-500">{{ $domain['deskripsi'] }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        wire:click="toggleAllDomain('{{ $domainKey }}', '{{ $activeTab }}', true)"
                                        type="button"
                                        class="text-xs text-blue-600 hover:text-blue-800 font-medium"
                                    >Pilih Semua</button>
                                    <span class="text-zinc-300">|</span>
                                    <button
                                        wire:click="toggleAllDomain('{{ $domainKey }}', '{{ $activeTab }}', false)"
                                        type="button"
                                        class="text-xs text-zinc-500 hover:text-zinc-700 font-medium"
                                    >Kosongkan</button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 pt-1">
                                @foreach ($domain['modules'] as $modKey => $mod)
                                    @php
                                        $isSuperAdminLocked = ($selectedRole->name === 'super_admin' && $modKey === 'user::role');
                                    @endphp

                                    <label class="flex items-start gap-3 p-2 rounded hover:bg-white dark:hover:bg-zinc-800/60 transition-colors {{ $isSuperAdminLocked ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' }}">
                                        @if ($activeTab === 'pages')
                                            @php
                                                $permKey = ! empty($mod['page_only']) ? $mod['permission'] : ($mod['page'] ?? null);
                                            @endphp
                                            @if ($permKey)
                                                <input
                                                    type="checkbox"
                                                    wire:model="state.pages.{{ $permKey }}"
                                                    {{ $isSuperAdminLocked ? 'disabled checked' : '' }}
                                                    class="mt-1 rounded border-zinc-300 text-blue-600 focus:ring-blue-500"
                                                >
                                                <div class="text-xs">
                                                    <span class="font-medium text-zinc-900 dark:text-zinc-200">{{ $mod['label'] }}</span>
                                                    <span class="block font-mono text-[11px] text-zinc-400">{{ $permKey }}</span>
                                                </div>
                                            @endif
                                        @else
                                            @if (empty($mod['page_only']))
                                                @php
                                                    $entityKey = $mod['entity'];
                                                @endphp
                                                <input
                                                    type="checkbox"
                                                    wire:model="state.{{ $activeTab }}.{{ $entityKey }}"
                                                    {{ $isSuperAdminLocked && in_array($activeTab, ['view_any', 'update']) ? 'disabled checked' : '' }}
                                                    class="mt-1 rounded border-zinc-300 text-blue-600 focus:ring-blue-500"
                                                >
                                                <div class="text-xs">
                                                    <span class="font-medium text-zinc-900 dark:text-zinc-200">{{ $mod['label'] }}</span>
                                                    <span class="block font-mono text-[11px] text-zinc-400">{{ $activeTab }}_{{ $entityKey }}</span>
                                                </div>
                                            @endif
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-zinc-400">
                    <p class="text-sm font-medium">Pilih salah satu peran di panel kiri untuk mengatur wewenangnya.</p>
                </div>
            @endif
        </div>
    </div>
</div>
