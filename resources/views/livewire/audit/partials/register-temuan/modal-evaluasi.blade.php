    {{-- Modal 3: Evaluasi Closing Temuan (Auditor) --}}
    <flux:modal name="modal-evaluasi-closing" wire:model="showEvaluasiModal" class="md:w-[44rem]">
        @if ($selectedTemuan)
            <form wire:submit="saveEvaluasiClosing" class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Evaluasi SPI: {{ $selectedTemuan->kode }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Objek: {{ $selectedTemuan->objek_audit }}
                        </flux:subheading>
                    </div>
                    @php
                        $cColor = match($selectedTemuan->status_capa) {
                            \App\Enums\Audit\StatusCapa::InProgress->value => 'amber',
                            \App\Enums\Audit\StatusCapa::InReview->value   => 'blue',
                            \App\Enums\Audit\StatusCapa::Closed->value      => 'emerald',
                            default       => 'zinc',
                        };
                    @endphp
                    <div style="margin-right: 2.25rem;">
                        <flux:badge size="sm" :color="$cColor">{{ $selectedTemuan->status_capa }}</flux:badge>
                    </div>
                </div>

                {{-- Detail Jawaban Tindak Lanjut Unit --}}
                <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs space-y-2">
                    <div>
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Akar Penyebab Dilaporkan:</span>
                        <p class="text-zinc-600 dark:text-zinc-400 mt-0.5">{{ $selectedTemuan->analisis_penyebab ?: 'Belum diisi' }}</p>
                    </div>
                    <div class="pt-1.5 border-t border-zinc-200/60 dark:border-zinc-700/60">
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Tindakan Perbaikan Dilakukan:</span>
                        <p class="text-zinc-600 dark:text-zinc-400 mt-0.5">{{ $selectedTemuan->tindakan_perbaikan ?: 'Belum diisi' }}</p>
                    </div>
                    @if (!empty($selectedTemuan->bukti_perbaikan) && is_array($selectedTemuan->bukti_perbaikan) && count($selectedTemuan->bukti_perbaikan) > 0)
                        <div class="pt-2 border-t border-zinc-200/60 dark:border-zinc-700/60 space-y-1.5">
                            <span class="font-semibold text-zinc-700 dark:text-zinc-300 block">Daftar Dokumen / Bukti Fisik Terunggah ({{ count($selectedTemuan->bukti_perbaikan) }} berkas):</span>
                            <div class="space-y-1.5">
                                @foreach ($selectedTemuan->bukti_perbaikan as $bItem)
                                    <div class="flex items-center justify-between p-2 rounded bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700">
                                        <div class="flex items-center gap-2">
                                            <flux:icon name="{{ in_array($bItem['tipe'] ?? '', ['jpg','jpeg','png','webp']) ? 'photo' : 'document-text' }}" class="size-4 text-blue-600" />
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $bItem['nama'] ?? 'Dokumen Bukti' }}</span>
                                            @if (!empty($bItem['ukuran']))
                                                <span class="text-[10px] text-zinc-400 font-mono">({{ number_format($bItem['ukuran'] / 1024, 1) }} KB)</span>
                                            @endif
                                        </div>
                                        <a href="{{ Storage::disk('public')->url($bItem['path'] ?? '') }}" target="_blank" class="text-xs text-blue-600 hover:underline inline-flex items-center gap-1 font-semibold">
                                            <span>Buka Berkas</span>
                                            <flux:icon name="arrow-top-right-on-square" class="size-3" />
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Keputusan Evaluasi Auditor SPI <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model="hasil_evaluasi"
                        required
                        class="w-full h-9 text-xs rounded-lg border {{ $errors->has('hasil_evaluasi') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    >
                        <option value="{{ \App\Enums\Audit\StatusCapa::Closed->value }}">Closed (Tindakan perbaikan terbukti valid dan tuntas)</option>
                        <option value="{{ \App\Enums\Audit\StatusCapa::InProgress->value }}">Kembalikan ke In Progress (Bukti belum memadai / perlu revisi)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Catatan Evaluasi Resmi Auditor <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        wire:model="catatan_evaluasi"
                        required
                        rows="3"
                        placeholder="Berikan catatan penilaian kelaikan tindakan perbaikan atau arahan perbaikan lanjutan..."
                        class="w-full text-xs rounded-lg border {{ $errors->has('catatan_evaluasi') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    ></textarea>
                    @error('catatan_evaluasi') <p class="text-[10px] text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveEvaluasiClosing">Simpan Keputusan Evaluasi</span>
                        <span wire:loading wire:target="saveEvaluasiClosing">Memproses...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
