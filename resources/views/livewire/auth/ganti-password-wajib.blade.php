<flux:card class="w-full max-w-md space-y-6 p-8">
    <div class="text-center space-y-1">
        <div class="inline-flex p-3 rounded-2xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 mb-2">
            <flux:icon name="shield-exclamation" class="size-8" />
        </div>
        <flux:heading size="xl" level="1">Wajib Perbarui Kata Sandi</flux:heading>
        <flux:subheading>
            Akun Anda menggunakan kata sandi sementara dari administrator. Demi keamanan akun kedinasan, silakan buat kata sandi baru.
        </flux:subheading>
    </div>

    @if (session('warning'))
        <div class="p-3.5 rounded-xl bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/60 text-xs text-amber-800 dark:text-amber-200">
            {{ session('warning') }}
        </div>
    @endif

    <form wire:submit="gantiPassword" class="space-y-4">
        <flux:input
            wire:model="password_saat_ini"
            label="Kata Sandi Sementara Saat Ini"
            type="password"
            placeholder="••••••••"
            required
            autofocus
            viewable
            icon="key"
        />

        <flux:input
            wire:model="password_baru"
            label="Kata Sandi Baru"
            type="password"
            placeholder="Minimal 8 karakter kombinasi"
            description="Minimal 8 karakter, wajib mengandung kombinasi huruf dan angka."
            required
            viewable
            icon="lock-closed"
        />

        <flux:input
            wire:model="password_baru_confirmation"
            label="Ulangi Kata Sandi Baru"
            type="password"
            placeholder="Ulangi kata sandi baru"
            required
            viewable
            icon="check-circle"
        />

        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="gantiPassword">Simpan & Lanjutkan ke Sistem</span>
            <span wire:loading wire:target="gantiPassword">Memperbarui Kata Sandi...</span>
        </flux:button>
    </form>

    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800 flex justify-between items-center text-xs">
        <span class="text-zinc-500">Bukan akun Anda?</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-rose-600 hover:underline font-medium">
                Keluar dari Sesi
            </button>
        </form>
    </div>
</flux:card>
