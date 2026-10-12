    {{-- Modal Informasi Sandi Sementara (One-Time Secure Display - Anti Leaking H-03) --}}
    <flux:modal wire:model="showResetSuccessModal" class="md:w-[32rem]">
        <div class="space-y-4">
            <div class="flex items-center gap-3 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400">
                    <flux:icon name="key" class="size-6" />
                </div>
                <div>
                    <flux:heading size="lg" class="font-bold">Kredensial Kata Sandi Sementara</flux:heading>
                    <flux:subheading class="text-xs text-zinc-500">
                        Kata sandi akun pegawai berhasil direset.
                    </flux:subheading>
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-900/60 text-xs text-amber-900 dark:text-amber-200 flex items-start gap-2.5">
                <flux:icon name="exclamation-triangle" class="size-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                <div>
                    <strong>Perhatian Keamanan:</strong> Kredensial sementara ini <strong>hanya ditampilkan satu kali</strong>. Harap segera salin dan berikan langsung kepada pegawai yang bersangkutan. Pegawai diwajibkan mengganti kata sandi ini saat pertama kali masuk ke sistem.
                </div>
            </div>

            @if (! empty($tempPasswordData))
                <div class="space-y-3 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 text-xs">
                    <div class="flex justify-between items-center py-1 border-b border-zinc-200/60 dark:border-zinc-800/60">
                        <span class="text-zinc-500 font-medium">Nama Pegawai</span>
                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $tempPasswordData['nama'] ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-zinc-200/60 dark:border-zinc-800/60">
                        <span class="text-zinc-500 font-medium">Username Login</span>
                        <span class="font-mono font-semibold text-zinc-800 dark:text-zinc-200">{{ $tempPasswordData['username'] ?? '-' }}</span>
                    </div>
                    <div class="pt-1 space-y-1.5">
                        <span class="text-zinc-500 font-medium block">Kata Sandi Sementara</span>
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                readonly
                                value="{{ $tempPasswordData['tempPassword'] ?? '' }}"
                                id="temp-password-input"
                                class="w-full h-9 text-sm font-mono font-bold tracking-widest text-center rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 select-all"
                            />
                            <flux:button
                                type="button"
                                size="sm"
                                variant="primary"
                                icon="clipboard-document"
                                onclick="navigator.clipboard.writeText(document.getElementById('temp-password-input').value); alert('Kata sandi sementara berhasil disalin!');"
                            >
                                Salin
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="button" variant="primary" wire:click="closeResetSuccessModal">
                    Tutup & Hapus Kredensial
                </flux:button>
            </div>
        </div>
    </flux:modal>
