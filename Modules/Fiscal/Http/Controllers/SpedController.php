<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fiscal\Services\SpedIcmsIpiGeneratorService;
use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * SPED & Livros (sub-página 7 do design KB-9.75).
 *
 * Wave 8 (PR #8): gerador EFD-ICMS/IPI MVP. Endpoint download .txt CONFAZ layout.
 * PIS/COFINS (EFD-Contribuições separado) + Bloco E apuração + Bloco H inventário
 * ficam pra próximas Waves.
 */
class SpedController extends Controller
{
    public function index(): Response
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.sped.export')) {
            abort(403, 'Sem permissão fiscal.sped.export');
        }

        // Trava fail-secure: a mesma que o `gerar()` consulta. Superadmin bypassa.
        // A tela NAO decide isto — recebe decidido (o dono da regra e o servidor).
        $travaLigada  = (bool) config('fiscal.sped_simples_only_lock', true);
        $ehSuperadmin = (bool) auth()->user()->can('superadmin');

        // 5 últimos meses — contagem agregada de notas autorizadas
        $periodos = [];
        for ($i = 0; $i < 5; $i++) {
            $start = now()->startOfMonth()->subMonths($i);
            $end   = $start->copy()->endOfMonth();

            $count = NfeEmissao::query()
                ->where('status', 'autorizada')
                ->whereBetween('emitido_em', [$start, $end])
                ->count();
            $valor = (float) NfeEmissao::query()
                ->where('status', 'autorizada')
                ->whereBetween('emitido_em', [$start, $end])
                ->sum('valor_total');

            $periodos[] = [
                'mes'           => $start->format('m/Y'),
                'mesIso'        => $start->format('Y-m'),
                'notasAutorizadas' => $count,
                'valorAutorizado'  => $valor,
                'status'        => $i === 0 ? 'aberto' : ($i === 1 ? 'pronto' : 'entregue'),
                'prazoEntrega'  => $i === 0 ? null : $start->copy()->addMonth()->day(15)->format('d/m/Y'),
                'checagens'     => $this->checagens($start, $travaLigada, $ehSuperadmin),
            ];
        }

        return Inertia::render('Fiscal/Sped', [
            'periodos' => $periodos,
            'notice'   => 'SPED Fiscal (EFD ICMS-IPI) — gerador MVP saídas v3.1.1 disponível (PR #8). PIS/COFINS + Bloco E apuração + entradas em próximas Waves.',
            // Prévia do conteúdo do TXT: ausência DECLARADA, não amostra fabricada.
            // Ver Sped.charter.md §Contrato destilado (decisão [W] pendente).
            'previaTxt' => null,
        ]);
    }

    /**
     * As 4 checagens da régua de geração, avaliadas no SERVIDOR.
     *
     * Cada uma devolve `ok` + `motivo` em texto — o motivo é o que a tela põe
     * no `title` do botão desabilitado, então ele é contrato de UI, não log.
     *
     * (a) e (b) espelham `SpedIcmsIpiGeneratorService::validar()` (ano ≥ 2020,
     * não-futura); (c) espelha a guarda `competenciaFechada` adicionada na
     * mesma onda; (d) é a trava `fiscal.sped_simples_only_lock`, que o
     * `gerar()` consulta antes de devolver o arquivo. A tela nunca é a única
     * defesa: as quatro são recusadas de novo no servidor.
     *
     * @return array<int, array{id: string, ok: bool, rotulo: string, motivo: string}>
     */
    private function checagens(\Carbon\Carbon $inicioMes, bool $travaLigada, bool $ehSuperadmin): array
    {
        $ano = (int) $inicioMes->format('Y');
        $mes = (int) $inicioMes->format('n');
        $competencia = $inicioMes->format('m/Y');

        $anoOk     = $ano >= 2020;
        $futuraOk  = ! $inicioMes->copy()->startOfMonth()->isFuture();
        $fechadaOk = $inicioMes->copy()->endOfMonth()->isPast();
        $travaOk   = ! $travaLigada || $ehSuperadmin;

        return [
            [
                'id'     => 'ano-minimo',
                'ok'     => $anoOk,
                'rotulo' => 'Ano dentro da faixa aceita',
                'motivo' => $anoOk
                    ? "Ano {$ano} está na faixa aceita (2020 até " . date('Y') . ')'
                    : "Ano {$ano} é anterior a 2020 — fora da faixa que o gerador aceita",
            ],
            [
                'id'     => 'nao-futura',
                'ok'     => $futuraOk,
                'rotulo' => 'Competência não é futura',
                'motivo' => $futuraOk
                    ? "Competência {$competencia} não está no futuro"
                    : "Competência {$competencia} ainda não começou",
            ],
            [
                'id'     => 'fechada',
                'ok'     => $fechadaOk,
                'rotulo' => 'Competência encerrada',
                'motivo' => $fechadaOk
                    ? "Competência {$competencia} encerrou — período de apuração completo"
                    : "Competência {$competencia} está em aberto: o mês ainda não terminou e a EFD "
                        . 'declara DT_INI/DT_FIN do período no registro 0000 (CONFAZ v3.1.1, perfil A)',
            ],
            [
                'id'     => 'trava',
                'ok'     => $travaOk,
                'rotulo' => 'Trava do gerador liberada',
                'motivo' => $travaOk
                    ? ($travaLigada
                        ? 'Trava ligada, mas o seu perfil de superadmin a dispensa'
                        : 'Trava fiscal.sped_simples_only_lock está desligada')
                    : 'Trava fiscal.sped_simples_only_lock está ligada: o gerador ainda usa valores '
                        . 'de fallback (CST 102, CFOP 5102) que só valem em Simples Nacional sem crédito '
                        . 'de ICMS — liberar depende de decisão do responsável',
            ],
        ];
    }

    /**
     * GET /fiscal/sped/icms-ipi/{ano}/{mes} — download TXT EFD-ICMS/IPI.
     *
     * Layout CONFAZ Guia Prático v3.1.1 (perfil A).
     * Permissão: fiscal.sped.export.
     * Multi-tenant Tier 0: businessId via session (ADR 0093 + cross-tenant guard
     * no Service).
     */
    public function gerar(SpedIcmsIpiGeneratorService $service, int $ano, int $mes): HttpResponse
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.sped.export')) {
            abort(403, 'Sem permissão fiscal.sped.export');
        }

        // Onda ESTABILIZAR 2026-05-25 (audit sênior GAP-FISCAL-003): feature flag
        // bloqueia download SPED enquanto 6 hardcodes Tier-0 não eliminados no
        // SpedIcmsIpiGeneratorService. Superadmin bypass — Wagner pode forçar
        // download em emergência via /superadmin com flag bypass.
        if ((bool) config('fiscal.sped_simples_only_lock', true) && ! auth()->user()->can('superadmin')) {
            return response(
                "Download SPED temporariamente bloqueado.\n\n"
                . "Motivo: gerador atual usa hardcodes (NCM 00000000, CST 102, CFOP 5102) "
                . "que funcionam acidentalmente pra Simples Nacional sem crédito ICMS, mas "
                . "podem gerar valores incorretos em vendas interestaduais ou outros regimes "
                . "(audit sênior 2026-05-25 GAP-FISCAL-003).\n\n"
                . "Visualização /fiscal/sped continua disponível.\n"
                . "Liberação prevista após integração MotorTributarioService (~Onda CONSOLIDAR).",
                503,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $businessId = (int) session('user.business_id');

        try {
            $conteudo = $service->gerar($businessId, $ano, $mes);

            Log::info('Fiscal.sped.gerar ok', [
                'business_id' => $businessId,
                'ano'         => $ano,
                'mes'         => $mes,
                'bytes'       => strlen($conteudo),
                'linhas'      => substr_count($conteudo, "\r\n"),
            ]);

            $nomeArquivo = sprintf('EFD-ICMS-IPI-%04d-%02d.txt', $ano, $mes);

            return response($conteudo, 200, [
                'Content-Type'              => 'text/plain; charset=UTF-8',
                'Content-Disposition'       => 'attachment; filename="' . $nomeArquivo . '"',
                'Content-Length'            => (string) strlen($conteudo),
                'X-Sped-Layout-Version'     => '018', // v3.1.1
                'X-Robots-Tag'              => 'noindex',
            ]);
        } catch (\Throwable $e) {
            Log::error('Fiscal.sped.gerar falhou', [
                'business_id' => $businessId,
                'ano'         => $ano,
                'mes'         => $mes,
                'error'       => $e->getMessage(),
            ]);

            return response("Erro na geração SPED: {$e->getMessage()}", 500, ['Content-Type' => 'text/plain']);
        }
    }
}
