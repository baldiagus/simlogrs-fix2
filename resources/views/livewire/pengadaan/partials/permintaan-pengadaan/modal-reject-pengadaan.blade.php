    {{-- Modal Tolak Pengadaan dengan Alasan Wajib --}}
    <flux:modal name="modal-reject-pengadaan" wire:model="showRejectPengadaanModal" class="md:w-[28rem]">
        <form wire:submit="submitRejectPengadaan" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Tolak di Meja Pengadaan</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Berikan alasan resmi mengapa permohonan ini tidak dapat diterbitkan menjadi PO (misal: pagu anggaran habis atau barang tidak tersedia).
                </flux:subheading>
            </div>

            <div>
                <flux:textarea
                    wire:model="alasanPenolakanPengadaan"
                    label="Alasan Penolakan (Wajib)"
                    placeholder="Tuliskan alasan penolakan secara jelas..."
                    rows="3"
                    required
                />
            </div>

            <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="submit" variant="danger" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submitRejectPengadaan">Tolak Permintaan</span>
                    <span wire:loading wire:target="submitRejectPengadaan">Memproses...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
