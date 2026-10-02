<div class="space-y-6">
    <flux:card class="space-y-4 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Satuan Barang</flux:heading>
                <flux:subheading>Daftar satuan unit kemasan resmi (PCS, BOX, RIM, PACK, UNIT, ROLL).</flux:subheading>
            </div>

            @can('create_master::satuan::barang')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Tambah Satuan
                </flux:button>
            @endcan
        </div>

        {{-- Toolbar Pencarian --}}
        <div>
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari satuan atau kode..."
                icon="magnifying-glass"
                clearable
            />
        </div>

        {{-- Tabel Bleed dalam Card --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-28">Kode</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'nama'" :direction="$sortDirection" wire:click="sort('nama')">Nama Satuan</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'simbol'" :direction="$sortDirection" wire:click="sort('simbol')">Simbol</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-28">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell class="font-medium">{{ $item->nama }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-zinc-500">{{ $item->simbol ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button wire:click="showDetail({{ $item->id }})" size="xs" variant="ghost" icon="eye" title="Lihat Detail" />
                                @can('update_master::satuan::barang')
                                    <flux:button wire:click="edit({{ $item->id }})" size="xs" variant="ghost" icon="pencil-square" title="Ubah Data" />
                                    <flux:button wire:click="confirmToggleStatus({{ $item->id }})" size="xs" variant="ghost" icon="arrow-path" title="Ubah Status" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada satuan barang yang terdaftar." cols="5" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Footer Pagination Terpadu: Kiri (Showing), Tengah (Baris per halaman), Kanan (Tombol) --}}
        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Form Tambah / Ubah Satuan --}}
    <flux:modal name="modal-satuan-form" wire:model="showModal" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Satuan' : 'Tambah Satuan Baru' }}</flux:heading>
                <flux:subheading>Standarisasi satuan kemasan logistik.</flux:subheading>
            </div>

            <flux:input wire:model="kode" label="Kode Satuan" placeholder="misal: PCS" required uppercase />
            <flux:input wire:model="nama" label="Nama Satuan" placeholder="misal: Pieces / Buah" required />
            <flux:input wire:model="simbol" label="Simbol / Singkatan" placeholder="misal: pcs" />

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Status <span class="text-red-500">*</span></label>
                <select wire:model="status" required class="w-full text-sm rounded-lg border {{ $errors->has('status') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                    <option value="AKTIF">AKTIF</option>
                    <option value="NONAKTIF">NONAKTIF</option>
                </select>
                @error('status') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
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

    {{-- Modal Detail Satuan --}}
    <flux:modal name="modal-satuan-detail" wire:model="showDetailModal" class="md:w-[28rem]">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Detail Satuan Barang</flux:heading>
                <flux:subheading>Informasi lengkap satuan yang dipilih.</flux:subheading>
            </div>

            @if ($selectedItem)
                <div class="grid grid-cols-2 gap-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 p-4 text-sm">
                    <div class="text-zinc-500 dark:text-zinc-400">Kode</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->kode }}</div>

                    <div class="text-zinc-500 dark:text-zinc-400">Nama Satuan</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->nama }}</div>

                    <div class="text-zinc-500 dark:text-zinc-400">Simbol</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->simbol ?? '-' }}</div>

                    <div class="text-zinc-500 dark:text-zinc-400">Status</div>
                    <div><x-status-badge :status="$selectedItem->status" /></div>

                    <div class="text-zinc-500 dark:text-zinc-400">Jumlah Barang yang Menggunakan Satuan Ini</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($selectedItem->barang_count) }}</div>
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
