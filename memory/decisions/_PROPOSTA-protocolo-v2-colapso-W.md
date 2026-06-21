# _PROPOSTA · Protocolo v2 (colapso) — só [W] decide · Tier 0

> **Status:** PROPOSTA de [CC]. **NÃO é lei.** [CC] propõe, **nunca edita/numera/versiona** a constituição
> (PROTOCOL.md · CLAUDE_DESIGN_BRIEFING.md · ADR 0114 · ADR 0094 · UI-0013). Mexer nelas = **Tier 0 = [W]**.
> Se [W] aprovar, o Code numera+versiona sob OK de [W] (regra de soberania 2026-05-31).
> **Origem:** `O Adversario vs Protocolo Nativo.html` (red team [CC] 2026-06-16) + os 2 adversários anteriores.

---

## 1. Diagnóstico — o protocolo é cicatriz de DUAS restrições, não design

| Restrição | Gera | Natureza |
|---|---|---|
| **R1 · Cowork é read-only no git** | transporte zero-toque · `COLE_NO_CODE` · fila `COWORK_NOTES` · espinha de memória espelhada · **Regra 6** · `memory-health` anti-órfão | **só CUSTO** (some sem perda) |
| **R2 · design vive fora do repo** | F1 (protótipo Cowork) + F3 (tradução →TSX) | **VALOR** (a razão do Cowork) |

Tudo que o nativo (agente-no-git + GitHub + CI) faz melhor — intake (Issues), aprovação (preview PR),
a11y (`a11y-axe-gate.yml` **já existe**), merge (branch protection), memória (git history) — o custom
**re-implementa em markdown + revezamento humano, pior**. O teste honesto por peça:
**"isto sobreviveria se o agente pudesse commitar numa branch?"** Se não → é andaime da R1.

## 2. A descoberta que destrava — R1 JÁ está meio-resolvida

O repo **já tem** `.github/scripts/cowork-inbox.py` + `cowork-inbox.yml`: um push pra `cowork-inbox/`
com header `<!-- cowork: target: ... -->` vira **auto-PR → auto-merge** pra `prototipo-ui/`, `memory/`, `docs/`.
**Isso é um write-path automático do Cowork pro git** — pra memória e protótipos. O protocolo
simplesmente **não se apoia nele**: continua com transporte manual (`COLE_NO_CODE` + [W] cola).
Falta cobrir só **código** (`resources/js/**`), que o inbox não sincroniza por segurança.

## 3. Proposta — Protocolo v2: 6 papéis → 2 · 7 fases → 3 · memória → o próprio git

### 3.1 Memória para de espelhar (mata R1 no flanco da memória — efeito imediato, baixo risco)
- A **espinha local deixa de ser fonte da verdade**. `git` é a memória (history + ADRs + sessions no repo).
- Cowork **escreve memória via `cowork-inbox`** (já automático) — não via [W] colando.
- **Regra 6 vira moot** (não há espelho pra ficar stale). `memory-health` anti-órfão idem (não há fila separada).
- `STATUS.md`/`MEMORY_INDEX.md` viram **cache de leitura descartável**, não autoridade.

### 3.2 Intake para de ser fila markdown
- Pedido vira **Issue** (label/assignee/notificação/link-pro-PR) **ou** um item `cowork-inbox`.
- A fila `COWORK_NOTES → Pendentes` (append-only que apodreceu: 18 órfãos + 19 refs mortas) **morre**.

### 3.3 As fases viram CHECKS, não papéis humanos
| Fase v1 | v2 |
|---|---|
| F1.5 critique [CD] | **CI** `critique-score` (já é JSON) |
| F2 screenshot [W2] | **CI** screenshots no PR (Playwright/Chromatic) — assíncrono, versionado |
| F3.5 a11y [CA] | **CI** `a11y-axe-gate.yml` (**já existe**) |
| F4 merge [W2] | **branch protection** (required checks + 1 review) — não depende de [W] online |

### 3.4 Papéis: 6 → 2
- **[CC] designer-agente** — F1 protótipo + abre o PR (protótipo + tarefa de tradução).
- **[W] aprovador** — revisa o PR, aprova; branch protection mergeia.
- **[CD]/[CA]/[W2] → CI.** **[CL] → o próprio [CC] commitando** numa branch, ou um agente de tradução no mesmo repo.

## 4. O que NÃO muda (o ouro — não tocar)
- **F1 · loop de protótipo visual** (`inbox-page.jsx`): fidelidade antes do código. É a razão do Cowork. **Investir tudo aqui.**
- **ADRs** (Nygard, numerados, no git) — já são nativos-bons.
- **Tokens canônicos / personas / Cockpit V2** (CLAUDE_DESIGN_BRIEFING) — design system intacto.
- **Os gates de CI do repo** — já são a trava física certa.

## 5. Migração em ondas (NÃO big-bang — cada onda é reversível)
1. **Onda A · memória** (menor risco, maior alívio): parar de tratar a espinha como autoridade; apontar tudo pro git; aposentar Regra 6 + `memory-health` anti-órfão. Não toca código de produto.
2. **Onda B · intake**: migrar a fila pra Issues / `cowork-inbox`; congelar `COWORK_NOTES → Pendentes`.
3. **Onda C · fases→CI**: garantir critique-score + screenshots + axe como required checks; aposentar F1.5/F2/F3.5 como papéis.
4. **Onda D · code write-path**: estender `cowork-inbox` (ou dar branch ao Cowork) pra cobrir `resources/js/**` atrás de review — aí o `COLE_NO_CODE` e o transporte manual **deixam de existir**.
5. **Onda E · ratificar PROTOCOL v2** (Tier 0, [W] numera): consolidar o colapso em ADR + PROTOCOL.md.

## 6. O único trabalho técnico que destrava o colapso
**Dar ao Cowork um caminho de escrita pro git que cubra código** (não só docs/protótipos). Hoje:
`cowork-inbox` cobre `prototipo-ui/`/`memory/`/`docs/`. Estendê-lo pra abrir **branch + PR de código**
(atrás de review humano, nunca auto-merge em `resources/js/**`) elimina R1 — e com ela, ~70% do protocolo.

---

**Decisão = [W].** [CC] não numera nem aplica nada disto. Se [W] disser "vai", o Code ratifica em ADR sob OK de [W].
Pergunta única pra [W]: **começamos pela Onda A (memória para de espelhar)?** É a de menor risco e maior alívio imediato.
