    {{-- Modal Perpanjangan Waktu Peminjaman --}}
    <flux:modal name="modal-perpanjang" wire:model="showPerpanjangModal" class="md:w-[54rem] max-w-[95vw]">
        <div class="space-y-6">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Perpanjangan Waktu Peminjaman</flux:heading>
                <flux:subheading>Perpanjang batas waktu pengembalian aset inventaris dengan mencantumkan alasan operasional.</flux:subheading>
            </div>
            <form wire:submit="submitPerpanjang" class="space-y-5">
                {{-- Tahap 1: Tanggal Rencana Pengembalian Baru --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 1: Penetapan Tanggal Pengembalian Baru
                    </div>
                    <flux:field>
                        <flux:label>Tanggal Rencana Pengembalian Baru <span class="text-red-500">*</span></flux:label>
                        <flux:input type="date" wire:model="tanggal_kembali_baru" required />
                        <flux:error name="tanggal_kembali_baru" />
                    </flux:field>
                </div>

                {{-- Tahap 2: Alasan / Justifikasi Perpanjangan --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 2: Alasan & Justifikasi Kebutuhan Pelayanan
                    </div>
                    <flux:field>
                        <flux:label>Alasan Perpanjangan <span class="text-red-500">*</span></flux:label>
                        <flux:textarea
                            wire:model="alasan_perpanjang"
                            placeholder="Jelaskan kebutuhan perpanjangan waktu penggunaan aset untuk pelayanan pasien/ruangan..."
                            rows="3"
                            required
                        />
                        <flux:error name="alasan_perpanjang" />
                    </flux:field>
                </div>

                {{-- Footer Buttons --}}
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showPerpanjangModal', false)">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitPerpanjang">Simpan Perpanjangan</span>
                        <span wire:loading wire:target="submitPerpanjang">Menyimpan...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
