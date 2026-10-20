<?php

declare(strict_types=1);

namespace Modules\Financeiro\Services;

use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Modules\NFSe\Models\NfseEmissao;

/**
 * US-FIN-072 · UC-FLX-30 — NFS-e autorizada com retenção → o título a receber da mesma venda
 * passa a cobrar o LÍQUIDO (bruto − ISS retido − PIS − COFINS − CSLL retidos).
 *
 * ⚠️ REGRA MESTRE — CÁLCULO DE VALOR (memory/proibicoes.md). Travas:
 *  - chave POR NEGÓCIO `business.common_settings['financeiro_iss_retido_ajuste']['ativo']`,
 *    DEFAULT DESLIGADA: deploy não muda o valor de ninguém;
 *  - só age na TRANSIÇÃO de status da nota (observer) — nota já autorizada antes não é tocada;
 *    para essas existe só o `financeiro:iss-retido-ajuste --dry-run`, que não grava;
 *  - título com qualquer baixa (ou fora de `aberto`) NÃO é reescrito: fica `pendente` no
 *    `metadata.retencao_nfse` para decisão humana;
 *  - idempotente: a mesma nota não desconta duas vezes;
 *  - cancelamento da nota devolve o retido (soma de volta), com a mesma trava de baixa.
 * O bruto e cada retenção ficam no `metadata.retencao_nfse`; a troca de `valor_total`/
 * `valor_aberto` fica no activity_log do `Titulo` (LogsActivity).
 *
 * Multi-tenant Tier 0 (ADR 0093): o título é procurado pelo `business_id` DA NOTA, explícito.
 * Contas em centavos (int) para não acumular erro de float.
 */
class RetencaoNfseTituloService
{
    public const CHAVE = 'financeiro_iss_retido_ajuste';

    /** Estados em que o título está cobrando o líquido (a re-sincronização da venda preserva). */
    public const ESTADOS_LIQUIDO = ['aplicado', 'estorno_pendente'];

    public function ativo(int $businessId): bool
    {
        $bruto = DB::table('business')->where('id', $businessId)->value('common_settings');
        $settings = is_string($bruto) ? json_decode($bruto, true) : null;

        return (bool) (($settings[self::CHAVE] ?? [])['ativo'] ?? false);
    }

    /**
     * Retenções da nota em centavos.
     *
     * @return array{iss: int, pis: int, cofins: int, csll: int, total: int}
     */
    public function retencoes(NfseEmissao $nota): array
    {
        $iss = 0;
        if ($nota->iss_retido) {
            // Nota anterior à coluna (NULL): mesma conta do NfseEmissaoPayload::valorIssRetido().
            $iss = $nota->valor_iss_retido !== null
                ? $this->centavos($nota->valor_iss_retido)
                : $this->centavos(round((float) $nota->valor_servicos * (float) $nota->aliquota_iss, 2));
        }
        $r = [
            'iss' => $iss,
            'pis' => $this->centavos($nota->valor_ret_pis),
            'cofins' => $this->centavos($nota->valor_ret_cofins),
            'csll' => $this->centavos($nota->valor_ret_csll),
        ];
        $r['total'] = array_sum($r);

        return $r;
    }

    /**
     * O que aconteceria com o título desta nota — sem gravar nada (dry-run e base do aplicar).
     *
     * @return array{acao: string, titulo: ?Titulo, bruto: int, retido: int, liquido: int}
     */
    public function simular(NfseEmissao $nota): array
    {
        $ret = $this->retencoes($nota);
        $titulo = $this->tituloDaNota($nota);
        $bruto = $titulo ? $this->centavos($titulo->valor_total) : 0;
        $base = ['titulo' => $titulo, 'bruto' => $bruto, 'retido' => $ret['total'], 'liquido' => $bruto - $ret['total']];

        $ajuste = $titulo?->metadata['retencao_nfse'] ?? null;
        $acao = match (true) {
            $titulo === null => 'sem_titulo',
            $ret['total'] === 0 => 'sem_retencao',
            is_array($ajuste) && ($ajuste['estado'] ?? '') !== 'estornado' => 'ja_ajustado',
            ! $this->semBaixa($titulo) => 'pendente_baixa',
            $ret['total'] >= $bruto => 'pendente_retido_maior',
            default => 'aplicar',
        };

        return ['acao' => $acao] + $base;
    }

    /** Autorização da nota. Retorna a ação tomada (para log/teste). */
    public function aplicar(NfseEmissao $nota): string
    {
        if (! $this->ativo((int) $nota->business_id)) {
            return 'desligado';
        }

        return DB::transaction(function () use ($nota): string {
            $s = $this->simular($nota);
            $titulo = $s['titulo'];
            if (! in_array($s['acao'], ['aplicar', 'pendente_baixa', 'pendente_retido_maior'], true)) {
                return $s['acao'];
            }

            $registro = [
                'nfse_id' => $nota->id,
                'estado' => $s['acao'] === 'aplicar' ? 'aplicado' : 'pendente',
                'motivo' => $s['acao'] === 'aplicar' ? null : $s['acao'],
                'bruto' => $s['bruto'] / 100,
                'retencoes' => array_map(fn (int $c) => $c / 100, $this->retencoes($nota)),
                'retido_total' => $s['retido'] / 100,
                'em' => now()->toDateTimeString(),
            ];
            if ($s['acao'] === 'aplicar') {
                $titulo->valor_total = $s['liquido'] / 100;
                $titulo->valor_aberto = $s['liquido'] / 100;
            }
            $titulo->metadata = array_merge($titulo->metadata ?? [], ['retencao_nfse' => $registro]);
            $titulo->save();

            return $s['acao'];
        });
    }

    /** Cancelamento da nota: devolve o retido ao título. Não depende da chave (desfaz o que foi feito). */
    public function estornar(NfseEmissao $nota): string
    {
        return DB::transaction(function () use ($nota): string {
            $titulo = $this->tituloDaNota($nota);
            $ajuste = $titulo?->metadata['retencao_nfse'] ?? null;
            if (! is_array($ajuste) || (int) ($ajuste['nfse_id'] ?? 0) !== (int) $nota->id || $ajuste['estado'] === 'estornado') {
                return 'nada_a_estornar';
            }

            $acao = 'estornado';
            if ($ajuste['estado'] === 'aplicado') {
                if (! $this->semBaixa($titulo)) {
                    $acao = 'estorno_pendente';
                } else {
                    $retido = $this->centavos($ajuste['retido_total']);
                    $titulo->valor_total = ($this->centavos($titulo->valor_total) + $retido) / 100;
                    $titulo->valor_aberto = ($this->centavos($titulo->valor_aberto) + $retido) / 100;
                }
            }
            $titulo->metadata = array_merge($titulo->metadata, [
                'retencao_nfse' => array_merge($ajuste, ['estado' => $acao, 'estornado_em' => now()->toDateTimeString()]),
            ]);
            $titulo->save();

            return $acao;
        });
    }

    private function tituloDaNota(NfseEmissao $nota): ?Titulo
    {
        if (empty($nota->transaction_id)) {
            return null;
        }

        // SUPERADMIN: observer/comando sem sessão — business_id explícito DA NOTA (ADR 0093).
        return Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', (int) $nota->business_id)
            ->where('tipo', 'receber')
            ->where('origem', 'venda')
            ->where('origem_id', (int) $nota->transaction_id)
            ->whereNull('parcela_numero')
            ->where('status', '!=', 'cancelado')
            ->first();
    }

    private function semBaixa(Titulo $titulo): bool
    {
        // SUPERADMIN: baixas pelo titulo_id (business preservado pela FK do título).
        $somaBaixas = (float) TituloBaixa::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('titulo_id', $titulo->id)
            ->sum('valor_baixa');

        return $titulo->status === 'aberto'
            && $somaBaixas == 0.0
            && $this->centavos($titulo->valor_aberto) === $this->centavos($titulo->valor_total);
    }

    private function centavos(mixed $valor): int
    {
        return (int) round(((float) $valor) * 100);
    }
}
