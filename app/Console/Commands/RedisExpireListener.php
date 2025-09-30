<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SeatFlowService;
use Illuminate\Support\Facades\Redis;

class RedisExpireListener extends Command
{
    protected $signature = 'redis:listen-expired {db=0}';
    protected $description = 'Listen Redis key expired events and release seats';

    public function handle(SeatFlowService $seatFlow)
    {
        $db = (int)$this->argument('db');
        $channel = "__keyevent@{$db}__:expired";

        // Lưu ý: nếu bạn dùng phpredis và có prefix, server publish tên key ĐẦY ĐỦ (đã có prefix).
        Redis::psubscribe([$channel], function ($message, $chan) use ($seatFlow) {
            $key = $message; // tên key vừa expired

            // Ví dụ: transport:trip:1:seat:15:lock  hoặc  trip:1:seat:15:lock
            // Dùng regex linh hoạt với/không prefix:
            if (preg_match('~(?:^.+?:)?trip:(\d+):seat:(\d+):lock$~', $key, $m)) {
                $tripId = (int)$m[1];
                $seatId = (int)$m[2];

                // Gọi hàm xử lý hết hạn: dọn ZSET/SET và broadcast
                // KHÔNG yêu cầu token vì key đã mất rồi.
                try {
                    $seatFlow->handleSeatExpired($tripId, $seatId);
                    $this->info("Expired -> released trip={$tripId} seat={$seatId}");
                } catch (\Throwable $e) {
                    $this->error("Expire handler error: ".$e->getMessage());
                }
            }
        });

        return self::SUCCESS;
    }
}
