<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;

/**
 * jana:index-regen — gate de integridade & priorização do índice mestre memory/INDEX.md.
 *
 * Origem: regressão 2026-05-29 (Wagner) — a Constituição (e NORTE-ROI, Protocolo Wagner,
 * Skills Tier A) sumiu da priorização do índice; contagens ficaram stale; links quebraram.
 * Estado-da-arte 2026 (Cognee) = índice auto-mantido. Este comando é o mínimo viável:
 *
 *   --check : VALIDA (CI-friendly). Exit 1 se algum doc Tier 0 sumiu do INDEX.md OU se há
 *             link relativo quebrado. Drift de contagem = info (não falha — contagens são ~).
 *   --fix   : reescreve as contagens conhecidas no INDEX.md com os números reais.
 *   (sem flag): relatório (Tier 0 + links + contagens reais vs declaradas).
 *
 * A lista Tier 0 é a mesma do bloco "LEI MÁXIMA" + "Norte/Protocolo/Skills" do INDEX.md.
 * Pareado com IndexIntegrityTest (Pest) que roda a MESMA validação no CI.
 */
class IndexRegenCommand extends Command
{
    protected $signature = 'jana:index-regen {--check : Só valida, exit 1 se Tier 0 sumiu ou link quebrado} {--fix : Reescreve contagens reais no INDEX.md}';

    protected $description = 'Gate de integridade/priorização do memory/INDEX.md (Tier 0 presentes + links + contagens)';

    /**
     * Docs Tier 0 / normativos-supremos que DEVEM estar linkados no índice mestre.
     * Caminhos relativos a memory/. Sumir daqui = regressão de priorização (falha).
     *
     * @var string[]
     */
    public const TIER0_DOCS = [
        'why-oimpresso.md',
        'what-oimpresso.md',
        'how-trabalhar.md',
        'proibicoes.md',
        'NORTE-ROI.md',
        'reference/PROTOCOLO-WAGNER-SEMPRE.md',
        '_INDEX-SECRETS.md',
        'governance/CONSTITUTION.md',
        'governance/ARCHITECTURE.md', // arc42 — "como o sistema funciona / escopo / responsabilidades" (2026-05-29)
        'decisions/0094-constituicao-v2-7-camadas-8-principios.md',
        'decisions/0093-multi-tenant-isolation-tier-0.md',
        'decisions/0095-skills-tiers-convencao-interna.md',
    ];

    public function handle(): int
    {
        $memoryDir = base_path('memory');
        $indexPath = $memoryDir.'/INDEX.md';

        if (! is_file($indexPath)) {
            $this->error("memory/INDEX.md não encontrado em {$indexPath}");

            return self::FAILURE;
        }

        $conteudo = (string) file_get_contents($indexPath);

        $missing = $this->tier0Faltando($conteudo);
        $quebrados = $this->linksQuebrados($conteudo, $memoryDir);
        $drift = $this->driftContagens($conteudo, $memoryDir);

        $this->info('Gate do índice mestre (memory/INDEX.md)');
        $this->line('  Tier 0 faltando : '.(empty($missing) ? '✓ nenhum' : count($missing)));
        foreach ($missing as $m) {
            $this->error("    ✗ AUSENTE no INDEX: {$m}");
        }
        $this->line('  Links quebrados : '.(empty($quebrados) ? '✓ nenhum' : count($quebrados)));
        foreach ($quebrados as $q) {
            $this->error("    ✗ link quebrado: {$q}");
        }
        $this->line('  Contagens (declarada → real):');
        foreach ($drift as $k => [$declarada, $real]) {
            $flag = ($declarada === $real) ? '✓' : '⚠';
            $this->line("    {$flag} {$k}: {$declarada} → {$real}");
        }

        if ($this->option('fix')) {
            $novo = $this->aplicarContagens($conteudo, $drift);
            if ($novo !== $conteudo) {
                file_put_contents($indexPath, $novo);
                $this->info('  contagens reescritas no INDEX.md');
            } else {
                $this->line('  nada a reescrever');
            }
        }

        // Hard fail: Tier 0 sumiu OU link quebrado. Drift de contagem NÃO falha (é ~).
        if ($this->option('check') && (! empty($missing) || ! empty($quebrados))) {
            $this->newLine();
            $this->error('GATE FALHOU — corrija Tier 0 ausente / links quebrados antes do merge.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Docs Tier 0 que NÃO aparecem (como substring) no índice. Lista vazia = OK.
     *
     * @return string[]
     */
    public function tier0Faltando(string $conteudoIndex): array
    {
        $faltando = [];
        foreach (self::TIER0_DOCS as $doc) {
            if (! str_contains($conteudoIndex, $doc)) {
                $faltando[] = $doc;
            }
        }

        return $faltando;
    }

    /**
     * Links markdown relativos do índice que apontam pra arquivo/pasta inexistente.
     * Ignora http(s), âncoras (#) e mailto. Resolve relativo a memory/ (dir do INDEX.md).
     *
     * @return string[]  "alvo" de cada link quebrado
     */
    public function linksQuebrados(string $conteudoIndex, string $memoryDir): array
    {
        $quebrados = [];
        if (! preg_match_all('/\]\(([^)]+)\)/', $conteudoIndex, $m)) {
            return [];
        }

        foreach ($m[1] as $alvo) {
            $alvo = trim($alvo);
            // Ignora externos, âncoras puras e mailto.
            if ($alvo === '' || str_starts_with($alvo, 'http') || str_starts_with($alvo, '#') || str_starts_with($alvo, 'mailto:')) {
                continue;
            }
            // Remove âncora (#secao) do final.
            $caminho = preg_replace('/#.*$/', '', $alvo) ?? $alvo;
            if ($caminho === '') {
                continue;
            }

            $abs = $this->resolver($memoryDir, $caminho);
            if (! file_exists($abs)) {
                $quebrados[] = $alvo;
            }
        }

        return array_values(array_unique($quebrados));
    }

    /**
     * Resolve um caminho relativo (que pode ter ../) a partir de $base.
     */
    private function resolver(string $base, string $rel): string
    {
        // Normaliza separadores: em Windows base_path() retorna backslash
        // (D:\oimpresso.com\memory). Sem normalizar, explode('/') deixa o path
        // inteiro como UM segmento e o `..` popa a raiz toda em vez de subir 1 dir.
        $base = str_replace('\\', '/', $base);
        $rel = str_replace('\\', '/', $rel);
        $rel = rtrim($rel, '/');
        $partes = explode('/', $base.'/'.$rel);
        $pilha = [];
        foreach ($partes as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }
            if ($p === '..') {
                array_pop($pilha);

                continue;
            }
            $pilha[] = $p;
        }

        // Preserva raiz unix ("/...") — em Windows os caminhos têm drive (D:), sem barra inicial.
        $prefix = str_starts_with($base, '/') ? '/' : '';

        return $prefix.implode('/', $pilha);
    }

    /**
     * Contagens declaradas no INDEX vs reais no filesystem.
     *
     * @return array<string, array{0:int,1:int}>  chave => [declarada, real]
     */
    public function driftContagens(string $conteudoIndex, string $memoryDir): array
    {
        $real = [
            'ADRs'     => $this->contar(glob($memoryDir.'/decisions/[0-9][0-9][0-9][0-9]-*.md') ?: []),
            'handoffs' => $this->contar(glob($memoryDir.'/handoffs/*.md') ?: [], '_'),
            'sessions' => $this->contar(glob($memoryDir.'/sessions/20*.md') ?: []),
            'docs'     => count($this->todosMd($memoryDir)),
        ];

        $declarada = [
            'ADRs'     => $this->parseNum($conteudoIndex, '/\(~?(\d+)\s+ADRs\)/'),
            'handoffs' => $this->parseNum($conteudoIndex, '/~?(\d+)\s+handoffs/'),
            'sessions' => $this->parseNum($conteudoIndex, '/~?(\d+)\s+session logs/'),
            'docs'     => $this->parseNum($conteudoIndex, '/navegável\s+\(~?([\d.]+)\s+docs\)/'),
        ];

        $out = [];
        foreach ($real as $k => $r) {
            $out[$k] = [$declarada[$k], $r];
        }

        return $out;
    }

    /** @param string[] $arquivos */
    private function contar(array $arquivos, ?string $ignorarPrefixo = null): int
    {
        $n = 0;
        foreach ($arquivos as $f) {
            $base = basename($f);
            if ($ignorarPrefixo !== null && str_starts_with($base, $ignorarPrefixo)) {
                continue;
            }
            $n++;
        }

        return $n;
    }

    /** @return string[] */
    private function todosMd(string $dir): array
    {
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        $out = [];
        foreach ($rii as $f) {
            if ($f instanceof \SplFileInfo && $f->isFile() && $f->getExtension() === 'md') {
                $out[] = $f->getPathname();
            }
        }

        return $out;
    }

    private function parseNum(string $conteudo, string $regex): int
    {
        if (preg_match($regex, $conteudo, $m)) {
            return (int) str_replace(['.', ','], '', $m[1]);
        }

        return 0;
    }

    /**
     * Reescreve as contagens conhecidas no texto do índice (modo --fix).
     *
     * @param array<string, array{0:int,1:int}> $drift
     */
    private function aplicarContagens(string $conteudo, array $drift): string
    {
        $docs = $drift['docs'][1] ?? 0;
        $adrs = $drift['ADRs'][1] ?? 0;
        $hand = $drift['handoffs'][1] ?? 0;
        $sess = $drift['sessions'][1] ?? 0;

        $conteudo = preg_replace('/(navegável\s+\(~?)[\d.]+(\s+docs\))/', '${1}'.$this->aprox($docs).'$2', $conteudo) ?? $conteudo;
        $conteudo = preg_replace('/(\()~?\d+(\s+ADRs\))/', '${1}~'.$adrs.'$2', $conteudo) ?? $conteudo;
        $conteudo = preg_replace('/~?\d+(\s+handoffs)/', '~'.$hand.'$1', $conteudo) ?? $conteudo;
        $conteudo = preg_replace('/~?\d+(\s+session logs)/', '~'.$sess.'$1', $conteudo) ?? $conteudo;

        return $conteudo;
    }

    private function aprox(int $n): string
    {
        // arredonda pra centena mais próxima e formata com ponto de milhar (~2.300)
        $r = (int) (round($n / 100) * 100);

        return '~'.number_format($r, 0, ',', '.');
    }
}
