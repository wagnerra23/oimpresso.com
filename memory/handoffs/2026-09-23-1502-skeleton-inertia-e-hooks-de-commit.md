---
date: "2026-09-23"
time: "15:02 BRT"
slug: skeleton-inertia-e-hooks-de-commit
tldr: "4 telas saíam do skeleton eterno (ajax() capturando o partial reload Inertia; DeviceModels vivo em prod biz=1) + os hooks de commit ficavam inertes em ~85% dos commits reais (git add na mesma chamada) e o commit-discipline-check em 99,1% — 10 PRs mergeados, lápide §5 e US-FIN-069."
decided_by: [W]
cycle: null
prs: [7769, 7777, 7780, 7787, 7794, 7799, 7824, 7828, 7830, 7834]
next_steps:
  - "Smoke em PROD (R1) das 4 telas: /hrm/sales-target, /hrm/leave-type, /repair/device-models (biz=1, flag ligada) — a lista tem que sair do skeleton"
  - "US-FIN-069: decidir o banner de categorias do DRE no tenant de teste; a baseline só regenera com decisão [W] (ADR 0411)"
  - "Parser dos hooks: 146 comandos de commit do corpus seguem sem reconhecimento; path com espaço no git add não é interpretado"
---

# Handoff — skeleton eterno nas telas Inertia + hooks de commit que não agiam

## Estado MCP no momento
- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work` (@wr23): sem tasks ativas.
- `sessions-recent` (3): três estado-da-arte de 2026-08-22 (fidelidade design→produção, escala, guard-rails de IA) — nenhum desta sessão.
- `decisions-search`: **ADR 0411** (hoje) — snapshot de pixel do VRT fica fora do passo 3 da 0409 e é regenerado **só com decisão [W]**. Governa a US-FIN-069.

## O que aconteceu
1. **Skeleton eterno (classe da lápide §5 2026-09-08).** `if (request()->ajax())` antes de `Inertia::render` com props em `Inertia::defer`: o cliente Inertia v3 manda `X-Requested-With` em toda visita, e o partial reload recebia o JSON do DataTables. Medido no staging antes de corrigir, com sonda read-only. Corrigido com `&& ! request()->inertia()` em **Essentials Metas/Tipos (#7769)** e **Repair DeviceModels + Officeimpresso Logs (#7780)**. A flag do Repair está **ligada para biz=1 em prod** — o defeito estava vivo lá. Testes passaram a mandar os headers reais; mordida provada rodando só os testes contra o controller antigo (vermelho) antes da correção (verde).
2. **Varredura da classe** (#7777/#7787): em `origin/main`, 123 métodos com `Inertia::defer`, 13 com `ajax()`: 8 guardados, 5 sem guarda → 4 defeitos reais (todos corrigidos) + 1 falso-positivo da sonda.
3. **Hooks de commit** — derivados (`SUPERFICIE.md`, backlog, índice de planos) envelheciam no `main` e travavam o PR seguinte. Estendido o `maquinas-inventario-no-commit` (#7794 SUPERFICIE; #7824 backlog + planos). Na medição apareceu o defeito maior: o hook é PreToolUse e **não via o `git add` do mesmo comando** (85,0% dos commits reais). Corrigido no #7824; o `ciclo-adversary` achou mais dois (heredoc da mensagem lido como argumento, quebra de linha) → #7828. O irmão **`commit-discipline-check` reconhecia 51 de 5.561 commits (99,1% cego)** — o aviso de CPF/CNPJ e o de diff grande quase nunca rodaram → #7834.
4. **DRE:** o `visual-regression` reprovava `Financeiro/Dre` em todo PR de escopo global — a baseline do #7767 foi fotografada em outro ambiente (tenant, sidebar, banner). Diagnóstico decodificado com `snap-diff.mjs`, registrado como **US-FIN-069** (#7799). Não regravei a baseline.

## Artefatos gerados
- Controllers: `Modules/Essentials/.../SalesTargetController.php`, `EssentialsLeaveTypeController.php`, `Modules/Repair/.../DeviceModelController.php`, `Modules/Officeimpresso/.../LicencaLogController.php`.
- Testes: `HrmMetasTest`, `HrmTiposIndexTest`, `DeviceModelsContratoTest`, `LogsBaselineTest` (+ UC-LOGS-14 no `Logs/Index.casos.md`).
- Hooks: `.claude/hooks/maquinas-inventario-no-commit.mjs` (+ test, 140 asserts), `.claude/hooks/commit-discipline-check.mjs` (+ test); `scripts/governance/module-surface.mjs` ganhou `raizesDoModulo`/`modulosAfetados`.
- Canon: lápide §5 **2026-09-23 "EMENDA da lápide 2026-08-20 … eixo HOOK"** em `memory/licoes-rejeitadas.md` (§5 derivado em `proibicoes.md`); recs LC-30 e LC-08 em `memory/LICOES_CODE.md`; US-FIN-069 em `memory/requisitos/Financeiro/SPEC.md`; BRIEFING do Repair (distilled_at 2026-09-23).

## Persistência
- git: 10 PRs mergeados (lista no frontmatter). Commits feitos à mão em PR alheio: regeneração do `SUPERFICIE.md` de Essentials no #7765.
- MCP: US-FIN-069 entra pelo webhook do SPEC.
- BRIEFING: Repair atualizado (o fix mexeu no módulo e o `distiller_freshness` subia 8 → 9).

## Próximos passos pra retomar
```
# 1) smoke em prod (biz=1): as listas têm de carregar, não ficar no skeleton
#    /hrm/sales-target · /hrm/leave-type · /repair/device-models
# 2) US-FIN-069 — decisão [W] (ADR 0411)
```

## Lições catalogadas
- **LC-30** (hook que passa no CI e não age no uso real): testes estagiavam antes de chamar o hook e usavam só `-m x`. Regra que ficou: teste de hook usa as **formas medidas no corpus** (heredoc 81,0%, add na mesma chamada 85,0%).
- **LC-08**: subi commit no branch de um PR que já tinha sido mergeado, sem ler `state` → commit órfão + descrição do #7824 afirmando conteúdo que não tinha. Corrigido com errata datada e #7828.
- Três vezes a barra invertida colapsou no transporte shell→Python (LC-26); em todas, `node --check` ou um `assert` pegou antes de gravar. Saída que funcionou: ferramenta de edição direta ou `chr(92)`/`String.fromCharCode(92)`.
- Controles que não controlavam: runs de PR com `SCOPE: none` (não executaram o DRE) e `rc=0` que era do `tail`/da mensagem de uso — conferidos antes de virar conclusão.

## Pointers detalhados
- Lápide completa: `memory/licoes-rejeitadas.md` § "2026-09-23 — EMENDA da lápide 2026-08-20 … eixo HOOK".
- Diagnóstico do DRE: `memory/requisitos/Financeiro/SPEC.md` § US-FIN-069.
- Medições de corpus e mutações: corpo dos PRs #7824, #7828 e #7834.
