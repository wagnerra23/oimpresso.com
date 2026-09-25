---
sessao: "02"
titulo: "5 charters do modulo: related_prototype -> cowork/Wagner"
autor: "[CL]"
data: "2026-09-25"
base: "origin/main 45a687387 (#7979 mergeado 2026-09-25T20:27:45Z)"
---
# _saida-02 · Charters apontam `cowork/Wagner`

## O que mudou
Nos 5 charters de `resources/js/Pages/Manufacturing/`, `related_prototype` trocou de `prototipo-ui/cowork/Felipe/<arq>` para `prototipo-ui/cowork/Wagner/<arq>` (mesmo basename, conferido por script antes de escrever). As duas linhas de comentario de 2026-09-22 ficaram como historico; entrou uma terceira, datada: `# 2026-09-25 [W]: fonte unica = cowork/Wagner (D-MFG-FONTE)`.

| tela | antes | depois |
|---|---|---|
| Index | Felipe/manufacturing-producao.jsx | Wagner/manufacturing-producao.jsx |
| Recipes | Felipe/manufacturing-page.jsx | Wagner/manufacturing-page.jsx |
| Insumos | Felipe/manufacturing-insumos.jsx | Wagner/manufacturing-insumos.jsx |
| Report | Felipe/manufacturing-producao.jsx | Wagner/manufacturing-producao.jsx |
| Settings | Felipe/manufacturing-producao.jsx | Wagner/manufacturing-producao.jsx |

## Consumidores rodados (nao so revisao do texto)
- `node scripts/design/ancora.mjs Manufacturing/<Tela>` nas 5: `âncora ✓ [related_prototype (charter)] prototipo-ui/cowork/Wagner/...`, sem ambiguidade. No Index aparece tambem o `bundle_source` `Wagner/manufacturing-page.jsx`, que o proprio ancora.mjs marca como bundle de origem, nao ancora da tela (ja era assim antes).
- `node scripts/memory-schemas/validate.mjs` nos 5 charters: `5 arquivo(s) conformes`, rc=0. Controle positivo: charter com `related_prototype: a: b` no mesmo diretorio deu `FAIL ... YAML parse error`, rc=1 (o validador morde).
- Parse YAML estrito (js-yaml) dos 5 frontmatters: `related_prototype` le o caminho novo.
- `node scripts/governance/design-code-map-check.mjs --strict`: rc=0, nenhum map.json com ancora quebrada.
- `node scripts/governance/cowork-ssot-guard.mjs`: rc=0.

## Pontas que ficam (fora do prefixo desta thread, nao tocadas)
- `memory/governance/prototipo-readiness.json` (linhas 121-151) ainda cita `cowork/Felipe/manufacturing-*`. E derivado por `scripts/qa/prototipo-readiness.mjs` no cron `mv-metabolismo`; ele se regenera a partir dos charters.
- `memory/requisitos/Manufacturing/{Index,Insumos,Recipes,Report,Settings}-visual-comparison.md` citam a ancora Felipe como retrato datado da comparacao (saida colada do ancora.mjs e tabela "ancora"). Sao fato datado, nao ponteiro vivo; atualizar vira com a proxima comparacao de cada tela.
- Para a thread 03: essas referencias nao sao host nem charter, mas quem apagar `cowork/Felipe/manufacturing-*` deve reconferir o `cowork-ssot-guard` e esses dois lugares.
