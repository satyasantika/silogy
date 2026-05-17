<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends BaseNotification
{
    public string $url;

    protected function resetUrl($notifiable): string
    {
        return $this->url;
    }

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi — '.config('app.name'))
            ->greeting('Halo!')
            ->line('Anda menerima email ini karena kami menerima permintaan atur ulang kata sandi untuk akun Anda.')
            ->action('Atur Ulang Kata Sandi', $this->resetUrl($notifiable))
            ->line("Tautan atur ulang kata sandi ini berlaku selama {$expire} menit.")
            ->line('Jika Anda tidak meminta atur ulang kata sandi, abaikan email ini.');
    }
}
