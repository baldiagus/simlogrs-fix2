<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Digital Forensic Audit Trail</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Rekaman audit forensik digital perubahan data transaksional sistem (snapshot diff sebelum vs sesudah dalam format JSONB).</flux:subheading>
            </div>

            @if ($filterAksi || $filterEntitas || $filterPenggunaId || $tanggalMulai || $tanggalSelesai || $search)
                <flux:button wire:click="resetFilters" size="xs" variant="ghost" icon="x-mark">
                    Reset Filter
                </flux:button>
            @endif
        </div>

        {{-- Navigasi Sub-Tab Hub Pengawasan Kepatuhan --}}
        <x-tabs.audit-kepatuhan active="trail" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Toolbar Filter & Pencarian --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 pt-1">
            <div>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari entitas, IP, pengguna..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div>
                <select wire:model.live="filterAksi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Aksi</option>
                    <option value="TAMBAH">TAMBAH (Created)</option>
                    <option value="UBAH">UBAH (Updated)</option>
                    <option value="HAPUS">HAPUS (Deleted)</option>
                    <option value="APPROVE">APPROVE</option>
                    <option value="REJECT">REJECT</option>
                    <option value="ADJUSTMENT">ADJUSTMENT</option>
                    <option value="RBAC_MUTATION">RBAC_MUTATION (Wewenang / Peran)</option>
                    <option value="RESET_PASSWORD">RESET_PASSWORD</option>
                    <option value="STATUS_MUTATION">STATUS_MUTATION (Status Akun)</option>
                    <option value="GANTI_PASSWORD">GANTI_PASSWORD</option>
                </select>
            </div>

            <div>
                <select wire:model.live="filterEntitas" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Model Entitas</option>
                    @foreach ($daftarEntitas as $ent)
                        <option value="{{ $ent }}">{{ $ent }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <select wire:model.live="filterPenggunaId" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Aktor Pengguna</option>
                    @foreach ($daftarPengguna as $p)
                        <option value="{{ $p->id }}">{{ $p->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Baris Filter Tanggal --}}
        <div class="flex flex-wrap items-center gap-3 pt-1 text-xs text-zinc-600 dark:text-zinc-400">
            <span class="font-medium">Rentang Tanggal Log:</span>
            <div class="w-36">
                <input
                    type="date"
                    wire:model.live="tanggalMulai"
                    class="w-full h-8 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2 py-1 text-zinc-900 dark:text-zinc-100"
                />
            </div>
            <span>s/d</span>
            <div class="w-36">
                <input
                    type="date"
                    wire:model.live="tanggalSelesai"
                    class="w-full h-8 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2 py-1 text-zinc-900 dark:text-zinc-100"
                />
            </div>
        </div>

        {{-- Tabel Log Audit --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-36">Waktu Kejadian</flux:table.column>
                <flux:table.column>Aktor Pengguna</flux:table.column>
                <flux:table.column>Model Entitas</flux:table.column>
                <flux:table.column class="w-24">ID Ref</flux:table.column>
                <flux:table.column class="text-center w-28">Aksi</flux:table.column>
                <flux:table.column>IP & Perangkat</flux:table.column>
                <flux:table.column class="text-right w-20">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    @php
                        $aksiColor = match($item->aksi) {
                            'TAMBAH'          => 'emerald',
                            'UBAH'            => 'blue',
                            'HAPUS'           => 'rose',
                            'APPROVE'         => 'emerald',
                            'REJECT'          => 'rose',
                            'ADJUSTMENT'      => 'amber',
                            'RBAC_MUTATION'   => 'indigo',
                            'RESET_PASSWORD'  => 'amber',
                            'STATUS_MUTATION' => 'orange',
                            'GANTI_PASSWORD'  => 'emerald',
                            default           => 'zinc',
                        };
                    @endphp
                    <flux:table.row :key="$item->id">
                        {{-- Waktu --}}
                        <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                            <div>{{ $item->dibuat_pada?->format('d/m/Y') }}</div>
                            <div class="text-[10px] text-zinc-500 font-mono">{{ $item->dibuat_pada?->format('H:i:s') }}</div>
                        </flux:table.cell>

                        {{-- Pengguna --}}
                        <flux:table.cell>
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $item->pengguna?->nama ?? 'Sistem / CLI' }}
                            </div>
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400">
                                {{ $item->peran?->name ?? 'System Process' }}
                            </div>
                        </flux:table.cell>

                        {{-- Entitas --}}
                        <flux:table.cell class="font-mono text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                            {{ $item->referensi_jenis }}
                        </flux:table.cell>

                        {{-- ID --}}
                        <flux:table.cell class="font-mono text-xs text-zinc-600 dark:text-zinc-400">
                            #{{ $item->referensi_id }}
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-center">
                            <flux:badge size="sm" :color="$aksiColor">
                                {{ $item->aksi }}
                            </flux:badge>
                        </flux:table.cell>

                        {{-- IP & Device --}}
                        <flux:table.cell>
                            <div class="font-mono text-xs text-zinc-700 dark:text-zinc-300">
                                {{ $item->ip_address ?: '-' }}
                            </div>
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 max-w-xs truncate" title="{{ $item->device }}">
                                {{ $item->device ?: '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="document-magnifying-glass">
                                        Inspeksi Diff (JSON)
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-ui.empty-state message="Belum ada rekaman audit trail digital yang tercatat." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-ui.table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Inspeksi Diff Perubahan Data (Forensik) --}}
    <flux:modal name="modal-detail-audit" wire:model="showDetailModal" class="w-full max-w-[95vw] lg:max-w-5xl">
        @if ($selectedLog)
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Forensik Audit Log #{{ $selectedLog->id }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Model: <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedLog->referensi_jenis }} #{{ $selectedLog->referensi_id }}</span> &bull;
                            Aktor: <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedLog->pengguna?->nama ?? 'Sistem' }}</span>
                        </flux:subheading>
                    </div>
                    @php
                        $mColor = match($selectedLog->aksi) {
                            'TAMBAH'          => 'emerald',
                            'UBAH'            => 'blue',
                            'HAPUS'           => 'rose',
                            'APPROVE'         => 'emerald',
                            'REJECT'          => 'rose',
                            'ADJUSTMENT'      => 'amber',
                            'RBAC_MUTATION'   => 'indigo',
                            'RESET_PASSWORD'  => 'amber',
                            'STATUS_MUTATION' => 'orange',
                            'GANTI_PASSWORD'  => 'emerald',
                            default           => 'zinc',
                        };
                    @endphp
                    <flux:badge size="sm" :color="$mColor">{{ $selectedLog->aksi }}</flux:badge>
                </div>

                {{-- Metadata Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Waktu Transaksi</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedLog->dibuat_pada?->format('d F Y H:i:s') }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Alamat IP</div>
                        <div class="font-mono font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedLog->ip_address ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Peran Aktor</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedLog->peran?->name ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">User Agent / Perangkat</div>
                        <div class="font-mono text-[11px] text-zinc-700 dark:text-zinc-300 mt-0.5 truncate" title="{{ $selectedLog->device }}">
                            {{ $selectedLog->device ?: '-' }}
                        </div>
                    </div>
                </div>

                {{-- Tabel Perbandingan Diff (Side-by-Side) --}}
                @php
                    $keysBefore = array_keys($selectedLog->data_sebelum ?? []);
                    $keysAfter = array_keys($selectedLog->data_sesudah ?? []);
                    $allKeys = array_unique(array_merge($keysBefore, $keysAfter));
                    sort($allKeys);
                @endphp

                <div class="space-y-2">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Perbandingan Nilai Atribut (Diff Snapshot)
                    </div>

                    @if (empty($allKeys))
                        <div class="p-4 text-center text-xs text-zinc-500 italic border rounded-xl">
                            Tidak ada atribut data yang tercatat dalam payload snapshot.
                        </div>
                    @else
                        <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-xl max-h-[50vh] overflow-y-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700 sticky top-0">
                                    <tr>
                                        <th class="p-2.5 w-44">Nama Atribut</th>
                                        <th class="p-2.5 w-1/2">Nilai Sebelum (Original)</th>
                                        <th class="p-2.5 w-1/2">Nilai Sesudah (Baru)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 bg-white dark:bg-zinc-900 font-mono text-[11px]">
                                    @foreach ($allKeys as $key)
                                        @php
                                            $valBefore = $selectedLog->data_sebelum[$key] ?? null;
                                            $valAfter = $selectedLog->data_sesudah[$key] ?? null;
                                            $strBefore = is_array($valBefore) ? json_encode($valBefore) : (is_null($valBefore) ? '-' : (string) $valBefore);
                                            $strAfter = is_array($valAfter) ? json_encode($valAfter) : (is_null($valAfter) ? '-' : (string) $valAfter);
                                            $isChanged = ($strBefore !== $strAfter);
                                        @endphp
                                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 {{ $isChanged ? 'bg-amber-50/30 dark:bg-amber-950/10' : '' }}">
                                            <td class="p-2.5 font-sans font-medium text-zinc-800 dark:text-zinc-200">
                                                {{ $key }}
                                                @if ($isChanged)
                                                    <span class="inline-block size-1.5 rounded-full bg-amber-500 ml-1" title="Atribut Berubah"></span>
                                                @endif
                                            </td>
                                            <td class="p-2.5 {{ $isChanged && $strBefore !== '-' ? 'bg-rose-50/80 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300' : 'text-zinc-600 dark:text-zinc-400' }}">
                                                <div class="break-all whitespace-pre-wrap">{{ $strBefore }}</div>
                                            </td>
                                            <td class="p-2.5 {{ $isChanged && $strAfter !== '-' ? 'bg-emerald-50/80 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 font-semibold' : 'text-zinc-600 dark:text-zinc-400' }}">
                                                <div class="break-all whitespace-pre-wrap">{{ $strAfter }}</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

            </div>
        @endif
    </flux:modal>
</div>
