# PROPOSTA · DS FASE 2 — direção de autoria design→git + sidebar preto-fixa + reconciliação de tokens dark

> **Status:** PROPOSTA. **NÃO é lei, NÃO é ADR numerado.** [CL] rascunha; **[W] decide, numera e aprova o SCREENSHOT buildado** (Tier 0 Fundações; soberania [ADR 0238](../0238-soberania-constituicao-wagner.md)).
> **Irmã de:** [`2026-07-08-profissionalizar-ds-sync-git-espelho.md`](2026-07-08-profissionalizar-ds-sync-git-espelho.md) (FASE 1, canvas — PR #3981).
> **Emenda:** [ADR 0315](../0315-design-sync-claude-design-vs-cowork-charter.md) (direção) + [ADR UI-0009](../../requisitos/_DesignSystem/adr/ui/0009-cockpit-sidebar-light-padrao.md) / UI-0014 (sidebar).
> **Origem (Wagner, verbatim 2026-07-08):** *"consegue pegar direto do DS vivo? fazer um protocolo para isso?"* + *"gostaria que pegasse direto do design"*. Decisões colhidas por imagem/pergunta nesta sessão.

---

## D-1 · Direção de autoria: design→git (emenda 0315)

**Decisão [W]:** o **Claude Design** (claude.ai/design) passa a ser a **superfície de autoria** de tokens do DS — Wagner desenha lá e o agente **puxa determinístico pro git**. O git **continua SSOT** ([ADR 0239](../0239-governanca-design-system-git-ssot-regressao-ia.md)): é o que o `deploy.yml` publica e o CI valida. O espelho nunca é fonte concorrente; ele é ponto de partida, o git é registro + enforcement.

A 0315 dizia "vitrine read-mostly a partir do git; **nunca o inverso**". Esta emenda **permite o inverso de forma controlada**: design→git **via PULL determinístico + triagem + gate de screenshot**, nunca dump. Mecânica no runbook [`design-sync-pull.md`](../../../.claude/runbooks/design-sync-pull.md) + motor `scripts/design-sync/ds-token-diff.mjs`.

**Salvaguarda que a torna segura (não é cheque em branco):** o espelho **não** está uniformemente à frente do git. O diff de 2026-07-08 provou: **19 das 28 divergências eram o design VELHO** (shadcn azul hue 258, "superseded" pelo próprio README). Regra: adota design→git **só onde o design está intencionalmente à frente** (Wagner editou); onde é stale, **mantém git + re-espelha git→design**.

## D-2 · Sidebar preto-fixa (emenda UI-0009/UI-0014)

**Decisão [W]:** a sidebar do cockpit é **DARK-FIXED** — menu preto nos **dois** modos (claro e escuro), não só no escuro. Isto **supersede** a UI-0009/UI-0014 ("sidebar light padrão"). Fonte concordante: o README do DS já registrava *"Sidebar is DARK-FIXED in the real cockpit (Wagner: 'menu fundo black')"* — a UI-0009 tinha ficado pra trás.

Implementação: os 10 tokens `cockpit.surface.sb-*` passam a ter `$value` (light) = valor dark (hue 240), ficando idênticos nos dois modos.

## D-3 · Reconciliação dos tokens dark (coerência 240)

**Decisão [W]:** alinhar o dark inteiro ao canvas hue 240 escolhido na FASE 1, mantendo o primary clareado.

| Grupo | Antes (git) | Depois | Racional |
|---|---|---|---|
| `@theme` canvas (`--color-background`, `--color-page-cream`) dark | `0.165 · 282` | `0.26 · 240` | alinha `bg-background` ao canvas do cockpit |
| `@theme` superfícies (`--color-card`, `--color-popover`) dark | `0.205 · 282` | `0.30 · 240` | mantém elevação (card mais claro que o canvas 0.26) |
| `@theme` bordas (`--color-border`, `--color-input`) dark | `0.335 · 282` | `0.34 · 240` | coerência |
| `@theme` neutros restantes (secondary/muted/accent/ring/*-foreground) dark | hue `282` | hue `240` (L/C mantidos) | mata o hue misto no dark |
| `--color-primary` dark | `0.7 · 295` | **mantido** | emenda de hoje, fidelidade ao proto |
| status (destructive/success/warning/info) | — | **intocado** | semântica não muda |
| cockpit `--accent-soft` dark | `0.30/0.07` | `0.32/0.06 · 295` | alinha ao espelho |

**Os 19 @theme "stale" NÃO entram no git** (decisão [W]): git está correto; o design é que será refrescado (git→design, passo 7 do runbook) depois do merge.

## Impacto (antes→depois, prova do diff)
- Divergências design↔git: **28 → 19** (as 19 restantes = design stale, resolvidas por re-espelho pós-merge).
- `cockpit-light` e `cockpit-dark`: **0 divergências**.
- Arquivos git: `semantic.tokens.json` (29 tokens) + `_generated-inertia-dark.css` + `_generated-cockpit-light.css` + `_generated-cockpit-dark.css`.

## Gates (Tier 0 Fundações — IRREVOGÁVEIS)
- ⛔ Muda **modo claro inteiro** (sidebar) + **dark inteiro** (canvas/superfícies). **[W] aprova o SCREENSHOT buildado** antes de merge/deploy (R1/R2/R10). `deploy.yml` roda em push no `main` → **merge = deploy**.
- ⛔ D-1 e D-2 são **emendas de ADR** — só valem com [W] numerando/aceitando (append-only: novas ADRs com `supersedes`, não editar as antigas).

---

**Rodapé de evolução**
- 2026-07-08 — [CL] rascunho FASE 2. Tokens reconciliados no git (verificado por `ds-token-diff.mjs`). Aguardando [W] aprovar screenshot buildado + numerar as emendas 0315/UI-0009.
