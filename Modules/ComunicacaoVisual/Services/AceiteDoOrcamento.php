<?php

namespace Modules\ComunicacaoVisual\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cliente aceita o orçamento da gráfica pelo link (US-COMVIS-023).
 *
 * Gerar: o operador pede o link de um orçamento salvo (rascunho ou enviado, ainda sem OS). Sai um
 * token aleatório; só o sha256 fica em `comvis_orcamento_aceites`, válido por DIAS_VALIDADE dias.
 * Gerar outro fecha o anterior. O orçamento passa a `enviado`.
 *
 * Decidir (cliente, sem login), uso único:
 *   aceitar → o orçamento vira OS pelo MESMO `OrcamentoViraOs::aprovar` da tela (nenhum valor é
 *             recalculado: a OS leva o total gravado — regra mestre de valor);
 *   ajuste  → o orçamento volta a `rascunho` e o comentário fica no link.
 * Recusa (exceção \DomainException, mensagem para o cliente): link desconhecido, vencido ou já
 * usado; orçamento já aprovado, recusado ou expirado; data de validade passada.
 *
 * Tier 0 (ADR 0093): o token resolve a empresa; tudo depois filtra pelo `business_id` do link.
 * Nenhum escopo é desligado: todas as consultas são `DB::table` com `business_id` explícito.
 */
final class AceiteDoOrcamento
{
    public const DIAS_VALIDADE = 7;

    /** Status de orçamento que ainda aceitam link e decisão do cliente. */
    public const ABERTOS = ['rascunho', 'enviado'];

    public function __construct(private readonly OrcamentoViraOs $viraOs) {}

    /**
     * @return array{token: string, expira_em: Carbon}
     * @throws \DomainException orçamento que não pode mais ir ao cliente
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException orçamento de outra empresa ou inexistente
     */
    public function gerar(int $businessId, int $orcamentoId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($businessId, $orcamentoId, $userId) {
            $orc = DB::table('comvis_orcamentos')->where('business_id', $businessId)->where('id', $orcamentoId)
                ->whereNull('deleted_at')->lockForUpdate()->first()
                ?? throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel('comvis_orcamentos', [$orcamentoId]);
            if (! in_array($orc->status, self::ABERTOS, true)) {
                throw new \DomainException("Orçamento {$orc->status} não vai mais ao cliente.");
            }
            if (DB::table('comvis_os')->where('business_id', $businessId)->where('orcamento_id', $orcamentoId)->exists()) {
                throw new \DomainException('Este orçamento já virou OS.');
            }

            $agora = now();
            DB::table('comvis_orcamento_aceites')->where('business_id', $businessId)->where('orcamento_id', $orcamentoId)
                ->whereNull('decisao')->where('expira_em', '>', $agora)->update(['expira_em' => $agora, 'updated_at' => $agora]);

            $token = Str::random(48);
            $expira = $agora->copy()->addDays(self::DIAS_VALIDADE);
            DB::table('comvis_orcamento_aceites')->insert([
                'business_id' => $businessId, 'orcamento_id' => $orcamentoId, 'token_hash' => self::hash($token),
                'expira_em' => $expira, 'created_by' => $userId, 'created_at' => $agora, 'updated_at' => $agora,
            ]);
            if ($orc->status === 'rascunho') {
                DB::table('comvis_orcamentos')->where('business_id', $businessId)->where('id', $orcamentoId)
                    ->update(['status' => 'enviado', 'updated_at' => $agora]);
            }

            return ['token' => $token, 'expira_em' => $expira];
        });
    }

    /**
     * O que a página pública mostra: só o que o cliente precisa ver (nunca custo nem margem).
     * Null para token desconhecido. `situacao` diz se o cliente ainda pode decidir.
     *
     * @return array<string, mixed>|null
     */
    public function paraCliente(string $token): ?array
    {
        $link = $this->link($token);
        if ($link === null) {
            return null;
        }
        $biz = (int) $link->business_id;
        $orc = DB::table('comvis_orcamentos')->where('business_id', $biz)->where('id', $link->orcamento_id)->first();
        if ($orc === null) {
            return null;
        }
        $itens = DB::table('comvis_orcamento_itens')->where('business_id', $biz)->where('orcamento_id', $orc->id)
            ->orderBy('ordem')->orderBy('id')->get(['descricao', 'largura_m', 'altura_m', 'quantidade', 'subtotal']);

        return [
            'empresa'       => (string) DB::table('business')->where('id', $biz)->value('name'),
            'numero'        => $orc->numero,
            'data_emissao'  => $orc->data_emissao,
            'data_validade' => $orc->data_validade,
            'itens'         => $itens->map(fn ($i) => [
                'descricao' => $i->descricao, 'largura_m' => (float) $i->largura_m, 'altura_m' => (float) $i->altura_m,
                'quantidade' => (int) $i->quantidade, 'subtotal' => (float) $i->subtotal,
            ])->all(),
            'subtotal'         => (float) $orc->subtotal,
            'desconto'         => (float) $orc->desconto,
            'extras'           => (float) $orc->extras,
            'custo_instalacao' => (float) $orc->custo_instalacao,
            'custo_entrega'    => (float) $orc->custo_entrega,
            'total'            => (float) $orc->total,
            'situacao'         => $this->situacao($link, $orc),
            'decisao'          => $link->decisao,
        ];
    }

    /**
     * @return array{decisao: string, os_numero: string|null}
     * @throws \DomainException o cliente não pode decidir por este link
     */
    public function decidir(string $token, string $decisao, ?string $comentario = null): array
    {
        if (! in_array($decisao, ['aceito', 'ajuste'], true)) {
            throw new \DomainException('Escolha aceitar ou pedir ajuste.');
        }
        $comentario = $comentario === null ? null : trim(mb_substr($comentario, 0, 2000));
        if ($decisao === 'ajuste' && ($comentario === null || $comentario === '')) {
            throw new \DomainException('Conte o que precisa mudar no orçamento.');
        }

        return DB::transaction(function () use ($token, $decisao, $comentario) {
            $link = DB::table('comvis_orcamento_aceites')->where('token_hash', self::hash($token))->lockForUpdate()->first();
            if ($link === null) {
                throw new \DomainException('Este link não existe.');
            }
            $biz = (int) $link->business_id;
            $orc = DB::table('comvis_orcamentos')->where('business_id', $biz)->where('id', $link->orcamento_id)
                ->lockForUpdate()->first();
            $situacao = $orc === null ? 'indisponivel' : $this->situacao($link, $orc);
            if ($situacao !== 'aberto') {
                throw new \DomainException(match ($situacao) {
                    'decidido' => 'Este link já foi usado.',
                    'vencido'  => 'Este link venceu. Peça um novo à gráfica.',
                    'fora_da_validade' => 'A validade deste orçamento passou. Peça um novo à gráfica.',
                    default    => 'Este orçamento não está mais disponível.',
                });
            }

            $agora = now();
            $osNumero = null;
            if ($decisao === 'aceito') {
                [$os] = $this->viraOs->aprovar($biz, (int) $orc->id, null);
                $osNumero = $os->numero;
            } else {
                DB::table('comvis_orcamentos')->where('business_id', $biz)->where('id', $orc->id)
                    ->update(['status' => 'rascunho', 'updated_at' => $agora]);
            }
            DB::table('comvis_orcamento_aceites')->where('business_id', $biz)->where('id', $link->id)->update([
                'decisao' => $decisao, 'comentario' => $comentario, 'decidido_em' => $agora, 'updated_at' => $agora,
            ]);

            return ['decisao' => $decisao, 'os_numero' => $osNumero];
        });
    }

    /** aberto | decidido | vencido | fora_da_validade | indisponivel */
    private function situacao(object $link, object $orc): string
    {
        if ($link->decisao !== null) {
            return 'decidido';
        }
        if (Carbon::parse($link->expira_em)->lessThanOrEqualTo(now())) {
            return 'vencido';
        }
        if (! in_array($orc->status, self::ABERTOS, true) || ($orc->deleted_at ?? null) !== null) {
            return 'indisponivel';
        }
        if ($orc->data_validade !== null && Carbon::parse($orc->data_validade)->endOfDay()->isPast()) {
            return 'fora_da_validade';
        }

        return 'aberto';
    }

    private function link(string $token): ?object
    {
        return DB::table('comvis_orcamento_aceites')->where('token_hash', self::hash($token))->first();
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
