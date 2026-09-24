<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class Midtrans
{
    public function ready(): void
    {
        abort_unless(config('services.midtrans.enabled') && filled(config('services.midtrans.server_key')), 503, 'Pembayaran Midtrans belum dikonfigurasi oleh pengelola.');
    }

    public function request(string $method, string $path, array $data = [], bool $snap = false): array
    {
        $this->ready();
        $sandbox = config('services.midtrans.production') ? '' : '.sandbox';
        $base = $snap ? 'https://app'.$sandbox.'.midtrans.com/snap/v1' : 'https://api'.$sandbox.'.midtrans.com/v2';
        try {
            $response = Http::withBasicAuth(config('services.midtrans.server_key'), '')
                ->acceptJson()->asJson()->connectTimeout(5)->timeout(15)
                ->send($method, $base.$path, $method === 'POST' ? ['json' => $data] : []);
        } catch (ConnectionException) {
            abort(503, 'Midtrans belum dapat dihubungi. Coba lagi dengan booking yang sama.');
        }
        if (! $snap && ($response->status() === 404 || $response->json('status_code') === '404')) {
            return ['transaction_status' => 'not_found'];
        }
        abort_unless($response->successful() && is_array($response->json()), 502, 'Permintaan ke Midtrans belum berhasil. Periksa konfigurasi atau coba kembali.');

        return $response->json();
    }

    public function validRedirect(string $url): bool
    {
        $host = config('services.midtrans.production') ? 'app.midtrans.com' : 'app.sandbox.midtrans.com';

        return parse_url($url, PHP_URL_SCHEME) === 'https' && parse_url($url, PHP_URL_HOST) === $host;
    }
}
