<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Informasi akun baru beserta kata sandi sementara untuk pegawai yang baru diregistrasi.
 */
class AkunPegawaiBaruMail extends Mailable
{
    public function __construct(
        public User $pengguna,
        public string $passwordSementara,
    ) {
        $this->pengguna->loadMissing(['unit', 'gudang', 'roles']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Akun SIMLOG RS Anda Telah Dibuat');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.akun-pegawai-baru',
            with: [
                'urlLogin' => rtrim(config('app.url'), '/') . '/login',
            ],
        );
    }
}
