<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Cnab\Drivers;

use Eduardokum\LaravelBoleto\Boleto\Banco\Bancoob as BancoobBoleto;
use Modules\PaymentGateway\Services\Cnab\CnabBoletoAdapter;

/**
 * Driver CNAB Sicoob — Sistema de Cooperativas de Crédito do Brasil.
 *
 * IMPORTANTE: Sicoob é o nome ATUAL do sistema cooperativista (código
 * FEBRABAN 756). A lib `eduardokum/laravel-boleto` usa o nome HISTÓRICO
 * `Bancoob` (razão social anterior "Banco Cooperativo do Brasil") —
 * MESMA INSTITUIÇÃO. Mantemos `bancoob` apenas como FQCN da lib; tudo
 * que é nosso (key, label, paths) usa `sicoob`.
 *
 * Label UI sugerida: "Sicoob (CNAB)" — nome reconhecido pelo cliente.
 *
 * Particularidades Sicoob (cooperativa de crédito):
 *   - `agencia` (4 dígitos — código PA / Posto de Atendimento)
 *   - `conta` (corrente cooperativada)
 *   - `convenio` (4, 6 ou 7 caracteres — código cedente Sicoob)
 *     Validado pela lib via `addCampoObrigatorio('convenio')` no construtor.
 *   - `carteira` aceita pela lib: ['1', '3'] (modalidade Simples / Caucionada)
 *   - `modalidade` é trafegada como sufixo de carteira em alguns layouts —
 *     declaramos como obrigatória pro registro consciente, mesmo que a lib
 *     não force (failsafe + telemetria pro suporte ao cliente cooperativado).
 *
 * Layout: CNAB 240 (canon Sicoob 2026) — a lib também tem Cnab400 mas
 * o Sicoob recomenda 240 desde 2018. Confirmado em
 * `lib-custom/laravel-boleto/src/Cnab/Remessa/Cnab240/Banco/Bancoob.php`.
 *
 * Refs: ADR 0170-bancos-nativos-top5-drivers-separados — Onda 4f.cnab/Sicoob
 *       (Wagner pediu "sicoob cecred?" v3 batch 11 — 2026-05-26)
 */
final class SicoobCnabDriver extends CnabBoletoAdapter
{
    public function key(): string
    {
        return 'sicoob_cnab';
    }

    protected function getBoletoClass(): string
    {
        // Atenção: lib usa nome LEGACY `Bancoob` — mesma instituição Sicoob.
        return BancoobBoleto::class;
    }

    protected function getLayoutVersion(): int
    {
        // Sicoob padronizou em CNAB 240 (canon cooperativa 2026).
        return 240;
    }

    /**
     * Campos obrigatórios `config_json` pra Sicoob.
     *
     * `convenio` é forçado pelo construtor de `Bancoob` (addCampoObrigatorio).
     * `carteira` aceita ['1', '3'] (Simples / Caucionada) — exigimos pra
     * registro consciente. `modalidade` declarada pra failsafe (alguns
     * Postos de Atendimento Sicoob diferenciam por modalidade no layout).
     *
     * @return array<int, string>
     */
    protected function camposObrigatoriosCnab(): array
    {
        return [
            'agencia',
            'conta',
            'carteira',
            'convenio',
            'modalidade',
            'cedente_nome',
            'cedente_documento',
        ];
    }
}
