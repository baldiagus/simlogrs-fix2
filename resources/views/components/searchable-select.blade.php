@props([
    'model' => '',
    'label' => '',
    'placeholder' => 'Pilih salah satu...',
    'options' => [],
    'required' => false,
    'nullable' => true,
    'disabled' => false,
    'live' => false,
])

@php
    $normalizedOptions = collect($options)->map(function ($opt) {
        if (is_array($opt)) {
            $value = $opt['id'] ?? $opt['value'] ?? '';
            $label = $opt['nama'] ?? $opt['label'] ?? ($opt['barang']['nama'] ?? '');
            $sub   = $opt['sub'] ?? $opt['kode'] ?? $opt['kode_aset'] ?? $opt['nip'] ?? $opt['email'] ?? $opt['simbol'] ?? '';
        } elseif (is_object($opt)) {
            $value = $opt->id ?? $opt->value ?? '';
            $label = $opt->nama ?? $opt->label ?? ($opt->barang?->nama ?? '');
            $sub   = $opt->sub ?? $opt->kode ?? $opt->kode_aset ?? $opt->nip ?? $opt->email ?? $opt->simbol ?? '';
        } else {
            $value = $opt;
            $label = $opt;
            $sub   = '';
        }
        return [
            'value' => (string) $value,
            'label' => (string) $label,
            'sub'   => (string) $sub,
        ];
    })->values()->toArray();
@endphp

<div
    x-data="{
        open: false,
        search: '',
        value: @if($live) @entangle($model).live @else @entangle($model) @endif,
        options: {{ Js::from($normalizedOptions) }},
        get selectedOption() {
            if (!this.value && this.value !== 0) return null;
            return this.options.find(o => String(o.value) === String(this.value)) || null;
        },
        get filteredOptions() {
            if (!this.search.trim()) return this.options;
            let q = this.search.toLowerCase();
            return this.options.filter(o =>
                o.label.toLowerCase().includes(q) ||
                (o.sub && o.sub.toLowerCase().includes(q))
            );
        },
        select(val) {
            this.value = val;
            this.open = false;
            this.search = '';
        },
        clear() {
            this.value = null;
            this.open = false;
            this.search = '';
        }
    }"
    x-effect="options = {{ Js::from($normalizedOptions) }}"
    class="relative w-full"
    @click.outside="open = false; search = ''"
    @keydown.escape.window="open = false; search = ''"
>
    @if ($label)
        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
            {{ $label }}@if($required)<span class="text-red-500 ml-0.5">*</span>@endif
        </label>
    @endif

    {{-- Trigger Button --}}
    <button
        type="button"
        @click="if (!{{ $disabled ? 'true' : 'false' }}) { open = !open; if (open) $nextTick(() => $refs.searchInput.focus()); }"
        @php
            $isInvalid = $errors->has($model);
        @endphp
        {{ $attributes->class([
            'w-full flex items-center justify-between text-left text-sm rounded-lg border px-3 py-2 transition-colors shadow-2xs',
            'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100',
            $isInvalid
                ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30 focus:border-red-500 focus:ring-red-500/30'
                : 'border-zinc-300 dark:border-zinc-700 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500',
            'opacity-60 cursor-not-allowed' => $disabled,
        ]) }}
        <span class="truncate pr-2" :class="selectedOption ? 'font-medium' : 'text-zinc-400 dark:text-zinc-500 text-xs'">
            <span x-show="selectedOption">
                <span x-text="selectedOption ? selectedOption.label : ''"></span>
                <span x-show="selectedOption && selectedOption.sub" class="text-zinc-400 text-xs font-normal" x-text="selectedOption && selectedOption.sub ? ' (' + selectedOption.sub + ')' : ''"></span>
            </span>
            <span x-show="!selectedOption">{{ $placeholder }}</span>
        </span>

        <span class="flex items-center gap-1.5 shrink-0 text-zinc-400">
            @if ($nullable)
                <template x-if="selectedOption && !{{ $disabled ? 'true' : 'false' }}">
                    <span
                        @click.stop="clear()"
                        class="hover:text-red-500 cursor-pointer p-0.5 rounded transition-colors"
                        title="Hapus Pilihan"
                    >
                        <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z"/>
                        </svg>
                    </span>
                </template>
            @endif

            <svg class="size-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                <path fill-rule="evenodd" d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
            </svg>
        </span>
    </button>

    {{-- Dropdown Floating Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 translate-y-1 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 scale-95"
        class="absolute left-0 top-full mt-1.5 w-full bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-xl z-50 overflow-hidden"
        style="display: none;"
    >
        {{-- Search Input Box --}}
        <div class="p-2 border-b border-zinc-100 dark:border-zinc-700/80 bg-zinc-50/70 dark:bg-zinc-900/40">
            <div class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 focus-within:ring-2 focus-within:ring-blue-500/30 focus-within:border-blue-500 shadow-2xs">
                <svg class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.965 11.026a5 5 0 1 1 1.06-1.06l2.755 2.754a.75.75 0 1 1-1.06 1.06l-2.755-2.754ZM10.5 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z" clip-rule="evenodd"/>
                </svg>
                <input
                    x-ref="searchInput"
                    x-model="search"
                    type="text"
                    placeholder="Ketik untuk mencari..."
                    class="w-full bg-transparent border-0 p-0 text-xs text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-0 placeholder:text-zinc-400 dark:placeholder:text-zinc-500"
                />
            </div>
        </div>

        {{-- Options Scroll Area --}}
        <div class="max-h-56 overflow-y-auto p-1 text-xs">
            <template x-for="item in filteredOptions" :key="item.value">
                <div
                    @click="select(item.value)"
                    class="flex items-center justify-between px-3 py-2 rounded-lg cursor-pointer transition-colors"
                    :class="String(item.value) === String(value) ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 font-semibold' : 'text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700/60'"
                >
                    <div class="flex flex-col truncate pr-2">
                        <span x-text="item.label" class="truncate font-medium"></span>
                        <span x-show="item.sub" x-text="item.sub" class="text-[10px] text-zinc-400 font-mono"></span>
                    </div>

                    <svg x-show="String(item.value) === String(value)" class="size-4 shrink-0 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </template>

            {{-- Empty Search Results State --}}
            <template x-if="filteredOptions.length === 0">
                <div class="py-4 text-center text-xs text-zinc-400">
                    Tidak ada opsi yang cocok dengan "<span x-text="search" class="font-semibold text-zinc-600 dark:text-zinc-300"></span>"
                </div>
            </template>
        </div>
    </div>

    @error($model)
        <p class="mt-1.5 text-xs text-red-500 dark:text-red-400 flex items-center gap-1 font-medium">
            <svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm.75-10.25a.75.75 0 0 0-1.5 0v4.5a.75.75 0 0 0 1.5 0v-4.5Zm-.75 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
            </svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
