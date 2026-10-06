<div class="space-y-6">
    {{-- Header Aksi & Judul --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" class="font-bold tracking-tight">Pengalihan & Mutasi Barang (BHP)</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                    Redistribusi dan mutasi persediaan barang habis pakai antar-gudang dan ke unit ruangan
                </flux:subheading>
            </div>

            @can('create_gudang_stok::pengalihan::barang')
                <flux:button variant="primary" icon="arrows-right-left" wire:click="openCreateModal">
                    Ajukan Pengalihan Barang
                </flux:button>
            @endcan
        </div>

        {{-- Sub-Navigasi Modul Pengalihan & Mutasi --}}
        <x-pengalihan-hub-tabs active="barang" />
    </div>

    {{-- Metrik KPI Ringkasan --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Pengalihan Berjalan</div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1 tabular-nums">{{ number_format($kpiBerjalan) }}</div>
            <div class="text-[11px] text-amber-600 dark:text-amber-400 mt-0.5">Menunggu respon & serah terima</div>
        </flux:card>

        <flux:card class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Siap Serah Terima</div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1 tabular-nums">{{ number_format($kpiMenungguTerima) }}</div>
            <div class="text-[11px] text-zinc-500 mt-0.5">Telah disetujui pimpinan</div>
        </flux:card>

        <flux:card class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Mutasi Bulan Ini</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 tabular-nums">{{ number_format($kpiSelesaiBulanIni) }}</div>
            <div class="text-[11px] text-zinc-500 mt-0.5">BAST resmi diterbitkan</div>
        </flux:card>

        <flux:card class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Total Item Dimutasi</div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1 tabular-nums">{{ number_format($kpiTotalItem) }}</div>
            <div class="text-[11px] text-zinc-500 mt-0.5">Kuantitas fisik berpindah</div>
        </flux:card>
    </div>

    {{-- Main Workspace Card --}}
    <flux:card class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-0 overflow-hidden shadow-xs">
        {{-- Tab Switcher --}}
        <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 px-4 pt-3 pb-0 bg-zinc-50/50 dark:bg-zinc-900/50">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="setTab('aktif')"
                    class="px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-2 {{ $activeTab === 'aktif' ? 'border-blue-600 text-blue-600 dark:text-blue-400 bg-white dark:bg-zinc-900' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
                >
                    <flux:icon name="arrows-right-left" class="size-4" />
                    <span>Pengalihan Aktif</span>
                    @if ($kpiBerjalan > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                            {{ $kpiBerjalan }}
                        </span>
                    @endif
                </button>

                <button
                    type="button"
                    wire:click="setTab('riwayat')"
                    class="px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-2 {{ $activeTab === 'riwayat' ? 'border-blue-600 text-blue-600 dark:text-blue-400 bg-white dark:bg-zinc-900' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
                >
                    <flux:icon name="clock" class="size-4" />
                    <span>Histori & Arsip Mutasi</span>
                </button>
            </div>
        </div>

        {{-- Filter & Search Toolbar --}}
        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="flex-1 max-w-md">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode TRF, nama barang, fasilitas..."
                    icon="magnifying-glass"
                    size="sm"
                />
            </div>

            <div class="flex items-center gap-2">
                @if ($activeTab === 'aktif')
                    <flux:select wire:model.live="filterStatus" size="sm" class="w-36">
                        <option value="">Semua Status</option>
                        <option value="DIAJUKAN">DIAJUKAN</option>
                        <option value="DISETUJUI">DISETUJUI</option>
                    </flux:select>
                @else
                    <flux:select wire:model.live="filterStatus" size="sm" class="w-36">
                        <option value="">Semua Status</option>
                        <option value="SELESAI">SELESAI</option>
                        <option value="DITOLAK">DITOLAK</option>
                        <option value="DIBATALKAN">DIBATALKAN</option>
                    </flux:select>
                @endif
            </div>
        </div>

        {{-- Table Pengalihan Barang --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36 font-mono">Kode Dokumen</flux:table.column>
                <flux:table.column class="w-48">Asal Mutasi</flux:table.column>
                <flux:table.column class="w-48">Tujuan Mutasi</flux:table.column>
                <flux:table.column class="w-64">Komoditas & Jumlah</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $row)
                    <flux:table.row :key="'trf-brg-'.$row->id">
                        <flux:table.cell>
                            <div class="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">
                                {{ $row->kode }}
                            </div>
                            <div class="text-[11px] text-zinc-500 mt-0.5">
                                {{ $row->tanggal_pengalihan?->translatedFormat('d M Y') ?? '-' }}
                                &bull; <span class="text-zinc-600 dark:text-zinc-400">{{ $row->pengaju?->nama ?? 'Pengaju' }}</span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell style="white-space: normal;">
                            <div class="flex items-center gap-1.5">
                                <flux:badge size="xs" :color="$row->tipe_asal === 'RUANGAN' ? 'cyan' : 'emerald'">
                                    {{ $row->tipe_asal }}
                                </flux:badge>
                                <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200 truncate">
                                    {{ $row->nama_lokasi_asal }}
                                </span>
                            </div>
                            @if ($row->alasan)
                                <div class="text-[11px] text-zinc-500 truncate max-w-xs mt-0.5" title="{{ $row->alasan }}">
                                    Ket: {{ $row->alasan }}
                                </div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell style="white-space: normal;">
                            <div class="flex items-center gap-1.5">
                                <flux:badge size="xs" :color="$row->tipe_tujuan === 'RUANGAN' ? 'blue' : 'purple'">
                                    {{ $row->tipe_tujuan }}
                                </flux:badge>
                                <span class="text-xs font-semibold text-blue-600 dark:text-blue-400 truncate">
                                    {{ $row->nama_lokasi_tujuan }}
                                </span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @foreach ($row->detail as $det)
                                <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ $det->barang?->nama ?? 'Barang' }} &bull; <span class="font-mono text-blue-600">{{ $det->jumlah }} {{ $det->barang?->satuan?->simbol ?? 'Unit' }}</span>
                                </div>
                            @endforeach
                        </flux:table.cell>

                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$row->status" />
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                                <flux:menu>
                                    <flux:menu.item icon="document-text" wire:click="showDetail({{ $row->id }})">
                                        Lihat Berita Acara / Detail
                                    </flux:menu.item>

                                    @if ($row->status === 'DIAJUKAN')
                                        @if (auth()->user()?->can('update_gudang_stok::pengalihan::barang') || auth()->user()?->hasRole(['super_admin', 'admin_logistik', 'kepala_gudang']))
                                            <flux:menu.item icon="check" wire:click="openApprovalModal({{ $row->id }}, 'APPROVE')">
                                                Setujui Pengalihan
                                            </flux:menu.item>
                                            <flux:menu.item icon="x-mark" class="text-red-600" wire:click="openApprovalModal({{ $row->id }}, 'REJECT')">
                                                Tolak Pengalihan
                                            </flux:menu.item>
                                        @endif

                                        <flux:menu.item icon="trash" class="text-zinc-500" wire:click="cancelPengalihan({{ $row->id }})">
                                            Batalkan Pengajuan
                                        </flux:menu.item>
                                    @endif

                                    @if ($row->status === 'DISETUJUI')
                                        <flux:menu.item icon="arrow-down-tray" class="text-blue-600 font-semibold" wire:click="executeTerima({{ $row->id }})">
                                            Konfirmasi Serah Terima Fisik
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-12 text-zinc-500">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <div class="p-3 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400">
                                    <flux:icon name="arrows-right-left" class="size-6" />
                                </div>
                                <div class="font-medium text-sm text-zinc-800 dark:text-zinc-200">
                                    {{ $activeTab === 'aktif' ? 'Belum Ada Pengalihan Barang Aktif' : 'Arsip Mutasi Barang Masih Kosong' }}
                                </div>
                                <div class="text-xs text-zinc-500 max-w-sm">
                                    Tidak ada pengalihan barang habis pakai yang sedang diproses.
                                </div>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="p-3 border-t border-zinc-200 dark:border-zinc-800">
            <x-table-pagination :paginator="$items" />
        </div>
    </flux:card>

    {{-- MODAL CREATE PENGALIHAN BARANG --}}
    <flux:modal name="modal-create-pengalihan-barang" wire:model="showCreateModal" class="md:w-[54rem] max-w-[95vw]" style="max-height: 90vh; overflow-y: auto;">
        <div class="space-y-6">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Form Pengajuan Pengalihan Barang (BHP)</flux:heading>
                <flux:subheading>Redistribusikan barang persediaan logistik antar-gudang atau ke unit ruangan</flux:subheading>
            </div>

            <form wire:submit="submitPengalihan" class="space-y-5">
                {{-- Tahap 1: Komoditas Barang & Gudang Asal --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 1: Pilih Komoditas & Batch Fisik Asal
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <flux:select wire:model.live="selected_barang_id" label="Komoditas Barang" size="sm" required>
                                <option value="">-- Pilih Komoditas Barang --</option>
                                @foreach ($daftarBarang as $b)
                                    <option value="{{ $b->id }}">{{ $b->nama }} ({{ $b->satuan?->simbol ?? 'Unit' }})</option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div>
                            <flux:select wire:model.live="gudang_asal_id" label="Gudang Asal (Sumber Stok)" size="sm" required>
                                <option value="">-- Pilih Gudang Asal --</option>
                                @foreach ($gudangs as $g)
                                    <option value="{{ $g->id }}" @disabled((int)$gudang_tujuan_id === (int)$g->id)>{{ $g->nama }} @if((int)$gudang_tujuan_id === (int)$g->id) (Tujuan) @endif</option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>

                    @if ($selected_barang_id && $gudang_asal_id)
                        <div>
                            <flux:select wire:model.live="selected_stok_batch_id" label="Pilih Batch Stok Fisik Asal" size="sm" required>
                                <option value="">-- Pilih Batch Stok --</option>
                                @foreach ($batchesAsal as $bt)
                                    <option value="{{ $bt->id }}">
                                        Batch: {{ $bt->nomor_batch }} &bull; Sisa: {{ $bt->jumlah }} &bull; ED: {{ $bt->tanggal_kedaluwarsa ?? 'Non-ED' }}
                                    </option>
                                @endforeach
                            </flux:select>
                            @if ($batchesAsal->isEmpty())
                                <p class="text-xs text-amber-600 mt-1">Tidak ada batch stok aktif dengan sisa &gt; 0 di gudang asal ini.</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Tahap 2: Lokasi Tujuan & Jumlah Kuantitas --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-4">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 2: Lokasi Tujuan & Jumlah Kuantitas
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {{-- Pilihan Tujuan: Ruangan / Gudang --}}
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                                Lokasi Tujuan Penerima <span class="text-red-500">*</span>
                            </label>
                            @if ($isDestinationLocked)
                                <div class="p-2.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-medium text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                                    <flux:icon name="{{ $tipe_tujuan === 'GUDANG' ? 'building-storefront' : 'building-office' }}" class="size-4 text-blue-600" />
                                    <span>{{ $namaLokasiTujuanUser }}</span>
                                </div>
                            @else
                                <div class="flex items-center gap-3 text-xs mb-2">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" value="GUDANG" wire:model.live="tipe_tujuan" class="text-blue-600" />
                                        <span>Gudang</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" value="RUANGAN" wire:model.live="tipe_tujuan" class="text-blue-600" />
                                        <span>Ruangan</span>
                                    </label>
                                </div>

                                @if ($tipe_tujuan === 'GUDANG')
                                    <flux:select wire:model.live="gudang_tujuan_id" size="sm" required>
                                        <option value="">-- Pilih Gudang Tujuan --</option>
                                        @foreach ($gudangs as $g)
                                            <option value="{{ $g->id }}" @disabled((int)$gudang_asal_id === (int)$g->id)>{{ $g->nama }}</option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <flux:select wire:model.live="unit_tujuan_id" size="sm" required>
                                        <option value="">-- Pilih Ruangan Tujuan --</option>
                                        @foreach ($units as $u)
                                            <option value="{{ $u->id }}">{{ $u->nama }}</option>
                                        @endforeach
                                    </flux:select>
                                @endif
                            @endif
                        </div>

                        {{-- Jumlah Unit --}}
                        <div>
                            <flux:field>
                                <flux:label>Jumlah Unit Dialihkan <span class="text-red-500">*</span></flux:label>
                                <flux:input type="number" min="1" max="{{ $this->maxQuantity }}" wire:model.live="quantity" required />
                                <flux:description class="text-[11px] text-zinc-500 mt-0.5">
                                    Maksimal {{ $this->maxQuantity }} unit tersedia
                                </flux:description>
                                <flux:error name="quantity" />
                            </flux:field>
                        </div>

                        {{-- Tanggal Pengalihan --}}
                        <div>
                            <flux:field>
                                <flux:label>Tanggal Eksekusi <span class="text-red-500">*</span></flux:label>
                                <flux:input type="date" wire:model="tanggal_pengalihan" required />
                                <flux:error name="tanggal_pengalihan" />
                            </flux:field>
                        </div>
                    </div>

                    {{-- Alasan Pengalihan --}}
                    <flux:field>
                        <flux:label>Alasan / Dasar Pengalihan <span class="text-red-500">*</span></flux:label>
                        <flux:textarea
                            wire:model="alasan"
                            placeholder="Jelaskan kebutuhan relokasi persediaan barang ini..."
                            rows="2"
                            required
                        />
                        <flux:error name="alasan" />
                    </flux:field>
                </div>

                {{-- Footer Buttons --}}
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showCreateModal', false)">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitPengalihan">Ajukan Pengalihan Barang</span>
                        <span wire:loading wire:target="submitPengalihan">Memproses...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- MODAL DETAIL / BAST PENGALIHAN BARANG --}}
    @if ($selectedTransfer)
        <flux:modal name="modal-detail-pengalihan-barang" wire:model="showDetailModal" class="w-full max-w-3xl" style="max-height: 90vh; overflow-y: auto;">
            <div class="space-y-5 pb-2">
                <div class="border-b-2 border-zinc-800 pb-3 text-center">
                    <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">Rumah Sakit Logistik Sentral</div>
                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">
                        BERITA ACARA SERAH TERIMA PENGALIHAN BARANG (BAST)
                    </div>
                    <div class="text-xs font-mono text-zinc-600 dark:text-zinc-400 mt-0.5">
                        Nomor Dokumen: <strong class="text-blue-600">{{ $selectedTransfer->kode }}</strong>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <div>
                        <div class="text-zinc-500 text-[11px]">Tanggal Pengajuan</div>
                        <div class="font-semibold">{{ $selectedTransfer->tanggal_pengalihan?->translatedFormat('d F Y') }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 text-[11px]">Status Dokumen</div>
                        <div>
                            <flux:badge size="sm" :color="$selectedTransfer->status === 'SELESAI' ? 'emerald' : 'blue'">
                                {{ $selectedTransfer->status }}
                            </flux:badge>
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 text-[11px]">Lokasi Asal</div>
                        <div class="font-semibold">{{ $selectedTransfer->nama_lokasi_asal }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 text-[11px]">Lokasi Tujuan</div>
                        <div class="font-semibold text-blue-600">{{ $selectedTransfer->nama_lokasi_tujuan }}</div>
                    </div>
                </div>

                <div>
                    <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-2">Rincian Barang yang Dimutasi:</div>
                    <table class="w-full text-xs text-left border border-zinc-200 dark:border-zinc-800 rounded-lg overflow-hidden">
                        <thead class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                            <tr>
                                <th class="p-2 w-8">#</th>
                                <th class="p-2">Nama Komoditas</th>
                                <th class="p-2">No. Batch</th>
                                <th class="p-2 text-right">Jumlah Unit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($selectedTransfer->detail as $i => $item)
                                <tr>
                                    <td class="p-2 text-zinc-400">{{ $i + 1 }}</td>
                                    <td class="p-2 font-medium">{{ $item->barang?->nama ?? '-' }}</td>
                                    <td class="p-2 font-mono text-zinc-500">{{ $item->stokBatch?->nomor_batch ?? '-' }}</td>
                                    <td class="p-2 font-bold font-mono text-right text-blue-600">{{ $item->jumlah }} {{ $item->barang?->satuan?->simbol ?? 'Unit' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" icon="printer" onclick="window.print()">
                        Cetak BAST
                    </flux:button>
                    <flux:button type="button" variant="primary" wire:click="$set('showDetailModal', false)">
                        Tutup
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- MODAL OTORISASI (APPROVE / REJECT) --}}
    <flux:modal name="modal-approval-pengalihan-barang" wire:model="showApprovalModal" class="w-full max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">
                    {{ $actionType === 'APPROVE' ? 'Setujui Pengalihan Barang' : 'Tolak Pengalihan Barang' }}
                </flux:heading>
                <flux:subheading>
                    {{ $actionType === 'APPROVE' ? 'Izinkan serah terima fisik barang persediaan.' : 'Tolak permohonan dan kembalikan stok fisik ke fasilitas asal.' }}
                </flux:subheading>
            </div>

            <flux:textarea
                wire:model="catatanApproval"
                label="Catatan Otorisasi (Opsional)"
                placeholder="Tuliskan catatan verifikasi pimpinan..."
                rows="3"
            />

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showApprovalModal', false)">
                    Batal
                </flux:button>
                <flux:button
                    variant="{{ $actionType === 'APPROVE' ? 'primary' : 'danger' }}"
                    wire:click="executeApproval"
                >
                    {{ $actionType === 'APPROVE' ? 'Ya, Setujui' : 'Tolak Permohonan' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
