<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Akun SIMLOG RS Anda Telah Dibuat</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #18181b; background: #f4f4f5; margin: 0; padding: 24px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 24px;">
        <tr>
            <td>
                <h2 style="margin-top: 0;">Halo, {{ $pengguna->nama }}</h2>
                <p>Akun Anda di {{ config('app.name') }} telah dibuat. Berikut informasi akun Anda:</p>

                <table cellpadding="6" cellspacing="0" style="font-size: 14px; border-collapse: collapse; width: 100%;">
                    <tr><td style="color: #71717a; width: 38%;">Nama</td><td>{{ $pengguna->nama }}</td></tr>
                    <tr><td style="color: #71717a;">Username</td><td>{{ $pengguna->username }}</td></tr>
                    <tr><td style="color: #71717a;">Email Login</td><td>{{ $pengguna->email }}</td></tr>
                    <tr><td style="color: #71717a;">Peran</td><td>{{ $pengguna->roles->pluck('name')->implode(', ') ?: '-' }}</td></tr>
                    <tr><td style="color: #71717a;">Penugasan</td><td>{{ $pengguna->unit?->nama ?? $pengguna->gudang?->nama ?? '-' }}</td></tr>
                    <tr><td style="color: #71717a;">Kata Sandi Sementara</td><td><code style="font-size: 15px; background: #f4f4f5; padding: 2px 6px; border-radius: 4px;">{{ $passwordSementara }}</code></td></tr>
                </table>

                <p style="margin-top: 20px;">
                    <a href="{{ $urlLogin }}" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 6px;">Masuk ke SIMLOG RS</a>
                </p>

                <p style="font-size: 13px; color: #52525b;">
                    Demi keamanan, Anda akan diminta mengganti kata sandi sementara ini saat login pertama.
                    Jangan membagikan kata sandi ini kepada siapa pun.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
