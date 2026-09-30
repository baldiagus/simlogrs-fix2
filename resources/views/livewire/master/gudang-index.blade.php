<div class="space-y-6">
    <flux:card class="space-y-4 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Gudang Logistik</flux:heading>
                <flux:subheading>Fasilitas simpan persediaan sentral, transit, depo, dan logistik rumah sakit.</flux:subheading>
            </div>

            @can('create_master::gudang')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Tambah Gudang
                </flux:button>
            @endcan
        </div>

        {{-- Toolbar Pencarian --}}
        <div>
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari gudang, kode, atau penanggung jawab..."
                icon="magnifying-glass"
                clearable
            />
        </div>

        {{-- Tabel Bleed dalam Card --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-28">Kode</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'nama'" :direction="$sortDirection" wire:click="sort('nama')">Nama Fasilitas Gudang</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'jenis'" :direction="$sortDirection" wire:click="sort('jenis')">Jenis Gudang</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'penanggung_jawab'" :direction="$sortDirection" wire:click="sort('penanggung_jawab')">Penanggung Jawab</flux:table.column>
                <flux:table.column>Lokasi Gedung</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-28">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell class="font-medium">{{ $item->nama }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-medium">
                                {{ $item->jenis }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $item->kepalaGudang?->nama ?? $item->penanggung_jawab ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $item->lokasi_gedung ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button wire:click="showDetail({{ $item->id }})" size="xs" variant="ghost" icon="eye" title="Lihat Detail" />
                                @can('update_master::gudang')
                                    <flux:button wire:click="edit({{ $item->id }})" size="xs" variant="ghost" icon="pencil-square" title="Ubah Data" />
                                    <flux:button wire:click="confirmToggleStatus({{ $item->id }})" size="xs" variant="ghost" icon="arrow-path" title="Ubah Status" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada fasilitas gudang yang terdaftar atau sesuai pencarian." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Footer Pagination Terpadu: Kiri (Showing), Tengah (Baris per halaman), Kanan (Tombol) --}}
        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Form Tambah / Ubah Gudang --}}
    <flux:modal name="modal-gudang-form" wire:model="showModal" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Data Gudang' : 'Tambah Gudang Baru' }}</flux:heading>
                <flux:subheading>Lengkapi informasi fasilitas penyimpanan logistik rumah sakit.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model="kode" label="Kode Gudang" placeholder="misal: GUD_SENTRAL" required uppercase />
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Jenis Gudang</label>
                    <select wire:model="jenis" class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="SENTRAL">Sentral</option>
                        <option value="TRANSIT">Transit</option>
                        <option value="UNIT">Unit</option>
                        <option value="DEPO">Depo</option>
                    </select>
                </div>
            </div>

            <flux:input wire:model="nama" label="Nama Fasilitas Gudang" placeholder="misal: Gudang Sentral Logistik Non-Medis" required />
            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Penanggung Jawab / Kepala Gudang</label>
                <select wire:model="kepala_gudang_id" class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                    <option value="">— Belum Ditentukan —</option>
                    @foreach ($daftarPengguna as $pgn)
                        <option value="{{ $pgn->id }}">{{ $pgn->nama }} ({{ $pgn->nip ?? 'Tanpa NIP' }})</option>
                    @endforeach
                </select>
            </div>
            <flux:input wire:model="lokasi_gedung" label="Lokasi Gedung / Lantai" placeholder="misal: Gedung Logistik Lantai 1" />

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Status Gudang</label>
                <select wire:model="status" class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                    <option value="AKTIF">AKTIF</option>
                    <option value="NONAKTIF">NONAKTIF</option>
                </select>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Simpan</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Detail Gudang --}}
    <flux:modal name="modal-gudang-detail" wire:model="showDetailModal" class="md:w-[32rem]">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Detail Gudang Logistik: {{ $selectedItem->nama ?? '' }}</flux:heading>
                <flux:subheading>Informasi lengkap fasilitas penyimpanan logistik.</flux:subheading>
            </div>

            @if ($selectedItem)
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 p-4 text-xs">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Kode</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $selectedItem->kode }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Nama Fasilitas Gudang</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->nama }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Jenis Gudang</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->jenis }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Penanggung Jawab / Kepala Gudang</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->kepalaGudang?->nama ?? $selectedItem->penanggung_jawab ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Lokasi Gedung</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->lokasi_gedung ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Status</dt>
                        <dd class="mt-1"><x-status-badge :status="$selectedItem->status" /></dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Jumlah Rak/Bin Aktif</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->lokasi_gudang_count }} lokasi</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Jumlah Batch Stok Tersimpan</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->stok_batch_count }} batch</dd>
                    </div>
                </div>
            @endif

            <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</flux:button>
            </div>
        </div>
    </flux:modal>

    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
