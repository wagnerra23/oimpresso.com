---
slug: 0114-prototipo-ui-cowork-loop-formalizado
number: 114
title: "Loop Cowork ↔ Claude Code formalizado via prototipo-ui/"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-09'
quarter: 2026-Q2
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0109-claude-design-plugin-integrado-processo-mwart
  - 0110-cockpit-pattern-v2-canon-list-detail
emends:
  - '0109'
pii: false
---

# ADR 0114 — Loop Cowork ↔ Claude Code formalizado via `prototipo-ui/`

**Status:** ✅ Aceita
**Data:** 2026-05-09
**Decisão por:** Wagner Rocha
**Emenda:** ADR 0109 (Claude Design plugin) — adiciona protocolo de loop entre Cowork app e Claude Code local
**Não supersede:** ADRs 0094, 0104, 0107, 0110

---

## Contexto

ADR 0109 (2026-05-08) introduziu **Claude Design plugin Anthropic** como sub-skills da `mwart-comparative` V3. O loop "design supervisionado estado-da-arte" funcionou no PR #257 (Sells/Create refator critique-driven).

Em 2026-05-09 Wagner trouxe um padrão complementar: **Anthropic Cowork app** — outro Claude (separado deste) que faz protótipo visual rápido em `<tela>/page.tsx` exportado como zip. O fluxo idealizado era:

```
Cowork (web app)        Repo Laravel
    [CC]                    [CL]
   protótipo  →  zip  →  prototipo-ui/<tela>/
                              ↓
                          Inertia/React real
```

Mas faltavam:

1. **Protocolo formal** — quem escreve onde, quando avança fase, quem aprova
2. **Briefing pra cada Claude** — Cowork não conhece personas/tokens do oimpresso, faz design genérico
3. **Loop bidirecional** — Cowork não sabe responder perguntas pra Wagner sem canal de comunicação
4. **Métricas de saúde** — sem alarme se uma tela ficar parada em F3 por dias
5. **Tradução genérico → Inertia** — sem glossário, Claude Code reinventa cada vez

Sem isso, o loop ficaria **frágil** e dependeria da memória de cada sessão pra funcionar.

## Decisão

Criar diretório `prototipo-ui/` no repositório raiz como **interface formal** entre Cowork e Claude Code, com 6 papéis, 7 fases (F0 a F4 com 1.5 e 3.5), 13 arquivos canônicos.

### Estrutura

```
prototipo-ui/
├── README.md                      ← landing
├── PROTOCOL.md                    ← regras formais 6 papéis × 7 fases
├── CLAUDE_CODE_BRIEFING.md        ← briefing pra [CL]
├── CLAUDE_DESIGN_BRIEFING.md      ← briefing pra [CC]/[CD]
├── COWORK_NOTES.md                ← INBOX [W] → [CC]/[CD]
├── CODE_NOTES.md                  ← OUTBOX [CL] → [W]
├── SYNC_LOG.md                    ← timeline append-only
├── HANDOFF.md                     ← estado vivo (sobrescrito)
├── TELAS_REVIEW_QUEUE.md          ← fila P0/P1/P2/P3
├── GLOSSARY.md                    ← mapa Cowork-genérico → Inertia/shadcn
├── templates/
│   ├── critique.md.template
│   ├── handoff-spec.md.template
│   └── charter-from-design.md.template
└── prototipos/<tela-kebab>/
    ├── page.tsx                   ← export Cowork (commitado, read-only)
    ├── COMPARISON.md              ← 15 dimensões mwart-comparative
    ├── critique-score.json        ← score 0-100
    └── a11y-report.md             ← WCAG 2.1 AA
```

### 6 papéis

| Sigla | Quem | Onde |
|---|---|---|
| **[W]** | Wagner | local + chat |
| **[CC]** | Claude Cowork | Anthropic Cowork web app |
| **[CD]** | Claude Design | Cowork OU plugin local |
| **[CL]** | Claude Code | repo Laravel |
| **[CA]** | Claude Accessibility | plugin local |
| **[W2]** | Wagner | aprovação SCREENSHOT + merge |

### 7 fases

```
F0 BRIEF       [W]  → COWORK_NOTES.md
F1 DESIGN      [CC] → prototipos/<tela>/page.tsx
F1.5 CRITIQUE  [CD] → critique-score.json (≥80 ok)
F2 SCREENSHOT  [W2] → aprovação síncrona (não tabela)
F3 CODE        [CL] → resources/js/Pages/<Mod>/<Tela>.tsx
F3.5 A11Y      [CA] → a11y-report.md (WCAG 2.1 AA)
F4 MERGE       [W2] → PR merge
```

Critérios de transição em [PROTOCOL.md §3](../../prototipo-ui/PROTOCOL.md).

### Override autorizado

3 overrides separados (resposta a pergunta 4 da proposta):

- `/design-override <razão>` — pula F1.5 (tela trivial)
- `/screenshot-override <razão>` — pula F2 (Wagner já viu Cowork)
- `/a11y-override <razão>` — pula F3.5 (interno superadmin)

Cada uso vira ADR per-tela `lifecycle: historical`.

> Nota: respostas das perguntas 3 (trigger F3) e 5 (ADR nova vs emend) ficaram em aberto pra Claude Design responder em `COWORK_NOTES.md`. Esta ADR assume default proposto (a) em ambas até resposta.

### Decisão sobre `prototipos/<tela>/page.tsx`

**Commitado** (resposta a pergunta 2). Razão: rastreabilidade total do que o Cowork produziu — diff visual no histórico do repo, possível voltar versão anterior se Cowork piorar tela em re-export. Custo: ruído em PR. Mitigação: PR de F1 tem label `cowork-export` e `[skip ci]`, PR de F3 (que mexe em Inertia real) é o que importa pra review.

## Skill `mwart-comparative` V4 (delta vs V3)

3 passos novos:
- **Passo 0:** ler `prototipo-ui/HANDOFF.md` antes de qualquer coisa
- **Passo 12.5:** gravar critique score em `prototipos/<tela>/critique-score.json`
- **Passo 24:** append em `SYNC_LOG.md` ao terminar

## Métricas de saúde (a adicionar em `jana:health-check`)

| Check | Falha quando |
|---|---|
| `design_loop_stuck` | tela em F3 há +7d |
| `design_critique_skipped` | protótipo sem critique-score.json |
| `design_a11y_skipped` | merge sem a11y-report.md |

## Consequências

### Boas

- **Loop formal** — qualquer Claude novo (na sessão ou no time) lê `PROTOCOL.md` e entende
- **Briefing isolado** — Cowork não precisa carregar contexto inteiro do oimpresso, só o que importa
- **Glossário** — tradução Cowork → Inertia deixa de ser reinvenção
- **Histórico rastreável** — `SYNC_LOG.md` é fonte única de "o que aconteceu quando"
- **Backpressure** — `HANDOFF.md` mostra o que está em voo, evita 2 telas simultâneas em F3
- **Override granular** — Wagner não precisa pular protocolo inteiro pra fix trivial

### Ruins / mitigações

- **Mais arquivos pra manter** (13 novos). **Mitigação:** estrutura simples, append-only onde possível, `HANDOFF.md` é o único sobrescrito
- **Cowork desconectado** do repo (não pode commitar direto). **Mitigação:** Wagner copia e cola via export zip; eventualmente Anthropic pode oferecer GitHub integration
- **Pode virar burocracia** se aplicado a tudo. **Mitigação:** só telas com mudança visual real; bug fix sem visual ignora `prototipo-ui/`
- **Dependência do Cowork** se virar canal único. **Mitigação:** plugin local (`design:design-critique`) pode rodar sem Cowork — protocolo aceita `[CD]` local

## Plano de aplicação

1. **Hoje (este PR):**
   - [x] Criar `prototipo-ui/` com 13 arquivos
   - [x] ADR 0114 (este)
   - [x] Skill `mwart-comparative` V3 → V4
   - [x] CLAUDE.md aponta pro `prototipo-ui/PROTOCOL.md`
   - [x] 3 perguntas em `COWORK_NOTES.md` pra Claude Design responder

2. **Próxima sessão:**
   - Wagner cola perguntas no Cowork → Claude Design responde
   - Resposta volta no `COWORK_NOTES.md` editado
   - Wagner (ou [CL]) ajusta protocolo conforme respostas

3. **Primeira tela real:**
   - P0: `Sells/Create` (já tem charter + foi refatorada por critique no PR #257)
   - Wagner adiciona pedido em `COWORK_NOTES.md`
   - Cowork produz `prototipos/sells-create/page.tsx`
   - Loop completo F0 → F4

4. **Métricas saúde** (próximo cycle):
   - Adicionar 3 checks em `jana:health-check`
   - Daily 06:00 BRT alerta loops parados

## Refs

- [Claude Design plugin docs](https://docs.anthropic.com/en/docs/claude-code/plugins#design)
- [ADR 0094 — Constituição V2](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0104 — Processo MWART](0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual gate F1.5](0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0109 — Claude Design plugin integrado](0109-claude-design-plugin-integrado-processo-mwart.md) (emendado por este)
- [ADR 0110 — Cockpit V2 canon](0110-cockpit-pattern-v2-canon-list-detail.md)
- [prototipo-ui/PROTOCOL.md](../../prototipo-ui/PROTOCOL.md) — protocolo formal
- [prototipo-ui/README.md](../../prototipo-ui/README.md) — landing

---

**Última atualização:** 2026-05-09
