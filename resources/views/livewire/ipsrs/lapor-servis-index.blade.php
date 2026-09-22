<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman & Tombol Tambah --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Lapor Kerusakan & Servis IPSRS</flux:heading>
                <flux:subheading>Pengajuan tiket perbaikan fasilitas, sarana, prasarana, dan alat kerja ruangan ke teknisi IPSRS.</flux:subheading>
            </div>

            @can('create_layanan_unit::lapor::servis')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Lapor Kerusakan
                </flux:button>
            @endcan
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode tiket, nama alat, keluhan..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-52">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status</option>
                    <option value="DILAPORKAN">DILAPORKAN</option>
                    <option value="DITUGASKAN">DITUGASKAN (SPK)</option>
                    <option value="SEDANG_DIKERJAKAN">SEDANG DIKERJAKAN</option>
                    <option value="MENUNGGU_SPAREPART">MENUNGGU SPAREPART</option>
                    <option value="UJI_FUNGSI">UJI FUNGSI</option>
                    <option value="SELESAI">SELESAI</option>
                    <option value="DIBATALKAN">DIBATALKAN</option>
                </select>
            </div>

            <div class="w-full sm:w-44">
                <select wire:model.live="filterPrioritas" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Prioritas</option>
                    <option value="CITO">CITO (Darurat)</option>
                    <option value="TINGGI">TINGGI</option>
                    <option value="SEDANG">SEDANG</option>
                    <option value="RENDAH">RENDAH</option>
                </select>
            </div>
        </div>

        {{-- Tabel Daftar Tiket Kerusakan --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">No. Tiket</flux:table.column>
                <flux:table.column>Sarana / Alat</flux:table.column>
                <flux:table.column>Unit Pelapor</flux:table.column>
                <flux:table.column>Prioritas</flux:table.column>
                <flux:table.column>Teknisi Ditugaskan</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-36">Status</flux:table.column>
                <flux:table.column class="text-right w-28">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarTiket as $item)
                    @php
                        $prioritasColor = match($item->prioritas) {
                            'CITO' => 'rose',
                            'TINGGI' => 'amber',
                            'SEDANG' => 'blue',
                            'RENDAH' => 'zinc',
                            default => 'zinc',
                        };
                    @endphp
                    <flux:table.row :key="$item->id">
                        {{-- Kode Tiket --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            <div>{{ $item->kode }}</div>
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-sans mt-0.5">
                                {{ $item->dibuat_pada?->format('d/m/Y H:i') }}
                            </div>
                        </flux:table.cell>

                        {{-- Sarana / Alat --}}
                        <flux:table.cell>
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $item->aset?->barang?->nama ?? '-' }}
                            </div>
                            <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Kode: {{ $item->aset?->kode_aset ?? '-' }}
                                @if ($item->aset?->nomor_seri)
                                    &bull; SN: {{ $item->aset->nomor_seri }}
                                @endif
                            </div>
                            <div class="text-[11px] text-zinc-600 dark:text-zinc-300 mt-1 line-clamp-1">
                                {{ $item->deskripsi_masalah }}
                            </div>
                        </flux:table.cell>

                        {{-- Unit Pelapor --}}
                        <flux:table.cell>
                            <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $item->unit?->nama ?? '-' }}
                            </div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                Pelapor: {{ $item->pelapor?->nama ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Prioritas --}}
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$prioritasColor">
                                {{ $item->prioritas }}
                            </flux:badge>
                        </flux:table.cell>

                        {{-- Teknisi Ditugaskan --}}
                        <flux:table.cell>
                            @if ($item->penugasanAktif && $item->penugasanAktif->teknisi)
                                <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ $item->penugasanAktif->teknisi->nama }}
                                </div>
                                <div class="text-[10px] font-mono text-zinc-500 dark:text-zinc-400">
                                    SPK: {{ $item->penugasanAktif->kode }}
                                </div>
                            @else
                                <span class="text-zinc-400 text-xs italic">Belum Ditugaskan</span>
                            @endif
                        </flux:table.cell>

                        {{-- Status --}}
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button
                                    wire:click="showDetail({{ $item->id }})"
                                    size="xs"
                                    variant="ghost"
                                    icon="eye"
                                    title="Lihat Detail Tiket"
                                />

                                @if ($item->status === 'DILAPORKAN' && (auth()->user()?->id === $item->dilaporkan_oleh || auth()->user()?->can('page_Dashboard')))
                                    <flux:button
                                        wire:click="confirmBatal({{ $item->id }})"
                                        size="xs"
                                        variant="ghost"
                                        icon="x-mark"
                                        class="text-rose-600 hover:text-rose-700"
                                        title="Batalkan Tiket"
                                    />
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada tiket laporan kerusakan sarana yang terdaftar." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$daftarTiket" />
    </flux:card>

    {{-- Modal Formulir Lapor Kerusakan --}}
    <flux:modal name="modal-lapor-servis" wire:model="showModal" class="md:w-[38rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">Formulir Lapor Kerusakan Sarana</flux:heading>
                <flux:subheading>Sampaikan keluhan kendala teknis fasilitas atau alat kerja ruangan untuk ditangani tim IPSRS.</flux:subheading>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Pilih Fasilitas / Alat yang Bermasalah <span class="text-red-500">*</span>
                </label>
                <select
                    wire:model="aset_id"
                    required
                    class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                    <option value="">-- Pilih Inventaris Aset --</option>
                    @foreach ($daftarAsetTersedia as $ast)
                        <option value="{{ $ast->id }}">
                            [{{ $ast->kode_aset }}] {{ $ast->barang?->nama }} {{ $ast->nomor_seri ? "(SN: {$ast->nomor_seri})" : '' }}
                        </option>
                    @endforeach
                </select>
                @error('aset_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Tingkat Urgensi Kerusakan <span class="text-red-500">*</span>
                </label>
                <select
                    wire:model="prioritas"
                    required
                    class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                    <option value="SEDANG">SEDANG (Operasional harian umum)</option>
                    <option value="RENDAH">RENDAH (Masalah minor / estetik)</option>
                    <option value="TINGGI">TINGGI (Mengganggu pelayanan unit)</option>
                    <option value="CITO">CITO (Darurat: Pelayanan kritis berhenti / risiko keselamatan)</option>
                </select>
                @error('prioritas') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Deskripsi Masalah / Gejala Kerusakan <span class="text-red-500">*</span>
                </label>
                <textarea
                    wire:model="deskripsi_masalah"
                    required
                    rows="3"
                    placeholder="Jelaskan secara jelas gejala kerusakan fisik, suara aneh, kebocoran, atau tombol yang tidak berfungsi..."
                    class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
                ></textarea>
                @error('deskripsi_masalah') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Kirim Laporan ke IPSRS</span>
                    <span wire:loading wire:target="save">Mengirim...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Detail Tiket Kerusakan --}}
    <flux:modal name="modal-detail-tiket" wire:model="showDetailModal" class="md:w-[48rem]">
        @if ($selectedTiket)
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail Tiket Servis: {{ $selectedTiket->kode }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Tanggal Lapor: {{ $selectedTiket->dibuat_pada?->format('d F Y H:i') }}
                        </flux:subheading>
                    </div>
                    <div class="flex items-center gap-2">
                        @php
                            $prioCol = match($selectedTiket->prioritas) {
                                'CITO' => 'rose',
                                'TINGGI' => 'amber',
                                'SEDANG' => 'blue',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$prioCol">{{ $selectedTiket->prioritas }}</flux:badge>
                        <x-status-badge :status="$selectedTiket->status" />
                    </div>
                </div>

                {{-- Informasi Aset & Pelapor --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Nama Fasilitas / Alat</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiket->aset?->barang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Kode Register Aset</div>
                        <div class="font-mono font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiket->aset?->kode_aset ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Ruangan</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiket->unit?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Pelapor</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiket->pelapor?->nama ?? '-' }}</div>
                    </div>
                </div>

                {{-- Deskripsi Gejala Kerusakan --}}
                <div class="p-3.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 text-xs space-y-1">
                    <div class="font-semibold text-zinc-700 dark:text-zinc-300">Deskripsi Keluhan / Gejala Kerusakan:</div>
                    <div class="text-zinc-800 dark:text-zinc-200 whitespace-pre-line">{{ $selectedTiket->deskripsi_masalah }}</div>
                </div>

                {{-- Riwayat Disposisi SPK --}}
                @if ($selectedTiket->penugasan->isNotEmpty())
                    <div class="space-y-2">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Surat Perintah Kerja (SPK) Ditugaskan</div>
                        @foreach ($selectedTiket->penugasan as $spk)
                            <div class="p-3 rounded-lg border border-zinc-200 dark:border-zinc-800 text-xs flex items-center justify-between">
                                <div>
                                    <div class="font-mono font-semibold">{{ $spk->kode }}</div>
                                    <div class="text-zinc-500 dark:text-zinc-400 mt-0.5">
                                        Teknisi: <strong>{{ $spk->teknisi?->nama ?? '-' }}</strong> &bull; Ditugaskan: {{ $spk->tanggal_penugasan?->format('d/m/Y') }}
                                    </div>
                                    @if ($spk->instruksi_khusus)
                                        <div class="text-zinc-600 dark:text-zinc-300 mt-1 italic">{{ $spk->instruksi_khusus }}</div>
                                    @endif
                                </div>
                                <x-status-badge :status="$spk->status" />
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Riwayat Lembar Kerja Teknisi --}}
                @if ($selectedTiket->logMaintenance->isNotEmpty())
                    <div class="space-y-2">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Catatan Pengerjaan & Tindakan Teknisi</div>
                        @foreach ($selectedTiket->logMaintenance as $log)
                            <div class="p-3 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900 text-xs space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">Teknisi: {{ $log->teknisi?->nama }} ({{ $log->tanggal_pengerjaan?->format('d/m/Y H:i') }})</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $log->hasil === 'BERHASIL' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($log->hasil === 'PENDING' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300') }}">
                                        {{ $log->hasil }}
                                    </span>
                                </div>
                                @if ($log->diagnosa)
                                    <div><span class="text-zinc-500">Diagnosa:</span> {{ $log->diagnosa }}</div>
                                @endif
                                <div><span class="text-zinc-500">Tindakan:</span> {{ $log->tindakan }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showDetailModal', false)">
                        Tutup
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
