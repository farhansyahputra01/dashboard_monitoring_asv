<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use App\Models\SensorData;

/**
 * ShouldBroadcastNow, BUKAN ShouldBroadcast.
 *
 * Dengan ShouldBroadcast, event ini masuk tabel `jobs` dulu lalu menunggu
 * queue:work memungutnya. Driver antreannya database dan queue:work tidur
 * 3 detik tiap kali antrean kosong, sehingga tiap pembacaan sensor tertahan
 * 2,2-2,6 detik (hasil pengukuran) sebelum sampai ke browser - padahal
 * perjalanan dari kapal sampai tersimpan cuma ~0,15 detik.
 *
 * ShouldBroadcastNow mengirim langsung ke Reverb di dalam request itu juga,
 * tanpa lewat antrean. Reverb ada di mesin yang sama, jadi tambahan
 * waktunya hanya beberapa milidetik.
 */
class SensorDataUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SensorData $sensorData;

    /**
     * Create a new event instance.
     */
    public function __construct(SensorData $sensorData)
    {
        $this->sensorData = $sensorData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('sensors'),
        ];
    }
}
