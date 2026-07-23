<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Selama MAIL_MAILER masih "log" di .env (belum ada SMTP asli), email ini TIDAK betulan
// terkirim — cukup buka storage/logs/laravel.log buat ambil link verifikasinya. Begitu SMTP
// asli diisi, tanpa ubah kode apa pun, email ini akan benar-benar terkirim ke inbox.
class VerifikasiEmail extends Notification
{
    use Queueable;

    public function __construct(private string $url)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifikasi Email — Jelajah Bahari Sungai Pisang')
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line('Terima kasih sudah mendaftar di Jelajah Bahari Sungai Pisang. Sebelum bisa login, klik tombol di bawah ini dulu untuk memverifikasi bahwa email ini benar milikmu.')
            ->action('Verifikasi Email', $this->url)
            ->line('Link ini berlaku selama 60 menit. Kalau kamu tidak merasa mendaftar di sini, abaikan saja email ini — akun tidak akan aktif tanpa verifikasi.');
    }
}
