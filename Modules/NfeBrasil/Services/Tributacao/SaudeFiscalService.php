<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use Illuminate\Support\Facades\DB;

/**
 * Saúde fiscal — o que vai virar rejeição antes da SEFAZ dizer (playbook Fiscal thread 14,
 * UC-NFTR-24 · UC-NFTR-25 · D-SUPORTE · US-NFE-010 "Filtro Suspeitas").
 *
 * SÓ LÊ. Nenhum método escreve; cada pendência traz a rota que a resolve. Toda consulta filtra
 * `business_id` explícito (roda fora de request também) — ADR 0093.
 *
 * As fontes são as mesmas que a emissão usa, para a lista não divergir do que a nota faz:
 *   - NCM padrão: `tributacao_default.ncm_default` → `business.ncm_padrao` (NfeService:125);
 *   - NCM válido: 8 dígitos e ≠ 00000000 (NfeService:465 cai no padrão fora disso);
 *   - itens que saíram com o NCM padrão: `nfe_emissoes.metadata.itens_ncm_padrao` (thread 17).
 *
 * Bloqueante = a emissão falha ou a SEFAZ rejeita (NCM padrão inválido, certificado ausente ou
 * vencido). O resto é aviso e não muda o "pronto pra emitir".
 */
class SaudeFiscalService
{
    public const DIAS_CERTIFICADO = 30;
    public const DIAS_EMISSOES = 30;
    private const AMOSTRA = 20;

    /**
     * @return array{pronto: bool, bloqueantes: int,
     *   itens: list<array{tipo: string, bloqueante: bool, titulo: string, contagem: int, ids: list<int>, rota: string}>}
     */
    public function avaliar(int $businessId): array
    {
        $itens = array_values(array_filter([
            $this->ncmPadrao($businessId),
            $this->certificado($businessId),
            $this->emitidosComNcmPadrao($businessId),
            $this->produtosSemNcm($businessId),
        ]));
        $bloqueantes = count(array_filter($itens, fn (array $i) => $i['bloqueante']));

        return ['pronto' => $bloqueantes === 0, 'bloqueantes' => $bloqueantes, 'itens' => $itens];
    }

    public static function ncmValido(?string $ncm): bool
    {
        return $ncm !== null && preg_match('/^\d{8}$/', $ncm) === 1 && $ncm !== '00000000';
    }

    private function ncmPadrao(int $biz): ?array
    {
        $default = DB::table('nfe_business_configs')->where('business_id', $biz)->value('tributacao_default');
        $ncm = json_decode((string) $default, true)['ncm_default']
            ?? DB::table('business')->where('id', $biz)->value('ncm_padrao');

        return self::ncmValido($ncm === null ? null : (string) $ncm) ? null : $this->item(
            'ncm_padrao_invalido', true, 'NCM padrão da empresa ausente ou 00000000', 1, [],
            'nfe-brasil.tributacao.config.show',
        );
    }

    private function certificado(int $biz): ?array
    {
        $validoAte = DB::table('nfe_certificados')->where('business_id', $biz)->where('ativo', true)
            ->whereNull('deleted_at')->max('valido_ate');
        $hoje = now()->startOfDay();

        if ($validoAte === null || $hoje->gt($validoAte)) {
            return $this->item('certificado_invalido', true, 'Certificado digital ausente ou vencido', 1, [],
                'nfe-brasil.certificado.status');
        }
        $dias = (int) $hoje->diffInDays($validoAte, false);

        return $dias <= self::DIAS_CERTIFICADO
            ? $this->item('certificado_vencendo', false, "Certificado vence em {$dias} dia(s)", $dias, [],
                'nfe-brasil.certificado.status')
            : null;
    }

    /** Produtos que já saíram em nota com o NCM padrão e continuam sem NCM próprio. */
    private function emitidosComNcmPadrao(int $biz): ?array
    {
        $ids = [];
        DB::table('nfe_emissoes')->where('business_id', $biz)->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDays(self::DIAS_EMISSOES))
            ->whereNotNull('metadata')->orderBy('id')->pluck('metadata')
            ->each(function ($m) use (&$ids) {
                foreach ((json_decode((string) $m, true)['itens_ncm_padrao'] ?? []) as $it) {
                    if (! empty($it['product_id'])) {
                        $ids[(int) $it['product_id']] = true;
                    }
                }
            });
        $ids = $this->semNcm($biz, array_keys($ids));

        return $ids === [] ? null : $this->item('emitido_com_ncm_padrao', false,
            'Produtos que saíram em nota com o NCM padrão', count($ids), array_slice($ids, 0, self::AMOSTRA),
            'products.unificado.index');
    }

    private function produtosSemNcm(int $biz): ?array
    {
        $ids = $this->semNcm($biz, null);

        return $ids === [] ? null : $this->item('produto_sem_ncm', false, 'Produtos ativos sem NCM válido',
            count($ids), array_slice($ids, 0, self::AMOSTRA), 'products.unificado.index');
    }

    /** @param list<int>|null $restringir */
    private function semNcm(int $biz, ?array $restringir): array
    {
        if ($restringir === []) {
            return [];
        }
        $q = DB::table('products')->where('business_id', $biz)->where('is_inactive', 0)
            ->whereNull('deleted_at')->where(fn ($w) => $w->whereNull('type')->orWhere('type', '!=', 'modifier'))
            ->where(fn ($w) => $w->whereRaw("ncm NOT REGEXP '^[0-9]{8}$'")->orWhere('ncm', '00000000'));
        if ($restringir !== null) {
            $q->whereIn('id', $restringir);
        }

        return $q->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function item(string $tipo, bool $bloqueante, string $titulo, int $contagem, array $ids, string $rota): array
    {
        return compact('tipo', 'bloqueante', 'titulo', 'contagem', 'ids', 'rota');
    }
}
