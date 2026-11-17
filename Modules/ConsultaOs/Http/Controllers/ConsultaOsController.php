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
use Modules\ConsultaOs\Services\EmpresaPublica;
use App\Support\Privacy\PiiRedactor;

/**
 * ConsultaOsController — portal público de consulta de OS (US-CONSULTA-001).
 *
 * Desde 2026-10-02 (decisão [W] "Ligar o ConsultaOs ao Repair") a busca lê as folhas de OS
 * reais do Modules/Repair — o mock de 4 OS fixas saiu. O antigo /repair-status passou a
 * redirecionar pra cá (CustomerRepairStatusController::index).
 *
 * Decisão [W] 2026-11-17 — "empresa na URL, celular só com série":
 * - index(): /consulta-os sem empresa. Não busca: a tela pede para abrir o link que a empresa
 *   enviou. Links genéricos (SiteHeader, /repair-status) caem aqui.
 * - empresa(): /consulta-os/{codigo}. Código desconhecido → 404. Props: `buscaPorCelular`
 *   (config) e `empresa` (o próprio código da URL) — eager, ambas baratas.
 * - buscar(): /consulta-os/{codigo}/buscar, JSON público (sem auth). Critério validado pelo
 *   ConsultaPublicaRequest e revalidado no repositório; throttle 30/min.
 *
 * Tier 0 (ADR 0093): a busca só olha a empresa do código (EmpresaPublica). Até 2026-11-17 um nº
 * válido era procurado em todas as empresas. O payload é a whitelist montada no repositório.
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
            'buscaPorCelular' => $this->buscaPorCelular(),
            'empresa' => null,
        ]);
    }

    public function empresa(string $empresa): Response
    {
        abort_if(EmpresaPublica::empresaDe($empresa) === null, 404);

        return Inertia::render('ConsultaOs/Index', [
            'buscaPorCelular' => $this->buscaPorCelular(),
            'empresa' => $empresa,
        ]);
    }

    public function buscar(ConsultaPublicaRequest $request, string $empresa): JsonResponse
    {
        $tipo = (string) $request->input('tipo');
        $numero = (string) $request->input('numero');
        $serie = $request->filled('serie') ? (string) $request->input('serie') : null;

        $businessId = EmpresaPublica::empresaDe($empresa);
        if ($businessId === null) {
            $this->auditarConsulta($request, $numero, $tipo, 'empresa_desconhecida');

            return response()->json(['found' => false], 404);
        }

        try {
            $resultado = $this->service->buscar($tipo, $numero, $serie, $businessId);
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

    private function buscaPorCelular(): bool
    {
        return in_array('mobile_num', RepairConsultaOsRepository::tiposHabilitados(), true);
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
