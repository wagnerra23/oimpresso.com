<?php

namespace Modules\Copiloto\Services\Memoria;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Contracts\MemoriaContrato;
use Modules\Copiloto\Contracts\MemoriaPersistida;

/**
 * MEM-MEM-MCP-1 (ADR 0056) — Driver MemoriaContrato que consome MCP server.
 *
 * Substitui o MeilisearchDriver direto pelo MCP server como fonte única.
 * Cliente HTTP JSON-RPC 2.0 (protocol MCP) pra `mcp.oimpresso.com/api/mcp`.
 *
 * Vantagens:
 *   - Audit log unificado (cada chamada do Copiloto chat fica em `mcp_audit_log`)
 *   - Quotas e RBAC do MCP server aplicam ao Copiloto também
 *   - Mesma fonte de memória pra Copiloto chat + Claude Code + futuros clientes
 *   - Trilha de auditoria pra compliance LGPD
 *
 * Token: usa `COPILOTO_MCP_SYSTEM_TOKEN` (server-side, não-user). Token tem
 * scope `copiloto.mcp.use` + `copiloto.mcp.memoria.read`. Wagner gera 1×
 * via /copiloto/admin/team e seta no .env.
 *
 * Fallback: se MCP indisponível (timeout/erro 5xx), degrada silenciosamente
 * pra cache local OU MeilisearchDriver direto (configurável). Default: degrade.
 */
class McpMemoriaDriver implements MemoriaContrato
{
    protected string $baseUrl;
    protected string $token;
    protected int $timeoutSeconds;
    protected ?MemoriaContrato $fallbackDriver;

    public function __construct(?MemoriaContrato $fallback = null)
    {
        $this->baseUrl = (string) config('copiloto.mcp.url', 'https://mcp.oimpresso.com/api/mcp');
        $this->token = (string) config('copiloto.mcp.system_token', env('COPILOTO_MCP_SYSTEM_TOKEN', ''));
        $this->timeoutSeconds = (int) config('copiloto.mcp.timeout_seconds', 5);
        $this->fallbackDriver = $fallback;
    }

    public function buscar(int $businessId, int $userId, string $query, int $topK = 5): array
    {
        if ($this->token === '') {
            $this->logAviso('Sem token MCP system. Pula recall.');
            return $this->fallback('buscar', [$businessId, $userId, $query, $topK]) ?? [];
        }

        try {
            $response = Http::withToken($this->token, 'Bearer')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->timeout($this->timeoutSeconds)
                ->post($this->baseUrl, [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'tools/call',
                    'params' => [
                        'name' => 'memoria-search',
                        'arguments' => [
                            'query' => $query,
                            'business_id' => $businessId,
                            'limit' => $topK,
                        ],
                    ],
                ]);

            if (! $response->ok()) {
                Log::channel('copiloto-ai')->warning('McpMemoriaDriver: HTTP non-OK', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 200),
                ]);
                return $this->fallback('buscar', [$businessId, $userId, $query, $topK]) ?? [];
            }

            $body = $response->json();
            $resultText = $this->extrairTextoResultado($body);

            if ($resultText === null || trim($resultText) === '') {
                return [];
            }

            // Parse texto retornado pra MemoriaPersistida[]
            return $this->parseResultText($resultText, $businessId, $userId);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning(
                'McpMemoriaDriver: erro ao buscar memória (degradação): ' . $e->getMessage()
            );
            return $this->fallback('buscar', [$businessId, $userId, $query, $topK]) ?? [];
        }
    }

    /**
     * Extrai o texto da resposta MCP JSON-RPC.
     * Formato: { result: { content: [{ type: 'text', text: '...' }] } }
     */
    protected function extrairTextoResultado(?array $body): ?string
    {
        if ($body === null) return null;

        $content = data_get($body, 'result.content', []);
        if (! is_array($content) || empty($content)) return null;

        $texts = array_filter(array_map(
            fn ($c) => ($c['type'] ?? null) === 'text' ? ($c['text'] ?? null) : null,
            $content
        ));

        return implode("\n", $texts);
    }

    /**
     * Parse texto markdown da MemoriaSearchTool em MemoriaPersistida[].
     * Formato esperado:
     *   ## Fato #N [categoria] · relevância M
     *   <fato>
     *   _Persistido em: YYYY-MM-DD HH:MM:SS_
     */
    protected function parseResultText(string $text, int $businessId, int $userId): array
    {
        $blocos = preg_split('/\n## Fato #/', "\n" . $text);
        $rs = [];

        foreach ($blocos as $i => $bloco) {
            if ($i === 0) continue; // header skip

            // Extract id
            if (! preg_match('/^(\d+)/', $bloco, $m)) continue;
            $id = (int) $m[1];

            // Extract metadata categoria + relevancia
            $cat = '';
            $relev = null;
            if (preg_match('/\[([^\]]+)\]/', $bloco, $mc)) $cat = $mc[1];
            if (preg_match('/relevância (\d+)/', $bloco, $mr)) $relev = (int) $mr[1];

            // Extract fato (linha após o header até "_Persistido")
            $linhas = explode("\n", $bloco);
            $fatoLines = [];
            foreach (array_slice($linhas, 1) as $l) {
                if (str_starts_with(trim($l), '_Persistido em:')) break;
                if (trim($l) !== '') $fatoLines[] = trim($l);
            }
            $fato = implode(' ', $fatoLines);

            // Extract valid_from
            $validFrom = null;
            if (preg_match('/_Persistido em: ([\d\- :]+)_/', $bloco, $mv)) {
                $validFrom = trim($mv[1]);
            }

            $rs[] = new MemoriaPersistida(
                id: $id,
                businessId: $businessId,
                userId: $userId,
                fato: $fato,
                metadata: array_filter([
                    'categoria' => $cat,
                    'relevancia' => $relev,
                ]),
                validFrom: $validFrom,
            );
        }

        return $rs;
    }

    // ---- Métodos write — usam MCP via tool específica (futuro) ou fallback ----

    public function lembrar(int $businessId, int $userId, string $fato, array $metadata = []): MemoriaPersistida
    {
        // Por enquanto: write via fallback. Futuro: tool MCP `memoria-write`.
        if ($this->fallbackDriver) {
            return $this->fallbackDriver->lembrar($businessId, $userId, $fato, $metadata);
        }
        throw new \RuntimeException('McpMemoriaDriver::lembrar requer fallback (write tool MCP TODO).');
    }

    public function atualizar(int $memoriaId, string $novoFato, array $metadata = []): void
    {
        if ($this->fallbackDriver) {
            $this->fallbackDriver->atualizar($memoriaId, $novoFato, $metadata);
            return;
        }
        throw new \RuntimeException('McpMemoriaDriver::atualizar requer fallback.');
    }

    public function esquecer(int $memoriaId): void
    {
        if ($this->fallbackDriver) {
            $this->fallbackDriver->esquecer($memoriaId);
            return;
        }
        throw new \RuntimeException('McpMemoriaDriver::esquecer requer fallback.');
    }

    public function listar(int $businessId, int $userId): array
    {
        if ($this->fallbackDriver) {
            return $this->fallbackDriver->listar($businessId, $userId);
        }
        return [];
    }

    // ---- Helpers ----

    /**
     * Chama método do fallback driver, se configurado. Retorna null se não tem.
     */
    protected function fallback(string $method, array $args): mixed
    {
        if ($this->fallbackDriver === null) return null;

        try {
            return $this->fallbackDriver->{$method}(...$args);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('McpMemoriaDriver: fallback também falhou: ' . $e->getMessage());
            return null;
        }
    }

    protected function logAviso(string $msg): void
    {
        Log::channel('copiloto-ai')->info('McpMemoriaDriver: ' . $msg);
    }
}
