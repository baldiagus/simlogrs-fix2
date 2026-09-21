<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">Manajemen Peran & Hak Akses (RBAC)</flux:heading>
            <flux:subheading>Konfigurasi wewenang dinamis tanpa hardcode role via Spatie Permissions.</flux:subheading>
        </div>

        @if ($selectedRole)
            <flux:button wire:click="save" variant="primary" icon="check" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Simpan Perubahan Wewenang</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </flux:button>
        @endif
    </div>

    {{-- Layout Grid: Pilihan Role di Kiri, Matriks di Kanan --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        {{-- Daftar Role --}}
        <flux:card class="space-y-3 p-4">
            <flux:heading size="sm" class="uppercase text-zinc-400">Pilih Peran Sistem</flux:heading>
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

        {{-- Matriks Wewenang 5 Kolom --}}
        <flux:card class="lg:col-span-3 space-y-6 p-6">
            @if ($selectedRole)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <flux:text size="sm" class="text-zinc-400">Mengatur Hak Akses Untuk:</flux:text>
                        <flux:heading size="lg">{{ $selectedRole->name }}</flux:heading>
                    </div>

                    @if ($selectedRole->name === 'super_admin')
                        <flux:badge color="amber" icon="lock-closed">
                            Anti Self-Lockout Aktif (Akses Role Terkunci)
                        </flux:badge>
                    @endif
                </div>

                {{-- Tabs 5 Kolom --}}
                <flux:tab.group>
                    <flux:tabs wire:model="activeTab" variant="pills">
                        <flux:tab name="pages" wire:click="setTab('pages')">Akses Halaman</flux:tab>
                        <flux:tab name="view_any" wire:click="setTab('view_any')">Lihat Data</flux:tab>
                        <flux:tab name="create" wire:click="setTab('create')">Tambah Data</flux:tab>
                        <flux:tab name="update" wire:click="setTab('update')">Ubah Data</flux:tab>
                        <flux:tab name="delete" wire:click="setTab('delete')">Hapus Data</flux:tab>
                    </flux:tabs>
                </flux:tab.group>

                {{-- Konten Matriks per Domain --}}
                <div class="space-y-6">
                    @foreach ($domains as $domainKey => $domain)
                        @if ($activeTab !== 'pages' && $domainKey === 'dashboard_portal')
                            @continue
                        @endif

                        <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 space-y-3 bg-zinc-50/50 dark:bg-zinc-950/20">
                            <div class="flex items-center justify-between pb-2 border-b border-zinc-200 dark:border-zinc-800">
                                <div>
                                    <flux:heading size="sm">{{ $domain['label'] }}</flux:heading>
                                    <flux:text size="xs">{{ $domain['deskripsi'] }}</flux:text>
                                </div>
                                <div class="flex gap-2">
                                    <flux:button size="xs" variant="ghost" wire:click="toggleAllDomain('{{ $domainKey }}', '{{ $activeTab }}', true)">
                                        Pilih Semua
                                    </flux:button>
                                    <flux:button size="xs" variant="ghost" wire:click="toggleAllDomain('{{ $domainKey }}', '{{ $activeTab }}', false)">
                                        Kosongkan
                                    </flux:button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                                @foreach ($domain['modules'] as $modKey => $mod)
                                    @php
                                        $isSuperAdminLocked = ($selectedRole->name === 'super_admin' && $modKey === 'user::role');
                                    @endphp

                                    @if ($activeTab === 'pages')
                                        @php
                                            $permKey = ! empty($mod['page_only']) ? $mod['permission'] : ($mod['page'] ?? null);
                                        @endphp
                                        @if ($permKey)
                                            <div class="p-2.5 rounded-lg border border-zinc-200/60 dark:border-zinc-800/60 bg-white dark:bg-zinc-900 flex items-start gap-2">
                                                <flux:checkbox
                                                    wire:model="state.pages.{{ $permKey }}"
                                                    :label="$mod['label']"
                                                    :description="$permKey"
                                                    :disabled="$isSuperAdminLocked"
                                                />
                                            </div>
                                        @endif
                                    @else
                                        @if (empty($mod['page_only']))
                                            @php
                                                $entityKey = $mod['entity'];
                                            @endphp
                                            <div class="p-2.5 rounded-lg border border-zinc-200/60 dark:border-zinc-800/60 bg-white dark:bg-zinc-900 flex items-start gap-2">
                                                <flux:checkbox
                                                    wire:model="state.{{ $activeTab }}.{{ $entityKey }}"
                                                    :label="$mod['label']"
                                                    :description="$activeTab . '_' . $entityKey"
                                                    :disabled="$isSuperAdminLocked && in_array($activeTab, ['view_any', 'update'])"
                                                />
                                            </div>
                                        @endif
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-zinc-400">
                    <flux:text>Pilih salah satu peran di panel kiri untuk mengatur wewenang.</flux:text>
                </div>
            @endif
        </flux:card>
    </div>
</div>
