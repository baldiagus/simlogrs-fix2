<div class="space-y-6">
    <flux:card class="space-y-4 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Tata Letak Rak & Bin</flux:heading>
                <flux:subheading>Pemetaan fisik lokasi simpan komoditas per gudang (baris, kolom, dan level rak).</flux:subheading>
            </div>

            @can('create_master::lokasi::gudang')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Tambah Rak / Bin
                </flux:button>
            @endcan
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari nama rak atau kode bin..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-64">
                <select wire:model.live="filterGudangId" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Fasilitas Gudang</option>
                    @foreach ($daftarGudang as $gudang)
                        <option value="{{ $gudang->id }}">{{ $gudang->nama }} ({{ $gudang->kode }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Tabel Bleed dalam Card --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode Bin</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'nama'" :direction="$sortDirection" wire:click="sort('nama')">Nama Rak Simpan</flux:table.column>
                <flux:table.column>Gudang</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'baris'" :direction="$sortDirection" wire:click="sort('baris')" class="text-center w-24">Baris</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'kolom'" :direction="$sortDirection" wire:click="sort('kolom')" class="text-center w-24">Kolom</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'level'" :direction="$sortDirection" wire:click="sort('level')" class="text-center w-24">Level</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-28">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell class="font-medium">{{ $item->nama }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">
                                {{ $item->gudang?->nama ?? '-' }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell class="text-center font-mono text-zinc-500">{{ $item->baris ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-center font-mono text-zinc-500">{{ $item->kolom ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-center font-mono text-zinc-500">{{ $item->level ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button wire:click="showDetail({{ $item->id }})" size="xs" variant="ghost" icon="eye" title="Lihat Detail" />
                                @can('update_master::lokasi::gudang')
                                    <flux:button wire:click="edit({{ $item->id }})" size="xs" variant="ghost" icon="pencil-square" title="Ubah Data" />
                                    <flux:button wire:click="confirmToggleStatus({{ $item->id }})" size="xs" variant="ghost" icon="arrow-path" title="Ubah Status" />
                                @endcan
                                @can('delete_master::lokasi::gudang')
                                    <flux:button wire:click="confirmDelete({{ $item->id }})" size="xs" variant="ghost" icon="trash" class="text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40" title="Hapus Data" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada lokasi rak/bin yang terdaftar atau sesuai pencarian." cols="8" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Footer Pagination Terpadu: Kiri (Showing), Tengah (Baris per halaman), Kanan (Tombol) --}}
        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Form Tambah / Ubah Rak --}}
    <flux:modal name="modal-lokasi-form" wire:model="showModal" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Lokasi Rak / Bin' : 'Tambah Lokasi Rak Baru' }}</flux:heading>
                <flux:subheading>Tentukan fasilitas gudang dan koordinat fisik rak penyimpanan.</flux:subheading>
            </div>

            <div>
                <x-searchable-select
                    model="gudang_id"
                    label="Gudang Logistik"
                    placeholder="-- Ketik untuk mencari nama atau kode gudang... --"
                    :options="$daftarGudang"
                    :required="true"
                    :nullable="false"
                />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model="kode" label="Kode Bin / Rak" placeholder="misal: RAK-ATK-01" required uppercase />
                <flux:input wire:model="nama" label="Nama / Label Rak" placeholder="misal: Rak ATK Utama" required />
            </div>

            <div class="grid grid-cols-3 gap-3">
                <flux:input wire:model="baris" label="Baris (Row)" placeholder="misal: A" />
                <flux:input wire:model="kolom" label="Kolom (Col)" placeholder="misal: 01" />
                <flux:input wire:model="level" label="Tingkat (Level)" placeholder="misal: 03" />
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Status Rak <span class="text-red-500">*</span></label>
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

    {{-- Modal Detail Lokasi Gudang --}}
    <flux:modal name="modal-lokasi-detail" wire:model="showDetailModal" class="md:w-[28rem]">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Detail Lokasi Rak</flux:heading>
                <flux:subheading>Informasi lengkap lokasi penyimpanan.</flux:subheading>
            </div>

            @if ($selectedItem)
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 p-4">
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Kode Bin</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->kode }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Nama Rak Simpan</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->nama }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Gudang Induk</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->gudang?->nama ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Baris</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->baris ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Kolom</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->kolom ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Level</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->level ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">Status</dt>
                        <dd><x-status-badge :status="$selectedItem->status" /></dd>
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
