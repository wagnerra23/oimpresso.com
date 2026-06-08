<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Cnab\Drivers;

use Eduardokum\LaravelBoleto\Boleto\Banco\Bb as BbBoleto;
use Modules\PaymentGateway\Services\Cnab\CnabBoletoAdapter;

/**
 * Driver fino CNAB — Banco do Brasil (BB · cód. 001).
 *
 * Onda 4f.cnab/BB · ADR 0170-bancos-nativos-top5-drivers-separados (v3).
 *
 * Estende `CnabBoletoAdapter` (Onda 4f.0 — fundação compartilhada) e expõe
 * APENAS os 4 hooks abstratos. Toda a lógica (emitirBoleto, healthCheck,
 * gravarRemessa, multi-tenant scope, exceptions PIX/cartão/refund) vive
 * na fundação — driver concreto não duplica.
 *
 * Campos obrigatórios em `config_json` (validados via `assertCredential`):
 *
 *   - `agencia` (4 dígitos, sem DV — DV opcional via `agenciaDv`)
 *   - `conta` (até 8 dígitos, sem DV — DV opcional via `contaDv`)
 *   - `convenio` (4, 6 ou 7 dígitos — exigência forte BB; lib lança
 *     ValidationException pra outros tamanhos)
 *   - `carteira` (uma de: '11','12','15','17','18','31','51' — array
 *     `$carteiras` da lib `Bb::class`)
 *   - `cedente_nome` + `cedente_documento` (PJ titular da conta)
 *
 * Opcionais comuns:
 *
 *   - `variacao_carteira` (3 dígitos — só obrigatório se `carteira` ∈ {16,18}
 *     com convênio 6 dígitos, conforme `gerarNossoNumero` da lib)
 *   - `cedente_endereco` / `cedente_cep` / `cedente_uf` / `cedente_cidade`
 *
 * Gotcha CIP (Cadastro de Inadimplentes Protestáveis):
 *   - BB convênios novos (pós-2018) exigem convenio com 7 dígitos pra emitir
 *     boletos registrados via CIP/Nova Plataforma. Convenios legados (4 ou 6
 *     dígitos) ainda funcionam pra carteiras `11`/`17`, mas BB recomenda
 *     migração — cliente deve confirmar com gerente PJ antes de cadastrar.
 *   - Variação `017` (carteira 16/18 + convenio 6 dig) gera nosso número de
 *     17 posições — caso especial usado por convênios antigos pra "boleto
 *     livre" (numeração própria do cedente). Lib detecta e ajusta `campoLivre`.
 *
 * Layout CNAB: 240 (FEBRABAN — padrão BB pós-2010; layout 400 ainda existe
 * em convênios legados mas Onda 4f.0 fundação grava só txt-debug, ficando
 * pra Onda futura de remessa binária real via SFTP/CIP). Wagner aprovou
 * default 240 conforme ADR 0170 §3.
 *
 * Multi-tenant Tier 0 honrado integralmente pela fundação (path Storage
 * `cnab-remessas/biz-{id}/cred-{id}/...`).
 *
 * @see CnabBoletoAdapter Fundação (Onda 4f.0)
 * @see \Eduardokum\LaravelBoleto\Boleto\Banco\Bb Lib boleto BB
 * @see lib-custom/laravel-boleto/src/Boleto/Banco/Bb.php Customizações oimpresso
 */
final class BBCnabDriver extends CnabBoletoAdapter
{
    public function key(): string
    {
        return 'bb_cnab';
    }

    protected function getBoletoClass(): string
    {
        return BbBoleto::class;
    }

    protected function getLayoutVersion(): int
    {
        return 240;
    }

    /**
     * Campos obrigatórios em `config_json` pra BB.
     *
     * Lib `Bb::__construct` força `numero, convenio, carteira` —
     * `numero` vem do `buildBoletoData` da fundação (derivado de
     * idempotencyKey), então no config_json precisamos garantir
     * `convenio` + `carteira` + dados básicos do cedente + conta.
     *
     * @return array<int, string>
     */
    protected function camposObrigatoriosCnab(): array
    {
        return [
            'agencia',
            'conta',
            'convenio',
            'carteira',
            'cedente_nome',
            'cedente_documento',
        ];
    }
}
