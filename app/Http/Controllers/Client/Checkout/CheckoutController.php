<?php

namespace App\Http\Controllers\Client\Checkout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Draft\UpdateDraftRequest;
use App\Services\DraftCheckoutService\UpdateDraftPayment;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private UpdateDraftPayment $updateDraftPayment
    ) {}

    public function updateDraftPayment(
        UpdateDraftRequest $request,
        int $draftId
    )
    {
        $sessionToken = $request->header('X-Session-Token');
        $data = $request->validated();
        
        $updated = $this->updateDraftPayment->updateDraftCheckout(
            draftId:      $draftId,
            sessionToken: $sessionToken,
            payload:      $data
        );

        try {
            $provider = strtolower((string)($data['payment_provider'] ?? $updated->payment_provider ?? ''));
            if ($provider === 'cash') {
                $updated->update(
                    ['status' => 'paid', 'paid_at' => now()]
                );
            } 
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Co loi khi thanh toan, vui long thu lai',
                'error'   => $e->getMessage(),
            ], 500);
        }
        
    }
}