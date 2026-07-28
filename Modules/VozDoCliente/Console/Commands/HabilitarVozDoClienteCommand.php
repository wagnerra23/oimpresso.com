<?php

namespace Modules\VozDoCliente\Console\Commands;

use App\System;
use Illuminate\Console\Command;
use Modules\Superadmin\Entities\Subscription;

/**
 * Habilita o módulo Voz do Cliente para UM business, pelos dois portões que a UI
 * usa: a propriedade de versão em `system` (o que o botão Install grava) e a chave
 * no `package_details` da assinatura ativa (o que a tela de pacotes do superadmin
 * grava, com "Atualizar inscrições existentes" marcado).
 *
 * ── POR QUE ESTE COMANDO EXISTE, E POR QUE NÃO É O HARDCODE PROIBIDO ──────────
 * A proibição Tier 0 de 2026-05-18 bane `if ($business_id === N)` na LÓGICA para
 * esconder/liberar módulo — código que decide por business e exige deploy para
 * mudar. Aqui NADA decide: o business vem por ARGUMENTO, o comando só grava o
 * MESMO dado que a UI gravaria, e a UI continua sendo a fonte depois (o admin
 * pode desmarcar a qualquer momento e este comando não volta atrás sozinho).
 *
 * O caminho é o que a própria REGRA PRIMÁRIA ("mexeu, registra") prescreve para
 * escrita em banco: *"INSERT/UPDATE direto no DB → Seeder OU comando artisan
 * idempotente OU backfill job + commit"*. Tinker e SQL na mão é que são proibidos.
 *
 * Idempotente: rodar N vezes = rodar 1. Reversível: `--desabilitar`.
 *
 * NÃO substitui a UI — é o caminho para quem não tem a tela à mão (sessão de
 * agente sem acesso HTTP ao domínio, por exemplo). Quando a tela estiver
 * disponível, ela é o caminho preferido.
 *
 * @see app/Http/Controllers/BaseModuleInstallController.php (o que o Install faz)
 * @see app/Utils/ModuleUtil.php::hasThePermissionInSubscription (quem lê a chave)
 * @see memory/proibicoes.md §"NUNCA hardcode business_id" + §"REGRA PRIMÁRIA"
 */
class HabilitarVozDoClienteCommand extends Command
{
    protected $signature = 'vozdocliente:habilitar
                            {business : ID do business (ex: 1)}
                            {--desabilitar : Remove a chave do pacote em vez de adicionar}
                            {--dry-run : Mostra o antes→depois e NÃO grava}';

    protected $description = 'Habilita (ou desabilita) o módulo Voz do Cliente para um business — mesmo efeito da UI, idempotente.';

    private const CHAVE_PACOTE = 'vozdocliente_module';

    private const CHAVE_VERSAO = 'vozdocliente_version';

    private const VERSAO = '0.1.0';

    public function handle(): int
    {
        $businessId = (int) $this->argument('business');
        $desabilitar = (bool) $this->option('desabilitar');
        $dryRun = (bool) $this->option('dry-run');

        if ($businessId <= 0) {
            $this->error('business inválido — informe um ID numérico positivo.');

            return self::FAILURE;
        }

        $assinatura = Subscription::active_subscription($businessId);

        if (empty($assinatura)) {
            $this->error("Business {$businessId} não tem assinatura ATIVA (aprovada e dentro da vigência).");
            $this->line('Sem assinatura ativa o gate hasThePermissionInSubscription() retorna false para qualquer chave —');
            $this->line('não adianta gravar a chave. Crie/renove a assinatura primeiro, na tela de superadmin.');

            return self::FAILURE;
        }

        $detalhes = $assinatura->package_details;
        if (is_string($detalhes)) {
            $detalhes = json_decode($detalhes, true) ?: [];
        }
        $detalhes = is_array($detalhes) ? $detalhes : [];

        $tinhaAntes = ! empty($detalhes[self::CHAVE_PACOTE]);
        $querDepois = ! $desabilitar;

        $this->line("Business:   {$businessId}");
        $this->line('Assinatura: #' . $assinatura->id . ' (' . $assinatura->start_date . ' → ' . $assinatura->end_date . ')');
        $this->line('Pacote:     ' . (($detalhes['name'] ?? '—')));
        $this->line('');
        $this->line(self::CHAVE_PACOTE . ':  ' . ($tinhaAntes ? 'SIM' : 'não') . '  →  ' . ($querDepois ? 'SIM' : 'não'));

        if ($tinhaAntes === $querDepois) {
            $this->info('Nada a fazer — já está no estado pedido (idempotente).');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('--dry-run: NADA foi gravado.');

            return self::SUCCESS;
        }

        if ($querDepois) {
            $detalhes[self::CHAVE_PACOTE] = 1;
        } else {
            unset($detalhes[self::CHAVE_PACOTE]);
        }

        $assinatura->package_details = $detalhes;
        $assinatura->save();

        $this->info('✓ package_details atualizado na assinatura ativa.');

        // A propriedade de versão é GLOBAL (tabela `system`), não por business — é o
        // que o botão Install grava e o que `isModuleInstalled()` lê para o superadmin.
        // Só ADICIONA; desabilitar um business não desinstala o módulo do sistema.
        if ($querDepois) {
            $versaoAtual = System::getProperty(self::CHAVE_VERSAO);
            if (empty($versaoAtual)) {
                System::addProperty(self::CHAVE_VERSAO, self::VERSAO);
                $this->info('✓ ' . self::CHAVE_VERSAO . ' = ' . self::VERSAO . ' (equivale ao botão Install).');
            } else {
                $this->line('· ' . self::CHAVE_VERSAO . ' já existia (' . $versaoAtual . ') — mantido.');
            }
        }

        $this->line('');
        $this->line('Falta ainda a 3ª camada (permissões do papel), que é por USUÁRIO e só existe na UI:');
        $this->line('  /roles/{id}/edit → marcar `vozdocliente.reportar` e/ou `vozdocliente.triar`');

        return self::SUCCESS;
    }
}
