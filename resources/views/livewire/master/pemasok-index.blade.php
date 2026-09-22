<div class="space-y-6">
    <flux:card class="space-y-4 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Pemasok & Rekanan Vendor</flux:heading>
                <flux:subheading>Daftar mitra penyedia komoditas logistik dan suku cadang rumah sakit.</flux:subheading>
            </div>

            @can('create_master::pemasok')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Tambah Pemasok
                </flux:button>
            @endcan
        </div>

        {{-- Toolbar Pencarian --}}
        <div>
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari nama vendor, PIC, atau kota..."
                icon="magnifying-glass"
                clearable
            />
        </div>

        {{-- Tabel Bleed dalam Card --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-28">Kode</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'nama'" :direction="$sortDirection" wire:click="sort('nama')">Nama Perusahaan Vendor</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'nama_kontak'" :direction="$sortDirection" wire:click="sort('nama_kontak')">PIC / Kontak</flux:table.column>
                <flux:table.column>Telepon & Email</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'kota'" :direction="$sortDirection" wire:click="sort('kota')">Kota</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-28">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->nama }}</div>
                            @if ($item->npwp)
                                <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">NPWP: {{ $item->npwp }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-700 dark:text-zinc-300">{{ $item->nama_kontak ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-mono text-xs">{{ $item->no_telepon ?? '-' }}</div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $item->email ?? '-' }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $item->kota ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button wire:click="showDetail({{ $item->id }})" size="xs" variant="ghost" icon="eye" title="Lihat Detail" />
                                @can('update_master::pemasok')
                                    <flux:button wire:click="edit({{ $item->id }})" size="xs" variant="ghost" icon="pencil-square" title="Ubah Data" />
                                    <flux:button wire:click="confirmToggleStatus({{ $item->id }})" size="xs" variant="ghost" icon="arrow-path" title="Ubah Status" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada data rekanan vendor yang terdaftar." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Footer Pagination Terpadu: Kiri (Showing), Tengah (Baris per halaman), Kanan (Tombol) --}}
        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Form Tambah / Ubah Pemasok --}}
    <flux:modal name="modal-pemasok-form" wire:model="showModal" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Data Pemasok' : 'Tambah Pemasok Baru' }}</flux:heading>
                <flux:subheading>Informasi legalitas dan kontak resmi perusahaan rekanan.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model="kode" label="Kode Vendor (Otomatis)" disabled readonly class="font-mono bg-zinc-100 dark:bg-zinc-800/80 cursor-not-allowed font-semibold" />
                <flux:input wire:model="npwp" label="NPWP Perusahaan" placeholder="00.000.000.0-000.000" />
            </div>

            <flux:input wire:model="nama" label="Nama Perusahaan" placeholder="misal: PT. Logistik Medika Abadi" required />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model="nama_kontak" label="Nama PIC / Kontak" placeholder="misal: Bpk. Gunawan" />
                <flux:input wire:model="no_telepon" label="Nomor Telepon / WA" placeholder="08123456789" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model="email" label="Email Resmi" type="email" placeholder="kontak@vendor.com" />
                <flux:input wire:model="kota" label="Kota Domisili" placeholder="misal: Jakarta Selatan" />
            </div>

            <flux:textarea wire:model="alamat" label="Alamat Kantor" placeholder="Alamat lengkap perusahaan..." rows="2" />

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Status Pemasok</label>
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

    {{-- Modal Detail Pemasok --}}
    <flux:modal name="modal-pemasok-detail" wire:model="showDetailModal" class="md:w-[32rem]">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Detail Pemasok: {{ $selectedItem?->nama }}</flux:heading>
                <flux:subheading>Informasi lengkap perusahaan rekanan vendor.</flux:subheading>
            </div>

            @if ($selectedItem)
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 p-4 text-xs">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Kode</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $selectedItem->kode }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Nama Perusahaan Vendor</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->nama }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">NPWP</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $selectedItem->npwp ?? '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">Alamat</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->alamat ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Kota</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->kota ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">PIC/Nama Kontak</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->nama_kontak ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">No. Telepon</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $selectedItem->no_telepon ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Email</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->email ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Status</dt>
                        <dd class="mt-1"><x-status-badge :status="$selectedItem->status" /></dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Jumlah PO Pernah Diterbitkan</dt>
                        <dd class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedItem->pesanan_pembelian_count }} PO</dd>
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
