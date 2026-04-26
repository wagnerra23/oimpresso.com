<?php

namespace Modules\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Cms\Entities\CmsPage;

/**
 * Importa posts e páginas do WordPress officeimpresso.com.br pra cms_pages.
 *
 * Conexão configurada via .env (WP_OFFICEIMPRESSO_DB_*).
 * Idempotente — usa o slug (title slugificado) como chave; rerun não duplica.
 *
 * NÃO rodar em produção sem revisar o conteúdo importado primeiro.
 */
class ImportWpOfficeImpressoCommand extends Command
{
    protected $signature = 'cms:import-wp-officeimpresso
                            {--connection=wp_officeimpresso : Nome da conexão DB do WP}
                            {--limit=0 : Limite de posts (0 = sem limite)}
                            {--dry-run : Não escreve no DB; só mostra o que seria importado}';

    protected $description = 'Importa posts/páginas do WP officeimpresso.com.br pra cms_pages';

    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Conectando em [$connection]…");

        try {
            DB::connection($connection)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Falha ao conectar: '.$e->getMessage());

            return self::FAILURE;
        }

        $query = DB::connection($connection)
            ->table('posts')
            ->whereIn('post_type', ['page', 'post'])
            ->where('post_status', 'publish')
            ->whereRaw('CHAR_LENGTH(post_content) > 100')
            ->orderBy('post_date', 'desc');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get([
            'ID',
            'post_title',
            'post_content',
            'post_excerpt',
            'post_type',
            'post_date',
            'post_name',
        ]);

        $this->info('Encontrados '.$rows->count().' posts/páginas.');

        $imported = ['post' => 0, 'page' => 0];
        $skipped = 0;

        foreach ($rows as $row) {
            $title = trim((string) $row->post_title);
            if ($title === '') {
                $skipped++;
                continue;
            }

            $slug = Str::lower(Str::slug($title));
            if ($slug === '') {
                $skipped++;
                continue;
            }

            $type = $row->post_type === 'post' ? 'blog' : 'page';

            if (CmsPage::query()
                ->where('type', $type)
                ->whereRaw('LOWER(REPLACE(title, " ", "-")) = ?', [$slug])
                ->exists()
            ) {
                $skipped++;
                continue;
            }

            $content = $this->cleanBethemeShortcodes((string) $row->post_content);
            $excerpt = trim((string) $row->post_excerpt);

            if ($dryRun) {
                $this->line("[dry-run] {$type}: {$title} ({$slug})");
                $imported[$row->post_type === 'post' ? 'post' : 'page']++;
                continue;
            }

            CmsPage::create([
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'meta_description' => $excerpt !== '' ? Str::limit($excerpt, 250) : null,
                'tags' => null,
                'priority' => 0,
                'is_enabled' => 1,
            ]);

            $imported[$row->post_type === 'post' ? 'post' : 'page']++;
        }

        $this->info(sprintf(
            'Imported %d posts, %d pages, %d skipped.',
            $imported['post'],
            $imported['page'],
            $skipped,
        ));

        return self::SUCCESS;
    }

    /**
     * Remove tags Betheme [muffin] / [mfn ...] e shortcodes WP comuns,
     * preservando o HTML útil. Não tenta ser perfeito — só limpa o ruído.
     */
    protected function cleanBethemeShortcodes(string $html): string
    {
        // Remove pares [muffin]...[/muffin] e variantes [mfn_...] (1 nível, suficiente pra Betheme)
        $cleaned = preg_replace('/\[\/?(muffin|mfn[_a-z0-9]*)[^\]]*\]/i', '', $html);

        // Remove shortcodes WP genéricos sem fechamento ([vc_*], [contact-form-7], etc.)
        $cleaned = preg_replace('/\[\/?(vc_[a-z0-9_-]+|contact-form-7|caption|gallery|embed)[^\]]*\]/i', '', $cleaned ?? '');

        return trim($cleaned ?? $html);
    }
}
