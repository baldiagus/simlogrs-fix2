    {{-- Modal Stiker QR Code --}}
    <flux:modal name="modal-qr-code" wire:model="showQrModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedAsetQr)
            <div class="space-y-5 text-center p-2">
                <div>
                    <flux:heading size="lg">Stiker QR Code Aset Inventaris</flux:heading>
                    <flux:subheading>Stiker identitas fisik aset inventaris rumah sakit.</flux:subheading>
                </div>

                <div id="print-area-pinjam" class="p-6 bg-white dark:bg-zinc-900 border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-2xl inline-block shadow-xs">
                    <div class="flex justify-center mb-3">
                        {!! $qrCodeSvg !!}
                    </div>
                    <div class="font-mono font-extrabold text-sm text-zinc-900 dark:text-zinc-100 tracking-wider">
                        {{ $selectedAsetQr->kode_aset }}
                    </div>
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mt-1 max-w-[240px] truncate">
                        {{ $selectedAsetQr->barang?->nama }}
                    </div>
                    @if ($selectedAsetQr->nomor_seri)
                        <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">SN: {{ $selectedAsetQr->nomor_seri }}</div>
                    @endif
                    <div class="text-[10px] uppercase font-bold text-zinc-500 mt-2 border-t border-zinc-200 dark:border-zinc-800 pt-1.5">
                        SIMLOG RS : SIRKULASI ASET
                    </div>
                </div>

                <div class="flex justify-center pt-2">
                    <flux:button variant="primary" icon="printer" onclick="window.print()">Cetak Stiker</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
