<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;
use Modules\NfeBrasil\Services\NfeCartaCorrecaoService;
use Modules\NfeBrasil\Services\NfeInutilizacaoService;
use Modules\NfeBrasil\Services\NfeService;

/**
 * Ações de mutação fiscal (Wave 4) — thin delegate.
 *
 * Delega pra Services existentes em Modules/NfeBrasil sem duplicar lógica:
 *  - Cancelar NFe → NfeService::cancelar (FSM cascade ADR 0143 LIVE biz=1)
 *  - Manifestar DF-e → ManifestacaoService (cienciar/confirmar/desconhecer/nao-realizada)
 *
 * Retorna back()->with('flash') pra usuário ficar na tela Fiscal cockpit.
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *  - $businessId via session('user.business_id')
 *  - guard explícito no findOrFail (HasBusinessScope cobre, mas defesa em profundidade)
 *
 * Permissões:
 *  - fiscal.nfe.acoes: cancelar NFe
 *  - fiscal.dfe.manage: manifestar DF-e
 *
 * Throttle 30/min anti-DOS herda do pattern Modules/NfeBrasil/Routes/web.php
 * (proteção SEFAZ — disparos descontrolados podem ban IP no webservice).
 *
 * Wave 5 (PR #5) adicionou:
 *  - cartaCorrecao (CCe 110110) → NfeCartaCorrecaoService
 *  - inutilizar (faixa numérica) → NfeInutilizacaoService
 *
 * Retransmitir nota rejeitada permanece backlog PR #6 (re-build payload).
 */
class AcoesController extends Controller
{
    /**
     * Teto de notas por lote de manifestação.
     *
     * Existe pra que o relatório caiba no request: o laço é sequencial e cada nota é uma ida
     * à SEFAZ. O `LOTE_ORCAMENTO_SEGUNDOS` é a defesa real (para antes do `max_execution_time`
     * e devolve as restantes como "não tentadas"); este teto só evita aceitar um payload que
     * jamais caberia — e mantém o front honesto sobre quantas dá pra selecionar de uma vez.
     */
    public const LOTE_MAX_NOTAS = 50;

    /**
     * Orçamento de tempo do laço do lote, em segundos.
     *
     * Abaixo do `max_execution_time` típico do shared hosting (60s). Quando estoura, o laço
     * PARA e as notas restantes voltam como "não tentadas" — o relatório sempre chega. Sem
     * isso, o request morreria no meio com metade das notas já manifestadas na SEFAZ e nenhum
     * registro do que aconteceu, que é o lote silencioso que esta tela não pode ter.
     */
    public const LOTE_ORCAMENTO_SEGUNDOS = 45.0;

    /**
     * POST /fiscal/acoes/nfe/{emissao}/cancelar
     *
     * Cancela NFe/NFC-e autorizada dentro da janela legal (24h NFCe / 168h NFe).
     * Justificativa obrigatória ≥15 caracteres (regra CONFAZ).
     *
     * Chama NfeService::cancelar que orquestra:
     *  - Validação janela legal
     *  - Envio evento 110111 SEFAZ
     *  - Persistir NfeEvento (cstat 135 = homologado)
     *  - Update NfeEmissao.status='cancelada'
     *  - Dispara FSM cascade ADR 0143 (refund gateway + notificação cliente — biz=1 LIVE)
     */
    public function cancelarNfe(Request $request, NfeService $nfeService, int $emissao): RedirectResponse
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.nfe.acoes')) {
            abort(403, 'Sem permissão fiscal.nfe.acoes');
        }

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:15', 'max:255'],
        ]);

        $businessId = (int) session('user.business_id');

        // Defesa em profundidade — global scope já filtra, mas guard explícito
        // protege caso futuro alguém remova HasBusinessScope.
        $nfeEmissao = NfeEmissao::query()
            ->where('id', $emissao)
            ->where('business_id', $businessId)
            ->firstOrFail();

        if ($nfeEmissao->status !== 'autorizada') {
            return back()->with('error', "Apenas notas autorizadas podem ser canceladas. Status atual: {$nfeEmissao->status}");
        }

        try {
            $evento = $nfeService->cancelar($businessId, $nfeEmissao->id, $data['motivo']);

            Log::info('Fiscal.acoes.cancelarNfe ok', [
                'business_id'    => $businessId,
                'nfe_emissao_id' => $nfeEmissao->id,
                'evento_id'      => $evento->id,
                'cstat_evento'   => $evento->cstat_evento,
            ]);

            return back()->with('success', "NFe {$nfeEmissao->numero} cancelada · cstat {$evento->cstat_evento}");
        } catch (\Throwable $e) {
            Log::error('Fiscal.acoes.cancelarNfe falhou', [
                'business_id'    => $businessId,
                'nfe_emissao_id' => $nfeEmissao->id,
                'error'          => $e->getMessage(),
            ]);

            return back()->with('error', 'Cancelamento falhou: ' . $e->getMessage());
        }
    }

    /**
     * POST /fiscal/acoes/dfe/{recebido}/{acao}
     *
     * Manifestação destinatário SEFAZ — 4 ações:
     *  - cienciar     → tpEvento 210210 (ciência da operação)
     *  - confirmar    → tpEvento 210200 (confirmação)
     *  - desconhecer  → tpEvento 210220 (desconhecimento — exige justificativa)
     *  - nao_realizada → tpEvento 210240 (operação não realizada — exige justificativa)
     *
     * Delega pra ManifestacaoService (Modules/NfeBrasil/Services/Manifestacao/).
     * Idempotente: re-chamar mesma ação retorna evento existente.
     */
    public function manifestarDfe(
        Request $request,
        ManifestacaoService $service,
        int $recebido,
        string $acao,
    ): RedirectResponse {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.dfe.manage')) {
            abort(403, 'Sem permissão fiscal.dfe.manage');
        }

        $businessId = (int) session('user.business_id');

        $acoesValidas = ['cienciar', 'confirmar', 'desconhecer', 'nao_realizada'];
        if (! in_array($acao, $acoesValidas, true)) {
            abort(404, 'Ação manifestação não reconhecida');
        }

        $justificativa = null;
        if (in_array($acao, ['desconhecer', 'nao_realizada'], true)) {
            $data = $request->validate([
                'justificativa' => ['required', 'string', 'min:15', 'max:255'],
            ]);
            $justificativa = $data['justificativa'];
        }

        // Carga escopada por tenant (ADR 0093) — FORA do try, pra que o 404 de DF-e
        // inexistente/alheia suba como 404 em vez de virar flash de "manifestação falhou".
        $dfe = NfeDfeRecebido::query()
            ->where('business_id', $businessId)
            ->where('id', $recebido)
            ->first();

        if (! $dfe) {
            abort(404, 'DF-e não encontrada neste business');
        }

        try {
            // ManifestacaoService API: cada método recebe o MODELO `NfeDfeRecebido` e retorna
            // o evento aplicado — mesma forma do consumidor de `Modules/NfeBrasil`
            // (`ManifestacaoController::bulkConfirmar`). Passar `(business_id, id)` aqui rendia
            // `TypeError` engolido pelo catch abaixo: a tela reportava erro e nada manifestava.
            match ($acao) {
                'cienciar'      => $service->cienciar($dfe),
                'confirmar'     => $service->confirmar($dfe),
                'desconhecer'   => $service->desconhecer($dfe, $justificativa),
                'nao_realizada' => $service->naoRealizada($dfe, $justificativa),
            };

            Log::info('Fiscal.acoes.manifestarDfe ok', [
                'business_id'      => $businessId,
                'dfe_recebido_id'  => $recebido,
                'acao'             => $acao,
            ]);

            $labels = [
                'cienciar'      => 'Ciência registrada',
                'confirmar'     => 'Operação confirmada',
                'desconhecer'   => 'Desconhecimento registrado',
                'nao_realizada' => 'Operação não realizada registrada',
            ];

            return back()->with('success', $labels[$acao]);
        } catch (\Throwable $e) {
            Log::error('Fiscal.acoes.manifestarDfe falhou', [
                'business_id'     => $businessId,
                'dfe_recebido_id' => $recebido,
                'acao'            => $acao,
                'error'           => $e->getMessage(),
            ]);

            return back()->with('error', 'Manifestação falhou: ' . $e->getMessage());
        }
    }

    /**
     * POST /fiscal/acoes/dfe/lote
     *
     * Manifestação em lote — a MESMA ação para as N DF-e selecionadas.
     *
     * ── Por que UMA requisição HTTP, e não N do navegador ────────────────────────────────
     * O protótipo (`prototipo-ui/cowork/fiscal-subpages.jsx`, `data-contract="lote-dfe"`) diz
     * *"uma requisição por nota"*, e é assim que roda: uma chamada ao motor **por nota**, em
     * laço sequencial — nunca paralelo, porque cada evento precisa do seu `nSeqEvento` isolado
     * (paralelo gera duplicidade, cStat 573). O que NÃO se multiplica é a requisição do
     * navegador: a rota por linha tem `throttle:30,1`, então um lote de 50 notas disparado
     * como 50 POSTs morreria no 31º — deixando 30 manifestadas e 20 não, sem ninguém saber
     * quais. Um POST de lote gasta **um** hit e cabe inteiro no orçamento.
     *
     * ── Falha parcial é NOMEADA ──────────────────────────────────────────────────────────
     * Manifestação vai ao ambiente nacional da SEFAZ e é definitiva por nota. Um lote que
     * responde só "3 de 10 falharam" é inútil: a contadora não sabe quais 3 refazer. Cada nota
     * volta com chave, emitente e o motivo — e as que o orçamento de tempo não alcançou voltam
     * como **não tentadas**, que é diferente de falhadas.
     *
     * ── Orçamento de tempo ───────────────────────────────────────────────────────────────
     * Cada ida à SEFAZ leva segundos. Sem teto, um lote grande estoura o `max_execution_time`
     * do shared hosting e o relatório morre junto com o request — as notas já manifestadas
     * ficam manifestadas e ninguém fica sabendo. É exatamente o lote silencioso que esta tela
     * não pode ter. Com o teto, o request sempre volta com o relatório completo.
     *
     * `nao_realizada` NÃO entra no lote: o protótipo oferece três ações em massa (ciência,
     * confirmação, desconhecimento) e mantém a quarta só na linha, onde a decisão é individual.
     */
    public function manifestarDfeLote(
        Request $request,
        ManifestacaoService $service,
    ): RedirectResponse {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.dfe.manage')) {
            abort(403, 'Sem permissão fiscal.dfe.manage');
        }

        $data = $request->validate([
            'ids'           => ['required', 'array', 'min:1', 'max:' . self::LOTE_MAX_NOTAS],
            'ids.*'         => ['integer', 'min:1'],
            'acao'          => ['required', 'string', 'in:cienciar,confirmar,desconhecer'],
            'justificativa' => [
                $request->input('acao') === 'desconhecer' ? 'required' : 'nullable',
                'string', 'min:15', 'max:255',
            ],
        ]);

        $businessId = (int) session('user.business_id');
        $acao = (string) $data['acao'];
        $justificativa = $data['justificativa'] ?? null;
        $ids = array_values(array_unique(array_map('intval', $data['ids'])));

        $aplicadas = [];
        $falhas = [];
        $naoTentadas = [];

        $inicio = microtime(true);

        foreach ($ids as $id) {
            if ((microtime(true) - $inicio) > self::LOTE_ORCAMENTO_SEGUNDOS) {
                $naoTentadas[] = ['id' => $id];

                continue;
            }

            // Escopo por tenant DENTRO do laço (ADR 0093): id de outro business não vira
            // exceção que aborta o lote — vira uma linha nomeada no relatório, como as demais.
            $dfe = NfeDfeRecebido::query()
                ->where('business_id', $businessId)
                ->where('id', $id)
                ->first();

            if (! $dfe) {
                $falhas[] = ['id' => $id, 'chave' => null, 'emitente' => null, 'erro' => 'DF-e não encontrada neste business'];

                continue;
            }

            if (! in_array($dfe->status_manifestacao, [NfeDfeRecebido::STATUS_PENDENTE, NfeDfeRecebido::STATUS_CIENCIA], true)) {
                $falhas[] = $this->linhaLote($dfe, 'Já manifestada (' . $dfe->status_manifestacao . ')');

                continue;
            }

            try {
                match ($acao) {
                    'cienciar'    => $service->cienciar($dfe),
                    'confirmar'   => $service->confirmar($dfe),
                    'desconhecer' => $service->desconhecer($dfe, (string) $justificativa),
                };

                $aplicadas[] = $this->linhaLote($dfe, null);
            } catch (\Throwable $e) {
                $falhas[] = $this->linhaLote($dfe, $e->getMessage());
            }
        }

        Log::info('Fiscal.acoes.manifestarDfeLote', [
            'business_id'  => $businessId,
            'acao'         => $acao,
            'pedidas'      => count($ids),
            'aplicadas'    => count($aplicadas),
            'falhas'       => count($falhas),
            'nao_tentadas' => count($naoTentadas),
        ]);

        return back()->with('fiscal.dfe.lote', [
            'acao'        => $acao,
            'pedidas'     => count($ids),
            'aplicadas'   => $aplicadas,
            'falhas'      => $falhas,
            'naoTentadas' => $naoTentadas,
        ]);
    }

    /** Linha do relatório do lote — identifica a nota pra contadora saber qual refazer. */
    private function linhaLote(NfeDfeRecebido $dfe, ?string $erro): array
    {
        return [
            'id'       => (int) $dfe->id,
            'chave'    => (string) $dfe->chave_44,
            'emitente' => (string) ($dfe->nome_emitente ?? ''),
            'erro'     => $erro,
        ];
    }

    /**
     * POST /fiscal/acoes/nfe/{emissao}/cce
     *
     * Aplica Carta de Correção Eletrônica (CCe — tpEvento 110110).
     *
     * Regras CONFAZ Ajuste SINIEF 07/2005 Art. 14:
     *  - Texto correção: 15-1000 caracteres
     *  - Janela: 720h (30d) após autorização
     *  - Sequência: 1-20 por NFe
     *  - NFe deve estar 'autorizada'
     */
    public function cartaCorrecao(
        Request $request,
        NfeCartaCorrecaoService $service,
        int $emissao,
    ): RedirectResponse {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.nfe.acoes')) {
            abort(403, 'Sem permissão fiscal.nfe.acoes');
        }

        $data = $request->validate([
            'texto_correcao' => ['required', 'string', 'min:15', 'max:1000'],
            'n_seq_evento'   => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $businessId = (int) session('user.business_id');

        $nfeEmissao = NfeEmissao::query()
            ->where('id', $emissao)
            ->where('business_id', $businessId)
            ->firstOrFail();

        if ($nfeEmissao->status !== 'autorizada') {
            return back()->with('error', "CCe só aplica em NFe autorizada. Status atual: {$nfeEmissao->status}");
        }

        try {
            $evento = $service->aplicar(
                $businessId,
                $nfeEmissao->id,
                $data['texto_correcao'],
                (int) $data['n_seq_evento'],
            );

            Log::info('Fiscal.acoes.cartaCorrecao ok', [
                'business_id'    => $businessId,
                'nfe_emissao_id' => $nfeEmissao->id,
                'n_seq_evento'   => $data['n_seq_evento'],
                'evento_id'      => $evento->id,
                'cstat_evento'   => $evento->cstat_evento,
            ]);

            return back()->with('success', "CCe aplicada · NFe {$nfeEmissao->numero} seq {$data['n_seq_evento']} · cstat {$evento->cstat_evento}");
        } catch (\Throwable $e) {
            Log::error('Fiscal.acoes.cartaCorrecao falhou', [
                'business_id'    => $businessId,
                'nfe_emissao_id' => $nfeEmissao->id,
                'n_seq_evento'   => $data['n_seq_evento'],
                'error'          => $e->getMessage(),
            ]);

            return back()->with('error', 'CCe falhou: ' . $e->getMessage());
        }
    }

    /**
     * POST /fiscal/acoes/nfe/inutilizar
     *
     * Inutiliza faixa numérica de NFe (SEFAZ cstat=102) — fecha "buracos"
     * fiscais por números pegos no banco mas sem autorização SEFAZ.
     *
     * Delega NfeInutilizacaoService::inutilizar (Service já existente US-SELL-030).
     */
    public function inutilizar(
        Request $request,
        NfeInutilizacaoService $service,
    ): RedirectResponse {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.nfe.acoes')) {
            abort(403, 'Sem permissão fiscal.nfe.acoes');
        }

        $data = $request->validate([
            'modelo'        => ['required', 'string', 'in:55,65'],
            'serie'         => ['required', 'string', 'max:3'],
            'numero_de'     => ['required', 'integer', 'min:1'],
            'numero_ate'    => ['required', 'integer', 'min:1', 'gte:numero_de'],
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ]);

        $businessId = (int) session('user.business_id');

        try {
            $inut = $service->inutilizar(
                $businessId,
                $data['modelo'],
                $data['serie'],
                (int) $data['numero_de'],
                (int) $data['numero_ate'],
                $data['justificativa'],
            );

            Log::info('Fiscal.acoes.inutilizar ok', [
                'business_id'     => $businessId,
                'modelo'          => $data['modelo'],
                'serie'           => $data['serie'],
                'faixa'           => "[{$data['numero_de']}..{$data['numero_ate']}]",
                'inutilizacao_id' => $inut->id,
                'cstat'           => $inut->cstat,
                'status'          => $inut->status,
            ]);

            $msg = $inut->status === 'autorizado'
                ? "Faixa [{$data['numero_de']}..{$data['numero_ate']}] inutilizada · cstat {$inut->cstat}"
                : "Inutilização rejeitada · cstat {$inut->cstat}";

            return back()->with($inut->status === 'autorizado' ? 'success' : 'error', $msg);
        } catch (\Throwable $e) {
            Log::error('Fiscal.acoes.inutilizar falhou', [
                'business_id' => $businessId,
                'modelo'      => $data['modelo'],
                'serie'       => $data['serie'],
                'faixa'       => "[{$data['numero_de']}..{$data['numero_ate']}]",
                'error'       => $e->getMessage(),
            ]);

            return back()->with('error', 'Inutilização falhou: ' . $e->getMessage());
        }
    }

    /**
     * POST /fiscal/acoes/nfe/{emissao}/retransmitir
     *
     * Retransmite NFe rejeitada/denegada/erro_envio (Wave 6 / PR #6).
     * Delega `NfeService::retransmitir` (UPDATE preservation contract CONFAZ
     * Art. 14 — não deleta antiga, marca `inutilizada` + null transaction_id
     * pra liberar UNIQUE constraint, depois reemite com novo número via
     * `emitirParaTransaction`).
     *
     * Status retransmissíveis: rejeitada / denegada / erro_envio.
     * NÃO retransmite: autorizada (idempotente — usar CC-e) / cancelada
     * (número usado oficialmente — exige FSM emitir_nova_apos_cancelamento).
     */
    public function retransmitir(
        Request $request,
        NfeService $nfeService,
        int $emissao,
    ): RedirectResponse {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.nfe.acoes')) {
            abort(403, 'Sem permissão fiscal.nfe.acoes');
        }

        $businessId = (int) session('user.business_id');

        // Cross-tenant guard explícito (defesa em profundidade — Service também valida)
        $nfeEmissao = NfeEmissao::query()
            ->where('id', $emissao)
            ->where('business_id', $businessId)
            ->firstOrFail();

        $statusValidos = ['rejeitada', 'denegada', 'erro_envio'];
        if (! in_array($nfeEmissao->status, $statusValidos, true)) {
            return back()->with(
                'error',
                "Retransmissão só aplica em NFe rejeitada/denegada/erro_envio. Status atual: {$nfeEmissao->status}"
            );
        }

        try {
            $nova = $nfeService->retransmitir($businessId, $nfeEmissao->id);

            Log::info('Fiscal.acoes.retransmitir ok', [
                'business_id'     => $businessId,
                'emissao_antiga'  => $emissao,
                'numero_antigo'   => $nfeEmissao->numero,
                'emissao_nova_id' => $nova->id,
                'numero_novo'     => $nova->numero,
                'status_novo'     => $nova->status,
                'cstat_novo'      => $nova->cstat,
            ]);

            $msg = $nova->status === 'autorizada'
                ? "NFe retransmitida · novo nº {$nova->numero} · cstat {$nova->cstat}"
                : "Retransmissão enviada · status {$nova->status} · cstat {$nova->cstat} · {$nova->motivo}";

            return back()->with($nova->status === 'autorizada' ? 'success' : 'error', $msg);
        } catch (\Throwable $e) {
            Log::error('Fiscal.acoes.retransmitir falhou', [
                'business_id'    => $businessId,
                'nfe_emissao_id' => $nfeEmissao->id,
                'error'          => $e->getMessage(),
            ]);

            return back()->with('error', 'Retransmissão falhou: ' . $e->getMessage());
        }
    }
}
