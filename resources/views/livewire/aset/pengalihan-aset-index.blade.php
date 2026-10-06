<div class="space-y-6">
    {{-- Header & Sub-Navigasi Sirkulasi Aset --}}
    <x-sirkulasi-peminjaman-tabs active="pengalihan" />

    {{-- Header Aksi & Judul --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight">Pengalihan & Mutasi Aset</flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                Mutasi kepemilikan operasional fisik aset antar-ruangan dan gudang secara permanen
            </flux:subheading>
        </div>

        @can('create_layanan_unit::pengalihan::aset')
            <flux:button variant="primary" icon="arrows-right-left" wire:click="openCreateModal">
                Ajukan Pengalihan Aset
            </flux:button>
        @endcan
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
            <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Total Aset Dimutasi</div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1 tabular-nums">{{ number_format($kpiTotalAset) }}</div>
            <div class="text-[11px] text-zinc-500 mt-0.5">Unit alat fisik terpindah</div>
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

            <div class="text-xs text-zinc-500 hidden sm:block">
                {{ $activeTab === 'aktif' ? 'Menampilkan dokumen proses berjalan' : 'Riwayat mutasi selesai & dibatalkan' }}
            </div>
        </div>

        {{-- Filter & Search Toolbar --}}
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="flex-1 max-w-md">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode TRF, nomor seri, nama alat, lokasi..."
                    icon="magnifying-glass"
                    size="sm"
                />
            </div>

            <div class="flex items-center gap-2">
                @if ($activeTab === 'aktif')
                    <flux:select wire:model.live="filterStatus" size="sm" class="w-44">
                        <option value="">Semua Status</option>
                        <option value="DIAJUKAN">DIAJUKAN</option>
                        <option value="DISETUJUI">DISETUJUI</option>
                    </flux:select>
                @else
                    <flux:select wire:model.live="filterStatus" size="sm" class="w-44">
                        <option value="">Semua Status</option>
                        <option value="SELESAI">SELESAI</option>
                        <option value="DITOLAK">DITOLAK</option>
                        <option value="DIBATALKAN">DIBATALKAN</option>
                    </flux:select>
                @endif
            </div>
        </div>

        {{-- Table Pengalihan --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-12 text-zinc-500 font-mono text-xs">#</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-40 font-mono">Kode Dokumen</flux:table.column>
                <flux:table.column class="w-60">Asal &rarr; Tujuan</flux:table.column>
                <flux:table.column class="w-60">Daftar Aset Fisik</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                <flux:table.column class="text-right w-20">Aksi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($items as $idx => $row)
                    <flux:table.row :key="'trf-row-'.$row->id">
                        <flux:table.cell class="text-zinc-500 font-mono text-xs">
                            {{ $items->firstItem() + $idx }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-mono font-bold text-xs text-blue-600 dark:text-blue-400">
                                {{ $row->kode }}
                            </div>
                            <div class="text-[11px] text-zinc-500 mt-0.5">
                                {{ $row->tanggal_pengalihan?->translatedFormat('d M Y') ?? '-' }}
                                &bull; <span class="text-zinc-600 dark:text-zinc-400">{{ $row->pengaju?->nama ?? 'Pengaju' }}</span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center gap-1.5 text-xs font-medium">
                                <span class="px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 flex items-center gap-1">
                                    <flux:icon name="{{ $row->tipe_asal === 'RUANGAN' ? 'building-office' : 'building-storefront' }}" class="size-3 text-zinc-400" />
                                    <span>{{ $row->nama_lokasi_asal }}</span>
                                </span>
                                <flux:icon name="arrow-right" class="size-3 text-zinc-400 shrink-0" />
                                <span class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 flex items-center gap-1">
                                    <flux:icon name="{{ $row->tipe_tujuan === 'RUANGAN' ? 'building-office' : 'building-storefront' }}" class="size-3 text-blue-400" />
                                    <span>{{ $row->nama_lokasi_tujuan }}</span>
                                </span>
                            </div>
                            @if ($row->alasan)
                                <div class="text-[11px] text-zinc-500 truncate max-w-xs mt-0.5" title="{{ $row->alasan }}">
                                    Ket: {{ $row->alasan }}
                                </div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $row->detail->count() }} Unit Aset
                            </div>
                            <div class="text-[11px] text-zinc-500 truncate max-w-xs mt-0.5">
                                {{ $row->detail->map(fn($d) => ($d->aset?->barang?->nama ?? 'Aset') . ' (' . ($d->aset?->kode_aset ?? '-') . ')')->take(2)->implode(', ') }}
                                @if ($row->detail->count() > 2)
                                    <span class="text-zinc-400"> +{{ $row->detail->count() - 2 }} lainnya</span>
                                @endif
                            </div>
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
                                        @if (auth()->user()?->can('update_layanan_unit::pengalihan::aset') || auth()->user()?->hasRole(['super_admin', 'admin_logistik', 'kepala_unit', 'kepala_gudang']))
                                            <flux:menu.item icon="check" wire:click="openApprovalModal({{ $row->id }}, 'APPROVE')">
                                                Setujui Pengalihan
                                            </flux:menu.item>
                                            <flux:menu.item icon="x-mark" class="text-red-600" wire:click="openApprovalModal({{ $row->id }}, 'REJECT')">
                                                Tolak Pengalihan
                                            </flux:menu.item>
                                        @endif

                                        @if (auth()->user()?->can('delete_layanan_unit::pengalihan::aset') || auth()->user()?->can('create_layanan_unit::pengalihan::aset') || auth()->user()?->hasRole(['super_admin', 'admin_logistik']))
                                            <flux:menu.item icon="trash" class="text-zinc-500" wire:click="cancelPengalihan({{ $row->id }})">
                                                Batalkan Pengajuan
                                            </flux:menu.item>
                                        @endif
                                    @endif

                                    @if ($row->status === 'DISETUJUI')
                                        @if (auth()->user()?->can('update_layanan_unit::pengalihan::aset') || auth()->user()?->hasRole(['super_admin', 'admin_logistik', 'kepala_unit', 'petugas_unit', 'kepala_gudang', 'petugas_gudang']))
                                            <flux:menu.item icon="arrow-down-tray" class="text-blue-600 font-semibold" wire:click="openTerimaModal({{ $row->id }})">
                                                Konfirmasi Serah Terima Fisik
                                            </flux:menu.item>
                                        @endif
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
                                    {{ $activeTab === 'aktif' ? 'Belum Ada Pengalihan Aktif' : 'Arsip Mutasi Masih Kosong' }}
                                </div>
                                <div class="text-xs text-zinc-500 max-w-sm">
                                    {{ $activeTab === 'aktif'
                                        ? 'Tidak ada permohonan pengalihan aset yang sedang menunggu proses atau serah terima.'
                                        : 'Belum ada riwayat mutasi kepemilikan aset yang telah rampung atau dibatalkan.' }}
                                </div>
                                @if ($activeTab === 'aktif')
                                    @can('create_layanan_unit::pengalihan::aset')
                                        <div class="pt-2">
                                            <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreateModal">
                                                Ajukan Pengalihan Sekarang
                                            </flux:button>
                                        </div>
                                    @endcan
                                @endif
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

    {{-- MODAL CREATE PENGALIHAN ASET --}}
    <flux:modal name="modal-create-pengalihan" wire:model="showCreateModal" class="w-full max-w-3xl" style="max-height: 90vh; overflow-y: auto;">
        <form wire:submit="submitPengalihan" class="space-y-4 pb-2">
            <div>
                <flux:heading size="lg">Form Pengajuan Pengalihan Aset</flux:heading>
                <flux:subheading>
                    Mutasikan aset fisik ke ruangan atau gudang lain secara permanen
                </flux:subheading>
            </div>

            {{-- Grid Asal & Tujuan --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-3.5 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700/60">
                {{-- Lokasi Asal --}}
                <div class="space-y-2.5">
                    <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider flex items-center gap-1.5">
                        <flux:icon name="arrow-up-tray" class="size-3.5 text-zinc-400" />
                        <span>Lokasi Asal (Sumber Aset)</span>
                    </div>

                    <div class="flex items-center gap-3 text-xs">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" value="RUANGAN" wire:model.live="tipe_asal" class="text-blue-600" />
                            <span>Ruangan / Unit</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" value="GUDANG" wire:model.live="tipe_asal" class="text-blue-600" />
                            <span>Gudang Logistik</span>
                        </label>
                    </div>

                    @if ($tipe_asal === 'RUANGAN')
                        <flux:select wire:model.live="unit_asal_id" label="Pilih Ruangan Asal" size="sm">
                            <option value="">-- Pilih Ruangan Asal --</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}">{{ $u->nama }}</option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:select wire:model.live="gudang_asal_id" label="Pilih Gudang Asal" size="sm">
                            <option value="">-- Pilih Gudang Asal --</option>
                            @foreach ($gudangs as $g)
                                <option value="{{ $g->id }}">{{ $g->nama }}</option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>

                {{-- Lokasi Tujuan --}}
                <div class="space-y-2.5">
                    <div class="text-xs font-bold text-blue-700 dark:text-blue-300 uppercase tracking-wider flex items-center gap-1.5">
                        <flux:icon name="arrow-down-tray" class="size-3.5 text-blue-500" />
                        <span>Lokasi Tujuan (Permanen)</span>
                    </div>

                    <div class="flex items-center gap-3 text-xs">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" value="RUANGAN" wire:model.live="tipe_tujuan" class="text-blue-600" />
                            <span>Ruangan / Unit</span>
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" value="GUDANG" wire:model.live="tipe_tujuan" class="text-blue-600" />
                            <span>Gudang Logistik</span>
                        </label>
                    </div>

                    @if ($tipe_tujuan === 'RUANGAN')
                        <flux:select wire:model.live="unit_tujuan_id" label="Pilih Ruangan Tujuan" size="sm">
                            <option value="">-- Pilih Ruangan Tujuan --</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}" @disabled($tipe_asal === 'RUANGAN' && (int)$unit_asal_id === (int)$u->id)>
                                    {{ $u->nama }} @if($tipe_asal === 'RUANGAN' && (int)$unit_asal_id === (int)$u->id) (Asal) @endif
                                </option>
                            @endforeach
                        </flux:select>
                        @error('unit_tujuan_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    @else
                        <flux:select wire:model.live="gudang_tujuan_id" label="Pilih Gudang Tujuan" size="sm">
                            <option value="">-- Pilih Gudang Tujuan --</option>
                            @foreach ($gudangs as $g)
                                <option value="{{ $g->id }}" @disabled($tipe_asal === 'GUDANG' && (int)$gudang_asal_id === (int)$g->id)>
                                    {{ $g->nama }} @if($tipe_asal === 'GUDANG' && (int)$gudang_asal_id === (int)$g->id) (Asal) @endif
                                </option>
                            @endforeach
                        </flux:select>

                        @if ($raks->isNotEmpty())
                            <flux:select wire:model.live="lokasi_gudang_tujuan_id" label="Rak Simpan Gudang (Opsional)" size="sm">
                                <option value="">-- Tanpa Rak Khusus --</option>
                                @foreach ($raks as $rak)
                                    <option value="{{ $rak->id }}">{{ $rak->kode }} - {{ $rak->nama }}</option>
                                @endforeach
                            </flux:select>
                        @endif
                        @error('gudang_tujuan_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    @endif
                </div>
            </div>

            {{-- Pemilihan Aset Fisik --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-bold text-zinc-700 dark:text-zinc-300">
                        Pilih Aset Fisik yang Hendak Dialihkan <span class="text-red-500">*</span>
                    </label>
                    <span class="text-xs text-zinc-500">
                        Terpilih: <strong class="text-blue-600">{{ count($selected_aset_ids) }}</strong> Aset
                    </span>
                </div>

                <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-2 max-h-52 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800/80 bg-white dark:bg-zinc-900">
                    @forelse ($asetTersedia as $ast)
                        <label class="flex items-center justify-between p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/40 rounded-lg cursor-pointer text-xs">
                            <div class="flex items-center gap-2.5">
                                <input
                                    type="checkbox"
                                    value="{{ $ast->id }}"
                                    wire:model.live="selected_aset_ids"
                                    class="rounded text-blue-600"
                                />
                                <div>
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $ast->barang?->nama ?? 'Aset' }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500 font-mono">
                                        Kode: {{ $ast->kode_aset }} &bull; SN: {{ $ast->nomor_seri ?? '-' }}
                                    </div>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                {{ $ast->kondisi }}
                            </span>
                        </label>
                    @empty
                        <div class="py-6 text-center text-xs text-zinc-400">
                            Tidak ada aset fisik tersedia di lokasi asal yang dipilih.
                        </div>
                    @endforelse
                </div>
                @error('selected_aset_ids') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            {{-- Justifikasi Alasan --}}
            <div class="space-y-1">
                <flux:textarea
                    wire:model="alasan"
                    label="Alasan / Dasar Pengalihan Aset"
                    placeholder="Contoh: Pemindahan permanen alat monitor jantung untuk perluasan ruang ICU gedung B..."
                    rows="2"
                    required
                />
                @error('alasan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            {{-- Footer Buttons --}}
            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showCreateModal', false)">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submitPengalihan">Ajukan Pengalihan</span>
                    <span wire:loading wire:target="submitPengalihan">Memproses...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- MODAL DETAIL / BERITA ACARA SERAH TERIMA (BAST) --}}
    @if ($selectedTransfer)
        <flux:modal name="modal-detail-pengalihan" wire:model="showDetailModal" class="w-full max-w-3xl" style="max-height: 90vh; overflow-y: auto;">
            <div class="space-y-5 pb-2" id="printable-bast">
                {{-- Kop Berita Acara --}}
                <div class="border-b-2 border-zinc-800 pb-3 text-center">
                    <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">Rumah Sakit Logistik Sentral</div>
                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">
                        BERITA ACARA SERAH TERIMA PENGALIHAN ASET (BAST)
                    </div>
                    <div class="text-xs font-mono text-zinc-600 dark:text-zinc-400 mt-0.5">
                        Nomor Dokumen: <strong class="text-blue-600">{{ $selectedTransfer->kode }}</strong>
                    </div>
                </div>

                {{-- Status & Informasi Pokok --}}
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
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $selectedTransfer->nama_lokasi_asal }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 text-[11px]">Lokasi Tujuan (Permanen)</div>
                        <div class="font-semibold text-blue-600 dark:text-blue-400">{{ $selectedTransfer->nama_lokasi_tujuan }}</div>
                    </div>
                </div>

                {{-- Narasi Justifikasi --}}
                <div class="text-xs text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-900 p-3 rounded-lg border border-zinc-200 dark:border-zinc-800">
                    <div class="font-bold text-zinc-500 text-[11px] uppercase mb-1">Dasar / Keperluan Pengalihan:</div>
                    <p>{{ $selectedTransfer->alasan }}</p>
                </div>

                {{-- Tabel Rincian Aset --}}
                <div>
                    <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-2">Daftar Aset yang Dimutasi:</div>
                    <table class="w-full text-xs text-left border border-zinc-200 dark:border-zinc-800 rounded-lg overflow-hidden">
                        <thead class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                            <tr>
                                <th class="p-2 w-8">#</th>
                                <th class="p-2">Kode Aset</th>
                                <th class="p-2">Nama Barang & Seri</th>
                                <th class="p-2">Kondisi Awal</th>
                                <th class="p-2">Kondisi Serah Terima</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($selectedTransfer->detail as $i => $item)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40">
                                    <td class="p-2 text-zinc-400">{{ $i + 1 }}</td>
                                    <td class="p-2 font-mono font-semibold">{{ $item->aset?->kode_aset ?? '-' }}</td>
                                    <td class="p-2">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->aset?->barang?->nama ?? '-' }}</div>
                                        <div class="text-[11px] text-zinc-400">SN: {{ $item->aset?->nomor_seri ?? '-' }}</div>
                                    </td>
                                    <td class="p-2">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-zinc-100 dark:bg-zinc-800 font-medium">
                                            {{ $item->kondisi_sebelum }}
                                        </span>
                                    </td>
                                    <td class="p-2 font-semibold">
                                        {{ $item->kondisi_saat_diterima ?: '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Kolom Tanda Tangan Para Pihak --}}
                <div class="grid grid-cols-3 gap-4 pt-4 border-t border-zinc-200 dark:border-zinc-800 text-center text-xs">
                    <div>
                        <div class="text-zinc-400 text-[11px]">Yang Menyerahkan (Asal)</div>
                        <div class="h-12 flex items-center justify-center font-serif italic text-zinc-400 text-xs">
                            [Tercatat di Sistem]
                        </div>
                        <div class="font-bold text-zinc-800 dark:text-zinc-200">{{ $selectedTransfer->pengaju?->nama ?? '-' }}</div>
                        <div class="text-[10px] text-zinc-400">NIP: {{ $selectedTransfer->pengaju?->nip ?? '-' }}</div>
                    </div>

                    <div>
                        <div class="text-zinc-400 text-[11px]">Mengetahui (Pimpinan)</div>
                        <div class="h-12 flex items-center justify-center font-serif italic text-zinc-400 text-xs">
                            @if ($selectedTransfer->penyetuju) [Disetujui Digital] @else - @endif
                        </div>
                        <div class="font-bold text-zinc-800 dark:text-zinc-200">{{ $selectedTransfer->penyetuju?->nama ?? '-' }}</div>
                        <div class="text-[10px] text-zinc-400">NIP: {{ $selectedTransfer->penyetuju?->nip ?? '-' }}</div>
                    </div>

                    <div>
                        <div class="text-zinc-400 text-[11px]">Yang Menerima (Tujuan)</div>
                        <div class="h-12 flex items-center justify-center font-serif italic text-zinc-400 text-xs">
                            @if ($selectedTransfer->penerima) [Diterima Fisik] @else - @endif
                        </div>
                        <div class="font-bold text-zinc-800 dark:text-zinc-200">{{ $selectedTransfer->penerima?->nama ?? '-' }}</div>
                        <div class="text-[10px] text-zinc-400">NIP: {{ $selectedTransfer->penerima?->nip ?? '-' }}</div>
                    </div>
                </div>

                {{-- Modal Footer --}}
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
    <flux:modal name="modal-approval-pengalihan" wire:model="showApprovalModal" class="w-full max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">
                    {{ $actionType === 'APPROVE' ? 'Setujui Pengalihan Aset' : 'Tolak Pengalihan Aset' }}
                </flux:heading>
                <flux:subheading>
                    {{ $actionType === 'APPROVE' ? 'Izinkan serah terima fisik aset ke lokasi tujuan.' : 'Tolak permohonan dan kembalikan aset ke status normal di lokasi asal.' }}
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

    {{-- MODAL KONFIRMASI SERAH TERIMA FISIK --}}
    @if ($transferTerima)
        <flux:modal name="modal-terima-pengalihan" wire:model="showTerimaModal" class="w-full max-w-2xl" style="max-height: 90vh; overflow-y: auto;">
            <div class="space-y-4 pb-2">
                <div>
                    <flux:heading size="lg">Konfirmasi Serah Terima Fisik Aset</flux:heading>
                    <flux:subheading>
                        Dokumen: <strong class="text-blue-600">{{ $transferTerima->kode }}</strong> &bull; Periksa kondisi fisik tiap alat saat tiba di tujuan
                    </flux:subheading>
                </div>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden">
                    @foreach ($transferTerima->detail as $item)
                        <div class="p-3 bg-white dark:bg-zinc-900 space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-xs font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ $item->aset?->barang?->nama ?? 'Aset' }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500 font-mono">
                                        Kode: {{ $item->aset?->kode_aset }} &bull; SN: {{ $item->aset?->nomor_seri ?? '-' }}
                                    </div>
                                </div>
                                <span class="text-[11px] text-zinc-400">
                                    Kondisi Awal: <strong>{{ $item->kondisi_sebelum }}</strong>
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <div>
                                    <label class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400">Kondisi Diterima:</label>
                                    <select
                                        wire:model="kondisiTerima.{{ $item->aset_id }}.kondisi"
                                        class="w-full text-xs rounded border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-1.5"
                                    >
                                        <option value="BAIK">BAIK</option>
                                        <option value="RUSAK_RINGAN">RUSAK_RINGAN</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400">Catatan Kondisi Fisik:</label>
                                    <input
                                        type="text"
                                        wire:model="kondisiTerima.{{ $item->aset_id }}.catatan"
                                        placeholder="Kondisi fisik normal..."
                                        class="w-full text-xs rounded border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-1.5"
                                    />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 rounded-xl border border-amber-200 dark:border-amber-900 text-xs text-amber-800 dark:text-amber-200">
                    <strong>Pemberitahuan Sistem:</strong> Konfirmasi ini akan memutasi kepemilikan dan lokasi aset secara permanen di database ke unit/gudang tujuan. Aset tidak akan kembali ke lokasi asal.
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button variant="ghost" wire:click="$set('showTerimaModal', false)">
                        Batal
                    </flux:button>
                    <flux:button variant="primary" wire:click="executeTerima">
                        Konfirmasi & Mutasi Kepemilikan
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
