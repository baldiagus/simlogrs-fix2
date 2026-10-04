<div class="space-y-6">
    {{-- Kartu Metrik Ringkasan CAPA --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between">
                <flux:text size="xs" class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Total Temuan Audit</flux:text>
                <flux:badge size="sm" color="blue" inset="top bottom">SPI</flux:badge>
            </div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 font-mono tabular-nums">
                {{ $totalTemuan }} <span class="text-xs font-sans font-normal text-zinc-500">kasus</span>
            </div>
            <flux:text size="xs" class="text-zinc-500 dark:text-zinc-400">Tercatat di register CAPA</flux:text>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between">
                <flux:text size="xs" class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Tingkat Penyelesaian</flux:text>
                <flux:badge size="sm" color="emerald" inset="top bottom">Closing Rate</flux:badge>
            </div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono tabular-nums">
                {{ $closingRate }}%
            </div>
            <flux:text size="xs" class="text-zinc-500 dark:text-zinc-400">{{ $totalClosed }} dari {{ $totalTemuan }} temuan telah closed</flux:text>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between">
                <flux:text size="xs" class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Risiko Mayor Terbuka</flux:text>
                <flux:badge size="sm" color="rose" inset="top bottom">Prioritas 1</flux:badge>
            </div>
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-400 font-mono tabular-nums">
                {{ $totalMayorOpen }} <span class="text-xs font-sans font-normal text-zinc-500">temuan</span>
            </div>
            <flux:text size="xs" class="text-zinc-500 dark:text-zinc-400">Wajib closing dalam 14 hari</flux:text>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between">
                <flux:text size="xs" class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Lewat Jatuh Tempo</flux:text>
                <flux:badge size="sm" color="amber" inset="top bottom">Overdue</flux:badge>
            </div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 font-mono tabular-nums">
                {{ $totalOverdue }} <span class="text-xs font-sans font-normal text-zinc-500">kasus</span>
            </div>
            <flux:text size="xs" class="text-zinc-500 dark:text-zinc-400">Melewati batas waktu tindakan</flux:text>
        </flux:card>
    </div>

    {{-- Tabel Register Temuan CAPA --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Register Temuan Audit & Tindakan Korektif (CAPA)</flux:heading>
                <flux:subheading>Sistem terpadu pelaporan ketidaksesuaian audit operasional, monitoring bukti perbaikan, dan evaluasi closing.</flux:subheading>
            </div>

            @if ($canCreate)
                <flux:button wire:click="openCreate" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Registrasi Temuan Baru
                </flux:button>
            @endif
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode temuan, objek audit, ringkasan..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="flex items-center gap-2.5">
                <div class="w-44">
                    <select wire:model.live="filterRisiko" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Tingkat Risiko</option>
                        <option value="Mayor">Mayor (Risiko Tinggi)</option>
                        <option value="Moderat">Moderat (Menengah)</option>
                        <option value="Minor">Minor (Rendah)</option>
                    </select>
                </div>

                <div class="w-44">
                    <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Status CAPA</option>
                        <option value="In Progress">In Progress (Tindakan Unit)</option>
                        <option value="In Review">In Review (Evaluasi SPI)</option>
                        <option value="Closed">Closed (Selesai Resmi)</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Tabel Temuan --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-36">Kode Temuan</flux:table.column>
                <flux:table.column>Objek Pemeriksaan</flux:table.column>
                <flux:table.column>Ringkasan Ketidaksesuaian</flux:table.column>
                <flux:table.column class="text-center w-28">Risiko</flux:table.column>
                <flux:table.column class="w-32">Jatuh Tempo</flux:table.column>
                <flux:table.column class="text-center w-32">Status CAPA</flux:table.column>
                <flux:table.column class="text-right w-44">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    @php
                        $isOverdue = ($item->status_capa !== 'Closed' && $item->due_date_capa && now()->startOfDay()->gt($item->due_date_capa));
                        $risikoColor = match($item->tingkat_risiko) {
                            'Mayor'   => 'rose',
                            'Moderat' => 'amber',
                            'Minor'   => 'blue',
                            default   => 'zinc',
                        };
                        $statusColor = match($item->status_capa) {
                            'In Progress' => 'amber',
                            'In Review'   => 'blue',
                            'Closed'      => 'emerald',
                            default       => 'zinc',
                        };
                    @endphp
                    <flux:table.row :key="$item->id">
                        {{-- Kode --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            <div>{{ $item->kode }}</div>
                            <div class="text-[10px] text-zinc-500 font-sans mt-0.5">
                                Tgl: {{ $item->tanggal_audit?->format('d/m/Y') }}
                            </div>
                        </flux:table.cell>

                        {{-- Objek Audit --}}
                        <flux:table.cell>
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $item->objek_audit }}
                            </div>
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Auditor: {{ $item->auditor?->nama ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Ringkasan --}}
                        <flux:table.cell>
                            <div class="text-xs text-zinc-700 dark:text-zinc-300 max-w-xs line-clamp-2" title="{{ $item->ringkasan }}">
                                {{ $item->ringkasan }}
                            </div>
                            @if (!empty($item->bukti_perbaikan) && is_array($item->bukti_perbaikan) && count($item->bukti_perbaikan) > 0)
                                <div class="text-[10px] text-blue-600 dark:text-blue-400 font-medium mt-0.5 flex items-center gap-1">
                                    <flux:icon name="paper-clip" class="size-3" />
                                    <span>{{ count($item->bukti_perbaikan) }} berkas bukti terunggah</span>
                                </div>
                            @endif
                        </flux:table.cell>

                        {{-- Tingkat Risiko --}}
                        <flux:table.cell class="text-center">
                            <flux:badge size="sm" :color="$risikoColor">
                                {{ $item->tingkat_risiko }}
                            </flux:badge>
                        </flux:table.cell>

                        {{-- Jatuh Tempo --}}
                        <flux:table.cell>
                            <div class="space-y-0.5">
                                <div class="text-xs tabular-nums {{ $isOverdue ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                    {{ $item->due_date_capa?->format('d/m/Y') ?? '-' }}
                                </div>
                                @if ($isOverdue)
                                    <span class="inline-block text-[10px] font-bold text-rose-600 dark:text-rose-400">
                                        LEWAT TEMPO
                                    </span>
                                @endif
                            </div>
                        </flux:table.cell>

                        {{-- Status CAPA --}}
                        <flux:table.cell class="text-center">
                            <flux:badge size="sm" :color="$statusColor">
                                {{ $item->status_capa }}
                            </flux:badge>
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($item->status_capa === 'In Progress')
                                    <flux:button
                                        wire:click="openTindakLanjut({{ $item->id }})"
                                        size="xs"
                                        variant="outline"
                                        icon="arrow-up-tray"
                                        title="Unggah Tindak Lanjut Perbaikan"
                                    >
                                        Tindak Lanjut
                                    </flux:button>
                                @endif

                                @if ($item->status_capa === 'In Review' && $canEvaluate)
                                    <flux:button
                                        wire:click="openEvaluasiClosing({{ $item->id }})"
                                        size="xs"
                                        variant="primary"
                                        icon="check-circle"
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white"
                                        title="Evaluasi & Closing Temuan"
                                    >
                                        Evaluasi SPI
                                    </flux:button>
                                @endif

                                @if ($item->status_capa === 'Closed')
                                    <flux:button
                                        wire:click="openEvaluasiClosing({{ $item->id }})"
                                        size="xs"
                                        variant="ghost"
                                        icon="eye"
                                        title="Lihat Evaluasi Closing"
                                    />
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada temuan audit atau ketidaksesuaian CAPA yang terdaftar." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal 1: Registrasi Temuan Audit Baru --}}
    <flux:modal name="modal-buat-temuan" wire:model="showCreateModal" class="md:w-[42rem]">
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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-searchable-select
                    model="unit_id"
                    label="Unit Terperiksa (Opsional)"
                    placeholder="-- Pilih Unit Terperiksa --"
                    :options="$daftarUnit"
                />

                <x-searchable-select
                    model="gudang_id"
                    label="Gudang Terperiksa (Opsional)"
                    placeholder="-- Pilih Fasilitas Gudang --"
                    :options="$daftarGudang"
                />
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
                            'In Progress' => 'amber',
                            'In Review'   => 'blue',
                            'Closed'      => 'emerald',
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
                        <option value="Closed">Closed (Tindakan perbaikan terbukti valid dan tuntas)</option>
                        <option value="In Progress">Kembalikan ke In Progress (Bukti belum memadai / perlu revisi)</option>
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
</div>
