<flux:card class="w-full max-w-md space-y-6 p-8">
    <div class="text-center space-y-1">
        <flux:heading size="xl" level="1">SIMLOG RS Non-Medis</flux:heading>
        <flux:subheading>Sistem Informasi Manajemen Logistik Rumah Sakit v2</flux:subheading>
    </div>

    <form wire:submit="login" class="space-y-4">
        <flux:input
            wire:model="email"
            label="Alamat Email Resmi"
            type="email"
            placeholder="nama@simlogrs.local"
            required
            autofocus
            icon="envelope"
        />

        <flux:input
            wire:model="password"
            label="Kata Sandi"
            type="password"
            placeholder="••••••••"
            required
            viewable
            icon="key"
        />

        <div class="flex items-center justify-between">
            <flux:checkbox wire:model="remember" label="Ingat Saya" />
        </div>

        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Masuk ke Sistem</span>
            <span wire:loading wire:target="login">Memvalidasi Kredensial...</span>
        </flux:button>
    </form>

    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800 text-center">
        <flux:text size="sm" class="text-zinc-400">
            Keamanan Terpadu Rumah Sakit • Zero-Hardcode RBAC
        </flux:text>
    </div>
</flux:card>
