<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * Detalhe do lançamento e ações do Financeiro do app (tela 06, chave LANCAMENTO_ACOES): ver, Alterar,
 * Receber/Pagar (inclusive parcial), Retornar, Excluir, Reativar e recibo.
 * Contrato: memory/requisitos/AppMobile/api/tela-06-financeiro.md §Detalhe e ações; formatos `LancamentoDetalhe`,
 * `BaixaLancamento`, `AlteracaoLancamento` (src/lancamento-regras.ts) e `ReciboDados` (src/recibo.ts) do app.
 *
 * REGRA MESTRE (valor). Toda conta em centavos inteiros, com o título travado (lockForUpdate) na transação.
 * Invariantes que os testes conferem antes × depois:
 * - baixa total: soma de valor_total igual; valor_aberto cai o pago; baixas ativas sobem o pago.
 * - baixa parcial (formato do app): o lançamento fica LIQUIDADO com o valor pago (valor_total = o que já foi
 *   baixado), e o restante vira um lançamento NOVO em aberto, com o vencimento escolhido. Soma de valor_total igual.
 * - retornar: cada baixa ativa ganha a linha de estorno (ledger, append-only — nunca delete); o restante de um
 *   parcial, ainda em aberto, volta para este lançamento (é excluído com o motivo) — soma de valor_total igual.
 * - excluir/reativar: soft delete do título sem baixa; nada de valor muda além de sair/voltar das somas.
 * Lançamento que veio de venda/compra/despesa não se altera em valor, não se exclui e não se retorna por aqui:
 * a origem é dona dele.
 *
 * Histórico: activity_log, log `financeiro.app`, um texto por evento (alteração com "antes → depois").
 * Idempotência: app_idempotencia (rota `financeiro.lancamentos.acao`), hash de id + ação + corpo; repetir
 * devolve o detalhe atual.
 * Tier 0 (ADR 0093): título, conta e pessoa sempre do business do token; de outro business = 404.
 */
class FinanceiroLancamentoAcoesController extends Controller
{
    private const ROTA = 'financeiro.lancamentos.acao';

    private const LOG = 'financeiro.app';

    public function __construct(private FinanceiroController $financeiro)
    {
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->financeiro->podeVerFinanceiro($user)) {
            return $this->erro('sem_permissao', 'Seu usuário não tem acesso ao financeiro.', 403);
        }
        $d = $this->detalhe((int) $user->business_id, $id);

        return $d === null ? $this->naoEncontrado() : response()->json($d);
    }

    public function alterar(Request $request, int $id): JsonResponse
    {
        return $this->acao($request, $id, 'alterar', function (Titulo $t, User $user, array $corpo) {
            if ($t->trashed()) {
                return $this->estado('Lançamento excluído: reative antes de alterar.');
            }
            if ($t->status === 'quitado') {
                return $this->estado($t->tipo === 'receber' ? 'Já recebido: retorne a receber antes de alterar.' : 'Já pago: retorne a pagar antes de alterar.');
            }
            if ($t->status !== 'aberto' || $this->centavosBaixados((int) $t->id) > 0) {
                return $this->estado('Lançamento com pagamento parcial: altere no computador.');
            }

            return $this->aplicarAlteracao($t, $user, $corpo);
        });
    }

    public function baixar(Request $request, int $id): JsonResponse
    {
        return $this->acao($request, $id, 'baixar', function (Titulo $t, User $user, array $corpo) {
            if (! $this->podeBaixar($user, (string) $t->tipo)) {
                return $this->erro('sem_permissao', 'Seu usuário não pode ' . ($t->tipo === 'receber' ? 'receber.' : 'pagar.'), 403);
            }
            if ($t->trashed()) {
                return $this->estado('Lançamento excluído: reative antes de dar baixa.');
            }
            if (! in_array($t->status, ['aberto', 'parcial'], true)) {
                return $this->estado($t->tipo === 'receber' ? 'Este lançamento já foi recebido.' : 'Este lançamento já foi pago.');
            }

            return $this->aplicarBaixa($t, $user, $corpo);
        });
    }

    public function estornar(Request $request, int $id): JsonResponse
    {
        return $this->acao($request, $id, 'estornar', function (Titulo $t, User $user) {
            $recebe = $t->tipo === 'receber';
            if (! $this->podeBaixar($user, (string) $t->tipo)) {
                return $this->erro('sem_permissao', 'Seu usuário não pode ' . ($recebe ? 'receber.' : 'pagar.'), 403);
            }
            if ($t->trashed() || $t->status !== 'quitado') {
                return $this->estado($recebe ? 'Este lançamento ainda não foi recebido.' : 'Este lançamento ainda não foi pago.');
            }
            $ativas = $this->baixasAtivas((int) $t->id);
            if ($ativas->contains(fn ($b) => $b->transaction_payment_id !== null)) {
                return $this->estado('Pagamento registrado na venda ou compra: retorne por lá.');
            }

            return $this->aplicarEstorno($t, $user, $ativas);
        });
    }

    public function excluir(Request $request, int $id): JsonResponse
    {
        return $this->acao($request, $id, 'excluir', function (Titulo $t, User $user, array $corpo) {
            if ($t->trashed()) {
                return null; // já excluído: devolve como está
            }
            $recebe = $t->tipo === 'receber';
            if ($t->status === 'quitado') {
                return $this->estado($recebe ? 'Já recebido: retorne a receber antes de excluir.' : 'Já pago: retorne a pagar antes de excluir.');
            }
            if ($this->centavosBaixados((int) $t->id) > 0) {
                return $this->estado('Lançamento com pagamento parcial: retorne o pagamento antes de excluir.');
            }
            if ($t->origem !== 'manual') {
                return $this->estado('Lançamento gerado por ' . $this->nomeOrigem((string) $t->origem) . ': exclua na origem.');
            }
            $motivo = trim((string) ($corpo['motivo'] ?? ''));
            $motivo = $motivo === '' ? null : mb_substr($motivo, 0, 200);
            $meta = (array) ($t->metadata ?? []);
            $meta['motivo_exclusao'] = $motivo;
            $t->fill(['metadata' => $meta, 'updated_by' => $user->id])->save();
            $t->delete();
            $this->historico($t, $user, $motivo !== null ? "Excluído: {$motivo}" : 'Excluído');

            return null;
        });
    }

    public function reativar(Request $request, int $id): JsonResponse
    {
        return $this->acao($request, $id, 'reativar', function (Titulo $t, User $user) {
            if (! $t->trashed()) {
                return $this->estado('Este lançamento não está excluído.');
            }
            $meta = (array) ($t->metadata ?? []);
            if (! empty($meta['juntado_em'])) {
                return $this->estado('Este restante voltou para o lançamento #' . (int) $meta['juntado_em'] . ' e não pode ser reativado.');
            }
            $t->restore();
            unset($meta['motivo_exclusao']);
            $t->fill(['metadata' => $meta, 'updated_by' => $user->id])->save();
            $this->historico($t, $user, 'Reativado');

            return null;
        });
    }

    /** GET recibo — só de lançamento liquidado e ativo. */
    public function recibo(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->financeiro->podeVerFinanceiro($user)) {
            return $this->erro('sem_permissao', 'Seu usuário não tem acesso ao financeiro.', 403);
        }
        $bizId = (int) $user->business_id;
        $d = $this->detalhe($bizId, $id);
        if ($d === null) {
            return $this->naoEncontrado();
        }
        if ($d['status'] !== 'liquidado' || $d['excluido']) {
            return $this->estado('Recibo só de lançamento recebido ou pago.');
        }
        $empresa = DB::table('business')->where('id', $bizId)->first(['name', 'tax_number_1']);
        $cidade = DB::table('business_locations')->where('business_id', $bizId)->whereNull('deleted_at')->orderBy('id')->value('city');
        $doc = null;
        if ($d['parte_id'] !== null) {
            $doc = DB::table('contacts')->where('business_id', $bizId)->where('id', $d['parte_id'])->value('tax_number');
        }

        return response()->json([
            'numero' => 'REC-' . substr((string) $d['pago_em'], 0, 4) . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT),
            'tipo' => $d['tipo'],
            'valor' => $d['valor'],
            'data' => $d['pago_em'],
            'referente' => $d['descricao'],
            'forma' => $d['forma_pagamento'],
            'documento' => $d['documento'],
            'empresa' => [
                'nome' => (string) ($empresa->name ?? ''),
                'documento' => ($empresa->tax_number_1 ?? '') !== '' ? (string) $empresa->tax_number_1 : null,
                'cidade' => $cidade !== null && $cidade !== '' ? (string) $cidade : null,
            ],
            'parte' => $d['parte'] !== null ? ['nome' => $d['parte'], 'documento' => $doc !== null && $doc !== '' ? (string) $doc : null] : null,
            'restante' => $d['restante'] !== null ? ['valor' => $d['restante']['valor'], 'vencimento' => $d['restante']['vencimento']] : null,
        ]);
    }

    // ── núcleo das ações ──

    /**
     * Permissão, Idempotency-Key, trava do título e transação. `$fazer` devolve null (ok → detalhe atual) ou
     * uma resposta de erro (a transação é desfeita e a chave não fica reservada).
     */
    private function acao(Request $request, int $id, string $nome, callable $fazer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->financeiro->podeVerFinanceiro($user)) {
            return $this->erro('sem_permissao', 'Seu usuário não tem acesso ao financeiro.', 403);
        }
        $bizId = (int) $user->business_id;
        $chave = trim((string) $request->header('Idempotency-Key', ''));
        if ($chave === '' || mb_strlen($chave) > 100) {
            return $this->invalido(['idempotency_key' => 'Envie o header Idempotency-Key.']);
        }
        $corpo = $request->all();
        $hash = hash('sha256', (string) json_encode([$id, $nome, $corpo]));

        $ja = DB::table('app_idempotencia')->where('business_id', $bizId)->where('user_id', $user->id)
            ->where('rota', self::ROTA)->where('chave', $chave)->first();
        if ($ja !== null) {
            if (! hash_equals((string) $ja->hash_corpo, $hash)) {
                return $this->erro('idempotencia_conflito', 'Esta chave já foi usada para outra ação. Gere uma chave nova.', 422);
            }
            $d = $this->detalhe($bizId, $id);

            return $d === null ? $this->naoEncontrado() : response()->json($d);
        }
        if (in_array($nome, ['alterar', 'excluir', 'reativar'], true)) {
            $tipo = DB::table('fin_titulos')->where('business_id', $bizId)->where('id', $id)->value('tipo');
            if ($tipo !== null && ! FinanceiroLancamentosController::podeCriar($user, (string) $tipo)) {
                return $this->erro('sem_permissao', 'Seu usuário não pode alterar lançamentos.', 403);
            }
        }

        DB::beginTransaction();
        try {
            try {
                DB::table('app_idempotencia')->insert([
                    'business_id' => $bizId, 'user_id' => (int) $user->id, 'rota' => self::ROTA, 'chave' => $chave,
                    'hash_corpo' => $hash, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                DB::rollBack();

                return $this->erro('em_andamento', 'Esta ação ainda está sendo registrada. Tente de novo.', 409);
            }
            $t = Titulo::withTrashed()->where('business_id', $bizId)->whereKey($id)->lockForUpdate()->first();
            if ($t === null) {
                DB::rollBack();

                return $this->naoEncontrado();
            }
            $resposta = $fazer($t, $user, $corpo);
            if ($resposta instanceof JsonResponse) {
                DB::rollBack();

                return $resposta;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("API app financeiro.lancamentos.{$nome}: " . $e->getMessage(), ['business_id' => $bizId, 'titulo_id' => $id]);

            return $this->erro('falha', 'Não foi possível concluir a ação.', 500);
        }

        return response()->json($this->detalhe($bizId, $id));
    }

    /** @param array<string, mixed> $c */
    private function aplicarAlteracao(Titulo $t, User $user, array $c): ?JsonResponse
    {
        $bizId = (int) $t->business_id;
        $v = Validator::make($c, [
            'descricao' => ['required', 'string', 'max:120'],
            'contato_id' => ['nullable', 'integer'],
            'valor' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'vencimento' => ['required', 'date_format:Y-m-d'],
            'emissao' => ['required', 'date_format:Y-m-d'],
            'competencia' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'forma_pagamento' => ['nullable', 'string', 'max:40'],
            'conta_id' => ['nullable', 'integer'],
            'plano_codigo' => ['required', 'string', 'max:20'],
            'documento' => ['nullable', 'string', 'max:40'],
            'nf_numero' => ['nullable', 'string', 'max:20'],
            'observacao' => ['nullable', 'string', 'max:1000'],
            'contrato' => ['nullable', 'string', 'max:40'],
        ], [
            'descricao.required' => 'Escreva uma descrição.', 'descricao.max' => 'Use até 120 caracteres.',
            'valor.*' => 'O valor precisa ser maior que zero.', 'vencimento.*' => 'Escolha a data de vencimento.',
            'emissao.*' => 'Escolha a data de emissão.', 'competencia.*' => 'Escolha o mês de competência.',
            'plano_codigo.*' => 'Escolha a conta do plano de contas.',
        ]);
        if ($v->fails()) {
            return $this->invalido(collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all());
        }
        $d = $v->validated();
        $campos = [];
        $centavos = FinanceiroLancamentosController::centavos($c['valor']);
        $antesCent = (int) round(((float) $t->valor_total) * 100);
        if ($centavos === null) {
            $campos['valor'] = 'Use no máximo 2 casas depois da vírgula.';
        } elseif ($centavos < 1) {
            $campos['valor'] = 'O valor precisa ser maior que zero.';
        } elseif ($centavos !== $antesCent && $t->origem !== 'manual') {
            $campos['valor'] = 'O valor de lançamento gerado por ' . $this->nomeOrigem((string) $t->origem) . ' se altera na origem.';
        }
        if ($d['vencimento'] < $d['emissao']) {
            $campos['vencimento'] = 'O vencimento não pode ser antes da emissão.';
        }
        $plano = DB::table('fin_planos_conta')->where('business_id', $bizId)->where('codigo', $d['plano_codigo'])
            ->whereNull('deleted_at')->where('ativo', true)->first(['id', 'codigo', 'nome', 'tipo']);
        if ($plano === null) {
            $campos['plano'] = 'Conta não encontrada no plano de contas.';
        } elseif (DB::table('fin_planos_conta')->where('business_id', $bizId)->whereNull('deleted_at')
            ->where('codigo', 'like', $plano->codigo . '.%')->exists()) {
            $campos['plano'] = 'Escolha uma conta, não um grupo.';
        } elseif (! in_array($plano->tipo, $t->tipo === 'receber' ? ['receita'] : ['despesa', 'custo'], true)) {
            $campos['plano'] = 'Essa conta não é do mesmo tipo do lançamento.';
        }
        $contato = null;
        if (isset($d['contato_id'])) {
            $contato = DB::table('contacts')->where('business_id', $bizId)->where('id', (int) $d['contato_id'])
                ->whereNull('deleted_at')->first(['id', 'name', 'supplier_business_name']);
            if ($contato === null) {
                $campos['contato_id'] = 'Pessoa não encontrada.';
            }
        }
        if (isset($d['conta_id']) && ! DB::table('fin_contas_bancarias')->where('business_id', $bizId)
            ->where('id', (int) $d['conta_id'])->whereNull('deleted_at')->exists()) {
            $campos['conta_id'] = 'Conta bancária não encontrada.';
        }
        $forma = null;
        if (! empty($d['forma_pagamento'])) {
            $forma = FinanceiroController::chaveForma((string) $d['forma_pagamento']);
            if ($forma === null) {
                $campos['forma_pagamento'] = 'Forma de pagamento não aceita.';
            }
        }
        if ($campos !== []) {
            return $this->invalido($campos);
        }

        $antes = $this->financeiro->itemPorId($bizId, (int) $t->id) ?? [];
        $txt = fn ($s) => ($s !== null && trim((string) $s) !== '') ? trim((string) $s) : null;
        $meta = (array) ($t->metadata ?? []);
        foreach (['descricao' => trim((string) $d['descricao']), 'documento' => $txt($d['documento'] ?? null),
            'nfe_numero' => $txt($d['nf_numero'] ?? null), 'contrato' => $txt($d['contrato'] ?? null)] as $k => $val) {
            if ($val === null) {
                unset($meta[$k]);
            } else {
                $meta[$k] = $val;
            }
        }
        $parte = $contato ? (trim((string) $contato->name) !== '' ? (string) $contato->name : (string) $contato->supplier_business_name) : null;
        $valor = sprintf('%d.%02d', intdiv((int) $centavos, 100), (int) $centavos % 100);
        $t->fill([
            'cliente_id' => $contato?->id,
            // Sem pessoa no corpo: mantém o nome digitado de título sem cadastro; se tinha cadastro, a pessoa sai.
            'cliente_descricao' => $contato !== null ? $parte : ($t->cliente_id === null ? $t->cliente_descricao : null),
            'valor_total' => $valor,
            'valor_aberto' => $valor,
            'vencimento' => $d['vencimento'],
            'emissao' => $d['emissao'],
            'competencia_mes' => $d['competencia'],
            'plano_conta_id' => (int) $plano->id,
            'forma_pagamento' => $forma,
            'conta_bancaria_id' => isset($d['conta_id']) ? (int) $d['conta_id'] : null,
            'observacoes' => $txt($d['observacao'] ?? null),
            'metadata' => $meta,
            'updated_by' => $user->id,
        ])->save();

        $depois = $this->financeiro->itemPorId($bizId, (int) $t->id) ?? [];
        $mudancas = $this->mudancas($t, $antes, $depois, $antesCent, (int) $centavos);
        if ($mudancas !== []) {
            $this->historico($t, $user, 'Alterado: ' . implode('; ', $mudancas));
        }

        return null;
    }

    /** @param array<string, mixed> $c */
    private function aplicarBaixa(Titulo $t, User $user, array $c): ?JsonResponse
    {
        $bizId = (int) $t->business_id;
        $hoje = CarbonImmutable::today()->toDateString();
        $recebe = $t->tipo === 'receber';
        $pagoEm = (string) ($c['pago_em'] ?? '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $pagoEm) || $pagoEm > $hoje) {
            return $this->invalido(['pago_em' => 'Data do pagamento inválida.']);
        }
        $aberto = (int) round(((float) $t->valor_aberto) * 100);
        $pago = FinanceiroLancamentosController::centavos($c['valor_pago'] ?? null);
        if ($pago === null || $pago < 1 || $pago > $aberto) {
            return $this->invalido(['valor' => 'O valor pago precisa ser maior que zero e até o valor do lançamento.']);
        }
        $restante = $aberto - $pago;
        $venceResto = (string) ($c['vencimento_restante'] ?? '');
        if ($restante > 0 && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $venceResto) || $venceResto < $pagoEm)) {
            return $this->invalido(['vencimento_restante' => 'Escolha o vencimento do restante.']);
        }
        $contaId = isset($c['conta_id']) && $c['conta_id'] !== null ? (int) $c['conta_id'] : null;
        if ($contaId !== null && ! DB::table('fin_contas_bancarias')->where('business_id', $bizId)->where('id', $contaId)->whereNull('deleted_at')->exists()) {
            return $this->invalido(['conta_id' => 'Conta bancária não encontrada.']);
        }
        $forma = null;
        if (! empty($c['forma_pagamento'])) {
            $forma = FinanceiroController::chaveForma((string) $c['forma_pagamento']);
            if ($forma === null) {
                return $this->invalido(['forma_pagamento' => 'Forma de pagamento não aceita.']);
            }
        }
        $meio = $forma ?? ($t->forma_pagamento ?: 'outro');
        $rs = fn (int $cent) => 'R$ ' . number_format($cent / 100, 2, ',', '.');
        $br = fn (string $iso) => implode('/', array_reverse(explode('-', $iso)));
        $palavra = $recebe ? 'Recebido' : 'Pago';

        if ($restante > 0) {
            $novo = $t->replicate(['conferido_by', 'conferido_at', 'aprovacao_status']);
            $metaNovo = (array) ($t->metadata ?? []);
            unset($metaNovo['restante_id'], $metaNovo['valor_antes_parcial'], $metaNovo['motivo_exclusao']);
            $descricao = trim((string) ($metaNovo['descricao'] ?? '')) ?: 'Título ' . $t->numero;
            $metaNovo['descricao'] = preg_replace('/ \(restante\)$/u', '', $descricao) . ' (restante)';
            $metaNovo['restante_de'] = (int) $t->id;
            $valorResto = sprintf('%d.%02d', intdiv($restante, 100), $restante % 100);
            $novo->fill([
                'numero' => $this->proximoNumero($bizId, (string) $t->tipo),
                'status' => 'aberto',
                'valor_total' => $valorResto,
                'valor_aberto' => $valorResto,
                'vencimento' => $venceResto,
                'origem' => 'manual',
                'origem_id' => null,
                'titulo_pai_id' => $t->id,
                'metadata' => $metaNovo,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $novo->save();
            $this->historico($novo, $user, "Criado com o restante do lançamento #{$t->id} (pagamento parcial de {$rs($pago)})");

            $total = (int) round(((float) $t->valor_total) * 100) - $restante;
            $meta = (array) ($t->metadata ?? []);
            $meta['restante_id'] = (int) $novo->id;
            $meta['valor_antes_parcial'] = (float) $t->valor_total;
            $t->fill(['metadata' => $meta, 'valor_total' => sprintf('%d.%02d', intdiv($total, 100), $total % 100)]);
            $texto = "{$palavra} parcialmente: {$rs($pago)} em {$br($pagoEm)}. Restante de {$rs($restante)} no lançamento #{$novo->id}, vencendo {$br($venceResto)}";
        } else {
            $texto = "{$palavra} em {$br($pagoEm)}";
        }

        TituloBaixa::create([
            'business_id' => $bizId,
            'titulo_id' => $t->id,
            'conta_bancaria_id' => $contaId,
            'valor_baixa' => sprintf('%d.%02d', intdiv($pago, 100), $pago % 100),
            'data_baixa' => $pagoEm,
            'meio_pagamento' => in_array($meio, Titulo::FORMAS_PAGAMENTO, true) ? $meio : 'outro',
            'idempotency_key' => (string) Str::uuid(),
            'observacoes' => 'Baixa pelo app',
            'created_by' => $user->id,
        ]);
        $t->fill(['valor_aberto' => '0.00', 'status' => 'quitado', 'updated_by' => $user->id])->save();
        $this->historico($t, $user, $texto);

        return null;
    }

    private function aplicarEstorno(Titulo $t, User $user, \Illuminate\Support\Collection $ativas): ?JsonResponse
    {
        $bizId = (int) $t->business_id;
        $recebe = $t->tipo === 'receber';
        $meta = (array) ($t->metadata ?? []);
        $total = (int) round(((float) $t->valor_total) * 100);
        $junta = '';

        if (! empty($meta['restante_id'])) {
            $resto = Titulo::withTrashed()->where('business_id', $bizId)->whereKey((int) $meta['restante_id'])->lockForUpdate()->first();
            if ($resto !== null && ! $resto->trashed()) {
                if ($resto->status !== 'aberto' || $this->centavosBaixados((int) $resto->id) > 0) {
                    return $this->estado("O restante (lançamento #{$resto->id}) já foi " . ($recebe ? 'recebido' : 'pago') . ': retorne ele antes.');
                }
                $metaR = (array) ($resto->metadata ?? []);
                $metaR['juntado_em'] = (int) $t->id;
                $metaR['motivo_exclusao'] = "Juntado de volta ao lançamento #{$t->id}";
                $resto->fill(['metadata' => $metaR, 'updated_by' => $user->id])->save();
                $resto->delete();
                $this->historico($resto, $user, "Juntado de volta ao lançamento #{$t->id} (retorno do pagamento parcial)");
                $total += (int) round(((float) $resto->valor_total) * 100);
                $junta = "; o restante #{$resto->id} voltou para este lançamento";
            }
            unset($meta['restante_id'], $meta['valor_antes_parcial']);
        }

        $datas = [];
        foreach ($ativas as $b) {
            TituloBaixa::create([
                'business_id' => $bizId,
                'titulo_id' => $t->id,
                'conta_bancaria_id' => $b->conta_bancaria_id,
                'valor_baixa' => -1 * (float) $b->valor_baixa,
                'data_baixa' => CarbonImmutable::today()->toDateString(),
                'meio_pagamento' => $b->meio_pagamento,
                'idempotency_key' => (string) Str::uuid(),
                'estorno_de_id' => $b->id,
                'observacoes' => 'Estorno pelo app (retornar ' . ($recebe ? 'a receber' : 'a pagar') . ')',
                'created_by' => $user->id,
            ]);
            $datas[] = implode('/', array_reverse(explode('-', substr((string) $b->data_baixa, 0, 10))));
        }
        $valor = sprintf('%d.%02d', intdiv($total, 100), $total % 100);
        $t->fill(['metadata' => $meta, 'valor_total' => $valor, 'valor_aberto' => $valor, 'status' => 'aberto', 'updated_by' => $user->id])->save();
        $this->historico($t, $user, ($recebe ? 'Retornado a receber' : 'Retornado a pagar')
            . ' (baixa de ' . (implode(', ', array_unique($datas)) ?: '—') . " desfeita{$junta})");

        return null;
    }

    // ── leitura ──

    /** @return array<string, mixed>|null */
    private function detalhe(int $bizId, int $id): ?array
    {
        $item = $this->financeiro->itemPorId($bizId, $id);
        if ($item === null) {
            return null;
        }
        $t = DB::table('fin_titulos')->where('business_id', $bizId)->where('id', $id)
            ->first(['cliente_id', 'observacoes', 'parcela_numero', 'parcela_total', 'metadata', 'origem', 'origem_id']);
        $meta = is_string($t->metadata) ? (json_decode($t->metadata, true) ?: []) : [];
        $restante = null;
        if (! empty($meta['restante_id'])) {
            $r = DB::table('fin_titulos')->where('business_id', $bizId)->where('id', (int) $meta['restante_id'])
                ->whereNull('deleted_at')->first(['id', 'valor_total', 'vencimento']);
            if ($r !== null) {
                $restante = ['id' => (int) $r->id, 'valor' => round((float) $r->valor_total, 2), 'vencimento' => substr((string) $r->vencimento, 0, 10)];
            }
        }
        $contaNome = $item['conta_id'] === null ? null : DB::table('fin_contas_bancarias as f')
            ->leftJoin('accounts as a', fn ($j) => $j->on('a.id', '=', 'f.account_id')->where('a.business_id', '=', $bizId))
            ->where('f.business_id', $bizId)->where('f.id', $item['conta_id'])->value(DB::raw("COALESCE(a.name, CONCAT('Conta ', f.id))"));
        $recorrencia = $item['contrato'] !== null && $t->origem === 'recurring' && ctype_digit((string) $item['contrato']) ? (int) $item['contrato'] : null;

        return $item + [
            'parte_id' => $t->cliente_id !== null ? (int) $t->cliente_id : null,
            'observacao' => $t->observacoes !== null && $t->observacoes !== '' ? (string) $t->observacoes : null,
            'recorrencia_id' => $recorrencia,
            'parcela' => (int) $t->parcela_total > 1 ? (int) $t->parcela_numero . '/' . (int) $t->parcela_total : null,
            'conta_nome' => $contaNome !== null ? (string) $contaNome : null,
            'motivo_exclusao' => $item['excluido'] && ! empty($meta['motivo_exclusao']) ? (string) $meta['motivo_exclusao'] : null,
            'historico' => $this->lerHistorico($id),
            'restante' => $restante,
            'origem_id' => ! empty($meta['restante_de']) ? (int) $meta['restante_de'] : null,
        ];
    }

    /** @return list<array{quando: string, texto: string}> */
    private function lerHistorico(int $id): array
    {
        return DB::table('activity_log')->where('log_name', self::LOG)
            ->where('subject_type', (new Titulo())->getMorphClass())->where('subject_id', $id)
            ->orderBy('id')->get(['description', 'created_at'])
            ->map(fn ($h) => ['quando' => substr((string) $h->created_at, 0, 10), 'texto' => (string) $h->description])->all();
    }

    private function historico(Titulo $t, User $user, string $texto): void
    {
        activity(self::LOG)->performedOn($t)->causedBy($user)
            ->tap(fn ($a) => $a->business_id = (int) $t->business_id)
            ->log($texto);
    }

    /** Baixas ativas (não estorno e não estornadas) do título. */
    private function baixasAtivas(int $tituloId): \Illuminate\Support\Collection
    {
        return DB::table('fin_titulo_baixas as b')->where('b.titulo_id', $tituloId)->whereNull('b.estorno_de_id')
            ->whereNotExists(fn ($e) => $e->from('fin_titulo_baixas as e')->whereColumn('e.estorno_de_id', 'b.id'))
            ->orderBy('b.id')->get();
    }

    private function centavosBaixados(int $tituloId): int
    {
        return (int) round($this->baixasAtivas($tituloId)->sum(fn ($b) => (float) $b->valor_baixa) * 100);
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     * @return list<string>
     */
    private function mudancas(Titulo $t, array $antes, array $depois, int $valorAntes, int $valorDepois): array
    {
        $rs = fn (int $c) => 'R$ ' . number_format($c / 100, 2, ',', '.');
        $br = fn ($v) => $v ? implode('/', array_reverse(explode('-', (string) $v))) : '—';
        $txt = fn ($v) => ($v === null || $v === '') ? '—' : (string) $v;
        $out = [];
        if ($valorAntes !== $valorDepois) {
            $out[] = "valor {$rs($valorAntes)} → {$rs($valorDepois)}";
        }
        $campos = [
            'descricao' => ['descrição', $txt], 'parte' => [$t->tipo === 'receber' ? 'cliente' : 'fornecedor', $txt],
            'vencimento' => ['vencimento', $br], 'emissao' => ['emissão', $br], 'competencia' => ['competência', $txt],
            'plano_codigo' => ['plano de contas', $txt], 'forma_pagamento' => ['forma', $txt], 'conta_id' => ['conta', $txt],
            'documento' => ['documento', $txt], 'nf_numero' => ['nota fiscal', $txt], 'contrato' => ['contrato', $txt],
        ];
        foreach ($campos as $k => [$nome, $fmt]) {
            if (($antes[$k] ?? null) !== ($depois[$k] ?? null)) {
                $out[] = "{$nome} {$fmt($antes[$k] ?? null)} → {$fmt($depois[$k] ?? null)}";
            }
        }

        return $out;
    }

    private function proximoNumero(int $bizId, string $tipo): string
    {
        $prefixo = $tipo === 'receber' ? 'R' : 'P';
        $ultimo = (int) DB::table('fin_titulos')->where('business_id', $bizId)->where('numero', 'like', $prefixo . '-%')
            ->lockForUpdate()->selectRaw('MAX(CAST(SUBSTRING(numero, 3) AS UNSIGNED)) as max_n')->value('max_n');

        return sprintf('%s-%05d', $prefixo, $ultimo + 1);
    }

    private function podeBaixar(User $user, string $tipo): bool
    {
        return $user->can('superadmin')
            || $user->can($tipo === 'receber' ? 'financeiro.contas_receber.baixar' : 'financeiro.contas_pagar.pagar');
    }

    private function nomeOrigem(string $origem): string
    {
        return match ($origem) {
            'venda' => 'venda', 'compra' => 'compra', 'despesa' => 'despesa', 'recurring' => 'contrato recorrente',
            'folha' => 'folha', 'caixa' => 'caixa', default => $origem,
        };
    }

    private function estado(string $mensagem): JsonResponse
    {
        return $this->erro('estado', $mensagem, 409);
    }

    /** @param array<string, string> $campos */
    private function invalido(array $campos): JsonResponse
    {
        return response()->json(['erro' => 'validacao', 'mensagem' => (string) reset($campos), 'campos' => $campos], 422);
    }

    private function naoEncontrado(): JsonResponse
    {
        return $this->erro('nao_encontrado', 'Lançamento não encontrado.', 404);
    }

    private function erro(string $codigo, string $mensagem, int $status): JsonResponse
    {
        return response()->json(['erro' => $codigo, 'mensagem' => $mensagem], $status);
    }
}
