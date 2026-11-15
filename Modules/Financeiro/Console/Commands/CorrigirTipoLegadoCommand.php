<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige títulos migrados do Delphi que entraram como "pagar" sendo recebimentos.
 *
 * O `scripts/legacy-migration/import-financeiro.py` só reconhecia `TIPO` com a palavra
 * RECEBER. O Delphi grava o título já recebido como `RECEBIDA`, então ele caía em `pagar`
 * (conserto do importador: PR #10868). Medido em produção em 2026-11-13: 2.099 títulos na
 * empresa 199 (1.959 quitados, 139 cancelados, 1 aberto), nenhum com cliente, plano de
 * contas, categoria, conta bancária ou movimento de caixa ligado — só as baixas, que não
 * carregam direção. Rodar o importador de novo não resolve: o UPSERT não reescreve `tipo`.
 *
 * Alvo, e nada além dele: `business_id` informado · `tipo = pagar` · `legacy_id` preenchido
 * · `metadata.delphi_tipo_raw = RECEBIDA` (o valor que o próprio importador gravou do Delphi).
 * Título digitado no oimpresso não tem `legacy_id` e nunca entra.
 *
 * SEGURO: só mostra o antes→depois por padrão. `--apply` grava, dentro de transação, e
 * marca cada título em `metadata.tipo_corrigido` (de, para, quando, por quê). Idempotente:
 * título corrigido vira `receber` e sai do alvo.
 *
 * Duas contas para conferir (regra de cálculo de valor): a soma do alvo antes, e o quanto a
 * soma a receber da empresa sobe e a soma a pagar desce depois. As três têm de bater.
 *
 * Uso:
 *   php artisan financeiro:corrigir-tipo-legado --business=199            # só mostra
 *   php artisan financeiro:corrigir-tipo-legado --business=199 --apply
 */
class CorrigirTipoLegadoCommand extends Command
{
    protected $signature = 'financeiro:corrigir-tipo-legado
        {--business= : business_id (obrigatório)}
        {--apply : Grava. Sem isso só mostra o antes→depois}';

    protected $description = 'Títulos RECEBIDA do Delphi gravados como pagar passam a receber (dry-run por padrão)';

    public function handle(): int
    {
        $businessId = (int) $this->option('business');
        if ($businessId <= 0) {
            $this->error('--business=N é obrigatório.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        // DB::table com business_id explícito: console não tem session para o BusinessScope.
        $alvo = fn () => DB::table('fin_titulos')
            ->where('business_id', $businessId)
            ->where('tipo', 'pagar')
            ->whereNotNull('legacy_id')
            ->whereRaw("UPPER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.delphi_tipo_raw')))) = 'RECEBIDA'");

        $porStatus = $alvo()
            ->selectRaw('status, COUNT(*) AS n, COALESCE(SUM(valor_total), 0) AS total, COALESCE(SUM(valor_aberto), 0) AS aberto')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $n = (int) $porStatus->sum('n');
        if ($n === 0) {
            $this->info("Nada a corrigir na empresa {$businessId}.");

            return self::SUCCESS;
        }

        $somaAlvo = round((float) $porStatus->sum('total'), 2);

        $this->info("Empresa {$businessId}: {$n} título(s) RECEBIDA gravados como pagar.");
        $this->table(
            ['status', 'títulos', 'valor total', 'valor aberto', 'antes', 'depois'],
            $porStatus->map(fn ($r) => [
                $r->status, $r->n,
                number_format((float) $r->total, 2, ',', '.'),
                number_format((float) $r->aberto, 2, ',', '.'),
                'pagar', 'receber',
            ])->all()
        );

        $somaPorTipo = fn () => DB::table('fin_titulos')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->selectRaw('tipo, COALESCE(SUM(valor_total), 0) AS total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        $antes = $somaPorTipo();

        if (! $apply) {
            $this->line('Dry-run: nada gravado. Rode com --apply para corrigir.');

            return self::SUCCESS;
        }

        $marca = json_encode([
            'de' => 'pagar',
            'para' => 'receber',
            'em' => now()->toIso8601String(),
            'por' => 'financeiro:corrigir-tipo-legado',
            'motivo' => 'Delphi TIPO=RECEBIDA importado como pagar (import-financeiro.py map_tipo)',
        ], JSON_UNESCAPED_UNICODE);

        $alterados = DB::transaction(fn () => $alvo()->update([
            'tipo' => 'receber',
            // JSON_EXTRACT(?, '$') e não CAST(? AS JSON): produção é MariaDB, que não tem CAST AS
            // JSON (medido 2026-11-13, MariaDB 11.8: erro 1064). As duas formas dão o mesmo
            // objeto no MySQL do CI; só esta roda nos dois.
            'metadata' => DB::raw('JSON_SET(COALESCE(metadata, '.DB::getPdo()->quote('{}').'), '.DB::getPdo()->quote('$.tipo_corrigido').', JSON_EXTRACT('.DB::getPdo()->quote($marca).', '.DB::getPdo()->quote('$').'))'),
            'updated_at' => now(),
        ]));

        $depois = $somaPorTipo();
        $subiuReceber = round((float) ($depois['receber'] ?? 0) - (float) ($antes['receber'] ?? 0), 2);
        $desceuPagar = round((float) ($antes['pagar'] ?? 0) - (float) ($depois['pagar'] ?? 0), 2);

        $this->info("Gravado: {$alterados} título(s).");
        $this->line('Conferência — soma do alvo: '.number_format($somaAlvo, 2, ',', '.')
            .' · receber subiu: '.number_format($subiuReceber, 2, ',', '.')
            .' · pagar desceu: '.number_format($desceuPagar, 2, ',', '.'));

        if ($alterados !== $n || abs($subiuReceber - $somaAlvo) > 0.005 || abs($desceuPagar - $somaAlvo) > 0.005) {
            $this->error('As contas não batem. Confira antes de seguir.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
