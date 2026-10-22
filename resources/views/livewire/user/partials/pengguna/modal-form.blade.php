    {{-- Modal Tambah / Edit Pengguna --}}
    <flux:modal wire:model="showModal" :dismissible="false" :closable="false" class="w-full max-w-3xl">
        <form wire:submit="save" class="space-y-3.5">
            <div class="pb-2.5 border-b border-zinc-200 dark:border-zinc-800">
                <flux:heading size="lg">
                    {{ $editingId ? 'Ubah Data Akun Pegawai' : 'Registrasi Akun Pegawai Baru' }}
                </flux:heading>
                <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400">
                    Lengkapi identitas kedinasan, tentukan peran hak akses, dan unit kerja penugasan.
                </flux:subheading>
            </div>

            {{-- Tahap 1: Identitas Pegawai & Kredensial Login --}}
            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-2.5">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 1: Identitas Kedinasan & Kredensial Akun
                </div>

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

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
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
                        @error('no_telepon') <p class="text-[10px] text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                            {{ $editingId ? 'Kata Sandi (Kosongkan jika tidak diubah)' : 'Kata Sandi Sementara (Otomatis)' }}
                        </label>
                        @if ($editingId)
                            <input
                                type="password"
                                wire:model="password"
                                placeholder="Minimal 8 karakter"
                                class="w-full h-9 text-xs rounded-lg border {{ $errors->has('password') ? 'border-rose-500 dark:border-rose-500 ring-1 ring-rose-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                            />
                        @else
                            <input
                                type="text"
                                wire:model="password"
                                readonly
                                class="w-full h-9 text-xs font-mono rounded-lg border border-zinc-300 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-900 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                            />
                            <p class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">Dikirim otomatis ke email pegawai. Pegawai wajib menggantinya saat login pertama.</p>
                        @endif
                        @error('password') <p class="text-[10px] text-rose-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    @if ($editingId)
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                                Konfirmasi Kata Sandi
                            </label>
                            <input
                                type="password"
                                wire:model="password_confirmation"
                                placeholder="Ulangi kata sandi"
                                class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    @endif
                </div>
            </div>

            {{-- Tahap 2: Hak Akses & Penugasan Kedinasan --}}
            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-2.5">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 2: Peran Hak Akses & Konteks Penugasan Kerja
                </div>

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

                <div class="p-3 rounded-xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700/60 space-y-2.5">
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
            </div>

            {{-- Tombol Aksi Modal --}}
            <div class="flex justify-end gap-2.5 pt-3 border-t border-zinc-200 dark:border-zinc-800">
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
