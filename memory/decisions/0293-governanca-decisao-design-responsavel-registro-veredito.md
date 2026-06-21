---
slug: 0293-governanca-decisao-design-responsavel-registro-veredito
number: 293
title: "Governança da decisão de design: responsável por etapa do ciclo + Decision Register por tela + ledger de vereditos pro Cowork"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-19"
module: design-system
tags: [design, governanca, ciclo-de-vida, cowork, decision-register, veredito, responsabilidade, tier-0, ds-guard]
supersedes: []
superseded_by: []
related:
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0291-distiller-modulo-verdade-contrato-emenda-0270-f3
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0281-dark-mode-bridge-data-theme-tokens
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

> **Proposta por [CC] em 2026-06-19.** Ratificação formal = merge por [W] (convenção [ADR 0270]).
> Direção dada por Wagner no chat 2026-06-19: *"vai ser o responsável por cada parte. e a decisão
> deve ficar guardada e como foi feita ou decidida?"* — durante o piloto de ingestão do handoff Cowork.

# ADR 0293 — Governança da decisão de design (responsável + registro + retorno)

## Contexto (verificado em `origin/main`)

A esteira de ingestão de design ficou pronta e provada: `design:ingest-zip` (diff por conteúdo
sobre os roteados — [#3041]) + `cowork-map` v2 (rota por prefixo de tela — [#3042]) levam um
**handoff completo do Cowork → `prototipo-ui/prototipos/<tela>/`** com gates (`ds-guard`,
`integrity-check`). Isso cobre **design → fonte**.

O que **faltava** era a governança do resto do ciclo (etapas 4–6 do [ADR 0270]): para **cada
decisão de design**, (a) **quem é o responsável** e (b) **onde fica registrado o que foi
decidido, como e por quê** — incluindo o **retorno do que foi rejeitado** pro design refazer.

O gatilho concreto: o handoff "n" da Caixa Unificada trouxe um **dark bespoke** (`--omd-*`,
13 tokens; a baseline tinha 0). O `ds-guard` barrou (L-02) e o [ADR 0281] (dark por
`[data-theme="dark"]`, token canônico, **sem paleta por-tela**) dá o padrão correto — mas **não
havia onde registrar a decisão nem canal pra devolver ao Cowork**. Esta ADR crava essa governança.

## Decisão

### D-A — Responsável por etapa do ciclo (quem decide o quê)

| Etapa | Responsável | Natureza |
|---|---|---|
| Ingestão (handoff → `prototipos/`) | **[CC]** | mecânico (rota + diff) |
| `cowork-map` (rota das telas) | [CC] propõe → **[W] ratifica** (merge) | canônico |
| Gates `ds-guard` / `integrity-check` | **automático** | passa/barra (gera veredito) |
| Aplicar na fonte (`prototipos/<tela>/`) | [CC] executa sob gate | mecânico |
| **Cor / identidade / dark / tokens / DS** | **[W]** (Tier-0) ou devolve a **[Design/Cowork]** | Tier-0 |
| Aplicar na vida real (tela Inertia) | [CC] migra (MWART) → **[W] aprova screenshot** | gate visual ([ADR 0107]) |
| Refazer o rejeitado | **[Design/Cowork]** | design |

Regra-mestre: **decisão Tier-0 (cor/identidade/token/DS/constituição) é sempre [W]** ([ADR 0094]
princípio 7 + invariante #10 do método). [CC] propõe e executa o mecânico; nunca decide Tier-0 sozinho.

### D-B — Registro por tela: Decision Register (`<tela>.decisoes.md`)

Cada decisão de design de uma tela é registrada no **Decision Register irmão** do charter
(padrão `D-NN` já em uso — ex. `prototipo-ui/prototipos/producao-oficina/OficinaProducao.decisoes.md`;
o `integrity-check` IT2 exige o par charter↔decisoes). Schema mínimo por entrada:

```
D-NN · <título curto>
  responsável: [W] | [CC] | [Design]
  detecção:    <o que disparou — gate, review, sinal>
  padrão:      <ADR/regra canônica que se aplica>
  opções:      <as alternativas consideradas>
  status:      PENDENTE [W] | DECIDIDO (<como/por quê>) | APLICADO (<PR>) | DEVOLVIDO ([Design])
```

O **anel** (Avaliar→Testar→Adotar→Descartar) do método continua valendo; o Register é onde o
debate vive até gradar pro charter como `✅`.

### D-C — Ledger de vereditos: `governance/design-requests/` (retorno pro Cowork)

O que **não** foi aprovado (gate barrou ou [W] rejeitou) vira um **veredito append-only** em
`governance/design-requests/` — com **motivo + padrão a seguir** — que o **próximo handoff do
Cowork lê** antes de refazer. É a **etapa 6** (retorno) do [ADR 0270], materializada. Fecha o loop
Cowork↔Code ([ADR 0114]) com um canal estruturado em vez de "vira lição solta".

### D-D — Gate = decisor automático com veredito explícito

Quando `ds-guard`/`integrity-check` barram, **não é erro silencioso**: gera um veredito (D-C) com
o motivo (ex. "paleta `--omd-*` viola L-02 / [ADR 0281]"). "Defesa que dispara > regra que se lê"
(método NÚCLEO #5). A aplicação só prossegue quando a decisão Tier-0 correspondente for tomada por [W].

## Consequências

**Positivas**
- Toda decisão de design passa a ter **dono** e **rastro** (o quê, quem, como, por quê) — responde
  "como foi decidido aplicar cada protótipo" e "o que volta pro design".
- O retorno ao Cowork deixa de ser informal; vira ledger que o próximo handoff consome.
- Reusa o que já existe (Decision Register + gates), **sem cunhar 5º placar** ([ADR 0270] D-6).

**Riscos / pegadinhas**
- Disciplina: exige registrar a decisão **na hora** (senão o rastro fura). Mitigado porque o gate
  já força o veredito quando barra.
- `governance/design-requests/` é novo — manter **append-only** e fora do escopo de tela (não rotear).

## Roadmap de PRs (cada ≤300 linhas · 1 intent)

- **PR-A (este):** ADR 0293 + `governance/design-requests/` (README + 1º veredito: dark da Caixa). `docs`.
- **PR-B+:** ao aplicar cada tela, registrar as decisões no Decision Register e os rejeitados no ledger.

## Referências

- [ADR 0270] ciclo de vida da informação (etapas 4–6 que esta ADR governa)
- [ADR 0291] distiller (registro datado/proveniência — padrão herdado) · [ADR 0114] loop Cowork↔Code
- [ADR 0107] gate visual F3 · [ADR 0281] dark por `[data-theme="dark"]` (padrão do caso-gatilho)
- [ADR 0094] Constituição v2 (princípio 7 transparência · soberania [W]) · método NÚCLEO #5/#10
- Estação: [#3041] (diff sobre roteados) · [#3042] (cowork-map v2)
