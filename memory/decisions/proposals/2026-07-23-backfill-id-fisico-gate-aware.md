---
status: proposal
title: "Backfill físico de id: no corpus memory/ — exceção autorizada ao §5 big-bang + gates cientes de diff id-only"
proposed_by: Claude — decisão [W] 2026-07-23 "físico em todos"
proposed_at: 2026-07-23
relates_to:
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio
---

# PROPOSAL (PR4) — Backfill físico de `id:` + gates cientes de diff `id-only`

> **Isto precisa de ratificação [W] + flip de gate required — não construo o código de gate sem sign-off.** É a peça de aterrissagem do design [`2026-07-23-referencia-id-estavel-doc-links.md`](2026-07-23-referencia-id-estavel-doc-links.md) §3-bis. Decisão [W]: stampar `id:` físico nos ~2016 docs sem id. Este doc autoriza o big-bang e desenha como ele **aterrissa sem virar teatro travado**.

## Contexto (medido)

- **2016** docs `memory/` sem id (worklist do PR1). Destes, **~670** em famílias grace/warn (stamp landa direto) e **1249** gate-toxic (`requisitos/**` + `charter`).
- Stampar `id:` nos gate-toxic os põe no diff → **`anchor-lint`** (required, ADR 0273) e **`distiller_freshness`/SDD scorecard** (required) mordem **dívida legada pré-existente**. É o cenário que a **lápide §5 2026-07-12** chama de teatro que `enforce_admins` bloqueia (PR #4156 morreu fazendo isso em 52 SPECs).
- **Verificado:** `charter/spec/runbook/briefing.schema.json` são `additionalProperties:true` → `id:` **não** fere schema. O muro é só `anchor-lint` + `distiller_freshness`, **não** o memory-schema-gate.

## Decisão proposta

1. **Autorizar o big-bang de `id:` como exceção explícita e ÚNICA à lápide §5 2026-07-12** (que exige ADR pra qualquer exceção — "nada sai daqui sem ADR explícito"). Escopo: adicionar **só** o campo `id:` no frontmatter; nenhuma outra mudança de conteúdo no mesmo commit.

2. **Tornar `anchor-lint` ciente de diff `id-only`:** um arquivo cujo **única** delta no PR é a adição de `id: <slug>` no frontmatter é **no-op** pra avaliação de âncora (a dívida de âncora dele não mudou). Enforçado por **selftest com controle negativo**: um diff que adiciona `id:` **E** mexe numa âncora **NÃO** é isento (§5 L-24: presença ≠ correção; a isenção é sobre a NATUREZA do diff, provada, não sobre um flag). Isto é **subtração segura** (ADR 0271), não afrouxamento — o gate segue mordendo tudo que não for id-only.

3. **`distiller_freshness` (item aberto — honesto):** é baseado em **git-mtime** (não em diff), então a isenção acima não o cobre automaticamente. **Preciso verificar o cálculo real do scorecard antes de cravar** (LC-08). Opções, por preferência:
   - **(A)** freshness ciente de commit `id-only` (ignora, no cálculo de data, commits cuja única mudança é `+id:`). Mais limpo se o cálculo for auditável por commit.
   - **(B)** re-destilar/tocar o `BRIEFING.md` do módulo no mesmo bundle (mtime do módulo sobe junto → delta ~0). Custo: toca BRIEFING (também gate-guarded).
   - **(C)** reset de baseline do scorecard **autorizado por esta ADR**, janela única do backfill. **Não é** o "editar baseline pra passar" proibido (§5) — é reset deliberado, documentado, de mudança semanticamente-nula, com recibo. Último recurso.

## Por que não é o teatro que a §5 proíbe

- A isenção `id-only` é **provada por selftest** (controle positivo isenta; negativo — qualquer outra mudança junto — **morde**). Não é presence-gate.
- O `id:` é **semanticamente nulo** pra âncora/frescor (não cria dívida, não muda comportamento) — a isenção reconhece isso, não esconde dívida.
- Escopo cirúrgico: **só** `+id:`. Um commit que aproveite pra mexer em outra coisa perde a isenção inteira.

## Rollout (depende desta ADR aceita)

- **PR5** — stamp `id:` nas ~670 grace/warn (landa direto, sem depender da isenção).
- **PR6+** — stamp `id:` nos 1249 gate-toxic, em **bundles por módulo**, cada bundle: (a) só `+id:`; (b) regenera `governance/doc-id-index.json`; (c) verde via a isenção `id-only`.
- Ids escolhidos por convenção clara (autoridade [W] delegada 2026-07-23 "achar ID vagos e remover conflito") — slug estável, sem colisão (o `doc-id-index --check` morde colisão).

## Riscos / reversão

- **Se a isenção `id-only` vazar** (isentar diff que não é só `+id:`): o selftest de controle negativo deve pegar em CI; se passar em prod, **reverter a isenção** (o backfill já feito fica; volta a morder diffs futuros).
- **Recomendo revisão adversarial** da isenção antes de wire (é modificação de gate required — mesmo padrão de rigor dos outros gates).

## Pendente [W]

1. Ratifica autorizar o big-bang (exceção à §5 2026-07-12) + a isenção `id-only` do `anchor-lint`?
2. Escolhe o caminho do `distiller_freshness` (A/B/C) — depois de eu verificar o cálculo real do scorecard e te trazer o custo de cada um?
3. Ok abrir isto como ADR aceita (não proposal) quando estiver ratificado — aí PR5/PR6 destravam.
