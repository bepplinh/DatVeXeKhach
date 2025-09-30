<?php

namespace App\Support\Time;

use Carbon\Carbon;

class ViDatetimeParser
{
    public static function resolveDate(string $input, string $tz = 'Asia/Bangkok'): Carbon
    {
        $now = Carbon::now($tz)->startOfMinute();

        $str = mb_strtolower(trim($input));
        // Các mẫu phổ biến
        if (str_contains($str, 'hôm nay')) return $now;
        if (str_contains($str, 'mai')) return $now->copy()->addDay();
        if (preg_match('/thứ\s*(\d)/u', $str, $m)) {
            $targetDow = (int)$m[1]; // 2..7 (2=Mon)
            // Carbon: 0=Sun..6=Sat. Ta map về ISO (Mon=1)
            $isoToday = (int)$now->isoWeekday();
            $delta = $targetDow - $isoToday;
            if ($delta < 0) $delta += 7;
            return $now->copy()->addDays($delta);
        }
        // Fallback: thử parse dạng YYYY-MM-DD
        try {
            return Carbon::parse($input, $tz);
        } catch (\Throwable) {
            return $now; // fallback thận trọng
        }
    }

    public static function resolveTimeWindow(string $labelOrRange): array
    {
        $s = mb_strtolower(trim($labelOrRange));
        if ($s === 'sáng')  return ['04:30', '11:59'];
        if ($s === 'chiều') return ['12:00', '17:59'];
        if ($s === 'tối')   return ['18:00', '23:59'];

        if (preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $s, $m)) {
            return [$m[1], $m[2]];
        }
        // default tối
        return ['18:00', '23:59'];
    }
}
