<?php

namespace Modules\Copiloto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MEM-MULTI-1 — Seeda ADRs do MCP → copiloto_memoria_facts.
 *
 * Modelo: cada empresa tem seus próprios ADRs em mcp_memory_documents
 * (filtrado por business_id). Este comando transforma ADRs em fatos RAG
 * pesquisáveis no copiloto.
 *
 * Evolução temporal:
 *   - ADR status=Superseded → valid_until preenchido (fact desativado pra recall)
 *   - ADR status=Accepted|Proposed → valid_until=NULL (fact ativo)
 *
 * Idempotente: upsert por (business_id, user_id, source_slug) em metadata.
 * Rodar N vezes tem mesmo efeito que rodar 1× — safe pra cron diário.
 *
 * Uso:
 *   php artisan copiloto:seed-adrs --business=1 --user=1
 *   php artisan copiloto:seed-adrs --business=1 --user=1 --dry-run
 *   php artisan copiloto:seed-adrs --business=1 --user=1 --reset  # remove e re-seed
 */
class SeedAdrsCommand extends Command
{
    protected $signature = 'copiloto:seed-adrs
                            {--business=1 : business_id da empresa dona dos ADRs}
                            {--user=1     : user_id que será atribuído como dono dos fatos}
                            {--dry-run    : Mostra o que seria feito sem gravar}
                            {--reset      : Remove fatos de ADR existentes antes de re-seed}
                            {--type=adr   : Tipo de documento a sedar (adr|spec|reference|all)}';

    protected $description = 'Transforma ADRs do MCP em fatos RAG pesquisáveis por business (MEM-MULTI-1)';

    public function handle(): int
    {
        $businessId = (int) $this->option('business');
        $userId     = (int) $this->option('user');
        $dryRun     = (bool) $this->option('dry-run');
        $reset      = (bool) $this->option('reset');
        $type       = (string) $this->option('type');

        $this->info("Seed ADRs → copiloto_memoria_facts");
        $this->line("  business_id : $businessId");
        $this->line("  user_id     : $userId");
        $this->line("  tipo        : $type");
        if ($dryRun) $this->warn("  [DRY RUN — nada será gravado]");

        // Carrega ADRs do MCP filtrado por business
        $query = DB::table('mcp_memory_documents')
            ->whereNull('deleted_at')
            ->where('business_id', $businessId);

        if ($type !== 'all') {
            $types = $type === 'adr' ? ['adr'] : [$type];
            $query->whereIn('type', $types);
        }

        $docs = $query->get(['id', 'slug', 'type', 'title', 'content_md', 'metadata', 'indexed_at']);

        $this->line("  documentos encontrados: " . $docs->count());

        if ($docs->isEmpty()) {
            $this->warn("Nenhum documento encontrado para business_id=$businessId tipo=$type.");
            $this->line("Dica: rode 'php artisan mcp:sync-memory' pra sincronizar memory/ com o DB.");
            return self::SUCCESS;
        }

        // Reset: remove fatos seedados anteriormente (marker source_slug no metadata)
        if ($reset && ! $dryRun) {
            $removed = DB::table('copiloto_memoria_facts')
                ->where('business_id', $businessId)
                ->where('user_id', $userId)
                ->whereRaw("JSON_EXTRACT(metadata, '$.seeded_from_mcp') = true")
                ->delete();
            $this->line("  reset: $removed fatos removidos");
        }

        $stats = ['inserted' => 0, 'updated' => 0, 'superseded' => 0, 'skipped' => 0];

        // Carrega slugs existentes de uma só query (evita N+1 com JSON_EXTRACT por linha)
        $existingBySlug = [];
        if (! $dryRun) {
            $existing = DB::table('copiloto_memoria_facts')
                ->where('business_id', $businessId)
                ->where('user_id', $userId)
                ->whereRaw("JSON_EXTRACT(metadata, '$.seeded_from_mcp') = true")
                ->get(['id', 'metadata', 'valid_until']);

            foreach ($existing as $row) {
                $m = json_decode($row->metadata ?? '{}', true) ?: [];
                if (isset($m['source_slug'])) {
                    $existingBySlug[$m['source_slug']] = $row;
                }
            }
        }

        $toInsert = [];
        $now = now()->toDateTimeString();

        foreach ($docs as $doc) {
            $meta = json_decode($doc->metadata ?? '{}', true) ?: [];

            $status     = Str::lower($meta['status'] ?? 'accepted');
            $supersedes = $meta['supersedes'] ?? null;

            // Determina validade temporal
            $isSuperseded = in_array($status, ['superseded', 'deprecated', 'rejected'], true);
            $validUntil   = $isSuperseded ? ($doc->indexed_at ?? $now) : null;

            // Extrai resumo do conteúdo (primeiros ~300 chars sem headers)
            $summary = $this->extractSummary($doc->content_md ?? '');

            // Monta o "fato" textual que irá pro índice Meilisearch
            $fato = $this->buildFatoText($doc, $meta, $summary, $status);

            $fatoMeta = json_encode([
                'seeded_from_mcp' => true,
                'source_type'     => $doc->type,
                'source_slug'     => $doc->slug,
                'source_title'    => $doc->title,
                'adr_status'      => $status,
                'supersedes'      => $supersedes,
                'module'          => $meta['module'] ?? null,
                'indexed_at'      => $doc->indexed_at,
            ]);

            if ($dryRun) {
                $this->line("  [DRY] [{$doc->slug}] status={$status} superseded=" . ($isSuperseded ? 'sim' : 'não'));
                $stats['inserted']++;
                continue;
            }

            if (isset($existingBySlug[$doc->slug])) {
                // Update individual (poucos — só quando re-seed)
                DB::table('copiloto_memoria_facts')
                    ->where('id', $existingBySlug[$doc->slug]->id)
                    ->update([
                        'fato'        => $fato,
                        'metadata'    => $fatoMeta,
                        'valid_until' => $validUntil,
                        'updated_at'  => $now,
                    ]);
                $stats[$isSuperseded ? 'superseded' : 'updated']++;
            } else {
                // Acumula pra batch insert
                $toInsert[] = [
                    'business_id' => $businessId,
                    'user_id'     => $userId,
                    'fato'        => $fato,
                    'metadata'    => $fatoMeta,
                    'valid_from'  => $now,
                    'valid_until' => $validUntil,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
                $stats[$isSuperseded ? 'superseded' : 'inserted']++;
            }
        }

        // Batch insert em chunks de 50 (evita query muito longa)
        if (! empty($toInsert)) {
            foreach (array_chunk($toInsert, 50) as $chunk) {
                DB::table('copiloto_memoria_facts')->insert($chunk);
            }
        }

        $this->newLine();
        $this->info("Concluído:");
        $this->line("  inseridos   : {$stats['inserted']}");
        $this->line("  atualizados : {$stats['updated']}");
        $this->line("  superseded  : {$stats['superseded']} (valid_until preenchido)");
        $this->line("  skipped     : {$stats['skipped']}");

        if (! $dryRun) {
            $total = $stats['inserted'] + $stats['updated'] + $stats['superseded'];
            $this->newLine();
            $this->info("Próximo passo: indexar no Meilisearch e medir recall:");
            $this->line("  php artisan scout:import \"Modules\\\\Copiloto\\\\Entities\\\\CopilotoMemoriaFato\"");
            $this->line("  php artisan copiloto:eval --persist --business=$businessId");
        }

        return self::SUCCESS;
    }

    private function extractSummary(string $contentMd): string
    {
        // Remove frontmatter YAML
        $content = preg_replace('/^---.*?---\s*/s', '', $contentMd);
        // Remove headers markdown
        $content = preg_replace('/^#{1,6}\s+.+$/m', '', $content);
        // Remove links e imagens
        $content = preg_replace('/!?\[([^\]]*)\]\([^\)]*\)/', '$1', $content);
        // Colapsa espaços
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        return mb_substr($content, 0, 400);
    }

    private function buildFatoText(object $doc, array $meta, string $summary, string $status): string
    {
        $parts = [];

        $typeLabel = match ($doc->type) {
            'adr'       => 'ADR',
            'spec'      => 'SPEC',
            'reference' => 'REF',
            default     => strtoupper($doc->type),
        };

        // Linha principal: tipo + slug + título
        $parts[] = "[{$typeLabel} {$doc->slug}] {$doc->title}";

        // Status e módulo
        $statusLabel = match ($status) {
            'accepted'   => 'Aceita',
            'proposed'   => 'Proposta',
            'superseded' => 'Supersedida',
            'deprecated' => 'Depreciada',
            'rejected'   => 'Rejeitada',
            default      => ucfirst($status),
        };

        $modulo = $meta['module'] ?? null;
        $infoLine = "Status: {$statusLabel}";
        if ($modulo) $infoLine .= " | Módulo: {$modulo}";
        if (isset($meta['deciders'])) $infoLine .= " | Decisores: {$meta['deciders']}";
        $parts[] = $infoLine;

        // Contexto/resumo
        if ($summary) {
            $parts[] = $summary;
        }

        // Se supersedida, mencionar o que substituiu
        if ($status === 'superseded' && isset($meta['superseded_by'])) {
            $parts[] = "Substituída por: {$meta['superseded_by']}";
        }
        if (isset($meta['supersedes']) && $meta['supersedes'] !== '-') {
            $parts[] = "Substitui: {$meta['supersedes']}";
        }

        return implode("\n", $parts);
    }
}
