<?php

namespace Modules\Fiscal\Services;

/**
 * Dicionário de códigos cStat da SEFAZ — DERIVADO da tabela oficial, nunca escrito à mão.
 *
 * ## Por que existe (incidente 2026-09-04, biz=1)
 *
 * O mapa anterior vivia como array literal no `NfeCockpitController::sefazCodes()`: 12 códigos
 * digitados à mão. Medido contra a tabela oficial, **5 dos 12 estavam ERRADOS** — e errado aqui
 * é pior que ausente, porque manda a contadora investigar a coisa errada:
 *
 *   | cód | dizia (à mão)          | é (tabela oficial)                                  |
 *   |-----|------------------------|-----------------------------------------------------|
 *   | 104 | Autorizada (NFC-e)     | Lote processado                                     |
 *   | 220 | Duplicidade            | Prazo de Cancelamento superior ao previsto na Lei   |
 *   | 691 | NCM divergente         | Chave de Acesso da NF-e diverge da Chave do EPEC    |
 *   | 778 | CST/CFOP inválido      | Informado NCM inexistente                           |
 *   | 999 | Processando            | Erro não catalogado                                 |
 *
 * O 778 tem prova direta em produção: a nota id=8 de biz=1 tem `cstat=778` e a SEFAZ gravou
 * `motivo = "Rejeicao: Informado NCM inexistente [nItem:1]"` — enquanto a tela dizia CST/CFOP.
 *
 * ## Fonte
 *
 * `vendor/nfephp-org/sped-nfe/storage/cstat.json` — 528 códigos, distribuído com o SDK que este
 * projeto usa para FALAR com a SEFAZ (`nfephp-org/sped-nfe`, composer.json). Derivar dele significa
 * que atualizar o SDK atualiza a tradução: escrito+lembrado apodrece, derivado sobrevive (ADR 0256).
 *
 * ## Degradação declarada
 *
 * O pacote está pinado em `dev-master` e o caminho do arquivo é interno a ele. Se sumir ou vier
 * inválido, este serviço devolve mapa VAZIO — nunca um mapa inventado. A tela então cai no rótulo
 * do status de domínio (`rejeitada`, `inutilizada`, …), que sempre existe. Perde-se especificidade,
 * nunca se ganha mentira.
 */
class SefazCstatService
{
    /** Caminho da tabela dentro do pacote nfephp-org/sped-nfe. */
    public const CAMINHO_TABELA = 'vendor/nfephp-org/sped-nfe/storage/cstat.json';

    /**
     * Códigos transitórios ou de indisponibilidade do webservice — âmbar, não vermelho.
     *
     * Decisão de UI (não é dado fiscal): a nota ainda não tem veredito (103/105) ou a SEFAZ é que
     * está fora do ar (108/109). Pintar de vermelho mandaria o operador corrigir uma nota que não
     * tem defeito. Todo o resto segue o campo `status` da própria tabela: "1" = aceito, o resto
     * rejeitado.
     */
    protected const CODIGOS_AMBAR = [103, 105, 108, 109];

    /** @var array<int, array{tone: string, label: string}>|null Memo por processo. */
    protected static ?array $tabela = null;

    /**
     * Mapa {cstat => {tone, label, hint}} restrito aos códigos pedidos.
     *
     * O `hint` sai vazio de propósito: quem explica a nota é o `motivo` que a SEFAZ gravou nela
     * (mais específico — traz o `[nItem:N]`), e ele já viaja em cada linha do payload.
     *
     * @param  array<int, int|string|null>  $codigos
     * @return array<int, array{tone: string, label: string, hint: string}>
     */
    public function mapaPara(array $codigos): array
    {
        $tabela = static::tabela();
        $mapa   = [];

        foreach ($codigos as $codigo) {
            $codigo = (int) $codigo;

            if ($codigo <= 0 || isset($mapa[$codigo]) || ! isset($tabela[$codigo])) {
                continue;
            }

            $mapa[$codigo] = $tabela[$codigo] + ['hint' => ''];
        }

        ksort($mapa);

        return $mapa;
    }

    /**
     * Tabela inteira {cstat => {tone, label}}, memoizada. Vazia se a fonte não estiver disponível.
     *
     * @return array<int, array{tone: string, label: string}>
     */
    public static function tabela(): array
    {
        if (static::$tabela !== null) {
            return static::$tabela;
        }

        return static::$tabela = static::carregar();
    }

    /** Descarta o memo — só para teste. */
    public static function esquecer(): void
    {
        static::$tabela = null;
    }

    /**
     * @return array<int, array{tone: string, label: string}>
     */
    protected static function carregar(): array
    {
        $caminho = base_path(static::CAMINHO_TABELA);

        if (! is_file($caminho) || ! is_readable($caminho)) {
            return [];
        }

        $bruto = json_decode((string) file_get_contents($caminho), true);

        if (! is_array($bruto)) {
            return [];
        }

        $tabela = [];

        foreach ($bruto as $linha) {
            if (! is_array($linha) || ! isset($linha['cod'], $linha['msg'])) {
                continue;
            }

            $codigo = (int) $linha['cod'];
            $rotulo = static::limparRotulo((string) $linha['msg']);

            if ($codigo <= 0 || $rotulo === '') {
                continue;
            }

            $tabela[$codigo] = [
                'tone'  => static::tom($codigo, (string) ($linha['status'] ?? '')),
                'label' => $rotulo,
            ];
        }

        return $tabela;
    }

    /**
     * A tabela oficial embute exemplos de placeholder na mensagem — ex.:
     * `Duplicidade de NF-e [nRec:999999999999999]`. Numa pílula de lista isso é ruído: o valor
     * REAL daquela nota vem no `motivo`. Some o colchete, some o prefixo "Rejeição:" (a cor já
     * diz que é rejeição) e o rótulo cabe na coluna.
     */
    protected static function limparRotulo(string $msg): string
    {
        $msg = (string) preg_replace('/\s*\[[^\]]*\]/u', '', $msg);
        $msg = (string) preg_replace('/^Rejei(?:ção|cao)\s*:\s*/ui', '', $msg);

        return trim($msg);
    }

    protected static function tom(int $codigo, string $status): string
    {
        if (in_array($codigo, static::CODIGOS_AMBAR, true)) {
            return 'warn';
        }

        return $status === '1' ? 'ok' : 'bad';
    }
}
