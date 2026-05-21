<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Contact;
use App\Http\Controllers\Controller;
use App\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Crm\Ai\Agents\ClienteProximaAcaoAgent;
use Modules\Crm\Ai\Agents\ClienteResumoAgent;
use Modules\Crm\Ai\Agents\ClienteSegmentoAgent;

/**
 * ClienteIaController -- Wave E Tab IA (ADR 0179 Q4 Default ON pra todos).
 *
 * 4 endpoints do Copiloto de cliente:
 *   POST /cliente/{id}/ia/resumo         -> ClienteResumoAgent (LLM, Haiku)
 *   POST /cliente/{id}/ia/segmento       -> ClienteSegmentoAgent (LLM, structured)
 *   POST /cliente/{id}/ia/proxima-acao   -> ClienteProximaAcaoAgent (LLM, structured)
 *   GET  /cliente/{id}/ia/score-risco    -> deterministico (zero LLM, 8 sinais)
 *
 * Multi-tenant Tier 0 ADR 0093 IRREVOGAVEL:
 *   Contact::where('business_id', $bizId)->where('id', $id)->firstOrFail()
 *   -> 404 se cross-tenant (nao vaza existencia, igual ClienteAutosaveController).
 *
 * Permission gate matricial (apenas LEITURA -- IA so le e propoe, nao escreve):
 *   customer.view ou customer.view_own (+ supplier.view/supplier.view_own).
 *
 * Cache (Redis quando disponivel):
 *   Key: cliente_ia:{tipo}:{business_id}:{contact_id}
 *   TTL: 6h (resumo/segmento/proxima-acao) | 24h (score-risco)
 *   Force refresh: ?force=true (resumo/segmento/proxima-acao -- nao score-risco).
 *
 * Mock mode pra tests (zero custo LLM):
 *   config('copiloto.dry_run')=true OU config('copiloto.cliente_ia.force_mock')=true
 *   -> retorna fixtures determinasticos. Mesma estrategia de LaravelAiSdkDriver.
 *
 * PII LGPD (ADR 0093 §LGPD Art.7):
 *   - Prompts NUNCA contem tax_number plain (passamos masked).
 *   - Prompts NUNCA contem email/telefone plain (passamos flags has_email, has_mobile).
 *   - Logs estruturados via channel 'copiloto-ai' (canon Jana).
 *
 * Telemetria custo (Wagner monitora Brain B):
 *   Log::channel('copiloto-ai')->info('cliente_ia.call', [...]) em cada chamada LLM.
 *
 * @see memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md §Q4
 * @see Modules/Crm/Ai/Agents/Cliente*Agent.php (3 LLM agents)
 * @see resources/js/Pages/Cliente/_show/RiscoClienteCard.tsx (mirror frontend score)
 */
class ClienteIaController extends Controller
{
    /** Cache TTLs em segundos. */
    private const TTL_LLM = 21600;          // 6h -- resumo/segmento/proxima
    private const TTL_SCORE_RISCO = 86400;  // 24h -- determinismo permite cache mais longo

    /** Timeout pro LLM (Haiku 4.5 + cache eh rapido; safety net 5s). */
    private const TIMEOUT_LLM_SECONDS = 5;

    /**
     * POST /cliente/{id}/ia/resumo
     *
     * Body opcional: { force: true } -- ignora cache.
     * Response 200: { sumario, generated_at, fonte }
     * Response 503: { error } -- IA indisponivel (timeout/exception).
     */
    public function resumo(Request $request, int $id): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact; // JsonResponse 404/403
        }

        $force = (bool) $request->boolean('force', false);
        $cacheKey = "cliente_ia:resumo:{$contact->business_id}:{$contact->id}";

        if (! $force && ($cached = Cache::get($cacheKey))) {
            return response()->json($cached);
        }

        if ($this->isMockMode()) {
            $payload = $this->fixtureResumo($contact);
            Cache::put($cacheKey, $payload, self::TTL_LLM);

            return response()->json($payload);
        }

        $dados = $this->prepararDadosCliente($contact);

        try {
            $agent = new ClienteResumoAgent($dados);
            $response = $agent->prompt($agent->montarPrompt(), timeout: self::TIMEOUT_LLM_SECONDS);
            $sumario = trim((string) $response);

            if ($sumario === '') {
                throw new \RuntimeException('Resposta vazia do agente');
            }

            $payload = [
                'sumario' => $sumario,
                'generated_at' => now()->toIso8601String(),
                'fonte' => 'jana-haiku',
            ];

            Cache::put($cacheKey, $payload, self::TTL_LLM);
            $this->logCustoIa('resumo', $contact, $response->usage ?? null);

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('cliente_ia.resumo error', [
                'business_id' => $contact->business_id,
                'contact_id' => $contact->id,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);

            return response()->json([
                'error' => 'IA indisponivel -- tente novamente em 1min',
            ], 503);
        }
    }

    /**
     * POST /cliente/{id}/ia/segmento
     *
     * Response 200: { segmento_sugerido, tags_sugeridas, justificativa, generated_at }
     * Response 503: { error }
     */
    public function segmento(Request $request, int $id): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact;
        }

        $force = (bool) $request->boolean('force', false);
        $cacheKey = "cliente_ia:segmento:{$contact->business_id}:{$contact->id}";

        if (! $force && ($cached = Cache::get($cacheKey))) {
            return response()->json($cached);
        }

        if ($this->isMockMode()) {
            $payload = $this->fixtureSegmento($contact);
            Cache::put($cacheKey, $payload, self::TTL_LLM);

            return response()->json($payload);
        }

        $dados = $this->prepararDadosCliente($contact);

        try {
            $agent = new ClienteSegmentoAgent($dados);
            $response = $agent->prompt($agent->montarPrompt(), timeout: self::TIMEOUT_LLM_SECONDS);

            // HasStructuredOutput -- response e array assoc.
            $data = is_array($response) ? $response : (array) $response;

            // Defensive shape check -- se LLM retornar shape invalido, fixture fallback.
            if (! isset($data['segmento_sugerido']) || ! isset($data['tags_sugeridas']) || ! isset($data['justificativa'])) {
                Log::channel('copiloto-ai')->warning('cliente_ia.segmento shape invalido', [
                    'business_id' => $contact->business_id,
                    'contact_id' => $contact->id,
                ]);
                $data = $this->fixtureSegmento($contact);
            } else {
                $data['generated_at'] = now()->toIso8601String();
            }

            Cache::put($cacheKey, $data, self::TTL_LLM);
            $this->logCustoIa('segmento', $contact, $response->usage ?? null);

            return response()->json($data);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('cliente_ia.segmento error', [
                'business_id' => $contact->business_id,
                'contact_id' => $contact->id,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);

            return response()->json([
                'error' => 'IA indisponivel -- tente novamente em 1min',
            ], 503);
        }
    }

    /**
     * POST /cliente/{id}/ia/proxima-acao
     *
     * Response 200: { acao, urgencia, justificativa, sugerido_em }
     * Response 503: { error }
     */
    public function proximaAcao(Request $request, int $id): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact;
        }

        $force = (bool) $request->boolean('force', false);
        $cacheKey = "cliente_ia:proxima_acao:{$contact->business_id}:{$contact->id}";

        if (! $force && ($cached = Cache::get($cacheKey))) {
            return response()->json($cached);
        }

        if ($this->isMockMode()) {
            $payload = $this->fixtureProximaAcao($contact);
            Cache::put($cacheKey, $payload, self::TTL_LLM);

            return response()->json($payload);
        }

        $dados = $this->prepararDadosCliente($contact);

        try {
            $agent = new ClienteProximaAcaoAgent($dados);
            $response = $agent->prompt($agent->montarPrompt(), timeout: self::TIMEOUT_LLM_SECONDS);

            $data = is_array($response) ? $response : (array) $response;

            if (! isset($data['acao']) || ! isset($data['urgencia']) || ! isset($data['justificativa'])) {
                Log::channel('copiloto-ai')->warning('cliente_ia.proxima_acao shape invalido', [
                    'business_id' => $contact->business_id,
                    'contact_id' => $contact->id,
                ]);
                $data = $this->fixtureProximaAcao($contact);
            } else {
                $data['sugerido_em'] = now()->toIso8601String();
            }

            Cache::put($cacheKey, $data, self::TTL_LLM);
            $this->logCustoIa('proxima_acao', $contact, $response->usage ?? null);

            return response()->json($data);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('cliente_ia.proxima_acao error', [
                'business_id' => $contact->business_id,
                'contact_id' => $contact->id,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);

            return response()->json([
                'error' => 'IA indisponivel -- tente novamente em 1min',
            ], 503);
        }
    }

    /**
     * GET /cliente/{id}/ia/score-risco
     *
     * Deterministico (zero LLM). 8 sinais com pesos canon (espelha o componente
     * frontend resources/js/Pages/Cliente/_show/RiscoClienteCard.tsx).
     *
     * Response 200: { score, label, breakdown:[{key,label,weight,active}], generated_at }
     * Latencia target < 100ms (cache 24h opcional).
     */
    public function scoreRisco(int $id): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact;
        }

        $cacheKey = "cliente_ia:score_risco:{$contact->business_id}:{$contact->id}";
        if ($cached = Cache::get($cacheKey)) {
            return response()->json($cached);
        }

        $stats = $this->calcularStatsCliente($contact);
        $invoiceDue = (float) $stats['invoice_due'];
        $totalInvoice = (float) $stats['total_invoice'];
        $diasUltimaCompra = $stats['dias_ultima_compra'];
        $diasDesdeCriacao = $stats['dias_desde_criacao'];

        // Peso linear ate +3 acima de R$ 1k; +2 com saldo > 0; 0 senao.
        $saldoWeight = $invoiceDue > 1000 ? 3 : ($invoiceDue > 0 ? 2 : 0);

        $breakdown = [
            [
                'key' => 'saldo',
                'label' => $invoiceDue > 0
                    ? 'Saldo a receber R$ ' . number_format($invoiceDue, 2, ',', '.')
                    : 'Saldo a receber > R$ 0',
                'weight' => $saldoWeight,
                'active' => $invoiceDue > 0,
            ],
            [
                'key' => 'sem_compra_90',
                'label' => $diasUltimaCompra !== null
                    ? "Sem compra ha {$diasUltimaCompra} dias"
                    : 'Sem compra > 90d',
                'weight' => 2,
                'active' => $diasUltimaCompra !== null && $diasUltimaCompra > 90 && $diasUltimaCompra <= 180,
            ],
            [
                'key' => 'sem_compra_180',
                'label' => $diasUltimaCompra !== null
                    ? "Sem compra ha {$diasUltimaCompra} dias (cliente esfriou)"
                    : 'Sem compra > 180d',
                'weight' => 3,
                'active' => $diasUltimaCompra !== null && $diasUltimaCompra > 180,
            ],
            [
                'key' => 'inativo',
                'label' => 'Cliente inativo',
                'weight' => 2,
                'active' => ($contact->contact_status ?? null) === 'inactive',
            ],
            [
                'key' => 'sem_contato',
                'label' => 'Sem email nem celular cadastrados',
                'weight' => 1,
                'active' => empty($contact->email) && empty($contact->mobile) && empty($contact->landline),
            ],
            [
                'key' => 'pj_sem_ie',
                'label' => 'PJ contribuinte sem inscricao estadual',
                'weight' => 1,
                'active' => ($contact->type === 'customer')
                    && empty($contact->ie)
                    && ((bool) ($contact->contribuinte ?? false) === true),
            ],
            [
                'key' => 'sem_localidade',
                'label' => 'Sem cidade ou estado preenchido',
                'weight' => 0.5,
                'active' => empty($contact->city) || empty($contact->state),
            ],
            [
                'key' => 'cadastro_velho_sem_compra',
                'label' => 'Cadastrado ha > 365d e nunca comprou',
                'weight' => 1,
                'active' => $diasDesdeCriacao !== null
                    && $diasDesdeCriacao > 365
                    && $totalInvoice === 0.0,
            ],
        ];

        $rawScore = 0.0;
        foreach ($breakdown as $signal) {
            if ($signal['active']) {
                $rawScore += (float) $signal['weight'];
            }
        }

        // Escala invertida: score 0-10 onde 10 = saudavel, 0 = risco alto.
        // Para alinhar com a UX do header do drawer ("cliente fiel" alto, "risco alto" baixo).
        $score = max(0.0, min(10.0, 10.0 - $rawScore));

        $label = match (true) {
            $score >= 8.5 => 'cliente fiel',
            $score >= 6.0 => 'risco baixo',
            default => 'risco alto',
        };

        $payload = [
            'score' => round($score, 1),
            'label' => $label,
            'breakdown' => $breakdown,
            'generated_at' => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $payload, self::TTL_SCORE_RISCO);

        return response()->json($payload);
    }

    // -----------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------

    /**
     * Localiza contact com scope multi-tenant Tier 0 + permission gate.
     * Pattern espelha ClienteAutosaveController::locateContact mas com gate
     * de LEITURA (customer.view) em vez de update.
     *
     * @return Contact|JsonResponse Contact em sucesso, JsonResponse 404/403 em falha.
     */
    private function locateContact(int $id): Contact|JsonResponse
    {
        $businessId = (int) request()->session()->get('user.business_id');

        try {
            $contact = Contact::where('business_id', $businessId)
                ->where('id', $id)
                ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Cliente nao encontrado'], 404);
        }

        $user = auth()->user();
        $type = (string) ($contact->type ?? 'customer');
        $canCustomer = $user->can('customer.view') || $user->can('customer.view_own');
        $canSupplier = $user->can('supplier.view') || $user->can('supplier.view_own');

        $allowed = match ($type) {
            'supplier' => $canSupplier,
            'customer' => $canCustomer,
            'both' => ($canCustomer || $canSupplier),
            default => false,
        };

        if (! $allowed) {
            return response()->json(['message' => 'Sem permissao'], 403);
        }

        return $contact;
    }

    /**
     * Prepara dados sanitizados pro prompt LLM (sem PII plain).
     *
     * @return array<string,mixed>
     */
    private function prepararDadosCliente(Contact $contact): array
    {
        $stats = $this->calcularStatsCliente($contact);

        // nome_curto: primeiro nome ou primeira palavra (evita logar nome completo
        // se for sensivel). Mais defensivo que enviar tudo.
        $nomeCurto = trim(explode(' ', trim((string) $contact->name))[0] ?? 'Cliente');

        // Decodifica tags JSON se string.
        $tags = $contact->tags ?? [];
        if (is_string($tags) && $tags !== '') {
            $decoded = json_decode($tags, true);
            $tags = is_array($decoded) ? array_values($decoded) : [];
        }

        return [
            'nome_curto' => $nomeCurto,
            'tipo' => $contact->tipo ?? ($contact->type === 'supplier' ? 'fornecedor' : 'cliente'),
            'cidade' => $contact->city ?? '?',
            'uf' => $contact->state ?? '?',
            'status' => $contact->contact_status ?? 'active',
            'segmento' => $contact->segmento ?? null,
            'tags' => $tags,
            'total_os' => (int) ($stats['total_invoice_count'] ?? 0),
            'ticket_medio' => (float) ($stats['ticket_medio'] ?? 0),
            'saldo_aberto' => (float) ($stats['invoice_due'] ?? 0),
            'dias_ultima_compra' => $stats['dias_ultima_compra'],
        ];
    }

    /**
     * Calcula stats reais a partir de Transaction (sells). Multi-tenant scope
     * obrigatorio: business_id alinhado com $contact->business_id.
     *
     * @return array<string,mixed>
     */
    private function calcularStatsCliente(Contact $contact): array
    {
        $businessId = (int) $contact->business_id;

        // Defensive: se Transaction nao existir ou DB nao migrado, retorna zeros.
        if (! class_exists(Transaction::class)) {
            return $this->statsZerados($contact);
        }

        try {
            $agg = Transaction::where('business_id', $businessId)
                ->where('contact_id', $contact->id)
                ->where('type', 'sell')
                ->whereIn('status', ['final'])
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(final_total), 0) as total, MAX(transaction_date) as ultima')
                ->first();

            $cnt = (int) ($agg->cnt ?? 0);
            $total = (float) ($agg->total ?? 0);
            $ticketMedio = $cnt > 0 ? $total / $cnt : 0.0;

            // Saldo aberto: soma final_total - amount_paid de payment_status nao paid.
            $saldoAgg = Transaction::where('business_id', $businessId)
                ->where('contact_id', $contact->id)
                ->where('type', 'sell')
                ->whereIn('payment_status', ['due', 'partial', 'overdue', 'partial-overdue'])
                ->selectRaw('COALESCE(SUM(final_total - COALESCE(total_paid, 0)), 0) as saldo')
                ->first();
            $saldo = max(0.0, (float) ($saldoAgg->saldo ?? 0));

            $diasUltima = null;
            if (! empty($agg->ultima)) {
                try {
                    $diasUltima = (int) Carbon::parse($agg->ultima)->diffInDays(now());
                } catch (\Throwable $e) {
                    $diasUltima = null;
                }
            }

            $diasDesdeCriacao = null;
            if (! empty($contact->created_at)) {
                try {
                    $diasDesdeCriacao = (int) Carbon::parse($contact->created_at)->diffInDays(now());
                } catch (\Throwable $e) {
                    $diasDesdeCriacao = null;
                }
            }

            return [
                'total_invoice' => $total,
                'total_invoice_count' => $cnt,
                'ticket_medio' => $ticketMedio,
                'invoice_due' => $saldo,
                'dias_ultima_compra' => $diasUltima,
                'dias_desde_criacao' => $diasDesdeCriacao,
            ];
        } catch (\Throwable $e) {
            // Degradacao silenciosa -- score-risco usa zeros se DB nao tem table.
            Log::channel('copiloto-ai')->debug('cliente_ia.stats fallback zeros: ' . $e->getMessage());

            return $this->statsZerados($contact);
        }
    }

    private function statsZerados(Contact $contact): array
    {
        $diasDesdeCriacao = null;
        if (! empty($contact->created_at)) {
            try {
                $diasDesdeCriacao = (int) Carbon::parse($contact->created_at)->diffInDays(now());
            } catch (\Throwable $e) {
                $diasDesdeCriacao = null;
            }
        }

        return [
            'total_invoice' => 0.0,
            'total_invoice_count' => 0,
            'ticket_medio' => 0.0,
            'invoice_due' => 0.0,
            'dias_ultima_compra' => null,
            'dias_desde_criacao' => $diasDesdeCriacao,
        ];
    }

    /**
     * Detecta mock mode -- usado em tests pra evitar custo LLM real.
     * Pattern espelha LaravelAiSdkDriver::responderChat() que checa
     * config('copiloto.dry_run').
     */
    private function isMockMode(): bool
    {
        return (bool) config('copiloto.dry_run', false)
            || (bool) config('copiloto.cliente_ia.force_mock', false)
            || (bool) env('CLIENTE_IA_FORCE_MOCK', false);
    }

    /**
     * Telemetria custo Brain B -- Wagner monitora via log channel.
     * Pattern espelha LaravelAiSdkDriver::logPromptCacheUsage.
     */
    private function logCustoIa(string $tipo, Contact $contact, mixed $usage): void
    {
        try {
            Log::channel('copiloto-ai')->info('cliente_ia.call', [
                'tipo' => $tipo,
                'business_id' => $contact->business_id,
                'contact_id' => $contact->id,
                'tokens_in' => (int) ($usage->promptTokens ?? 0),
                'tokens_out' => (int) ($usage->completionTokens ?? 0),
                'cache_read_tokens' => (int) ($usage->cacheReadInputTokens ?? 0),
                'cache_write_tokens' => (int) ($usage->cacheWriteInputTokens ?? 0),
            ]);
        } catch (\Throwable $e) {
            // Telemetria nunca quebra request.
        }
    }

    // -----------------------------------------------------------------
    // Fixtures pra mock mode (zero custo LLM em tests + canary)
    // -----------------------------------------------------------------

    private function fixtureResumo(Contact $contact): array
    {
        $nomeCurto = trim(explode(' ', trim((string) $contact->name))[0] ?? 'Cliente');

        return [
            'sumario' => "Relacionamento com {$nomeCurto} esta saudavel. Sem saldo em aberto recente e historico de compras regular.",
            'generated_at' => now()->toIso8601String(),
            'fonte' => 'fixture-mock',
        ];
    }

    private function fixtureSegmento(Contact $contact): array
    {
        return [
            'segmento_sugerido' => $contact->segmento ?? 'varejo',
            'tags_sugeridas' => ['novo', 'potencial'],
            'justificativa' => 'Sinal fraco -- mantida classificacao atual ate ter mais historico.',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function fixtureProximaAcao(Contact $contact): array
    {
        return [
            'acao' => 'Manter contato bimestral de rotina',
            'urgencia' => 'baixa',
            'justificativa' => 'Cliente sem sinais de risco -- cadencia padrao basta.',
            'sugerido_em' => now()->toIso8601String(),
        ];
    }
}
