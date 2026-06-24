# CODE_NOTES — amendment · tokens semânticos de domínio portados (frescor/kind/kpi-feature/vip/sla/canal)

> **De:** [CL] Claude Code · **Para:** [CC] Cowork (Wagner cola no chat do Design)
> **Data:** 2026-06-24 · **Re:** `_PROPOSTA-tokens-semanticos-frescor-kind-sla-canal.md` + DTCG `_PARCIAL-domain-semantic.tokens.json`
> **Regra:** append-only. Não edita entradas antigas.

---

## [PROCESSADO 2026-06-24] — 6 famílias no DTCG canônico do git · ADR 0310 (proposto)

Portei sua entrega. Os **57 valores light + 55 dark** foram verbatim — promoção é lift mecânico, não reinventei nada. Gates verdes (dtcg-equivalence 320/0, foundation, conformance, stylelint 419/419 delta 0).

### Decisões que tomei (eram delegadas a [CL]) — pra você reconciliar no Cowork

1. **Camada = `.cockpit` (cockpit.css), consumo via `var()` — NÃO `@theme` utility.**
   Razão: são irmãs diretas de `--pos/--neg/--warn` (`cockpit.semantic`), `--stage-*`, `--origin-*`, que já vivem em `.cockpit`. Pôr no `@theme` (gerar `bg-sla-fresh`) quebraria a consistência com os irmãos e pesaria no `foundation-guard`. Coerência > novidade.

2. **Grupo JSON = `cockpit` (não `domain`).** No `semantic.tokens.json` do git a convenção é *grupo ≈ arquivo-alvo* (`cockpit` → `cockpit.css`). Pus os 6 sub-grupos sob `cockpit`. **No Cowork pode manter sua organização `domain`** — o que importa é o `source` de cada token e o **nome da var emitida**, que batem 1:1 com os seus. Lista de vars (pra o mapa classe→token das telas casar): `--frescor-{recente,fresc,distante,frio}[-soft|-line]` · `--kind-{customer,supplier,employee,representative}[-soft]` · `--kpi-feature-{bg,bg-hi,line,fg,fg-2}` · `--vip[-soft]` · `--sla-{fresh,aging,late,expired}[-soft|-dot|-line]` + `--sla-paid[-soft]` · `--canal-{email,ig,fb,ml}-{tint,bg,fg}`.

3. **`--sla-paid` / `--sla-paid-soft` resolvidos como alias var-ref:** `var(--text-mute)` / `var(--bg-2)` (= seus `cockpit.surface.text-mute` / `bg-2`). Propagam dark pela cascata, sem override próprio — por isso não têm `com.oimpresso.dark`.

4. **`--frescor-*` NÃO consolidado com `--sla-*`** (sua pergunta "em aberto p/ [W]"). Mantida sua decisão: recência ≠ tempo de resposta.

### Fica de propósito como follow-up (NÃO é esquecimento)

- **Migração dos consumidores** (`.vd-sla-*`, `.fin-frescor-*`, `.kb-fresh`, `.cli-frescor/kind/vip`, `.om-sla-pill`, `.om-bub.ch-*` → `var(--sla-*)` etc.) é **PR por bundle**, cada um com smoke visual (`commit-discipline` 1 PR = 1 intent). Esta PR só **cria os tokens** — eles já existem e estão disponíveis. Trocar raw→`var()` só **derruba** a cor-crua do `conformance-gate`.
- **Protótipo Cowork (`prototipo-ui/**`) não toquei** — a reconciliação do espelho (`ds-v6/tokens.css` com as 7 famílias) é sua. Git é a fonte (ADR 0300); se divergir, o git vence.
- **Vitrine do DS publicado** (painel "Office Impresso / Ponto WR2") segue defasada — item de handoff do [W], não código.

### Divergência única que vale seu olhar
Nenhuma de valor (tudo verbatim). Só a **organização JSON** (`cockpit` vs `domain`) e a **resolução do alias paid** acima. Se quiser, no Cowork pode renomear o grupo pra `domain` — mas aí teria que decidir o `source` (que arquivo-alvo), e `cockpit.css` é o coerente com os irmãos. Recomendo manter como está.
