<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman & Tombol Tambah --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Jadwal Pemeliharaan Preventif (Servis Rutin)</flux:heading>
                <flux:subheading>Penjadwalan servis berkala fasilitas dan sarana rumah sakit per hari, minggu, atau bulan dengan penunjukan teknisi penanggung jawab.</flux:subheading>
            </div>

            @can('create_pemeliharaan::pemeliharaan::berkala')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Tambah Jadwal Servis
                </flux:button>
            @endcan
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode jadwal, nama alat, teknisi..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="flex items-center gap-2.5">
                <div class="w-40">
                    <select wire:model.live="filterTipe" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Siklus</option>
                        <option value="HARI">Siklus Harian</option>
                        <option value="MINGGU">Siklus Mingguan</option>
                        <option value="BULAN">Siklus Bulanan</option>
                    </select>
                </div>

                <div class="w-36">
                    <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Status</option>
                        <option value="AKTIF">AKTIF</option>
                        <option value="NONAKTIF">NONAKTIF</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Tabel Jadwal Preventif --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode Jadwal</flux:table.column>
                <flux:table.column>Sarana / Fasilitas</flux:table.column>
                <flux:table.column>Kegiatan Servis</flux:table.column>
                <flux:table.column>Siklus Rutin</flux:table.column>
                <flux:table.column>Teknisi PIC</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_berikutnya'" :direction="$sortDirection" wire:click="sort('tanggal_berikutnya')" class="w-36">Jatuh Tempo</flux:table.column>
                <flux:table.column class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-44">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarJadwal as $item)
                    @php
                        $isOverdue = $item->tanggal_berikutnya && $item->tanggal_berikutnya->isPast() && ! $item->tanggal_berikutnya->isToday();
                        $isToday = $item->tanggal_berikutnya && $item->tanggal_berikutnya->isToday();
                    @endphp
                    <flux:table.row :key="$item->id">
                        {{-- Kode Jadwal --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $item->kode }}
                        </flux:table.cell>

                        {{-- Sarana / Fasilitas --}}
                        <flux:table.cell>
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $item->aset?->barang?->nama ?? '-' }}
                            </div>
                            <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Kode: {{ $item->aset?->kode_aset ?? '-' }}
                                @if ($item->aset?->unit)
                                    &bull; Ruangan: {{ $item->aset->unit->nama }}
                                @endif
                            </div>
                        </flux:table.cell>

                        {{-- Kegiatan Servis --}}
                        <flux:table.cell>
                            <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $item->jenis_maintenance }}
                            </div>
                            @if ($item->catatan)
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5 line-clamp-1">
                                    {{ $item->catatan }}
                                </div>
                            @endif
                        </flux:table.cell>

                        {{-- Siklus Rutin --}}
                        <flux:table.cell>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60">
                                {{ $item->label_interval }}
                            </span>
                        </flux:table.cell>

                        {{-- Teknisi PIC --}}
                        <flux:table.cell>
                            <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $item->teknisi?->nama ?? 'Belum Ditunjuk' }}
                            </div>
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400">
                                Teknisi Penanggung Jawab
                            </div>
                        </flux:table.cell>

                        {{-- Jatuh Tempo --}}
                        <flux:table.cell>
                            <div class="space-y-0.5">
                                <div class="text-xs tabular-nums {{ $isOverdue ? 'font-bold text-rose-600 dark:text-rose-400' : ($isToday ? 'font-bold text-amber-600 dark:text-amber-400' : 'text-zinc-700 dark:text-zinc-300') }}">
                                    {{ $item->tanggal_berikutnya?->format('d/m/Y') ?? '-' }}
                                </div>
                                @if ($isOverdue)
                                    <span class="inline-block text-[10px] font-bold text-rose-600 dark:text-rose-400">
                                        LEWAT JATUH TEMPO
                                    </span>
                                @elseif ($isToday)
                                    <span class="inline-block text-[10px] font-bold text-amber-600 dark:text-amber-400">
                                        HARI INI
                                    </span>
                                @endif
                            </div>
                        </flux:table.cell>

                        {{-- Status --}}
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($item->status === 'AKTIF')
                                    @can('create_pemeliharaan::spk::perbaikan')
                                        <flux:button
                                            wire:click="confirmEksekusi({{ $item->id }})"
                                            size="xs"
                                            variant="primary"
                                            icon="bolt"
                                            title="Eksekusi Servis Sekarang (Terbitkan SPK)"
                                        >
                                            Eksekusi
                                        </flux:button>
                                    @endcan
                                @endif

                                @can('update_pemeliharaan::pemeliharaan::berkala')
                                    <flux:button
                                        wire:click="edit({{ $item->id }})"
                                        size="xs"
                                        variant="ghost"
                                        icon="pencil-square"
                                        title="Ubah Jadwal"
                                    />

                                    <flux:button
                                        wire:click="confirmToggleStatus({{ $item->id }})"
                                        size="xs"
                                        variant="ghost"
                                        icon="arrow-path"
                                        title="Ubah Status Aktif/Nonaktif"
                                    />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada jadwal pemeliharaan preventif yang terdaftar." cols="8" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$daftarJadwal" />
    </flux:card>

    {{-- Modal Form Tambah / Ubah Jadwal Servis Rutin --}}
    <flux:modal name="modal-jadwal-form" wire:model="showModal" class="md:w-[42rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Jadwal Pemeliharaan Preventif' : 'Pendaftaran Jadwal Servis Rutin Baru' }}</flux:heading>
                <flux:subheading>Tentukan fasilitas aset, siklus interval servis berkala, dan teknisi pelaksana penanggung jawab.</flux:subheading>
            </div>

            {{-- 1. Pilih Aset --}}
            <x-searchable-select
                model="aset_id"
                label="Fasilitas / Sarana Aset yang Diservis"
                placeholder="-- Cari Inventaris Aset... --"
                :options="$daftarAset"
                required
            />

            {{-- 2. Nama Kegiatan Servis --}}
            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Nama Kegiatan Servis Rutin <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    wire:model="jenis_maintenance"
                    required
                    placeholder="Contoh: Pembersihan Filter & Pengecekan Tekanan Freon AC"
                    class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                @error('jenis_maintenance') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- 3. Interval Fleksibel Berdampingan --}}
            <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 space-y-2">
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Siklus Pengulangan Servis <span class="text-red-500">*</span>
                </label>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-zinc-600 dark:text-zinc-400 font-medium">Setiap</span>
                    <div class="w-28">
                        <input
                            type="number"
                            wire:model="interval_nilai"
                            min="1"
                            max="365"
                            required
                            class="w-full text-sm font-mono text-center rounded-lg border {{ $errors->has('interval_nilai') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                        />
                    </div>
                    <div class="w-36">
                        <select
                            wire:model="interval_tipe"
                            required
                            class="w-full h-9 text-xs rounded-lg border {{ $errors->has('interval_tipe') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-900 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                        >
                            <option value="HARI">Hari</option>
                            <option value="MINGGU">Minggu</option>
                            <option value="BULAN">Bulan</option>
                        </select>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">sekali</span>
                </div>
                @error('interval_nilai') <p class="text-[10px] text-red-500 mt-0.5">{{ $message }}</p> @enderror
            </div>

            {{-- 4. Teknisi PIC & Tanggal Servis Pertama --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-searchable-select
                        model="teknisi_id"
                        label="Teknisi Penanggung Jawab (PIC)"
                        placeholder="-- Cari & Pilih Teknisi IPSRS... --"
                        :options="$daftarTeknisi"
                        required
                    />
                </div>

                <flux:field>
                    <flux:label>Tanggal Servis Pertama <span class="text-red-500">*</span></flux:label>
                    <flux:input type="date" wire:model="tanggal_berikutnya" required />
                    <flux:error name="tanggal_berikutnya" />
                </flux:field>
            </div>

            {{-- 5. Catatan / Petunjuk Teknis --}}
            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Petunjuk Teknis Pengerjaan (Opsional)
                </label>
                <textarea
                    wire:model="catatan"
                    rows="2"
                    placeholder="Tuliskan checklist komponen yang wajib diperiksa teknisi saat servis berlangsung..."
                    class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
                ></textarea>
                @error('catatan') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan Perubahan' : 'Daftarkan Jadwal' }}</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
