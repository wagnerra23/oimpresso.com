---
sessão: Vendas — reconciliação com o git (foco [W])
data: 2026-06-03
agente: [CC]
tela: Vendas (alvo repo = Sells/Index)
refs: Vendas.charter.md v2 · git Sells/Index.charter.md v6 @main · Index.review.md @main
---

# Sessão 2026-06-03 — Vendas: reconciliação com o git

## Pedido [W]
> "foco na vendas no git, atualize e analise as diferenças. crie o plano. quais regras ativas?"

## O que foi feito
1. **Leitura da espinha + lei** (ritual): STATUS · MEMORY_INDEX · CARTA · LICOES_CC. Git: `Sells/Index.charter.md` **v6** + `Index.review.md` (✓ lido @main 2026-06-03) + árvore `Pages/Sells/*` (24 componentes).
2. **Aterrei o lado local** (gate 6 — ler antes de afirmar): `vendas-page.jsx` (1922L), `vendas-output.jsx` (WhatsApp 3-tab + transcript ✓), `vendas-extras.jsx` (sem NextAction/Copiloto/validações ✓), `pg-vendas-integration.jsx` ("Emitir cobrança" ✓).
3. **Atualizei `Vendas.charter.md` v1→v2** (append-only): frontmatter aponta o canon git v6; nova seção "Diferenças vs git v6"; trilha do tempo. Corrige a L-grounding (charter v1 não referenciava o git).

## Decisões / achados (tudo PROPOSTA — Tier 0 = [W])
- O git é **live/v6** e está à frente em fiscal/multi-tenant; o protótipo local é a **semente visual** e está à frente em comissão/IA-palette/placa.
- **Gap mais sério (P0):** `localStorage` local sem `b<bizId>.` → quebra anti-hook multi-tenant Tier 0 (ADR 0093).
- **2 conflitos com anti-hook do git:** IA no ⌘K palette (git proíbe LLM em listagem) · PDV F2 (git aposentou o POS — e o próprio charter local já diz aposentado → inconsistência interna).
- **Divergência intencional (não mexer):** sem PageHeader v3 — **[W] odeia page header** (L-28).

## Plano (proposto · ordem)
**Pré-condição:** [W] confirmar que Vendas volta a ser foco (estava em Oficina desde 06-01). Nada de build sem o "vai".

- **P0 — Segurança/conflito (precisa [W], Tier 0):**
  1. `localStorage` → prefixo per-business `oimpresso.sells.b<bizId>.` (alinhar ao git · ADR 0093).
  2. Resolver IA-no-palette: mover pro drawer (`SaleAiPanel`) OU [W] mantém (decisão de produto).
  3. Limpar PDV F2 (charter já diz POS aposentado) OU [W] reabilita.
- **P1 — Fechar gaps onde o git é canon (build [CC] no host, reuse-first):**
  4. `VdNextActionPanel` (FSM emojis canon) no drawer.
  5. Gate de **validações fiscais BR** antes de emitir (sem CNPJ/CPF/idEstrangeiro = bloqueia).
  6. Botão **"Criar OS"** outbound no drawer (contraparte do listener inbound).
  7. Saved view **"Aguardando faturamento"**.
- **P2 — Promover o local-ahead pro git (proposta pro [CL] validar vs main):**
  8. Coluna Comissão + Ranking vendedores · Placa Mercosul em Vendas (domínio Martinho caminhão · ADR 0194).
- **Sempre:** pré-flight de build visual (L-23) · accent escopado `.vendas-scope{--accent}` · gate F1.5 antes/depois (é piloto) · DS-GUARD antes do `done`.

## Erros + correção
- Nenhum erro cometido nesta sessão. **Risco evitado:** não inventei a comparação (li o git v6 real — anti-L-grounding); não toquei a constituição/ADR; não prometi commit (L-06); não fiz over-reach build (L-28/L-25 — só atualizei memória + plano, como pedido).

## Residual (aguarda [W])
- "Vai" pro foco Vendas + ordem do P0 (multi-tenant é o mais urgente).
- Mirror do `Vendas.charter.md` v2 no git (L-13) — ponte pendente em `COWORK_NOTES.md`.
- Reconciliar vocabulário Foco-3 × SubNav-4/segmented (não-trivial, [W]).

## Refs
- `Vendas.charter.md` v2 (local) · `Vendas.casos.md` (8 UCs)
- git `resources/js/Pages/Sells/Index.charter.md` v6 · `Index.review.md` · ADRs 0093/0143/0178/0190/0192/0194
- LICOES_CC: L-grounding · L-23 · L-26/27 (gate 6) · L-28

## Próximo passo
[W] dá o "vai" e a ordem; [CC] executa P0→P1 no host (`vendas-page.jsx`), gate F1.5 a cada passo, e gera a ponte zero-toque pro [CL].

## Plano de CSS (faixa transversal — pré-req da migração ds-v5 + mirror git)
> Estado real de `vendas.css` ✓ lido cowork 2026-06-03. **Tudo Cowork-local** (o repo tem `sells-cowork.css` ⚠ por STATUS (k), não re-lido esta sessão; a dívida dele já tem ponte `PROMPT_PARA_CODE_DARK-BACKFILL-SWEEP` — NÃO duplicar · L-11/L-20). Princípio: aditivo sobre ds-v5 · identidade só `.vendas-scope{--accent}` · status pela escala warm do DS · zero cor crua · dark-aware via token · responsivo `@container`.

- **CSS-1 · Unificar escopo (1 nome):** hoje `.vendas-aplus` (css) ≠ `.vendas-scope` (charter) ≠ `.sells-cowork` (git ✓ lido). No host: `.vendas-scope{ --accent: oklch(0.45 0.11 155) }` define a cor; regras sob ele. No mirror pro git renomeia p/ `.sells-cowork` (canon). NÃO inventar 4º nome (L-23).
- **CSS-2 · Matar cor crua (MAIOR item):** ~12+ `oklch(...)` hardcoded em `.vd-sla-*`/`.row-focused`/`.vd-fav` + `background: white` + scrim `oklch(0 0 0/.5)`. → tokens da escala warm do ds-v5 (emerald/amber/rose soft+strong) e `--accent`. Os `--vd-green/-bad/-warn/-ok` viram **aliases p/ tokens do DS**, não valores crus. `white`→`var(--surface)`, scrim→token de overlay. Alinha com o stylelint anti-hex do repo (#2054).
- **CSS-3 · `--vd-ai` (roxo 295) anda junto do conflito IA:** o bloco `:root{--vd-ai...}` só existe p/ IA-no-palette. Se a IA migra pro drawer (resolução B), esses tokens vão p/ `SaleAiPanel` ou somem. Não tratar isolado.
- **CSS-4 · Dark-aware "de graça":** feito CSS-2, o dark vem dos tokens warm do ds-v5 (já têm variante dark). Auditar só os 2-3 pontos que sobraram crus (white/scrim). É o espelho Cowork da DARK-BACKFILL do git.
- **CSS-5 · `@media`→`@container`:** as 2 queries (≤1100/≤900) reflowam por viewport; trocar por `@container` na largura real (cura Larissa 1280 com drawer aberto — mesmo fix do Financeiro · CARTA §4.5).
- **Gate:** DS-GUARD nos arquivos tocados antes do `done` + F1.5 antes/depois (piloto). Ordem: CSS-1→CSS-2→CSS-4→CSS-5; CSS-3 casa com o P0.2 (conflito IA).

## CSS-2 — EXECUTADO (2026-06-03, [W] "sim") + VERIFICADO
**Feito (Cowork-local · `styles.css` + `vendas.css`):**
1. `styles.css .vendas-aplus{}`: tokens de **status** (`--vd-ok/-soft`, `--vd-warn/-soft`, `--vd-bad/-soft`, `--vd-neutral`) deixaram de ser oklch cru → **alias do ds-v5** (`var(--pos/--warn/--neg/--text-3/--sunken)`); + `--vd-info/-soft`→`var(--origin-CRM-*)`. Dark/density propagam de graça.
2. **Identidade verde 155** preservada (D-02 = [W]; NÃO virou roxo) + override `[data-theme="dark"] .vendas-aplus{}` com verdes clareados → legível no escuro sem decidir verde×roxo.
3. `vendas.css`: SLA pills (`.vd-sla-fresh/warning/overdue`), SLA mini, row-focused, fav ★ → `var(--vd-*)`.

**Verificado VISUALMENTE (LOOK · L-default-exposto)** — `screenshots/vendas-light.png` + `vendas-dark.png`:
- ✅ Light limpo; status corretos (rose "A receber", ageing tricolor, SLA mini).
- ✅ Dark: status agora **legíveis** (antes light-built cru).
- 🔴 **DÍVIDA EXPOSTA (flag, não conserto):** KPI cards (`.os-kpi`) ficam **brancos no dark**. `.os-kpi` usa `var(--surface)` certo e `styles.css` TEM `--surface` dark (linha 64) → um **bundle light-built carrega depois e re-declara `--surface` claro** (L-10/L-19 + STATUS k). É a **DARK-BACKFILL shell-wide** (todas as telas os-page), já bridada. NÃO é CSS-2 nem só Vendas; mexer = shell compartilhado (L-28). Fica pra CSS-4.

**Pendente (CSS-2 cont., chunks revisados — NÃO script cego):** ~150 literais oklch restantes em `vendas.css` (audit, cross-link `.vd-link-*`, fin-links, message bubble, comentários azul, item-edit âmbar). **Excluir sempre:** transcript `.vd-trans-*` (papel A4 #hex) + apresentação `.vd-pres-*` (dark self-contido). `--vd-ai` 295 = CSS-3. Há `oklch(... var(--cb-hue,145))` com paren aninhado → confirma que script global cego QUEBRA → chunk revisado.

## new_design_memories
- tipo: token · ref: `styles.css .vendas-aplus` + `vendas.css` · resumo: CSS-2 status→tokens ds-v5 + dark-aware verde (Cowork-local; git `sells-cowork.css`/bundles têm o MESMO bloco cru → cobrir junto da DARK-BACKFILL já em ponte, NÃO duplicar · L-11/L-20)
- tipo: anti-padrao · ref: shell `--surface` re-declarado claro por bundle tardio · resumo: card branco no dark = cascata (L-10/L-19), shell-wide, já bridado
