<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">{{ $portalTitle }}</flux:heading>
            <flux:subheading>{{ $portalSubtitle }}</flux:subheading>
        </div>
        <div>
            <x-ui.status-badge status="AKTIF" />
        </div>
    </div>

    {{-- Kartu Metrik Operasional --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-1 p-5">
            <flux:text size="sm" class="text-zinc-500">Status Wewenang</flux:text>
            <div class="text-lg font-bold text-emerald-600">Terverifikasi</div>
            <flux:text size="xs" class="text-zinc-400">100% Dynamic RBAC Active</flux:text>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text size="sm" class="text-zinc-500">Sistem Logistik</flux:text>
            <div class="text-lg font-bold text-blue-600">Operasional</div>
            <flux:text size="xs" class="text-zinc-400">SIMLOG RS v2 Livewire</flux:text>
        </flux:card>
    </div>
</div>
