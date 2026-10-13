@props([
    'title' => 'Konfirmasi Tindakan',
    'description' => 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
    'confirmText' => 'Ya, Lanjutkan',
    'variant' => 'primary',
])

<flux:modal name="confirm-modal" wire:model="showConfirmModal" class="md:w-[28rem]">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            <flux:subheading class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ $description }}
            </flux:subheading>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
            <flux:button type="button" variant="ghost" wire:click="$set('showConfirmModal', false)">
                Batal
            </flux:button>
            <flux:button
                type="button"
                :variant="$variant"
                wire:click="executeConfirmedAction"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="executeConfirmedAction">{{ $confirmText }}</span>
                <span wire:loading wire:target="executeConfirmedAction">Memproses...</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
