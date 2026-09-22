<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Otorisasi Distribusi Logistik (Admin Sentral)</flux:heading>
                <flux:subheading>Review amprah ruangan yang telah disetujui Karu dan tentukan kuota persetujuan distribusi berdasarkan stok fisik gudang.</flux:subheading>
            </div>
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode amprah, unit, atau gudang..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-56">
                <select wire:model.live="filterStatus" class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 font-medium">
                    <option value="">Semua Status Terkait</option>
                    <option value="DISETUJUI_KARU">DISETUJUI KARU (Perlu Review)</option>
                    <option value="DISETUJUI_GUDANG">DISETUJUI GUDANG</option>
                    <option value="SEDANG_DIKIRIM">SEDANG DIKIRIM</option>
                    <option value="SELESAI">SELESAI</option>
                    <option value="DITOLAK">DITOLAK</option>
                </select>
            </div>

            <div class="w-full sm:w-44">
                <select wire:model.live="filterUrgensi" class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 font-medium">
                    <option value="">Semua Urgensi</option>
                    <option value="REGULER">REGULER</option>
                    <option value="CITO">CITO</option>
                </select>
            </div>
        </div>

        {{-- Tabel Permintaan Distribusi --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-40">Kode Amprah</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'unit_id'" :direction="$sortDirection" wire:click="sort('unit_id')">Unit Pemohon</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_permintaan'" :direction="$sortDirection" wire:click="sort('tanggal_permintaan')">Tanggal</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'urgensi'" :direction="$sortDirection" wire:click="sort('urgensi')" class="text-center">Urgensi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-36">Status</flux:table.column>
                <flux:table.column class="text-right w-64">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $item->kode }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->unit?->nama ?? '-' }}</div>
                            <div class="text-[11px] text-zinc-500">Gudang: {{ $item->gudang?->nama ?? '-' }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs tabular-nums text-zinc-700 dark:text-zinc-300">
                            {{ $item->tanggal_permintaan?->format('d/m/Y') }}
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-urgency-badge :urgency="$item->urgensi" />
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="eye"
                                    wire:click="openDetail({{ $item->id }})"
                                    title="Lihat Detail Permintaan"
                                />

                                @if ($item->status === 'DISETUJUI_KARU')
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        icon="clipboard-document-check"
                                        wire:click="openReview({{ $item->id }})"
                                        class="bg-blue-600 hover:bg-blue-700 text-white"
                                    >
                                        Review & Setujui
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        icon="x-mark"
                                        wire:click="confirmRejectGudang({{ $item->id }})"
                                    >
                                        Tolak
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Tidak ada permintaan distribusi yang sesuai dengan filter." :cols="6" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Review & Kuota Distribusi --}}
    <flux:modal name="modal-review-kuota" wire:model="showReviewModal" class="md:w-4xl max-w-5xl">
        @if ($selectedPermintaan)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Review & Penyesuaian Kuota Distribusi</flux:heading>
                    <flux:subheading class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        Periksa ketersediaan stok fisik gudang dan sesuaikan kuota barang yang disetujui untuk didistribusikan.
                    </flux:subheading>
                </div>

                {{-- Header Ringkasan Permintaan --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Kode Amprah</span>
                        <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->kode }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Unit Pemohon</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->unit?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Gudang Penyedia</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->gudang?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Tingkat Urgensi</span>
                        <div class="mt-0.5">
                            <x-urgency-badge :urgency="$selectedPermintaan->urgensi" />
                        </div>
                    </div>
                </div>

                @if ($selectedPermintaan->catatan)
                    <div class="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 rounded-lg text-xs">
                        <span class="font-semibold text-amber-800 dark:text-amber-300">Catatan Pemohon:</span>
                        <span class="text-amber-900 dark:text-amber-200 ml-1">{{ $selectedPermintaan->catatan }}</span>
                    </div>
                @endif

                {{-- Tabel Penyesuaian Kuota --}}
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 font-semibold">
                                <th class="py-2.5 px-3">Nama Barang</th>
                                <th class="py-2.5 px-3 text-center">Satuan</th>
                                <th class="py-2.5 px-3 text-right">Diminta Karu</th>
                                <th class="py-2.5 px-3 text-right">Stok Gudang Tersedia</th>
                                <th class="py-2.5 px-3 text-right w-40">Input Jml Disetujui</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($itemQtys as $detailId => $item)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                    <td class="py-2.5 px-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item['barang_nama'] }}</div>
                                        @if (!empty($item['barang_kode']))
                                            <div class="text-[11px] font-mono text-zinc-500">{{ $item['barang_kode'] }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-center text-zinc-600 dark:text-zinc-400">
                                        {{ $item['satuan'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ number_format($item['jumlah_diminta'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono">
                                        @if ($item['stok_gudang'] <= 0)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">
                                                0 (Kosong)
                                            </span>
                                        @elseif ($item['stok_gudang'] < $item['jumlah_diminta'])
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                                {{ number_format($item['stok_gudang'], 0, ',', '.') }} (Kurang)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                {{ number_format($item['stok_gudang'], 0, ',', '.') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <div class="flex flex-col items-end">
                                            <input
                                                type="number"
                                                wire:model="itemQtys.{{ $detailId }}.jumlah_disetujui"
                                                min="0"
                                                max="{{ $item['stok_gudang'] }}"
                                                class="w-24 text-right rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-2 py-1 text-xs font-mono text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            />
                                            @error("itemQtys.{$detailId}.jumlah_disetujui")
                                                <span class="text-[11px] text-rose-500 mt-1">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 italic">
                        * Jumlah disetujui tidak boleh melebihi jumlah permintaan maupun saldo stok fisik gudang yang tersedia.
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button type="button" variant="ghost" wire:click="$set('showReviewModal', false)">
                            Batal
                        </flux:button>
                        <flux:button type="button" variant="primary" wire:click="confirmApproveGudang" class="bg-blue-600 hover:bg-blue-700 text-white">
                            Setujui Distribusi
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Detail Permintaan Distribusi --}}
    <flux:modal name="modal-detail-distribusi" wire:model="showDetailModal" class="md:w-3xl max-w-4xl">
        @if ($selectedPermintaan)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Detail Permintaan Distribusi</flux:heading>
                    <flux:subheading class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        Informasi lengkap permohonan amprah barang ruangan.
                    </flux:subheading>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Kode Amprah</span>
                        <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->kode }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Unit Pemohon</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->unit?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Gudang Penyedia</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->gudang?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Tanggal Permintaan</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->tanggal_permintaan?->format('d/m/Y') }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Diajukan Oleh</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->pengaju?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Otorisator</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->penyetuju?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Tingkat Urgensi</span>
                        <div class="mt-0.5">
                            <x-urgency-badge :urgency="$selectedPermintaan->urgensi" />
                        </div>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Status Terakhir</span>
                        <div class="mt-0.5">
                            <x-status-badge :status="$selectedPermintaan->status" />
                        </div>
                    </div>
                </div>

                @if ($selectedPermintaan->catatan)
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 rounded-lg text-xs">
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Catatan:</span>
                        <span class="text-zinc-600 dark:text-zinc-400 ml-1">{{ $selectedPermintaan->catatan }}</span>
                    </div>
                @endif

                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 font-semibold">
                                <th class="py-2.5 px-3">Nama Barang</th>
                                <th class="py-2.5 px-3 text-center">Satuan</th>
                                <th class="py-2.5 px-3 text-right">Jumlah Diminta</th>
                                <th class="py-2.5 px-3 text-right">Jumlah Disetujui</th>
                                <th class="py-2.5 px-3">Catatan Item</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($selectedPermintaan->detail as $detail)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                    <td class="py-2.5 px-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->barang?->nama ?? '-' }}</div>
                                        @if ($detail->barang?->kode)
                                            <div class="text-[11px] font-mono text-zinc-500">{{ $detail->barang->kode }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-center text-zinc-600 dark:text-zinc-400">
                                        {{ $detail->barang?->satuan?->nama ?? '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($detail->jumlah, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-semibold">
                                        @if ($detail->jumlah_disetujui !== null)
                                            <span class="text-blue-600 dark:text-blue-400">{{ number_format($detail->jumlah_disetujui, 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-zinc-400 italic">-</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-zinc-600 dark:text-zinc-400">
                                        {{ $detail->catatan ?: '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showDetailModal', false)">
                        Tutup
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Konfirmasi Aksi --}}
    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
