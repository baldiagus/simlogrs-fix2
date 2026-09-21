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
                <select wire:model.live="filterStatus" class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status</option>
                    <option value="DIAJUKAN">DIAJUKAN</option>
                    <option value="DISETUJUI">DISETUJUI</option>
                    <option value="DITERUSKAN_KE_PENGADAAN">DITERUSKAN KE PENGADAAN</option>
                    <option value="DITOLAK">DITOLAK</option>
                </select>
            </div>

            <div class="w-full sm:w-40">
                <select wire:model.live="filterUrgensi" class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Urgensi</option>
                    <option value="REGULER">REGULER</option>
                    <option value="MENDESAK">MENDESAK</option>
                    <option value="CITO">CITO</option>
                </select>
            </div>
        </div>

        {{-- Tabel --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">Kode PR</flux:table.column>
                <flux:table.column>Unit Pemohon</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_permintaan'" :direction="$sortDirection" wire:click="sort('tanggal_permintaan')">Tanggal</flux:table.column>
                <flux:table.column>Urgensi</flux:table.column>
                <flux:table.column class="text-center">Jumlah Item</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-40">Status</flux:table.column>
                <flux:table.column class="text-right w-36">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarPr as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->unit?->nama ?? '-' }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs tabular-nums">{{ $item->tanggal_permintaan?->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <x-urgency-badge :urgency="$item->urgensi" />
                        </flux:table.cell>
                        <flux:table.cell class="text-center font-mono text-xs">{{ $item->detail->count() }}</flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button wire:click="showDetail({{ $item->id }})" size="xs" variant="ghost" icon="eye" title="Lihat Detail" />

                                @can('update_pengadaan::permintaan::pengadaan')
                                    @if ($item->status === 'DIAJUKAN')
                                        <flux:button wire:click="approve({{ $item->id }})" size="xs" variant="ghost" icon="check-circle" title="Setujui" wire:loading.attr="disabled" />
                                        <flux:button wire:click="reject({{ $item->id }})" size="xs" variant="ghost" icon="x-circle" title="Tolak" wire:loading.attr="disabled" />
                                    @endif
                                    @if ($item->status === 'DISETUJUI')
                                        <flux:button wire:click="teruskan({{ $item->id }})" size="xs" variant="ghost" icon="arrow-right-circle" title="Teruskan ke Pengadaan" wire:loading.attr="disabled" />
                                    @endif
                                @endcan
                            </div>
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
            <div>
                <flux:heading size="lg">Buat Permintaan Pengadaan Baru</flux:heading>
                <flux:subheading>Lengkapi informasi permintaan dan daftar barang yang dibutuhkan.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <flux:input wire:model="kode" label="Kode PR (Otomatis)" disabled readonly class="font-mono bg-zinc-100 dark:bg-zinc-800/80 cursor-not-allowed font-semibold" />

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Unit Pemohon</label>
                    <select wire:model="unit_id" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="">Pilih Unit...</option>
                        @foreach ($daftarUnit as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->nama }}</option>
                        @endforeach
                    </select>
                    @error('unit_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Urgensi</label>
                    <select wire:model="urgensi" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="REGULER">REGULER</option>
                        <option value="MENDESAK">MENDESAK</option>
                        <option value="CITO">CITO</option>
                    </select>
                </div>
            </div>

            <flux:textarea wire:model="catatan" label="Catatan / Justifikasi" placeholder="Alasan atau keterangan tambahan..." rows="2" />

            {{-- Daftar Item Barang --}}
            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">Daftar Barang Diminta</div>
                    <flux:button type="button" wire:click="addItem" size="xs" variant="ghost" icon="plus">Tambah Baris</flux:button>
                </div>

                @foreach ($items as $index => $row)
                    <div class="flex items-end gap-3" wire:key="item-{{ $index }}">
                        <div class="flex-1">
                            @if ($loop->first)
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Barang</label>
                            @endif
                            <select wire:model="items.{{ $index }}.barang_id" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                                <option value="">Pilih Barang...</option>
                                @foreach ($daftarBarang as $brg)
                                    <option value="{{ $brg->id }}">{{ $brg->kode }} — {{ $brg->nama }}</option>
                                @endforeach
                            </select>
                            @error("items.{$index}.barang_id") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="w-28">
                            @if ($loop->first)
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Jumlah</label>
                            @endif
                            <input type="number" wire:model="items.{{ $index }}.jumlah" min="1" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 text-right tabular-nums" />
                            @error("items.{$index}.jumlah") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
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

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
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
                <div>
                    <flux:heading size="lg">Detail Permintaan Pengadaan</flux:heading>
                    <flux:subheading>{{ $selectedPr->kode }}</flux:subheading>
                </div>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Unit Pemohon</div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPr->unit?->nama ?? '-' }}</div>
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
                                        <div class="text-[11px] text-zinc-400 font-mono">{{ $detail->barang?->kode ?? '' }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ number_format($detail->jumlah, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right font-mono tabular-nums">
                                        @if ($detail->jumlah_disetujui !== null)
                                            {{ number_format($detail->jumlah_disetujui, 0, ',', '.') }}
                                        @else
                                            <span class="text-zinc-300 dark:text-zinc-600 italic font-sans">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
