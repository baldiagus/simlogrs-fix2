<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Audit Kepatuhan Pengadaan (PO vs PR)</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Uji kepatuhan silang pengadaan: deteksi PO tanpa PR sah, keterlambatan kirim vendor, dan selisih penerimaan fisik.</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Sub-Tab Hub Pengawasan Kepatuhan --}}
        <x-audit-kepatuhan-tabs active="kepatuhan" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Navigasi Tab Filter Anomali (Pills) --}}
        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
            <button
                wire:click="$set('filterAnomali', '')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterAnomali === '' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Semua Transaksi PO
            </button>
            <button
                wire:click="$set('filterAnomali', 'TANPA_PR')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterAnomali === 'TANPA_PR' ? 'bg-white dark:bg-zinc-900 text-rose-600 dark:text-rose-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                PO Tanpa PR Sah (Kritis)
            </button>
            <button
                wire:click="$set('filterAnomali', 'TERLAMBAT')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterAnomali === 'TERLAMBAT' ? 'bg-white dark:bg-zinc-900 text-amber-600 dark:text-amber-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Keterlambatan Kirim
            </button>
            <button
                wire:click="$set('filterAnomali', 'SELISIH_RCV')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterAnomali === 'SELISIH_RCV' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Diterima Sebagian
            </button>
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode PO, nama vendor..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-56">
                <select wire:model.live="filterPemasokId" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Rekanan Vendor</option>
                    @foreach ($daftarPemasok as $pms)
                        <option value="{{ $pms->id }}">{{ $pms->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Tabel Uji Petik Kepatuhan PO --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-32">Kode PO</flux:table.column>
                <flux:table.column class="w-44">Dasar PR Pemohon</flux:table.column>
                <flux:table.column class="w-44">Rekanan Vendor</flux:table.column>
                <flux:table.column class="text-right w-32">Nilai Dokumen</flux:table.column>
                <flux:table.column class="w-24">Est. Kirim</flux:table.column>
                <flux:table.column class="text-center w-36">Indikator Kepatuhan</flux:table.column>
                <flux:table.column class="text-right w-32">Aksi Auditor</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $po)
                    @php
                        $isTanpaPr = empty($po->permintaan_pengadaan_id);
                        $isLate = $po->estimasi_tanggal_kirim && $po->estimasi_tanggal_kirim->isPast() && $po->status !== 'DITERIMA_LENGKAP';
                        $isSebagian = ($po->status === 'DITERIMA_SEBAGIAN');
                        $hasAnomaly = ($isTanpaPr || $isLate || $isSebagian);
                    @endphp
                    <flux:table.row :key="$po->id">
                        {{-- Kode PO --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            <div>{{ $po->kode }}</div>
                            <div class="text-[10px] text-zinc-500 font-sans mt-0.5">
                                {{ $po->tanggal_po?->format('d/m/Y') }}
                            </div>
                        </flux:table.cell>

                        {{-- Ref PR --}}
                        <flux:table.cell style="white-space: normal;">
                            @if ($po->permintaanPengadaan)
                                <div class="font-mono text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $po->permintaanPengadaan->kode }}
                                </div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400">
                                    Unit: {{ $po->permintaanPengadaan->unit?->nama ?? '-' }}
                                </div>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                    <flux:icon name="exclamation-triangle" class="size-3.5" />
                                    <span>TANPA DOKUMEN PR</span>
                                </span>
                            @endif
                        </flux:table.cell>

                        {{-- Rekanan Vendor --}}
                        <flux:table.cell style="white-space: normal;">
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $po->pemasok?->nama ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Nilai Dokumen --}}
                        <flux:table.cell class="text-right font-mono font-semibold text-xs tabular-nums text-zinc-900 dark:text-zinc-100">
                            <x-rupiah :value="$po->total_biaya" />
                        </flux:table.cell>

                        {{-- Estimasi Kirim --}}
                        <flux:table.cell class="text-xs tabular-nums">
                            @if ($po->estimasi_tanggal_kirim)
                                <span class="{{ $isLate ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                    {{ $po->estimasi_tanggal_kirim->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-zinc-400">-</span>
                            @endif
                        </flux:table.cell>

                        {{-- Indikator Kepatuhan --}}
                        <flux:table.cell class="text-center">
                            @if ($isTanpaPr)
                                <flux:badge size="sm" color="rose">Anomali: Tanpa PR</flux:badge>
                            @elseif ($isLate)
                                <flux:badge size="sm" color="amber">Terlambat Kirim</flux:badge>
                            @elseif ($isSebagian)
                                <flux:badge size="sm" color="blue">Diterima Sebagian</flux:badge>
                            @else
                                <flux:badge size="sm" color="emerald">Sesuai SOP</flux:badge>
                            @endif
                        </flux:table.cell>

                        {{-- Aksi Auditor --}}
                        <flux:table.cell class="text-right w-16">
                            @if ($hasAnomaly)
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        <flux:menu.item wire:click="openTerbitkanCapa({{ $po->id }}, '{{ $isTanpaPr ? 'TANPA_PR' : ($isLate ? 'TERLAMBAT' : 'SELISIH_RCV') }}')" icon="shield-exclamation" class="text-rose-600 dark:text-rose-400">
                                            Terbitkan CAPA
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @else
                                <span class="text-[11px] text-zinc-400 italic">Sesuai SOP</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Tidak ada transaksi pesanan pembelian yang sesuai filter." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Terbitkan Temuan CAPA dari Hasil Audit --}}
    <flux:modal name="modal-terbitkan-capa" wire:model="showCapaModal" class="md:w-[38rem]">
        @if ($selectedPo)
            <form wire:submit="saveCapa" class="space-y-4">
                <div>
                    <flux:heading size="lg">Terbitkan Temuan Audit (CAPA)</flux:heading>
                    <flux:subheading>Registrasi ketidaksesuaian transaksi ke dalam sistem monitoring tindak lanjut SPI.</flux:subheading>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Objek Pemeriksaan Audit <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="objek_audit"
                        required
                        class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                    @error('objek_audit') <p class="text-[10px] text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                            Tingkat Risiko Temuan <span class="text-red-500">*</span>
                        </label>
                        <select
                            wire:model="tingkat_risiko"
                            required
                            class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="Mayor">Mayor (Risiko Kritis / Keuangan)</option>
                            <option value="Moderat">Moderat (Ketidakpatuhan SOP)</option>
                            <option value="Minor">Minor (Administratif Ringan)</option>
                        </select>
                    </div>

                    <flux:field>
                        <flux:label>Batas Waktu Penyelesaian (Due Date) <span class="text-red-500">*</span></flux:label>
                        <flux:input type="date" wire:model="due_date_capa" required />
                        <flux:error name="due_date_capa" />
                    </flux:field>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Ringkasan Uraian Temuan Ketidaksesuaian <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        wire:model="ringkasan"
                        required
                        rows="3"
                        class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    ></textarea>
                    @error('ringkasan') <p class="text-[10px] text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveCapa">Terbitkan Temuan ke CAPA</span>
                        <span wire:loading wire:target="saveCapa">Menerbitkan...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
