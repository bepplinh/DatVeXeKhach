<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Bus;
use App\Models\Seat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\SeatMaterializerService;
use App\Http\Requests\Bus\StoreBusRequest;
use App\Http\Requests\Bus\SearchBusRequest;
use App\Http\Requests\Bus\UpdateBusRequest;

class BusController extends Controller
{
    public function __construct(private SeatMaterializerService $materializer) {}

    public function index(Request $request)
    {
        $perPage     = $request->query('per_page', 10);      // mặc định phân trang 10 bản ghi
        $search      = $request->query('search');            // tìm kiếm theo code / name / plate_number
        $typeBusId   = $request->query('type_bus_id');       // lọc theo loại xe

        $buses = Bus::query()
            ->select(['id','code','name','plate_number','type_bus_id']) 
            ->with(['typeBus' => function ($q) {
                $q->select(['id','name','seat_count']); 
            }])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%");
                });
            })
            ->when($typeBusId, fn ($query) => $query->where('type_bus_id', $typeBusId))
            ->paginate($perPage);
        
        if ($buses->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Bus no found',
            ]);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách xe thành công',
                'data' => $buses,
            ]);
        }
    }

    public function store(StoreBusRequest $request)
    {
        $v = $request->validated();

        try {
            $bus = DB::transaction(function () use ($v) {
                $bus = new Bus();
                $bus->code = $v['code'];
                $bus->name = $v['name'];
                $bus->plate_number = $v['plate_number'];
                $bus->type_bus_id = $v['type_bus_id'];
                $bus->seat_layout_template_id = $v['seat_layout_template_id'];
                $bus->uses_custom_seats = false; // sẽ bật lên true sau khi materialize
                $bus->save();
                return $bus;
            });

            $assigned = $this->materializer->materialize($bus, overwrite: true);

            return response()->json([
                'message' => 'Created & seats materialized',
                'data' => [
                    'bus' => $bus->fresh(),
                    'materialized_seats' => $assigned,
                ]
            ], 201);
        } catch (Throwable $e) {
            // rollback và trả lỗi
            return response()->json([
                'message' => 'Cannot create bus or materialize seats',
                'error'   => app()->hasDebugModeEnabled() ? $e->getMessage() : 'Server error'
            ], 422);
        }
    }

    public function show(Bus $bus): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Lấy thông tin xe thành công',
            'data' => $bus->load('typeBus'),
        ]);
    }

    public function update(UpdateBusRequest $req, Bus $bus)
    {
        $v = $req->validated();

        try {
            DB::transaction(function () use ($v, $bus) {
                // Lưu lại template cũ để so sánh
                $oldTemplateId = $bus->seat_layout_template_id;

                // Cập nhật các trường cơ bản
                $bus->fill(array_intersect_key($v, array_flip([
                    'code','name','plate_number','type_bus_id'
                ])));

                // Nếu client gửi seat_layout_template_id -> set vào model
                if (array_key_exists('seat_layout_template_id', $v)) {
                    $bus->seat_layout_template_id = $v['seat_layout_template_id'];
                }

                $bus->save();

                // Nếu template thay đổi -> xoá ghế cũ & materialize ghế mới 100%
                if (
                    array_key_exists('seat_layout_template_id', $v) &&
                    (int)$oldTemplateId !== (int)$bus->seat_layout_template_id
                ) {
                    // Xoá toàn bộ ghế cũ của bus
                    Seat::where('bus_id', $bus->id)->delete();

                    // Materialize từ template mới (overwrite luôn = true)
                    $this->materializer->materialize($bus, overwrite: true); // sẽ bật uses_custom_seats = true
                }
            });

            return response()->json([
                'message' => 'Updated',
                'data'    => $bus->refresh(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Cannot update bus',
                'error'   => app()->hasDebugModeEnabled() ? $e->getMessage() : 'Server error',
            ], 422);
        }
    }

    public function destroy(Bus $bus): JsonResponse
    {
        $bus->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xoá xe thành công',
        ]);
    }
}
