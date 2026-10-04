<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $pageTitle }}</flux:heading>
                <flux:subheading>{{ $pageSubtitle }}</flux:subheading>
            </div>

            @can('create_layanan_unit::peminjaman::aset')
                <div class="flex items-center gap-2">
                    <flux:button wire:click="createPinjam" variant="primary" icon="plus">
                        Ajukan Peminjaman
                    </flux:button>
                </div>
            @endcan
        </div>

        {{-- Navigasi tab --}}
        <flux:tabs class="pt-2">
            <flux:tab
                wire:click="setTab('aktif')"
                :current="$activeTab === 'aktif'"
                icon="arrow-path"
                :badge="$countAktif"
            >
                Peminjaman Aktif & Riwayat
            </flux:tab>
            <flux:tab
                wire:click="setTab('katalog')"
                :current="$activeTab === 'katalog'"
                icon="archive-box"
                :badge="$countKatalog"
            >
                Katalog Aset Tersedia
            </flux:tab>
        </flux:tabs>

        {{-- Toolbar filter dan pencarian --}}
        <div class="flex flex-wrap items-center justify-between gap-4 pt-1">
            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <div class="w-full sm:w-80">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ $activeTab === 'aktif' ? 'Cari kode PINJ, aset, unit, keperluan...' : 'Cari kode register aset, nama barang, nomor seri...' }}"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>

                @if ($activeTab === 'aktif')
                    <div class="w-full sm:w-44">
                        <select
                            wire:model.live="filterStatus"
                            class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100"
                        >
                            <option value="">Semua Status</option>
                            <option value="DIAJUKAN">DIAJUKAN</option>
                            <option value="DISETUJUI">DISETUJUI</option>
                            <option value="DIPINJAM">DIPINJAM</option>
                            <option value="SELESAI">SELESAI</option>
                            <option value="DITOLAK">DITOLAK</option>
                            <option value="DIBATALKAN">DIBATALKAN</option>
                        </select>
                    </div>

                    @if ($userUnitId)
                        <div class="w-full sm:w-60">
                            <select
                                wire:model.live="filterPerspektif"
                                class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                            >
                                <option value="semua">Semua Terkait Ruangan</option>
                                <option value="penyedia">Aset Ruangan Dipinjamkan Keluar</option>
                                <option value="peminjam">Aset Luar yang Sedang Dipinjam</option>
                            </select>
                        </div>
                    @endif
                @else
                    @if ($isGlobalAdmin)
                        <div class="w-full sm:w-44">
                            <select
                                wire:model.live="filterKatalogLokasi"
                                class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                            >
                                <option value="">Semua Lokasi</option>
                                <option value="RUANGAN">Ruangan Unit</option>
                                <option value="GUDANG">Gudang Logistik</option>
                            </select>
                        </div>

                        @if ($filterKatalogLokasi === 'RUANGAN')
                            <div class="w-full sm:w-48">
                                <select
                                    wire:model.live="filterKatalogUnitId"
                                    class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                                >
                                    <option value="">Semua Ruangan</option>
                                    @foreach ($daftarUnit as $unitOpt)
                                        <option value="{{ $unitOpt->id }}">{{ $unitOpt->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif ($filterKatalogLokasi === 'GUDANG')
                            <div class="w-full sm:w-48">
                                <select
                                    wire:model.live="filterKatalogGudangId"
                                    class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                                >
                                    <option value="">Semua Gudang</option>
                                    @foreach ($daftarGudang as $gdgOpt)
                                        <option value="{{ $gdgOpt->id }}">{{ $gdgOpt->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    @else
                        <div class="px-3 py-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 text-xs text-zinc-600 dark:text-zinc-300 flex items-center gap-1.5 font-medium">
                            <flux:icon icon="map-pin" class="size-3.5 text-blue-600" />
                            <span>Lokasi Terdaftar: {{ $userUnitId ? (auth()->user()->unit?->nama ?? 'Ruangan Anda') : (auth()->user()->gudang?->nama ?? 'Gudang Anda') }}</span>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Tampilan Tab 1: Peminjaman Aktif & Riwayat --}}
        @if ($activeTab === 'aktif')
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode PINJ</flux:table.column>
                    <flux:table.column class="w-36">Penyedia</flux:table.column>
                    <flux:table.column class="w-36">Unit Peminjam</flux:table.column>
                    <flux:table.column class="w-44">Periode Pinjam</flux:table.column>
                    <flux:table.column class="w-48">Aset Dipinjam</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                    <flux:table.column class="text-right w-16">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($daftarPeminjaman as $item)
                        @php
                            $isOverdue = ($item->status === 'DIPINJAM' && now()->startOfDay()->gt($item->tanggal_kembali_rencana));
                        @endphp
                        <flux:table.row :key="$item->id">
                            {{-- Kode PINJ --}}
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $item->kode }}
                            </flux:table.cell>

                            {{-- Penyedia --}}
                            <flux:table.cell style="white-space: normal;">
                                @if ($item->tipe_penyedia === 'RUANGAN')
                                    <div class="flex items-center gap-1.5">
                                        <flux:badge size="xs" color="cyan">RUANGAN</flux:badge>
                                        <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $item->unitPenyedia?->nama ?? '-' }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-1.5">
                                        <flux:badge size="xs" color="emerald">GUDANG</flux:badge>
                                        <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $item->gudangPenyedia?->nama ?? '-' }}</span>
                                    </div>
                                @endif
                            </flux:table.cell>

                            {{-- Unit Peminjam --}}
                            <flux:table.cell style="white-space: normal;">
                                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $item->unitPeminjam?->nama ?? '-' }}
                                </span>
                            </flux:table.cell>

                            {{-- Periode Pinjam (Gabungan Tgl Pinjam & Rencana Kembali) --}}
                            <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                                <div>{{ $item->tanggal_pinjam?->format('d/m/Y') }} &rarr; <span class="{{ $isOverdue ? 'font-bold text-rose-600 dark:text-rose-400' : '' }}">{{ $item->tanggal_kembali_rencana?->format('d/m/Y') }}</span></div>
                                @if ($isOverdue)
                                    <div class="mt-0.5"><flux:badge size="xs" color="rose">OVERDUE</flux:badge></div>
                                @endif
                            </flux:table.cell>

                            {{-- Aset Dipinjam --}}
                            <flux:table.cell style="white-space: normal;">
                                @forelse ($item->detail as $detail)
                                    <div class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $detail->aset?->barang?->nama ?? 'Aset #' . $detail->aset_id }}
                                    </div>
                                    <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">
                                        {{ $detail->aset?->kode_aset ?? '-' }}
                                    </div>
                                @empty
                                    <span class="text-zinc-400 text-xs">-</span>
                                @endforelse
                            </flux:table.cell>

                            {{-- Status --}}
                            <flux:table.cell class="text-center">
                                <x-status-badge :status="$item->status" />
                            </flux:table.cell>

                            {{-- Aksi --}}
                            <flux:table.cell class="text-right w-16">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        <flux:menu.item wire:click="openDetail({{ $item->id }})" icon="eye">
                                            Detail Pinjam
                                        </flux:menu.item>
                                        @php
                                            $canSerahTerima = $isGlobalAdmin ||
                                                ($item->tipe_penyedia === 'RUANGAN' && (int) $userUnitId === (int) $item->unit_penyedia_id) ||
                                                ($item->tipe_penyedia === 'GUDANG' && (int) $userGudangId === (int) $item->gudang_penyedia_id);
                                        @endphp
                                        @if ($item->status === 'DISETUJUI' && $canSerahTerima)
                                            <flux:menu.item wire:click="confirmSerahTerima({{ $item->id }})" icon="arrow-right-start-on-rectangle">
                                                Serah Terima
                                            </flux:menu.item>
                                        @endif

                                        @if (in_array($item->status, ['DIPINJAM'], true))
                                            @can('update_layanan_unit::peminjaman::aset')
                                                <flux:menu.item wire:click="openPerpanjang({{ $item->id }})" icon="calendar-days">
                                                    Perpanjang
                                                </flux:menu.item>
                                            @endcan
                                        @endif

                                        @if ($item->status === 'DIAJUKAN')
                                            @can('create_layanan_unit::peminjaman::aset')
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="confirmBatal({{ $item->id }})" icon="x-mark" variant="danger">
                                                    Batalkan Pengajuan
                                                </flux:menu.item>
                                            @endcan
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Belum ada transaksi peminjaman aset yang terdaftar." cols="7" />
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <x-table-pagination :paginator="$daftarPeminjaman" />
        @else
            {{-- Tampilan Tab 2: Katalog Aset Tersedia --}}
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode_aset'" :direction="$sortDirection" wire:click="sort('kode_aset')" class="w-32">Kode Register</flux:table.column>
                    <flux:table.column class="w-64">Komoditas & Nomor Seri</flux:table.column>
                    <flux:table.column class="w-56">Lokasi Penyedia</flux:table.column>
                    <flux:table.column class="text-center w-24">Kondisi</flux:table.column>
                    <flux:table.column class="text-center w-24">Status</flux:table.column>
                    <flux:table.column class="text-right w-36">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($daftarKatalogAset as $aset)
                        <flux:table.row :key="$aset->id">
                            {{-- Kode Register --}}
                            <flux:table.cell class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $aset->kode_aset }}
                            </flux:table.cell>

                            {{-- Komoditas & Seri --}}
                            <flux:table.cell style="white-space: normal;">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $aset->barang?->nama ?? '-' }}</div>
                                @if ($aset->nomor_seri)
                                    <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">SN: {{ $aset->nomor_seri }}</div>
                                @endif
                            </flux:table.cell>

                            {{-- Lokasi Penyedia --}}
                            <flux:table.cell style="white-space: normal;">
                                @if ($aset->unit_id)
                                    <div class="flex items-center gap-1.5">
                                        <flux:badge size="xs" color="cyan">RUANGAN</flux:badge>
                                        <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $aset->unit?->nama }}</span>
                                    </div>
                                @elseif ($aset->gudang_id)
                                    <div class="flex items-center gap-1.5">
                                        <flux:badge size="xs" color="emerald">GUDANG</flux:badge>
                                        <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $aset->gudang?->nama }}</span>
                                    </div>
                                    @if ($aset->lokasiGudang)
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 font-mono mt-0.5">Bin/Rak: {{ $aset->lokasiGudang->nama }}</div>
                                    @endif
                                @else
                                    <span class="text-zinc-400 text-xs">-</span>
                                @endif
                            </flux:table.cell>

                            {{-- Kondisi --}}
                            <flux:table.cell class="text-center">
                                <flux:badge size="sm" color="emerald">BAIK</flux:badge>
                            </flux:table.cell>

                            {{-- Status --}}
                            <flux:table.cell class="text-center">
                                <flux:badge size="sm" color="emerald">TERSEDIA</flux:badge>
                            </flux:table.cell>

                            {{-- Aksi --}}
                            <flux:table.cell class="text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button
                                        wire:click="showQr({{ $aset->id }})"
                                        size="xs"
                                        variant="ghost"
                                        icon="qr-code"
                                        title="Stiker QR Code"
                                    >
                                        Stiker QR
                                    </flux:button>

                                    @can('create_layanan_unit::peminjaman::aset')
                                        <flux:button
                                            wire:click="pinjamAset({{ $aset->id }})"
                                            size="xs"
                                            variant="primary"
                                            icon="arrow-right-circle"
                                        >
                                            Ajukan Pinjam
                                        </flux:button>
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Tidak ada aset inventaris yang berstatus TERSEDIA dan kondisi BAIK sesuai filter." cols="6" />
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <x-table-pagination :paginator="$daftarKatalogAset" />
        @endif
    </flux:card>

    {{-- Modal Pengajuan Peminjaman Baru --}}
    <flux:modal name="modal-pinjam" wire:model="showPinjamModal" class="md:w-[54rem] max-w-[95vw]">
        <div class="space-y-6">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Form Pengajuan Peminjaman Aset</flux:heading>
                <flux:subheading>Pilih fasilitas penyedia aset inventaris yang berstatus tersedia dan dalam kondisi baik.</flux:subheading>
            </div>

            <form wire:submit="savePinjam" class="space-y-5">
                {{-- Tahap 1: Pilih Aset Inventaris --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 1: Pilih Aset Inventaris
                    </div>

                    <div>
                        <x-searchable-select
                            model="selected_barang_id"
                            label="Aset / Komoditas yang Ingin Dipinjam"
                            placeholder="-- Cari Aset Inventaris Tersedia... --"
                            :options="$daftarBarangAsetTersedia->map(fn($b) => ['id' => $b->id, 'nama' => $b->nama, 'sub' => 'Tersedia: ' . $b->aset_count . ' ' . ($b->satuan?->simbol ?? 'Unit')])"
                            live="true"
                            required
                        />

                        @if ($selected_barang_id)
                            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1.5">
                                Ditemukan {{ count($daftarPenyediaAset) }} fasilitas penyedia yang memiliki unit komoditas ini.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Tahap 2: Pilih Unit atau Gudang Penyedia Aset --}}
                @if ($selected_barang_id)
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                            Tahap 2: Pilih Unit Ruangan atau Gudang Penyedia
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                                Lokasi Fasilitas Pemilik Aset <span class="text-red-500">*</span>
                            </label>

                            @if (empty($daftarPenyediaAset))
                                <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300">
                                    Saat ini tidak ada unit ruangan atau fasilitas gudang yang memiliki aset ini dalam kondisi siap pinjam.
                                </div>
                            @else
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                    @foreach ($daftarPenyediaAset as $p)
                                        <label
                                            class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors {{ $selected_penyedia_key === $p['key'] ? 'border-blue-600 bg-blue-50/60 dark:bg-blue-900/30 text-blue-950 dark:text-blue-100 ring-1 ring-blue-600' : 'border-zinc-200 dark:border-zinc-700 hover:bg-white dark:hover:bg-zinc-800' }} {{ $p['is_own'] ? 'opacity-60 cursor-not-allowed' : '' }}"
                                        >
                                            <input
                                                type="radio"
                                                wire:model.live="selected_penyedia_key"
                                                value="{{ $p['key'] }}"
                                                class="mt-0.5 text-blue-600 focus:ring-blue-500"
                                                {{ $p['is_own'] ? 'disabled' : '' }}
                                            />
                                            <div class="flex-1 text-xs">
                                                <div class="font-semibold flex items-center justify-between gap-2">
                                                    <span>{{ $p['nama'] }}</span>
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $p['tipe'] === 'RUANGAN' ? 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' }}">
                                                        {{ $p['tipe'] }}
                                                    </span>
                                                </div>
                                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                                                    Tersedia: <strong>{{ $p['jumlah'] }} unit</strong> (Kode: {{ $p['kode_asets'] }})
                                                </div>
                                                @if ($p['is_own'])
                                                    <div class="text-xs text-amber-700 dark:text-amber-300 font-medium mt-0.5">
                                                        Ruangan Anda sendiri (tidak dapat meminjam ke ruangan sendiri)
                                                    </div>
                                                @endif
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                                @error('selected_penyedia_key') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Tahap 3: Jadwal dan Keperluan --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-4">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 3: Jadwal dan Keperluan Peminjaman
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Tanggal Mulai Pinjam <span class="text-red-500">*</span></flux:label>
                            <flux:input type="date" wire:model="tanggal_pinjam" required />
                            <flux:error name="tanggal_pinjam" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Tanggal Rencana Kembali <span class="text-red-500">*</span></flux:label>
                            <flux:input type="date" wire:model="tanggal_kembali_rencana" required />
                            <flux:error name="tanggal_kembali_rencana" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>Keperluan Peminjaman <span class="text-red-500">*</span></flux:label>
                        <flux:textarea
                            wire:model="keperluan"
                            placeholder="Jelaskan kebutuhan operasional peminjaman aset ini..."
                            rows="2"
                            required
                        />
                        <flux:error name="keperluan" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Catatan Tambahan (Opsional)</flux:label>
                        <flux:input
                            type="text"
                            wire:model="catatan"
                            placeholder="Contoh: Termasuk kelengkapan kabel power dan remote control"
                        />
                        <flux:error name="catatan" />
                    </flux:field>
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="savePinjam">Kirim Pengajuan</span>
                        <span wire:loading wire:target="savePinjam">Memproses...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Modal Perpanjangan Waktu Peminjaman --}}
    <flux:modal name="modal-perpanjang" wire:model="showPerpanjangModal" class="md:w-[54rem] max-w-[95vw]">
        <div class="space-y-6">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Perpanjangan Waktu Peminjaman</flux:heading>
                <flux:subheading>Perpanjang batas waktu pengembalian aset inventaris dengan mencantumkan alasan operasional.</flux:subheading>
            </div>

            <form wire:submit="submitPerpanjang" class="space-y-4">
                <flux:field>
                    <flux:label>Tanggal Rencana Pengembalian Baru</flux:label>
                    <flux:input type="date" wire:model="tanggal_kembali_baru" />
                    <flux:error name="tanggal_kembali_baru" />
                </flux:field>

                <flux:field>
                    <flux:label>Alasan Perpanjangan</flux:label>
                    <flux:textarea
                        wire:model="alasan_perpanjang"
                        placeholder="Jelaskan alasan perpanjangan waktu peminjaman aset..."
                        rows="3"
                    />
                    <flux:error name="alasan_perpanjang" />
                </flux:field>

                <div class="flex justify-end pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button type="submit" variant="primary" icon="check">
                        Simpan Perpanjangan
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Modal Stiker QR Code --}}
    <flux:modal name="modal-qr-code" wire:model="showQrModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedAsetQr)
            <div class="space-y-5 text-center p-2">
                <div>
                    <flux:heading size="lg">Stiker QR Code Aset Inventaris</flux:heading>
                    <flux:subheading>Stiker identitas fisik aset inventaris rumah sakit.</flux:subheading>
                </div>

                <div id="print-area-pinjam" class="p-6 bg-white dark:bg-zinc-900 border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-2xl inline-block shadow-xs">
                    <div class="flex justify-center mb-3">
                        {!! $qrCodeSvg !!}
                    </div>
                    <div class="font-mono font-extrabold text-sm text-zinc-900 dark:text-zinc-100 tracking-wider">
                        {{ $selectedAsetQr->kode_aset }}
                    </div>
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mt-1 max-w-[240px] truncate">
                        {{ $selectedAsetQr->barang?->nama }}
                    </div>
                    @if ($selectedAsetQr->nomor_seri)
                        <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">SN: {{ $selectedAsetQr->nomor_seri }}</div>
                    @endif
                    <div class="text-[10px] uppercase font-bold text-zinc-500 mt-2 border-t border-zinc-200 dark:border-zinc-800 pt-1.5">
                        SIMLOG RS : SIRKULASI ASET
                    </div>
                </div>

                <div class="flex justify-center pt-2">
                    <flux:button variant="primary" icon="printer" onclick="window.print()">Cetak Stiker</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Detail Peminjaman --}}
    <flux:modal name="modal-detail-pinjam" wire:model="showDetailModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedPinjam)
            <div class="space-y-6">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-200 dark:border-zinc-700">
                    <div>
                        <flux:heading size="lg">Detail Peminjaman: {{ $selectedPinjam->kode }}</flux:heading>
                        <flux:subheading>Informasi lengkap sirkulasi aset, penanggung jawab, dan catatan operasional.</flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <x-status-badge :status="$selectedPinjam->status" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div class="text-zinc-500">Tipe & Lokasi Penyedia:</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            @if ($selectedPinjam->tipe_penyedia === 'RUANGAN')
                                Ruangan: {{ $selectedPinjam->unitPenyedia?->nama ?? '-' }}
                            @else
                                Gudang: {{ $selectedPinjam->gudangPenyedia?->nama ?? '-' }}
                            @endif
                        </div>
                    </div>

                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div class="text-zinc-500">Unit Peminjam:</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->unitPeminjam?->nama ?? '-' }}
                        </div>
                    </div>

                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div class="text-zinc-500">Diajukan Oleh:</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->pengaju?->nama ?? '-' }}
                        </div>
                    </div>

                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div class="text-zinc-500">Tanggal Pinjam:</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5 tabular-nums">
                            {{ $selectedPinjam->tanggal_pinjam?->format('d/m/Y') ?? '-' }}
                        </div>
                    </div>

                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div class="text-zinc-500">Rencana Kembali:</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5 tabular-nums">
                            {{ $selectedPinjam->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}
                        </div>
                    </div>

                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <div class="text-zinc-500">Disetujui Oleh:</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->penyetuju?->nama ?? 'Menunggu Otorisasi' }}
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg text-xs space-y-1">
                    <div class="text-zinc-500 font-medium">Keperluan:</div>
                    <div class="text-zinc-800 dark:text-zinc-200 whitespace-pre-line">{{ $selectedPinjam->keperluan ?? '-' }}</div>
                </div>

                @if ($selectedPinjam->catatan)
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg text-xs space-y-1">
                        <div class="text-zinc-500 font-medium">Catatan & Riwayat Perpanjangan:</div>
                        <div class="text-zinc-800 dark:text-zinc-200 whitespace-pre-line font-mono text-[11px]">{{ $selectedPinjam->catatan }}</div>
                    </div>
                @endif

                {{-- Tabel Rincian Aset --}}
                <div>
                    <div class="font-semibold text-xs text-zinc-700 dark:text-zinc-300 mb-2">Daftar Aset Inventaris yang Dipinjam</div>
                    <flux:table bleed>
                        <flux:table.columns>
                            <flux:table.column class="w-32">Kode Register</flux:table.column>
                            <flux:table.column>Nama Komoditas</flux:table.column>
                            <flux:table.column class="text-center w-28">Kondisi Pinjam</flux:table.column>
                            <flux:table.column>Catatan</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($selectedPinjam->detail as $detail)
                                <flux:table.row :key="$detail->id">
                                    <flux:table.cell class="font-mono font-bold text-xs">
                                        {{ $detail->aset?->kode_aset ?? '-' }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="font-medium text-xs">{{ $detail->aset?->barang?->nama ?? '-' }}</div>
                                        @if ($detail->aset?->nomor_seri)
                                            <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">SN: {{ $detail->aset->nomor_seri }}</div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-center">
                                        <flux:badge size="sm" color="emerald">{{ $detail->kondisi_saat_pinjam }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-xs text-zinc-500">
                                        {{ $detail->catatan ?? '-' }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>

                {{-- Riwayat Pengembalian Fisik Jika Ada --}}
                @if ($selectedPinjam->pengembalian->isNotEmpty())
                    <div>
                        <div class="font-semibold text-xs text-zinc-700 dark:text-zinc-300 mb-2">Riwayat Pengembalian & Inspeksi Fisik</div>
                        @foreach ($selectedPinjam->pengembalian as $rtn)
                            <div class="p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30 text-xs space-y-2 mb-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono font-bold">{{ $rtn->kode }}</span>
                                    <span class="text-zinc-500">Kembali aktual: {{ $rtn->tanggal_kembali_aktual?->format('d/m/Y') }} (Pemeriksa: {{ $rtn->pemeriksa?->nama ?? '-' }})</span>
                                </div>
                                @if ($rtn->catatan)
                                    <p class="text-zinc-600 dark:text-zinc-400 text-[11px]">{{ $rtn->catatan }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @php
                    $canSerahTerimaModal = $isGlobalAdmin ||
                        ($selectedPinjam->tipe_penyedia === 'RUANGAN' && (int) $userUnitId === (int) $selectedPinjam->unit_penyedia_id) ||
                        ($selectedPinjam->tipe_penyedia === 'GUDANG' && (int) $userGudangId === (int) $selectedPinjam->gudang_penyedia_id);
                @endphp

                @if ($selectedPinjam->status === 'DISETUJUI' && $canSerahTerimaModal)
                    <div class="flex items-center justify-end pt-3 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:button
                            wire:click="confirmSerahTerima({{ $selectedPinjam->id }})"
                            variant="primary"
                            icon="arrow-right-start-on-rectangle"
                            wire:loading.attr="disabled"
                        >
                            Serah Terima Fisik
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>

    {{-- Modal Konfirmasi Tindakan --}}
    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
