<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Cnab\Drivers;

use Eduardokum\LaravelBoleto\Boleto\Banco\Ailos as AilosBoleto;
use Modules\PaymentGateway\Services\Cnab\CnabBoletoAdapter;

/**
 * Driver CNAB Ailos (ex-Cecred) — cooperativa central de crédito urbano.
 *
 * Wagner refere como "Cecred" (nome anterior à fusão 2018). A lib
 * `eduardokum/laravel-boleto` usa o nome ATUAL: `Ailos` —
 * Cooperativa Central de Crédito Urbano (sistema AILOS, código FEBRABAN 085).
 *
 * Label UI sugerida: "Ailos (ex-Cecred)" — preserva reconhecimento legado.
 *
 * Particularidades do esquema cooperativa (diferente bancos comerciais):
 *   - `cooperativa` (código da unidade ~ "agência" pra propósito CNAB)
 *   - `posto` (posto de atendimento dentro da cooperativa — alguns convênios)
 *   - `agencia` + `agenciaDv` (a lib pede DV separado — incomum)
 *   - `conta` + `contaDv` (DV obrigatório também)
 *   - `carteira` fixa em '1' (única — lib hard-coded)
 *   - `convenio` (6 dígitos — código do cliente cedente)
 *
 * Layout: APENAS CNAB 240 (lib não tem Cnab400/Ailos). Confirmado em
 * `lib-custom/laravel-boleto/src/Cnab/Remessa/Cnab{240,400}/Banco/`.
 *
 * Carteira: lib hard-coded em '1' — driver concreto não precisa override,
 * mas exigimos no config_json pra forçar usuário a registrar consciente
 * (failsafe contra cooperativa que mude no futuro).
 *
 * Refs: ADR 0170-bancos-nativos-top5-drivers-separados — Onda 4f.cnab/Ailos
 *       (Wagner pediu "sicoob cecred?" v3 batch 11 — 2026-05-26)
 */
final class AilosCnabDriver extends CnabBoletoAdapter
{
    public function key(): string
    {
        return 'ailos_cnab';
    }

    protected function getBoletoClass(): string
    {
        return AilosBoleto::class;
    }

    protected function getLayoutVersion(): int
    {
        // Ailos só existe em Cnab240 na lib (Cooperativa SiCoob/Ailos modernizaram
        // pra 240 quando padronizaram pós-fusão). 400 não existe — verificado.
        return 240;
    }

    /**
     * Campos obrigatórios `config_json` pra Ailos.
     *
     * Cooperativa: esquema diferente de bancos comerciais — `agenciaDv` e
     * `contaDv` obrigatórios separados, `convenio` é o código cedente
     * (6 dígitos), `carteira` fixa em '1' mas exigimos pra forçar registro
     * consciente.
     *
     * `cooperativa` (código da unidade) é geralmente o mesmo valor de
     * `agencia` no contexto AILOS — não declaramos campo separado pra
     * não confundir; quem precisar, override `configToBoletoArgs`.
     *
     * @return array<int, string>
     */
    protected function camposObrigatoriosCnab(): array
    {
        return [
            'agencia',
            'agenciaDv',
            'conta',
            'contaDv',
            'carteira',
            'convenio',
            'cedente_nome',
            'cedente_documento',
        ];
    }
}
