<?php

declare(strict_types=1);

namespace Modules\Arquivos\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Arquivos\Jobs\AvisarTitularJob;
use Modules\Arquivos\Services\AvisoTitularCanais;
use Modules\Arquivos\Services\AvisoTitularService;

/**
 * arquivos:avisar-titulares — aviso ao titular (ADR 0421 + 0422), por negócio.
 *
 *   php artisan arquivos:avisar-titulares 98 --canais            mostra os canais do negócio
 *   php artisan arquivos:avisar-titulares 98 --email=on --whatsapp=off   liga/desliga
 *   php artisan arquivos:avisar-titulares 98 --dry-run           lista quem está na janela
 *   php artisan arquivos:avisar-titulares 98                     envia (só canais ligados)
 *   php artisan arquivos:avisar-titulares --todos                envia em todo negócio com canal ligado
 *
 * Agendado diário no `app/Console/Kernel.php` com `--todos` ([W] 2026-10-01). Só entra negócio
 * que ligou algum canal; default dos canais = desligados, então o agendamento sozinho não envia nada.
 */
class AvisarTitularesCommand extends Command
{
    protected $signature = 'arquivos:avisar-titulares
        {business? : business_id (omitir só com --todos)}
        {--todos : envia em todos os negócios com algum canal ligado (uso do agendador)}
        {--canais : só mostra os canais configurados}
        {--email= : on|off — liga/desliga o canal e-mail}
        {--whatsapp= : on|off — liga/desliga o canal WhatsApp}
        {--dry-run : lista os elegíveis sem enviar}';

    protected $description = 'Aviso ao titular (LGPD Art. 18): configura canais e envia por e-mail/WhatsApp os arquivos na janela de 30 dias';

    public function handle(AvisoTitularService $avisos, AvisoTitularCanais $config): int
    {
        if ($this->option('todos')) {
            if ($this->argument('business') !== null) {
                $this->error('Use --todos OU um business_id, não os dois.');

                return self::FAILURE;
            }

            // Pré-filtro barato pela chave no JSON; a decisão é do leitor canônico (algumAtivo).
            $negocios = DB::table('business')
                ->where('common_settings', 'like', '%' . AvisoTitularCanais::CHAVE . '%')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $config->algumAtivo($id))
                ->values();

            $this->info("{$negocios->count()} negócio(s) com canal de aviso ligado.");
            foreach ($negocios as $id) {
                $this->enviar($id, $avisos, (bool) $this->option('dry-run'));
            }

            return self::SUCCESS;
        }

        $biz = (int) $this->argument('business');
        if ($biz <= 0) {
            $this->error('business_id inválido.');

            return self::FAILURE;
        }

        $email = $this->flag('email');
        $whatsapp = $this->flag('whatsapp');
        if ($email === 'invalido' || $whatsapp === 'invalido') {
            $this->error('Use on|off em --email/--whatsapp.');

            return self::FAILURE;
        }

        if ($email !== null || $whatsapp !== null) {
            $estado = $config->definir($biz, $email, $whatsapp);
            $this->mostrar($biz, $estado);

            return self::SUCCESS;
        }

        $estado = $config->ativos($biz);
        if ($this->option('canais')) {
            $this->mostrar($biz, $estado);

            return self::SUCCESS;
        }

        if (! $this->option('dry-run') && ! in_array(true, $estado, true)) {
            $this->warn('Nenhum canal ligado neste negócio — nada enviado. Ligue com --email=on e/ou --whatsapp=on.');

            return self::SUCCESS;
        }

        $this->enviar($biz, $avisos, (bool) $this->option('dry-run'));

        return self::SUCCESS;
    }

    private function enviar(int $biz, AvisoTitularService $avisos, bool $dryRun): void
    {
        $elegiveis = $avisos->elegiveis($biz);
        $this->info("business {$biz}: {$elegiveis->count()} arquivo(s) na janela de aviso.");

        if ($dryRun) {
            $this->table(['arquivo', 'vence em', 'dias'], $elegiveis->map(fn ($e) => [$e['id'], $e['vence_em'], $e['dias_restantes']])->all());

            return;
        }

        $ids = $elegiveis->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($ids as $id) {
            AvisarTitularJob::dispatchSync($biz, $id);
        }

        // dispatchSync não devolve o retorno do handle: a prova é a coluna gravada.
        $avisados = DB::table('arquivos')
            ->where('business_id', $biz)
            ->whereIn('id', $ids)
            ->whereNotNull('titular_avisado_at')
            ->count();

        $this->info("business {$biz}: avisados {$avisados} de {$elegiveis->count()}.");
    }

    /** @return bool|string|null null = opção ausente; 'invalido' = valor fora de on|off */
    private function flag(string $nome): bool|string|null
    {
        $v = $this->option($nome);
        if ($v === null) {
            return null;
        }

        return match (strtolower((string) $v)) {
            'on', '1', 'true', 'sim' => true,
            'off', '0', 'false', 'nao', 'não' => false,
            default => 'invalido',
        };
    }

    /** @param array{email: bool, whatsapp: bool} $estado */
    private function mostrar(int $biz, array $estado): void
    {
        $this->info(sprintf(
            'business %d — e-mail: %s · WhatsApp: %s',
            $biz,
            $estado['email'] ? 'ligado' : 'desligado',
            $estado['whatsapp'] ? 'ligado' : 'desligado',
        ));
    }
}
