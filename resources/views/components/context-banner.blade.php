@php
    $contextManager = app(\App\Support\Auth\ContextManager::class);
@endphp

@if ($contextManager->isEmulating())
    <div class="bg-amber-500 text-white px-4 py-2 text-xs font-semibold flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <span>Mode Emulasi Aktif: <strong>{{ $contextManager->getLabel() }}</strong></span>
        </div>
        <button
            wire:click="resetKonteksGlobal"
            type="button"
            class="bg-amber-700 hover:bg-amber-800 text-white text-[11px] px-2.5 py-1 rounded font-bold uppercase tracking-wider transition-colors"
        >
            Reset Mode Global
        </button>
    </div>
@endif
