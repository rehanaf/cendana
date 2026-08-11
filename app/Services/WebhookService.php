<?php

namespace App\Services;

use App\Models\RetailInvoice;
use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Kirim notifikasi pembayaran ke webhook sesuai jenis tagihan.
     * - SubscriptionInvoice  -> setting webhook_corporate_*
     * - RetailInvoice        -> setting webhook_retail_*
     *
     * Setting per jenis: method (HTTP), url, secret, headers (baris key=value),
     * dan body (template JSON). Semua mendukung variabel:
     *   - {{event}}                        e.g. payment.received
     *   - {{transaction.id|name|amount|description|transaction_date|wallet_id|coa_id}}
     *   - {{invoice.no|total|period|due_date|status}}
     *   - {{customer.kolom}}  mis. {{customer.name}}, {{customer.email}}, {{customer.code}}
     *
     * Jika body kosong (atau JSON tidak valid), dikirim seluruh konteks sebagai JSON.
     */
    public function dispatch(Model $transaction, ?Model $context = null): void
    {
        $keys = $this->resolveKeys($context);

        if ($keys === null) {
            return;
        }

        if (! (bool) Setting::get($keys['enabled'], false)) {
            return;
        }

        $url = trim((string) Setting::get($keys['url'], ''));

        if ($url === '') {
            return;
        }

        $method = strtoupper((string) Setting::get($keys['method'], 'POST'));
        $contextArray = $this->buildContext($transaction, $context);

        $headers = $this->buildHeaders((string) Setting::get($keys['headers'], ''), $contextArray);
        $secret = trim((string) Setting::get($keys['secret'], ''));
        if ($secret !== '') {
            $headers['X-Webhook-Secret'] = $secret;
        }

        $body = $this->buildBody((string) Setting::get($keys['body'], ''), $contextArray);

        try {
            $request = Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders($headers);

            $this->send($request, $method, $url, $body);
        } catch (\Throwable $e) {
            Log::warning('Webhook gagal dikirim.', [
                'url' => $url,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function send(PendingRequest $request, string $method, string $url, array $body)
    {
        switch ($method) {
            case 'GET':
                return $request->get($url, $body);
            case 'PUT':
                return $request->asJson()->put($url, $body);
            case 'PATCH':
                return $request->asJson()->patch($url, $body);
            case 'DELETE':
                return $request->asJson()->delete($url, $body);
            default:
                return $request->asJson()->post($url, $body);
        }
    }

    /**
     * Kirim data dummy dengan nilai konfigurasi yang diberikan (biasanya dari
     * state form yang belum disimpan) + custom payload yang menimpa data dummy.
     */
    public function test(string $type, array $values, array $overrides = []): array
    {
        $context = $this->dummyContext($type);

        foreach ($overrides as $key => $value) {
            data_set($context, $key, $value);
        }

        $headers = $this->buildHeaders((string) ($values['headers'] ?? ''), $context);
        $secret = trim((string) ($values['secret'] ?? ''));
        if ($secret !== '') {
            $headers['X-Webhook-Secret'] = $secret;
        }

        $method = strtoupper((string) ($values['method'] ?? 'POST'));
        $url = trim((string) ($values['url'] ?? ''));

        $base = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $this->buildBody((string) ($values['body'] ?? ''), $context),
        ];

        if ($url === '') {
            $base['ok'] = false;
            $base['error'] = 'URL webhook kosong.';

            return $base;
        }

        try {
            $request = Http::timeout(10)->connectTimeout(5)->withHeaders($headers);
            $response = $this->send($request, $method, $url, $base['body']);

            $base['ok'] = $response !== null && ! $response->failed();
            $base['status'] = method_exists($response, 'status') ? $response->status() : null;

            return $base;
        } catch (\Throwable $e) {
            $base['ok'] = false;
            $base['error'] = $e->getMessage();

            return $base;
        }
    }

    protected function dummyContext(string $type): array
    {
        $customer = $type === 'corporate'
            ? [
                'customer_code' => 'K001',
                'name' => 'PT Contoh Dummy',
                'email' => 'corporate@example.com',
                'wa' => '6281234567890',
                'address' => 'Jl. Contoh No. 1',
                'is_subscription' => true,
                'monthly_fee' => 500000,
                'due_day' => 5,
            ]
            : [
                'customer_code' => 'R001',
                'name' => 'Budi Santoso Dummy',
                'email' => 'retail@example.com',
                'wa' => '6281999999999',
                'block_location' => 'Cendana',
                'full_address' => 'Jl. Cendana Blok A. 1',
                'internet_package_id' => 10,
            ];

        return [
            'event' => 'payment.received',
            'transaction' => [
                'id' => 1,
                'name' => ($type === 'corporate' ? 'SUB' : 'RTL') . '-TEST',
                'amount' => 150000,
                'description' => 'Pembayaran pengujian webhook',
                'transaction_date' => now()->format('Y-m-d H:i:s'),
                'wallet_id' => 1,
                'coa_id' => 1,
            ],
            'invoice' => [
                'no' => ($type === 'corporate' ? 'SUB-202608-0001' : 'RTL-202608-0001'),
                'total' => $type === 'corporate' ? '500000.00' : '150000.00',
                'period' => now()->startOfMonth()->format('Y-m-d'),
                'due_date' => now()->addDays(10)->format('Y-m-d'),
                'status' => 'berjalan',
            ],
            'customer' => $customer,
        ];
    }

    protected function buildHeaders(string $headersInput, array $context): array
    {
        $headers = [];

        foreach (preg_split('/\R/', $headersInput) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $headers[trim($key)] = $this->render(trim($value), $context);
        }

        return $headers;
    }

    protected function buildBody(string $bodyInput, array $context): array
    {
        $rendered = trim($this->render($bodyInput, $context));

        if ($rendered === '') {
            return $context;
        }

        $decoded = json_decode($rendered, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return $context;
    }

    public function buildContext(Model $transaction, ?Model $context = null): array
    {
        $transactionData = collect($transaction->getAttributes())
            ->except(['updated_at'])
            ->map(function ($value) {
                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d H:i:s');
                }

                return $value;
            })
            ->toArray();

        $invoice = $context ? [
            'no' => $context->invoice_no,
            'total' => (string) $context->total,
            'period' => $context->period?->format('Y-m-d'),
            'due_date' => $context->due_date?->format('Y-m-d'),
            'status' => $context->status,
        ] : null;

        $customer = $context?->customer;
        $customerData = $customer
            ? collect($customer->getAttributes())->except(['created_at', 'updated_at'])->toArray()
            : null;

        return [
            'event' => 'payment.received',
            'transaction' => $transactionData,
            'invoice' => $invoice,
            'customer' => $customerData,
        ];
    }

    protected function resolveKeys(?Model $context): ?array
    {
        if ($context instanceof SubscriptionInvoice) {
            return $this->keys('corporate');
        }

        if ($context instanceof RetailInvoice) {
            return $this->keys('retail');
        }

        return null;
    }

    protected function keys(string $type): array
    {
        $prefix = "webhook_{$type}_";

        return [
            'enabled' => "{$prefix}enabled",
            'url' => "{$prefix}url",
            'secret' => "{$prefix}secret",
            'method' => "{$prefix}method",
            'headers' => "{$prefix}headers",
            'body' => "{$prefix}body",
        ];
    }

    protected function render(string $template, array $context): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            fn ($match) => (string) data_get($context, $match[1], ''),
            $template
        );
    }
}