<div class="space-y-6">
    {{-- Header & Tombol Tambah --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight">Manajemen Akun Pengguna</flux:heading>
            <flux:subheading class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                Buku induk akun pegawai kedinasan rumah sakit, penetapan peran RBAC, dan unit penugasan operasional.
            </flux:subheading>
        </div>

        @can('create_user::pengguna')
            <flux:button
                wire:click="openCreate"
                variant="primary"
                size="sm"
                icon="user-plus"
            >
                Tambah Pengguna Baru
            </flux:button>
        @endcan
    </div>

    {{-- 3 Kartu Ringkasan Akun (Bersih Tanpa Stripe Dekoratif - Anti Slop R-01 & R-31) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Akun Pegawai</span>
                <flux:icon name="users" class="size-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalPengguna }}
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Pegawai terdaftar dalam direktori SIMLOG RS</p>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Akun Aktif Beroperasi</span>
                <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">
                {{ $penggunaAktif }}
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Dapat melakukan otentikasi login ke sistem</p>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Akun Nonaktif / Dibatasi</span>
                <flux:icon name="no-symbol" class="size-5 text-zinc-500 dark:text-zinc-400" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-700 dark:text-zinc-300 tabular-nums">
                {{ $penggunaNonaktif }}
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Hak akses login ditangguhkan sementara</p>
        </flux:card>
    </div>

    {{-- Filter Toolbar & Tabel Pengguna --}}
    <flux:card class="space-y-4 shadow-sm border border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="lg">Direktori Akun Pegawai Kedinasan</flux:heading>
            <flux:subheading class="text-xs text-zinc-600 dark:text-zinc-400">
                Gunakan filter pencarian untuk menemukan pegawai berdasarkan NIP, nama, atau peranan.
            </flux:subheading>
        </div>

            <div class="flex flex-wrap items-center gap-2.5">
                {{-- Search Bar --}}
                <div class="w-full sm:w-72">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari nama, NIP, username..."
                        icon="magnifying-glass"
                        clearable
                    />
                </div>

                {{-- Filter Peran --}}
                <div class="w-full sm:w-44">
                    <select
                        wire:model.live="filterRole"
                        class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    >
                        <option value="">Semua Peran</option>
                        @foreach ($daftarRoles as $roleItem)
                            <option value="{{ $roleItem->name }}">{{ ucwords(str_replace('_', ' ', $roleItem->name)) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Status --}}
                <div class="w-full sm:w-36">
                    <select
                        wire:model.live="filterStatus"
                        class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    >
                        <option value="">Semua Status</option>
                        <option value="AKTIF">Hanya Aktif</option>
                        <option value="NONAKTIF">Nonaktif</option>
                    </select>
                </div>

                @if ($search || $filterRole || $filterStatus)
                    <flux:button
                        wire:click="resetFilters"
                        size="sm"
                        variant="subtle"
                        icon="x-mark"
                    >
                        Reset Filter
                    </flux:button>
                @endif
            </div>

        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-64">Pegawai</flux:table.column>
                <flux:table.column class="w-48">Kredensial</flux:table.column>
                <flux:table.column class="text-center w-32">Peran Utama</flux:table.column>
                <flux:table.column class="w-56">Unit / Penugasan</flux:table.column>
                <flux:table.column class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="w-36">Terakhir Login</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                    @forelse ($users as $userItem)
                        @php
                            $primaryRole = $userItem->roles->firstWhere('name', '!=', 'panel_user');
                            $roleLabel = $primaryRole ? ucwords(str_replace('_', ' ', $primaryRole->name)) : 'Pengguna';
                            $roleColor = match($primaryRole?->name) {
                                'super_admin'    => 'blue',
                                'admin_logistik' => 'sky',
                                'kepala_gudang'  => 'amber',
                                'petugas_gudang' => 'emerald',
                                'pengadaan'      => 'indigo',
                                'kepala_unit'    => 'cyan',
                                'petugas_unit'   => 'teal',
                                'teknisi'        => 'violet',
                                'manajemen'      => 'zinc',
                                'auditor'        => 'rose',
                                default          => 'zinc',
                            };
                            $initials = collect(explode(' ', $userItem->nama))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->join('');
                            $isTargetSuperAdmin = $userItem->hasRole('super_admin');
                            $canManageUser = auth()->user()?->hasRole('super_admin') || ! $isTargetSuperAdmin;
                        @endphp
                    <flux:table.row :key="$userItem->id">
                        <flux:table.cell style="white-space: normal;">
                                <div class="flex items-center gap-2.5">
                                    <div class="size-8 rounded-full bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center font-bold font-mono text-[11px] text-zinc-700 dark:text-zinc-300 shrink-0">
                                        {{ $initials ?: 'U' }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $userItem->nama }}</div>
                                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">NIP: {{ $userItem->nip ?: '-' }}</div>
                                    </div>
                                </div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono" style="white-space: normal;">
                                <div class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $userItem->username }}</div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400">{{ $userItem->email }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                                <flux:badge size="sm" :color="$roleColor">
                                    {{ $roleLabel }}
                                </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell style="white-space: normal;">
                                @if ($userItem->unit)
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $userItem->unit->nama }}</div>
                                    <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">Ruangan Pelayanan</div>
                                @elseif ($userItem->gudang)
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $userItem->gudang->nama }}</div>
                                    <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">Gudang Logistik</div>
                                @else
                                    <div class="text-zinc-400">Pusat / Sentral</div>
                                @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                                <flux:badge size="sm" :color="$userItem->status === 'AKTIF' ? 'emerald' : 'zinc'">
                                    {{ $userItem->status }}
                                </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-[11px] whitespace-nowrap">
                                @if ($userItem->terakhir_login)
                                    <div>{{ $userItem->terakhir_login->format('d/m/Y H:i') }} WIB</div>
                                    @if ($userItem->ip_terakhir)
                                        <div class="text-[10px] text-zinc-500">{{ $userItem->ip_terakhir }}</div>
                                    @endif
                                @else
                                    <span class="text-zinc-400">Belum pernah login</span>
                                @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right w-16">
                            @if ($canManageUser)
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        @can('update_user::pengguna')
                                            <flux:menu.item wire:click="openEdit({{ $userItem->id }})" icon="pencil-square">
                                                Ubah Data & Peran
                                            </flux:menu.item>
                                            <flux:menu.item wire:click="confirmResetPassword({{ $userItem->id }})" icon="key">
                                                Reset Kata Sandi
                                            </flux:menu.item>
                                            <flux:menu.item wire:click="confirmToggleStatus({{ $userItem->id }})" icon="{{ $userItem->status === 'AKTIF' ? 'lock-closed' : 'lock-open' }}">
                                                {{ $userItem->status === 'AKTIF' ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                                            </flux:menu.item>
                                        @endcan
                                        @can('delete_user::pengguna')
                                            @if (auth()->id() !== $userItem->id)
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="confirmDelete({{ $userItem->id }})" icon="trash" variant="danger">
                                                    Hapus Akun
                                                </flux:menu.item>
                                            @endif
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            @else
                                <flux:badge size="xs" color="amber" icon="lock-closed">
                                    Terkunci
                                </flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-ui.empty-state message="Tidak ada data akun pegawai yang sesuai dengan parameter pencarian." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-ui.table-pagination :paginator="$users" />
    </flux:card>

    @include('livewire.user.partials.pengguna.modal-form')

    {{-- Modal Konfirmasi Operasi Sensitif --}}
    <x-ui.confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />

    @include('livewire.user.partials.pengguna.modal-reset-password')
</div>
