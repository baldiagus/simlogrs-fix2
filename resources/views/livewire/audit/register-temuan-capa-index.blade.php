<div class="space-y-6">
    {{-- Header Halaman & Aksi Utama --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1" class="font-bold tracking-tight">Register Temuan Audit & Tindakan Korektif (CAPA)</flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400">Sistem terpadu pelaporan ketidaksesuaian audit operasional, monitoring bukti perbaikan, dan evaluasi closing.</flux:subheading>
        </div>

        @if ($canCreate)
            <flux:button wire:click="openCreate" variant="primary" icon="plus" wire:loading.attr="disabled">
                Registrasi Temuan Baru
            </flux:button>
        @endif
    </div>

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
                        <option value="{{ \App\Enums\Audit\StatusCapa::InProgress->value }}">In Progress (Tindakan Unit)</option>
                        <option value="{{ \App\Enums\Audit\StatusCapa::InReview->value }}">In Review (Evaluasi SPI)</option>
                        <option value="{{ \App\Enums\Audit\StatusCapa::Closed->value }}">Closed (Selesai Resmi)</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Tabel Temuan --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-32">Kode Temuan</flux:table.column>
                <flux:table.column class="w-44">Objek Pemeriksaan</flux:table.column>
                <flux:table.column class="w-64">Ringkasan Ketidaksesuaian</flux:table.column>
                <flux:table.column class="text-center w-24">Risiko</flux:table.column>
                <flux:table.column class="w-28">Jatuh Tempo</flux:table.column>
                <flux:table.column class="text-center w-28">Status CAPA</flux:table.column>
                <flux:table.column class="text-right w-36">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    @php
                        $isOverdue = ($item->status_capa !== \App\Enums\Audit\StatusCapa::Closed->value && $item->due_date_capa && now()->startOfDay()->gt($item->due_date_capa));
                        $risikoColor = match($item->tingkat_risiko) {
                            'Mayor'   => 'rose',
                            'Moderat' => 'amber',
                            'Minor'   => 'blue',
                            default   => 'zinc',
                        };
                        $statusColor = match($item->status_capa) {
                            \App\Enums\Audit\StatusCapa::InProgress->value => 'amber',
                            \App\Enums\Audit\StatusCapa::InReview->value   => 'blue',
                            \App\Enums\Audit\StatusCapa::Closed->value      => 'emerald',
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
                        <flux:table.cell style="white-space: normal;">
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $item->objek_audit }}
                            </div>
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Auditor: {{ $item->auditor?->nama ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Ringkasan --}}
                        <flux:table.cell style="white-space: normal;">
                            <div class="text-xs text-zinc-700 dark:text-zinc-300 line-clamp-2" title="{{ $item->ringkasan }}">
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
                        <flux:table.cell class="text-right w-16">
                            @php
                                $currentUser = auth()->user();
                                $canManageThis = $this->canUserManageTindakLanjut($currentUser, $item);
                            @endphp
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    @if ($item->status_capa === \App\Enums\Audit\StatusCapa::Closed->value && $canEvaluate)
                                        <flux:menu.item wire:click="openEvaluasiClosing({{ $item->id }})" icon="eye">
                                            Rincian Evaluasi Closing
                                        </flux:menu.item>
                                    @endif
                                    @if ($item->status_capa === \App\Enums\Audit\StatusCapa::InProgress->value)
                                        @if ($canManageThis)
                                            <flux:menu.item wire:click="openTindakLanjut({{ $item->id }})" icon="arrow-up-tray" class="text-blue-600 dark:text-blue-400">
                                                Unggah Tindak Lanjut
                                            </flux:menu.item>
                                        @else
                                            <div class="px-2.5 py-1.5 text-[11px] text-zinc-400 italic">
                                                Menunggu Respon Auditee
                                            </div>
                                        @endif
                                    @endif
                                    @if ($item->status_capa === \App\Enums\Audit\StatusCapa::InReview->value && $canEvaluate)
                                        <flux:menu.item wire:click="openEvaluasiClosing({{ $item->id }})" icon="check-badge" class="text-emerald-600 dark:text-emerald-400">
                                            Evaluasi &amp; Closing SPI
                                        </flux:menu.item>
                                    @endif
                                    @if ($item->status_capa === \App\Enums\Audit\StatusCapa::InReview->value && ! $canEvaluate && $canManageThis)
                                        <flux:menu.item wire:click="openTindakLanjut({{ $item->id }})" icon="eye">
                                            Lihat Tindak Lanjut
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-ui.empty-state message="Belum ada temuan audit atau ketidaksesuaian CAPA yang terdaftar." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-ui.table-pagination :paginator="$items" />
    </flux:card>

    @include('livewire.audit.partials.register-temuan.modal-buat-temuan')

    @include('livewire.audit.partials.register-temuan.modal-tindak-lanjut')

    @include('livewire.audit.partials.register-temuan.modal-evaluasi')
</div>
