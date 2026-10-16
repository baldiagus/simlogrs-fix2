<div class="space-y-6">
    {{-- Header Aksi & Judul --}}
    <div class="space-y-3">
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
                    @if ($countAktif > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                            {{ $countAktif }}
                        </span>
                    @endif
                </button>

                <button
                    type="button"
                    wire:click="setTab('otorisasi')"
                    class="px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors border-b-2 flex items-center gap-2 {{ $activeTab === 'otorisasi' ? 'border-amber-600 text-amber-600 dark:text-amber-400 bg-white dark:bg-zinc-900' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
                >
                    <flux:icon name="check-badge" class="size-4" />
                    <span>Menunggu Otorisasi</span>
                    @if ($countOtorisasi > 0)
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                            {{ $countOtorisasi }}
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
        {{-- Banner Notifikasi Otorisasi Menunggu --}}
        @if ($activeTab === 'aktif' && $countOtorisasi > 0)
            <div class="mx-4 mt-3 p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <flux:icon name="exclamation-circle" class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
                    <span>Terdapat <strong>{{ $countOtorisasi }} permohonan pengalihan aset</strong> dari unit lain yang menunggu persetujuan Anda di tab <strong>Menunggu Otorisasi</strong>.</span>
                </div>
                <button
                    type="button"
                    wire:click="setTab('otorisasi')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-lg text-xs transition-colors shrink-0"
                >
                    <flux:icon name="check-badge" class="size-3.5" />
                    <span>Buka Tab Otorisasi ({{ $countOtorisasi }})</span>
                </button>
            </div>
        @endif


        {{-- Filter & Search Toolbar --}}
        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
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

                <flux:select wire:model.live="filterTujuanUnitId" size="sm" class="w-44">
                    <option value="">Semua Tujuan</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}">{{ $u->nama }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <style>
            .pengalihan-table th:first-child, .pengalihan-table td:first-child {
                padding-left: 24px !important;
            }
            .pengalihan-table th:last-child, .pengalihan-table td:last-child {
                padding-right: 24px !important;
            }
        </style>
        <flux:table class="pengalihan-table">
            <flux:table.columns sticky class="bg-zinc-50/80 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-700">
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">Kode Dokumen</flux:table.column>
                <flux:table.column class="w-48">Asal Mutasi</flux:table.column>
                <flux:table.column class="w-48">Tujuan Mutasi</flux:table.column>
                <flux:table.column class="w-64">Daftar Aset Fisik</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($items as $idx => $row)
                    <flux:table.row :key="'trf-row-'.$row->id">
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
                            <x-ui.status-badge :status="$row->status" />
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                                <flux:menu>
                                    <flux:menu.item icon="document-text" wire:click="showDetail({{ $row->id }})">
                                        Lihat Berita Acara / Detail
                                    </flux:menu.item>

                                    @if ($activeTab === 'otorisasi' && (int)$row->diajukan_oleh !== (int)auth()->user()?->id && ($izin[$row->id]['otorisasi'] ?? false))
                                        <flux:menu.item icon="check" wire:click="openApprovalModal({{ $row->id }}, 'APPROVE')">
                                            Setujui Pengalihan
                                        </flux:menu.item>
                                        <flux:menu.item icon="x-mark" class="text-red-600" wire:click="openApprovalModal({{ $row->id }}, 'REJECT')">
                                            Tolak Pengalihan
                                        </flux:menu.item>
                                    @endif

                                    @if ($row->status === 'DIAJUKAN' && ($izin[$row->id]['batal'] ?? false))
                                        <flux:menu.item icon="trash" class="text-zinc-500" wire:click="cancelPengalihan({{ $row->id }})">
                                            Batalkan Pengajuan
                                        </flux:menu.item>
                                    @endif

                                    @if ($row->status === 'DISETUJUI' && ($izin[$row->id]['serah_terima'] ?? false))
                                        <flux:menu.item icon="arrow-down-tray" class="text-blue-600 font-semibold" wire:click="openTerimaModal({{ $row->id }})">
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
                                    @if ($activeTab === 'aktif')
                                        Belum Ada Pengalihan Aktif
                                    @elseif ($activeTab === 'otorisasi')
                                        Tidak Ada Permintaan Menunggu Otorisasi
                                    @else
                                        Arsip Mutasi Aset Masih Kosong
                                    @endif
                                </div>
                                <div class="text-xs text-zinc-500 max-w-sm">
                                    @if ($activeTab === 'aktif')
                                        @if ($countOtorisasi > 0)
                                            Tidak ada permohonan aktif keluar/masuk. Namun ada <strong>{{ $countOtorisasi }} permohonan</strong> dari unit lain yang menunggu persetujuan Anda di tab Menunggu Otorisasi.
                                        @else
                                            Tidak ada permohonan pengalihan aset yang sedang diproses oleh fasilitas Anda.
                                        @endif
                                    @elseif ($activeTab === 'otorisasi')
                                        Saat ini tidak ada unit atau gudang lain yang meminta aset dari fasilitas Anda.
                                    @else
                                        Belum ada riwayat mutasi kepemilikan aset yang telah rampung atau dibatalkan.
                                    @endif
                                </div>
                                @if ($activeTab === 'aktif' && $countOtorisasi > 0)
                                    <div class="pt-2">
                                        <flux:button size="sm" variant="primary" wire:click="setTab('otorisasi')">
                                            Buka Tab Menunggu Otorisasi ({{ $countOtorisasi }})
                                        </flux:button>
                                    </div>
                                @endif
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
            <x-ui.table-pagination :paginator="$items" />
        </div>
    </flux:card>

    @include('livewire.aset.partials.pengalihan-aset.modal-create-pengalihan')

    @include('livewire.aset.partials.pengalihan-aset.modal-detail-pengalihan')

    @include('livewire.aset.partials.pengalihan-aset.modal-approval-pengalihan')

    @include('livewire.aset.partials.pengalihan-aset.modal-terima-pengalihan')
</div>
