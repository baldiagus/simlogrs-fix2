<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman & Tombol Tambah --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Permintaan Barang Ruangan (Amprah)</flux:heading>
                <flux:subheading>Kelola pengajuan permintaan barang dan komoditas dari ruangan ke gudang logistik.</flux:subheading>
            </div>

            @can('create_layanan_unit::permintaan::barang')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    + Ajukan Amprah
                </flux:button>
            @endcan
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode amprah atau nama unit..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-56">
                <select wire:model.live="filterStatus" class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status</option>
                    <option value="DIAJUKAN">DIAJUKAN</option>
                    <option value="DISETUJUI_KARU">DISETUJUI KARU</option>
                    <option value="DISETUJUI_GUDANG">DISETUJUI GUDANG</option>
                    <option value="SEDANG_DIKIRIM">SEDANG DIKIRIM</option>
                    <option value="SELESAI">SELESAI</option>
                    <option value="DITOLAK">DITOLAK</option>
                </select>
            </div>

            <div class="w-full sm:w-44">
                <select wire:model.live="filterUrgensi" class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Urgensi</option>
                    <option value="REGULER">REGULER</option>
                    <option value="CITO">CITO (Darurat)</option>
                </select>
            </div>
        </div>

        {{-- Tabel Permintaan Amprah --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">Kode Amprah</flux:table.column>
                <flux:table.column>Unit Pemohon</flux:table.column>
                <flux:table.column>Gudang Tujuan</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_permintaan'" :direction="$sortDirection" wire:click="sort('tanggal_permintaan')">Tanggal</flux:table.column>
                <flux:table.column>Urgensi</flux:table.column>
                <flux:table.column class="text-center">Jml Item</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-40">Status</flux:table.column>
                <flux:table.column class="text-right w-28">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarPermintaan as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $item->kode }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->unit?->nama ?? '-' }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="text-xs text-zinc-700 dark:text-zinc-300">{{ $item->gudang?->nama ?? '-' }}</div>
                        </flux:table.cell>

                        <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                            {{ $item->tanggal_permintaan?->format('d/m/Y') }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <x-urgency-badge :urgency="$item->urgensi" />
                        </flux:table.cell>

                        <flux:table.cell class="text-center font-mono text-xs">
                            {{ $item->detail_count ?? $item->detail->count() }}
                        </flux:table.cell>

                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button wire:click="showDetail({{ $item->id }})" size="xs" variant="ghost" icon="eye" title="Lihat Detail" />

                                @if ($item->status === 'DIAJUKAN' && ($isGlobalAdmin || (int) $userUnitId === (int) $item->unit_id))
                                    <flux:button
                                        wire:click="confirmBatal({{ $item->id }})"
                                        size="xs"
                                        variant="ghost"
                                        icon="x-mark"
                                        title="Batalkan Permintaan"
                                        class="text-rose-600 hover:text-rose-700"
                                        wire:loading.attr="disabled"
                                    />
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada data permintaan amprah ruangan." cols="8" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Navigasi Paginasi --}}
        <x-table-pagination :paginator="$daftarPermintaan" />
    </flux:card>

    {{-- Modal Form Tambah Amprah --}}
    <flux:modal name="modal-permintaan-form" wire:model="showModal" class="md:w-[50rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">Formulir Permintaan Barang (Amprah)</flux:heading>
                <flux:subheading>Isi formulir amprah untuk mengajukan kebutuhan barang ruangan ke gudang logistik.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <flux:input wire:model="kode" label="Kode Amprah (Otomatis)" disabled readonly class="font-mono bg-zinc-100 dark:bg-zinc-800/80 cursor-not-allowed font-semibold" />

                @if ($isGlobalAdmin || ! auth()->user()?->unit_id)
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Unit Pemohon <span class="text-red-500">*</span></label>
                        <select wire:model="unit_id" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                            <option value="">Pilih Unit...</option>
                            @foreach ($daftarUnit as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->nama }}</option>
                            @endforeach
                        </select>
                        @error('unit_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Unit Pemohon</label>
                        <flux:input value="{{ auth()->user()->unit?->nama ?? '-' }}" disabled readonly class="bg-zinc-100 dark:bg-zinc-800/80 cursor-not-allowed font-medium text-zinc-700 dark:text-zinc-300" />
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Gudang Tujuan <span class="text-red-500">*</span></label>
                    <select wire:model="gudang_id" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="">Pilih Gudang...</option>
                        @foreach ($daftarGudang as $gdg)
                            <option value="{{ $gdg->id }}">{{ $gdg->nama }}</option>
                        @endforeach
                    </select>
                    @error('gudang_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Tanggal Permintaan <span class="text-red-500">*</span></label>
                    <input type="date" wire:model="tanggal_permintaan" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100" />
                    @error('tanggal_permintaan') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Urgensi <span class="text-red-500">*</span></label>
                    <select wire:model="urgensi" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="REGULER">REGULER</option>
                        <option value="CITO">CITO (Darurat)</option>
                    </select>
                    @error('urgensi') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <flux:textarea wire:model="catatan" label="Catatan / Justifikasi" placeholder="Alasan atau keterangan kebutuhan barang..." rows="2" />

            {{-- Daftar Baris Item --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">
                        Daftar Barang Diminta <span class="text-red-500">*</span>
                    </div>
                    <flux:button type="button" wire:click="addItem" size="xs" variant="ghost" icon="plus">
                        Tambah Baris
                    </flux:button>
                </div>

                @foreach ($items as $index => $row)
                    <div class="flex flex-col sm:flex-row items-start sm:items-end gap-3 p-3 sm:p-0 rounded-lg sm:rounded-none bg-white sm:bg-transparent dark:bg-zinc-800/60 sm:dark:bg-transparent border sm:border-0 border-zinc-200 dark:border-zinc-700" wire:key="item-{{ $index }}">
                        <div class="flex-1 w-full sm:w-auto">
                            @if ($loop->first)
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Barang</label>
                            @endif
                            <select wire:model="items.{{ $index }}.barang_id" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                                <option value="">Pilih Barang...</option>
                                @foreach ($daftarBarang as $brg)
                                    <option value="{{ $brg->id }}">{{ $brg->kode }} — {{ $brg->nama }} ({{ $brg->satuan?->simbol ?? $brg->satuan?->nama ?? 'Unit' }})</option>
                                @endforeach
                            </select>
                            @error("items.{$index}.barang_id") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="w-full sm:w-28">
                            @if ($loop->first)
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Jumlah</label>
                            @endif
                            <input type="number" wire:model="items.{{ $index }}.jumlah" min="1" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 text-right tabular-nums" />
                            @error("items.{$index}.jumlah") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="w-full sm:w-48">
                            @if ($loop->first)
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Catatan Item</label>
                            @endif
                            <input type="text" wire:model="items.{{ $index }}.catatan" placeholder="Keterangan opsional..." class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100" />
                            @error("items.{$index}.catatan") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="w-full sm:w-10 sm:shrink-0 flex justify-end">
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
                    <span wire:loading.remove wire:target="save">Ajukan Amprah</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Detail Permintaan Distribusi --}}
    <flux:modal name="modal-permintaan-detail" wire:model="showDetailModal" class="md:w-[48rem]">
        @if ($selectedPermintaan)
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail Permintaan Amprah</flux:heading>
                            <span class="font-mono text-sm font-bold text-zinc-500 dark:text-zinc-400">#{{ $selectedPermintaan->kode }}</span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Tanggal: {{ $selectedPermintaan->tanggal_permintaan?->format('d F Y') }}
                        </flux:subheading>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-urgency-badge :urgency="$selectedPermintaan->urgensi" />
                        <x-status-badge :status="$selectedPermintaan->status" />
                    </div>
                </div>

                {{-- Informasi Metadata --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Pemohon</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPermintaan->unit?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Gudang Tujuan</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPermintaan->gudang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Diajukan Oleh</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPermintaan->pengaju?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Disetujui Oleh</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPermintaan->penyetuju?->nama ?? 'Belum diproses' }}</div>
                    </div>
                </div>

                @if ($selectedPermintaan->catatan)
                    <div>
                        <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">Catatan Permintaan</div>
                        <div class="text-sm text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-lg">
                            {{ $selectedPermintaan->catatan }}
                        </div>
                    </div>
                @endif

                {{-- Tabel Detail Barang Diminta vs Disetujui --}}
                <div>
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Daftar Komoditas / Barang</div>
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                                <tr>
                                    <th class="text-left px-3 py-2 w-10">#</th>
                                    <th class="text-left px-3 py-2">Barang</th>
                                    <th class="text-left px-3 py-2 w-24">Satuan</th>
                                    <th class="text-left px-3 py-2">Catatan</th>
                                    <th class="text-right px-3 py-2 w-28">Diminta</th>
                                    <th class="text-right px-3 py-2 w-28">Disetujui</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @forelse ($selectedPermintaan->detail as $detail)
                                    <tr>
                                        <td class="px-3 py-2 text-zinc-500 text-xs">{{ $loop->iteration }}</td>
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->barang?->nama ?? '-' }}</div>
                                            <div class="text-[11px] text-zinc-400 font-mono">{{ $detail->barang?->kode ?? '' }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{ $detail->barang?->satuan?->simbol ?? $detail->barang?->satuan?->nama ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{ $detail->catatan ?: '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono tabular-nums text-zinc-900 dark:text-zinc-100 font-medium">
                                            {{ number_format($detail->jumlah, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono tabular-nums">
                                            @if ($detail->jumlah_disetujui !== null)
                                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                                    {{ number_format($detail->jumlah_disetujui, 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-zinc-400 italic text-xs font-sans">Belum diproses</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-3 py-4 text-center text-zinc-400 text-xs italic">
                                            Tidak ada item barang dalam permintaan ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</flux:button>
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
