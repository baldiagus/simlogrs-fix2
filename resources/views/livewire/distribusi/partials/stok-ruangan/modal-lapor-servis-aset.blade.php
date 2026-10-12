    {{-- Modal Lapor Servis / Kerusakan Aset Langsung ke IPSRS --}}
    <flux:modal name="modal-lapor-servis-aset" wire:model="showLaporServisModal" class="md:w-[34rem]">
        @if ($selectedAsetServis)
            <form wire:submit="simpanLaporServis" class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon name="wrench" class="size-5 text-indigo-600 dark:text-indigo-400" />
                        <span>Lapor Servis / Kerusakan Aset</span>
                    </flux:heading>
                    <flux:subheading>Terbitkan tiket perbaikan sarana langsung ke teknisi IPSRS untuk unit yang mengalami kendala.</flux:subheading>
                </div>

                {{-- Preview Identitas Aset Terpilih --}}
                <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/60 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">Aset Sarana:</span>
                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $selectedAsetServis->barang?->nama }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">Kode & No. Seri:</span>
                        <span class="font-mono font-semibold text-zinc-800 dark:text-zinc-200">{{ $selectedAsetServis->kode_aset }} &bull; {{ $selectedAsetServis->nomor_seri ?: '-' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">Lokasi Penempatan:</span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $selectedAsetServis->unit?->nama ?? 'Ruangan' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">Kondisi Saat Ini:</span>
                        <span class="font-semibold {{ $selectedAsetServis->kondisi === 'BAIK' ? 'text-emerald-600' : 'text-amber-600' }}">
                            {{ $selectedAsetServis->kondisi }}
                        </span>
                    </div>
                </div>

                {{-- Pilihan Prioritas Penanganan --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Tingkat Prioritas Darurat <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer {{ $servisPrioritas === 'SEDANG' ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-950/20' : 'border-zinc-200 dark:border-zinc-700' }}">
                            <input type="radio" wire:model="servisPrioritas" value="SEDANG" class="text-blue-600">
                            <div>
                                <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Sedang (Rutin)</div>
                                <div class="text-[10px] text-zinc-400">Kerusakan minor / gangguan ringan</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer {{ $servisPrioritas === 'TINGGI' ? 'border-amber-500 bg-amber-50/50 dark:bg-amber-950/20' : 'border-zinc-200 dark:border-zinc-700' }}">
                            <input type="radio" wire:model="servisPrioritas" value="TINGGI" class="text-amber-600">
                            <div>
                                <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Tinggi</div>
                                <div class="text-[10px] text-zinc-400">Menghambat operasional sebagian</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer {{ $servisPrioritas === 'CITO' ? 'border-red-500 bg-red-50/50 dark:bg-red-950/20' : 'border-zinc-200 dark:border-zinc-700' }}">
                            <input type="radio" wire:model="servisPrioritas" value="CITO" class="text-red-600">
                            <div>
                                <div class="text-xs font-semibold text-red-600 dark:text-red-400">CITO (Darurat Pasien)</div>
                                <div class="text-[10px] text-zinc-400">Mati total & kritis layanan</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer {{ $servisPrioritas === 'RENDAH' ? 'border-zinc-400 bg-zinc-50/50 dark:bg-zinc-800/40' : 'border-zinc-200 dark:border-zinc-700' }}">
                            <input type="radio" wire:model="servisPrioritas" value="RENDAH" class="text-zinc-600">
                            <div>
                                <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Rendah</div>
                                <div class="text-[10px] text-zinc-400">Perbaikan terjadwal berkala</div>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Deskripsi Kerusakan --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Deskripsi Kerusakan / Kendala Alat <span class="text-red-500">*</span>
                    </label>
                    <flux:textarea
                        wire:model="servisDeskripsi"
                        placeholder="Jelaskan secara spesifik gejala kerusakan, bunyi abnormal, indikator error, atau kendala fisik yang terjadi..."
                        rows="3"
                        required
                    />
                    @error('servisDeskripsi') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                {{-- Lampiran Foto Bukti Kerusakan --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Foto Bukti Kerusakan <span class="text-zinc-400 font-normal">(Opsional)</span>
                    </label>
                    @if ($servisFoto)
                        <div class="relative w-fit">
                            <img src="{{ $servisFoto->temporaryUrl() }}" class="h-28 w-auto rounded-lg object-cover border border-zinc-200 dark:border-zinc-700 shadow-xs" alt="Preview Foto">
                            <button type="button" wire:click="hapusFotoServis" class="absolute -top-1.5 -right-1.5 bg-red-600 text-white rounded-full p-1 hover:bg-red-700 shadow-sm">
                                <flux:icon name="x-mark" class="size-3" />
                            </button>
                        </div>
                    @else
                        <input
                            type="file"
                            wire:model="servisFoto"
                            accept="image/*"
                            class="text-xs text-zinc-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-200"
                        />
                    @endif
                    @error('servisFoto') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                {{-- Tombol Aksi Modal --}}
                <div class="flex items-center justify-between pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button type="button" variant="ghost" wire:click="batalLaporServis">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="simpanLaporServis">Kirim Laporan ke IPSRS</span>
                        <span wire:loading wire:target="simpanLaporServis">Menerbitkan Tiket...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
