<?php

namespace Modules\Jana\Console\Commands;

use App\Business;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Jana\Mail\WeeklyDigestMail;
use Modules\Jana\Services\MemoriaAutonoma\WeeklyDigestService;

/**
 * JanaWeeklyDigestCommand — Reflect-style weekly digest (AUDITORIA G8 P2).
 *
 * Gera digest semanal estruturado em 5 seções (Marco / Trabalho / Cycle progress
 * / Decisões / Próxima semana) consolidando commits + PRs mergeados + US closed
 * + ADRs + handoffs + cycle goals progress.
 *
 * Diferente de `copiloto:sintese-semanal` (narrativa LLM sex 18h):
 *  - SEMANA-YYYY-Www-resumo.md = síntese narrativa Wagner-style (Haiku)
 *  - WEEKLY-DIGEST-YYYY-Www.md = digest report Reflect-style (gpt-4o-mini)
 *
 * Schedule: segunda 09:00 BRT (Wagner abre semana e vê o que mudou).
 *
 * AUDITORIA-MEMORIA-2026-05-15 §D8 #6 — fecha gap "weekly digest populado":
 *  - Antes: arquivo + DB apenas, Wagner precisava abrir manual
 *  - Agora: envia email markdown pro owner do business toda segunda 09h BRT
 *
 * Uso:
 *   php artisan jana:weekly-digest                              # semana anterior + email auto
 *   php artisan jana:weekly-digest --week=2026-W19              # específica
 *   php artisan jana:weekly-digest --dry-run                    # contexto sem LLM
 *   php artisan jana:weekly-digest --force                      # sobrescreve existente
 *   php artisan jana:weekly-digest --no-email                   # pula envio
 *   php artisan jana:weekly-digest --email-to=foo@bar.com       # override destinatário
 *   php artisan jana:weekly-digest --business-id=1              # business alvo (default 1 superadmin)
 */
class JanaWeeklyDigestCommand extends Command
{
    protected $signature = 'jana:weekly-digest
                            {--week=          : Semana ISO YYYY-Www (default: anterior)}
                            {--dry-run        : Coleta contexto sem chamar LLM nem enviar email}
                            {--force          : Sobrescreve arquivo/row existente}
                            {--no-email       : Pula envio de email (útil em CI/local)}
                            {--email-to=      : Override destinatário (default: business.owner.email)}
                            {--business-id=1  : ID do business alvo (default 1 — superadmin)}';

    protected $description = 'Gera weekly digest Reflect-style (AUDITORIA G8 P2 + D8 #6) + envia email segunda 09h';

    public function handle(WeeklyDigestService $service): int
    {
        $semana = (string) ($this->option('week') ?: $this->semanaAnterior());
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');
        $noEmail = (bool) $this->option('no-email');
        $emailToOverride = $this->option('email-to');
        $businessId = (int) ($this->option('business-id') ?: 1);

        $this->info("Weekly digest: {$semana}" . ($dryRun ? ' (dry-run)' : ''));

        try {
            $resultado = $service->gerar($semana, $dryRun, $force);
        } catch (\Throwable $e) {
            $this->error("Falhou: {$e->getMessage()}");

            return self::FAILURE;
        }

        $metrics = $resultado['metrics'];
        $this->line('');
        $this->line('=== MÉTRICAS COLETADAS ===');
        $this->line("Commits     : {$metrics['commits']}");
        $this->line("PRs merged  : {$metrics['prs_merged']}");
        $this->line("US closed   : {$metrics['us_closed']}");
        $this->line("US created  : {$metrics['us_created']}");
        $this->line("ADRs new    : {$metrics['adrs_new']}");
        $this->line("Handoffs    : {$metrics['handoffs']}");
        $this->line("Cycle prog  : {$metrics['cycle_progress_pct']}%");

        if ($dryRun) {
            $this->line('');
            $this->line('=== CONTEXTO (truncado a 2000 chars) ===');
            $this->line(mb_substr($resultado['contexto'], 0, 2000));
            $custo = $resultado['custo_estimado'];
            $this->line('');
            $this->line('=== CUSTO ESTIMADO ===');
            $this->line("Input tokens : ~{$custo['input_tokens']}");
            $this->line("Output tokens: ~{$custo['output_tokens']}");
            $this->line("USD          : ~\${$custo['usd']}");
            $this->line("BRL          : ~R\${$custo['brl_aprox']}");

            return self::SUCCESS;
        }

        $this->info("✓ Digest gerado: {$resultado['path']}");
        $custo = $resultado['custo_estimado'];
        $this->line("  Custo: ~\${$custo['usd']} (~R\${$custo['brl_aprox']})");

        // AUDITORIA D8 #6 — envio email do digest pro destinatário do business
        if (! $noEmail) {
            $envio = $this->enviarEmail(
                businessId: $businessId,
                semana: $semana,
                digestMarkdown: (string) $resultado['digest'],
                metrics: $metrics,
                rangeInicio: $resultado['path'] ? $this->extrairRangeDoMd($resultado['path'])[0] : '',
                rangeFim: $resultado['path'] ? $this->extrairRangeDoMd($resultado['path'])[1] : '',
                emailToOverride: is_string($emailToOverride) && $emailToOverride !== '' ? $emailToOverride : null,
            );

            if ($envio['ok']) {
                $this->info("  ✉  Email enviado pra: {$envio['destinatario']}");
            } else {
                $this->warn("  ✉  Email NÃO enviado: {$envio['motivo']}");
            }
        } else {
            $this->line('  ✉  Email pulado (--no-email)');
        }

        return self::SUCCESS;
    }

    /**
     * Semana ANTERIOR à atual (ID ISO YYYY-Www).
     * Segunda 09h roda → semana fechada que terminou ontem.
     */
    protected function semanaAnterior(): string
    {
        $umaSemanaAtras = Carbon::now()->subWeek();
        $ano = (int) $umaSemanaAtras->isoWeekYear;
        $sem = (int) $umaSemanaAtras->isoWeek;

        return sprintf('%04d-W%02d', $ano, $sem);
    }

    /**
     * Resolve range YYYY-MM-DD do arquivo gerado (fallback se Service não retornar).
     *
     * @return array{0:string, 1:string}
     */
    protected function extrairRangeDoMd(string $path): array
    {
        $conteudo = @file_get_contents($path) ?: '';
        if (preg_match('/range:\s*(\d{4}-\d{2}-\d{2})\.\.(\d{4}-\d{2}-\d{2})/', $conteudo, $m)) {
            return [$m[1], $m[2]];
        }

        return ['', ''];
    }

    /**
     * Envia o digest por email ao destinatário do business.
     * Multi-tenant Tier 0 (ADR 0093): destinatário derivado de Business->owner->email.
     *
     * @param array<string, int|string> $metrics
     * @return array{ok:bool, destinatario:?string, motivo:?string}
     */
    protected function enviarEmail(
        int $businessId,
        string $semana,
        string $digestMarkdown,
        array $metrics,
        string $rangeInicio,
        string $rangeFim,
        ?string $emailToOverride,
    ): array {
        try {
            $business = Business::find($businessId);
            if (! $business) {
                return ['ok' => false, 'destinatario' => null, 'motivo' => "Business {$businessId} não encontrado"];
            }

            $destinatario = $emailToOverride
                ?: optional($business->owner)->email;

            if (! $destinatario) {
                return ['ok' => false, 'destinatario' => null, 'motivo' => 'Business sem owner.email — set --email-to='];
            }

            $businessName = (string) ($business->name ?: "business {$businessId}");

            Mail::to($destinatario)->send(new WeeklyDigestMail(
                semana: $semana,
                rangeInicio: $rangeInicio,
                rangeFim: $rangeFim,
                digestMarkdown: $digestMarkdown,
                metrics: $metrics,
                businessName: $businessName,
            ));

            Log::channel('copiloto-ai')->info('WeeklyDigest email enviado', [
                'semana' => $semana,
                'business_id' => $businessId,
                'destinatario' => $destinatario,
            ]);

            return ['ok' => true, 'destinatario' => $destinatario, 'motivo' => null];
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('WeeklyDigest email falhou', [
                'semana' => $semana,
                'business_id' => $businessId,
                'erro' => $e->getMessage(),
            ]);

            return ['ok' => false, 'destinatario' => null, 'motivo' => $e->getMessage()];
        }
    }
}
