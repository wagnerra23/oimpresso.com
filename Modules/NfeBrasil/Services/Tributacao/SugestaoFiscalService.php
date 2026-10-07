<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Jana\Ai\Agents\SugestaoFiscalAgent;
use Modules\Jana\Ai\Tools\Fiscal\ProdutoFiscalTool;
use Modules\NfeBrasil\Http\Requests\UpsertRegraTributariaRequest;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Models\NfeSugestaoFiscal;
use Throwable;

/**
 * Sugestões da Jana na tributação (playbook Fiscal thread 10 · D-IA · UC-NFTR-10..13).
 *
 * Três regras duras:
 *  1. A sugestão NUNCA se aplica sozinha — nasce `pendente`; aplicar é `aceitar()`, chamado por
 *     uma pessoa com `nfe.tributacao.manage` (o FormRequest do controller checa).
 *  2. A saída do LLM é dado não confiável: só vira sugestão se o alvo é da empresa, o tipo e o
 *     risco são conhecidos, o NCM tem 8 dígitos e a regra traz só campos permitidos.
 *  3. A Jana fora do ar não afeta nada: `gerar()` devolve `indisponivel=true` e segue. Emissão e
 *     cadastro não chamam este serviço.
 */
class SugestaoFiscalService
{
    /** Campos de regra que uma sugestão pode mudar — validados pelas regras do formulário de regra. */
    private const CAMPOS_REGRA = ['cfop', 'csosn', 'cst', 'c_class_trib', 'cst_ibs', 'cst_cbs'];

    /**
     * @return array{indisponivel: bool, criadas: int}
     */
    public function gerar(int $businessId, ?int $productId = null): array
    {
        $produtos = (new ProdutoFiscalTool($businessId))->dados($productId);
        if ($produtos === []) {
            return ['indisponivel' => false, 'criadas' => 0];
        }

        try {
            $agent = new SugestaoFiscalAgent($produtos);
            $resposta = $agent->prompt($agent->montarPrompt());
            $itens = $resposta['sugestoes'] ?? [];
        } catch (Throwable $e) {
            Log::warning('nfe.sugestao_fiscal.indisponivel', [
                'business_id' => $businessId,
                'erro'        => get_class($e),
            ]);

            return ['indisponivel' => true, 'criadas' => 0];
        }

        $produtoIds = array_column($produtos, 'id');
        $criadas = 0;
        foreach (is_array($itens) ? $itens : [] as $item) {
            $linha = $this->normalizar($businessId, (array) $item, $produtoIds);
            if ($linha !== null) {
                NfeSugestaoFiscal::query()->create($linha + ['business_id' => $businessId, 'status' => 'pendente']);
                $criadas++;
            }
        }

        return ['indisponivel' => false, 'criadas' => $criadas];
    }

    /**
     * Aplica a sugestão pelo caminho normal e registra quem aceitou.
     *
     * @throws ValidationException risco alto sem confirmação, sugestão já decidida, ou valor inválido
     */
    public function aceitar(NfeSugestaoFiscal $s, User $autor, bool $confirmouLeitura): NfeSugestaoFiscal
    {
        if ($s->status !== 'pendente') {
            throw ValidationException::withMessages(['status' => 'Esta sugestão já foi decidida.']);
        }
        if ($s->risco === 'alto' && ! $confirmouLeitura) {
            throw ValidationException::withMessages([
                'confirmou_leitura' => 'Sugestão de risco alto: confirme que leu o motivo antes de aceitar.',
            ]);
        }

        return DB::transaction(function () use ($s, $autor, $confirmouLeitura): NfeSugestaoFiscal {
            $alterado = null;

            if ($s->tipo === 'ncm') {
                $ncm = (string) ($s->valor_sugerido['ncm'] ?? '');
                Validator::make(['ncm' => $ncm], ['ncm' => ['required', 'digits:8']])->validate();
                $n = DB::table('products')->where('business_id', $s->business_id)->where('id', $s->alvo_id)
                    ->update(['ncm' => $ncm, 'updated_at' => now()]);
                abort_if($n === 0, 404);
                $alterado = ['produto_id' => $s->alvo_id, 'ncm' => $ncm];
            } elseif ($s->tipo === 'regra') {
                $regra = NfeFiscalRule::query()->where('business_id', $s->business_id)->where('id', $s->alvo_id)->firstOrFail();
                $campos = array_intersect_key((array) $s->valor_sugerido, array_flip(self::CAMPOS_REGRA));
                $todas = (new UpsertRegraTributariaRequest())->rules();
                $dados = Validator::make(
                    array_merge($regra->only(self::CAMPOS_REGRA), $campos),
                    array_intersect_key($todas, array_flip(self::CAMPOS_REGRA)),
                )->validate();
                // Thread 15a: a versão nova entra na revisão do contador com origem "jana".
                $nova = RevisaoContadorService::comOrigem('jana', fn () => NfeFiscalRule::temVersionamento()
                    ? $regra->novaVersao($dados)
                    : tap($regra)->update($dados));
                $alterado = ['regra_id' => $nova->id, 'campos' => array_keys($campos)];
            }
            // natureza e inconsistencia: aceitar registra a decisão; não há escrita automática —
            // a natureza de operação vira regra por quem a cadastra (thread 07).

            $s->forceFill([
                'status'            => 'aceita',
                'decidido_por'      => $autor->id,
                'decidido_em'       => now(),
                'confirmou_leitura' => $confirmouLeitura,
            ])->save();

            activity('nfe.tributacao')
                ->causedBy($autor)
                ->performedOn($s)
                ->withProperties(['business_id' => $s->business_id, 'tipo' => $s->tipo, 'alterado' => $alterado])
                ->log('sugestao.aceita');

            return $s;
        });
    }

    public function descartar(NfeSugestaoFiscal $s, User $autor): NfeSugestaoFiscal
    {
        if ($s->status !== 'pendente') {
            throw ValidationException::withMessages(['status' => 'Esta sugestão já foi decidida.']);
        }

        $s->forceFill(['status' => 'descartada', 'decidido_por' => $autor->id, 'decidido_em' => now()])->save();

        activity('nfe.tributacao')
            ->causedBy($autor)
            ->performedOn($s)
            ->withProperties(['business_id' => $s->business_id, 'tipo' => $s->tipo])
            ->log('sugestao.descartada');

        return $s;
    }

    /**
     * Saída do LLM → linha gravável, ou null se não for confiável.
     *
     * @param array<string, mixed> $item
     * @param list<int> $produtoIds produtos da empresa entregues à Jana
     * @return array<string, mixed>|null
     */
    private function normalizar(int $businessId, array $item, array $produtoIds): ?array
    {
        $tipo  = (string) ($item['tipo'] ?? '');
        $risco = (string) ($item['risco'] ?? '');
        $alvo  = (int) ($item['alvo_id'] ?? 0);
        $motivo = trim((string) ($item['motivo'] ?? ''));
        if (! in_array($tipo, NfeSugestaoFiscal::TIPOS, true) || ! in_array($risco, NfeSugestaoFiscal::RISCOS, true) || $motivo === '') {
            return null;
        }

        $bruto = (string) ($item['valor_sugerido'] ?? '');
        if ($tipo === 'regra') {
            $existe = DB::table('nfe_fiscal_rules')->where('business_id', $businessId)->where('id', $alvo)
                ->whereNull('deleted_at')->exists();
            $valor = json_decode($bruto, true);
            $valor = is_array($valor) ? array_intersect_key($valor, array_flip(self::CAMPOS_REGRA)) : [];
            if (! $existe || $valor === []) {
                return null;
            }
            $alvoTipo = 'regra';
        } else {
            if (! in_array($alvo, $produtoIds, true)) {
                return null;
            }
            if ($tipo === 'ncm') {
                $ncm = preg_replace('/\D/', '', $bruto);
                if (strlen((string) $ncm) !== 8) {
                    return null;
                }
                $valor = ['ncm' => $ncm];
            } else {
                $valor = ['texto' => mb_substr($bruto, 0, 500)];
            }
            $alvoTipo = 'produto';
        }

        return [
            'tipo'           => $tipo,
            'alvo_tipo'      => $alvoTipo,
            'alvo_id'        => $alvo,
            'valor_sugerido' => $valor,
            'confianca'      => max(0.0, min(1.0, (float) ($item['confianca'] ?? 0))),
            'risco'          => $risco,
            'motivo'         => mb_substr($motivo, 0, 1000),
        ];
    }
}
