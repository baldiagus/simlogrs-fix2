    {{-- Modal 2: Formulir Tindak Lanjut Perbaikan (Auditee) --}}
    <flux:modal name="modal-tindak-lanjut" wire:model="showTindakLanjutModal" class="md:w-[42rem]">
        @if ($selectedTemuan)
            <form wire:submit="saveTindakLanjut" class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Tindak Lanjut Perbaikan: {{ $selectedTemuan->kode }}</flux:heading>
                    <flux:subheading>Objek: {{ $selectedTemuan->objek_audit }} &bull; Batas Waktu: {{ $selectedTemuan->due_date_capa?->format('d/m/Y') }}</flux:subheading>
                </div>

                {{-- Deskripsi Temuan Asli --}}
                <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                    <span class="font-semibold text-zinc-700 dark:text-zinc-300">Uraian Temuan SPI:</span>
                    <p class="text-zinc-600 dark:text-zinc-400 mt-1">{{ $selectedTemuan->ringkasan }}</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Analisis Akar Masalah (Root Cause Analysis) <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        wire:model="analisis_penyebab"
                        required
                        rows="2"
                        placeholder="Jelaskan faktor penyebab mendasar (man, method, material, machine)..."
                        class="w-full text-xs rounded-lg border {{ $errors->has('analisis_penyebab') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    ></textarea>
                    @error('analisis_penyebab') <p class="text-[10px] text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Rencana / Tindakan Korektif & Preventif yang Dijalankan <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        wire:model="tindakan_perbaikan"
                        required
                        rows="3"
                        placeholder="Uraikan tindakan perbaikan langsung dan pencegahan agar tidak berulang..."
                        class="w-full text-xs rounded-lg border {{ $errors->has('tindakan_perbaikan') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    ></textarea>
                    @error('tindakan_perbaikan') <p class="text-[10px] text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Unggah Bukti Fisik / Dokumen Pendukung Perbaikan (Bisa Pilih Banyak Berkas)
                    </label>
                    <input
                        type="file"
                        wire:model="fileBuktiList"
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.docx"
                        class="w-full text-xs text-zinc-600 dark:text-zinc-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 hover:file:bg-zinc-200 dark:file:bg-zinc-800 dark:hover:file:bg-zinc-700"
                    />

                    {{-- Berkas Sementara yang Baru Dipilih --}}
                    @if (!empty($fileBuktiList) && is_array($fileBuktiList))
                        <div class="mt-2 space-y-1.5 bg-blue-50/50 dark:bg-blue-950/20 p-2.5 rounded-lg border border-blue-200/80 dark:border-blue-900/40">
                            <div class="text-[11px] font-semibold text-blue-900 dark:text-blue-300">Berkas Siap Diunggah:</div>
                            @foreach ($fileBuktiList as $idx => $tempF)
                                <div class="flex items-center justify-between text-xs text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-900 p-1.5 rounded border border-zinc-200/80 dark:border-zinc-700/80" wire:key="temp-f-{{ $idx }}">
                                    <span class="truncate max-w-xs font-medium">{{ $tempF->getClientOriginalName() }} ({{ number_format($tempF->getSize() / 1024, 1) }} KB)</span>
                                    <button type="button" wire:click="removeTempFile({{ $idx }})" class="text-rose-600 hover:text-rose-700 text-xs font-semibold">Batal</button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Daftar Berkas Tersimpan Sebelumnya --}}
                    @if (!empty($selectedTemuan->bukti_perbaikan) && is_array($selectedTemuan->bukti_perbaikan) && count($selectedTemuan->bukti_perbaikan) > 0)
                        <div class="mt-2 space-y-1.5 bg-zinc-50 dark:bg-zinc-800/40 p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <div class="text-[11px] font-semibold text-zinc-700 dark:text-zinc-300">Berkas Tersimpan Sebelumnya:</div>
                            @foreach ($selectedTemuan->bukti_perbaikan as $idx => $bf)
                                <div class="flex items-center justify-between text-xs p-1.5 bg-white dark:bg-zinc-900 rounded border border-zinc-200/80 dark:border-zinc-700/80" wire:key="stored-f-{{ $idx }}">
                                    <a href="{{ Storage::disk('public')->url($bf['path'] ?? '') }}" target="_blank" class="text-blue-600 hover:underline font-mono truncate max-w-xs flex items-center gap-1.5">
                                        <flux:icon name="paper-clip" class="size-3.5 shrink-0" />
                                        <span>{{ $bf['nama'] ?? 'Dokumen Bukti' }}</span>
                                        @if (!empty($bf['ukuran']))
                                            <span class="text-[10px] text-zinc-400">({{ number_format($bf['ukuran'] / 1024, 1) }} KB)</span>
                                        @endif
                                    </a>
                                    <button type="button" wire:click="deleteExistingBukti({{ $idx }})" class="text-rose-600 hover:text-rose-700 text-xs font-semibold">Hapus</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveTindakLanjut">Kirim Tindak Lanjut ke SPI</span>
                        <span wire:loading wire:target="saveTindakLanjut">Mengirim Berkas...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
