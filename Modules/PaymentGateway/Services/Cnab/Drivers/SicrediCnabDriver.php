<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Cnab\Drivers;

use Eduardokum\LaravelBoleto\Boleto\Banco\Sicredi as SicrediBoleto;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Services\Cnab\CnabBoletoAdapter;

/**
 * Driver CNAB Sicredi — herda fundação `CnabBoletoAdapter` (Onda 4f.0).
 *
 * Sicredi é o **segundo maior sistema cooperativista do Brasil** após Sicoob
 * (3º banco em volume PIX 2025, sistema com 130+ cooperativas singulares
 * organizadas em centrais regionais). Diferente dos bancos comerciais, a
 * conta Sicredi vive em uma cooperativa filiada — daí o conceito de
 * `posto` (PA — Posto de Atendimento) na hierarquia agência→posto→cliente.
 *
 * ## Campos específicos Sicredi
 *
 * Além dos comuns CNAB (agencia/conta/carteira/cedente_*), Sicredi exige:
 *
 *   - `byte` (1 dígito · default `2` na lib): identificador do gerador do
 *     nosso número. `1` = boleto gerado pela cooperativa; `2-9` = boleto
 *     gerado pelo beneficiário (cedente). Como nossa plataforma é o gerador,
 *     usamos sempre faixa 2-9 (lib levanta `ValidationException` se > 9).
 *   - `posto` (2 dígitos): Posto de Atendimento da cooperativa que mantém
 *     a conta. Compõe a string `agencia.posto.codigoCliente` exibida no
 *     "Agência/Beneficiário" do boleto e entra no cálculo do nosso número.
 *   - `codigoCliente` (5 dígitos): código do cedente no PA (geralmente o
 *     próprio número da conta sem DV, mas pode divergir em migração entre
 *     agências — manual Sicredi v2.3 §3.2).
 *   - `carteira` (1 dígito): no Boleto class aceita `1/2/3` (carteira
 *     simples com registro). Na camada Remessa CNAB240 vira `A`.
 *
 * ## Campos obrigatórios validados em `assertCredential()`
 *
 * - `agencia` · `conta` · `carteira` · `byte` · `posto` · `codigo_cliente`
 * - `cedente_nome` · `cedente_documento`
 *
 * O snake_case `codigo_cliente` é convertido pra camelCase `codigoCliente`
 * via override de `configToBoletoArgs()` (pois a lib usa Util::fillClass com
 * setters camelCase). `byte`/`posto` também são injetados ali.
 *
 * ## Multi-tenant Tier 0
 *
 * `business_id` honrado via `cred->business_id` (path remessa
 * `cnab-remessas/biz-{id}/cred-{id}/{idem}.rem` herdado da fundação).
 *
 * Refs:
 *   - ADR 0170 — bancos-nativos-top5-drivers-separados (Onda 4f.cnab/Sicredi)
 *   - lib: `Eduardokum\LaravelBoleto\Boleto\Banco\Sicredi` (carteiras 1/2/3)
 *   - Manual Sicredi CNAB 240 v2.3 (campos posto/byte/codigoCliente)
 */
class SicrediCnabDriver extends CnabBoletoAdapter
{
    public function key(): string
    {
        return 'sicredi_cnab';
    }

    protected function getBoletoClass(): string
    {
        return SicrediBoleto::class;
    }

    protected function getLayoutVersion(): int
    {
        // Sicredi opera CNAB 240 (FEBRABAN) — layout 400 está em deprecation
        // no manual 2.3 (set/2022) e cooperativas novas só aceitam 240.
        return 240;
    }

    /**
     * Campos OBRIGATÓRIOS do `config_json` pra emitir boleto Sicredi.
     *
     * Faltando qualquer um → `CredentialMisconfiguredException` (fundação).
     *
     * @return array<int, string>
     */
    protected function camposObrigatoriosCnab(): array
    {
        return [
            'agencia',
            'conta',
            'carteira',          // 1, 2 ou 3 (Boleto class) — vira 'A' na Remessa
            'byte',              // 2-9 (1 reservado à cooperativa)
            'posto',             // 2 dígitos PA
            'codigo_cliente',    // 5 dígitos — cedente no PA
            'cedente_nome',
            'cedente_documento',
        ];
    }

    /**
     * Override do mapeamento config → args do construtor da lib Boleto.
     *
     * Adiciona `byte` + `posto` + `codigoCliente` (snake_case do config_json
     * → camelCase esperado pelo `Util::fillClass`). Mantém os comuns do
     * `parent::configToBoletoArgs()` (agencia/conta/carteira/etc).
     *
     * @return array<string, mixed>
     */
    protected function configToBoletoArgs(array $config): array
    {
        $args = parent::configToBoletoArgs($config);

        // byte: aceita 2-9 (lib valida > 9; default lib = 2).
        if (isset($config['byte'])) {
            $args['byte'] = (int) $config['byte'];
        }

        // posto: 2 dígitos do Posto de Atendimento.
        if (isset($config['posto'])) {
            $args['posto'] = $config['posto'];
        }

        // codigoCliente: snake → camel. Cedente no PA (5 dígitos).
        if (isset($config['codigo_cliente']) && ! isset($args['codigoCliente'])) {
            $args['codigoCliente'] = $config['codigo_cliente'];
        }

        return $args;
    }
}
