    {{-- Modal Label QR Code Batch --}}
    <flux:modal name="modal-qr-batch" wire:model="showQrModal" class="md:w-[32rem]">
        @if ($selectedBatchQr)
            <div class="space-y-4 text-center p-2">
                <flux:heading size="lg">Label QR Code Batch Persediaan</flux:heading>
                <flux:subheading>Identitas fisik barcode rak simpan dan batch kedaluwarsa.</flux:subheading>

                <div id="print-area-batch" class="p-6 bg-white dark:bg-zinc-900 border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-2xl inline-block shadow-xs">
                    <div class="flex justify-center mb-3">
                        {!! $qrCodeSvg !!}
                    </div>
                    <div class="font-mono font-extrabold text-sm text-zinc-900 dark:text-zinc-100">
                        {{ $selectedBatchQr->nomor_batch }}
                    </div>
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mt-0.5">
                        {{ $selectedBatchQr->barang?->nama }}
                    </div>
                    <div class="text-[11px] text-zinc-500 font-mono mt-0.5">
                        Rak: {{ $selectedBatchQr->lokasiGudang?->nama ?? 'Sentral' }} &bull; ED: {{ $selectedBatchQr->tanggal_kedaluwarsa?->format('d/m/Y') ?? 'Non-ED' }}
                    </div>
                </div>

                <div class="flex justify-center pt-2">
                    <flux:button variant="primary" icon="printer" onclick="window.print()">Cetak Label</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
