    {{-- Modal Stiker QR Code --}}
    <flux:modal name="modal-qr-code" wire:model="showQrModal" class="md:w-96">
        @if ($selectedAset)
            <div class="space-y-5 text-center p-2">
                <div>
                    <flux:heading size="lg">Stiker QR Code Aset</flux:heading>
                    <flux:subheading>Tempelkan stiker ini pada unit fisik barang inventaris.</flux:subheading>
                </div>

                <div id="print-area" class="p-6 bg-white dark:bg-zinc-900 border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-2xl inline-block shadow-xs">
                    <div class="flex justify-center mb-3">
                        {!! $qrCodeSvg !!}
                    </div>
                    <div class="font-mono font-extrabold text-sm text-zinc-900 dark:text-zinc-100 tracking-wider">
                        {{ $selectedAset->kode_aset }}
                    </div>
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mt-1 max-w-[200px] truncate">
                        {{ $selectedAset->barang?->nama }}
                    </div>
                    @if ($selectedAset->nomor_seri)
                        <div class="font-mono text-[10px] text-zinc-500 dark:text-zinc-400">SN: {{ $selectedAset->nomor_seri }}</div>
                    @endif
                    <div class="text-[10px] uppercase font-bold text-zinc-500 mt-2 border-t border-zinc-200 dark:border-zinc-800 pt-1.5">
                        SIMLOG RS • INVENTARIS
                    </div>
                </div>

                <div class="flex justify-center pt-2">
                    <flux:button variant="primary" icon="printer" onclick="window.print()">Cetak Stiker</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
