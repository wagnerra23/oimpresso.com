---
date: "2026-09-06"
topic: "Os 6 gap.md que saíram do lote #6897 (fundação, shell, OficinaAuto, Crm, Sells ×2): 3 ganham map honesto pela porta certa, 4 saem do denominador por `map_json: n/a` — cobertura 20/20, 2 decisões ficam com [W]"
authors: ["C"]
related_adrs: ["0324-identidade-prototipo-por-conteudo", "0282-protocolo-v2-colapso-ratificacao", "0344-two-strikes-cobre-processo"]
outcomes:
  - "gerar-map.mjs: resolverArquivoVivo aceita resources/js/{Components,Layouts}/ — FP medido no corpus dos 23 tela_viva (21 iguais, mudam só os 2 alvos), 4 bites no selftest"
  - "design-code-map-check.mjs: `map_json: n/a (<motivo>)` no frontmatter tira o gap.md do denominador e lista o motivo; n/a + map ao lado = WARN; 8 checks novos"
  - "PageHeader (12 partes) e Sidebar (17) ganham tabela derivada + map ancorado arquivo a arquivo; protótipo TODO por desenho (expurgo 9da73296d3)"
  - "OficinaAuto: kanban-producao-gap recebe banner de MIS-ANCHOR datado; ordens-servico-board-gap.md + map novos (par correto de oficina-page.jsx, 8 partes, PARIDADE)"
  - "Crm/clientes, Sells/vendas-index, Sells/vendas-create: map_json n/a com o motivo — os dois de Sells carregam a proposta e ficam com [W]"
  - "Refutação GT-G5: r1 7/253 (2,77%, reprovado) → corrigido → r2 3/316 (0,95%, aprovado); os 3 resíduos corrigidos no mesmo PR"
---

# Os 6 que sobraram do #6897 — cada um pela porta que tinha

> Continuação de [2026-09-06-seis-perguntas-design-sync-resolvidas.md](2026-09-06-seis-perguntas-design-sync-resolvidas.md) §Pós-merge. O lote #6897 encolheu de 11 para 5 telas porque estes 6 gap.md não tinham como ganhar map honesto pela porta que existia. Aqui cada um recebe a porta certa ou a declaração honesta de que não terá map — nunca o esqueleto.

## O que foi medido antes de mexer

| # | gap.md | medida que decidiu a ação |
|---|---|---|
| 1-2 | `_DesignSystem/pageheader-canon-v3`, `sidebar-v3-unificado` | `resolverArquivoVivo` (`gerar-map.mjs:80`) só aceitava `Pages/`. Custo da opção (a): 1 regex + 4 asserts. FP no corpus dos 23 `tela_viva`: **21 iguais, mudam só os 2** (16 resolvem sob `Pages/`, 2 têm campo sem match nos dois regex, 3 sem campo). Escolhida (a); protótipo `TODO` + `prototipo_nota` íntegra (a citação truncada era o R-2 da r2 do #6897). O código andou desde a prosa de 2026-06-23: `PageHeaderTabs.tsx` (352 ln) + `SubNav.tsx` + slot `below` fecham o P4; badge de contagem voltou por decisão [W] 2026-07-14 (`Cliente/Index.tsx:827`); ADR 0189 está `status: aceito` (o gate "só se aceita" do P8 caducou); hints kbd da sidebar existem (`Sidebar.tsx:490-527`, `useSidebarShortcut.ts:178`); tema da sidebar é ADR UI-0023. |
| 3 | `OficinaAuto/kanban-producao-gap.md` | `ancora.mjs Repair/ProducaoOficina` → `repair-page.jsx`; `ancora.mjs OficinaAuto/ServiceOrders/Board` → `oficina-page.jsx` (`Board.charter.md:5`). Os 4 "ganhos a colher" do gap antigo estão todos no `Board.tsx` (Onda 1/1.5/2): busca `:769-772`, 6 KPIs `:477-492`, Lista `:900-909`, `MercosulPlate` `:89`. Fazia sentido o gap novo: 8 partes com âncora dupla por `grep -n`, veredito PARIDADE, Non-Goals do charter onde o mock inventava (ETA, "Pago", heurística da Grade). |
| 4 | `Crm/clientes-gap.md` | A "releitura contra o espelho atual" que o banner pedia já tem dono: `Cliente/clientes-gap.md` + `clientes.map.json` (7 partes), ancorado no `clientes-page.jsx` que `Cliente/Index.charter.md:5-6` declara. 2º map = régua duplicada (§5 2026-07-09) — o mesmo motivo do r4 R-A do #6897. Registrado; sem esqueleto. |
| 5 | `Sells/vendas-index-gap.md` | `vendas.map.json` (gap_fonte `vendas-gap.md`) tem 11 de 12 âncoras com linha real e 0 DRIFT; este tem 0 âncoras e nenhum citador fora do selftest do `gerar-contrato` e um comentário; o charter não cita nenhum dos dois. Os 3 primeiros "Adotar" seguem abertos no vivo (`totals` buscado em `:499/:725`, 0 render de `vd-totalbar`). Proposta com evidência no §Reconciliação — **decisão [W]**. |
| 6 | `Sells/vendas-create-gap.md` | `ancora.mjs Sells/Create` → "registre o protótipo". `vendas-create-page.jsx:1-3` se declara bi-vertical ComVis+Oficina, "domínio do vendas-create-completo.jsx" — **não** é porte reverso do `Create.tsx`. Proposta `bundle_source: vendas-create-page.jsx` + `related_prototype: n/a (…)` no charter — **decisão [W]**; 5 das 9 partes tocam VALOR (Regra Mestre). |

O denominador precisava da opção (b) de qualquer forma: os 4 que não ganham map ficariam eternamente em "candidatos". `map_json: n/a (<motivo>)` é a mesma convenção do `related_prototype: n/a` dos charters (`ehDeclaracaoNa`), lida pelo dono do tema (`design-code-map-check`), com bite-test (n/a sai do denominador · sem declaração segue candidato · n/a + map = WARN contradição).

## Refutação GT-G5 (voluntária — o PR toca exatamente 10 arquivos em `memory/requisitos`, o protocolo exige em >10)

| rodada | itens | refutados | taxa | veredito | evidência |
|---|---|---|---|---|---|
| r1 | 253 | 7 | 2,77% | reprovado | [`2026-09-06-refutacao-gt-g5-seis-gap-fundacao-r1.md`](2026-09-06-refutacao-gt-g5-seis-gap-fundacao-r1.md) |
| r2 | 316 | 3 | 0,95% | **aprovado** | [`2026-09-06-refutacao-gt-g5-seis-gap-fundacao-r2.md`](2026-09-06-refutacao-gt-g5-seis-gap-fundacao-r2.md) |

Os 7 da r1: badge de contagem re-adicionado que a tabela negava (2 itens — a prosa de junho, obedecida sem re-medir); listener do `G X` apontado pra linha errada; `RECURSOS` em `:33-39`, não `:41-47`; `oficina-print.js` hoje **está** no espelho; 5 dos 6 KPIs do mock são clicáveis, não 6; tabela de citações incompleta. Os 3 da r2, corrigidos no mesmo PR depois da aprovação: `acao` de uma parte preservado pelo `--atualizar` com o texto antigo (regen fresca + preenchimento resolve — o `fundirComExistente` preserva `acao` por desenho), "8 arquivos" expurgados eram 7 (+ o da sidebar), decomposição "18 sob Pages/" era 16+2. **Lição que fica:** as duas rodadas pegaram o mesmo defeito de fundo — Estado/Ação escritos a partir da prosa de 2026-06-23 sem re-medir o código de hoje (LC-08). Onde eu re-medi (SubNav, ADR 0189, atalhos kbd), passou; onde herdei (badge, oficina-print), caiu.

## Gates (rodados no fim, `origin/main` = 80bc4ef8b9)

- `node prototipo-ui/gerar-map.mjs --selftest` OK · `node scripts/governance/design-code-map-check.test.mjs` OK (8 checks novos).
- `design-code-map-check --check --strict`: **cobertura 20/20 (100%) · 4 fora do denominador por `map_json: n/a`** · 1 DRIFT **pré-existente** em `Cliente/clientes.map.json` (STALE após o #6893 descer o `clientes-page.jsx`; `git diff origin/main -- memory/requisitos/Cliente/` vazio — dono é o par `Cliente/`, não este PR).
- `gerar-map --atualizar` reproduz os 3 mapas byte-a-byte (fora `gerado_em`).
- `requisitos-status` OficinaAuto/Sells/Crm `--check` rc=0 · `plans-index --check` rc=0 · `doc-id-index --check-collisions` 0 colisões.
- PII: 0 hits nas duas rodadas (7 padrões, controle positivo cada). Nenhum valor em reais no diff.

## Fica com [W] (2 decisões)

1. **Sells/Index — qual dos dois gap é o dono.** Proposta: `vendas-gap.md` + `vendas.map.json` (evidência no §Reconciliação de `vendas-index-gap.md`). Ratificando: 4 linhas novas na tabela do dono + `gerar-map Sells/vendas --atualizar` + este vira registro datado. Até lá, `map_json: n/a`.
2. **Sells/Create — âncora no charter.** Proposta: `bundle_source: vendas-create-page.jsx` + `related_prototype: n/a (herda Cockpit V2 ADR 0110 — vestuário; o bundle é ComVis+Oficina)`. Custo: acorda `charter-us-lint` (charter sem `related_us`). Se aceita, o map nasce com 9 partes e Tier 0 valor em 5 delas. Até lá, `map_json: n/a`.

## Não feito (declarado)

- Handoff em `memory/handoffs/` não escrito: sessão sem tools MCP conectadas (checklist MCP-first do ADR 0130 não executável aqui) — este session log + o PR carregam o estado.
- `Cliente/clientes.map.json` STALE segue (dono próprio).
- `governance/doc-id-index.json` não regenerado (frescor é ato de consolidação; o CI só checa colisão — 0).
