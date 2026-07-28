<?php

declare(strict_types=1);

namespace Modules\KB\Console\Commands;

use App\Util\OtelHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * kb:code-graph — Fase D (grafo de dependências do código — doc↔código estilo Swimm).
 *
 * Varre PHP (AST via nikic/php-parser — ADR 0350), extrai as dependências
 * "classe A usa classe B" dos `use` imports, e materializa como `kb_edges`
 * (edge_type=references-data, generated_by=code_scan) entre os nós de código
 * que o `kb:code-scan` gerou (Fase B/C). É o "entendimento de codebase" do
 * Swimm — o mapa de quem-depende-de-quem, navegável no grafo do KB.
 *
 * Só cria aresta quando AMBAS as pontas foram escaneadas (dependência
 * INTRA-conjunto) e existem como KbNode type=reference — os nós são resolvidos
 * por `title` (FQCN). Rode `kb:code-scan` no mesmo path ANTES. Deps externas
 * (Laravel, vendor) são ignoradas de propósito (não são nós do projeto).
 *
 * Sem migration: `edge_type`/`generated_by` são varchar(40); `code_scan` foi
 * adicionado a KbEdge::GENERATED_BY. Escrita RAW (updateOrInsert no quad único
 * business+from+to+edge_type), idempotente, fora de Observer.
 *
 * Roda em DEV/CI/CT 100 — NUNCA no runtime web do Hostinger (ADR 0062).
 * Tier 0 multi-tenant: --business-id obrigatório (ADR 0093).
 *
 * Uso:
 *   php artisan kb:code-scan  --path=Modules/KB/Services --business-id=1   # 1º: nós
 *   php artisan kb:code-graph --path=Modules/KB/Services --business-id=1   # 2º: arestas
 */
class KbCodeGraphCommand extends Command
{
    protected $signature = 'kb:code-graph
                            {--path= : Diretório (ou arquivo) PHP a varrer (recursivo)}
                            {--business-id= : Business ID (obrigatório — Tier 0 ADR 0093)}
                            {--dry-run : Mostra as arestas que criaria, sem gravar}';

    protected $description = 'Materializa kb_edges de dependência (classe usa classe) a partir dos use-imports do código (AST).';

    public function handle(): int
    {
        if (! class_exists(ParserFactory::class)) {
            $this->error('nikic/php-parser ausente (ADR 0350). composer install COM dev deps (CT 100).');

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

        return OtelHelper::span('kb.code_graph', [
            'module' => 'KB',
            'business_id' => $bizId,
        ], fn () => $this->doHandle($bizId, $path));
    }

    private function doHandle(int $bizId, string $path): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // File collection inline (evita duplicar símbolo com kb:code-scan — reuse-gate).
        $files = [];
        if (is_file($path)) {
            if (str_ends_with($path, '.php')) {
                $files = [$path];
            }
        } else {
            foreach (File::allFiles($path) as $f) {
                $p = $f->getPathname();
                if (str_ends_with($p, '.php') && ! str_contains($p, '/vendor/') && ! str_contains($p, '/node_modules/')) {
                    $files[] = $p;
                }
            }
        }

        if (empty($files)) {
            $this->info('Nenhum arquivo .php encontrado no path.');

            return self::SUCCESS;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $finder = new NodeFinder();

        // Passe 1 — mapeia as FQCNs presentes no conjunto + os use-imports por classe.
        $classFqcns = [];   // set: fqcn => true
        $classImports = []; // fqcn => [importedFqcn, ...]

        foreach ($files as $file) {
            $code = @file_get_contents($file);
            if ($code === false) {
                continue;
            }
            try {
                $ast = $parser->parse($code);
            } catch (\Throwable $e) {
                $this->warn("parse falhou em {$file}: {$e->getMessage()}");
                continue;
            }
            if ($ast === null) {
                continue;
            }

            $nsName = $finder->findFirstInstanceOf($ast, Namespace_::class)?->name?->toString();

            // use-imports do arquivo (compartilhados por todas as classes do arquivo).
            $imports = [];
            /** @var Use_[] $uses */
            $uses = $finder->findInstanceOf($ast, Use_::class);
            foreach ($uses as $use) {
                foreach ($use->uses as $u) {
                    $imports[] = $u->name->toString();
                }
            }

            /** @var Class_[] $classes */
            $classes = $finder->findInstanceOf($ast, Class_::class);
            foreach ($classes as $class) {
                if ($class->name === null) {
                    continue;
                }
                $fqcn = ($nsName ? $nsName.'\\' : '').$class->name->toString();
                $classFqcns[$fqcn] = true;
                $classImports[$fqcn] = $imports;
            }
        }

        // Passe 2 — cada import que é OUTRA classe do conjunto vira aresta.
        $rows = [];
        $written = 0;
        $skippedNoNode = 0;

        foreach ($classImports as $fromFqcn => $imports) {
            foreach (array_unique($imports) as $toFqcn) {
                if ($fromFqcn === $toFqcn || ! isset($classFqcns[$toFqcn])) {
                    continue; // self-loop OU dependência externa (fora do conjunto)
                }

                if ($dryRun) {
                    $rows[] = ['de' => $fromFqcn, 'usa' => $toFqcn];
                    continue;
                }

                $res = $this->upsertEdge($bizId, $fromFqcn, $toFqcn);
                if ($res === 'ok') {
                    $written++;
                    $rows[] = ['de' => $fromFqcn, 'usa' => $toFqcn];
                } elseif ($res === 'no_node') {
                    $skippedNoNode++;
                }
            }
        }

        if (! empty($rows)) {
            $this->table(['Classe', 'depende de'], array_map(
                fn ($r) => [\Illuminate\Support\Str::limit($r['de'], 50), \Illuminate\Support\Str::limit($r['usa'], 50)],
                array_slice($rows, 0, 200)
            ));
        }

        if ($dryRun) {
            $this->info(count($rows).' aresta(s) seriam criadas (dry-run — nada gravado).');
        } else {
            $this->info("{$written} aresta(s) de dependência gravadas (business_id={$bizId}).");
            if ($skippedNoNode > 0) {
                $this->warn("{$skippedNoNode} puladas: nó de código ausente — rode `kb:code-scan --path=… --business-id={$bizId}` primeiro.");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Cria/atualiza a aresta de dependência entre os nós de código das duas FQCNs.
     * Resolve os nós por (business_id, type=reference, title=FQCN).
     *
     * @return string 'ok' | 'no_node' (nó ausente) | 'self' (mesma ponta)
     */
    private function upsertEdge(int $bizId, string $fromFqcn, string $toFqcn): string
    {
        $fromId = $this->nodeIdFor($bizId, $fromFqcn);
        $toId = $this->nodeIdFor($bizId, $toFqcn);

        if ($fromId === null || $toId === null) {
            return 'no_node';
        }
        if ($fromId === $toId) {
            return 'self'; // CHECK constraint no-self-loop
        }

        DB::table('kb_edges')->updateOrInsert(
            [
                'business_id' => $bizId,
                'from_node_id' => $fromId,
                'to_node_id' => $toId,
                'edge_type' => 'references-data',
            ],
            [
                'weight' => 1.0,
                'generated_by' => 'code_scan',
                'payload' => json_encode(['source' => 'code_scan', 'kind' => 'php-use'], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return 'ok';
    }

    private function nodeIdFor(int $bizId, string $fqcn): ?int
    {
        $id = DB::table('kb_nodes')
            ->where('business_id', $bizId)
            ->where('type', 'reference')
            ->where('title', $fqcn)
            ->whereNull('deleted_at')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
