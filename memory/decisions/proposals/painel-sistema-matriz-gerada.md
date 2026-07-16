---
status: proposal
title: "Painel do sistema como MATRIZ gerada — índice derivado, não doc à mão"
proposed_by: Wagner + Claude
proposed_at: 2026-07-12
relates_to:
  - 0091-daily-brief
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0258-adr-index-gerado-supersede-atomico
  - 0094-constituicao-v2-7-camadas-8-principios
---

# PROPOSAL — Painel do sistema como MATRIZ gerada

> **Status:** `proposal` — Wagner promove a ADR aceita após revisão.
> **Origem (2026-07-12):** Wagner, cansado de ter que ficar lembrando/regenerando o mapa
> do sistema à mão: *"deveria ser a máquina matriz que não quebra e possa sempre manter
> atualizado"*.

## Contexto

O oimpresso ficou grande: núcleo + ~37 módulos, 342 ADRs, 239 handoffs, o programa SDD,
a infra CT 100/Hostinger. Entender "tudo que existe" e "o que já foi tentado" exigia
regenerar um panorama à mão a cada sessão — chato, caro e drifta.

O projeto **já tem o padrão** pra isso: o Daily Brief ([ADR 0091](../0091-daily-brief.md)),
o scorecard SDD ([ADR 0275](../0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md))
e o índice gerado de ADRs ([ADR 0258](../0258-adr-index-gerado-supersede-atomico.md)) são
todos **máquinas matriz**: geradas de fontes canônicas, não mantidas à mão.

## Decisão

Adicionar o **painel do sistema** a essa família como um **índice DERIVADO**:

- **Gerador:** `scripts/governance/system-map.mjs` (node puro, irmão do `sdd-scorecard.mjs`).
- **Saída canônica:** `memory/reference/PAINEL-SISTEMA.md` (`authority: generated`), versionada
  e servida via MCP. É a "entrada pra ser lembrada".
- **Deriva com confiança** o que é estruturado: módulos + frescor real (git-mtime), ADRs +
  lifecycle/supersede, ideias mortas (`proibicoes.md §5`), Tier 0 gaps, scorecard SDD, contagens.
- **Aponta pros donos** (linka BRIEFING/SPEC/roadmap) o que é curado — **nunca recopia**.
- **Views humanas** (mapa 🗺️ / guia 🧭 em claude.ai) derivam DESTES dados.

### O que TORNA isto anti-drift (e não mais um doc que apodrece)

1. **Derivado, não declarado.** O painel é regenerado das fontes; editar à mão é inútil
   (a máquina sobrescreve). Nada de "campo `atualizado_em` auto-escrito" (presence-gate,
   proibido por `proibicoes.md §5` / L-24).
2. **Determinístico.** O `.md` commitado só muda quando uma **fonte** muda (datas absolutas,
   sem contadores relativos a "hoje") — zero churn diário.
3. **Máquina que mantém:** workflow `system-map.yml` — advisory `--check` no PR (avisa se o
   painel ficou stale vs. fontes) + regen diário com commit-back.

### Fronteira honesta (o que a máquina NÃO faz)

O **status/narrativa** de cada módulo vive em prosa no BRIEFING dele (curado — a máquina
não inventa um `status:` que a prosa não declara). O painel mostra existência + frescor e
**linka o dono**. A moldura narrativa das views humanas muda só em pivô real.

> Isto se beneficia da wave **"estrutura-canon-memoria"** (padronizar os arquivos antigos
> num conceito único): quanto mais estruturado o BRIEFING, mais o painel pode derivar em
> vez de só linkar. Uma coisa habilita a outra.

## Consequências

- **Positivas:** ninguém "lembra" o estado — a máquina serve. Sem drift (derivado). Reusa
  o padrão brief/scorecard/índice-ADR (não é tecnologia nova).
- **Custo:** manter o gerador acompanhando as fontes; a parte curada (BRIEFING) segue humana.
- **Neutras:** `authority: generated` é uma categoria nova de frontmatter de `reference` —
  a wave estrutura-canon formaliza.

## Enforcement

`system-map.yml` nasce **advisory** ([ADR 0261/0271](../0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) —
gate novo nunca nasce required). Sem presence-gate: o sinal é "o painel bate com as fontes?",
comparação de conteúdo derivado, não existência de campo.
