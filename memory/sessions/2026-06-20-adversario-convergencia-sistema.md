---
date: "2026-06-20"
topic: "Adversário da convergência (10 céticos): converge em 2 camadas — núcleo-duro por mecanismo (gates mordem), casca-mole só por disciplina. Diagnóstico: super-construído e sub-armado; 4 modos quebram hoje."
authors: [C]
related_adrs: ["0256-knowledge-survival-meia-vida-catraca-sentinela", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0294-metodo-dual-track-shape-up-travado-por-catraca", "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"]
prs: []
---

# Adversário da convergência — isso vai funcionar mesmo? (maturação)

> Pergunta-mãe (Wagner, lost): "muitos conflitos, isso realmente vai funcionar? vários vão tentar quebrar ou fazer diferente — o que garante?" 10 céticos atacaram 10 modos de falha, cada um verificando contra o repo se a defesa **morde** (gate required) ou é **teatro** (advisory). Run wf_5925027a (10 agentes). Prova ao vivo durante a sessão: **PR #3072 mergeou com o gate "Session log" VERMELHO** — exatamente o comportamento previsto de um gate advisory.

## Placar
- **10/10 defesa PARCIAL** (nenhuma 100% blindada, nenhuma 100% aberta).
- Severidade: 3 alta · 7 média.
- **4 QUEBRAM a convergência hoje** (todas na "casca-mole"): sdd-scorecard-advisory · session-knowledge-survival-gap · docs-git-drift · double-supersede-sem-gate.

## O veredito em 2 camadas (a chave de tudo)

### CAMADA 1 — núcleo-duro: **CONVERGE POR MECANISMO** ✅
O que protege contra o pior (vazar dado de tenant, corromper canon, código não-compilar) **morde de verdade**:
- **18 required checks** que bloqueiam merge: Append-only canon, **No hardcode business_id (Tier-0)**, ADR frontmatter, PHPStan/Larastan, Pest, PII scan, cor-crua/ui-lint/dSIH ratchets, visual-regression.
- `enforce_admins=true` na proteção clássica (admin não fura esses 18).
- **branch-per-PR + worktrees isolados** — nenhuma sessão empurra direto pra main. Colisão de N sessões não corrompe main.
- ADR index é **gerado + --check required** no umbrella (mordeu no #3072).

→ **Esta camada aguenta N atores paralelos.** É o que garante que "não vira caos" no que importa.

### CAMADA 2 — casca-mole: **CONVERGE SÓ POR DISCIPLINA DO WAGNER** ⚠️
O aparato existe mas está **advisory/desarmado** — depende de você notar:
- **SDD scorecard**: `continue-on-error:true` nos 3 steps; **0/18 required são SDD**. Mede, não governa.
- **anchor-lint** (docs↔realidade): detecta 15 anchors mortos mas **sempre exit 0**; não-required. Spec drift passa livre.
- **distiller módulo-verdade** (força doc=git): **cron COMENTADO** aguardando gate humano.
- **knowledge-survival cobre FORMA não CONTEÚDO**: `front_door_coverage=100%` mede *existência de BRIEFING*, não destilação — por isso os **155 planos perdidos** passaram batido (prova viva).
- **block-pr-without-approval.mjs** (R10 aprovação humana): existe no disco, **NÃO registrado em settings.json** → não dispara. R10 é teatro.
- **double-supersede** (2 ADRs herdando a mesma): não detectado.
- **PLANS-INDEX** manual (15 de ~200, 0/15 reviewed_at).
- **strict=false** + auto_merge: 2 PRs no mesmo arquivo podem mergear contra base dessincronizada.

→ **Esta camada NÃO se auto-garante.** Você tem sido o mecanismo de convergência dela. É por isso que se sentiu perdido.

## O que isso responde

**"Vai funcionar?"** — O que precisa funcionar (isolamento Tier-0, integridade do canon, código são) **funciona e é garantido por gates que mordem**. O que te preocupa (planos/conhecimento/SDD coerentes sob muitos atores) **ainda não se auto-garante** — apoia na sua atenção.

**"O que garante?"** — Hoje: ~12 gates que bloqueiam + sua vigilância. O achado libertador: **o sistema é super-construído e sub-armado.** Quase todos os buracos da camada 2 fecham **promovendo advisory→required** ou **registrando um hook que já existe** — não é infra nova, é *armar o que já está pronto*.

## Backlog de armamento (barato — quase nada é código novo)
Ordenado por (impacto × custo). A maioria é 1 linha de config ou 1 PR de promoção (calendário ADR 0275 §5):

1. **Registrar `block-pr-without-approval.mjs` em settings.json** — 1 linha. Liga o R10 (hoje teatro). *(o hook já está implementado e testado)*
2. **Promover anchor-lint a required** (`--check` no anchor-drift.yml + add aos required checks) — converte o radar de doc-drift em dente. Sem infra nova.
3. **Promover SDD scorecard a required** — remover `continue-on-error` dos 3 steps + add context aos required. Arma ghost_count/front_door/staleness.
4. **memory-health Check F** — session log >30d com marcadores de decisão (`## Decisão`, `US-`, `rollout`) sem link pra ADR/BRIEFING → WARN. **É o detector dos "155 perdidos".**
5. **strict=true** na branch protection — fecha a janela CI-contra-base-stale com auto_merge.
6. **double-supersede check** no adr-index-generate.mjs (após L99) — vira gate duro.
7. **plans-index generator + --check** no umbrella — (já é o workstream ADR 0294 em voo; não duplicar).
8. **CODEOWNERS + reviews≥1** (ADR 0262 gatilho atingido c/ Maiara ativa) — reduz o SPOF-Wagner na camada 2.

## Prova ao vivo (o sistema se delatou)
PR #3072 (estes próprios artefatos) **mergeou com "Session log" vermelho** (topic >250) porque esse gate **não é required**. O follow-up #3075 conserta o conteúdo — mas a lição é o item 2/3 acima: **gate advisory deixa entrar torto.** Armar > consertar depois.

> Run wf_5925027a (10 agentes, 703k tokens). Evidência por modo no transcript.
