<div class="space-y-6">
    {{-- Ringkasan Metrik Pengawasan Opname Fisik --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex items-center gap-4">
            <div class="p-3 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400">
                <flux:icon name="clipboard-document-check" class="w-6 h-6" />
            </div>
            <div>
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Sesi Stock Opname</div>
                <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ $countTotalSesi }} Sesi</div>
            </div>
        </flux:card>

        <flux:card class="p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex items-center gap-4">
            <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400">
                <flux:icon name="exclamation-triangle" class="w-6 h-6" />
            </div>
            <div>
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Sesi Mengalami Selisih Fisik</div>
                <div class="text-xl font-bold text-rose-600 dark:text-rose-400">{{ $countAdaSelisih }} Sesi</div>
            </div>
        </flux:card>

        <flux:card class="p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex items-center gap-4">
            <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="check-badge" class="w-6 h-6" />
            </div>
            <div>
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Sesi Akurat / Klop Sempurna</div>
                <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $countKlop }} Sesi</div>
            </div>
        </flux:card>
    </div>

    {{-- Kartu Utama Tabel Pemeriksaan Selisih --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Pemeriksaan Selisih Opname (Audit)</flux:heading>
                <flux:subheading>Pengawasan independen uji petik fisik barang di rak gudang terhadap pencatatan saldo sistem dan tindak lanjut CAPA.</flux:subheading>
            </div>
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-72">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode opname, gudang, catatan..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-48">
                <select wire:model.live="filterGudangId" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Gudang</option>
                    @foreach ($daftarGudang as $g)
                        <option value="{{ $g->id }}">{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-44">
                <select wire:model.live="filterKategoriSelisih" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Kondisi Selisih</option>
                    <option value="ADA_SELISIH">Ada Selisih Fisik</option>
                    <option value="SELISIH_KURANG">Selisih Kurang (Minus)</option>
                    <option value="SELISIH_LEBIH">Selisih Lebih (Plus)</option>
                    <option value="KLOP">Klop Sempurna (0)</option>
                </select>
            </div>

            <div class="w-full sm:w-44">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status Sesi</option>
                    <option value="BERJALAN">BERJALAN (Aktif)</option>
                    <option value="MENUNGGU_APPROVAL">MENUNGGU APPROVAL</option>
                    <option value="DISETUJUI">DISETUJUI (Selesai)</option>
                </select>
            </div>
        </div>

        {{-- Tabel Hasil Uji Petik Opname --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-36">Kode Opname</flux:table.column>
                <flux:table.column>Fasilitas Gudang</flux:table.column>
                <flux:table.column class="w-28">Tgl Opname</flux:table.column>
                <flux:table.column class="text-center w-28">Total Batch</flux:table.column>
                <flux:table.column class="text-center w-44">Temuan Selisih Fisik</flux:table.column>
                <flux:table.column class="text-center w-36">Status Sesi</flux:table.column>
                <flux:table.column class="text-right w-44">Aksi Auditor</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $opn)
                    @php
                        $hasSelisih = $opn->selisih_count > 0;
                        $statusColor = match($opn->status) {
                            'DISETUJUI' => 'emerald',
                            'MENUNGGU_APPROVAL' => 'amber',
                            'BERJALAN' => 'blue',
                            default => 'zinc',
                        };
                    @endphp
                    <flux:table.row :key="$opn->id">
                        {{-- Kode Sesi --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            <div>{{ $opn->kode }}</div>
                            <div class="text-[10px] text-zinc-500 font-sans mt-0.5">
                                Oleh: {{ $opn->pembuat?->nama ?? 'Petugas' }}
                            </div>
                        </flux:table.cell>

                        {{-- Fasilitas Gudang --}}
                        <flux:table.cell>
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $opn->gudang?->nama ?? '-' }}
                            </div>
                            <div class="text-[11px] text-zinc-500 line-clamp-1">
                                {{ $opn->catatan ?: 'Siklus opname fisik rutin' }}
                            </div>
                        </flux:table.cell>

                        {{-- Tanggal --}}
                        <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                            {{ $opn->tanggal_opname?->format('d/m/Y') }}
                        </flux:table.cell>

                        {{-- Total Batch --}}
                        <flux:table.cell class="text-center font-mono text-xs">
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $opn->total_batch_count }}</span>
                            <span class="text-zinc-400 text-[10px]">batch</span>
                        </flux:table.cell>

                        {{-- Temuan Selisih --}}
                        <flux:table.cell class="text-center">
                            @if ($hasSelisih)
                                <div class="flex flex-col items-center gap-1">
                                    <flux:badge size="xs" color="rose">
                                        {{ $opn->selisih_count }} Batch Berselisih
                                    </flux:badge>
                                    <div class="text-[10px] font-mono text-zinc-500">
                                        @if ($opn->minus_count > 0)
                                            <span class="text-rose-600 dark:text-rose-400 font-bold">-{{ $opn->minus_count }}</span>
                                        @endif
                                        @if ($opn->plus_count > 0)
                                            <span class="text-blue-600 dark:text-blue-400 font-bold ml-1">+{{ $opn->plus_count }}</span>
                                        @endif
                                    </div>
                                </div>
                            @elseif ($opn->status === 'BERJALAN')
                                <flux:badge size="xs" color="blue">Sedang Dihitung</flux:badge>
                            @else
                                <flux:badge size="xs" color="emerald">Klop Sempurna (0)</flux:badge>
                            @endif
                        </flux:table.cell>

                        {{-- Status Sesi --}}
                        <flux:table.cell class="text-center">
                            <flux:badge size="sm" :color="$statusColor">
                                {{ str_replace('_', ' ', $opn->status) }}
                            </flux:badge>
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button
                                    wire:click="showDetail({{ $opn->id }})"
                                    size="xs"
                                    variant="ghost"
                                    icon="eye"
                                    title="Lihat Rincian Selisih"
                                >
                                    Rincian
                                </flux:button>

                                @if ($hasSelisih && auth()->user()?->can('create_audit_manajemen::temuan::capa'))
                                    <flux:button
                                        wire:click="openTerbitkanCapa({{ $opn->id }})"
                                        size="xs"
                                        variant="danger"
                                        icon="clipboard-document-check"
                                        title="Terbitkan Temuan Audit CAPA"
                                    >
                                        CAPA
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada sesi stock opname yang terdaftar atau sesuai kriteria filter." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Footer Paginasi Kartu --}}
        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal 1: Rincian Selisih Hitung Fisik Opname --}}
    <flux:modal wire:model="showDetailModal" class="w-full max-w-5xl">
        @if ($selectedOpname)
            <div class="space-y-4">
                <div class="flex items-start justify-between border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <div>
                        <flux:heading size="lg">Rincian Hitung Fisik Opname: {{ $selectedOpname->kode }}</flux:heading>
                        <flux:subheading>Fasilitas: {{ $selectedOpname->gudang?->nama }} &bull; Tanggal: {{ $selectedOpname->tanggal_opname?->format('d/m/Y') }} &bull; Pelaksana: {{ $selectedOpname->pembuat?->nama ?? '-' }}</flux:subheading>
                    </div>
                </div>

                {{-- Tabel Daftar Batch Hasil Hitung Fisik --}}
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-xl max-h-[55vh] overflow-y-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700 sticky top-0">
                            <tr>
                                <th class="p-2.5">Nama Komoditas</th>
                                <th class="p-2.5">No. Batch</th>
                                <th class="p-2.5">Rak Simpan</th>
                                <th class="p-2.5 text-right">Saldo Sistem</th>
                                <th class="p-2.5 text-right">Hitung Fisik</th>
                                <th class="p-2.5 text-center">Selisih</th>
                                <th class="p-2.5">Catatan Petugas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($selectedOpname->detail as $det)
                                @php
                                    $isSelisih = $det->selisih != 0;
                                    $isMinus   = $det->selisih < 0;
                                @endphp
                                <tr class="{{ $isSelisih ? ($isMinus ? 'bg-rose-50/40 dark:bg-rose-950/20' : 'bg-blue-50/40 dark:bg-blue-950/20') : '' }}">
                                    <td class="p-2.5 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $det->barang?->nama ?? '-' }}
                                    </td>
                                    <td class="p-2.5 font-mono text-zinc-600 dark:text-zinc-400">
                                        {{ $det->stokBatch?->nomor_batch ?? '-' }}
                                    </td>
                                    <td class="p-2.5 text-zinc-600 dark:text-zinc-400">
                                        {{ $det->stokBatch?->lokasiGudang?->nama ?? 'Gudang' }}
                                    </td>
                                    <td class="p-2.5 text-right font-mono tabular-nums text-zinc-600 dark:text-zinc-400">
                                        {{ number_format($det->jumlah_sistem, 0, ',', '.') }} {{ $det->barang?->satuan?->simbol ?? 'unit' }}
                                    </td>
                                    <td class="p-2.5 text-right font-mono tabular-nums font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($det->jumlah_fisik, 0, ',', '.') }} {{ $det->barang?->satuan?->simbol ?? 'unit' }}
                                    </td>
                                    <td class="p-2.5 text-center font-mono tabular-nums font-bold">
                                        @if ($det->selisih === 0)
                                            <span class="text-emerald-600 dark:text-emerald-400">0</span>
                                        @elseif ($isMinus)
                                            <span class="text-rose-600 dark:text-rose-400">{{ $det->selisih }}</span>
                                        @else
                                            <span class="text-blue-600 dark:text-blue-400">+{{ $det->selisih }}</span>
                                        @endif
                                    </td>
                                    <td class="p-2.5 text-zinc-600 dark:text-zinc-400">
                                        {{ $det->catatan ?: '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-zinc-400">Belum ada rincian batch pada sesi ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-2">
                    <flux:button variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal 2: Formulir Terbitkan Temuan CAPA dari Hasil Selisih Opname --}}
    <flux:modal wire:model="showCapaModal" class="md:w-[38rem]">
        @if ($selectedOpname)
            <form wire:submit="saveCapa" class="space-y-4">
                <div>
                    <flux:heading size="lg">Terbitkan Temuan Audit CAPA</flux:heading>
                    <flux:subheading>Eskalasi temuan selisih fisik barang di gudang ke dalam register CAPA resmi.</flux:subheading>
                </div>

                <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/60 text-xs space-y-1.5">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Ref Sesi Opname:</span>
                        <span class="font-mono font-bold text-zinc-900 dark:text-zinc-100">{{ $selectedOpname->kode }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Gudang Terperiksa:</span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $selectedOpname->gudang?->nama }}</span>
                    </div>
                </div>

                <flux:input
                    wire:model="objek_audit"
                    label="Objek Pemeriksaan Audit"
                    required
                />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Klasifikasi Risiko <span class="text-red-500">*</span>
                        </label>
                        <select
                            wire:model="tingkat_risiko"
                            required
                            class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="Mayor">Mayor (Kerugian Signifikan / Rawan Fraud)</option>
                            <option value="Moderat">Moderat (Ketidakpatuhan SOP Administrasi)</option>
                            <option value="Minor">Minor (Perbedaan Pencatatan Ringan)</option>
                        </select>
                    </div>

                    <flux:field>
                        <flux:label>Batas Waktu (Due Date CAPA) <span class="text-red-500">*</span></flux:label>
                        <flux:input type="date" wire:model="due_date_capa" required />
                        <flux:error name="due_date_capa" />
                    </flux:field>
                </div>

                <flux:textarea
                    wire:model="ringkasan"
                    label="Uraian Temuan Ketidaksesuaian Selisih"
                    rows="3"
                    required
                />

                <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button type="button" variant="ghost" wire:click="$set('showCapaModal', false)">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveCapa">Daftarkan ke CAPA</span>
                        <span wire:loading wire:target="saveCapa">Memproses...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
