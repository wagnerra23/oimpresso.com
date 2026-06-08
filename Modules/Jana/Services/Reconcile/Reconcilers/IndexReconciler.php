<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Reconcile\Reconcilers;

use Modules\Jana\Contracts\Reconciler;
use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\Reconcile\ReconcileResult;
use Symfony\Component\Yaml\Yaml;

/**
 * IndexReconciler — cura a poluição dos índices mantidos à mão (ADR 0237).
 *
 * Problema (P0): índices curados à mão DRIFTAM do git e o MCP + Claude Design
 * consomem o drift como verdade. Casos reais medidos 2026-05-30 neste repo:
 *   - `_INDEX-LIFECYCLE.md` frontmatter `total_adrs: 119` / `unique_numbers: 116`,
 *     mas o disco tem 238 arquivos `NNNN-*.md` / 226 números únicos (contagem stale);
 *   - `numbering_collisions` já apodreceu antes (5 de 11 colisões reais ausentes);
 *   - `INDEX-DESIGN-MEMORIAS.md` já carregou link stale apontando pra
 *     `proposals/governanca-evolucao-doc-design.md` (virou o ADR aceito 0236).
 *
 * Este Reconciler é o `--check`/`heal` runtime das 3 lógicas de detecção que hoje
 * vivem como teste (AdrNumberCollisionTest + DesignIndexSingleSourceTest) — só que
 * agora CURA o que é seguro, em vez de só falhar o CI.
 *
 * 3 facetas (desired = git/disco · observed = arquivo de índice):
 *
 *   1. `_INDEX-LIFECYCLE.md` › `numbering_collisions`
 *        desired  = números que REALMENTE colidem no disco (`NNNN-*.md` ≥2 arquivos);
 *        observed = a lista declarada no frontmatter.
 *        healable=TRUE → heal reescreve a lista (campo COMPUTÁVEL de um doc `type:index`,
 *        não ADR — pode reescrever a parte derivada; nunca toca a prosa curada à mão).
 *
 *   2. `_INDEX-LIFECYCLE.md` › `total_adrs` / `unique_numbers`
 *        desired  = contagem real no disco (arquivos / números distintos);
 *        observed = os escalares no frontmatter.
 *        healable=TRUE → heal reescreve os escalares (campos puramente derivados do disco).
 *
 *   3. `INDEX-DESIGN-MEMORIAS.md` › links markdown LOCAIS
 *        desired  = todo link local resolve (arquivo destino existe);
 *        observed = links cujo destino sumiu.
 *        healable=FALSE → só ALERTA (R10: pra onde repontar é decisão humana).
 *
 * NÃO reconcilia a contagem `**Total:** N módulos` de `modulos/INDEX.md`: aquele número
 * é derivado de `module:specs` varrendo MÚLTIPLAS branches do git (cross-branch), não
 * reproduzível read-only a partir do disco — heal a partir de uma contagem de arquivos
 * daria um número ERRADO. Fora de escopo por honestidade de fonte-de-verdade.
 *
 * Invariantes (ADR 0237 / 0230):
 *   - Núcleo PURO injetável: {@see analisar()} recebe desired/observed e devolve drifts
 *     SEM tocar disco — determinístico, testável sem I/O (padrão DeployDriftChecker::analisar
 *     / DesignDocsFreshnessChecker::analisarDoc).
 *   - Idempotência REAL: heal reescreve só o campo computável (regex cirúrgico) e só quando
 *     o valor difere → rodar 2× = mesmo arquivo + a 2ª run CONVERGE (drift some).
 *   - Cura HONESTA: `healed=true` SÓ quando a reescrita de fato mudou o conteúdo daquele
 *     alvo. Se o regex no-opa (chave ausente no frontmatter — não dá pra inserir via
 *     replace; `numbering_collisions` em BLOCK list; trailer inesperado) o drift fica
 *     `healed=false` (detectou mas NÃO curou) e reaparece — nunca finge cura.
 *   - Cura segura só: drift com fonte-de-verdade clara (contagem/colisão = disco) cura;
 *     link quebrado (ambíguo) alerta humano.
 *   - Repo-wide: índices não têm business_id (multi-tenant N/A).
 *
 * Refs:
 *   - ADR 0237 (jana:reconcile loop único — mãe)
 *   - ADR 0028 (numeração monotônica de ADR)
 *   - ADR 0236 (índice de design = fonte única, zero link órfão)
 *   - tests/Feature/Memory/AdrNumberCollisionTest.php (lógica de colisão espelhada)
 *   - tests/Feature/Design/DesignIndexSingleSourceTest.php (lógica de link espelhada)
 */
final class IndexReconciler implements Reconciler
{
    /** Frontmatter de lifecycle das ADRs (single source of truth de lifecycle). */
    private const LIFECYCLE_INDEX = 'memory/decisions/_INDEX-LIFECYCLE.md';

    /** Diretório-raiz das ADRs canon (glob NÃO recursivo — proposals/ fica de fora de propósito). */
    private const ADR_DIR = 'memory/decisions';

    /** Índice-mestre dos docs de design. */
    private const DESIGN_INDEX = 'memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md';

    /** Diretório do índice de design (resolução dos links relativos — regra ADR 0236). */
    private const DESIGN_INDEX_DIR = 'memory/requisitos/_DesignSystem';

    // ── Chaves canônicas dos drifts (target prefix) ──────────────────────────
    private const T_COLLISIONS = 'lifecycle.numbering_collisions';
    private const T_TOTAL_ADRS = 'lifecycle.total_adrs';
    private const T_UNIQUE_NUMS = 'lifecycle.unique_numbers';
    private const T_DESIGN_LINK = 'design_index.link';

    private readonly string $basePath;

    public function __construct(?string $basePath = null)
    {
        // base_path() injetável pra teste apontar num tmpdir sem bootar a app.
        $this->basePath = rtrim($basePath ?? base_path(), "/\\");
    }

    public function name(): string
    {
        return 'index';
    }

    public function description(): string
    {
        return 'Cura índices à mão que driftam do git (numbering_collisions, contagens ADR, links de design quebrados)';
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tier_0', 'governance', 'docs', 'index'];
    }

    public function reconcile(array $opts = []): ReconcileResult
    {
        $start = microtime(true);

        $heal = ($opts['heal'] ?? false) === true;
        $dryRun = ($opts['dry_run'] ?? false) === true;

        $desired = $this->coletarDesired();
        $observed = $this->coletarObserved();

        $drifts = $this->analisar($desired, $observed);

        // Cura: aplica só o que é healable, e só quando heal=true E NÃO dry_run.
        // dry_run mostra o que CURARIA (healed continua false), não escreve.
        if ($heal && ! $dryRun && $drifts !== []) {
            $drifts = $this->curar($drifts, $desired);
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        return ReconcileResult::from(
            name: $this->name(),
            drifts: $drifts,
            durationMs: $durationMs,
            metadata: [
                'heal' => $heal,
                'dry_run' => $dryRun,
                'lifecycle_index' => self::LIFECYCLE_INDEX,
                'design_index' => self::DESIGN_INDEX,
            ],
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NÚCLEO PURO — sem I/O. Recebe desired/observed e devolve os drifts.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compara o estado desejado (git/disco) com o observado (índice) e devolve os
     * drifts. PURO + determinístico: nada de disco/rede/DB aqui — tudo injetado.
     *
     * Forma esperada de $desired:
     *   - collisions:   list<string>  números de 4 díg que colidem no disco (ordenado)
     *   - total_adrs:   int           # de arquivos NNNN-*.md no disco
     *   - unique_numbers:int          # de números distintos no disco
     *   - design_links_present: list<string>  links locais cujo destino EXISTE
     *
     * Forma esperada de $observed:
     *   - collisions:   list<string>  números declarados em numbering_collisions
     *   - total_adrs:   int|null      escalar declarado no frontmatter (null = ausente)
     *   - unique_numbers:int|null     escalar declarado no frontmatter (null = ausente)
     *   - design_links_broken: list<string>  links locais cujo destino SUMIU
     *
     * @param array{
     *     collisions?: list<string>,
     *     total_adrs?: int,
     *     unique_numbers?: int,
     *     design_links_present?: list<string>
     * } $desired
     * @param array{
     *     collisions?: list<string>,
     *     total_adrs?: int|null,
     *     unique_numbers?: int|null,
     *     design_links_broken?: list<string>
     * } $observed
     * @return array<int, ReconcileDrift>
     */
    public function analisar(array $desired, array $observed): array
    {
        $drifts = [];

        // ── Faceta 1: numbering_collisions (healable) ──────────────────────────
        $desiredCol = $this->normalizarColisoes($desired['collisions'] ?? []);
        $observedCol = $this->normalizarColisoes($observed['collisions'] ?? []);
        if ($desiredCol !== $observedCol) {
            $faltando = array_values(array_diff($desiredCol, $observedCol)); // colisão real não-registrada
            $orfas = array_values(array_diff($observedCol, $desiredCol));    // entrada stale/órfã
            $drifts[] = new ReconcileDrift(
                target: self::T_COLLISIONS,
                detail: $this->detalheColisoes($faltando, $orfas),
                desired: '['.implode(', ', $desiredCol).']',
                observed: '['.implode(', ', $observedCol).']',
                healable: true,
            );
        }

        // ── Faceta 2: contagens total_adrs / unique_numbers (healable) ─────────
        $desiredTotal = (int) ($desired['total_adrs'] ?? 0);
        $observedTotal = $observed['total_adrs'] ?? null;
        if ($observedTotal === null || (int) $observedTotal !== $desiredTotal) {
            $drifts[] = new ReconcileDrift(
                target: self::T_TOTAL_ADRS,
                detail: 'Contagem stale de ADRs no frontmatter `total_adrs` (# de arquivos NNNN-*.md no disco).',
                desired: (string) $desiredTotal,
                observed: $observedTotal === null ? '(ausente)' : (string) $observedTotal,
                healable: true,
            );
        }

        $desiredUnique = (int) ($desired['unique_numbers'] ?? 0);
        $observedUnique = $observed['unique_numbers'] ?? null;
        if ($observedUnique === null || (int) $observedUnique !== $desiredUnique) {
            $drifts[] = new ReconcileDrift(
                target: self::T_UNIQUE_NUMS,
                detail: 'Contagem stale de números únicos no frontmatter `unique_numbers` (# de números distintos no disco).',
                desired: (string) $desiredUnique,
                observed: $observedUnique === null ? '(ausente)' : (string) $observedUnique,
                healable: true,
            );
        }

        // ── Faceta 3: links de design quebrados (NÃO-healable → alerta) ────────
        // desired = links que resolvem (presentes); observed = links que sumiram.
        // Um link quebrado é drift por definição: o destino deveria existir e não existe.
        foreach ($this->normalizarLinks($observed['design_links_broken'] ?? []) as $linkQuebrado) {
            $drifts[] = new ReconcileDrift(
                target: self::T_DESIGN_LINK.':'.$linkQuebrado,
                detail: 'Link local do índice de design não resolve (arquivo destino sumiu). '
                    .'Repontar/criar destino é decisão humana (ADR 0236).',
                desired: 'destino existe',
                observed: 'destino ausente: '.$linkQuebrado,
                healable: false,
            );
        }

        return $drifts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CURA — idempotente + cirúrgica. Reescreve só o campo computável.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Aplica a cura nos drifts healable e marca `healed=true` SÓ no que a reescrita
     * realmente mudou. Os não-healable passam intactos (continuam só-alerta).
     *
     * Honestidade da cura (ADR 0237, R10): cada `reescrever*` reporta se de fato
     * casou+mudou o conteúdo daquele alvo. Se o regex no-opa (chave ausente no
     * frontmatter, `numbering_collisions` em BLOCK list YAML em vez de inline `[...]`,
     * trailer inesperado), o drift continua `healed=false` — detectou mas NÃO curou,
     * e REAPARECE na próxima run (em vez de fingir cura e entrar em loop infinito de
     * "curei mas não curei"). Inserir uma chave que não existe NÃO é tarefa de
     * regex-replace cirúrgico → fica honestamente não-curado.
     *
     * Idempotente: reescreve apenas o campo derivado via regex cirúrgico, e só
     * quando o valor difere → rodar 2× = mesmo arquivo + 2ª run converge (drift some).
     *
     * @param array<int, ReconcileDrift> $drifts
     * @param array{collisions?: list<string>, total_adrs?: int, unique_numbers?: int} $desired
     * @return array<int, ReconcileDrift>
     */
    private function curar(array $drifts, array $desired): array
    {
        $lifecyclePath = $this->path(self::LIFECYCLE_INDEX);
        $conteudo = $this->lerArquivo($lifecyclePath);

        // Sem o arquivo de lifecycle não há o que curar (os healable vivem nele).
        if ($conteudo === null) {
            return $drifts;
        }

        $novo = $conteudo;
        $alvosCurados = [];

        foreach ($drifts as $drift) {
            if (! $drift->healable) {
                continue;
            }

            // Cada reescrita devolve o conteúdo + se mudou ESTE alvo. Só marca
            // healed quando mudou de fato (regex casou e o valor era diferente).
            if ($drift->target === self::T_COLLISIONS) {
                [$novo, $mudou] = $this->reescreverColisoes($novo, $this->normalizarColisoes($desired['collisions'] ?? []));
            } elseif ($drift->target === self::T_TOTAL_ADRS) {
                [$novo, $mudou] = $this->reescreverEscalar($novo, 'total_adrs', (int) ($desired['total_adrs'] ?? 0));
            } elseif ($drift->target === self::T_UNIQUE_NUMS) {
                [$novo, $mudou] = $this->reescreverEscalar($novo, 'unique_numbers', (int) ($desired['unique_numbers'] ?? 0));
            } else {
                continue;
            }

            if ($mudou) {
                $alvosCurados[$drift->target] = true;
            }
        }

        if ($novo !== $conteudo) {
            $this->escreverArquivo($lifecyclePath, $novo);
        }

        // Marca healed=true só nos alvos cuja reescrita efetivamente mudou o conteúdo.
        return array_map(
            static fn (ReconcileDrift $d): ReconcileDrift => isset($alvosCurados[$d->target])
                ? new ReconcileDrift(
                    target: $d->target,
                    detail: $d->detail,
                    desired: $d->desired,
                    observed: $d->observed,
                    healable: $d->healable,
                    healed: true,
                )
                : $d,
            $drifts,
        );
    }

    /**
     * Reescreve a linha `numbering_collisions: [...]` do frontmatter com a lista
     * desejada (já normalizada — zero-pad de 4 díg, ordenada). Cirúrgico: casa SÓ
     * a linha da chave (até o `]`), preserva qualquer comentário `# ...`/`\r` (CRLF)
     * após o colchete (prosa curada à mão). Idempotente.
     *
     * Devolve [conteúdo, mudou?]. `mudou=false` (no-op honesto) quando:
     *   - a chave não existe como lista inline `[...]` (ex: BLOCK list YAML `- 0101`
     *     ou chave ausente) → regex não casa, nada a inserir via replace;
     *   - já está no valor desejado (idempotência) → casa mas conteúdo não muda.
     * Em ambos o drift fica `healed=false` — detectou mas não fingiu cura.
     *
     * @param list<string> $cols números de colisão normalizados (ex: ['0101', '0170'])
     * @return array{0: string, 1: bool} [conteúdo resultante, se mudou de fato]
     */
    private function reescreverColisoes(string $conteudo, array $cols): array
    {
        $render = '['.implode(', ', $cols).']';

        // Captura: (1) "numbering_collisions:" + espaços  (2) o array [...]  (3) trailer (comentário + `\r` do CRLF).
        // `.` casa `\r` (não casa só `\n`) → o trailer absorve o CR e a cura preserva CRLF.
        $regex = '/^(\h*numbering_collisions:\h*)\[[^\]]*\](.*)$/m';
        $resultado = preg_replace_callback(
            $regex,
            static fn (array $m): string => $m[1].$render.$m[2],
            $conteudo,
            1,
        );

        // preg_replace_callback devolve null só em erro de PCRE (regex inválido) — guarda mixed.
        if ($resultado === null) {
            return [$conteudo, false];
        }

        return [$resultado, $resultado !== $conteudo];
    }

    /**
     * Reescreve um escalar inteiro do frontmatter (`total_adrs: N` / `unique_numbers: N`)
     * com o valor desejado, preservando comentário inline + `\r` (CRLF). Cirúrgico + idempotente.
     *
     * CRLF-tolerante: o trailer é `(.*)` (casa `\r`, comentário, etc.) em vez do antigo
     * `(\h*(?:#.*)?)`, onde `\r` NÃO estava em `\h` → em arquivos CRLF o `$` (que casa
     * antes do `\n`) ficava encalhado no `\r` e o escalar NUNCA curava (mas era contado
     * como healed). O trailer absorve o CR e a cura preserva o CRLF (não força LF).
     *
     * Devolve [conteúdo, mudou?]. `mudou=false` (no-op honesto) quando a chave não
     * existe (regex não casa — não dá pra inserir via replace) ou já está no valor
     * desejado (idempotência). Em ambos o drift fica `healed=false`.
     *
     * @return array{0: string, 1: bool} [conteúdo resultante, se mudou de fato]
     */
    private function reescreverEscalar(string $conteudo, string $chave, int $valor): array
    {
        // (1) "chave:" + espaços  (2) o número  (3) trailer (comentário + `\r` do CRLF).
        // `\d+` garante boundary à direita (não casa substring); `(.*)$` preserva CRLF.
        $regex = '/^(\h*'.preg_quote($chave, '/').':\h*)\d+(.*)$/m';
        $resultado = preg_replace_callback(
            $regex,
            static fn (array $m): string => $m[1].$valor.($m[2] ?? ''),
            $conteudo,
            1,
        );

        // preg_replace_callback devolve null só em erro de PCRE — guarda mixed.
        if ($resultado === null) {
            return [$conteudo, false];
        }

        return [$resultado, $resultado !== $conteudo];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COLETA DE ESTADO (I/O) — desired do disco, observed do índice.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array{
     *     collisions: list<string>,
     *     total_adrs: int,
     *     unique_numbers: int,
     *     design_links_present: list<string>
     * }
     */
    private function coletarDesired(): array
    {
        $porNumero = $this->adrNumerosParaArquivos();

        $colisoes = [];
        foreach ($porNumero as $numero => $arquivos) {
            if (count($arquivos) >= 2) {
                $colisoes[] = $numero;
            }
        }
        $colisoes = $this->normalizarColisoes($colisoes);

        $totalAdrs = 0;
        foreach ($porNumero as $arquivos) {
            $totalAdrs += count($arquivos);
        }

        [$presentes] = $this->classificarLinksDesign();

        return [
            'collisions' => $colisoes,
            'total_adrs' => $totalAdrs,
            'unique_numbers' => count($porNumero),
            'design_links_present' => $presentes,
        ];
    }

    /**
     * @return array{
     *     collisions: list<string>,
     *     total_adrs: int|null,
     *     unique_numbers: int|null,
     *     design_links_broken: list<string>
     * }
     */
    private function coletarObserved(): array
    {
        $fm = $this->lerFrontmatter($this->path(self::LIFECYCLE_INDEX));

        $collisions = [];
        $rawCol = $fm['numbering_collisions'] ?? null;
        if (is_array($rawCol)) {
            $collisions = $this->normalizarColisoes($rawCol);
        }

        $total = isset($fm['total_adrs']) && is_scalar($fm['total_adrs']) ? (int) $fm['total_adrs'] : null;
        $unique = isset($fm['unique_numbers']) && is_scalar($fm['unique_numbers']) ? (int) $fm['unique_numbers'] : null;

        [, $quebrados] = $this->classificarLinksDesign();

        return [
            'collisions' => $collisions,
            'total_adrs' => $total,
            'unique_numbers' => $unique,
            'design_links_broken' => $quebrados,
        ];
    }

    /**
     * Mapeia número de 4 díg → arquivos `NNNN-*.md` no nível raiz de memory/decisions/.
     * Espelha adrNumerosParaArquivos() do AdrNumberCollisionTest (glob não-recursivo;
     * só casa `^(\d{4})-`; `_INDEX`/`_SCHEMA`/`_TEMPLATE`/README descartados).
     *
     * @return array<string, list<string>>
     */
    private function adrNumerosParaArquivos(): array
    {
        $glob = glob($this->path(self::ADR_DIR).'/*.md');
        $arquivos = $glob === false ? [] : $glob;

        $porNumero = [];
        foreach ($arquivos as $path) {
            $nome = basename($path, '.md');
            if (preg_match('/^(\d{4})-.+$/', $nome, $m) !== 1) {
                continue;
            }
            $porNumero[$m[1]][] = $nome;
        }

        ksort($porNumero);

        return $porNumero;
    }

    /**
     * Classifica os links markdown LOCAIS do índice de design em [presentes, quebrados].
     * Espelha a lógica do DesignIndexSingleSourceTest (resolve relativo ao DIR do índice;
     * ignora http(s)/mailto/âncora; corta título e fragmento).
     *
     * @return array{0: list<string>, 1: list<string>}  [presentes, quebrados]
     */
    private function classificarLinksDesign(): array
    {
        $conteudo = $this->lerArquivo($this->path(self::DESIGN_INDEX));
        if ($conteudo === null) {
            return [[], []];
        }

        $indexDir = $this->path(self::DESIGN_INDEX_DIR);

        if (preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $conteudo, $matches) === false) {
            return [[], []];
        }
        // Grupo 2 (o alvo do link) sempre presente após match bem-sucedido.
        $alvos = $matches[2];

        $presentes = [];
        $quebrados = [];
        foreach ($alvos as $rawTarget) {
            $target = trim($rawTarget);

            if (preg_match('#^(https?:)//#i', $target) === 1
                || str_starts_with($target, 'mailto:')
                || str_starts_with($target, '#')) {
                continue;
            }

            // Corta ` "title"` e `#ancora`.
            $semTitulo = preg_split('/\s+/', $target, 2);
            $target = ($semTitulo !== false && isset($semTitulo[0])) ? $semTitulo[0] : $target;
            $semAncora = explode('#', $target, 2);
            $target = $semAncora[0];
            $target = trim($target);
            if ($target === '') {
                continue;
            }

            $candidate = $this->normalizarCaminho($indexDir.'/'.$target);
            if (file_exists($candidate)) {
                $presentes[] = $rawTarget;
            } else {
                $quebrados[] = $rawTarget;
            }
        }

        return [array_values(array_unique($presentes)), array_values(array_unique($quebrados))];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS PUROS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Normaliza uma lista de "números de colisão" (mixed do YAML: int 101, string '0101',
     * etc.) pra string de 4 díg com zero-pad, deduplicada e ordenada. Espelha
     * colisoesRegistradas() do AdrNumberCollisionTest.
     *
     * @param array<int, mixed> $valores
     * @return list<string>
     */
    private function normalizarColisoes(array $valores): array
    {
        $out = [];
        foreach ($valores as $valor) {
            $bruto = is_scalar($valor) ? (string) $valor : '';
            $digitos = preg_replace('/\D/', '', $bruto) ?? '';
            if ($digitos === '') {
                continue;
            }
            $out[] = sprintf('%04d', (int) $digitos);
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * @param array<int, mixed> $valores
     * @return list<string>
     */
    private function normalizarLinks(array $valores): array
    {
        $out = [];
        foreach ($valores as $valor) {
            if (is_string($valor) && $valor !== '') {
                $out[] = $valor;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * @param list<string> $faltando colisões reais não-registradas
     * @param list<string> $orfas entradas registradas que não colidem mais
     */
    private function detalheColisoes(array $faltando, array $orfas): string
    {
        $partes = [];
        if ($faltando !== []) {
            $partes[] = 'colisões reais NÃO registradas: '.implode(', ', $faltando);
        }
        if ($orfas !== []) {
            $partes[] = 'entradas órfãs/stale (não colidem no disco): '.implode(', ', $orfas);
        }
        $base = 'numbering_collisions diverge do disco (ADR 0028)';

        return $partes === [] ? $base.'.' : $base.' — '.implode(' · ', $partes).'.';
    }

    /**
     * Normaliza caminho resolvendo `.`/`..` SEM tocar o disco (slash-agnóstico
     * Windows/Unix). Espelha designNormalizePath() do DesignIndexSingleSourceTest.
     */
    private function normalizarCaminho(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $isAbsolute = str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path) === 1;

        $segments = explode('/', $path);
        $out = [];
        foreach ($segments as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                if ($out !== [] && end($out) !== '..') {
                    array_pop($out);
                } elseif (! $isAbsolute) {
                    $out[] = '..';
                }

                continue;
            }
            $out[] = $seg;
        }

        $prefix = ($isAbsolute && str_starts_with($path, '/')) ? '/' : '';

        return $prefix.implode('/', $out);
    }

    /** Caminho absoluto a partir de um relativo ao base. */
    private function path(string $rel): string
    {
        return $this->basePath.'/'.$rel;
    }

    /** Lê arquivo guardando o `false` do file_get_contents. Null = ausente/ilegível. */
    private function lerArquivo(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }
        $conteudo = file_get_contents($path);

        return $conteudo === false ? null : $conteudo;
    }

    private function escreverArquivo(string $path, string $conteudo): void
    {
        file_put_contents($path, $conteudo);
    }

    /**
     * Lê + parseia o frontmatter YAML de um arquivo. Tolerante: array vazio se
     * ausente/ilegível/inválido (mesmo contrato defensivo do AdrNumberCollisionTest).
     *
     * @return array<string, mixed>
     */
    private function lerFrontmatter(string $path): array
    {
        $conteudo = $this->lerArquivo($path);
        if ($conteudo === null) {
            return [];
        }

        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $conteudo, $m) !== 1) {
            return [];
        }

        try {
            $parsed = Yaml::parse($m[1]);
        } catch (\Throwable) {
            return [];
        }

        return is_array($parsed) ? $parsed : [];
    }
}
