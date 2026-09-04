<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fiscal\Services\SpedIcmsIpiGeneratorService;
use Modules\Fiscal\Services\SpedReferenciaArquivoService;
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
    /**
     * Chave de sessão do bypass de superadmin.
     *
     * `true` = o superadmin REATIVOU a trava pra si nesta sessão. Ausente ou
     * `false` = comportamento de sempre (superadmin dispensa a trava).
     *
     * O default é a ausência de propósito: a ação só consegue RESTRINGIR o que já
     * era permitido, nunca afrouxar. Por isso o `UC-FSPED-09 · superadmin bypassa`
     * — que baixa direto, sem passar pela tela — continua verde.
     */
    private const SESSAO_TRAVA_REATIVADA = 'fiscal.sped.trava_reativada';

    public function index(SpedReferenciaArquivoService $referencia): Response
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.sped.export')) {
            abort(403, 'Sem permissão fiscal.sped.export');
        }

        // Trava fail-secure: a mesma que o `gerar()` consulta. Superadmin bypassa.
        // A tela NAO decide isto — recebe decidido (o dono da regra e o servidor).
        $travaLigada  = (bool) config('fiscal.sped_simples_only_lock', true);
        // O superadmin dispensa a trava — a menos que tenha REATIVADO ele mesmo,
        // nesta sessão, pelo botão da barra de validação (Onda 10).
        $ehSuperadmin = (bool) auth()->user()->can('superadmin') && ! $this->travaReativada();

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
                'checagens'     => $this->checagens(
                    $start,
                    $travaLigada,
                    $ehSuperadmin,
                    (bool) auth()->user()->can('superadmin') && $this->travaReativada(),
                ),
            ];
        }

        return Inertia::render('Fiscal/Sped', [
            'periodos' => $periodos,
            'notice'   => 'SPED Fiscal (EFD ICMS-IPI) — gerador MVP saídas v3.1.1 disponível (PR #8). PIS/COFINS + Bloco E apuração + entradas em próximas Waves.',
            // Prévia do conteúdo do TXT: ausência DECLARADA, não amostra fabricada.
            // Ver Sped.charter.md §Contrato destilado (decisão [W] pendente).
            'previaTxt' => null,
            // Onda 10 · Goals 4 e 5 do charter do Cowork. As duas props são MEDIDAS
            // no golden a cada request (SpedReferenciaArquivoService) — a tela nunca
            // afirma estrutura nem estado de validação por conta própria. Ver o
            // docblock do serviço para por que o arquivo mora em Tests/Fixtures.
            'referenciaArquivo' => $referencia->referencia(),
            'validacaoExterna'  => $referencia->validacaoExterna(),
            // Onda 10 · Goal 2: o bypass de superadmin deixa de ser silencioso.
            // Quem NÃO é superadmin recebe `disponivel: false` e não vê ação
            // nenhuma — liberar a trava global é decisão de [W], não de tela.
            'bypassSuperadmin' => [
                'disponivel'        => (bool) auth()->user()->can('superadmin'),
                'travaGlobalLigada' => $travaLigada,
                'reativadaNaSessao' => $this->travaReativada(),
            ],
        ]);
    }

    /** O superadmin desta sessão reativou a trava pra si? */
    private function travaReativada(): bool
    {
        return (bool) session(self::SESSAO_TRAVA_REATIVADA, false);
    }

    /**
     * POST /fiscal/sped/trava — alterna o bypass de superadmin DESTA SESSÃO.
     *
     * POR QUE ISTO EXISTE, e o que ele deliberadamente NÃO faz
     * ------------------------------------------------------
     * O charter do Cowork pede que liberar a trava seja "ação nomeada na tela,
     * não configuração escondida", com reativar a um clique. Hoje o superadmin
     * dispensa a trava **em silêncio**: ele baixa o arquivo sem que nada na tela
     * diga que passou por cima de uma proteção fail-secure.
     *
     * O que este endpoint alterna é SÓ o bypass da própria sessão de quem chama.
     * Ele NÃO escreve em `fiscal.sped_simples_only_lock` — a flag global é
     * `true` por padrão em `config/fiscal.php`, protege todos os tenants, e
     * desligá-la depende de eliminar os hardcodes de fallback (anti-hook do
     * charter, audit sênior R1). Aqui não há caminho que afrouxe nada: a única
     * mudança possível a partir do default é ficar MAIS restrito.
     *
     * ⚠️ O charter do Cowork queria o inverso — tela abrindo BLOQUEADA até o
     * superadmin liberar (opt-in). O `UC-FSPED-09 · superadmin bypassa flag`,
     * que é teste VERDE, prova o contrário do lado do servidor, e a precedência
     * do projeto é *teste verde > charter*. Inverter aquele contrato fiscal é
     * decisão de [W], não conserto — então o default preserva o comportamento
     * provado e a ação explícita restringe. Divergência registrada no charter.
     */
    public function trava(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        // Só superadmin tem bypass, logo só ele tem o que alternar. Para os
        // demais a trava já reprova e não há ação — 403, não 200 silencioso.
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Só superadmin alterna o próprio bypass da trava SPED');
        }

        $reativar = $request->boolean('reativar');

        $request->session()->put(self::SESSAO_TRAVA_REATIVADA, $reativar);

        Log::info('Fiscal.sped.trava alternada', [
            'business_id' => (int) session('user.business_id'),
            'user_id'     => (int) auth()->id(),
            'reativada'   => $reativar,
        ]);

        return back();
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
    private function checagens(
        \Carbon\Carbon $inicioMes,
        bool $travaLigada,
        bool $ehSuperadmin,
        bool $reativadaPeloSuperadmin = false,
    ): array {
        $ano = (int) $inicioMes->format('Y');
        $mes = (int) $inicioMes->format('n');
        $competencia = $inicioMes->format('m/Y');

        $anoOk     = $ano >= 2020;
        $futuraOk  = ! $inicioMes->copy()->startOfMonth()->isFuture();
        $fechadaOk = $inicioMes->copy()->endOfMonth()->isPast();
        $travaOk   = ! $travaLigada || $ehSuperadmin;

        // A DATA em que a competência encerra — o último dia do próprio mês.
        // O `UC-FSF1-03` do Cowork pede que o motivo do mês aberto a mostre, e o
        // protótipo cita ali o campo `entrega`. São coisas DIFERENTES: a
        // competência 05/2026 encerra em 31/05 e o prazo de entrega é 15/06. Quem
        // espera até a data de entrega para gerar perde o mês inteiro achando que
        // ainda está bloqueado. O prazo de entrega segue na coluna própria da
        // tabela; aqui vai o encerramento, que é o que destrava a geração.
        $encerraEm = $inicioMes->copy()->endOfMonth()->format('d/m/Y');

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
                    ? "Competência {$competencia} encerrou em {$encerraEm} — período de apuração completo"
                    : "Competência {$competencia} está em aberto: encerra em {$encerraEm} e a EFD "
                        . 'declara DT_INI/DT_FIN do período no registro 0000 (CONFAZ v3.1.1, perfil A)',
            ],
            [
                'id'     => 'trava',
                'ok'     => $travaOk,
                'rotulo' => 'Trava do gerador liberada',
                'motivo' => $travaOk
                    ? ($travaLigada
                        ? 'Trava ligada, mas o seu perfil de superadmin a dispensa — o download passa '
                            . 'por cima de uma proteção fail-secure, e você pode reativá-la nesta sessão'
                        : 'Trava fiscal.sped_simples_only_lock está desligada')
                    // Reativada pelo próprio superadmin: o motivo tem de dizer que a
                    // saída está a um clique. O texto genérico abaixo mandaria ele
                    // procurar decisão de terceiro por uma trava que ele mesmo pôs.
                    : ($reativadaPeloSuperadmin
                        ? 'Trava fiscal.sped_simples_only_lock reativada por você nesta sessão — '
                            . 'liberar de novo é um clique, e o seu perfil de superadmin permite'
                        : 'Trava fiscal.sped_simples_only_lock está ligada: o gerador ainda usa valores '
                            . 'de fallback (CST 102, CFOP 5102) que só valem em Simples Nacional sem crédito '
                            . 'de ICMS — liberar depende de decisão do responsável'),
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
        // Onda 10: o bypass do superadmin vale, salvo se ele mesmo reativou a
        // trava pra si nesta sessão (POST /fiscal/sped/trava). A ação só
        // restringe — quem nunca a usou tem exatamente o comportamento de antes.
        $bypassSuperadmin = (bool) auth()->user()->can('superadmin') && ! $this->travaReativada();

        if ((bool) config('fiscal.sped_simples_only_lock', true) && ! $bypassSuperadmin) {
            if ($this->travaReativada()) {
                return response(
                    "Download SPED bloqueado: você reativou a trava nesta sessão.\n\n"
                    . "Liberar de novo é um clique na barra de validação da tela /fiscal/sped.\n"
                    . "A trava global fiscal.sped_simples_only_lock não foi alterada.",
                    503,
                    ['Content-Type' => 'text/plain; charset=UTF-8'],
                );
            }

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
