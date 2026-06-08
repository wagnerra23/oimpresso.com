<?php

namespace Modules\ConsultaOs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ConsultaOs\Http\Requests\ConsultaPublicaRequest;
use Modules\ConsultaOs\Services\ConsultaOsMockService;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * ConsultaOsController — Portal publico de consulta de OS (mock-only).
 *
 * Arquitetura (SoC — D4):
 *
 * - index(): boot do Inertia React (zero props — pagina opera 100% client-side via
 *   useState; nao usa Inertia partial reload). D6.a Inertia::defer marcado N/A
 *   justificado no SPEC.md (frontmatter `na_justified.D6.a`, ADR 0155).
 *
 * - buscar(): endpoint JSON publico (sem auth). Validacao anti-enumeration via
 *   ConsultaPublicaRequest (FormRequest dedicado — D8.c): `alpha_num` + `max:20`
 *   + lista controlada de estagios. Throttle/rate-limit aplicado via middleware
 *   na rota (defesa em profundidade — TODO infra na US-CONSULTA-001).
 *
 * Status: mock-only ate US-CONSULTA-001 substituir mockData() por query real em
 * Modules/Repair via Service read-only (canary 7d ROTA LIVRE antes outros tenants).
 * Mapping pendente Wagner: invoice_no + ultimos 4 do telefone (padrao Repair).
 *
 * Tier 0 multi-tenant: consulta publica por protocolo unico globally — NAO scopa
 * por business_id hoje. Quando US-CONSULTA-001 ativar busca real, Service deve
 * resolver business_id via lookup do protocolo + rate limit por IP.
 *
 * @see memory/requisitos/ConsultaOs/SPEC.md
 * @see memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md §188 (D6.a N/A pattern)
 * @see Modules\ConsultaOs\Http\Requests\ConsultaPublicaRequest (D8.c FormRequest)
 */
class ConsultaOsController extends Controller
{
    public function __construct(
        private readonly ConsultaOsMockService $service,
    ) {
    }

    public function index(): Response
    {
        // D6.a N/A justified — zero props (pagina React opera client-state + fetch
        // JSON via @buscar). Quando US-CONSULTA-001 entregar payload real via Inertia
        // props, aplicar Inertia::defer pattern (RUNBOOK-inertia-defer-pattern.md).
        return Inertia::render('ConsultaOs/Index');
    }

    public function buscar(ConsultaPublicaRequest $request): JsonResponse
    {
        $numero  = $request->input('numero');
        $estagio = $request->input('estagio', 'todos');

        // Wave 18 D4 — Service-driven (SoC brutal). Controller responsabiliza-se
        // apenas por validacao (ConsultaPublicaRequest) + auditoria. Mock vs real
        // e decidido no Provider via bind(ConsultaOsRepositoryInterface).
        $resultado = $this->service->buscar($numero, $estagio);

        if (! $resultado['found']) {
            $this->auditarConsulta($request, $numero, $estagio, $resultado['reason'] ?? 'not_found');

            return response()->json(['found' => false], 404);
        }

        $this->auditarConsulta($request, $numero, $estagio, 'found');

        return response()->json([
            'found' => true,
            'os'    => $resultado['os'],
        ]);
    }

    /**
     * Audit log estruturado da busca publica (D7.a — PiiRedactor + LGPD compliance).
     *
     * Registra IP truncado (/24 anti-tracking), numero da OS redacted via PiiRedactor
     * (cobre CPF/CNPJ/email/telefone caso usuario cole no campo errado), User-Agent
     * truncado a 80 chars, resultado e timestamp. Retencao 365d conforme
     * `Modules/ConsultaOs/Config/retention.php` (consulta_os_logs).
     *
     * NAO loga: business_id (rota publica nao tem sessao), dados da OS encontrada
     * (mock-only nao tem PII real; query-real fase US-CONSULTA-001 manter mesma regra).
     *
     * LGPD Art. 5º §II — registro tecnico de seguranca da rede (necessidade legitima
     * + nao requer aviso previo ao titular conforme retention.notice_period_days=0).
     */
    private function auditarConsulta(
        ConsultaPublicaRequest $request,
        string $numero,
        string $estagio,
        string $resultado
    ): void {
        $redactor = app(PiiRedactor::class);

        // IP truncado /24 (192.168.1.X → 192.168.1.0) — anti-tracking individual.
        $ip = $request->ip() ?? '0.0.0.0';
        $ipTruncado = $this->truncarIp($ip);

        Log::channel(config('logging.default'))->info('consultaos.busca_publica', [
            'numero_redacted' => $redactor->redact($numero),
            'estagio'         => $estagio,
            'resultado'       => $resultado,
            'ip_truncado'     => $ipTruncado,
            'user_agent'      => substr((string) $request->userAgent(), 0, 80),
            'timestamp'       => now()->toIso8601String(),
        ]);
    }

    /**
     * Trunca IP pra /24 (IPv4) ou /48 (IPv6) — preserva utilidade analytics
     * (regiao geografica) sem identificar individuo (LGPD pseudonimizacao).
     */
    private function truncarIp(string $ip): string
    {
        if (str_contains($ip, ':')) {
            // IPv6 — manter primeiros 3 grupos (/48)
            $partes = explode(':', $ip);

            return implode(':', array_slice($partes, 0, 3)).'::';
        }

        // IPv4 — zerar ultimo octeto (/24)
        $partes = explode('.', $ip);
        if (count($partes) === 4) {
            $partes[3] = '0';

            return implode('.', $partes);
        }

        return '0.0.0.0';
    }

    // mockData() removido Wave 18 — extraido pra MockConsultaOsRepository
    // (Repository pattern + bind no Provider). Trocar fonte = 1 linha (US-CONSULTA-001).
}
