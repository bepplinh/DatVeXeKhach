<?php

namespace App\Http\Controllers\Client\Checkout;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DraftCheckout;
use App\Services\SeatFlowService;
use App\Services\Checkout\PayOSService;

class DraftPaymentController extends Controller
{
    public function __construct(
        private SeatFlowService $seats,
        private PayOSService $payos,
    ) {}

    public function createPaymentLink(Request $req, string $id)
    {
        // (optional) Idempotency
        $idempotencyKey = $req->header('Idempotency-Key') ?? null;

        return DB::transaction(function () use ($id, $idempotencyKey) {
            /** @var DraftCheckout $draft */
            $draft = DraftCheckout::query()
                ->whereKey($id)
                ->whereIn('status', ['pending', 'active'])
                ->lockForUpdate()
                ->firstOrFail();

            // 1) Gia hạn TTL lock để user có đủ thời gian trả tiền (ví dụ 10 phút)
            $this->seats->renewLocksForPayment($draft, 10 * 60);

            // 2) Tạo Payment Link từ draft (server-trust tính tiền)
            $payment = $this->payos->createLinkFromDraft($draft, $idempotencyKey);

            // 3) Lưu metadata & step
            $draft->update([
                'status' => 'paying',
                'payment_provider' => 'payos',
                'payment_intent_id' => $payment->orderCode,
            ]);

            return response()->json([
                'checkoutUrl' => $payment->checkoutUrl,
                'orderCode'   => $payment->orderCode,
            ]);
        });
    }
}
