<?php

declare(strict_types=1);

namespace Modules\Auditoria\Console\Commands;

use App\Business;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * auditoria:backfill-business-id — preenche activity_log.business_id NULL a partir do
 * REGISTRO auditado (subject), nunca da sessão (Tier 0, ADR 0093).
 *
 * Origem: até o PR #8384 o trait LogsActivity não gravava business_id. Medido em prod
 * 2026-10-01 (leitura): 106.813 de 166.455 linhas NULL; essas linhas não aparecem na
 * tela /auditoria (filtra por business_id) e o RevertService não as reverte.
 *
 * Caminhos, por subject_type (o primeiro que a tabela do model suportar):
 *   1. o próprio Business            → business_id = subject_id
 *   2. tabela com coluna business_id → o business_id do registro
 *   3. tabela com transaction_id     → transactions.business_id (SellLine, PurchaseLine)
 *   4. tabela com product_id         → products.business_id (Variation, VLD)
 *   Sem caminho (classe inexistente, tabela sem tenant) → reportado e PULADO.
 *   Registro apagado ou com tenant NULL → a linha fica NULL (não inventa).
 *
 * Log da PLATAFORMA (licenças do Officeimpresso — lista no ActivityCauserKindObserver,
 * [W] 2026-10-01) NUNCA recebe tenant: o backfill pula esses tipos e o
 * --apply LIMPA (volta a NULL) as linhas deles que já saíram com business_id de cliente.
 *
 * Seguro por construção: só toca linhas com business_id IS NULL (idempotente; nunca
 * sobrescreve valor existente) e escreve em fatias por faixa de id.
 *
 * Dry-run é o PADRÃO — mostra o antes→depois por subject_type sem escrever.
 * Escreve só com --apply.
 */
class BackfillActivityBusinessIdCommand extends Command
{
    protected $signature = 'auditoria:backfill-business-id
        {--apply : Escreve (sem isto é dry-run)}
        {--type=* : Restringe a estes subject_type (FQCN)}
        {--chunk=5000 : Tamanho da faixa de id por UPDATE}';

    protected $description = 'Backfill de activity_log.business_id NULL pelo tenant do registro auditado (dry-run por padrão).';

    public function handle(): int
    {
        if (! Schema::hasTable('activity_log') || ! Schema::hasColumn('activity_log', 'business_id')) {
            $this->error('activity_log.business_id não existe neste schema.');

            return self::FAILURE;
        }

        $aplicar = (bool) $this->option('apply');
        $chunk = max(1, (int) $this->option('chunk'));
        $filtro = array_filter((array) $this->option('type'));

        $tipos = DB::table('activity_log')
            ->whereNull('business_id')
            ->whereNotNull('subject_type')
            ->when($filtro, fn ($q) => $q->whereIn('subject_type', $filtro))
            ->groupBy('subject_type')
            ->selectRaw('subject_type, count(*) as n')
            ->orderByDesc('n')
            ->get()
            ->pluck('n', 'subject_type');

        $linhas = [];
        $totalNull = 0;
        $totalResolvivel = 0;
        $totalEscrito = 0;

        foreach ($tipos as $tipo => $nNull) {
            $totalNull += (int) $nNull;
            if (self::ehDaPlataforma((string) $tipo)) {
                $linhas[] = [$tipo, $nNull, 0, '— log da plataforma (fica NULL)', 0];

                continue;
            }
            $caminho = $this->caminho((string) $tipo);

            if ($caminho === null) {
                $linhas[] = [$tipo, $nNull, 0, '— sem caminho (pulado)', 0];

                continue;
            }

            [$descricao, $expr] = $caminho;
            $resolvivel = (int) DB::table('activity_log')
                ->where('subject_type', $tipo)
                ->whereNull('business_id')
                ->whereRaw("($expr) IS NOT NULL")
                ->count();
            $totalResolvivel += $resolvivel;

            $escrito = 0;
            if ($aplicar && $resolvivel > 0) {
                $escrito = $this->aplicar((string) $tipo, $expr, $chunk);
                $totalEscrito += $escrito;
            }

            $linhas[] = [$tipo, $nNull, $resolvivel, $descricao, $escrito];
        }

        $this->table(['subject_type', 'NULL hoje', 'resolvível', 'caminho', 'escrito'], $linhas);

        if (! $filtro) {
            $this->semSubject($aplicar);
        }

        // Limpeza: logs da plataforma que já saíram COM tenant (cliente ou outro).
        $vazados = DB::table('activity_log')
            ->whereNotNull('business_id')
            ->whereNotNull('subject_type')
            ->when($filtro, fn ($q) => $q->whereIn('subject_type', $filtro))
            ->groupBy('subject_type')
            ->selectRaw('subject_type, count(*) as n')
            ->get()
            ->filter(fn ($r) => self::ehDaPlataforma((string) $r->subject_type));
        foreach ($vazados as $r) {
            $limpo = $aplicar
                ? DB::table('activity_log')->where('subject_type', $r->subject_type)->whereNotNull('business_id')->update(['business_id' => null])
                : 0;
            $this->line(sprintf('plataforma %s: %d linha(s) com tenant → %s', $r->subject_type, $r->n, $aplicar ? "limpas: $limpo" : 'seriam limpas (NULL)'));
        }
        $this->line(sprintf(
            '%s · NULL: %d · resolvível: %d · escrito: %d · continua NULL: %d',
            $aplicar ? 'APLICADO' : 'DRY-RUN (nada escrito; use --apply)',
            $totalNull,
            $totalResolvivel,
            $totalEscrito,
            $totalNull - ($aplicar ? $totalEscrito : $totalResolvivel)
        ));

        return self::SUCCESS;
    }

    /**
     * Fonte única: ActivityCauserKindObserver::ehLogDaPlataforma (lista das licenças do
     * Officeimpresso, PR #8410). Até ele estar no main, cai no marcador de constante.
     */
    private static function ehDaPlataforma(string $tipo): bool
    {
        $observer = \App\Observers\ActivityCauserKindObserver::class;
        if (method_exists($observer, 'ehLogDaPlataforma')) {
            return $observer::ehLogDaPlataforma($tipo);
        }

        return class_exists($tipo) && defined($tipo.'::AUDITORIA_LOG_DA_PLATAFORMA')
            && constant($tipo.'::AUDITORIA_LOG_DA_PLATAFORMA') === true;
    }

    /**
     * Subquery escalar (alias externo `activity_log`) que devolve o tenant do subject,
     * ou null quando o subject_type não tem caminho para um tenant.
     *
     * @return array{0: string, 1: string}|null
     */
    private function caminho(string $tipo): ?array
    {
        if (! class_exists($tipo) || ! is_subclass_of($tipo, Model::class)) {
            return null;
        }

        if ($tipo === Business::class || is_subclass_of($tipo, Business::class)) {
            return ['próprio Business', 'select b.id from business b where b.id = activity_log.subject_id'];
        }

        /** @var Model $modelo */
        $modelo = new $tipo();
        $tabela = $modelo->getTable();
        $chave = $modelo->getKeyName();

        if (! Schema::hasTable($tabela)) {
            return null;
        }

        $s = fn (string $sql) => sprintf($sql, $tabela, $chave);

        if (Schema::hasColumn($tabela, 'business_id')) {
            return ["$tabela.business_id", $s('select s.business_id from %1$s s where s.%2$s = activity_log.subject_id')];
        }

        if (Schema::hasColumn($tabela, 'transaction_id')) {
            return ["$tabela → transactions", $s('select t.business_id from %1$s s join transactions t on t.id = s.transaction_id where s.%2$s = activity_log.subject_id')];
        }

        if (Schema::hasColumn($tabela, 'product_id')) {
            return ["$tabela → products", $s('select p.business_id from %1$s s join products p on p.id = s.product_id where s.%2$s = activity_log.subject_id')];
        }

        return null;
    }

    /**
     * Logs SEM subject (activity()->withProperties([...])->log(), sem performedOn): não há
     * registro auditado. O tenant que o chamador declarou em properties.business_id só é
     * gravado quando CONFERE com o business_id do usuário causador — duas fontes
     * independentes concordando. Divergiu, sem causador ou sem declaração → fica NULL.
     * [W] 2026-10-01 aprovou este caminho para os logs `nfe.certificado` (16 linhas, 16/16
     * conferindo). Só roda sem --type (não tem subject_type para filtrar).
     */
    private function semSubject(bool $aplicar): void
    {
        $confere = 0;
        $diverge = 0;
        $semFonte = 0;
        $escrito = 0;

        DB::table('activity_log as a')
            ->leftJoin('users as u', function ($j) {
                $j->on('u.id', '=', 'a.causer_id')->where('a.causer_type', '=', \App\User::class);
            })
            ->whereNull('a.subject_type')
            ->whereNull('a.business_id')
            ->select('a.id', 'a.properties', 'u.business_id as causer_biz')
            ->orderBy('a.id')
            ->chunk(1000, function ($rows) use ($aplicar, &$confere, &$diverge, &$semFonte, &$escrito) {
                foreach ($rows as $r) {
                    $declarado = \App\Observers\ActivityCauserKindObserver::businessIdDeclarado($r->properties);
                    if ($declarado === null || $r->causer_biz === null) {
                        $semFonte++;

                        continue;
                    }
                    if ($declarado !== (int) $r->causer_biz) {
                        $diverge++;

                        continue;
                    }
                    $confere++;
                    if ($aplicar) {
                        $escrito += DB::table('activity_log')->where('id', $r->id)->whereNull('business_id')
                            ->update(['business_id' => $declarado]);
                    }
                }
            });

        $this->line(sprintf(
            'sem subject: confere %d · diverge %d (fica NULL) · sem fonte %d (fica NULL) · %s',
            $confere, $diverge, $semFonte, $aplicar ? "escrito: $escrito" : 'nada escrito (dry-run)'
        ));
    }

    private function aplicar(string $tipo, string $expr, int $chunk): int
    {
        $limites = DB::table('activity_log')
            ->where('subject_type', $tipo)
            ->whereNull('business_id')
            ->selectRaw('min(id) as lo, max(id) as hi')
            ->first();

        $escrito = 0;
        for ($de = (int) $limites->lo; $de <= (int) $limites->hi; $de += $chunk) {
            $ate = $de + $chunk - 1;
            // MySQL não deixa a subquery ler a tabela que está sendo atualizada
            // (erro 1093) — o tenant vem só de OUTRAS tabelas, então não há leitura de
            // activity_log dentro de $expr.
            $escrito += DB::update(
                "update activity_log set business_id = ($expr)
                  where subject_type = ? and business_id is null and id between ? and ?
                    and ($expr) is not null",
                [$tipo, $de, $ate]
            );
        }

        return $escrito;
    }
}
