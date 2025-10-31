<?php

namespace App\Services\Checkout;

use App\Models\DraftCheckout;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayOSService
{
    // Dùng milliseconds (true) hay seconds (false) cho expireAt
    private const EXPIRE_USE_MILLISECONDS = true;

    // TTL mặc định 15 phút cho payment link
    private const DEFAULT_LINK_TTL_MINUTES = 15;

    private string $clientId;
    private string $apiKey;
    private string $checksumKey;
    private string $returnUrl;
    private string $cancelUrl;

    public function __construct()
    {
        $this->clientId    = (string) config('services.payos.client_id');
        $this->apiKey      = (string) config('services.payos.api_key');
        $this->checksumKey = (string) config('services.payos.checksum_key');
        $this->returnUrl   = (string) config('services.payos.return_url');
        $this->cancelUrl   = (string) config('services.payos.cancel_url');

        if (!$this->clientId || !$this->apiKey || !$this->checksumKey) {
            throw new RuntimeException('PayOS config is missing.');
        }
    }

    /**
     * Tạo link thanh toán từ draft.
     * - TTL mặc định 15 phút (có thể override bằng $ttlMinutes)
     * - Chữ ký ký trên 5 field: amount, orderCode, description, returnUrl, cancelUrl
     */
    public function createLinkFromDraft(DraftCheckout $draft, ?int $ttlMinutes = null): object
    {
        $draft->loadMissing('items');

        $amount = (int) ($draft->total_price ?? 0);
        if ($amount <= 0) {
            throw new \RuntimeException("Invalid amount: $amount");
        }

        // 1) Build items
        $items = [];
        foreach ($draft->items as $item) {
            $items[] = [
                'name'     => 'Ghế ' . (string) ($item->seat_label ?? 'N/A'),
                'quantity' => 1,
                'price'    => (int) ($item->price ?? 0),
            ];
        }
        $sum = array_sum(array_map(fn($i) => (int)$i['price'] * (int)$i['quantity'], $items));
        if ($sum !== $amount) {
            throw new \RuntimeException("Amount mismatch: items=$sum != amount=$amount");
        }

        // 2) orderCode: unique, map về draft.payment_intent_id
        $orderCode   = (int) (now()->format('ymdHis') . random_int(100, 999));
        $description = 'Thanh toán đơn hàng #' . $draft->id;

        // 3) expiredAt từ TTL (mặc định 15 phút)
        $ttlMinutes = $ttlMinutes ?? 15;
        $expiredAt  = now()->addMinutes($ttlMinutes)->timestamp;

        // 4) Ký đúng 5 fields
        $signData = [
            'amount'      => $amount,
            'orderCode'   => $orderCode,
            'description' => $description,
            'returnUrl'   => $this->returnUrl, // ví dụ https://app.yoursite/checkout/success
            'cancelUrl'   => $this->cancelUrl, // ví dụ https://app.yoursite/checkout/cancel
        ];
        $signature = $this->signFiveFields($signData, $this->checksumKey);

        // 5) Body gửi PayOS: 5 field đã ký + các field phụ
        $body = [
            ...$signData,
            'expiredAt' => $expiredAt, // << dùng TTL
            'items'     => $items,
            // tuỳ chọn nếu bạn có trong draft:
            // 'customerName'  => $draft->passenger_name,
            // 'customerEmail' => $draft->passenger_email,
            // 'customerPhone' => $draft->passenger_phone,
            'signature' => $signature,
        ];

        // 6) Call API
        $res = Http::withHeaders([
            'x-api-key'   => $this->apiKey,
            'x-client-id' => $this->clientId,
        ])
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 200)
            ->post('https://api-merchant.payos.vn/v2/payment-requests', $body);

        $json = $res->json();

        if (!$res->ok() || (string)($json['code'] ?? '') !== '00') {
            throw new \RuntimeException(
                'Create payment link failed: HTTP ' . $res->status() . ' ' .
                    json_encode($json, JSON_UNESCAPED_UNICODE)
            );
        }

        $data = (object) ($json['data'] ?? []);

        return (object) [
            'orderCode'     => $orderCode,
            'paymentLinkId' => $data->paymentLinkId ?? null,
            'checkoutUrl'   => $data->checkoutUrl ?? null,
            'expiredAt'     => $expiredAt,
        ];
    }


    /**
     * Verify webhook.
     * Lưu ý: Quy tắc verify có thể khác giữa "payment-requests" và "webhook" tùy spec.
     * Ở đây mình giữ cách sort key + key=value&... + HMAC sha256.
     */
    public function verifyWebhook(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $sig  = (string) ($payload['signature'] ?? $payload['sig'] ?? '');

        ksort($data);

        $parts = [];
        foreach ($data as $k => $v) {
            $parts[] = $k . '=' . $this->stableStringify($v);
        }
        $plain = implode('&', $parts);

        $calc = hash_hmac('sha256', $plain, $this->checksumKey);
        $ok   = hash_equals($calc, $sig);

        return [$ok, $data];
    }

    /**
     * Ký chữ ký cho phần tạo link thanh toán: chỉ 5 field.
     */
    private function signFiveFields(array $fields, string $secret): string
    {
        // Giữ đúng kiểu dữ liệu: amount/orderCode số; các trường còn lại chuỗi.
        $fields['amount']      = (int) $fields['amount'];
        $fields['orderCode']   = (int) $fields['orderCode'];
        $fields['description'] = (string) $fields['description'];
        $fields['returnUrl']   = (string) $fields['returnUrl'];
        $fields['cancelUrl']   = (string) $fields['cancelUrl'];

        ksort($fields);

        $parts = [];
        foreach ($fields as $k => $v) {
            // Không json_encode ở đây vì 5 field đều là scalar
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            } elseif ($v === null) {
                $v = '';
            } else {
                $v = (string) $v;
            }
            $parts[] = $k . '=' . $v;
        }

        $plain = implode('&', $parts);
        return hash_hmac('sha256', $plain, $secret);
    }

    /**
     * Stringify ổn định cho verify webhook (mảng: giữ kiểu gốc, assoc ksort).
     */
    private function stableStringify(mixed $value): string
    {
        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            if ($isAssoc) {
                ksort($value);
            }
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            );
        }

        if (is_bool($value))   return $value ? 'true' : 'false';
        if ($value === null)   return '';
        if (is_scalar($value)) return (string) $value;

        return (string) $value;
    }
}
