<?php

namespace Modules\MemCofre\Services;

use Illuminate\Support\Facades\File;
use Modules\MemCofre\Entities\DocPage;

/**
 * Auditor de qualidade de documentação de um módulo específico.
 *
 * 15 checks (ADR 0007):
 *   C01 FRONTMATTER_COMPLETE   — status/risk/priority preenchidos
 *   C02 README_HAS_PURPOSE     — primeira seção clara do propósito
 *   C03 ARCHITECTURE_HAS_STACK — menciona stack/camadas/modelos
 *   C04 SPEC_HAS_STORIES       — ≥1 user story
 *   C05 SPEC_HAS_RULES         — ≥1 regra Gherkin
 *   C06 RULES_HAVE_TEST        — toda regra tem "Testado em:"
 *   C07 CHANGELOG_VERSIONED    — tem seção [x.y.z] - YYYY-MM-DD
 *   C08 ADRS_MINIMUM           — ≥3 ADRs em módulo ativo
 *   C09 PAGES_ANNOTATED        — todo .tsx do módulo tem @memcofre
 *   C10 HAS_GLOSSARY           — GLOSSARY.md presente
 *   C11 HAS_RUNBOOK            — RUNBOOK.md presente
 *   C12 HAS_DIAGRAMS           — diagrams/*.md presentes
 *   C13 HAS_CONTRACTS          — contracts/*.{yaml,md} presentes
 *   C14 MODULE_STATUS_MATCHES  — modules_statuses.json coerente com frontmatter.status
 *   C15 NO_EMPTY_PLACEHOLDERS  — sem [TODO] ou _[placeholder]_ soltos
 *
 * Retorna score 0-100 e lista de findings estruturados.
 */
class ModuleAuditor
{
    public function __construct(
        protected RequirementsFileReader $reader
    ) {}

    public function audit(string $moduleName): array
    {
        $data = $this->reader->readModule($moduleName);
        if (! $data) {
            return [
                'module'   => $moduleName,
                'score'    => 0,
                'findings' => [['code' => 'MODULE_NOT_FOUND', 'level' => 'critical', 'message' => 'Módulo não existe em memory/requisitos/.']],
                'date'     => date('Y-m-d'),
            ];
        }

        $findings = [];
        $checks = [
            'C01' => fn () => $this->c01Frontmatter($data),
            'C02' => fn () => $this->c02ReadmePurpose($data),
            'C03' => fn () => $this->c03ArchitectureStack($data),
            'C04' => fn () => $this->c04SpecStories($data),
            'C05' => fn () => $this->c05SpecRules($data),
            'C06' => fn () => $this->c06RulesTest($data),
            'C07' => fn () => $this->c07ChangelogVersioned($data),
            'C08' => fn () => $this->c08AdrsMinimum($data),
            'C09' => fn () => $this->c09PagesAnnotated($moduleName),
            'C10' => fn () => $this->simpleCheck($data['glossary'] ?? null, 'HAS_GLOSSARY', 'info', 'GLOSSARY.md não existe (opcional).'),
            'C11' => fn () => $this->simpleCheck($data['runbook'] ?? null, 'HAS_RUNBOOK', 'info', 'RUNBOOK.md não existe (opcional).'),
            'C12' => fn () => $this->simpleCheck(! empty($data['diagrams'] ?? []) ?: null, 'HAS_DIAGRAMS', 'info', 'diagrams/ vazio (opcional).'),
            'C13' => fn () => $this->simpleCheck(! empty($data['contracts'] ?? []) ?: null, 'HAS_CONTRACTS', 'info', 'contracts/ vazio (opcional).'),
            'C14' => fn () => $this->c14ModuleStatusMatch($data, $moduleName),
            'C15' => fn () => $this->c15NoPlaceholders($data),
        ];

        foreach ($checks as $code => $fn) {
            $result = $fn();
            if ($result !== null) {
                $findings[] = array_merge(['code' => $code], $result);
            }
        }

        // Score: cada check "ok" vale X pontos. Opcionais valem menos.
        $weights = [
            'C01' => 8, 'C02' => 6, 'C03' => 8, 'C04' => 10, 'C05' => 8,
            'C06' => 12, 'C07' => 6, 'C08' => 8, 'C09' => 10, 'C14' => 6, 'C15' => 6,
            'C10' => 3, 'C11' => 3, 'C12' => 3, 'C13' => 3,
        ];
        $maxScore = array_sum($weights);
        $failed = array_column($findings, 'code');
        $earnedScore = 0;
        foreach ($weights as $code => $w) {
            if (! in_array($code, $failed, true)) $earnedScore += $w;
        }
        $score = $maxScore > 0 ? (int) round(($earnedScore / $maxScore) * 100) : 0;

        return [
            'module'   => $moduleName,
            'score'    => $score,
            'findings' => $findings,
            'date'     => date('Y-m-d'),
            'critical' => count(array_filter($findings, fn ($f) => $f['level'] === 'critical')),
            'warning'  => count(array_filter($findings, fn ($f) => $f['level'] === 'warning')),
            'info'     => count(array_filter($findings, fn ($f) => $f['level'] === 'info')),
        ];
    }

    /**
     * Grava o relatório no audits/YYYY-MM-DD.md do módulo.
     */
    public function saveReport(array $result): ?string
    {
        $module = $result['module'];
        $folder = base_path("memory/requisitos/{$module}/audits");
        if (! File::isDirectory(dirname($folder))) return null;
        File::ensureDirectoryExists($folder);

        $path = $folder . DIRECTORY_SEPARATOR . $result['date'] . '.md';
        $md = $this->renderReport($result);
        File::put($path, $md);
        return $path;
    }

    protected function renderReport(array $r): string
    {
        $lines = [
            "# Auditoria · {$r['module']} · {$r['date']}",
            "",
            "- **Score**: {$r['score']}/100",
            "- **Issues**: {$r['critical']} critical · {$r['warning']} warning · {$r['info']} info",
            "",
            "## Findings",
            "",
        ];
        if (empty($r['findings'])) {
            $lines[] = "✓ Nenhuma issue — módulo em ordem.";
        } else {
            foreach ($r['findings'] as $f) {
                $emoji = match ($f['level']) {
                    'critical' => '🔴', 'warning' => '🟡', 'info' => 'ℹ️', default => '·',
                };
                $lines[] = "### {$emoji} {$f['code']} — " . strtoupper($f['level']);
                $lines[] = "";
                $lines[] = $f['message'];
                $lines[] = "";
            }
        }
        return implode("\n", $lines);
    }

    // ---------------- Checks ----------------

    protected function c01Frontmatter(array $d): ?array
    {
        $fm = $d['frontmatter'] ?? [];
        $missing = array_filter(['status', 'risk', 'migration_priority'], fn ($k) => empty($fm[$k]));
        if (empty($missing)) return null;
        return ['level' => 'warning', 'message' => 'Frontmatter incompleto — faltam: ' . implode(', ', $missing)];
    }

    protected function c02ReadmePurpose(array $d): ?array
    {
        if (empty($d['readme'])) return ['level' => 'warning', 'message' => 'README.md ausente ou vazio.'];
        $firstPara = trim(preg_replace('/^#+\s*.+?\n+/m', '', $d['readme'], 1));
        if (mb_strlen($firstPara) < 40) {
            return ['level' => 'warning', 'message' => 'README.md não tem parágrafo de propósito claro nas primeiras linhas.'];
        }
        return null;
    }

    protected function c03ArchitectureStack(array $d): ?array
    {
        $arch = $d['architecture'] ?? '';
        if (empty($arch)) return ['level' => 'warning', 'message' => 'ARCHITECTURE.md ausente.'];
        $hasStack = preg_match('/\b(Laravel|PHP|MySQL|Redis|React|Inertia|stack|camada)\b/i', $arch);
        if (! $hasStack) return ['level' => 'warning', 'message' => 'ARCHITECTURE.md não menciona stack/camadas (Laravel, PHP, MySQL, etc.).'];
        return null;
    }

    protected function c04SpecStories(array $d): ?array
    {
        $n = count($d['stories'] ?? []);
        if ($n === 0) return ['level' => 'warning', 'message' => 'SPEC.md sem user stories (US-XXX).'];
        return null;
    }

    protected function c05SpecRules(array $d): ?array
    {
        $n = count($d['rules'] ?? []);
        if ($n === 0) return ['level' => 'warning', 'message' => 'SPEC.md sem regras Gherkin (R-XXX).'];
        return null;
    }

    protected function c06RulesTest(array $d): ?array
    {
        $rules = $d['rules'] ?? [];
        if (empty($rules)) return null;
        $untested = array_filter($rules, fn ($r) => empty($r['testado_em']));
        if (! empty($untested)) {
            $ids = implode(', ', array_slice(array_column($untested, 'id'), 0, 5));
            $n = count($untested);
            return ['level' => 'critical', 'message' => "{$n} regras sem 'Testado em:': {$ids}" . ($n > 5 ? ', …' : '')];
        }
        return null;
    }

    protected function c07ChangelogVersioned(array $d): ?array
    {
        $chg = $d['changelog'] ?? '';
        if (empty($chg)) return ['level' => 'warning', 'message' => 'CHANGELOG.md ausente.'];
        if (! preg_match('/##\s+\[\d+\.\d+\.\d+\]\s+-\s+\d{4}-\d{2}-\d{2}/', $chg)) {
            return ['level' => 'info', 'message' => 'CHANGELOG.md não segue padrão [x.y.z] - YYYY-MM-DD.'];
        }
        return null;
    }

    protected function c08AdrsMinimum(array $d): ?array
    {
        $adrs = $d['adrs'] ?? [];
        $status = $d['frontmatter']['status'] ?? null;
        if ($status === 'ativo' && count($adrs) < 3) {
            return ['level' => 'warning', 'message' => 'Módulo ativo tem menos de 3 ADRs (atual: ' . count($adrs) . ').'];
        }
        return null;
    }

    protected function c09PagesAnnotated(string $moduleName): ?array
    {
        $pagesRoot = resource_path('js/Pages');
        if (! is_dir($pagesRoot)) return null;

        $modulePage = $pagesRoot . DIRECTORY_SEPARATOR . $this->inferPageFolder($moduleName);
        if (! is_dir($modulePage)) return null;

        $total = 0; $annotated = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modulePage, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.tsx')) continue;
            $total++;
            $head = @file_get_contents($f->getPathname(), false, null, 0, 2048);
            if ($head && str_contains($head, '@memcofre')) $annotated++;
        }

        if ($total === 0) return null;
        if ($annotated < $total) {
            return ['level' => 'warning', 'message' => "{$annotated}/{$total} telas .tsx com @memcofre — faltam " . ($total - $annotated) . "."];
        }
        return null;
    }

    protected function inferPageFolder(string $moduleName): string
    {
        // Convenção: MemCofre → MemCofre/, PontoWr2 → Ponto/, Essentials → Essentials/
        return match ($moduleName) {
            'PontoWr2' => 'Ponto',
            default => $moduleName,
        };
    }

    protected function c14ModuleStatusMatch(array $d, string $moduleName): ?array
    {
        $statusFile = base_path('modules_statuses.json');
        if (! File::exists($statusFile)) return null;
        $json = json_decode(File::get($statusFile), true);
        $active = $json[$moduleName] ?? null;
        $fmStatus = $d['frontmatter']['status'] ?? null;

        if ($active === true && $fmStatus !== 'ativo') {
            return ['level' => 'warning', 'message' => "modules_statuses.json diz 'true' mas frontmatter.status = '{$fmStatus}'."];
        }
        if ($active === false && $fmStatus === 'ativo') {
            return ['level' => 'warning', 'message' => "modules_statuses.json diz 'false' mas frontmatter.status = 'ativo' (incoerente)."];
        }
        return null;
    }

    protected function c15NoPlaceholders(array $d): ?array
    {
        $blobs = [
            'README'       => $d['readme'] ?? '',
            'ARCHITECTURE' => $d['architecture'] ?? '',
            'SPEC'         => $d['raw'] ?? '',
            'CHANGELOG'    => $d['changelog'] ?? '',
        ];
        $hits = [];
        foreach ($blobs as $k => $txt) {
            if (preg_match_all('/\[TODO[^\]]*\]|_\[[^\]]+\]_/', $txt, $m)) {
                $hits[$k] = count($m[0]);
            }
        }
        if (empty($hits)) return null;
        $msg = implode(', ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($hits), array_values($hits)));
        return ['level' => 'info', 'message' => "Placeholders não preenchidos detectados — {$msg}."];
    }

    protected function simpleCheck($value, string $code, string $level, string $msgWhenEmpty): ?array
    {
        if (empty($value)) return ['level' => $level, 'message' => $msgWhenEmpty];
        return null;
    }
}
