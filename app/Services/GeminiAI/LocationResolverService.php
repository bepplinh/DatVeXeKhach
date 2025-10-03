<?php

namespace App\Services\GeminiAI;

use Illuminate\Support\Facades\DB;

class LocationResolverService
{
    // Dùng collation phổ biến (tránh lỗi 1273 Unknown collation)
    private string $collation = 'utf8mb4_unicode_ci';

    /**
     * Trả về id location đã match (KHÔNG leo lên city).
     * Nếu muốn leo lên city, hãy viết hàm khác: resolveCityIdFromText().
     */
    public function resolveIdFromText(string $text): ?int
    {
        $res = $this->resolve($text);
        return $res['id'] ?? null;
    }

    /**
     * Trả về ['id'=>int,'name'=>string,'type'=>'city'|'station','parent_id'=>?int] hoặc [] nếu không thấy
     */
    public function resolve(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [];

        // ƯU TIÊN STATION TRƯỚC (parent_id != null)
        $order = 'CASE WHEN l.parent_id IS NULL THEN 1 ELSE 0 END';

        // 1) exact: alias -> name
        if ($row = $this->findByAliasExact($text, $order)) return $this->formatRow($row);
        if ($row = $this->findByNameExact($text, $order))  return $this->formatRow($row);

        // 2) prefix
        if ($row = $this->findByAliasPrefix($text, $order)) return $this->formatRow($row);
        if ($row = $this->findByNamePrefix($text, $order))  return $this->formatRow($row);

        // 3) substring
        if ($row = $this->findByAliasSubstring($text, $order)) return $this->formatRow($row);
        if ($row = $this->findByNameSubstring($text, $order))  return $this->formatRow($row);

        return [];
    }

    public function suggest(string $text, int $limit = 5): array
    {
        $text = trim($text);
        if ($text === '') return [];

        $order = 'CASE WHEN l.parent_id IS NULL THEN 1 ELSE 0 END'; // station trước
        $rows = [];
        $buckets = [
            $this->queryAliasExact($text, $order),
            $this->queryNameExact($text, $order),
            $this->queryAliasPrefix($text, $order),
            $this->queryNamePrefix($text, $order),
            $this->queryAliasSubstring($text, $order),
            $this->queryNameSubstring($text, $order),
        ];

        foreach ($buckets as $q) {
            foreach ($q as $r) {
                $rows[$r->id] = $r;
                if (count($rows) >= $limit) break 2;
            }
        }

        return collect($rows)->values()->map(fn($r) => $this->formatRow($r))->all();
    }

    /* ====================== FIND 1 ====================== */

    private function findByAliasExact(string $text, string $order): ?object
    {
        if (!$this->hasAliasTable()) return null;
        return DB::table('location_aliases AS a')
            ->join('locations AS l', 'l.id', '=', 'a.location_id')
            ->select('l.id', 'l.name', 'l.parent_id')
            ->whereRaw("a.alias COLLATE {$this->collation} = ?", [$text])
            ->orderByRaw($order)
            ->first();
    }

    private function findByNameExact(string $text, string $order): ?object
    {
        return DB::table('locations AS l')
            ->select('l.id', 'l.name', 'l.parent_id')
            ->whereRaw("l.name COLLATE {$this->collation} = ?", [$text])
            ->orderByRaw($order)
            ->first();
    }

    private function findByAliasPrefix(string $text, string $order): ?object
    {
        if (!$this->hasAliasTable()) return null;
        return DB::table('location_aliases AS a')
            ->join('locations AS l', 'l.id', '=', 'a.location_id')
            ->select('l.id', 'l.name', 'l.parent_id')
            ->whereRaw("a.alias COLLATE {$this->collation} LIKE ?", [$text . '%'])
            ->orderByRaw($order)
            ->first();
    }

    private function findByNamePrefix(string $text, string $order): ?object
    {
        return DB::table('locations AS l')
            ->select('l.id', 'l.name', 'l.parent_id')
            ->whereRaw("l.name COLLATE {$this->collation} LIKE ?", [$text . '%'])
            ->orderByRaw($order)
            ->first();
    }

    private function findByAliasSubstring(string $text, string $order): ?object
    {
        if (!$this->hasAliasTable()) return null;
        return DB::table('location_aliases AS a')
            ->join('locations AS l', 'l.id', '=', 'a.location_id')
            ->select('l.id', 'l.name', 'l.parent_id')
            ->whereRaw("a.alias COLLATE {$this->collation} LIKE ?", ['%' . $text . '%'])
            ->orderByRaw($order)
            ->first();
    }

    private function findByNameSubstring(string $text, string $order): ?object
    {
        return DB::table('locations AS l')
            ->select('l.id', 'l.name', 'l.parent_id')
            ->whereRaw("l.name COLLATE {$this->collation} LIKE ?", ['%' . $text . '%'])
            ->orderByRaw($order)
            ->first();
    }

    /* ====================== QUERY N ====================== */

    private function queryAliasExact(string $text, string $order): array
    {
        if (!$this->hasAliasTable()) return [];
        return DB::table('location_aliases AS a')
            ->join('locations AS l', 'l.id', '=', 'a.location_id')
            ->select('l.id', 'l.name', 'l.parent_id')
            ->whereRaw("a.alias COLLATE {$this->collation} = ?", [$text])
            ->orderByRaw($order)
            ->limit(10)->get()->all();
    }

    private function queryNameExact(string $text, string $order): array
    {
        return DB::table('locations AS l')
            ->select('l.id', 'l.name', 'l.parent_id')
            ->whereRaw("l.name COLLATE {$this->collation} = ?", [$text])
            ->orderByRaw($order)
            ->limit(10)->get()->all();
    }

    private function queryAliasPrefix(string $text, string $order): array
    {
        if (!$this->hasAliasTable()) return [];
        return DB::table('location_aliases AS a')
            ->join('locations AS l', 'l.id', '=', 'a.location_id')
            ->select('l.id', 'l.name', 'l.parent_id')
            ->whereRaw("a.alias COLLATE {$this->collation} LIKE ?", [$text . '%'])
            ->orderByRaw($order)
            ->limit(10)->get()->all();
    }

    private function queryNamePrefix(string $text, string $order): array
    {
        return DB::table('locations AS l')
            ->select('l.id', 'l.name', 'l.parent_id')
            ->whereRaw("l.name COLLATE {$this->collation} LIKE ?", [$text . '%'])
            ->orderByRaw($order)
            ->limit(10)->get()->all();
    }

    private function queryAliasSubstring(string $text, string $order): array
    {
        if (!$this->hasAliasTable()) return [];
        return DB::table('location_aliases AS a')
            ->join('locations AS l', 'l.id', '=', 'a.location_id')
            ->select('l.id', 'l.name', 'l.parent_id')
            ->whereRaw("a.alias COLLATE {$this->collation} LIKE ?", ['%' . $text . '%'])
            ->orderByRaw($order)
            ->limit(10)->get()->all();
    }

    private function queryNameSubstring(string $text, string $order): array
    {
        return DB::table('locations AS l')
            ->select('l.id', 'l.name', 'l.parent_id')
            ->whereRaw("l.name COLLATE {$this->collation} LIKE ?", ['%' . $text . '%'])
            ->orderByRaw($order)
            ->limit(10)->get()->all();
    }

    /* ====================== Utils ====================== */

    private function hasAliasTable(): bool
    {
        static $exists = null;
        if ($exists === null) {
            $exists = DB::getSchemaBuilder()->hasTable('location_aliases');
        }
        return $exists;
    }

    private function formatRow(object $row): array
    {
        return [
            'id'        => (int)$row->id,
            'name'      => (string)$row->name,
            'parent_id' => $row->parent_id !== null ? (int)$row->parent_id : null,
            'type'      => $row->parent_id === null ? 'city' : 'station',
        ];
    }

    // Nếu thực sự cần leo lên city, dùng riêng hàm này
    public function resolveCityIdFromText(string $text): ?int
    {
        $res = $this->resolve($text);
        if (!$res) return null;
        if (($res['type'] ?? null) === 'city') return (int)$res['id'];

        // leo lên city
        $current = DB::table('locations')->where('id', $res['id'])->first();
        $guard = 0;
        while ($current && $current->parent_id !== null && $guard < 5) {
            $current = DB::table('locations')->where('id', $current->parent_id)->first();
            $guard++;
        }
        return $current ? (int)$current->id : null;
    }
}
