<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

/**
 * Aviso de CST/CFOP incoerente — avisa, não bloqueia (Sabor Brasil, [F] 2026-11-04).
 *
 * "Não vamos passar por cima de nenhuma regra fiscal. O Ricardo vai poder emitir com o CST e o
 * CFOP que está emitindo (ele sabe que não é recomendável, mas faz para não perder o cliente),
 * mas o sistema vai avisar que está errado."
 *
 * Por isso esta classe SÓ devolve textos. Ninguém recusa nada com base nela: a regra é salva, a
 * nota sai, e o aviso aparece na regra, na nota (metadata `avisos_fiscais`) e na Saúde fiscal.
 *
 * Sem DB: tudo entra por parâmetro. As combinações vêm do Ajuste SINIEF 07/2005 (tabela de CFOP),
 * da tabela B do CST/CSOSN e do Convênio ICMS 142/2018 (CEST obrigatório com ST, cláusula 3ª):
 *   - 5401/5402/5403 · 6401/6402/6403 = quem RETÉM o ST (substituto) → CST 10/30/70 · CSOSN 201/202/203;
 *   - 5405 · 6404 = quem recebeu com ST já RETIDO (substituído) → CST 60 · CSOSN 500;
 *   - 5101/5102 · 6101/6102 = venda sem ST → CST sem ST · CSOSN 101/102/103/300/400;
 *   - Regime Normal (CRT 3) usa CST; Simples (CRT 1/2) usa CSOSN.
 * "Esta empresa é substituta" (industrializa ou envasa produto da lista do ST — caso do
 * envasador de temperos) é marcado na configuração padrão (`tributacao_default.substituto_tributario`):
 * com a marca, vender como substituído (5405 · CST 60 · CSOSN 500) também é avisado.
 */
final class CoerenciaFiscalDoItem
{
    public const CFOP_SUBSTITUTO = ['5401', '5402', '5403', '6401', '6402', '6403'];
    public const CFOP_SUBSTITUIDO = ['5405', '6404'];
    public const CFOP_SEM_ST = ['5101', '5102', '6101', '6102'];

    private const DESTACA_ST = ['10', '30', '70', '201', '202', '203'];
    private const ST_RETIDO = ['60', '500'];
    private const SEM_ST = ['00', '20', '40', '41', '50', '51', '101', '102', '103', '300', '400'];

    /**
     * @param  int|null    $crt   1/2 = Simples, 3 = Regime Normal, null = não sabido
     * @param  string|null $cest  CEST do produto; null = não verificar (ex.: ao salvar a regra)
     * @return list<string>
     */
    public static function avisos(?int $crt, ?string $cfop, ?string $cst, ?string $csosn, ?string $cest = null, bool $substituto = false): array
    {
        $cfop = self::digitos($cfop);
        $cst = self::cst($cst);
        $csosn = self::digitos($csosn);
        $avisos = [];

        if ($crt === 3 && $csosn !== null && $cst === null) {
            $avisos[] = "Regime Normal usa CST, mas a operação está com CSOSN {$csosn}.";
        } elseif (($crt === 1 || $crt === 2) && $cst !== null && $csosn === null) {
            $avisos[] = "Simples Nacional usa CSOSN, mas a operação está com CST {$cst}.";
        }

        $codigo = $crt === 3 ? ($cst ?? $csosn) : ($csosn ?? $cst);
        if ($codigo === null || $cfop === null) {
            return $avisos;
        }
        $rotulo = strlen($codigo) === 3 ? "CSOSN {$codigo}" : "CST {$codigo}";
        $esperadoSubstituto = $crt === 3 ? 'CST 10 ou 70' : ($crt === null ? 'CST 10/70 ou CSOSN 201' : 'CSOSN 201');
        $esperadoRetido = $crt === 3 ? 'CST 60' : ($crt === null ? 'CST 60 ou CSOSN 500' : 'CSOSN 500');

        if (in_array($cfop, self::CFOP_SUBSTITUTO, true) && ! in_array($codigo, self::DESTACA_ST, true)) {
            $avisos[] = "CFOP {$cfop} é de quem retém o ICMS-ST (substituto), mas o {$rotulo} não destaca o ST. Esperado: {$esperadoSubstituto}.";
        }
        if (in_array($cfop, self::CFOP_SUBSTITUIDO, true) && in_array($codigo, self::DESTACA_ST, true)) {
            $avisos[] = "CFOP {$cfop} é de quem recebeu com o ST já retido, mas o {$rotulo} destaca ST. Para reter o ST o CFOP é 5401/5403 (6401/6403 fora do estado).";
        }
        if (in_array($cfop, self::CFOP_SUBSTITUIDO, true) && in_array($codigo, self::SEM_ST, true)) {
            $avisos[] = "CFOP {$cfop} é de mercadoria com ST já retido. Esperado: {$esperadoRetido}.";
        }
        if (in_array($cfop, self::CFOP_SEM_ST, true) && in_array($codigo, self::DESTACA_ST, true)) {
            $avisos[] = "O {$rotulo} destaca ICMS-ST, mas o CFOP {$cfop} é de venda sem ST. Esperado: 5401/5403 (6401/6403 fora do estado).";
        }
        if (in_array($cfop, self::CFOP_SEM_ST, true) && in_array($codigo, self::ST_RETIDO, true)) {
            $avisos[] = "O {$rotulo} indica ST já retido, mas o CFOP {$cfop} é de venda sem ST. Esperado: 5405 (6404 fora do estado).";
        }

        $comSt = in_array($codigo, self::DESTACA_ST, true) || in_array($codigo, self::ST_RETIDO, true)
            || in_array($cfop, self::CFOP_SUBSTITUTO, true) || in_array($cfop, self::CFOP_SUBSTITUIDO, true);

        if ($substituto && (in_array($cfop, self::CFOP_SUBSTITUIDO, true) || in_array($codigo, self::ST_RETIDO, true))) {
            $avisos[] = "A empresa está marcada como substituta (industrializa ou envasa), mas a operação sai como se o ST já tivesse sido retido ({$rotulo}, CFOP {$cfop}). Quem fabrica retém: CFOP 5401 com {$esperadoSubstituto}.";
        }
        if ($cest !== null && $comSt && self::digitos($cest) === null) {
            $avisos[] = 'Operação com ICMS-ST sem CEST no cadastro do produto (Convênio ICMS 142/2018).';
        }

        return $avisos;
    }

    private static function digitos(?string $v): ?string
    {
        $v = preg_replace('/\D/', '', (string) $v);

        return $v === '' ? null : $v;
    }

    /** CST de ICMS com a origem na frente (ex. "060") vira "60". */
    private static function cst(?string $v): ?string
    {
        $v = self::digitos($v);
        if ($v === null) {
            return null;
        }

        return strlen($v) === 3 ? substr($v, 1) : $v;
    }
}
