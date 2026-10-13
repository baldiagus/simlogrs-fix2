            {{-- Tabel Aset & Inventaris Ruangan --}}
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode_aset'" :direction="$sortDirection" wire:click="sort('kode_aset')" class="w-36">Kode Aset</flux:table.column>
                    <flux:table.column>Nama Sarana & Kategori</flux:table.column>
                    <flux:table.column class="w-36">Nomor Seri</flux:table.column>
                    @if ($canPickUnit)
                        <flux:table.column>Unit / Ruangan</flux:table.column>
                    @endif
                    <flux:table.column class="text-center w-32">Kondisi</flux:table.column>
                    <flux:table.column class="text-center w-32">Status</flux:table.column>
                    <flux:table.column class="text-right w-36">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($daftarAset as $ast)
                        <flux:table.row :key="$ast->id">
                            {{-- Kode Aset --}}
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $ast->kode_aset }}
                            </flux:table.cell>

                            {{-- Nama & Kategori --}}
                            <flux:table.cell>
                                <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $ast->barang?->nama ?? 'Aset Fisik' }}
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Kategori: {{ $ast->barang?->kategori?->nama ?? 'Umum' }}
                                </div>
                            </flux:table.cell>

                            {{-- Nomor Seri --}}
                            <flux:table.cell class="text-xs font-mono text-zinc-600 dark:text-zinc-300">
                                {{ $ast->nomor_seri ?? '-' }}
                            </flux:table.cell>

                            {{-- Unit (Jika Admin) --}}
                            @if ($canPickUnit)
                                <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-300">
                                    {{ $ast->unit?->nama ?? '-' }}
                                </flux:table.cell>
                            @endif

                            {{-- Kondisi Fisik --}}
                            <flux:table.cell class="text-center">
                                @if ($ast->kondisi === 'BAIK')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                        BAIK
                                    </span>
                                @elseif ($ast->kondisi === 'RUSAK_RINGAN')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-800">
                                        RUSAK RINGAN
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 dark:border-rose-800">
                                        {{ $ast->kondisi }}
                                    </span>
                                @endif
                            </flux:table.cell>

                            {{-- Status Operasional --}}
                            <flux:table.cell class="text-center">
                                @if ($ast->status === 'DIGUNAKAN')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-300 dark:border-blue-800">
                                        DIGUNAKAN
                                    </span>
                                @elseif ($ast->status === 'TERSEDIA')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                        TERSEDIA
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-300 dark:border-purple-800">
                                        {{ $ast->status }}
                                    </span>
                                @endif
                            </flux:table.cell>

                            {{-- Aksi --}}
                            <flux:table.cell class="text-right w-16">
                                @php
                                    $user = auth()->user();
                                    $ownsThisAset = $isGlobalAdmin
                                        || ($user?->unit_id && $ast->unit_id && (int) $user->unit_id === (int) $ast->unit_id)
                                        || ($user?->gudang_id && $ast->gudang_id && (int) $user->gudang_id === (int) $ast->gudang_id);
                                    $canUbahStatus = $canUpdateStok && $ownsThisAset;
                                    $canLaporThis = $canLaporServis && $ownsThisAset;
                                @endphp
                                @if ($ast->status === 'DALAM_MAINTENANCE')
                                    <flux:badge size="xs" color="amber" class="font-medium" title="Aset sedang dalam penanganan perbaikan oleh teknisi IPSRS">
                                        Dalam Servis IPSRS
                                    </flux:badge>
                                @else
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                        <flux:menu class="min-w-44">
                                            @if ($canUbahStatus && ! in_array($ast->status, ['DIPINJAM', 'DALAM_MAINTENANCE', 'DIHAPUSKAN']))
                                                <flux:menu.item wire:click="openUbahStatusAset({{ $ast->id }})" icon="pencil-square">
                                                    Ubah Status Operasional
                                                </flux:menu.item>
                                            @endif
                                            @if ($canLaporThis && ! in_array($ast->status, ['DIPINJAM', 'DALAM_MAINTENANCE', 'DIHAPUSKAN']))
                                                <flux:menu.item wire:click="openLaporServis({{ $ast->id }})" icon="wrench">
                                                    Lapor Servis ke IPSRS
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-ui.empty-state message="Belum ada aset fisik atau sarana inventaris yang terdaftar pada unit ruangan ini." cols="7" />
                    @endforelse
                </flux:table.rows>
            </flux:table>

            {{-- Pagination Aset --}}
            <x-ui.table-pagination :paginator="$daftarAset" />
