    {{-- MODAL OTORISASI (APPROVE / REJECT) --}}
    <flux:modal name="modal-approval-pengalihan" wire:model="showApprovalModal" class="w-full max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">
                    {{ $actionType === 'APPROVE' ? 'Setujui Pengalihan Aset' : 'Tolak Pengalihan Aset' }}
                </flux:heading>
                <flux:subheading>
                    {{ $actionType === 'APPROVE' ? 'Izinkan serah terima fisik aset ke lokasi tujuan.' : 'Tolak permohonan dan kembalikan aset ke status normal di lokasi asal.' }}
                </flux:subheading>
            </div>

            <flux:textarea
                wire:model="catatanApproval"
                label="Catatan Otorisasi (Opsional)"
                placeholder="Tuliskan catatan verifikasi pimpinan..."
                rows="3"
            />

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showApprovalModal', false)">
                    Batal
                </flux:button>
                <flux:button
                    variant="{{ $actionType === 'APPROVE' ? 'primary' : 'danger' }}"
                    wire:click="executeApproval"
                >
                    {{ $actionType === 'APPROVE' ? 'Ya, Setujui' : 'Tolak Permohonan' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
