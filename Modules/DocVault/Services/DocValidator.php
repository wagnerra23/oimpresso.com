<?php

namespace Modules\DocVault\Services;

use Illuminate\Support\Facades\File;
use Modules\DocVault\Entities\DocPage;
use Modules\DocVault\Entities\DocValidationRun;

/**
 * Valida a integridade da documentação do DocVault.
 *
 * 5 checks (definidos no ADR 0005):
 *   1. STORY_ORPHAN:   user story sem página associada
 *   2. RULE_NO_TEST:   regra sem "Testado em:"
 *   3. ADR_DANGLING:   ADR referenciado que não existe (em páginas ou textos)
 *   4. PAGE_NO_META:   .tsx sem bloco @docvault
 *   5. PAGE_STALE:     página planejada há mais de 30 dias sem mudança
 *
 * Resultado persistido em docs_validation_runs com health_score 0-100.
 */
class DocValidator
{
    public function __construct(
        protected RequirementsFileReader $reader
    ) {}

    public function validate(?string $onlyModule = null): array
    {
        $issues = [];
        $modules = $onlyModule
            ? array_filter([$this->reader->readModule($onlyModule)])
            : $this->reader->listModules();

        $allPages = DocPage::all();

        foreach ($modules as $m) {
            $moduleName = $m['name'];
            $data = isset($m['stories']) && is_array($m['stories'])
                ? $m
                : $this->reader->readModule($moduleName);
            if (! $data) continue;

            $pagesOfModule = $allPages->where('module', $moduleName);

            // 1. STORY_ORPHAN
            $storiesWithPage = [];
            foreach ($pagesOfModule as $p) {
                foreach (($p->stories ?? []) as $s) $storiesWithPage[$s] = true;
            }
            foreach ($data['stories'] as $story) {
                if (! isset($storiesWithPage[$story['id']])) {
                    $issues[] = [
                        'type'    => 'STORY_ORPHAN',
                        'level'   => 'warning',
                        'module'  => $moduleName,
                        'ref'     => $story['id'],
                        'message' => "Story {$story['id']} ({$story['title']}) não tem página associada em docs_pages.",
                    ];
                }
            }

            // 2. RULE_NO_TEST
            foreach ($data['rules'] as $rule) {
                if (empty($rule['testado_em'])) {
                    $issues[] = [
                        'type'    => 'RULE_NO_TEST',
                        'level'   => 'warning',
                        'module'  => $moduleName,
                        'ref'     => $rule['id'],
                        'message' => "Regra {$rule['id']} ({$rule['title']}) não tem 'Testado em:' apontando pra arquivo de teste.",
                    ];
                }
            }

            // 3. ADR_DANGLING: páginas citam ADR que não existe
            $availableAdrs = array_column($data['adrs'] ?? [], 'number');
            foreach ($pagesOfModule as $p) {
                foreach (($p->adrs ?? []) as $adrNum) {
                    $normalized = str_pad(preg_replace('/\D/', '', $adrNum), 4, '0', STR_PAD_LEFT);
                    if (! empty($availableAdrs) && ! in_array($normalized, $availableAdrs, true)) {
                        $issues[] = [
                            'type'    => 'ADR_DANGLING',
                            'level'   => 'critical',
                            'module'  => $moduleName,
                            'ref'     => "{$p->path} → ADR {$adrNum}",
                            'message' => "Página {$p->path} referencia ADR {$adrNum} que não existe no módulo.",
                        ];
                    }
                }
            }

            // 5. PAGE_STALE: planejada há >30 dias
            foreach ($pagesOfModule as $p) {
                if ($p->status === 'planejada' && $p->updated_at && $p->updated_at->lt(now()->subDays(30))) {
                    $issues[] = [
                        'type'    => 'PAGE_STALE',
                        'level'   => 'warning',
                        'module'  => $moduleName,
                        'ref'     => $p->path,
                        'message' => "Página {$p->path} está 'planejada' há mais de 30 dias sem atualização.",
                    ];
                }
            }
        }

        // 4. PAGE_NO_META: .tsx sem @docvault (roda uma vez só, global)
        if (! $onlyModule) {
            $pagesRoot = resource_path('js/Pages');
            if (File::isDirectory($pagesRoot)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pagesRoot, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.tsx')) continue;
                    $head = @file_get_contents($f->getPathname(), false, null, 0, 4096);
                    if ($head === false || ! str_contains($head, '@docvault')) {
                        $relative = str_replace($pagesRoot . DIRECTORY_SEPARATOR, '', $f->getPathname());
                        $issues[] = [
                            'type'    => 'PAGE_NO_META',
                            'level'   => 'info',
                            'module'  => null,
                            'ref'     => 'resources/js/Pages/' . str_replace('\\', '/', $relative),
                            'message' => "Tela {$relative} não tem bloco @docvault.",
                        ];
                    }
                }
            }
        }

        // Score: 100 = zero issue; perde pontos por severidade
        $critical = count(array_filter($issues, fn ($i) => $i['level'] === 'critical'));
        $warnings = count(array_filter($issues, fn ($i) => $i['level'] === 'warning'));
        $infos    = count(array_filter($issues, fn ($i) => $i['level'] === 'info'));
        $score = max(0, 100 - ($critical * 10) - ($warnings * 3) - ($infos * 1));

        $run = DocValidationRun::create([
            'run_at'          => now(),
            'module'          => $onlyModule,
            'issues_total'    => count($issues),
            'issues_critical' => $critical,
            'issues'          => $issues,
            'health_score'    => $score,
        ]);

        return [
            'run_id'       => $run->id,
            'health_score' => $score,
            'totals'       => [
                'critical' => $critical,
                'warnings' => $warnings,
                'infos'    => $infos,
                'total'    => count($issues),
            ],
            'issues'       => $issues,
        ];
    }
}
