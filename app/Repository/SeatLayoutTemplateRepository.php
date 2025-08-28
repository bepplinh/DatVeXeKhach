<?php


namespace App\Repository;

use App\Models\SeatLayoutTemplate;
use Illuminate\Support\Facades\DB;
use App\Repository\Interfaces\SeatLayoutTemplateRepositoryInterface;

class SeatLayoutTemplateRepository implements SeatLayoutTemplateRepositoryInterface
{
    public function layoutGrouped(SeatLayoutTemplate $tpl): array
    {
        $rows = DB::table('template_seats')
            ->where('seat_layout_template_id', $tpl->id)
            ->orderBy('deck')
            ->orderByRaw("FIELD(column_group,'right','middle','left')")
            ->orderBy('index_in_column')
            ->get(['id','seat_label','deck','column_group','index_in_column']);

        return $rows->groupBy('deck')->map(function ($byDeck) {
            $cols = $byDeck->groupBy('column_group')->map(function ($g) {
                return $g->map(fn($r)=>[
                    'id' => (int)$r->id,
                    'label' => $r->seat_label,
                    'index' => (int)$r->index_in_column,
                ])->values();
            });
            return [
                'right'  => $cols['right']  ?? collect(),
                'middle' => $cols['middle'] ?? collect(),
                'left'   => $cols['left']   ?? collect(),
            ];
        })->toArray();
    }
}
