    {{-- Modal Konfirmasi Tolak Kiriman Fisik Vendor --}}
    <flux:modal name="modal-reject-kiriman" wire:model="showRejectModal" class="md:w-[34rem]">
        <form wire:submit="executeTolakKiriman" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg" class="text-red-600 dark:text-red-400 flex items-center gap-2">
                    <flux:icon.no-symbol class="size-5" />
                    <span>Tolak Kiriman Fisik Vendor</span>
                </flux:heading>
                <flux:subheading>
                    @if ($po)
                        PO: <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $po->kode }}</span> &bull; {{ $po->pemasok?->nama ?? '-' }}
                    @endif
                </flux:subheading>
            </div>

            <div class="p-3 bg-red-50 dark:bg-red-950/40 rounded-xl border border-red-200 dark:border-red-800 text-xs text-red-800 dark:text-red-300 space-y-1">
                <div class="font-semibold flex items-center gap-1.5">
                    <flux:icon.exclamation-triangle class="size-4 shrink-0" />
                    <span>Perhatian: Penolakan Fisik Inbound</span>
                </div>
                <p>
                    Barang vendor yang ditolak <strong>tidak akan dimasukkan ke dalam stok fisik rumah sakit</strong> (kuantitas diterima = 0). Dokumen bukti kedatangan (RCV) akan diterbitkan dengan status <strong>DITOLAK</strong> untuk keperluan audit dan klaim retur rekanan.
                </p>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Nomor Surat Jalan Vendor <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="nomor_surat_jalan"
                        placeholder="No. surat jalan dari kurir vendor..."
                        required
                        class="w-full text-xs rounded-lg border {{ $errors->has('nomor_surat_jalan') ? 'border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    />
                    @error('nomor_surat_jalan') <p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Alasan Penolakan Kiriman <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        wire:model="alasan_penolakan"
                        rows="3"
                        placeholder="Jelaskan alasan penolakan secara rinci, misal: Kemasan jerigen bocor/rembes, segel pabrik rusak, sisa masa kedaluwarsa kurang dari standar RS, atau spesifikasi barang tidak cocok dengan PO..."
                        required
                        class="w-full text-xs rounded-lg border {{ $errors->has('alasan_penolakan') ? 'border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none resize-none"
                    ></textarea>
                    @error('alasan_penolakan') <p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="submit" variant="danger" wire:loading.attr="disabled" x-on:click="$flux.modal('modal-reject-kiriman').close()">
                    <span wire:loading.remove wire:target="executeTolakKiriman">Konfirmasi Tolak Kiriman</span>
                    <span wire:loading wire:target="executeTolakKiriman">Memproses...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
