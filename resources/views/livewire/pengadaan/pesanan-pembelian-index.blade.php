<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Pesanan Pembelian (PO)</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Kelola pesanan pembelian yang dikonversi dari permintaan pengadaan yang disetujui.</flux:subheading>
            </div>

        </div>

        {{-- Navigasi Sub-Tab Hub Pengadaan --}}
        <x-tabs.pengadaan-transaksi active="po" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode PO atau nama vendor..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-56">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status</option>
                    <option value="{{ \App\Enums\Pengadaan\StatusPesananPembelian::Diterbitkan->value }}">DITERBITKAN</option>
                    <option value="{{ \App\Enums\Pengadaan\StatusPesananPembelian::DikirimVendor->value }}">DIKIRIM VENDOR</option>
                    <option value="{{ \App\Enums\Pengadaan\StatusPesananPembelian::DiterimaSebagian->value }}">DITERIMA SEBAGIAN</option>
                    <option value="{{ \App\Enums\Pengadaan\StatusPesananPembelian::DiterimaLengkap->value }}">DITERIMA LENGKAP</option>
                    <option value="{{ \App\Enums\Pengadaan\StatusPesananPembelian::Dibatalkan->value }}">DIBATALKAN</option>
                </select>
            </div>
            @if ($search || $filterStatus)
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

        {{-- Tabel PO --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode PO</flux:table.column>
                <flux:table.column>Vendor</flux:table.column>
                <flux:table.column>Ref PR</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_po'" :direction="$sortDirection" wire:click="sort('tanggal_po')">Tanggal PO</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'total_biaya'" :direction="$sortDirection" wire:click="sort('total_biaya')" class="text-right">Total Biaya</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-36">Status</flux:table.column>
                <flux:table.column class="text-right w-28">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->pemasok?->nama ?? '-' }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $item->permintaanPengadaan?->kode ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $item->tanggal_po?->translatedFormat('d M Y') ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                            <x-ui.rupiah :value="$item->total_biaya" class="font-semibold text-zinc-900 dark:text-zinc-100" />
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-ui.status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    @can('view_pengadaan::pesanan::pembelian')
                                        <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                            Detail Kontrak PO
                                        </flux:menu.item>
                                    @endcan
                                    @if ($item->status === \App\Enums\Pengadaan\StatusPesananPembelian::Diterbitkan->value)
                                        @can('update_pengadaan::pesanan::pembelian')
                                            <flux:menu.item wire:click="confirmUpdateStatusKirim({{ $item->id }})" icon="truck">
                                                Tandai Dikirim Vendor
                                            </flux:menu.item>
                                        @endcan
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-ui.empty-state message="Belum ada pesanan pembelian yang terdaftar atau sesuai kriteria pencarian." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-ui.table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Konversi PR → PO --}}
    <flux:modal name="modal-konversi-po" wire:model="showModal" class="md:w-[54rem]">
        <form wire:submit="savePo" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Konversi PR → Pesanan Pembelian</flux:heading>
                <flux:subheading>Buat PO baru dari permintaan pengadaan yang telah disetujui.</flux:subheading>
            </div>

            {{-- Tahap 1: Kontrak Vendor & Syarat Pembayaran --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-4">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 1: Rekanan Vendor & Ketentuan Kontrak PO
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <flux:input wire:model="kode" label="Kode PO (Otomatis)" disabled readonly class="font-mono bg-zinc-100 dark:bg-zinc-800/80 cursor-not-allowed font-semibold" />

                    <div>
                        <x-ui.searchable-select
                            model="pemasok_id"
                            label="Vendor / Pemasok"
                            placeholder="Pilih Vendor..."
                            :options="$daftarPemasok"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Syarat Pembayaran <span class="text-red-500">*</span></label>
                        <select wire:model="syarat_pembayaran" required class="w-full text-sm rounded-lg border {{ $errors->has('syarat_pembayaran') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                            <option value="TEMPO_30_HARI">Tempo 30 Hari</option>
                            <option value="TEMPO_60_HARI">Tempo 60 Hari</option>
                            <option value="TUNAI">Tunai</option>
                        </select>
                        @error('syarat_pembayaran') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input wire:model="estimasi_tanggal_kirim" label="Estimasi Tanggal Kirim" type="date" />
                    <flux:textarea wire:model="catatan" label="Catatan Tambahan (Opsional)" placeholder="Catatan instruksi pengiriman atau syarat teknis..." rows="2" />
                </div>
            </div>
            {{-- Tabel Item Barang --}}
            {{-- Tahap 2: Penetapan Harga & Rincian Item PO --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 2: Negosiasi Harga Satuan & Verifikasi Nilai Total PO
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-xs text-zinc-500">
                                <th class="text-left py-2 px-2">#</th>
                                <th class="text-left py-2 px-2">Nama Barang</th>
                                <th class="text-right py-2 px-2">Jumlah</th>
                                <th class="text-right py-2 px-2 w-48">Harga Satuan</th>
                                <th class="text-right py-2 px-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($itemRows as $i => $row)
                                <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                    <td class="py-2 px-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $i + 1 }}</td>
                                    <td class="py-2 px-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $row['nama_barang'] }}</td>
                                    <td class="py-2 px-2 text-right font-mono tabular-nums">{{ number_format($row['jumlah'], 0, ',', '.') }}</td>
                                    <td class="py-2 px-2">
                                        <x-ui.number-input model="itemRows.{{ $i }}.harga_satuan" label="" prefix="Rp" />
                                    </td>
                                    <td class="py-2 px-2 text-right font-mono tabular-nums font-semibold">
                                        Rp {{ number_format($row['subtotal'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-zinc-300 dark:border-zinc-600">
                                <td colspan="4" class="py-2 px-2 text-right font-bold text-zinc-700 dark:text-zinc-300">Total Biaya</td>
                                <td class="py-2 px-2 text-right font-mono tabular-nums font-bold text-zinc-900 dark:text-zinc-100">
                                    Rp {{ number_format(collect($itemRows)->sum('subtotal'), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @error('itemRows.*.harga_satuan') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="savePo">Terbitkan Pesanan Pembelian</span>
                    <span wire:loading wire:target="savePo">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Detail PO --}}
    <flux:modal name="modal-detail-po" wire:model="showDetailModal" class="md:w-[48rem]">
        @if ($selectedPo)
            <div class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Detail Pesanan Pembelian</flux:heading>
                    <flux:subheading>{{ $selectedPo->kode }}</flux:subheading>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-zinc-500">Vendor</div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPo->pemasok?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Ref PR</div>
                        <div class="font-mono font-medium">{{ $selectedPo->permintaanPengadaan?->kode ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Tanggal PO</div>
                        <div>{{ $selectedPo->tanggal_po?->translatedFormat('d M Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Est. Kirim</div>
                        <div>{{ $selectedPo->estimasi_tanggal_kirim?->translatedFormat('d M Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Syarat Pembayaran</div>
                        <div>{{ str_replace('_', ' ', $selectedPo->syarat_pembayaran) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Status</div>
                        <div><x-ui.status-badge :status="$selectedPo->status" /></div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Dibuat Oleh</div>
                        <div>{{ $selectedPo->pembuat?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Total Biaya</div>
                        <div class="font-mono font-bold"><x-ui.rupiah :value="$selectedPo->total_biaya" /></div>
                    </div>
                    @if ($selectedPo->catatan)
                        <div class="col-span-full">
                            <div class="text-xs text-zinc-500">Catatan</div>
                            <div>{{ $selectedPo->catatan }}</div>
                        </div>
                    @endif
                </div>

                {{-- Item Detail --}}
                <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Item Pesanan</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 text-xs text-zinc-500">
                                    <th class="text-left py-2 px-2">#</th>
                                    <th class="text-left py-2 px-2">Nama Barang</th>
                                    <th class="text-right py-2 px-2">Dipesan</th>
                                    <th class="text-right py-2 px-2">Diterima</th>
                                    <th class="text-right py-2 px-2">Harga Satuan</th>
                                    <th class="text-right py-2 px-2">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($selectedPo->detail as $i => $d)
                                    <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                        <td class="py-2 px-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $i + 1 }}</td>
                                        <td class="py-2 px-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $d->barang?->nama ?? '-' }}</td>
                                        <td class="py-2 px-2 text-right font-mono tabular-nums">{{ number_format($d->jumlah_dipesan, 0, ',', '.') }}</td>
                                        <td class="py-2 px-2 text-right font-mono tabular-nums">{{ number_format($d->jumlah_diterima, 0, ',', '.') }}</td>
                                        <td class="py-2 px-2 text-right font-mono tabular-nums">
                                            <x-ui.rupiah :value="$d->harga_satuan" />
                                        </td>
                                        <td class="py-2 px-2 text-right font-mono tabular-nums font-semibold">
                                            <x-ui.rupiah :value="$d->subtotal" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <td colspan="5" class="py-2 px-2 text-right font-bold text-zinc-700 dark:text-zinc-300">Total</td>
                                    <td class="py-2 px-2 text-right font-mono tabular-nums font-bold text-zinc-900 dark:text-zinc-100">
                                        <x-ui.rupiah :value="$selectedPo->total_biaya" />
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div>
        @endif
    </flux:modal>

    <x-ui.confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
