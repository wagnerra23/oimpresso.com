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
 *
 * Não está no `Kernel` de propósito: quem dispara (agendado ou manual) segue pendência [W]
 * (ADR 0421 §Pendências). Default dos canais = desligados, então rodar sem ligar não envia nada.
 */
class AvisarTitularesCommand extends Command
{
    protected $signature = 'arquivos:avisar-titulares
        {business : business_id}
        {--canais : só mostra os canais configurados}
        {--email= : on|off — liga/desliga o canal e-mail}
        {--whatsapp= : on|off — liga/desliga o canal WhatsApp}
        {--dry-run : lista os elegíveis sem enviar}';

    protected $description = 'Aviso ao titular (LGPD Art. 18): configura canais e envia por e-mail/WhatsApp os arquivos na janela de 30 dias';

    public function handle(AvisoTitularService $avisos, AvisoTitularCanais $config): int
    {
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

        $elegiveis = $avisos->elegiveis($biz);
        $this->info("business {$biz}: {$elegiveis->count()} arquivo(s) na janela de aviso.");

        if ($this->option('dry-run')) {
            $this->table(['arquivo', 'vence em', 'dias'], $elegiveis->map(fn ($e) => [$e['id'], $e['vence_em'], $e['dias_restantes']])->all());

            return self::SUCCESS;
        }

        if (! in_array(true, $estado, true)) {
            $this->warn('Nenhum canal ligado neste negócio — nada enviado. Ligue com --email=on e/ou --whatsapp=on.');

            return self::SUCCESS;
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

        $this->info("Avisados: {$avisados} de {$elegiveis->count()}.");

        return self::SUCCESS;
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
