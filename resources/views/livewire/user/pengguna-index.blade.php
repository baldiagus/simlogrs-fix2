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
                    <x-empty-state message="Tidak ada data akun pegawai yang sesuai dengan parameter pencarian." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$users" />
    </flux:card>

    {{-- Modal Tambah / Edit Pengguna --}}
    <flux:modal wire:model="showModal" class="md:w-[42rem]">
        <form wire:submit="save" class="space-y-4">
            <div class="pb-3 border-b border-zinc-200 dark:border-zinc-800" style="padding-right: 2.5rem;">
                <flux:heading size="lg">
                    {{ $editingId ? 'Ubah Data Akun Pegawai' : 'Registrasi Akun Pegawai Baru' }}
                </flux:heading>
                <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400">
                    Lengkapi identitas kedinasan, tentukan peran hak akses, dan unit kerja penugasan.
                </flux:subheading>
            </div>

            {{-- Baris 1: Nama & NIP --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Nama Lengkap Pegawai <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="nama"
                        required
                        placeholder="Contoh: dr. Pratama, Sp.EM"
                        class="w-full h-9 text-xs rounded-lg border {{ $errors->has('nama') ? 'border-rose-500 dark:border-rose-500 ring-1 ring-rose-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    />
                    @error('nama') <p class="text-[10px] text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Nomor Induk Pegawai (NIP)
                    </label>
                    <input
                        type="text"
                        wire:model="nip"
                        placeholder="198003122006041006"
                        class="w-full h-9 text-xs font-mono rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    @error('nip') <p class="text-[10px] text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Baris 2: Username & Email --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Username Login <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="username"
                        required
                        placeholder="kepalaunit"
                        class="w-full h-9 text-xs font-mono rounded-lg border {{ $errors->has('username') ? 'border-rose-500 dark:border-rose-500 ring-1 ring-rose-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    />
                    @error('username') <p class="text-[10px] text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Email Kedinasan <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="email"
                        wire:model="email"
                        required
                        placeholder="kepalaunit@simlogrs.local"
                        class="w-full h-9 text-xs font-mono rounded-lg border {{ $errors->has('email') ? 'border-rose-500 dark:border-rose-500 ring-1 ring-rose-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    />
                    @error('email') <p class="text-[10px] text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Baris 3: Password & Konfirmasi --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Kata Sandi {{ $editingId ? '(Kosongkan jika tidak diubah)' : '*' }}
                    </label>
                    <input
                        type="password"
                        wire:model="password"
                        {{ $editingId ? '' : 'required' }}
                        placeholder="Minimal 8 karakter"
                        class="w-full h-9 text-xs rounded-lg border {{ $errors->has('password') ? 'border-rose-500 dark:border-rose-500 ring-1 ring-rose-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    />
                    @error('password') <p class="text-[10px] text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Konfirmasi Kata Sandi {{ $editingId ? '' : '*' }}
                    </label>
                    <input
                        type="password"
                        wire:model="password_confirmation"
                        {{ $editingId ? '' : 'required' }}
                        placeholder="Ulangi kata sandi"
                        class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            {{-- Baris 4: Peran Hak Akses & Status --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Peran Utama (RBAC Role) <span class="text-rose-500">*</span>
                    </label>
                    <select
                        wire:model="selectedRole"
                        required
                        class="w-full h-9 text-xs rounded-lg border {{ $errors->has('selectedRole') ? 'border-rose-500 dark:border-rose-500 ring-1 ring-rose-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    >
                        @foreach ($assignableRoles as $roleOption)
                            <option value="{{ $roleOption->name }}">{{ ucwords(str_replace('_', ' ', $roleOption->name)) }}</option>
                        @endforeach
                    </select>
                    @error('selectedRole') <p class="text-[10px] text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Status Akun <span class="text-rose-500">*</span>
                    </label>
                    <select
                        wire:model="status"
                        required
                        class="w-full h-9 text-xs rounded-lg border {{ $errors->has('status') ? 'border-rose-500 dark:border-rose-500 ring-1 ring-rose-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    >
                        <option value="AKTIF">AKTIF (Dapat Login)</option>
                        <option value="NONAKTIF">NONAKTIF (Ditolak Masuk)</option>
                    </select>
                    @error('status') <p class="text-[10px] text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Baris 5: Opsi Penugasan Unit / Fasilitas --}}
            <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 space-y-3">
                <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                    Konteks Penugasan Operasional Pegawai
                </div>

                <div class="flex items-center gap-4 text-xs">
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="radio" wire:model.live="penugasan_tipe" value="none" class="text-blue-600" />
                        <span>Tanpa Penugasan Fisik (Pusat / Sentral)</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="radio" wire:model.live="penugasan_tipe" value="unit" class="text-blue-600" />
                        <span>Unit Kerja / Ruangan</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                        <input type="radio" wire:model.live="penugasan_tipe" value="gudang" class="text-blue-600" />
                        <span>Gudang Logistik</span>
                    </label>
                </div>

                @if ($penugasan_tipe === 'unit')
                    <div>
                        <label class="block text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 mb-1">
                            Pilih Unit Kerja / Ruangan Pelayanan <span class="text-rose-500">*</span>
                        </label>
                        <select
                            wire:model="unit_id"
                            required
                            class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">-- Pilih Unit Kerja --</option>
                            @foreach ($daftarUnit as $u)
                                <option value="{{ $u->id }}">{{ $u->nama }} ({{ $u->kode }})</option>
                            @endforeach
                        </select>
                        @error('unit_id') <p class="text-[10px] text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($penugasan_tipe === 'gudang')
                    <div>
                        <label class="block text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 mb-1">
                            Pilih Fasilitas Gudang Logistik <span class="text-rose-500">*</span>
                        </label>
                        <select
                            wire:model="gudang_id"
                            required
                            class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">-- Pilih Fasilitas Gudang --</option>
                            @foreach ($daftarGudang as $g)
                                <option value="{{ $g->id }}">{{ $g->nama }} ({{ $g->kode }})</option>
                            @endforeach
                        </select>
                        @error('gudang_id') <p class="text-[10px] text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            {{-- Baris 6: No. Telepon / WhatsApp --}}
            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                    Nomor Telepon / WhatsApp Dinas
                </label>
                <input
                    type="text"
                    wire:model="no_telepon"
                    placeholder="081234567890"
                    class="w-full h-9 text-xs font-mono rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>

            {{-- Tombol Aksi Modal --}}
            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan Perubahan' : 'Buat Akun Pegawai' }}</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Konfirmasi Operasi Sensitif --}}
    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />

    {{-- Modal Informasi Sandi Sementara (One-Time Secure Display - Anti Leaking H-03) --}}
    <flux:modal wire:model="showResetSuccessModal" class="md:w-[32rem]">
        <div class="space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400">
                    <flux:icon name="key" class="size-6" />
                </div>
                <div>
                    <flux:heading size="lg" class="font-bold">Kredensial Kata Sandi Sementara</flux:heading>
                    <flux:subheading class="text-xs text-zinc-500">
                        Kata sandi akun pegawai berhasil direset.
                    </flux:subheading>
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-900/60 text-xs text-amber-900 dark:text-amber-200 flex items-start gap-2.5">
                <flux:icon name="exclamation-triangle" class="size-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                <div>
                    <strong>Perhatian Keamanan:</strong> Kredensial sementara ini <strong>hanya ditampilkan satu kali</strong>. Harap segera salin dan berikan langsung kepada pegawai yang bersangkutan. Pegawai diwajibkan mengganti kata sandi ini saat pertama kali masuk ke sistem.
                </div>
            </div>

            @if (! empty($tempPasswordData))
                <div class="space-y-3 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 text-xs">
                    <div class="flex justify-between items-center py-1 border-b border-zinc-200/60 dark:border-zinc-800/60">
                        <span class="text-zinc-500 font-medium">Nama Pegawai</span>
                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $tempPasswordData['nama'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-zinc-200/60 dark:border-zinc-800/60">
                        <span class="text-zinc-500 font-medium">Username Login</span>
                        <span class="font-mono font-semibold text-zinc-800 dark:text-zinc-200">{{ $tempPasswordData['username'] ?? '-' }}</span>
                    </div>
                    <div class="pt-1 space-y-1.5">
                        <span class="text-zinc-500 font-medium block">Kata Sandi Sementara</span>
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                readonly
                                value="{{ $tempPasswordData['tempPassword'] ?? '' }}"
                                id="temp-password-input"
                                class="w-full h-9 text-sm font-mono font-bold tracking-widest text-center rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 select-all"
                            />
                            <flux:button
                                type="button"
                                size="sm"
                                variant="primary"
                                icon="clipboard-document"
                                onclick="navigator.clipboard.writeText(document.getElementById('temp-password-input').value); alert('Kata sandi sementara berhasil disalin!');"
                            >
                                Salin
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="button" variant="primary" wire:click="closeResetSuccessModal">
                    Tutup & Hapus Kredensial
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
