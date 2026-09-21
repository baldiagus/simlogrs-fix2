<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">Kategori Komoditas</flux:heading>
            <flux:subheading>Klasifikasi jenis barang rumah sakit (ATK, ART, Cetakan, Sparepart, Elektronik).</flux:subheading>
        </div>

        @can('create_master::kategori::barang')
            <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                Tambah Kategori
            </flux:button>
        @endcan
    </div>

    {{-- Filter Toolbar --}}
    <flux:card class="p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <div class="flex flex-col sm:flex-row gap-3 items-center justify-between">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kategori atau kode..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="flex items-center gap-2 text-xs text-zinc-500">
                <span>Tampilkan:</span>
                <select wire:model.live="perPage" class="border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 rounded-md px-2 py-1 text-xs">
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                </select>
            </div>
        </div>
    </flux:card>

    {{-- Tabel Data --}}
    <flux:card class="p-0 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
        <flux:table :paginate="$items">
            <flux:table.columns sticky>
                <flux:table.column class="w-28">Kode</flux:table.column>
                <flux:table.column>Nama Kategori</flux:table.column>
                <flux:table.column>Deskripsi</flux:table.column>
                <flux:table.column class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-28">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell class="font-medium">{{ $item->nama }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $item->deskripsi ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                @can('update_master::kategori::barang')
                                    <flux:button wire:click="edit({{ $item->id }})" size="xs" variant="ghost" icon="pencil-square" title="Ubah Data" />
                                    <flux:button wire:click="toggleStatus({{ $item->id }})" size="xs" variant="ghost" icon="arrow-path" title="Ubah Status" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada kategori komoditas yang terdaftar." cols="5" />
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Modal Form Tambah / Ubah Kategori --}}
    <flux:modal name="modal-kategori-form" wire:model="showModal" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Kategori' : 'Tambah Kategori Baru' }}</flux:heading>
                <flux:subheading>Klasifikasikan kelompok komoditas logistik.</flux:subheading>
            </div>

            <flux:input wire:model="kode" label="Kode Kategori" placeholder="misal: ATK" required uppercase />
            <flux:input wire:model="nama" label="Nama Kategori" placeholder="misal: Alat Tulis Kantor" required />
            <flux:textarea wire:model="deskripsi" label="Deskripsi / Cakupan" placeholder="Keterangan jenis barang..." rows="2" />

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Status</label>
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
</div>
