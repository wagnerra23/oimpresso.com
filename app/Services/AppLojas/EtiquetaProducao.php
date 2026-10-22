<?php

declare(strict_types=1);

namespace App\Services\AppLojas;

/**
 * Código do QR da OS: `oimpresso:p:<business_hash>:<os_tipo>:<os_id>` (ex. `oimpresso:p:3f9a…:comvis_os:501`).
 *
 * Um formato só para a etiqueta e o PDF da OS, lido por três consumidores: a API do app
 * (`GET /api/app/producao/etiqueta/{codigo}`, PR 06), o leitor do app (PR 08) e a tela do operador
 * no celular do PCP (thread 04, que lê os dois últimos segmentos como tipo e id da OS). O `os_tipo`
 * é o do PCP (`pcp_programacoes.os_tipo`, ADR 0431); hoje só `comvis_os` é impresso. O
 * `business_hash` NÃO é o business_id: é um HMAC com a APP_KEY, para que o QR não exponha nem
 * permita adivinhar o id da empresa.
 *
 * `ler()` aceita também o formato antigo sem tipo (`oimpresso:p:<hash>:<os_id>`), tratado como
 * `comvis_os`, para não invalidar etiqueta já impressa nem o contrato do app.
 *
 * QR da ETAPA (thread 05 do PCP · E05 modelo `termica_100x50_etapa`): o mesmo código com o sufixo
 * `:e<programacao_id>` — `oimpresso:p:<hash>:comvis_os:501:e77`. Quem lê abre direto aquela etapa
 * em vez de "escolha sua etapa". O sufixo é opcional: sem ele, nada muda para quem já lia. O leitor
 * do app (`oimpresso-app`, `apontamento-regras.ts`) ainda não aceita o sufixo; enquanto não aceitar,
 * só a etiqueta térmica (escolha da empresa) leva o QR da etapa, e o A4 segue no formato da OS.
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-02-apontamento.md.
 */
final class EtiquetaProducao
{
    public const PREFIXO = 'oimpresso:p:';

    public const OS_TIPO_PADRAO = 'comvis_os';

    /** 12 hex = 48 bits: basta para não colidir entre empresas e cabe num QR pequeno. */
    private const TAMANHO_HASH = 12;

    public static function hashDaEmpresa(int $bizId): string
    {
        return substr(hash_hmac('sha256', 'etiqueta-producao:' . $bizId, (string) config('app.key')), 0, self::TAMANHO_HASH);
    }

    public static function codigo(int $bizId, int $osId, string $osTipo = self::OS_TIPO_PADRAO, ?int $programacaoId = null): string
    {
        if (! preg_match('/^[a-z][a-z_]{0,19}$/', $osTipo)) {
            throw new \InvalidArgumentException('os_tipo inválido para o QR.');
        }
        if ($programacaoId !== null && $programacaoId < 1) {
            throw new \InvalidArgumentException('Etapa inválida para o QR.');
        }

        return self::PREFIXO . self::hashDaEmpresa($bizId) . ':' . $osTipo . ':' . $osId
            . ($programacaoId === null ? '' : ':e' . $programacaoId);
    }

    /**
     * Lê um código no formato do QR. Devolve null se não for o formato (o chamador decide se aceita
     * número digitado); `empresa` diz se o hash é o da empresa de quem leu.
     *
     * `programacao_id` vem preenchido só no QR da etapa (sufixo `:e<id>`).
     *
     * @return array{empresa: bool, os_tipo: string, pedido_id: int, programacao_id: ?int}|null
     */
    public static function ler(string $codigo, int $bizId): ?array
    {
        if (! preg_match('/^oimpresso:p:([0-9a-f]{' . self::TAMANHO_HASH . '}):(?:([a-z][a-z_]{0,19}):)?([1-9][0-9]{0,18})(?::e([1-9][0-9]{0,18}))?$/', trim($codigo), $m)) {
            return null;
        }

        return [
            'empresa' => hash_equals(self::hashDaEmpresa($bizId), $m[1]),
            'os_tipo' => $m[2] !== '' ? $m[2] : self::OS_TIPO_PADRAO,
            'pedido_id' => (int) $m[3],
            'programacao_id' => isset($m[4]) ? (int) $m[4] : null,
        ];
    }
}
