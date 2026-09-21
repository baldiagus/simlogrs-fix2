<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIMLOG RS Non-Medis v2</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxStyles
</head>
<body class="bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-sm text-center space-y-4">
        <h1 class="text-xl font-bold tracking-tight">SIMLOG RS Non-Medis v2</h1>
        <p class="text-sm text-zinc-500">Sistem Informasi Manajemen Logistik Rumah Sakit</p>
        <div class="p-3 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 rounded-lg text-xs font-semibold">
            Infrastruktur Container Berjalan Normal
        </div>
    </div>
    @livewireScripts
    @fluxScripts
</body>
</html>
