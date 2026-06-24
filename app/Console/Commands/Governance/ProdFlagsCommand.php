<?php

declare(strict_types=1);

namespace App\Console\Commands\Governance;

use Illuminate\Console\Command;

/**
 * governance:prod-flags — deriva governance/prod-flags.json do estado REAL dos flags
 * MWART_CLIENTE_* (config/env de prod). Replica ContactController::shouldRenderInertiaCliente
 * — a fonte-de-verdade do "que está live por tenant" (proposta SDD 2026-06-24, metade PRODUTOR).
 *
 * Consumido por scripts/governance/charter-live-signal.mjs: charter status:live cujo component
 * não esteja em prod-flags.json `live` (e sem `smoke:`) = live_sem_sinal.
 *
 * Roda no host com o .env de PROD (Hostinger). Config-puro: SEM DB, SEM rede.
 *   php artisan governance:prod-flags            # dry-run (imprime o JSON)
 *   php artisan governance:prod-flags --write     # grava governance/prod-flags.json
 *
 * Publish: rodar com --write no prod e commitar o arquivo (manual via SSH agora; agendar/
 * pós-deploy é wiring de infra). NÃO editar prod-flags.json à mão — re-rodar o comando.
 */
class ProdFlagsCommand extends Command
{
    protected $signature = 'governance:prod-flags {--write : grava governance/prod-flags.json (default: dry-run)}';

    protected $description = 'Deriva governance/prod-flags.json do estado real dos flags MWART_CLIENTE_* (replica shouldRenderInertiaCliente).';

    /** flag mwart → componente Inertia (resources/js/Pages/<X>.tsx, sem prefixo/sufixo). */
    public const FLAG_COMPONENT = [
        'cliente_index' => 'Cliente/Index',
        'cliente_create' => 'Cliente/Create',
        'cliente_edit' => 'Cliente/Edit',
        'cliente_show' => 'Cliente/Show',
        'cliente_import' => 'Cliente/Import',
        'cliente_ledger' => 'Cliente/Ledger',
        'cliente_map' => 'Cliente/Map',
    ];

    /**
     * Espelha ContactController::shouldRenderInertiaCliente por (flag, tenant):
     *  - !enabled               → não-live (fica fora do mapa)
     *  - enabled + allowlist []  → live pra TODOS os tenants → ['*']
     *  - enabled + allowlist     → live só pros business_ids listados
     *
     * @return array<string, list<string>> component → business_ids (ou ['*'])
     */
    public function buildLive(): array
    {
        $live = [];
        foreach (self::FLAG_COMPONENT as $flag => $component) {
            if (! config("mwart.{$flag}.enabled")) {
                continue;
            }
            $allowed = config("mwart.{$flag}.business_ids", []);
            $live[$component] = empty($allowed)
                ? ['*']
                : array_values(array_map(static fn ($id) => (string) $id, $allowed));
        }
        ksort($live);

        return $live;
    }

    public function handle(): int
    {
        $live = $this->buildLive();

        $payload = [
            '_meta' => [
                'schema' => 'prod-flags/v1',
                'purpose' => 'Estado de PRODUCAO das telas Inertia flag-gated, por tenant. DERIVADO do config/env real por `php artisan governance:prod-flags` (replica ContactController::shouldRenderInertiaCliente). Consumido por scripts/governance/charter-live-signal.mjs.',
                'contrato' => 'live[<component>] = lista de business_id (string) onde a tela roda em React em PRODUCAO; ["*"] = todos os tenants (flag enabled sem allowlist). <component> = path Inertia sem resources/js/Pages/ e sem .tsx.',
                'fonte' => 'gerado por `php artisan governance:prod-flags --write` no host com o .env de prod (Hostinger). NAO editar a mao — re-rodar o comando.',
                'nao_e' => 'NAO e a lista de telas que EXISTEM (isso e charter/anchor). E a lista das que estao LIVE pra tenant real. existir != estar live.',
            ],
            'live' => (object) $live,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

        if ($this->option('write')) {
            file_put_contents(base_path('governance/prod-flags.json'), $json);
            $this->info('governance/prod-flags.json escrito — '.count($live).' componente(s) live.');

            return self::SUCCESS;
        }

        $this->line($json);
        $this->comment('dry-run — use --write pra gravar. '.count($live).' componente(s) live.');

        return self::SUCCESS;
    }
}
