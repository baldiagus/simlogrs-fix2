    {{-- Modal Form Tambah / Ubah Barang --}}
    <flux:modal name="modal-barang-form" wire:model="showModal" class="md:w-[42rem]">
        <form wire:submit="save" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">{{ $editingId ? 'Ubah Data Komoditas' : 'Daftarkan Barang Baru' }}</flux:heading>
                <flux:subheading>Lengkapi informasi spesifikasi, klasifikasi, stok minimum, dan harga estimasi.</flux:subheading>
            </div>

            {{-- Tahap 1: Klasifikasi & Identitas Komoditas --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 1: Klasifikasi & Identitas Komoditas
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <flux:input wire:model="kode" label="Kode Barang (Otomatis)" disabled readonly class="font-mono bg-zinc-100 dark:bg-zinc-800/80 cursor-not-allowed font-semibold" />
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Tipe Komoditas</label>
                        <select wire:model="tipe" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                            <option value="HABIS_PAKAI">Habis Pakai (ATK/ART)</option>
                            <option value="ASET">Aktiva / Aset Inventaris</option>
                            <option value="SPAREPART">Suku Cadang IPSRS</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Status Barang</label>
                        <select wire:model="status" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                            <option value="AKTIF">AKTIF</option>
                            <option value="NONAKTIF">NONAKTIF</option>
                        </select>
                    </div>
                </div>

                <flux:input wire:model="nama" label="Nama Lengkap Komoditas" placeholder="misal: Kertas HVS A4 80gr Sinar Dunia" required />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.searchable-select
                        model="kategori_id"
                        label="Kategori Komoditas"
                        placeholder="Pilih Kategori..."
                        :options="$daftarKategori"
                        required
                    />

                    <x-ui.searchable-select
                        model="satuan_id"
                        label="Satuan Kemasan"
                        placeholder="Pilih Satuan..."
                        :options="$daftarSatuan"
                        required
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.searchable-select
                        model="merek_id"
                        label="Merek / Brand (Opsional)"
                        placeholder="Tanpa Merek / Generic"
                        :options="$daftarMerek"
                    />

                    <x-ui.searchable-select
                        model="pemasok_default_id"
                        label="Pemasok Rekanan Utama (Opsional)"
                        placeholder="Pilih Vendor Default..."
                        :options="$daftarPemasok"
                    />
                </div>
            </div>
            {{-- Tahap 2: Parameter Pengendalian Stok & Estimasi Harga --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 2: Parameter Pengendalian Stok & Estimasi Harga
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <x-ui.number-input model="stok_minimum" label="Stok Minimum" placeholder="Tanpa Batas" />
                    <x-ui.number-input model="titik_pesan" label="Titik Pesan (ROP)" placeholder="Tanpa Batas" />
                    <x-ui.number-input model="stok_maksimum" label="Stok Maksimum" placeholder="Tanpa Batas" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                    <x-ui.number-input model="harga_beli_terakhir" label="Harga Beli Terakhir" prefix="Rp" required />
                    <x-ui.number-input model="harga_estimasi" label="Harga Estimasi Pengadaan" prefix="Rp" required />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Simpan Komoditas</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
