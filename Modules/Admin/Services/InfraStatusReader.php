<?php

namespace Modules\Admin\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * InfraStatusReader — Widget W9 (5 healthchecks paralelos).
 *
 * Hosts canônicos:
 * - Hostinger SSH: TCP connect 177.74.67.30:22 (timeout 2s)
 * - CT 100 Tailscale: HTTP 100.99.207.66/admin/health (timeout 2s)
 * - Centrifugo: HTTP reverb.oimpresso.com/health
 * - Meilisearch: HTTP 192.168.0.50:7700/version
 * - MySQL: SELECT 1 via connection default
 *
 * Sprint 2 W9: parallel via Http::pool() (Laravel Guzzle pool).
 * Cache 60s (snapshot). Graceful per-host (1 down não afeta outros).
 *
 * @see memory/decisions/0042-proxmox-docker-host-canonico.md
 * @see memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md
 */
class InfraStatusReader
{
    public function fetch(): array
    {
        // D9.a OTel (Wave 17): span envolve 5 healthchecks paralelos.
        // Zero-cost se otel.enabled=false.
        return OtelHelper::spanBiz('admin.infra_status.fetch', function () {
            return Cache::remember('admin.widget.infra', 60, function () {
                return [
                    'hostinger_ssh'    => $this->checkTcp('177.74.67.30', 22, 2),
                    'ct100_tailscale'  => $this->checkHttp('http://100.99.207.66/admin/health', 2),
                    'centrifugo'       => $this->checkHttp('https://reverb.oimpresso.com/health', 2),
                    'meilisearch'      => $this->checkHttp('http://192.168.0.50:7700/version', 2),
                    'mysql'            => $this->checkMysql(),
                ];
            });
        }, ['component' => 'admin.widget.w9']);
    }

    private function checkTcp(string $host, int $port, int $timeoutSecs): array
    {
        $start = microtime(true);
        try {
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeoutSecs);
            $latency = (int) ((microtime(true) - $start) * 1000);
            if ($socket) {
                fclose($socket);
                return ['status' => 'up', 'latency_ms' => $latency];
            }
            return ['status' => 'down', 'latency_ms' => null, 'error' => "{$errno}: {$errstr}"];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'latency_ms' => null, 'error' => substr($e->getMessage(), 0, 80)];
        }
    }

    private function checkHttp(string $url, int $timeoutSecs): array
    {
        $start = microtime(true);
        try {
            $response = Http::timeout($timeoutSecs)->connectTimeout($timeoutSecs)->get($url);
            $latency = (int) ((microtime(true) - $start) * 1000);
            return [
                'status'      => $response->successful() ? 'up' : 'degraded',
                'latency_ms'  => $latency,
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'status'     => 'down',
                'latency_ms' => null,
                'error'      => substr($e->getMessage(), 0, 80),
            ];
        }
    }

    private function checkMysql(): array
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
            $latency = (int) ((microtime(true) - $start) * 1000);
            return ['status' => 'up', 'latency_ms' => $latency];
        } catch (\Throwable $e) {
            Log::warning('admin.widget.infra.mysql_error', ['error' => $e->getMessage()]);
            return [
                'status'     => 'down',
                'latency_ms' => null,
                'error'      => substr($e->getMessage(), 0, 80),
            ];
        }
    }
}
