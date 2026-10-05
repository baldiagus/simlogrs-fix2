<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header & Tombol Tambah --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Permintaan Pengadaan (PR)</flux:heading>
                <flux:subheading>Kelola permintaan pengadaan barang dari seluruh unit rumah sakit.</flux:subheading>
            </div>

            @can('create_pengadaan::permintaan::pengadaan')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Tambah PR
                </flux:button>
            @endcan
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode PR atau nama unit..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-56">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status</option>
                    <option value="{{ \App\Models\PermintaanPengadaan::STATUS_DIAJUKAN }}">{{ \App\Models\PermintaanPengadaan::STATUS_DIAJUKAN }}</option>
                    <option value="{{ \App\Models\PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN }}">DITERUSKAN KE PENGADAAN</option>
                    <option value="{{ \App\Models\PermintaanPengadaan::STATUS_DIPROSES }}">DIPROSES (PO)</option>
                    <option value="{{ \App\Models\PermintaanPengadaan::STATUS_SELESAI }}">SELESAI</option>
                    <option value="{{ \App\Models\PermintaanPengadaan::STATUS_DITOLAK }}">{{ \App\Models\PermintaanPengadaan::STATUS_DITOLAK }}</option>
                </select>
            </div>

            <div class="w-full sm:w-40">
                <select wire:model.live="filterUrgensi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Urgensi</option>
                    <option value="REGULER">REGULER</option>
                    <option value="MENDESAK">MENDESAK</option>
                    <option value="CITO">CITO</option>
                </select>
            </div>
            @if ($search || $filterStatus || $filterUrgensi)
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

        {{-- Tabel --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">Kode PR</flux:table.column>
                <flux:table.column class="w-56">Pemohon (Unit / Gudang)</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_permintaan'" :direction="$sortDirection" wire:click="sort('tanggal_permintaan')" class="w-28">Tanggal</flux:table.column>
                <flux:table.column class="w-28 text-center">Urgensi</flux:table.column>
                <flux:table.column class="text-center w-24">Jumlah Item</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-36">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarPr as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell style="white-space: normal;">
                            <button type="button" wire:click="showDetail({{ $item->id }})" class="text-left font-medium text-xs text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors line-clamp-2">
                                {{ $item->nama_pemohon }}
                            </button>
                            <div class="text-[10px] text-zinc-400 mt-0.5 font-mono flex items-center gap-1">
                                @if ($item->unit_id)
                                    <span class="text-emerald-600 dark:text-emerald-400 font-semibold">[Unit]</span>
                                @elseif ($item->gudang_id)
                                    <span class="text-blue-600 dark:text-blue-400 font-semibold">[Gudang]</span>
                                @endif
                                <span>&bull; {{ $item->pengaju?->nama ?? '-' }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs tabular-nums">{{ $item->tanggal_permintaan?->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <x-urgency-badge :urgency="$item->urgensi" />
                        </flux:table.cell>
                        <flux:table.cell class="text-center font-mono text-xs">{{ $item->detail->count() }}</flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-48">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                        Lihat Detail PR
                                    </flux:menu.item>

                                    @php
                                        $canApproveThis = $isGlobalApprover 
                                            || ($userUnitId && $item->unit_id && (int) $userUnitId === (int) $item->unit_id)
                                            || ($userGudangId && $item->gudang_id && (int) $userGudangId === (int) $item->gudang_id);
                                        $approverRoleLabel = $item->gudang_id ? 'Kagud' : ($item->unit?->kode === 'IPSRS' ? 'Ka. IPSRS' : 'Karu');
                                    @endphp

                                    {{-- MEJA 1: Otorisasi Kepala Ruangan / Unit / Gudang (Khusus status DIAJUKAN) --}}
                                    @can('update_pengadaan::permintaan::pengadaan')
                                        @if ($item->status === \App\Models\PermintaanPengadaan::STATUS_DIAJUKAN && $canApproveThis)
                                            <flux:menu.item wire:click="confirmApprove({{ $item->id }})" icon="check-circle" class="text-emerald-600 dark:text-emerald-400">
                                                Setujui ({{ $approverRoleLabel }})
                                            </flux:menu.item>
                                            <flux:menu.item wire:click="confirmReject({{ $item->id }})" icon="x-circle" variant="danger">
                                                Tolak ({{ $approverRoleLabel }})
                                            </flux:menu.item>
                                        @endif
                                    @endcan

                                    {{-- MEJA 2: Meja Bagian Pengadaan (Khusus status DITERUSKAN_KE_PENGADAAN) --}}
                                    @can('create_pengadaan::pesanan::pembelian')
                                        @if ($item->status === \App\Models\PermintaanPengadaan::STATUS_DITERUSKAN_KE_PENGADAAN)
                                            <flux:menu.item
                                                as="a"
                                                href="/{{ request()->segment(1) ?? 'pengadaan' }}/pesanan-pembelian?konversi_pr={{ $item->id }}"
                                                icon="shopping-cart"
                                            >
                                                Terbitkan PO
                                            </flux:menu.item>
                                            <flux:menu.item
                                                wire:click="openRejectPengadaan({{ $item->id }})"
                                                icon="x-circle"
                                                variant="danger"
                                            >
                                                Tolak di Pengadaan
                                            </flux:menu.item>
                                        @endif
                                    @endcan
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada permintaan pengadaan atau sesuai kriteria pencarian." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Pagination --}}
        <x-table-pagination :paginator="$daftarPr" />
    </flux:card>

    {{-- Modal Form Tambah PR --}}
    <flux:modal name="modal-pr-form" wire:model="showModal" class="md:w-[48rem]">
        <form wire:submit="save" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Buat Permintaan Pengadaan Baru</flux:heading>
                <flux:subheading>Lengkapi informasi permintaan dan daftar barang yang dibutuhkan.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model="kode" label="Kode PR (Otomatis)" disabled readonly class="font-mono bg-zinc-100 dark:bg-zinc-800/80 cursor-not-allowed font-semibold" />

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Urgensi <span class="text-red-500">*</span></label>
                    <select wire:model="urgensi" required class="w-full text-sm rounded-lg border {{ $errors->has('urgensi') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none">
                        <option value="REGULER">REGULER</option>
                        <option value="MENDESAK">MENDESAK</option>
                        <option value="CITO">CITO</option>
                    </select>
                    @error('urgensi') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                </div>
            </div>
            {{-- Pilihan Pemohon: Unit vs Gudang (Mutually Exclusive) --}}
            <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-zinc-200/60 dark:border-zinc-700/60">
                    <div>
                        <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Entitas Pemohon Pengadaan <span class="text-red-500">*</span></div>
                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Pilih salah satu: Unit Ruangan ATAU Gudang Logistik. Memilih salah satu akan menonaktifkan pilihan lainnya.</div>
                    </div>
                    @if ($unit_id || $gudang_id)
                        <button type="button" wire:click="resetPemohon" class="text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline flex items-center gap-1">
                            <flux:icon name="arrow-path" class="size-3" />
                            Reset Pilihan
                        </button>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Dropdown 1: Unit Pemohon --}}
                    <div>
                        <x-searchable-select
                            model="unit_id"
                            label="Unit / Ruangan Pemohon"
                            placeholder="-- Pilih Unit Ruangan --"
                            :options="$daftarUnit"
                            :disabled="!empty($gudang_id)"
                            live="true"
                        />
                        @if ($gudang_id)
                            <p class="text-[10px] text-zinc-400 mt-1 italic">Nonaktif karena Gudang Pemohon telah dipilih.</p>
                        @endif
                    </div>

                    {{-- Dropdown 2: Gudang Pemohon --}}
                    <div>
                        <x-searchable-select
                            model="gudang_id"
                            label="Gudang Pemohon"
                            placeholder="-- Pilih Gudang Logistik --"
                            :options="$daftarGudang"
                            :disabled="!empty($unit_id)"
                            live="true"
                        />
                        @if ($unit_id)
                            <p class="text-[10px] text-zinc-400 mt-1 italic">Nonaktif karena Unit Pemohon telah dipilih.</p>
                        @endif
                    </div>
                </div>
            </div>

            <flux:textarea wire:model="catatan" label="Catatan / Justifikasi" placeholder="Alasan atau keterangan tambahan..." rows="2" />

            {{-- Daftar Item Barang --}}
            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Daftar Barang Diminta</div>
                    <flux:button type="button" wire:click="addItem" size="xs" variant="ghost" icon="plus">Tambah Baris</flux:button>
                </div>

                @foreach ($items as $index => $row)
                    <div class="flex items-end gap-3" wire:key="item-{{ $index }}">
                        <div class="flex-1">
                            @if ($loop->first)
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Barang</label>
                            @endif
                            <x-searchable-select
                                model="items.{{ $index }}.barang_id"
                                placeholder="Pilih Barang..."
                                :options="$daftarBarang"
                                required
                            />
                            @error("items.{$index}.barang_id") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="w-28">
                            @if ($loop->first)
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Jumlah</label>
                            @endif
                            <input type="number" wire:model="items.{{ $index }}.jumlah" min="1" required class="w-full text-sm rounded-lg border {{ $errors->has("items.{$index}.jumlah") ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 text-right tabular-nums" />
                            @error("items.{$index}.jumlah") <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                        </div>
                        <div class="w-10 shrink-0">
                            @if (count($items) > 1)
                                <flux:button type="button" wire:click="removeItem({{ $index }})" size="xs" variant="ghost" icon="trash" title="Hapus Baris" class="text-red-500 hover:text-red-700" />
                            @endif
                        </div>
                    </div>
                @endforeach

                @error('items') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Ajukan Permintaan</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Detail PR --}}
    <flux:modal name="modal-pr-detail" wire:model="showDetailModal" class="md:w-[42rem]">
        @if ($selectedPr)
            <div class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Detail Permintaan Pengadaan</flux:heading>
                    <flux:subheading>{{ $selectedPr->kode }}</flux:subheading>
                </div>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Pemohon (Unit / Gudang)</div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5 mt-0.5">
                            @if ($selectedPr->unit_id)
                                <span class="text-[10px] px-1.5 py-0.2 rounded font-mono font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Unit</span>
                            @elseif ($selectedPr->gudang_id)
                                <span class="text-[10px] px-1.5 py-0.2 rounded font-mono font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">Gudang</span>
                            @endif
                            <span>{{ $selectedPr->nama_pemohon }}</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Pengaju</div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPr->pengaju?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Tanggal</div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPr->tanggal_permintaan?->format('d/m/Y') }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Urgensi</div>
                        <div><x-urgency-badge :urgency="$selectedPr->urgensi" /></div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Status</div>
                        <div><x-status-badge :status="$selectedPr->status" /></div>
                    </div>
                    @if ($selectedPr->penyetuju)
                        <div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Disetujui/Ditolak Oleh</div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPr->penyetuju->nama }}</div>
                        </div>
                    @endif
                </div>

                @if ($selectedPr->catatan)
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Catatan</div>
                        <div class="text-sm text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-800/50 p-2.5 rounded-lg">{{ $selectedPr->catatan }}</div>
                    </div>
                @endif

                {{-- Tabel Detail Item --}}
                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                            <tr>
                                <th class="text-left px-3 py-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400">#</th>
                                <th class="text-left px-3 py-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400">Barang</th>
                                <th class="text-right px-3 py-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400">Diminta</th>
                                <th class="text-right px-3 py-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400">Disetujui</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($selectedPr->detail as $detail)
                                <tr>
                                    <td class="px-3 py-2 text-zinc-500">{{ $loop->iteration }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->barang?->nama ?? '-' }}</div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 font-mono">{{ $detail->barang?->kode ?? '' }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ number_format($detail->jumlah, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right font-mono tabular-nums">
                                        @if ($detail->jumlah_disetujui !== null)
                                            {{ number_format($detail->jumlah_disetujui, 0, ',', '.') }}
                                        @else
                                            <span class="text-zinc-500 dark:text-zinc-400 italic text-xs font-sans">Belum diproses</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        @endif
    </flux:modal>

    {{-- Modal Tolak Pengadaan dengan Alasan Wajib --}}
    <flux:modal name="modal-reject-pengadaan" wire:model="showRejectPengadaanModal" class="md:w-[28rem]">
        <form wire:submit="submitRejectPengadaan" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Tolak di Meja Pengadaan</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Berikan alasan resmi mengapa permohonan ini tidak dapat diterbitkan menjadi PO (misal: pagu anggaran habis atau barang tidak tersedia).
                </flux:subheading>
            </div>

            <div>
                <flux:textarea
                    wire:model="alasanPenolakanPengadaan"
                    label="Alasan Penolakan (Wajib)"
                    placeholder="Tuliskan alasan penolakan secara jelas..."
                    rows="3"
                    required
                />
            </div>

            <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="submit" variant="danger" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submitRejectPengadaan">Tolak Permintaan</span>
                    <span wire:loading wire:target="submitRejectPengadaan">Memproses...</span>
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
