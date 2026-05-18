<?php

namespace App\Modules\AI\Notifications;

use App\Modules\AI\Models\AnalisisAi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AnalisisAiGagalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AnalisisAi $analisisAi,
        public string $pesanError,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'analisis_ai_id' => $this->analisisAi->id,
            'jenis' => $this->analisisAi->jenis,
            'pesan' => 'Analisis AI gagal diproses.',
            'error' => $this->pesanError,
        ];
    }
}
