<div class="w-full max-w-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-sm space-y-6">
    <div class="text-center space-y-2">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 mb-1 border border-blue-200 dark:border-blue-900">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>
        </div>
        <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">SIMLOG RS Non-Medis</h1>
        <p class="text-xs sm:text-sm text-zinc-500">Pintu Masuk Terpadu Logistik Rumah Sakit v2</p>
    </div>

    <form wire:submit="login" class="space-y-4">
        {{-- Email --}}
        <div class="space-y-1.5">
            <label for="email" class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Alamat Email Resmi</label>
            <input
                wire:model="email"
                type="email"
                id="email"
                placeholder="nama@simlogrs.local"
                required
                autofocus
                class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
            >
            @error('email')
                <p class="text-xs text-rose-600 dark:text-rose-400 font-medium">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div class="space-y-1.5">
            <label for="password" class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">Kata Sandi</label>
            <input
                wire:model="password"
                type="password"
                id="password"
                placeholder="••••••••"
                required
                class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
            >
            @error('password')
                <p class="text-xs text-rose-600 dark:text-rose-400 font-medium">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember Me --}}
        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 cursor-pointer">
                <input
                    wire:model="remember"
                    type="checkbox"
                    class="rounded border-zinc-300 dark:border-zinc-700 text-blue-600 focus:ring-blue-500"
                >
                <span class="text-xs text-zinc-600 dark:text-zinc-400 font-medium">Ingat Saya</span>
            </label>
        </div>

        {{-- Submit Button --}}
        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-medium text-sm shadow-sm transition-colors flex items-center justify-center gap-2"
        >
            <span wire:loading.remove wire:target="login">Masuk ke Sistem</span>
            <span wire:loading wire:target="login" class="flex items-center gap-2">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Memvalidasi...</span>
            </span>
        </button>
    </form>

    <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center text-[11px] text-zinc-400">
        Keamanan Autentikasi RS • Terproteksi Rate Limiter
    </div>
</div>
