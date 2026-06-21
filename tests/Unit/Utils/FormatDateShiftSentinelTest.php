<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\Util;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sentinela ADR 0066 (pré-condição #5) — `format_date()` PRESERVA o shift +3h legacy.
 *
 * ROTA LIVRE (biz=4, Larissa) operou meses com o shift +3h e DECOROU os horários
 * dos recibos. O fix matematicamente correto (Carbon::parse) foi aplicado e
 * REVERTIDO no mesmo dia (commits 10634ad2 → e5c8c90d) por quebrar a memória
 * visual dela. A ADR 0066 exige um teste que impeça a reaplicação inadvertida do
 * fix sem migration de dados históricos + aviso 7d ao cliente — mas a própria ADR
 * registrava este teste como "TODO" (linha 134). Este é esse sentinela.
 *
 * Estratégia ANTI-FLAKY (independente do timezone do CI): ancora `format_date()`
 * ao seu comportamento legacy EXATO (`createFromTimestamp(strtotime())`). Se alguém
 * trocar por `Carbon::parse()` (o "fix"), o resultado diverge e o teste falha —
 * sem depender de o ambiente exibir o shift ABSOLUTO (que muda entre CI UTC e app SP).
 *
 * @see memory/decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md
 * @see app/Utils/Util.php:353 — format_date (legacy +3h)
 * @see app/Utils/Util.php:369 — format_date_no_shift (correto, Carbon::parse)
 * @see app/Providers/AppServiceProvider.php:200 — Blade directive @format_date
 */
class FormatDateShiftSentinelTest extends TestCase
{
    private Util $util;

    private const INPUT = '2026-04-24 09:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->util = app(Util::class);
    }

    private function biz(): object
    {
        // business_details explícito → $format determinístico, sem depender de session.
        return (object) ['date_format' => 'd/m/Y', 'time_format' => 24];
    }

    #[Test]
    public function format_date_preserva_o_shift_legacy_plus_3h(): void
    {
        // format_date() DEVE continuar usando createFromTimestamp(strtotime()) — o +3h legacy.
        $this->assertSame(
            Carbon::createFromTimestamp(strtotime(self::INPUT))->format('d/m/Y H:i'),
            $this->util->format_date(self::INPUT, true, $this->biz()),
            'REGRESSÃO ADR 0066: format_date() deixou de preservar o shift +3h legacy '
            .'(trocaram createFromTimestamp por Carbon::parse?). Isso quebra a memória visual '
            .'da ROTA LIVRE — só reaplicar APÓS migration de dados + aviso 7d ao cliente.'
        );
    }

    #[Test]
    public function format_date_no_shift_e_o_helper_correto_sem_shift(): void
    {
        // Contraste: o helper "no_shift" usa Carbon::parse (sem +3h) — pra dados de DB.
        $this->assertSame(
            Carbon::parse(self::INPUT)->format('d/m/Y H:i'),
            $this->util->format_date_no_shift(self::INPUT, true, $this->biz()),
            'format_date_no_shift() deve usar Carbon::parse (sem shift). Ver ADR 0066.'
        );
    }

    /**
     * Onde o tz do ambiente EXIBE o shift (app em America/Sao_Paulo), os dois helpers
     * divergem — prova viva do quirk. Onde não exibe (CI UTC), pula sem falhar.
     */
    #[Test]
    public function quando_o_ambiente_exibe_o_shift_os_dois_helpers_divergem(): void
    {
        $legacy = $this->util->format_date(self::INPUT, true, $this->biz());
        $correto = $this->util->format_date_no_shift(self::INPUT, true, $this->biz());

        if ($legacy === $correto) {
            $this->markTestSkipped('Ambiente não exibe o shift +3h (provável CI em UTC) — quirk só aparece em tz America/Sao_Paulo.');
        }

        $this->assertNotSame(
            $correto,
            $legacy,
            'Com shift no ambiente, format_date() (legacy) NÃO pode bater com format_date_no_shift() (correto).'
        );
    }

    /**
     * Protege a OUTRA implementação do +3h: a Blade directive @format_date
     * (app/Providers/AppServiceProvider.php) também usa createFromTimestamp.
     */
    #[Test]
    public function blade_directive_format_date_ainda_usa_create_from_timestamp(): void
    {
        $src = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $pos = strpos($src, "Blade::directive('format_date'");

        $this->assertNotFalse(
            $pos,
            'Blade directive @format_date sumiu de AppServiceProvider — revisar ADR 0066 antes de remover.'
        );

        $bloco = substr($src, (int) $pos, 400);
        $this->assertStringContainsString(
            'createFromTimestamp',
            $bloco,
            'REGRESSÃO ADR 0066: a Blade directive @format_date deixou de usar createFromTimestamp (+3h legacy).'
        );
    }
}
