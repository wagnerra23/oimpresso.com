<?php

declare(strict_types=1);

namespace Modules\Jana\Ai;

/**
 * UiDeterministicScorer — porte PHP do regex de `prototipo-ui/audit/score-mechanized.mjs`.
 *
 * Onda 1 da auditoria LLM-judge → determinístico (dossiê
 * memory/sessions/2026-06-06-arte-llm-judge-para-deterministico.md · ADR 0255).
 *
 * 6 das 9 dimensões do PrUiJudgeAgent são DETERMINÍSTICAS — o próprio
 * score-mechanized.mjs já as computa por regex (R1-R9). Este scorer porta esses
 * regex pra PHP (mesma fonte de verdade, zero LLM, reproduzível, sem custo/viés/
 * flakiness) pra que o juiz LLM rode SÓ nas 3 dims genuinamente semânticas
 * (hierarquia_4_camadas · pt_01_slot_adherence · pt_br_voice_tone).
 *
 * Por que regex e não chamar `node score-mechanized.mjs`:
 *  - O job CI do PR UI Judge (pr-ui-judge.yml) só monta PHP, não Node → portar é
 *    menor risco que adicionar toolchain Node no workflow.
 *  - score-mechanized.mjs lê o ARQUIVO inteiro + ds-map do baseline; o PR Judge
 *    avalia só o DIFF do PR (linhas adicionadas). Aplicar o mesmo regex às linhas
 *    `+` do diff é o escopo honesto: julga o que o PR introduz.
 *
 * Mapeamento dim (PrUiJudgeAgent) ↔ regra (GOLDEN-REFERENCE):
 *   tokens_semanticos              ← R1 (cor crua: #hex · oklch/rgb/hsl)
 *   componentes_shared             ← R2 (elementos nativos <select>/<input>/<table>/<textarea>)
 *   localStorage_prefix_oimpresso  ← R3 (localStorage sem prefixo oimpresso.<modulo>.*)
 *   lucide_iconography_only        ← R4 (<svg> inline · import de icon-lib fora lucide)
 *   anti_padroes_ap1_ap8           ← composto (R1+R2+R3+R4+R6+R7) — a metade grep-ável dos AP
 *   atalhos_canonicos_jk_cmdk      ← presença de palette/hotkey canônico (cmdk · onKeyDown j/k)
 *
 * As 3 que NÃO entram aqui (ficam LLM): hierarquia_4_camadas, pt_01_slot_adherence,
 * pt_br_voice_tone — exigem juízo semântico que regex não captura.
 *
 * Os regex abaixo são CÓPIA FIEL de RX em score-mechanized.mjs (linhas 58-70).
 * Qualquer drift entre os dois é regressão — manter sincronizado.
 *
 * @see prototipo-ui/audit/score-mechanized.mjs (fonte canônica dos regex)
 * @see prototipo-ui/audit/GOLDEN-REFERENCE.md (as 10 regras R1-R10)
 * @see memory/sessions/2026-06-06-arte-llm-judge-para-deterministico.md (Onda 1)
 */
final class UiDeterministicScorer
{
    /**
     * As 6 dimensões determinísticas computadas aqui (na ordem do schema do agent).
     *
     * @var list<string>
     */
    public const DETERMINISTIC_DIMENSIONS = [
        'tokens_semanticos',
        'componentes_shared',
        'atalhos_canonicos_jk_cmdk',
        'localStorage_prefix_oimpresso',
        'lucide_iconography_only',
        'anti_padroes_ap1_ap8',
    ];

    /**
     * As 3 dimensões semânticas que continuam sob o juiz LLM.
     *
     * @var list<string>
     */
    public const SEMANTIC_DIMENSIONS = [
        'hierarquia_4_camadas',
        'pt_01_slot_adherence',
        'pt_br_voice_tone',
    ];

    /**
     * Pontua as 6 dimensões determinísticas a partir do diff do PR.
     *
     * Aplica os regex de score-mechanized.mjs SOMENTE às linhas adicionadas
     * (`+`) do diff — o escopo honesto: julga o que o PR introduz, não o
     * arquivo inteiro pré-existente.
     *
     * @return array<string, array{score:int, rationale:string}> dim => {score 0-10, rationale}
     */
    public function score(string $diff): array
    {
        $added = $this->addedLines($diff);

        $hits = $this->detect($added);

        return [
            'tokens_semanticos' => $this->dim(
                $hits['R1'],
                'Cor crua (R1): #hex · oklch/rgb/hsl literal',
                'Tokens semânticos OK — nenhuma cor crua nas linhas adicionadas (R1).',
            ),
            'componentes_shared' => $this->dim(
                $hits['R2'],
                'Elemento nativo (R2): use componente shared (Select/Input/Table/Textarea @/ui)',
                'Sem elemento nativo reinventado nas linhas adicionadas (R2).',
            ),
            'atalhos_canonicos_jk_cmdk' => $this->dimAtalhos($hits['shortcut'], $added),
            'localStorage_prefix_oimpresso' => $this->dim(
                $hits['R3'],
                'localStorage sem prefixo oimpresso.<modulo>.* (R3 · multi-tenant Tier 0)',
                'localStorage prefixado corretamente ou ausente (R3).',
            ),
            'lucide_iconography_only' => $this->dim(
                $hits['R4'],
                'Ícone fora lucide-react (R4): <svg> inline ou icon-lib externa',
                'Iconografia lucide-only respeitada nas linhas adicionadas (R4).',
            ),
            'anti_padroes_ap1_ap8' => $this->dimAntiPadroes($hits),
        ];
    }

    /**
     * Extrai as linhas adicionadas (`+`) de um diff unificado, sem o `+` inicial
     * e ignorando o header `+++ b/...`.
     */
    private function addedLines(string $diff): string
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $diff) ?: [] as $line) {
            if ($line === '' || $line[0] !== '+') {
                continue;
            }
            if (str_starts_with($line, '+++')) {
                continue;
            }
            $out[] = substr($line, 1);
        }

        return implode("\n", $out);
    }

    /**
     * Roda os regex de score-mechanized.mjs (RX, linhas 58-70). Cópia fiel.
     *
     * @return array{R1:list<string>, R2:list<string>, R3:list<string>, R4:list<string>, R6:list<string>, R7:list<string>, shortcut:list<string>}
     */
    private function detect(string $src): array
    {
        // R1 — cor crua. hex (exceto #fff/#000) + colorFn (oklch/rgb/hsl).
        $hex = $this->matchAll('/#[0-9a-fA-F]{3,8}\b/', $src);
        $hex = array_values(array_filter(
            $hex,
            fn (string $x): bool => preg_match('/^#(?:fff|ffffff|000|000000)$/i', $x) !== 1,
        ));
        $colorFn = $this->matchAll('/\b(?:oklch|rgba?|hsla?)\s*\(/', $src);

        // R2 — elementos nativos.
        $native = $this->matchAll('/<(?:select|input|textarea|table)[\s\/>]/', $src);

        // R3 — localStorage sem prefixo oimpresso.*.
        $r3 = [];
        if (preg_match_all('/localStorage\.(?:get|set|remove)Item\s*\(\s*[`\'"]([^`\'"]+)/', $src, $m)) {
            foreach ($m[1] as $key) {
                if (str_starts_with($key, 'oimpresso.')) {
                    continue;
                }
                // ${VAR}... — se a const resolve pra 'oimpresso.*' está OK.
                if (preg_match('/^\$\{(\w+)\}/', $key, $tv)) {
                    if (preg_match('/(?:const|let|var)\s+'.preg_quote($tv[1], '/').'\s*=\s*[\'"`]([^\'"`]+)/', $src, $d)
                        && str_starts_with($d[1], 'oimpresso.')) {
                        continue;
                    }
                }
                $r3[] = $key;
            }
        }

        // R4 — ícones fora lucide. <svg> inline + import de icon-lib externa.
        $svg = $this->matchAll('/<svg[\s\/>]/', $src);
        $iconLib = $this->matchAll('/from\s+[\'"](?:react-icons|@heroicons|@tabler\/icons|feather)/', $src);

        // R6 — emoji real (range astral). NÃO dingbats BMP (glyphs de UI).
        $emoji = $this->matchAll('/[\x{1F000}-\x{1FAFF}]/u', $src);

        // R7 — status com bg-fill (heurística ampla, baixa precisão — sinal).
        $statusFill = $this->matchAll('/\bbg-(?:red|rose|green|emerald|amber|yellow|orange|sky|blue|indigo|violet)-(?:50|100|200)\b/', $src);

        // atalhos — presença de palette/hotkey canônico (cmdk · onKeyDown j/k).
        $shortcut = array_merge(
            $this->matchAll('/\bcmdk\b/i', $src),
            $this->matchAll('/CommandPalette/', $src),
            $this->matchAll('/onKeyDown|useHotkeys|useKeyboard/', $src),
        );

        return [
            'R1' => array_merge($hex, $colorFn),
            'R2' => $native,
            'R3' => $r3,
            'R4' => array_merge($svg, $iconLib),
            'R6' => $emoji,
            'R7' => $statusFill,
            'shortcut' => $shortcut,
        ];
    }

    /**
     * @return list<string>
     */
    private function matchAll(string $pattern, string $src): array
    {
        if (preg_match_all($pattern, $src, $m)) {
            return $m[0];
        }

        return [];
    }

    /**
     * Dim binária: 10 se zero hits, 4 se houver (espelha o dedutor mecanizado
     * `peso×4` de score-mechanized — aqui normalizado pra 0-10 por dim).
     *
     * @param  list<string>  $hits
     * @return array{score:int, rationale:string}
     */
    private function dim(array $hits, string $failMsg, string $passMsg): array
    {
        if ($hits === []) {
            return ['score' => 10, 'rationale' => $passMsg];
        }

        $ev = $this->evidence($hits);

        return ['score' => 4, 'rationale' => "{$failMsg}. {$ev}"];
    }

    /**
     * Atalhos: dim informativa. Sem hotkey nas linhas adicionadas → neutro
     * (10, a maioria das telas não introduz atalho). Com hotkey → confirma
     * presença (10) — a detecção é de presença, não de violação. Mantida
     * determinística e conservadora: nunca penaliza, só registra evidência.
     *
     * @param  list<string>  $hits
     * @return array{score:int, rationale:string}
     */
    private function dimAtalhos(array $hits, string $added): array
    {
        if ($hits === []) {
            return ['score' => 10, 'rationale' => 'Nenhum atalho introduzido nas linhas adicionadas — neutro (atalho é opcional na PT-01).'];
        }

        return ['score' => 10, 'rationale' => 'Atalho/command-palette presente nas linhas adicionadas: '.$this->evidence($hits)];
    }

    /**
     * anti_padroes_ap1_ap8 — composto da metade grep-ável dos anti-padrões:
     * R1 (AP1 cor) · R2 (AP2 nativo) · R3 (AP3 localStorage) · R4 (AP4 ícone) ·
     * R6 (AP6 emoji) · R7 (AP7 status-fill). AP5 (gradient) e AP8 (PT-BR) são
     * julgados — não entram aqui.
     *
     * @param  array{R1:list<string>, R2:list<string>, R3:list<string>, R4:list<string>, R6:list<string>, R7:list<string>, shortcut:list<string>}  $hits
     * @return array{score:int, rationale:string}
     */
    private function dimAntiPadroes(array $hits): array
    {
        $failed = [];
        if ($hits['R1'] !== []) {
            $failed[] = 'AP1(cor)';
        }
        if ($hits['R2'] !== []) {
            $failed[] = 'AP2(nativo)';
        }
        if ($hits['R3'] !== []) {
            $failed[] = 'AP3(localStorage)';
        }
        if ($hits['R4'] !== []) {
            $failed[] = 'AP4(ícone)';
        }
        if ($hits['R6'] !== []) {
            $failed[] = 'AP6(emoji)';
        }
        if ($hits['R7'] !== []) {
            $failed[] = 'AP7(status-fill)';
        }

        if ($failed === []) {
            return ['score' => 10, 'rationale' => 'Nenhum anti-padrão grep-ável (AP1/2/3/4/6/7) nas linhas adicionadas.'];
        }

        // Quanto mais AP violados, menor a nota (10 → cai 2 por AP, piso 0).
        $score = max(0, 10 - count($failed) * 2);

        return ['score' => $score, 'rationale' => 'Anti-padrões grep-áveis detectados: '.implode(' · ', $failed).' (AP5 gradient + AP8 PT-BR ficam no juiz LLM).'];
    }

    /**
     * Resumo de evidência (espelha evidenceOf de score-mechanized): N× + amostra.
     *
     * @param  list<string>  $hits
     */
    private function evidence(array $hits): string
    {
        $uniq = array_slice(array_values(array_unique(array_map('strval', $hits))), 0, 4);

        return count($hits).'× — '.implode(' · ', $uniq);
    }
}
