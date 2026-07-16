# PROPOSTA · Profissionalizar o Design System — sync git↔espelho + reconciliar o canvas dark (FASE 1)

> **Status:** PROPOSTA. **NÃO é lei, NÃO é ADR numerado.** [CL] rascunha; **[W] decide, numera e aprova o SCREENSHOT buildado** (Tier 0 Fundações — Constituição UI v2 · UI-0013; soberania [ADR 0238](../0238-soberania-constituicao-wagner.md)).
> **Build sobre:** [ADR 0239](../0239-governanca-design-system-git-ssot-regressao-ia.md) (DS git = SSOT) · [ADR 0249](../0249-ds-v6-naming-amends-0235.md) (DS v6 naming) · [ADR 0300](../0300-errata-0239-nome-real-fonte-design-system.md) (errata — nome real da fonte) · [ADR 0281](../0281-dark-mode-bridge-data-theme-tokens.md) (bridge dark `[data-theme=dark]`) · [ADR 0315](../0315-design-sync-claude-design-vs-cowork-charter.md) (`/design-sync`/`DesignSync`: claude.ai/design é **espelho**, não fonte).
> **Origem:** Wagner viu "cores diferentes / fundo estranho" em `/contacts`. Investigação (sessão 2026-07-08) achou o canvas dark divergindo em **3 cópias** + a causa-raiz: **não existe loop de sincronização** entre o git (SSOT) e o projeto-espelho no claude.ai/design.

---

## 1. O que a investigação achou (números verificados, não opinião)

O **canvas dark** do shell operacional (`.cockpit --bg`, o que pinta telas como `/contacts`) existe hoje em **três cópias divergentes**:

| Cópia | Store | `--bg` dark | Perfil |
|---|---|---|---|
| **Prod/git (SSOT)** | `resources/css/tokens/semantic.tokens.json` → `cockpit.surface.bg.$extensions.com.oimpresso.dark` | `oklch(0.165 0.008 282)` | **escuro** (L 0.165), **violeta** (hue 282), quase neutro |
| **Snapshot ds-v6 (jun/congelado)** | `prototipo-ui/cowork/ds-v6` | `oklch(0.205 0.008 282)` | violeta, um degrau mais claro |
| **Espelho claude.ai/design** | projeto `019dd02f-…` → `colors_and_type.css` (`.cockpit[data-theme=dark] --bg`) | `oklch(0.26 0.006 240)` | **mais claro** (L 0.26), **azul-frio** (hue 240) |

O que o Wagner enxerga em `/contacts` (prod) vs o design que aprovou é uma diferença de **dois eixos**:
- **Lightness:** prod `0.165` (perto do preto) vs design `0.26` (grafite/slate mais claro) — provável "fundo estranho".
- **Hue:** prod `282` (tinta roxa) vs design `240` (cinza-azulado frio).

### Companheiros do canvas (mesma divergência, para o comparativo ser fiel)

| Token cockpit (dark) | Prod/git | Espelho design |
|---|---|---|
| `--bg` (canvas) | `oklch(0.165 0.008 282)` | `oklch(0.26 0.006 240)` |
| `--surface` (cards/painéis) | `oklch(0.205 0.009 282)` | `oklch(0.30 0.008 240)` |
| `--border` | `oklch(0.335 0.012 282)` | `oklch(0.34 0.008 240)` |
| `--text` | `oklch(0.965 0.004 282)` | `oklch(0.94 0.005 90)` |
| `--sb-bg` (sidebar dark-fixa) | `oklch(0.18 0.006 282)` | `oklch(0.18 0.006 240)` |

**Divergência secundária (camada @theme inertia, `bg-background`):** git `--color-background` dark `oklch(0.165 0.008 282)` vs espelho `oklch(0.137 0.036 258.5)` (o default shadcn azul-saturado). Fica **fora do escopo P0** (a maioria das telas cockpit pinta com `--bg`, não `--color-background`), mas entra no P1 do loop de sync para não voltar a driftar.

### O que **já** está alinhado (não reabrir)

- **Accent/primary roxo hue 295** — emenda 2026-07-08. Ambos os lados usam hue 295. Resíduo só de lightness no dark (git primary dark `oklch(0.7 0.15 295)` vs espelho `oklch(0.62 0.15 295)`) — item menor, tratado no loop, **não** é o gap desta fase.

## 2. Causa-raiz — o gap real não é "cor errada", é **loop de sync ausente**

O [ADR 0239](../0239-governanca-design-system-git-ssot-regressao-ia.md) fixa **git = SSOT** do Design System, e o [ADR 0315](../0315-design-sync-claude-design-vs-cowork-charter.md) classifica o projeto no claude.ai/design como **espelho derivado** (o próprio `README.md` do projeto diz *"derived from wagnerra23/oimpresso.com @ commit 5390c5a2cd8f"*). Mas **não existe mecanismo que mantenha o espelho em dia com o git**. Resultado: alguém editou o canvas no canvas do claude.ai/design (→ `0.26 / hue 240`) e o git seguiu no seu valor (`0.165 / hue 282`) — **drift silencioso**, exatamente o vetor que a 0239 existe para prevenir.

**Corolário de governança:** a direção legítima do `DesignSync` é **pull read-mostly a PARTIR do git aprovado** (git → espelho), nunca o inverso (espelho → git como se fosse fonte). Reconciliar significa: **decidir o valor no git** e depois **espelhar pro claude.ai/design**, não copiar o espelho pra dentro do git no escuro.

## 3. Plano — P0 a P4

### P0 · Reconciliar o canvas dark (ESTA FASE 1) — decisão de [W] por imagem
1. Montar **comparativo visual** dos candidatos de canvas renderizados em chrome cockpit realista (sidebar + canvas + card + texto + borda + botão primary) — **[W] escolhe POR IMAGEM, não por oklch**.
2. Candidatos: **(A)** manter git `0.165/282`; **(B)** snapshot jun `0.205/282`; **(C)** adotar espelho `0.26/240`; **(D)** um reconciliado (ex.: manter hue 282 e clarear L, ou adotar hue 240 e ajustar L) — [W] pode pedir um ponto novo.
3. Gravar o escolhido em `semantic.tokens.json` (`cockpit.surface.bg` + companheiros `--surface`/`--border`/`--text`/`--sb-bg` no `com.oimpresso.dark`).
4. `npm run tokens:build` regenera `_generated-cockpit-dark.css` (+ inertia/foundations dark). PR **draft**.
5. **Gate Tier 0:** [W] aprova o **screenshot buildado** (prod/staging) **antes** de qualquer merge/deploy. Fundações = muda toda tela, todo negócio.

### P1 · Estabelecer o loop de sync git → espelho (pull-only)
- Formalizar o uso de `/design-sync` + `DesignSync` na direção **git aprovado → claude.ai/design**, incremental (um componente/arquivo por vez, nunca replace atacado — a própria tool exige `finalize_plan`).
- Espelhar `colors_and_type.css` do espelho a partir do `_generated-*.css` do git após cada mudança de token aceita. Opt-in Wagner explícito por sessão (hooks `block-(skill-)design-sync-without-optin`).

### P2 · Sentinela de drift (fingerprint git vs espelho)
- Check que compara o fingerprint dos tokens no git (`semantic.tokens.json` / `_generated-*.css`) com o `colors_and_type.css` do espelho e **alerta** quando divergirem (advisory primeiro; promover a gate só depois de estável — política ADR 0314: required = só Tier-0).

### P3 · Profissionalizar a vitrine do espelho
- Garantir cards `@dsCard` + `_ds_manifest.json` coerentes; previews (`preview/colors-*.html`) refletindo o token vigente do git; README do espelho apontando o commit-fonte atualizado.

### P4 · Documentar o método
- RUNBOOK curto "sync DS git → espelho" (quando/como/opt-in) + atualizar `.claude/runbooks/design-sync.md` (hoje descreve só o handoff canvas→código, não o espelhamento token→vitrine).

## 4. Decisão que depende de [W] agora

**Só o P0 está no caminho crítico desta sessão.** [W] precisa **escolher o canvas dark por imagem** (comparativo em anexo). Sem essa escolha, nada é gravado em `semantic.tokens.json` e nenhum PR abre. **[CL] não escolhe a paleta.**

## 5. Gates (Tier 0 Fundações — IRREVOGÁVEIS)

- ⛔ **Fundações imutável via ADR** — canvas é camada Fundações (Constituição UI v2). Mudança só com [W] aprovando.
- ⛔ **[W] aprova o SCREENSHOT buildado** (não tabela, não oklch) **antes** de merge/deploy — R1 smoke real + R2 cópia literal do design aprovado + R10.
- ⛔ **git = SSOT** — o valor canônico nasce no git; o espelho é atualizado DEPOIS. Espelho **nunca** vira fonte concorrente ([ADR 0239](../0239-governanca-design-system-git-ssot-regressao-ia.md)/[0315](../0315-design-sync-claude-design-vs-cowork-charter.md)).
- ⛔ **`/design-sync` só com opt-in [W]** — direção pull-only git→espelho.

---

**Rodapé de evolução**
- 2026-07-08 — [CL] rascunho FASE 1 (P0 canvas dark). Números do canvas verificados nos dois stores (git `semantic.tokens.json` @ origin/main + espelho `colors_and_type.css` via `DesignSync get_file`). Aguardando [W] escolher paleta por imagem.
