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
        try {
            $validated = $request->validated();

            $template = ScheduleTemplateTrip::create($validated);

            return response()->json([
                'success' => true,
                'data' => $template,
                'message' => 'Tạo lịch trình mẫu thành công'
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Xử lý lỗi unique constraint từ database
            if (str_contains($e->getMessage(), 'unique_schedule_template_trip')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xe này đã có lịch trình vào thời gian này. Vui lòng chọn giờ khác hoặc xe khác.'
                ], 409); // Conflict
            }

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo lịch trình'
            ], 500);
        }
    }

    public function update(Update_Schedule_Template_Trip_Request $request, ScheduleTemplateTrip $scheduleTemplateTrip)
    {
        try {
            $validated = $request->validated();

            $scheduleTemplateTrip->update($validated);

            return response()->json([
                'success' => true,
                'data' => $scheduleTemplateTrip,
                'message' => 'Cập nhật lịch trình mẫu thành công'
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Xử lý lỗi unique constraint từ database
            if (str_contains($e->getMessage(), 'unique_schedule_template_trip')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xe này đã có lịch trình vào thời gian này. Vui lòng chọn giờ khác hoặc xe khác.'
                ], 409); // Conflict
            }

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật lịch trình'
            ], 500);
        }
    }

    public function show(ScheduleTemplateTrip $scheduleTemplateTrip)
    {
        return response()->json([
            'success' => true,
            'data' => $scheduleTemplateTrip->load(['route', 'bus'])
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
