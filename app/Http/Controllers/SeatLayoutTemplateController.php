<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SeatLayoutTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\SeatTemplateLayout\ListSeatTemplatesRequest;
use App\Repository\Interfaces\SeatLayoutTemplateRepositoryInterface;

class SeatLayoutTemplateController extends Controller
{
    public function __construct(private SeatLayoutTemplateRepositoryInterface $tplSeats) {}

    public function index(ListSeatTemplatesRequest  $request)
    {
        $v = $request->validated();
        $q   = $v['q'] ?? null;
        $per = $v['per_page'] ?? 20;
        $sort= $v['sort'] ?? 'created_at';
        $dir = $v['direction'] ?? 'desc';
        $withLayout = (bool)($v['with_layout'] ?? false);

        $query = SeatLayoutTemplate::query()->withCount('templateSeats');

        if($q) {
            $query->where(function($x) use ($q) {
                $x->where('code','like',"%{$q}%")
                    ->orWhere('name','like',"%{$q}%");
            });
        }

        $query->orderBy($sort, $dir);

        $page = $query->paginate($per);

        if($withLayout) {
            foreach ($page as $tpl) {
                $tpl->preview = $this->tplSeats->layoutGrouped($tpl);
            }
        }

        return response()->json($page);
    }
}
