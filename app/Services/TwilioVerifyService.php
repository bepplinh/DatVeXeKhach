<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioVerifyService
{
    public function __construct(
        private ?Client $client = null,
        private ?string $serviceSid = null,
    ) {
        $this->client = $this->client ?: new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
        $this->serviceSid = $this->serviceSid ?: (string) config('services.twilio.verify_sid');
    }

    // Gửi OTP
    public function start(string $e164Phone, string $channel = 'sms', array $options = []): void
    {
        // v8.7.1: create(string $to, string $channel, array $options = [])
        $this->client->verify->v2
            ->services($this->serviceSid)
            ->verifications
            ->create($e164Phone, $channel, $options);
    }

    // Kiểm tra OTP
    public function check(string $e164Phone, string $code): bool
    {
        // v8.7.1: verificationChecks->create(array $opts)
        $check = $this->client->verify->v2
            ->services($this->serviceSid)
            ->verificationChecks
            ->create(['to' => $e164Phone, 'code' => $code]);

        return $check->status === 'approved';
    }
}
