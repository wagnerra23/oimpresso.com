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

/**
 * Lançamento manual do Financeiro pelo app (tela 06, "+ Novo", chave NOVO_LANCAMENTO).
 * Contrato: memory/requisitos/AppMobile/api/tela-06-financeiro.md §Novo lançamento; corpo `NovoLancamento`
 * de oimpresso-app src/lancamento-regras.ts.
 *
 * REGRA MESTRE (valor): o ERP revalida tudo e faz a conta em CENTAVOS inteiros — nunca float. Parcelado: o
 * valor é o TOTAL, dividido como `dividirParcelas` do app (a diferença de centavos vai para a 1ª), uma parcela
 * por mês a partir do vencimento. O título nasce em aberto (valor_aberto = valor_total), como o store da Visão
 * Unificada web. Recorrente fica fora: o Financeiro não tem gerador de título recorrente (422).
 *
 * Idempotência: tabela app_idempotencia (a da venda rápida), reservada na MESMA transação dos títulos.
 * Tier 0 (ADR 0093): business do token em tudo — plano, contato e conta de outro business são recusados.
 */
class FinanceiroLancamentosController extends Controller
{
    private const ROTA = 'financeiro.lancamentos';

    public function __construct(private FinanceiroController $financeiro)
    {
    }

    /** Criar lançamento: Financeiro + permissão de criar do tipo (ou a geral de lançamentos). */
    public static function podeCriar(User $user, string $tipo): bool
    {
        if ($user->can('superadmin') || $user->can('financeiro.lancamentos.create')) {
            return true;
        }

        return $user->can($tipo === 'receber' ? 'financeiro.contas_receber.create' : 'financeiro.contas_pagar.create');
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->financeiro->podeVerFinanceiro($user)) {
            return $this->semPermissao('Seu usuário não tem acesso ao financeiro.');
        }
        $bizId = (int) $user->business_id;
        $chave = trim((string) $request->header('Idempotency-Key', ''));

        $v = Validator::make(array_merge($request->all(), ['idempotency_key' => $chave]), [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'in:receber,pagar'],
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
            'parcelas' => ['nullable', 'integer', 'min:2', 'max:60'],
            'recorrencia' => ['nullable', 'array'],
        ], [
            'idempotency_key.required' => 'Envie o header Idempotency-Key.',
            'tipo.*' => 'Escolha a receber ou a pagar.',
            'descricao.required' => 'Escreva uma descrição.',
            'descricao.max' => 'Use até 120 caracteres.',
            'valor.*' => 'O valor precisa ser maior que zero.',
            'vencimento.*' => 'Escolha a data de vencimento.',
            'emissao.*' => 'Escolha a data de emissão.',
            'competencia.*' => 'Escolha o mês de competência.',
            'plano_codigo.*' => 'Escolha a conta do plano de contas.',
            'parcelas.*' => 'Use de 2 a 60 parcelas.',
        ]);
        if ($v->fails()) {
            return $this->invalido(collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all());
        }
        $d = $v->validated();
        $tipo = (string) $d['tipo'];
        if (! self::podeCriar($user, $tipo)) {
            return $this->semPermissao('Seu usuário não pode criar lançamento ' . ($tipo === 'receber' ? 'a receber.' : 'a pagar.'));
        }

        // Centavos inteiros a partir do texto do número: "10.10" → 1010; "10.101" é recusado (o usuário não veria).
        $centavos = self::centavos($request->input('valor'));
        $campos = [];
        if ($centavos === null) {
            $campos['valor'] = 'Use no máximo 2 casas depois da vírgula.';
        } elseif ($centavos < 1) {
            $campos['valor'] = 'O valor precisa ser maior que zero.';
        }
        if (! empty($d['recorrencia'])) {
            $campos['recorrencia'] = 'Lançamento recorrente ainda não é criado pelo app: use "Não repete" ou "Parcelado".';
        }
        if ($d['vencimento'] < $d['emissao']) {
            $campos['vencimento'] = 'O vencimento não pode ser antes da emissão.';
        }
        $parcelas = isset($d['parcelas']) ? (int) $d['parcelas'] : null;
        if ($parcelas !== null && $centavos !== null && $centavos < $parcelas) {
            $campos['parcelas'] = 'Cada parcela precisa ter pelo menos R$ 0,01.';
        }
        $plano = DB::table('fin_planos_conta')->where('business_id', $bizId)->where('codigo', $d['plano_codigo'])
            ->whereNull('deleted_at')->where('ativo', true)->first(['id', 'codigo', 'nome', 'tipo']);
        if ($plano === null) {
            $campos['plano'] = 'Conta não encontrada no plano de contas.';
        } elseif (DB::table('fin_planos_conta')->where('business_id', $bizId)->whereNull('deleted_at')
            ->where('codigo', 'like', $plano->codigo . '.%')->exists()) {
            $campos['plano'] = 'Escolha uma conta, não um grupo.';
        } elseif (! in_array($plano->tipo, $tipo === 'receber' ? ['receita'] : ['despesa', 'custo'], true)) {
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

        $texto = fn (?string $s) => ($s !== null && trim($s) !== '') ? trim($s) : null;
        $dados = [
            'tipo' => $tipo, 'descricao' => trim((string) $d['descricao']), 'contato_id' => $contato?->id,
            'centavos' => $centavos, 'vencimento' => $d['vencimento'], 'emissao' => $d['emissao'],
            'competencia' => $d['competencia'], 'forma' => $forma, 'conta_id' => isset($d['conta_id']) ? (int) $d['conta_id'] : null,
            'plano_id' => (int) $plano->id, 'documento' => $texto($d['documento'] ?? null), 'nf' => $texto($d['nf_numero'] ?? null),
            'observacao' => $texto($d['observacao'] ?? null), 'parcelas' => $parcelas,
        ];
        $hash = hash('sha256', (string) json_encode($dados));

        $ja = $this->idempotencia($bizId, (int) $user->id, $chave);
        if ($ja !== null) {
            return $this->repetida($ja, $hash);
        }

        $parte = $contato ? (trim((string) $contato->name) !== '' ? (string) $contato->name : (string) $contato->supplier_business_name) : null;
        DB::beginTransaction();
        try {
            try {
                $reservaId = DB::table('app_idempotencia')->insertGetId([
                    'business_id' => $bizId, 'user_id' => (int) $user->id, 'rota' => self::ROTA, 'chave' => $chave,
                    'hash_corpo' => $hash, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                DB::rollBack();
                $ja = $this->idempotencia($bizId, (int) $user->id, $chave);

                return $ja !== null ? $this->repetida($ja, $hash) : $this->emAndamento();
            }

            $titulos = $this->criarTitulos($bizId, (int) $user->id, $dados, $parte);
            $ids = array_map(fn ($t) => (int) $t->id, $titulos);
            $resposta = ['id' => $ids[0], 'ids' => $ids, 'recorrencia_id' => null];
            DB::table('app_idempotencia')->where('id', $reservaId)->update(['resposta' => json_encode($resposta), 'updated_at' => now()]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API app financeiro.lancamentos.store: ' . $e->getMessage(), ['business_id' => $bizId]);

            return response()->json(['erro' => 'falha', 'mensagem' => 'Não foi possível criar o lançamento.'], 500);
        }

        foreach ($titulos as $t) {
            \Modules\Financeiro\Events\TituloCriado::dispatch($t);
        }

        return response()->json($resposta, 201);
    }

    /**
     * Um título (único) ou N parcelas mensais. Número sequencial do business (R-/P-) com lock, o mesmo
     * padrão do store da Visão Unificada.
     *
     * @param  array<string, mixed>  $d
     * @return list<\Modules\Financeiro\Models\Titulo>
     */
    private function criarTitulos(int $bizId, int $userId, array $d, ?string $parte): array
    {
        $n = $d['parcelas'] ?? 1;
        $valores = self::dividirCentavos((int) $d['centavos'], $n);
        $prefixo = $d['tipo'] === 'receber' ? 'R' : 'P';
        $ultimo = (int) DB::table('fin_titulos')->where('business_id', $bizId)->where('numero', 'like', $prefixo . '-%')
            ->lockForUpdate()->selectRaw('MAX(CAST(SUBSTRING(numero, 3) AS UNSIGNED)) as max_n')->value('max_n');
        $vencimento = CarbonImmutable::parse((string) $d['vencimento']);

        $titulos = [];
        foreach ($valores as $i => $c) {
            $valor = sprintf('%d.%02d', intdiv($c, 100), $c % 100);
            $parcelado = $n > 1;
            $meta = array_filter([
                'descricao' => $parcelado ? "{$d['descricao']} (" . ($i + 1) . "/{$n})" : $d['descricao'],
                'documento' => $d['documento'] !== null ? ($parcelado ? $d['documento'] . '/' . ($i + 1) : $d['documento']) : null,
                'nfe_numero' => $d['nf'],
                'criado_via' => 'app',
            ], fn ($x) => $x !== null);

            $titulos[] = \Modules\Financeiro\Models\Titulo::create([
                'business_id' => $bizId,
                'numero' => sprintf('%s-%05d', $prefixo, $ultimo + $i + 1),
                'tipo' => $d['tipo'],
                'status' => 'aberto',
                'cliente_id' => $d['contato_id'],
                'cliente_descricao' => $parte,
                'valor_total' => $valor,
                'valor_aberto' => $valor,
                'moeda' => 'BRL',
                'emissao' => $d['emissao'],
                'vencimento' => $vencimento->addMonthsNoOverflow($i)->toDateString(),
                'competencia_mes' => $d['competencia'],
                'origem' => 'manual',
                'origem_id' => null,
                'parcela_numero' => $parcelado ? $i + 1 : null,
                'parcela_total' => $parcelado ? $n : null,
                'plano_conta_id' => $d['plano_id'],
                'forma_pagamento' => $d['forma'],
                'conta_bancaria_id' => $d['conta_id'],
                'observacoes' => $d['observacao'],
                'metadata' => $meta,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        return $titulos;
    }

    /** "1250.5" / 1250.5 → 125050 centavos; mais de 2 casas ou não-número → null. */
    public static function centavos(mixed $valor): ?int
    {
        $s = is_string($valor) ? trim($valor) : (is_int($valor) || is_float($valor) ? (string) $valor : '');
        // Float do JSON: "10.1" ou "1.0E-5"; normaliza pela representação curta do PHP.
        if (is_float($valor)) {
            $s = rtrim(rtrim(sprintf('%.6F', $valor), '0'), '.');
        }
        if (! preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/', $s, $m)) {
            return null;
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    /**
     * Divide em parcelas de centavos inteiros; a diferença vai para a 1ª (100,00 em 3 = 33,34 + 33,33 + 33,33)
     * — a regra de `dividirParcelas` do app, para o que o usuário viu na prévia ser o que o ERP grava.
     *
     * @return list<int>
     */
    public static function dividirCentavos(int $centavos, int $n): array
    {
        $base = intdiv($centavos, $n);
        $partes = array_fill(0, $n, $base);
        $partes[0] += $centavos - $base * $n;

        return $partes;
    }

    private function idempotencia(int $bizId, int $userId, string $chave): ?object
    {
        return DB::table('app_idempotencia')->where('business_id', $bizId)->where('user_id', $userId)
            ->where('rota', self::ROTA)->where('chave', $chave)->first();
    }

    private function repetida(object $ja, string $hash): JsonResponse
    {
        if (! hash_equals((string) $ja->hash_corpo, $hash)) {
            return response()->json([
                'erro' => 'idempotencia_conflito',
                'mensagem' => 'Esta chave já foi usada para outro lançamento. Gere uma chave nova.',
            ], 422);
        }
        if ($ja->resposta === null) {
            return $this->emAndamento();
        }

        return response()->json(json_decode((string) $ja->resposta, true), 200);
    }

    private function emAndamento(): JsonResponse
    {
        return response()->json(['erro' => 'em_andamento', 'mensagem' => 'Este lançamento ainda está sendo registrado. Tente de novo.'], 409);
    }

    /** @param array<string, string> $campos */
    private function invalido(array $campos): JsonResponse
    {
        return response()->json(['erro' => 'validacao', 'mensagem' => (string) reset($campos), 'campos' => $campos], 422);
    }

    private function semPermissao(string $mensagem): JsonResponse
    {
        return response()->json(['erro' => 'sem_permissao', 'mensagem' => $mensagem], 403);
    }
}
