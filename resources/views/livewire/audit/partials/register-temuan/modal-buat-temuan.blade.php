    {{-- Modal 1: Registrasi Temuan Audit Baru --}}
    <flux:modal name="modal-buat-temuan" wire:model="showCreateModal" class="w-full max-w-4xl">
        <form wire:submit="saveCreate" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Registrasi Temuan Audit Baru</flux:heading>
                <flux:subheading>Catat ketidaksesuaian tata kelola logistik hasil uji petik SPI ke dalam sistem CAPA.</flux:subheading>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                    Objek Pemeriksaan Audit <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    wire:model="objek_audit"
                    required
                    placeholder="Contoh: Gudang Sentral Logistik / Pengadaan PO-2026-003..."
                    class="w-full text-xs rounded-lg border {{ $errors->has('objek_audit') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                />
                @error('objek_audit') <p class="text-[10px] text-red-500 mt-0.5">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Tingkat Risiko <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model="tingkat_risiko"
                        required
                        class="w-full h-9 text-xs rounded-lg border {{ $errors->has('tingkat_risiko') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    >
                        <option value="Mayor">Mayor (Risiko Kritis / Keuangan)</option>
                        <option value="Moderat">Moderat (Ketidakpatuhan SOP)</option>
                        <option value="Minor">Minor (Administratif Ringan)</option>
                    </select>
                </div>

                <flux:field>
                    <flux:label>Tanggal Audit <span class="text-red-500">*</span></flux:label>
                    <flux:input type="date" wire:model="tanggal_audit" required />
                    <flux:error name="tanggal_audit" />
                </flux:field>

                <flux:field>
                    <flux:label>Batas Waktu (Due Date) <span class="text-red-500">*</span></flux:label>
                    <flux:input type="date" wire:model="due_date_capa" required />
                    <flux:error name="due_date_capa" />
                </flux:field>
            </div>

            {{-- Pilihan Entitas Terperiksa: Unit vs Gudang (Mutually Exclusive) --}}
            <div class="p-3.5 rounded-xl bg-white dark:bg-zinc-900 border {{ $errors->has('auditee') ? 'border-red-500 ring-1 ring-red-500/30' : 'border-zinc-200 dark:border-zinc-700/60' }} space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-zinc-200/60 dark:border-zinc-700/60">
                    <div>
                        <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Entitas Terperiksa (Auditee) <span class="text-red-500">*</span></div>
                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Pilih salah satu: Unit Ruangan ATAU Fasilitas Gudang. Memilih salah satu akan menonaktifkan pilihan lainnya.</div>
                    </div>
                    @if ($unit_id || $gudang_id)
                        <button type="button" wire:click="resetAuditee" class="text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline flex items-center gap-1">
                            <flux:icon name="arrow-path" class="size-3" />
                            Reset Pilihan
                        </button>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-ui.searchable-select
                            model="unit_id"
                            label="Unit Terperiksa"
                            placeholder="-- Pilih Unit Terperiksa --"
                            :options="$daftarUnit"
                            :disabled="!empty($gudang_id)"
                            live="true"
                        />
                        @if ($gudang_id)
                            <p class="text-[10px] text-zinc-400 mt-1 italic">Nonaktif karena Fasilitas Gudang telah dipilih.</p>
                        @endif
                    </div>

                    <div>
                        <x-ui.searchable-select
                            model="gudang_id"
                            label="Gudang Terperiksa"
                            placeholder="-- Pilih Fasilitas Gudang --"
                            :options="$daftarGudang"
                            :disabled="!empty($unit_id)"
                            live="true"
                        />
                        @if ($unit_id)
                            <p class="text-[10px] text-zinc-400 mt-1 italic">Nonaktif karena Unit Ruangan telah dipilih.</p>
                        @endif
                    </div>
                </div>

                @error('auditee')
                    <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                    Uraian Ringkasan Temuan Ketidaksesuaian <span class="text-red-500">*</span>
                </label>
                <textarea
                    wire:model="ringkasan"
                    required
                    rows="3"
                    placeholder="Uraikan temuan ketidaksesuaian, pasal SOP yang dilanggar, dan dampak operasionalnya..."
                    class="w-full text-xs rounded-lg border {{ $errors->has('ringkasan') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                ></textarea>
                @error('ringkasan') <p class="text-[10px] text-red-500 mt-0.5">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveCreate">Daftarkan Temuan</span>
                    <span wire:loading wire:target="saveCreate">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
