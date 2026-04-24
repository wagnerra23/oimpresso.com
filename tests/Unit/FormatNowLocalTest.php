<?php

namespace Tests\Unit;

use App\Utils\Util;
use Tests\TestCase;

/**
 * Garante que `Util::format_now_local()` retorna "agora" SEM o shift +3h
 * intencional do `format_date()`.
 *
 * Contexto: format_date() foi mantida com bug histórico (+3h em SP) pra
 * preservar consistência com vendas antigas — ver
 * `feedback_carbon_timezone_bug.md` na auto-memória. Mas pra pré-preencher
 * "agora" em formulários (ex: `/sells/create` campo `transaction_date`),
 * esse shift criava data 3h no futuro e travava o operador.
 *
 * Este teste impede que alguém substitua `format_now_local` por `format_date`
 * "padronizando" sem entender o contexto.
 */
class FormatNowLocalTest extends TestCase
{
    public function test_format_now_local_does_not_shift_timezone(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        date_default_timezone_set('America/Sao_Paulo');
        session()->put('business.date_format', 'd/m/Y');
        session()->put('business.time_format', 24);

        $util = new Util;
        $now = \Carbon::now()->format('d/m/Y H:i');
        $local = $util->format_now_local(true);

        // format_now_local deve bater (até o minuto) com Carbon::now()
        // formatado nas mesmas opções. format_date('now') NÃO bate (vai +3h).
        $this->assertSame($now, $local, 'format_now_local empurrou o tempo (regressao do fix 2026-04-24)');
    }

    public function test_format_now_local_respects_business_details_override(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        date_default_timezone_set('America/Sao_Paulo');

        $util = new Util;
        $business = (object) ['date_format' => 'Y-m-d', 'time_format' => 24];

        $now = \Carbon::now()->format('Y-m-d H:i');
        $this->assertSame($now, $util->format_now_local(true, $business));
    }

    public function test_format_date_still_has_intentional_shift_for_historical_data(): void
    {
        // Sentinela: garante que format_date NÃO foi "corrigido" silenciosamente.
        // Histórico do ROTA LIVRE depende desse shift +3h pra exibir os horários
        // que o cliente decorou. Não reaplicar Carbon::parse sem migration de dados.
        config(['app.timezone' => 'America/Sao_Paulo']);
        date_default_timezone_set('America/Sao_Paulo');
        session()->put('business.date_format', 'd/m/Y');
        session()->put('business.time_format', 24);

        $util = new Util;
        $formatted = $util->format_date('2026-04-24 09:00:00', true);

        $this->assertSame(
            '24/04/2026 12:00',
            $formatted,
            'format_date NAO deve ser "corrigido" sem migration. Ver feedback_carbon_timezone_bug.md.'
        );
    }
}
