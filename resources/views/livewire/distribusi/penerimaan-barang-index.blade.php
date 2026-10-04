<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Konfirmasi Penerimaan Distribusi Ruangan</flux:heading>
                <flux:subheading>Pemeriksaan fisik dan konfirmasi serah terima barang dari SPPB Gudang ke persediaan lokal ruangan.</flux:subheading>
            </div>
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode SPPB, amprah, gudang..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-56">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status</option>
                    <option value="DIKIRIM">DIKIRIM (Menunggu Penerimaan)</option>
                    <option value="DITERIMA">DITERIMA (Sudah Masuk Stok)</option>
                    <option value="BATAL">BATAL (Dibatalkan)</option>
                </select>
            </div>
        </div>

        {{-- Tabel Pengiriman Barang (SPPB) --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-44">Kode SPPB</flux:table.column>
                <flux:table.column>Ref Amprah</flux:table.column>
                <flux:table.column>Pengirim Gudang</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_kirim'" :direction="$sortDirection" wire:click="sort('tanggal_kirim')">Tanggal Kirim</flux:table.column>
                <flux:table.column class="text-center">Jml Item</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-36">Status</flux:table.column>
                <flux:table.column class="text-right w-48">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarPengiriman as $item)
                    <flux:table.row :key="$item->id">
                        {{-- Kode SPPB --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $item->kode }}
                        </flux:table.cell>

                        {{-- Ref Amprah --}}
                        <flux:table.cell>
                            @if ($item->permintaanDistribusi)
                                <div class="font-mono text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                    {{ $item->permintaanDistribusi->kode }}
                                </div>
                                <div class="mt-0.5">
                                    <x-urgency-badge :urgency="$item->permintaanDistribusi->urgensi" />
                                </div>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400 text-xs">-</span>
                            @endif
                        </flux:table.cell>

                        {{-- Pengirim Gudang --}}
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100 text-xs">
                                {{ $item->gudang?->nama ?? '-' }}
                            </div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                Oleh: {{ $item->pengirim?->nama ?? 'Petugas Gudang' }}
                            </div>
                        </flux:table.cell>

                        {{-- Tanggal Kirim --}}
                        <flux:table.cell class="text-xs tabular-nums">
                            {{ $item->tanggal_kirim?->format('d/m/Y') ?? '-' }}
                        </flux:table.cell>

                        {{-- Jml Item --}}
                        <flux:table.cell class="text-center text-xs">
                            <span class="font-mono font-semibold">{{ $item->detail_count ?? $item->detail->count() }}</span>
                            <span class="text-zinc-500 dark:text-zinc-400 text-[11px]">item</span>
                        </flux:table.cell>

                        {{-- Status --}}
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button
                                    wire:click="showDetail({{ $item->id }})"
                                    size="xs"
                                    variant="ghost"
                                    icon="eye"
                                    title="Lihat Detail SPPB"
                                />

                                @php
                                    $canConfirmThis = $isGlobalAdmin || ($userUnitId && (int) $userUnitId === (int) $item->unit_id);
                                @endphp

                                @if ($item->status === 'DIKIRIM' && $canConfirmThis)
                                    <flux:button
                                        wire:click="confirmTerima({{ $item->id }})"
                                        size="xs"
                                        variant="primary"
                                        class="!bg-emerald-600 hover:!bg-emerald-700 !text-white font-medium shadow-xs"
                                        icon="check-circle"
                                        title="Konfirmasi Terima Barang"
                                        wire:loading.attr="disabled"
                                    >
                                        Konfirmasi Terima
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada pengiriman SPPB yang menunggu penerimaan." :cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Navigasi Paginasi --}}
        <x-table-pagination :paginator="$daftarPengiriman" />
    </flux:card>

    {{-- Modal Detail Pengiriman Barang (SPPB) --}}
    <flux:modal name="modal-detail-pengiriman" wire:model="showDetailModal" class="md:w-[52rem]">
        @if ($selectedSppb)
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail Pengiriman Distribusi (SPPB)</flux:heading>
                            <span class="font-mono text-sm font-bold text-zinc-500 dark:text-zinc-400">#{{ $selectedSppb->kode }}</span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Tanggal Kirim: {{ $selectedSppb->tanggal_kirim?->format('d F Y') }}
                        </flux:subheading>
                    </div>
                    <div class="flex items-center gap-2" style="margin-right: 2.25rem;">
                        @if ($selectedSppb->permintaanDistribusi)
                            <x-urgency-badge :urgency="$selectedSppb->permintaanDistribusi->urgensi" />
                        @endif
                        <x-status-badge :status="$selectedSppb->status" />
                    </div>
                </div>

                {{-- Informasi Pengiriman & Unit --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Penerima</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedSppb->unit?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Gudang Pengirim</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedSppb->gudang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Ref No. Amprah</div>
                        <div class="font-mono font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedSppb->permintaanDistribusi?->kode ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Petugas Pengirim</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedSppb->pengirim?->nama ?? '-' }}</div>
                    </div>
                </div>

                {{-- Catatan Pengiriman jika ada --}}
                @if ($selectedSppb->catatan)
                    <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40 text-xs">
                        <span class="font-semibold text-amber-800 dark:text-amber-400">Catatan SPPB:</span>
                        <span class="text-amber-900 dark:text-amber-200 ml-1">{{ $selectedSppb->catatan }}</span>
                    </div>
                @endif

                {{-- Informasi Bukti Penerimaan jika sudah diterima --}}
                @if ($selectedSppb->penerimaan && $selectedSppb->penerimaan->isNotEmpty())
                    @php
                        $rcv = $selectedSppb->penerimaan->first();
                    @endphp
                    <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40 text-xs space-y-1">
                        <div class="flex items-center gap-2 font-semibold text-emerald-800 dark:text-emerald-300">
                            <flux:icon name="check-badge" class="size-4" />
                            <span>Telah Diterima Fisik (Nomor Tanda Terima: {{ $rcv->kode }})</span>
                        </div>
                        <div class="text-emerald-700 dark:text-emerald-400 flex flex-wrap gap-4 text-[11px] mt-1">
                            <span>Tanggal Terima: <strong>{{ $rcv->tanggal_terima?->format('d F Y') ?? '-' }}</strong></span>
                            <span>Diterima Oleh: <strong>{{ $rcv->penerima?->nama ?? '-' }}</strong></span>
                            <span>Status: <strong>{{ $rcv->status }}</strong></span>
                        </div>
                    </div>
                @endif

                {{-- Rincian Item & Alokasi Batch FEFO yang Dikirim --}}
                <div class="space-y-2">
                    <flux:heading size="sm">Rincian Komoditas & Batch FEFO yang Dikirim</flux:heading>
                    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="p-2.5 text-center w-10">No</th>
                                    <th class="p-2.5">Nama Komoditas</th>
                                    <th class="p-2.5">Satuan</th>
                                    <th class="p-2.5">No. Batch</th>
                                    <th class="p-2.5">Kedaluwarsa</th>
                                    <th class="p-2.5">Rak Asal</th>
                                    <th class="p-2.5 text-right w-28">Jumlah Kirim</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 font-mono">
                                @forelse ($selectedSppb->detail as $idx => $detail)
                                    <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                        <td class="p-2.5 text-center text-zinc-500">{{ $idx + 1 }}</td>
                                        <td class="p-2.5 font-sans font-medium text-zinc-900 dark:text-zinc-100">
                                            <div>{{ $detail->barang?->nama ?? '-' }}</div>
                                            <div class="font-mono text-[10px] text-zinc-500 dark:text-zinc-400">{{ $detail->barang?->kode }}</div>
                                        </td>
                                        <td class="p-2.5 font-sans text-zinc-600 dark:text-zinc-400">
                                            {{ $detail->barang?->satuan?->nama ?? '-' }}
                                        </td>
                                        <td class="p-2.5 text-zinc-800 dark:text-zinc-200">
                                            {{ $detail->stokBatch?->nomor_batch ?? '-' }}
                                        </td>
                                        <td class="p-2.5 text-zinc-600 dark:text-zinc-400 tabular-nums">
                                            {{ $detail->stokBatch?->tanggal_kedaluwarsa?->format('d/m/Y') ?? '-' }}
                                        </td>
                                        <td class="p-2.5 font-sans text-zinc-600 dark:text-zinc-400">
                                            {{ $detail->stokBatch?->lokasiGudang?->nama ?? '-' }}
                                        </td>
                                        <td class="p-2.5 text-right font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                                            {{ number_format($detail->jumlah_dikirim) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-zinc-500 dark:text-zinc-400 font-sans">
                                            Tidak ada rincian item pengiriman.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-zinc-50 dark:bg-zinc-800 font-semibold border-t border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <td colspan="6" class="p-2.5 text-right font-sans text-zinc-700 dark:text-zinc-300">Total Unit Dikirim:</td>
                                    <td class="p-2.5 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                                        {{ number_format($selectedSppb->detail->sum('jumlah_dikirim')) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                @php
                    $canConfirmThisModal = $isGlobalAdmin || ($userUnitId && (int) $userUnitId === (int) $selectedSppb->unit_id);
                @endphp

                @if ($selectedSppb->status === 'DIKIRIM' && $canConfirmThisModal)
                    <div class="flex items-center justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button
                            wire:click="confirmTerima({{ $selectedSppb->id }})"
                            variant="primary"
                            class="!bg-emerald-600 hover:!bg-emerald-700 !text-white"
                            icon="check-circle"
                            wire:loading.attr="disabled"
                        >
                            Konfirmasi Terima Barang
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
