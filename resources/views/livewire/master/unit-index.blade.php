<div class="space-y-6">
    <flux:card class="space-y-4 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Unit Kerja & Ruangan</flux:heading>
                <flux:subheading>Daftar instalasi pelayanan medis, penunjang, dan operasional rumah sakit.</flux:subheading>
            </div>

            @can('create_master::unit')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Tambah Unit
                </flux:button>
            @endcan
        </div>

        {{-- Toolbar Pencarian --}}
        <div>
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari unit, kode, atau penanggung jawab..."
                icon="magnifying-glass"
                clearable
            />
        </div>

        {{-- Tabel Bleed dalam Card --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-24">Kode</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'nama'" :direction="$sortDirection" wire:click="sort('nama')">Nama Ruangan / Instalasi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'jenis'" :direction="$sortDirection" wire:click="sort('jenis')">Jenis Pelayanan</flux:table.column>
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
                                {{ str_replace('_', ' ', $item->jenis) }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $item->penanggung_jawab ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $item->lokasi_gedung ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button wire:click="showDetail({{ $item->id }})" size="xs" variant="ghost" icon="eye" title="Lihat Detail" />
                                @can('update_master::unit')
                                    <flux:button wire:click="edit({{ $item->id }})" size="xs" variant="ghost" icon="pencil-square" title="Ubah Data" />
                                    <flux:button wire:click="confirmToggleStatus({{ $item->id }})" size="xs" variant="ghost" icon="arrow-path" title="Ubah Status" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada unit kerja yang terdaftar atau sesuai pencarian." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Footer Pagination Terpadu: Kiri (Showing), Tengah (Baris per halaman), Kanan (Tombol) --}}
        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Form Tambah / Ubah Unit --}}
    <flux:modal name="modal-unit-form" wire:model="showModal" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Data Unit' : 'Tambah Unit Kerja Baru' }}</flux:heading>
                <flux:subheading>Lengkapi data identitas ruangan atau instalasi rumah sakit.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model="kode" label="Kode Unit" placeholder="misal: IGD" required uppercase />
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Jenis Unit</label>
                    <select wire:model="jenis" class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="PELAYANAN_MEDIS">Pelayanan Medis</option>
                        <option value="PENUNJANG_MEDIS">Penunjang Medis</option>
                        <option value="ADMINISTRASI">Administrasi</option>
                        <option value="OPERASIONAL">Operasional</option>
                    </select>
                </div>
            </div>

            <flux:input wire:model="nama" label="Nama Ruangan / Unit" placeholder="misal: Instalasi Gawat Darurat" required />
            <flux:input wire:model="penanggung_jawab" label="Penanggung Jawab / Kepala Unit" placeholder="misal: dr. Pratama, Sp.EM" />
            <flux:input wire:model="lokasi_gedung" label="Lokasi Gedung / Lantai" placeholder="misal: Gedung A Lantai 1" />

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Status Unit</label>
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

    {{-- Modal Detail Unit --}}
    <flux:modal name="modal-unit-detail" wire:model="showDetailModal" class="md:w-[32rem]">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Detail Unit Kerja: {{ $selectedItem?->nama }}</flux:heading>
                <flux:subheading>Informasi lengkap unit kerja rumah sakit.</flux:subheading>
            </div>

            @if ($selectedItem)
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Kode</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->kode }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Nama Ruangan</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->nama }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Jenis Pelayanan</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ str_replace('_', ' ', $selectedItem->jenis) }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Penanggung Jawab</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->penanggung_jawab ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Lokasi Gedung</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->lokasi_gedung ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Status</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100"><x-status-badge :status="$selectedItem->status" /></div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Jumlah Aset di Unit</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->aset_count ?? 0 }}</div>
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
