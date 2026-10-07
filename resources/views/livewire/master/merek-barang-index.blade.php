<div class="space-y-6">
    {{-- Header Halaman & Tombol Tambah --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Merek Barang</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Daftar merek produsen / pabrikan komoditas logistik rumah sakit.</flux:subheading>
            </div>

            @can('create_master::merek::barang')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Tambah Merek
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-4 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Input Pencarian --}}
        <div>
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama merek atau kode..."
                icon="magnifying-glass"
                clearable
            />
        </div>

        {{-- Tabel Bleed dalam Card --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'nama'" :direction="$sortDirection" wire:click="sort('nama')">Nama Merek / Produsen</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell>
                            <button type="button" wire:click="showDetail({{ $item->id }})" class="text-left font-medium text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                {{ $item->nama }}
                            </button>
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-40">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                        Detail Merek
                                    </flux:menu.item>
                                    @can('update_master::merek::barang')
                                        <flux:menu.item wire:click="edit({{ $item->id }})" icon="pencil-square">
                                            Ubah Data
                                        </flux:menu.item>
                                        <flux:menu.item wire:click="confirmToggleStatus({{ $item->id }})" icon="arrow-path">
                                            {{ $item->status === 'AKTIF' ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </flux:menu.item>
                                    @endcan
                                    @can('delete_master::merek::barang')
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmDelete({{ $item->id }})" icon="trash" variant="danger">
                                            Hapus Data
                                        </flux:menu.item>
                                    @endcan
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada merek komoditas yang terdaftar." cols="4" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Footer Pagination Terpadu: Kiri (Showing), Tengah (Baris per halaman), Kanan (Tombol) --}}
        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Form Tambah / Ubah Merek --}}
    <flux:modal name="modal-merek-form" wire:model="showModal" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Merek' : 'Tambah Merek Baru' }}</flux:heading>
                <flux:subheading>Kelola data pabrikan atau produsen komoditas.</flux:subheading>
            </div>

            <flux:input wire:model="kode" label="Kode Merek (Otomatis)" disabled readonly class="font-mono bg-zinc-100 dark:bg-zinc-800/80 cursor-not-allowed font-semibold" />
            <flux:input wire:model="nama" label="Nama Merek / Brand" placeholder="misal: Sinar Dunia, Phillips, Canon" required />

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Status <span class="text-red-500">*</span></label>
                <select wire:model="status" required class="w-full text-sm rounded-lg border {{ $errors->has('status') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                    <option value="AKTIF">AKTIF</option>
                    <option value="NONAKTIF">NONAKTIF</option>
                </select>
                @error('status') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Simpan</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Detail Merek --}}
    <flux:modal name="modal-merek-detail" wire:model="showDetailModal" class="md:w-[28rem]">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Detail Merek</flux:heading>
                <flux:subheading>Informasi lengkap data merek barang.</flux:subheading>
            </div>

            @if ($selectedItem)
                <div class="grid grid-cols-1 gap-3 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 p-4">
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Kode</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $selectedItem->kode }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Nama Merek / Produsen</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->nama }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Status</dt>
                        <dd class="mt-1"><x-status-badge :status="$selectedItem->status" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Jumlah Barang yang Memiliki Merek Ini</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->barang_count }} item</dd>
                    </div>
                </div>
            @endif

        </div>
    </flux:modal>

    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
