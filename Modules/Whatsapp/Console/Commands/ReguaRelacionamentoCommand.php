<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Services\Regua\ReguaDeRelacionamento;

/**
 * Régua de relacionamento por WhatsApp — roda uma vez por dia (US-WA-326).
 *
 * Percorre as empresas com régua ativa (`whatsapp_reguas_relacionamento.ativo`) e chama o
 * `ReguaDeRelacionamento` com o "hoje" do fuso da empresa. Só age com
 * `whatsapp.regua_relacionamento.enabled` ligado (padrão desligado — ligar é decisão [W]).
 *
 * Uso: `php artisan whatsapp:regua-relacionamento` · `--business=98` para uma empresa só.
 * Tier 0: cada empresa é processada com o próprio business_id; log só com ids e contagens.
 */
class ReguaRelacionamentoCommand extends Command
{
    protected $signature = 'whatsapp:regua-relacionamento {--business= : processa só esta empresa}';

    protected $description = 'Régua de relacionamento por WhatsApp (aniversário e recompra) — uma vez por dia';

    public function handle(ReguaDeRelacionamento $regua): int
    {
        if (! config('whatsapp.regua_relacionamento.enabled', false)) {
            $this->info('Régua de relacionamento desligada (WHATSAPP_REGUA_RELACIONAMENTO_ENABLED).');

            return self::SUCCESS;
        }

        $empresas = DB::table('whatsapp_reguas_relacionamento')
            ->where('ativo', true)
            ->when($this->option('business'), fn ($q, $b) => $q->where('business_id', (int) $b))
            ->distinct()->pluck('business_id');

        foreach ($empresas as $biz) {
            $tz = (string) (DB::table('business')->where('id', $biz)->value('time_zone') ?: config('app.timezone'));
            $r = $regua->executar((int) $biz, CarbonImmutable::now($tz));
            $this->line("empresa {$biz}: aniversário {$r['aniversario']} · recompra {$r['recompra']}");
            Log::info('[whatsapp.regua] executada', ['business_id' => (int) $biz] + $r);
        }

        return self::SUCCESS;
    }
}
