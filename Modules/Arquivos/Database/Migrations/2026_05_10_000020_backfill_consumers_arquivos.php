<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration backfill — US-ARQ-026..028 (Sprint 5 do ADR 0123).
 *
 * Cria rows em `arquivos` table pra 3 consumers Sprint 4 (PR #399):
 * - media table → arquivos polimórfico (App\Media morphMany — JobSheet/etc)
 * - cms_pages.feature_image → arquivos polimórfico CmsPage (sub_destination='cms-featured')
 * - fin_boleto_remessas.pdf_path → arquivos polimórfico BoletoRemessa (sub_destination='fin-boleto-pdf')
 *
 * Idempotente: skip se já existir Arquivo pra (arquivable, sub_destination).
 * Reversível: down() DELETE WHERE classified_by='backfill-us-arq-026..028'.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * arquivos.business_id herda de cada origem.
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 5 plano
 */
return new class extends Migration {
    private const SOURCE_TAG_REPAIR  = 'backfill-us-arq-026';
    private const SOURCE_TAG_CMS     = 'backfill-us-arq-027';
    private const SOURCE_TAG_BOLETO  = 'backfill-us-arq-028';

    public function up(): void
    {
        if (! Schema::hasTable('arquivos')) {
            $this->log('arquivos table missing — skip');
            return;
        }

        $this->backfillMedia();
        $this->backfillCmsFeatureImage();
        $this->backfillBoletoPdf();
    }

    public function down(): void
    {
        if (! Schema::hasTable('arquivos')) return;

        DB::table('arquivos')
            ->whereIn('classified_by', [
                self::SOURCE_TAG_REPAIR,
                self::SOURCE_TAG_CMS,
                self::SOURCE_TAG_BOLETO,
            ])
            ->delete();
    }

    /**
     * App\Media polymorphic — backfill rows com model_type='Modules\\Repair\\Entities\\JobSheet'
     * (foco Sprint 5 — outros morphs JobSheet entram quando necessário).
     */
    private function backfillMedia(): void
    {
        if (! Schema::hasTable('media')) {
            $this->log('media missing — skip Repair backfill');
            return;
        }

        $created = 0;
        $skipped = 0;

        DB::table('media')
            ->where('model_type', 'Modules\\Repair\\Entities\\JobSheet')
            ->select(['id', 'business_id', 'file_name', 'model_id', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$created, &$skipped) {
                foreach ($rows as $row) {
                    $exists = DB::table('arquivos')
                        ->where('arquivable_type', 'Modules\\Repair\\Entities\\JobSheet')
                        ->where('arquivable_id', $row->model_id)
                        ->where('sub_destination', 'repair-foto')
                        ->where('original_name', basename($row->file_name))
                        ->exists();
                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    DB::table('arquivos')->insert($this->buildRow(
                        arquivableType: 'Modules\\Repair\\Entities\\JobSheet',
                        arquivableId:   $row->model_id,
                        businessId:     $row->business_id,
                        path:           $row->file_name,
                        subDestination: 'repair-foto',
                        sourceTag:      self::SOURCE_TAG_REPAIR,
                        mime:           $this->guessMimeFromPath($row->file_name, 'image/jpeg'),
                        createdAt:      $row->created_at ?? now(),
                    ));
                    $created++;
                }
            });

        $this->log("Repair Media: criados={$created} skipped={$skipped}");
    }

    /**
     * cms_pages.feature_image string → arquivos polimórfico CmsPage.
     */
    private function backfillCmsFeatureImage(): void
    {
        if (! Schema::hasTable('cms_pages')) {
            $this->log('cms_pages missing — skip');
            return;
        }

        $columns = Schema::getColumnListing('cms_pages');
        if (! in_array('feature_image', $columns, true)) {
            $this->log('cms_pages.feature_image column missing — skip');
            return;
        }

        $created = 0;
        $skipped = 0;

        // cms_pages NÃO tem business_id histórico — usa created_by ou null.
        // Sprint 5 assume business_id=1 (Wagner WR2) pra rows sem owner — Felipe valida.
        DB::table('cms_pages')
            ->whereNotNull('feature_image')
            ->where('feature_image', '!=', '')
            ->select(['id', 'feature_image', 'created_by', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$created, &$skipped) {
                foreach ($rows as $row) {
                    $exists = DB::table('arquivos')
                        ->where('arquivable_type', 'Modules\\Cms\\Entities\\CmsPage')
                        ->where('arquivable_id', $row->id)
                        ->where('sub_destination', 'cms-featured')
                        ->exists();
                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    DB::table('arquivos')->insert($this->buildRow(
                        arquivableType: 'Modules\\Cms\\Entities\\CmsPage',
                        arquivableId:   $row->id,
                        businessId:     1, // CMS é singleton site landing — biz=1
                        path:           'uploads/cms/' . $row->feature_image,
                        subDestination: 'cms-featured',
                        sourceTag:      self::SOURCE_TAG_CMS,
                        mime:           $this->guessMimeFromPath($row->feature_image, 'image/jpeg'),
                        createdAt:      $row->created_at ?? now(),
                    ));
                    $created++;
                }
            });

        $this->log("Cms feature_image: criados={$created} skipped={$skipped}");
    }

    /**
     * fin_boleto_remessas.pdf_path → arquivos polimórfico BoletoRemessa.
     */
    private function backfillBoletoPdf(): void
    {
        if (! Schema::hasTable('fin_boleto_remessas')) {
            $this->log('fin_boleto_remessas missing — skip');
            return;
        }

        $columns = Schema::getColumnListing('fin_boleto_remessas');
        if (! in_array('pdf_path', $columns, true)) {
            $this->log('fin_boleto_remessas.pdf_path column missing — skip');
            return;
        }

        $created = 0;
        $skipped = 0;

        DB::table('fin_boleto_remessas')
            ->whereNotNull('pdf_path')
            ->where('pdf_path', '!=', '')
            ->select(['id', 'business_id', 'pdf_path', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$created, &$skipped) {
                foreach ($rows as $row) {
                    $exists = DB::table('arquivos')
                        ->where('arquivable_type', 'Modules\\Financeiro\\Models\\BoletoRemessa')
                        ->where('arquivable_id', $row->id)
                        ->where('sub_destination', 'fin-boleto-pdf')
                        ->exists();
                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    DB::table('arquivos')->insert($this->buildRow(
                        arquivableType: 'Modules\\Financeiro\\Models\\BoletoRemessa',
                        arquivableId:   $row->id,
                        businessId:     $row->business_id,
                        path:           $row->pdf_path,
                        subDestination: 'fin-boleto-pdf',
                        sourceTag:      self::SOURCE_TAG_BOLETO,
                        mime:           'application/pdf',
                        createdAt:      $row->created_at ?? now(),
                    ));
                    $created++;
                }
            });

        $this->log("Financeiro Boleto: criados={$created} skipped={$skipped}");
    }

    private function buildRow(
        string $arquivableType,
        int $arquivableId,
        int $businessId,
        string $path,
        string $subDestination,
        string $sourceTag,
        string $mime,
        $createdAt,
    ): array {
        return [
            'business_id'         => $businessId,
            'arquivable_type'     => $arquivableType,
            'arquivable_id'       => $arquivableId,
            'disk'                => 'local',
            'storage_path'        => $path,
            'original_name'       => basename($path),
            'mime_type'           => $mime,
            'size_bytes'          => 0, // placeholder — Sprint 6 recalcula via job
            'md5'                 => md5("{$arquivableType}:{$arquivableId}:{$path}"),
            'bucket'              => 'active',
            'sub_destination'     => $subDestination,
            'sensitive_flags'     => null,
            'classified_by'       => $sourceTag,
            'classified_at'       => now(),
            'uploaded_by_user_id' => null,
            'visibility'          => 'private',
            'encrypted'           => false,
            'retention_days'      => null,
            'created_at'          => $createdAt,
            'updated_at'          => now(),
        ];
    }

    private function guessMimeFromPath(string $path, string $default = 'application/octet-stream'): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'pdf'           => 'application/pdf',
            'png'           => 'image/png',
            'jpg', 'jpeg'   => 'image/jpeg',
            'gif'           => 'image/gif',
            'webp'          => 'image/webp',
            'svg'           => 'image/svg+xml',
            'xml'           => 'application/xml',
            'json'          => 'application/json',
            'doc', 'docx'   => 'application/msword',
            'xls', 'xlsx'   => 'application/vnd.ms-excel',
            default         => $default,
        };
    }

    private function log(string $msg): void
    {
        echo "  [arquivos-backfill-consumers] {$msg}\n";
    }
};
