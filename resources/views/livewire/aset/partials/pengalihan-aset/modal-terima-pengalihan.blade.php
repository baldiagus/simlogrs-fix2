    {{-- MODAL KONFIRMASI SERAH TERIMA FISIK --}}
    @if ($transferTerima)
        <flux:modal name="modal-terima-pengalihan" wire:model="showTerimaModal" class="w-full max-w-2xl" style="max-height: 90vh; overflow-y: auto;">
            <div class="space-y-4 pb-2">
                <div>
                    <flux:heading size="lg">Konfirmasi Serah Terima Fisik Aset</flux:heading>
                    <flux:subheading>
                        Dokumen: <strong class="text-blue-600">{{ $transferTerima->kode }}</strong> &bull; Periksa kondisi fisik tiap alat saat tiba di tujuan
                    </flux:subheading>
                </div>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden">
                    @foreach ($transferTerima->detail as $item)
                        <div class="p-3 bg-white dark:bg-zinc-900 space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-xs font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ $item->aset?->barang?->nama ?? 'Aset' }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500 font-mono">
                                        Kode: {{ $item->aset?->kode_aset }} &bull; SN: {{ $item->aset?->nomor_seri ?? '-' }}
                                    </div>
                                </div>
                                <span class="text-[11px] text-zinc-400">
                                    Kondisi Awal: <strong>{{ $item->kondisi_sebelum }}</strong>
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <div>
                                    <label class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400">Kondisi Diterima:</label>
                                    <select
                                        wire:model="kondisiTerima.{{ $item->aset_id }}.kondisi"
                                        class="w-full text-xs rounded border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-1.5"
                                    >
                                        <option value="BAIK">BAIK</option>
                                        <option value="RUSAK_RINGAN">RUSAK_RINGAN</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400">Catatan Kondisi Fisik:</label>
                                    <input
                                        type="text"
                                        wire:model="kondisiTerima.{{ $item->aset_id }}.catatan"
                                        placeholder="Kondisi fisik normal..."
                                        class="w-full text-xs rounded border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-1.5"
                                    />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 rounded-xl border border-amber-200 dark:border-amber-900 text-xs text-amber-800 dark:text-amber-200">
                    <strong>Pemberitahuan Sistem:</strong> Konfirmasi ini akan memutasi kepemilikan dan lokasi aset secara permanen di database ke unit/gudang tujuan. Aset tidak akan kembali ke lokasi asal.
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button variant="ghost" wire:click="$set('showTerimaModal', false)">
                        Batal
                    </flux:button>
                    <flux:button variant="primary" wire:click="executeTerima">
                        Konfirmasi & Mutasi Kepemilikan
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
