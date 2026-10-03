<?php

namespace Modules\ConsultaOs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ConsultaOs\Http\Requests\ConsultaPublicaRequest;
use Modules\ConsultaOs\Repositories\RepairConsultaOsRepository;
use Modules\ConsultaOs\Services\ConsultaOsService;
use App\Support\Privacy\PiiRedactor;

/**
 * ConsultaOsController — portal público de consulta de OS (US-CONSULTA-001).
 *
 * Desde 2026-10-02 (decisão [W] "Ligar o ConsultaOs ao Repair") a busca lê as folhas de OS
 * reais do Modules/Repair — o mock de 4 OS fixas saiu. O antigo /repair-status passou a
 * redirecionar pra cá (CustomerRepairStatusController::index).
 *
 * - index(): boot do Inertia React. Uma prop só, `buscaPorCelular` — leitura de config,
 *   barata, eager (exceção do Inertia::defer pra config static).
 * - buscar(): JSON público (sem auth). Critério validado pelo ConsultaPublicaRequest
 *   (tipo em lista fechada + número não vazio) e revalidado no repositório; throttle 30/min.
 *
 * Tier 0 (ADR 0093): sem business_id — o portal não sabe a empresa do cliente; um nº válido
 * é procurado em todas as empresas (igual ao /repair-status). PENDENTE [W], ver o docblock do
 * RepairConsultaOsRepository. O payload é a whitelist montada no repositório.
 *
 * @see memory/requisitos/ConsultaOs/SPEC.md
 * @see Modules\ConsultaOs\Repositories\RepairConsultaOsRepository
 */
class ConsultaOsController extends Controller
{
    public function __construct(
        private readonly ConsultaOsService $service,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('ConsultaOs/Index', [
            'buscaPorCelular' => in_array('mobile_num', RepairConsultaOsRepository::tiposHabilitados(), true),
        ]);
    }

    public function buscar(ConsultaPublicaRequest $request): JsonResponse
    {
        $tipo = (string) $request->input('tipo');
        $numero = (string) $request->input('numero');
        $serie = $request->filled('serie') ? (string) $request->input('serie') : null;

        try {
            $resultado = $this->service->buscar($tipo, $numero, $serie);
        } catch (\Throwable $e) {
            // Mesma postura do #8527: nada de detalhe técnico pro público.
            Log::error('consultaos.busca_falhou', ['erro' => class_basename($e)]);
            $this->auditarConsulta($request, $numero, $tipo, 'erro');

            return response()->json(['found' => false], 500);
        }

        if (! $resultado['found']) {
            $this->auditarConsulta($request, $numero, $tipo, $resultado['reason'] ?? 'not_found');

            return response()->json(['found' => false], 404);
        }

        $this->auditarConsulta($request, $numero, $tipo, 'found');

        return response()->json([
            'found' => true,
            'ordens' => $resultado['ordens'],
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
     * NAO loga: business_id (rota publica nao tem sessao) nem dados da OS encontrada.
     *
     * LGPD Art. 5º §II — registro tecnico de seguranca da rede (necessidade legitima
     * + nao requer aviso previo ao titular conforme retention.notice_period_days=0).
     */
    private function auditarConsulta(
        ConsultaPublicaRequest $request,
        string $numero,
        string $tipo,
        string $resultado
    ): void {
        $redactor = app(PiiRedactor::class);

        // IP truncado /24 (192.168.1.X → 192.168.1.0) — anti-tracking individual.
        $ip = $request->ip() ?? '0.0.0.0';
        $ipTruncado = $this->truncarIp($ip);

        Log::channel(config('logging.default'))->info('consultaos.busca_publica', [
            'numero_redacted' => $redactor->redact($numero),
            'tipo'            => $tipo,
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
}
