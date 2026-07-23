<?php

declare(strict_types=1);

namespace Modules\KB\Console\Commands;

use App\Util\OtelHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * kb:code-scan — Fase B (auto-document estilo Swimm, PHP-first — ADR 0350).
 *
 * Extrai a ESTRUTURA de arquivos PHP via AST (nikic/php-parser) — namespace,
 * classe, métodos públicos, resumo do docblock — e gera/atualiza um nó KB
 * `type=reference` (idempotente por slug derivado do FQCN). É o "auto-document"
 * do doc↔código: a outra metade do kb:drift-detector (que detecta doc apontando
 * pra código morto; este DERIVA doc do código vivo).
 *
 * `type='reference'` reusado (sem migration — decisão [W] 2026-07-23). O nó é
 * `is_editable=true` (precisa de body_blocks inline; o invariante ADR 0061 só
 * proíbe body_blocks em bridge is_editable=false) mas a escrita é RAW
 * (DB::table::updateOrInsert), fora do KbNodeObserver — sem churn de versão a
 * cada scan e sem audit-log de cron.
 *
 * Roda em DEV/CI/CT 100 — NUNCA no runtime web do Hostinger (ADR 0062). É
 * ferramenta de geração, não daemon nem rota.
 *
 * Tier 0 multi-tenant: --business-id obrigatório (ADR 0093 — CLI fora de HTTP).
 *
 * Uso:
 *   php artisan kb:code-scan --path=Modules/KB/Services/KbRagService.php --business-id=1 --dry-run
 *   php artisan kb:code-scan --path=Modules/KB/Services --business-id=1
 */
class KbCodeScanCommand extends Command
{
    protected $signature = 'kb:code-scan
                            {--path= : Arquivo .php OU diretório a varrer (recursivo)}
                            {--business-id= : Business ID (obrigatório — Tier 0 ADR 0093)}
                            {--dry-run : Mostra os nós que geraria, sem gravar}';

    protected $description = 'Gera nós KB (type=reference) a partir da estrutura de arquivos PHP (AST) — auto-document doc↔código.';

    public function handle(): int
    {
        if (! class_exists(ParserFactory::class)) {
            $this->error('nikic/php-parser ausente (ADR 0350). Rode composer install COM dev deps (CT 100), não --no-dev.');

            return self::FAILURE;
        }

        $bizId = (int) $this->option('business-id');
        if ($bizId <= 0) {
            $this->error('--business-id obrigatório (multi-tenant Tier 0 ADR 0093).');

            return self::FAILURE;
        }
        if ($bizId === 4) {
            $this->error('biz=4 (ROTA LIVRE prod) NUNCA em scripts (ADR 0101). Use biz=1.');

            return self::FAILURE;
        }

        $path = (string) $this->option('path');
        if ($path === '' || ! file_exists($path)) {
            $this->error("--path inexistente: '{$path}'");

            return self::FAILURE;
        }

        return OtelHelper::span('kb.code_scan', [
            'module' => 'KB',
            'business_id' => $bizId,
        ], fn () => $this->doHandle($bizId, $path));
    }

    private function doHandle(int $bizId, string $path): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $files = $this->collectPhpFiles($path);

        if (empty($files)) {
            $this->info('Nenhum arquivo .php encontrado no path.');

            return self::SUCCESS;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $finder = new NodeFinder();

        $rows = [];
        $written = 0;

        foreach ($files as $file) {
            $code = @file_get_contents($file);
            if ($code === false) {
                continue;
            }

            try {
                $ast = $parser->parse($code);
            } catch (\Throwable $e) {
                // Arquivo com sintaxe inválida/parcial — pula sem quebrar o scan.
                $this->warn("parse falhou em {$file}: {$e->getMessage()}");
                continue;
            }
            if ($ast === null) {
                continue;
            }

            $namespace = $finder->findFirstInstanceOf($ast, Namespace_::class);
            $nsName = $namespace?->name?->toString();

            /** @var Class_[] $classes */
            $classes = $finder->findInstanceOf($ast, Class_::class);
            foreach ($classes as $class) {
                if ($class->name === null) {
                    continue; // classe anônima
                }
                $fqcn = ($nsName ? $nsName.'\\' : '').$class->name->toString();
                $doc = $this->summaryFromDoc($class->getDocComment());

                /** @var ClassMethod[] $methods */
                $methods = $finder->findInstanceOf($class, ClassMethod::class);
                $publicMethods = [];
                foreach ($methods as $m) {
                    if (! $m->isPublic() || $m->name->toString() === '__construct') {
                        continue;
                    }
                    $mDoc = $this->summaryFromDoc($m->getDocComment());
                    $publicMethods[] = $m->name->toString().'()'.($mDoc !== '' ? " — {$mDoc}" : '');
                }

                $slug = $this->slugFor($fqcn);
                $rows[] = [
                    'fqcn' => $fqcn,
                    'metodos' => (string) count($publicMethods),
                    'slug' => Str::limit($slug, 40, '…'),
                ];

                if (! $dryRun) {
                    $this->upsertNode($bizId, $slug, $fqcn, $doc, $publicMethods, $file);
                    $written++;
                }
            }
        }

        $this->table(['FQCN', 'Métodos púb.', 'Slug'], $rows);

        if ($dryRun) {
            $this->info(count($rows).' nó(s) seriam gerados (dry-run — nada gravado).');
        } else {
            $this->info("{$written} nó(s) KB gravado(s)/atualizado(s) (business_id={$bizId}).");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int,string>
     */
    private function collectPhpFiles(string $path): array
    {
        if (is_file($path)) {
            return str_ends_with($path, '.php') ? [$path] : [];
        }

        $out = [];
        foreach (File::allFiles($path) as $f) {
            $p = $f->getPathname();
            if (! str_ends_with($p, '.php')) {
                continue;
            }
            if (str_contains($p, '/vendor/') || str_contains($p, '/node_modules/')) {
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }

    /** Primeira linha significativa do docblock (sem `/**`, `*`, tags @). */
    private function summaryFromDoc(?Doc $doc): string
    {
        if ($doc === null) {
            return '';
        }
        foreach (preg_split('/\r?\n/', $doc->getText()) as $line) {
            $line = trim($line);
            $line = ltrim($line, '/* ');
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '@')) {
                continue;
            }

            return Str::limit($line, 480, '…');
        }

        return '';
    }

    private function slugFor(string $fqcn): string
    {
        return Str::limit('code-'.Str::slug(str_replace('\\', '/', $fqcn)), 180, '');
    }

    /**
     * Upsert RAW (fora do Observer): idempotente por (business_id, slug).
     *
     * @param  array<int,string>  $publicMethods
     */
    private function upsertNode(int $bizId, string $slug, string $fqcn, string $doc, array $publicMethods, string $file): void
    {
        $blocks = [
            ['kind' => 'para', 't' => "Arquivo: {$file}"],
        ];
        if (! empty($publicMethods)) {
            $blocks[] = ['kind' => 'h2', 't' => 'Métodos públicos'];
            $blocks[] = ['kind' => 'list', 'items' => array_values($publicMethods)];
        }

        DB::table('kb_nodes')->updateOrInsert(
            ['business_id' => $bizId, 'slug' => $slug],
            [
                'type' => 'reference',
                'title' => $fqcn,
                'excerpt' => $doc !== '' ? Str::limit($doc, 480, '…') : null,
                'body_blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
                'is_editable' => true, // tem body_blocks inline — invariante ADR 0061 exige is_editable=true
                'tags' => json_encode(['gerado', 'codigo'], JSON_UNESCAPED_UNICODE),
                'status' => 'ok',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
