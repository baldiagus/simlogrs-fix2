    {{-- Modal Catat Pemakaian Barang --}}
    <flux:modal name="modal-catat-pemakaian" wire:model="showPakaiModal" class="md:w-[32rem]">
        @if ($selectedStok)
            <form wire:submit="simpanPemakaian" class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Catat Pemakaian Barang</flux:heading>
                    <flux:subheading>Kurangi sisa fisik stok di lemari ruangan saat digunakan untuk operasional atau pelayanan pasien.</flux:subheading>
                </div>

                {{-- Tahap 1: Verifikasi Komoditas & Saldo Lemari Ruangan --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-2.5">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 1: Verifikasi Komoditas & Saldo Lemari Ruangan
                    </div>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Nama Barang:</span>
                            <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $selectedStok->barang?->nama }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Unit / Ruangan:</span>
                            <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $selectedStok->unit?->nama }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Sisa Fisik Saat Ini:</span>
                            <span class="font-bold text-purple-600 dark:text-purple-400 text-sm">
                                {{ $selectedStok->jumlah }} {{ $selectedStok->barang?->satuan?->simbol ?? 'Pcs' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Tahap 2: Kuantitas Penggunaan & Keterangan Pelayanan --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 2: Kuantitas Penggunaan & Keperluan Pelayanan
                    </div>
                    <div>
                        <flux:input
                            wire:model="jumlah_pakai"
                            type="number"
                            min="1"
                            max="{{ $selectedStok->jumlah }}"
                            label="Kuantitas yang Digunakan / Habis"
                            placeholder="Masukkan jumlah yang dipakai"
                            required
                        />
                        <div class="text-[11px] text-zinc-500 mt-1">
                            Maksimal pemakaian: {{ $selectedStok->jumlah }} {{ $selectedStok->barang?->satuan?->simbol ?? 'Pcs' }}
                        </div>
                    </div>
                    <div>
                        <flux:textarea
                            wire:model="keterangan"
                            label="Keterangan / Keperluan Pemakaian"
                            placeholder="Contoh: Operasional pelayanan IGD shift pagi / pembersihan ruang rawat..."
                            rows="2"
                            required
                        />
                    </div>
                </div>

                {{-- Tombol Aksi Modal --}}
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showPakaiModal', false)">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="simpanPemakaian">Simpan Pemakaian</span>
                        <span wire:loading wire:target="simpanPemakaian">Memproses...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
