---
date: "2026-07-15"
time: "15:04 BRT"
slug: onda-combobox-3de5-migradas
tldr: "Onda do papel combobox (componente-por-papel, ADR 0338): tripé entregue (#4295) + 3 de 5 telas migradas pro canon Popover/Command E testadas olhando em produção (#4302/#4303/#4304); 2 de Sells ficam como resíduo Tier 0 intencional (decisão W), doc de fechamento #4313."
prs: [4295, 4302, 4303, 4304, 4313]
decided_by: [W]
related_adrs: [0338-ds-lint-eixo-valor-token-fecha-por-forma, 0209-eslint-9-flat-config, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura]
next_steps:
  - "Mergear #4313 (doc de fechamento, CI verde) pra formalizar o estado da onda"
  - "NÃO force-migrar as 2 de Sells sem aceite Tier 0 + smoke pesado (proibicoes §5 2026-07-15)"
---

# Onda combobox — 3 de 5 migradas + testadas em prod, 2 de Sells = resíduo Tier 0

## Estado MCP no momento
- **cycles-active:** nenhum cycle ATIVO em COPI (trabalho off-cycle DS).
- **my-work @wagner:** 5 tasks REVIEW (US-TR-309/310/305 triage, US-PG-008 linkage, **US-PROD-022 `⚠️Tier0` markup por SellingPriceGroup**). Nenhuma é desta onda — mas a US-PROD-022 confirma que há trabalho Tier 0 de grupo-de-preço vivo (reforça não mexer nas de Sells).
- **Handoffs irmãos hoje:** `2026-07-15-1435-status-badge-onda-tripe` + `2026-07-15-0030-consolidacao-ds-tabnav-dark` + `2026-07-15-1420-pht-accent-token-dark-aware` — mesma linhagem componente-por-papel (tab-nav, status-badge, combobox rodaram em paralelo).

## O que aconteceu
Abri a **onda do papel `combobox`** (campo de busca com dropdown) no padrão componente-por-papel (ADR proposta `2026-07-15-tab-nav-canonico`, tripé ADR 0338 §5). O detector `--roles` tinha catalogado 5 hand-rolls.

1. **Tripé (#4295):** canon = composição `Popover`+`Command` (cmdk); ambos já no REGISTRY; ref viva `ServiceOrders/Create.tsx`. Regra `ds/no-handrolled-combobox` (component-substitute, selector preciso: `aria-autocomplete` + `role=combobox` em `<input>` nativo — o `<Button role=combobox>` canônico NÃO é pego) no ratchet 0209. Assinatura `combobox` em `ROLE_SIGNATURES` (âncora = importar `@/Components/ui/command`) + self-test. **Nenhum workflow/gate novo** → nada em `gates-registry.json`.
2. **3 migrações limpas** (cada uma preservou API + comportamento 1:1, baseline delta 0):
   - `PlanoContaCombobox` (Financeiro) → `Popover`+`Command`, client-side single-select (#4302).
   - `GradeProductCombobox` (Purchase) → `Command` inline async, `shouldFilter=false` + fetch verbatim (#4303). Bônus: `text-stone-*` viraram tokens dark-aware.
   - `ClienteCombobox` (Financeiro) → `Command` inline async + **texto-livre** preservado (#4304).
3. **Testei OLHANDO em produção** (Chrome logado, sem salvar) as 3 — abrir/buscar/selecionar. Todas funcionam, visual bom. `PlanoConta`: busca "web"→2 resultados, seleciona "1.1.2.2 WEB SITE". `GradeProduct`: filial→busca "co"→produtos reais. `Cliente`: busca "fel"→contatos ricos→seleciona "ARTEFINAL SERIGRAFIA ME".
4. **2 de Sells = resíduo intencional** (decisão W "deixar como resíduo, recomendo"): `CustomerSearchAutocomplete` + `ProductSearchAutocomplete`. Doc de fechamento #4313.

## Artefatos gerados
- Código canon: `eslint.config.js` (regra), `scripts/governance/component-registry-check.mjs` + `.test.mjs` (assinatura+self-test), `prototipo-ui/REGISTRY_DS_COMPONENTES.md` + `REGRAS_DS_LINT.md` (docs) — via #4295.
- 3 componentes migrados (1 arquivo + baseline cada): #4302/#4303/#4304.
- Doc fechamento #4313: REGISTRY §"Combobox" → "Estado da migração" + `memory/proibicoes.md §5` entrada "force-migrar 2 comboboxes async de Sells".

## Persistência
- **git:** #4295/#4302/#4303/#4304 MERGED em `origin/main`; #4313 CI verde aguardando merge W.
- **MCP:** webhook GitHub→MCP propaga os docs canon (~2min).
- **BRIEFING:** onda DS, sem BRIEFING de módulo específico.

## Por que as 2 de Sells NÃO migram (o resíduo honesto)
São **Tier 0 valor/estoque**: `CustomerSearchAutocomplete` reaplica `selling_price_group_id` no `onSelect` (muda preços da venda) + usada em 5+ telas + carrega o **hotfix do sufixo** (bug "Cliente padrãowagner", W smoke 2026-05-27) cuja UX `value = query || selectedLabel` conflita com o modelo controlado do `CommandInput`. `ProductSearchAutocomplete` (821 linhas) adiciona item→total+estoque. Forçar = over-fitting (anti-padrão que a 0338 alerta) + risco Tier 0 >> ganho advisory. Detector `--roles` segue listando-as como drift honesto (não é bug). Drift do papel: **5 → 2**.

## Próximos passos pra retomar
`gh pr merge 4313 --squash` (fecha a doc da onda). Onda combobox encerrada; próxima onda componente-por-papel (se houver) = novo papel em `ROLE_SIGNATURES`.

## Lições catalogadas
- **Ondas paralelas mesma-linhagem colidem em git** — combobox rebaseou 3× (vs status-badge #4298/#4310, tab-nav SubNav #4294, e o baseline `eslint-baseline.json` que TODA PR de DS toca). Resolução: manter as duas assinaturas/entradas lado a lado, nunca sobrescrever.
- **Testar olhando > narração** (R1): a `GradeProduct`/`Cliente` async eu tinha marcado "visual precisa de olhos"; o smoke real em prod provou que ficou bom — o que eu não conseguia afirmar estático.
- **Nem todo hand-roll deve migrar** (doutrina 0338): resíduo honesto > over-fitting. A regra Tier 0 "CÁLCULO DE VALOR" sobrepõe o "todos" — surfacei o tradeoff e W decidiu.
- **Rota Inertia opt-in** (`?v=2` em `/purchases/create`) foi como cheguei na tela React do GradeProduct em prod (dual-path MWART).

## Pointers detalhados (on-demand)
- Onda/tripé: `prototipo-ui/REGISTRY_DS_COMPONENTES.md §"Combobox"` + `REGRAS_DS_LINT.md`.
- Resíduo Sells: `memory/proibicoes.md §5` entrada 2026-07-15 "force-migrar os 2 comboboxes async de Sells".
- Detector: `scripts/governance/component-registry-check.mjs --roles` (papel `combobox`).
