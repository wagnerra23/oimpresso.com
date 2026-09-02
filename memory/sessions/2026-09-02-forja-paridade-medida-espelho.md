---
date: "2026-09-02"
hour: "08:00 BRT"
duration: "1.5h"
topic: "Forja — o que falta pra aparência bater com o protótipo: espelho completado e primeira medição por sonda em 5 pares"
authors: [W, C]
outcomes:
  - "Espelho da Forja provado no dia: shell e styles.css estavam STALE e 4 adaptadores cli-* estavam ausentes — sem eles Trabalho e Integrador quebravam no protótipo local"
  - "Primeira comparação medida (design-diff) em 5 pares: primary 0,55×0,70 em todos (componente ignora o token dark), KPI valor 22px×17/28/30px, Trabalho 3×13 colunas e 1×3 linhas de filtro, MCP sem mono/cor própria, Changelog 5×2 colunas"
  - "Bloqueio de transporte declarado: bundle do DS local publica 44 componentes, o vivo 55 (sem Segmented) e o get_file devolve o vivo TRUNCADO — só o pacote v2 resolve, e ele não tem dono"
prs: []
us: []
related_adrs:
  - "0374-emenda-0315-espelho-cowork-e-rota-prevista"
  - "0367-cockpit-unico-forja-project-mgmt-morre"
---

# Forja — paridade protótipo × produção, medida (2026-09-02)

**Pergunta do [W]:** *"confira o protocolo e máquinas — o que falta para o módulo Forja ficar igual ao protótipo? a aparência"*, com as instruções *"considere as sessões paralelas"* e *"controle tudo"*. Hipótese dele no meio: *"falta css, acho que a camada do DS"*.

## Sessões paralelas

`Aplicar forja do protótipo` estava RODANDO na branch `claude/forja-prototipo-f3cafb` com o [#6537](https://github.com/wagnerra23/oimpresso.com/pull/6537) aberto (topnav 13 → 9, toca `ForjaHub.tsx` · `core_topnavs.php` · `Cockpit.casos.md` · `PARIDADE-area-forja-diagnostico-e-ondas.md`). Esta sessão **não tocou nenhum desses arquivos** — trabalhou só no espelho (`prototipo-ui/cowork/**`), no ledger e no dono do inventário por tela (`forja-cockpit-visual-comparison.md`).

## Máquinas rodadas (na ordem do painel)

1. `protocolo.config.mjs` + `--selftest` ✓ · `--sla` (7/254 medidos ontem, 157 vivos fora do espelho) · `--manifest --all` (7 âncoras da Forja).
2. `ancora.mjs` nas 17 telas: 3 apontam pra `forja-page.jsx` (Cockpit, Trabalho, Aprovações); 2 sem `related_prototype` (Board, Gantt); 12 declaram `n/a` legítimo.
3. `status.mjs --check-mapping`: os 3 alvos `ANCHORED` sem `map.json` ("gerar/registrar map.json antes de aplicar").
4. **Fonte provada** pelo DesignSync (16 `get_file` → extração por script do transcript → `--snapshot-from` → `--compare --check`): `oimpresso.com.html` e `styles.css` STALE · `cli-seg.js`/`cli-tabs.jsx`/`cli-pagehead.jsx`/`cli-kebab.jsx` AUSENTES · os 7 `forja-*` SYNC. `--export-from` (fiel por construção) → `--manifest` → `--preview-ds` ✓ → `--compare --check --ledger` (14 SYNC · 0 stale).
5. **Sonda `design-diff --probe`** injetada nos dois renders (mesmo tema dark, espera de render, 2 leituras estáveis) e `--compare --check` em 5 pares. Resultado no [dono do inventário](../requisitos/TeamMcp/forja-cockpit-visual-comparison.md) §2026-09-02.
6. D1 (rede): chip de ordenação em `/forja/trabalho` = GET Inertia parcial, marcador sobreviveu.
7. Gates do espelho: `cowork-ssot-guard` ✓ · `--unverified --check` ✓ · `anchor-content-check --check` ✓ · `--check-orfaos` ✓.

## O que falta pra "ficar igual" — por camada, do mais barato ao mais caro

1. **Token do primary no dark (1 arquivo):** `PageHeaderPrimary.tsx:70` fixa `oklch(0.55 0.15 295)` inline; o canon dark (`_generated-inertia-dark.css`, emenda 2026-07-08 "fidelidade ao proto") é `oklch(0.7 0.15 295)`. É a D6 vermelha dos 5 pares — e afeta todo módulo que usa o PageHeader canon, não só a Forja.
2. **Camada de DS do módulo:** não existe `resources/css/cowork-forja-bundle.css`; as telas de produção não têm um `fj-*`/`ap-*` sequer. O protótipo vive de `forja-page.css` (97 KB, 765 seletores `.fj-` + 76 `.ap-`). A regra da casa é bundle inteiro primeiro, customização depois — e a lápide de 2026-07-10 manda provar que nenhum consumidor renderiza em portal antes de "deduplicar" token.
3. **Composição por tela** (a parte cara, e é onda por onda — [PARIDADE §7](../requisitos/Forja/PARIDADE-area-forja-diagnostico-e-ondas.md)): Aprovações (mesa inteira: herói + ao-vivo + fila + painel + placar), Trabalho (KPI que filtra, 3 linhas de filtro, linha de 13 colunas, segmentado Lista|Quadro|Gantt), MCP (painel Handoffs dentro do MCP, mono e cor própria nas células), Changelog (linha dot+corpo com 5 blocos, sessão com título), Saúde (sparkline + `ver →` por KPI).
4. **Topnav:** 13 → 9 já no #6537; 6 em 3 grupos-pílula depende de construção (Handoffs/Equipe no MCP, Sessões no Changelog, Triagem em Aprovações).

## Bloqueio de transporte (decisão fora do meu alcance)

O bundle do DS que o espelho consome (`mirror-snapshot/_ds_bundle.js`) é o do pacote de **24/08** (44 componentes). O vivo publica **55** — inclui `Segmented`, que a Lista|Quadro|Gantt do protótipo usa via `window.CliSeg`. O `get_file` devolve o bundle vivo **TRUNCADO** (290 KB > 256 KiB), então o segmentado do protótipo **não renderiza localmente** até o Cowork regerar o pacote v2 — pedido formal já enviado em 2026-09-01 (`CODE_NOTES.prompt-cowork-regenerar-bundle-por-ciclo-2026-09-01.md`); o `sync/bundle.manifest.json` vivo confirma o mesmo `bundleId` de 24/08.

## Não fiz, de propósito

- Não editei `.tsx`/`.css` de produção (merge de `.tsx` é humano, e a onda 0 é decisão [W] — US-FORJA-006).
- Não criei `contrato/forja*.contract.json` (copy literal é slot do [W]).
- Não remendei o espelho à mão (ADR 0374) — tudo desceu por `--export-from` a partir do JSON do `get_file`.
