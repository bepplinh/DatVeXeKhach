<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ScheduleTemplateTrip;
use App\Http\Requests\ScheduleTemplateTrip\Store_Schedule_Template_Trip_Request;
use App\Http\Requests\ScheduleTemplateTrip\Update_Schedule_Template_Trip_Request;
use App\Services\ScheduleTemplateTripService;


class ScheduleTemplateTripController extends Controller
{
    public function index(Request $request)
    {
        $q = ScheduleTemplateTrip::with(['route', 'bus'])
            ->when($request->filled('weekday'), fn($qr) => $qr->where('weekday'))
            ->when($request->filled('active'),  fn($qr) => $qr->where('active', filter_var($request->active, FILTER_VALIDATE_BOOL)))
            ->orderBy('weekday')->orderBy('departure_time');
            
        if ($q->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No schedule template trips found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $q->paginate($request->get('per_page', 20))
        ]);
    }

    public function __construct(private ScheduleTemplateTripService $service) {}

    public function store(Store_Schedule_Template_Trip_Request $request)
    {
        $tpl = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $tpl
        ], 201);
    }

    public function show(ScheduleTemplateTrip $scheduleTemplateTrip)
    {
        return response()->json([
            'success' => true,
            'data' => $scheduleTemplateTrip->load(['route', 'bus'])
        ]);
    }

    public function update(Update_Schedule_Template_Trip_Request $request, ScheduleTemplateTrip $scheduleTemplateTrip)
    {
        $tpl = $this->service->update($scheduleTemplateTrip, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => $tpl,
            'message' => 'Cập nhật template_trip thành công.',
        ]);
    }

    public function destroy(ScheduleTemplateTrip $scheduleTemplateTrip)
    {
        $scheduleTemplateTrip->delete();
        return response()->json([
            'success' => true,
            'message' => 'Schedule template trip deleted successfully.'
        ]);
    }
}
