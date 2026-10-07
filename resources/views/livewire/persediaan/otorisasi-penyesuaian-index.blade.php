<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Otorisasi Penyesuaian Stok Opname</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Verifikasi selisih fisik lapangan terhadap buku sistem dan pengesahan koreksi saldo stok batch secara atomik.</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Sub-Tab Hub Opname & Penyesuaian --}}
        <x-persediaan-opname-tabs active="penyesuaian" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode penyesuaian, gudang, alasan..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="flex items-center gap-2.5">
                <div class="w-48">
                    <select wire:model.live="filterGudangId" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Fasilitas Gudang</option>
                        @foreach ($daftarGudang as $gdg)
                            <option value="{{ $gdg->id }}">{{ $gdg->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-44">
                    <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="MENUNGGU">Menunggu Persetujuan</option>
                        <option value="DISETUJUI">Telah Disahkan (DISETUJUI)</option>
                        <option value="DITOLAK">Ditolak (DITOLAK)</option>
                        <option value="SEMUA">Semua Status</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Tabel Penyesuaian Stok --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-32">Kode Penyesuaian</flux:table.column>
                <flux:table.column class="w-44">Fasilitas Gudang</flux:table.column>
                <flux:table.column class="w-36">Ref Stock Opname</flux:table.column>
                <flux:table.column class="w-28">Tanggal</flux:table.column>
                <flux:table.column class="text-center w-24">Item Selisih</flux:table.column>
                <flux:table.column class="w-48">Alasan / Justifikasi</flux:table.column>
                <flux:table.column class="text-center w-28">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $adj)
                    <flux:table.row :key="$adj->id">
                        {{-- Kode Penyesuaian --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            <div>{{ $adj->kode }}</div>
                            <div class="text-[10px] text-zinc-500 font-sans mt-0.5">
                                Oleh: {{ $adj->pengaju?->nama ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Gudang --}}
                        <flux:table.cell style="white-space: normal;">
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $adj->gudang?->nama ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Ref Opname --}}
                        <flux:table.cell>
                            @if ($adj->stokOpname)
                                <div class="font-mono text-xs font-semibold text-blue-600 dark:text-blue-400">
                                    {{ $adj->stokOpname->kode }}
                                </div>
                                <div class="text-[10px] text-zinc-500">
                                    Tgl: {{ $adj->stokOpname->tanggal_opname?->format('d/m/Y') }}
                                </div>
                            @else
                                <span class="text-zinc-400 text-xs">-</span>
                            @endif
                        </flux:table.cell>

                        {{-- Tanggal Penyesuaian --}}
                        <flux:table.cell class="text-xs tabular-nums text-zinc-700 dark:text-zinc-300">
                            {{ $adj->tanggal_penyesuaian?->format('d/m/Y') }}
                        </flux:table.cell>

                        {{-- Item Selisih --}}
                        <flux:table.cell class="text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                                {{ $adj->detail_count }} batch
                            </span>
                        </flux:table.cell>

                        {{-- Alasan --}}
                        <flux:table.cell style="white-space: normal;">
                            <div class="text-xs text-zinc-700 dark:text-zinc-300 line-clamp-2" title="{{ $adj->alasan }}">
                                {{ $adj->alasan }}
                            </div>
                        </flux:table.cell>

                        {{-- Status --}}
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$adj->status" />
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetail({{ $adj->id }})" icon="eye">
                                        Rincian Selisih
                                    </flux:menu.item>
                                    @if ($adj->status === 'MENUNGGU')
                                        @can('update_gudang_stok::otorisasi::penyesuaian')
                                            <flux:menu.item wire:click="confirmApprove({{ $adj->id }})" icon="check-circle" class="text-emerald-600 dark:text-emerald-400">
                                                Sahkan Penyesuaian
                                            </flux:menu.item>
                                            <flux:menu.item wire:click="confirmReject({{ $adj->id }})" icon="x-circle" variant="danger">
                                                Tolak Penyesuaian
                                            </flux:menu.item>
                                        @endcan
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Tidak ada dokumen penyesuaian stok yang sesuai dengan filter." cols="8" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Rincian Varians & Otorisasi Penyesuaian --}}
    <flux:modal name="modal-detail-penyesuaian" wire:model="showDetailModal" class="w-full max-w-[95vw] lg:max-w-6xl">
        @if ($selectedPenyesuaian)
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Penyesuaian Stok: {{ $selectedPenyesuaian->kode }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Gudang: {{ $selectedPenyesuaian->gudang?->nama }} &bull;
                            Ref Opname: {{ $selectedPenyesuaian->stokOpname?->kode ?? '-' }}
                        </flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <x-status-badge :status="$selectedPenyesuaian->status" />
                    </div>
                </div>

                {{-- Ringkasan Valuasi Selisih --}}
                @php
                    $totalTambahQty = $selectedPenyesuaian->detail->where('jenis_penyesuaian', 'TAMBAH')->sum('jumlah_penyesuaian');
                    $totalKurangQty = $selectedPenyesuaian->detail->where('jenis_penyesuaian', 'KURANG')->sum('jumlah_penyesuaian');
                    $totalNilaiSelisih = $selectedPenyesuaian->detail->sum('subtotal_nilai');
                @endphp
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Total Fisik Bertambah (Surplus)</div>
                        <div class="font-bold text-sm font-mono text-blue-600 dark:text-blue-400 mt-0.5">+{{ number_format($totalTambahQty, 0, ',', '.') }} unit</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Total Fisik Berkurang (Defisit)</div>
                        <div class="font-bold text-sm font-mono text-rose-600 dark:text-rose-400 mt-0.5">-{{ number_format($totalKurangQty, 0, ',', '.') }} unit</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Total Valuasi Nilai Varians</div>
                        <div class="font-bold text-sm font-mono text-zinc-900 dark:text-zinc-100 mt-0.5">Rp {{ number_format($totalNilaiSelisih, 0, ',', '.') }}</div>
                    </div>
                </div>

                {{-- Tabel Rincian Batch yang Disesuaikan --}}
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-xl max-h-[50vh] overflow-y-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700 sticky top-0">
                            <tr>
                                <th class="p-2.5 w-10 text-center">#</th>
                                <th class="p-2.5">Komoditas Barang</th>
                                <th class="p-2.5">Nomor Batch</th>
                                <th class="p-2.5">Lokasi Rak</th>
                                <th class="p-2.5 text-center w-28">Jenis Koreksi</th>
                                <th class="p-2.5 text-right w-24">Jumlah</th>
                                <th class="p-2.5 text-right w-32">Harga Satuan</th>
                                <th class="p-2.5 text-right w-32">Subtotal Nilai</th>
                                <th class="p-2.5">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach ($selectedPenyesuaian->detail as $det)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40">
                                    <td class="p-2.5 text-center text-zinc-400 font-mono">{{ $loop->iteration }}</td>
                                    <td class="p-2.5">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $det->barang?->nama }}</div>
                                        <div class="text-[10px] text-zinc-500 font-mono">{{ $det->barang?->kode }}</div>
                                    </td>
                                    <td class="p-2.5 font-mono text-zinc-800 dark:text-zinc-200">
                                        {{ $det->stokBatch?->nomor_batch ?? '-' }}
                                    </td>
                                    <td class="p-2.5 text-zinc-600 dark:text-zinc-400">
                                        {{ $det->stokBatch?->lokasiGudang?->nama ?? 'Tanpa Rak' }}
                                    </td>
                                    <td class="p-2.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $det->jenis_penyesuaian === 'TAMBAH' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' }}">
                                            {{ $det->jenis_penyesuaian }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-right font-mono font-bold {{ $det->jenis_penyesuaian === 'TAMBAH' ? 'text-blue-600 dark:text-blue-400' : 'text-rose-600 dark:text-rose-400' }}">
                                        {{ $det->jenis_penyesuaian === 'TAMBAH' ? '+' : '-' }}{{ number_format($det->jumlah_penyesuaian, 0, ',', '.') }}
                                    </td>
                                    <td class="p-2.5 text-right font-mono text-zinc-700 dark:text-zinc-300">
                                        <x-rupiah :value="$det->harga_satuan" />
                                    </td>
                                    <td class="p-2.5 text-right font-mono font-semibold text-zinc-900 dark:text-zinc-100">
                                        <x-rupiah :value="$det->subtotal_nilai" />
                                    </td>
                                    <td class="p-2.5 text-zinc-600 dark:text-zinc-400">
                                        {{ $det->catatan ?: '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($selectedPenyesuaian->status === 'MENUNGGU')
                    @can('update_gudang_stok::otorisasi::penyesuaian')
                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                            <flux:button
                                wire:click="confirmReject({{ $selectedPenyesuaian->id }})"
                                variant="danger"
                                icon="x-circle"
                            >
                                Tolak Penyesuaian
                            </flux:button>
                            <flux:button
                                wire:click="confirmApprove({{ $selectedPenyesuaian->id }})"
                                variant="primary"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white"
                                icon="check-circle"
                            >
                                Sahkan Penyesuaian Stok
                            </flux:button>
                        </div>
                    @endcan
                @endif
            </div>
        @endif
    </flux:modal>

    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
