<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight">Evaluasi Kinerja Rekanan & Pemasok</flux:heading>
            <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                Pusat pengawasan profil vendor, akumulasi realisasi nilai belanja, dan rekam jejak keandalan pasokan logistik.
            </flux:subheading>
        </div>
    </div>

    {{-- Navigasi Sub-Tab Hub Evaluasi --}}
    <x-tabs.pengadaan-evaluasi active="rekanan" />

    {{-- 3 Kartu Ringkasan Metrik Rekanan --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {{-- Total Rekanan --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Rekanan Vendor</span>
                <flux:icon name="building-office" class="size-4 text-blue-600" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100">
                {{ $totalRekanan }}
            </div>
            <div class="text-[11px] text-zinc-500">Pemasok terdaftar dalam database</div>
        </flux:card>

        {{-- Rekanan Aktif --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Mitra Aktif Beroperasi</span>
                <flux:icon name="check-badge" class="size-4 text-emerald-600" />
            </div>
            <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                {{ $rekananAktif }}
            </div>
            <div class="text-[11px] text-zinc-500">Memenuhi syarat pengadaan</div>
        </flux:card>

        {{-- Akumulasi Belanja --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Akumulasi Belanja PO</span>
                <flux:icon name="banknotes" class="size-4 text-indigo-600" />
            </div>
            <div class="text-xl font-bold font-mono text-zinc-900 dark:text-zinc-100">
                Rp {{ number_format($totalBelanjaVendor, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-zinc-500">Total nilai pesanan disetujui</div>
        </flux:card>
    </div>

    {{-- Tabel Evaluasi Vendor --}}
    <flux:card class="space-y-4 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <flux:heading size="lg">Daftar Rekanan & Rapor Transaksi</flux:heading>
                <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400">
                    Daftar seluruh perusahaan rekanan penyedia komoditas logistik rumah sakit.
                </flux:subheading>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select
                    wire:model.live="filterKategori"
                    class="h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                    <option value="">Semua Status</option>
                    <option value="AKTIF">Hanya Aktif</option>
                    <option value="NONAKTIF">Nonaktif</option>
                </select>

                <div class="w-full sm:w-60">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari nama, kode, kota..."
                        class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="py-2.5 px-3 font-semibold">Kode</th>
                        <th class="py-2.5 px-3 font-semibold">Nama Perusahaan / Vendor</th>
                        <th class="py-2.5 px-3 font-semibold">Kontak PIC</th>
                        <th class="py-2.5 px-3 font-semibold">Kota</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Total PO</th>
                        <th class="py-2.5 px-3 font-semibold text-right">Total Belanja (Rp)</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Status</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($items as $vendor)
                        @php
                            $totalPo = $vendor->pesanan_pembelian_count ?? 0;
                            $totalNilai = (float) ($vendor->pesanan_pembelian_sum_total_biaya ?? 0);
                        @endphp
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30" wire:key="vendor-{{ $vendor->id }}">
                            <td class="py-2.5 px-3 font-mono font-semibold text-zinc-800 dark:text-zinc-200 whitespace-nowrap">
                                {{ $vendor->kode }}
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $vendor->nama }}</div>
                                @if ($vendor->npwp)
                                    <div class="text-[10px] text-zinc-400 font-mono">NPWP: {{ $vendor->npwp }}</div>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-zinc-600 dark:text-zinc-400">
                                <div>{{ $vendor->nama_kontak ?: '-' }}</div>
                                <div class="text-[10px] text-zinc-400 font-mono">{{ $vendor->no_telepon ?: $vendor->email ?: '' }}</div>
                            </td>
                            <td class="py-2.5 px-3 text-zinc-700 dark:text-zinc-300">
                                {{ $vendor->kota ?: '-' }}
                            </td>
                            <td class="py-2.5 px-3 font-mono text-center font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $totalPo }} PO
                            </td>
                            <td class="py-2.5 px-3 font-mono text-right font-semibold {{ $totalNilai > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500' }} tabular-nums">
                                Rp {{ number_format($totalNilai, 0, ',', '.') }}
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <flux:badge size="sm" :color="$vendor->status === 'AKTIF' ? 'emerald' : 'zinc'">
                                    {{ $vendor->status }}
                                </flux:badge>
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <flux:button
                                    wire:click="openDetail({{ $vendor->id }})"
                                    size="xs"
                                    variant="ghost"
                                    icon="eye"
                                    title="Lihat Riwayat & Profil Vendor"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-zinc-500 text-xs">
                                Belum ada rekanan atau pemasok vendor yang terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-ui.table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Detail Profil & Riwayat Transaksi Vendor --}}
    <flux:modal name="modal-detail-rekanan" wire:model="showDetailModal" class="md:w-[44rem]">
        @if ($selectedPemasok)
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="text-[11px] font-mono text-blue-600 dark:text-blue-400 font-bold uppercase">
                            {{ $selectedPemasok->kode }}
                        </div>
                        <flux:heading size="lg" class="mt-0.5">{{ $selectedPemasok->nama }}</flux:heading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <flux:badge size="sm" :color="$selectedPemasok->status === 'AKTIF' ? 'emerald' : 'zinc'">
                            {{ $selectedPemasok->status }}
                        </flux:badge>
                    </div>
                </div>

                {{-- Detail Informasi Kontak & Legalitas --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 text-[11px]">NPWP Perusahaan:</span>
                        <div class="font-mono font-medium text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedPemasok->npwp ?: '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 text-[11px]">Person in Charge (PIC):</span>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedPemasok->nama_kontak ?: '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 text-[11px]">Nomor Telepon:</span>
                        <div class="font-mono text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedPemasok->no_telepon ?: '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 text-[11px]">Email Resmi:</span>
                        <div class="font-mono text-blue-600 dark:text-blue-400 mt-0.5">{{ $selectedPemasok->email ?: '-' }}</div>
                    </div>
                    <div class="sm:col-span-2">
                        <span class="text-zinc-500 dark:text-zinc-400 text-[11px]">Alamat Domisili:</span>
                        <div class="text-zinc-700 dark:text-zinc-300 mt-0.5">{{ $selectedPemasok->alamat ?: '-' }} {{ $selectedPemasok->kota ? '(' . $selectedPemasok->kota . ')' : '' }}</div>
                    </div>
                </div>

                {{-- Riwayat Transaksi PO --}}
                <div class="space-y-2">
                    <flux:heading size="sm">Riwayat Pesanan Pembelian Terakhir</flux:heading>
                    <div class="overflow-x-auto max-h-56 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="py-2 px-3">Nomor PO</th>
                                    <th class="py-2 px-3">Tanggal PO</th>
                                    <th class="py-2 px-3">Estimasi Kirim</th>
                                    <th class="py-2 px-3 text-right">Nilai Total</th>
                                    <th class="py-2 px-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($selectedPemasok->pesananPembelian as $po)
                                    <tr>
                                        <td class="py-2 px-3 font-mono font-semibold">{{ $po->kode }}</td>
                                        <td class="py-2 px-3 font-mono text-zinc-500">{{ $po->tanggal_po ? $po->tanggal_po->format('d/m/Y') : '-' }}</td>
                                        <td class="py-2 px-3 font-mono text-zinc-500">{{ $po->estimasi_tanggal_kirim ? $po->estimasi_tanggal_kirim->format('d/m/Y') : '-' }}</td>
                                        <td class="py-2 px-3 font-mono text-right text-emerald-600 font-semibold">
                                            Rp {{ number_format($po->total_biaya ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            <flux:badge size="xs" color="emerald">{{ $po->status }}</flux:badge>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-4 text-center text-zinc-500 text-xs">
                                            Belum ada pesanan pembelian yang diterbitkan untuk rekanan ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        @endif
    </flux:modal>
</div>
