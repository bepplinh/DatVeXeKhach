<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\TemplateTripGeneratorService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TripGenerateFromTemplateController extends Controller
{
    public function __construct(private TemplateTripGeneratorService $service) {}

    public function generate(Request $req) {
        $v = $req->validate([
            'range'        => ['required','in:day,week,month'],
            'date'         => ['nullable','date_format:Y-m-d'],
            'start_date'   => ['nullable','date_format:Y-m-d'],
            'month'        => ['nullable','regex:/^\d{4}-\d{2}$/'],
            'template_ids' => ['nullable','array'],
            'template_ids.*'=> ['integer','exists:schedule_template_trips,id'],
        ]);

        $results = [];

        if ($v['range'] === 'day') {
            $d = Carbon::parse($v['date'] ?? now()->toDateString());
            $results[$d->toDateString()] = $this->service->generateForDate($d, $v['template_ids'] ?? null);
        }

        if ($v['range'] === 'week') {
            $start = Carbon::parse($v['start_date'] ?? now()->toDateString());
            for ($i=0; $i<7; $i++) {
                $d = (clone $start)->addDay($i);
                $results[$d->toDateString()] = $this->service->generateForDate($d, $v['template_ids'] ?? null);
            }
        }

        if ($v['range'] === 'month') {
            $first = Carbon::parse(($v['month'] ?? now()->format('Y-m')).'-01')->startOfMonth();
            $last  = (clone $first)->endOfMonth();
            for ($d=$first->copy(); $d->lte($last); $d->addDay()) {
                $results[$d->toDateString()] = $this->service->generateForDate($d, $v['template_ids'] ?? null);
            }
        }

        return response()->json(['success'=>true,'data'=>$results,'message'=>'Generate trips thành công']);
    }
}
