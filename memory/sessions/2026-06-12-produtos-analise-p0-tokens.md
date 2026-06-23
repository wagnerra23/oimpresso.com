# Sessão 2026-06-12 — Análise + P0 conformidade da tela Produtos

**Pedido [W] (comentário inline no h1 "Produtos"):** "Essa tela está dentro do padrão (tokens)? esse data grid é o melhor que tenho? poderia ser o componente shared? faça uma análise e me diga o que fazer." → depois "sim" pro P0.

## Análise entregue (grounded no código)
- **Conformidade:** `prod-mec.css` era a MENOS conforme das listas — **79 hex cruas** (19 únicas), 8 rgba, só 9 tokens, **0 bloco dark**, 5 `font-family` cru. Comparativo: clientes-page.css=2 hex/231 var (modelo limpo); vendas.css=63 hex/673 var.
- **Consequência:** no **dark (tema padrão)** a tela quebrava — totalizador/texto sumiam (escuro-no-escuro). Confirmado ao vivo.
- **Grid:** é o mais COMPLETO em features (sort 8 col · 2 modos Densa/Balcão · chips de estoque · totalizador 6 KPIs · drawer com tabela de preços 4 níveis/SKUs/fornecedores/BOM) → melhor candidato a DataGrid shared, MAS bespoke (`pm-*`, cores cruas, colado ao domínio produto).
- **Plano:** P0 conformar (corrige dark) · P1 polir 9.75 · P2 extrair `<DataGrid>` shared (API de colunas, migra Vendas/Clientes/Produtos) — P2 é projeto à parte (mexe em 3 telas).

## P0 EXECUTADO (✅ verificado light+dark)
- `prod-mec.css` tokenizado via run_script (26 mapeamentos, host `?v=tok1`): `background:#fff`→`--surface` · off-whites `#fbfbf6/#f3f6fc/var(--bg-elev,#…)`→`--bg-2`/`--accent-soft` · verde `#1f6d3a/#e6f1e8`→`--ok-fg/--ok-bg` · âmbar `#d4910f/#fdf3df/#7a4a00`→`--warn-*` · vermelho `#9a2a2a/#f8e6e6`→`--danger-*` · neutros→`--bg-2/--border/--text-mute` · `#c9d6e8`→`--accent-line` · 5 `font-family` cru→`var(--font-sans)` · 24 `var(--mono,…)`→`var(--font-mono)` · sombras rgba→`--sh-2`.
- Resta só `#fff` = **foreground sobre thumb de categoria** (correto nos 2 temas). PMCat (paleta de categoria no JS) mantida — identidade, lê no dark.
- Prova: dark agora legível (totalizador/pills/linhas), light intacto.

## FIX pós-verifier (quirk Chromium — LIÇÃO NOVA)
- Verifier pegou: `.pm-row` continuava BRANCA no dark mesmo com `--surface` resolvendo escuro no elemento. Causa: **Chromium não re-resolve `var()` numa cor que está no `transition` ao trocar de tema** — vale pro shorthand `background` E pro longhand `background-color`. Tentar `background`→`background-color` NÃO resolve; a cura é **tirar a cor do `transition`** (hover muda instantâneo). Apliquei em `.pm-row` (→ `transition: box-shadow .12s`), `.pm-pt-row` e `.pm-stockbar-chip`. Prova por `getComputedStyle`: dark=0.198 · light=1 · volta-pra-dark=0.198 (não trava mais). Host `?v=tok3`.
- **REGRA pra próximas tokenizações:** ao trocar cor crua→`var(--token)`, conferir se a propriedade está no `transition`; se estiver, REMOVER do transition (ou nunca animar cor temática). Senão o dark trava no valor do tema anterior.

## FIX 2 pós-verifier (root cause app-wide — LIÇÃO MAIOR)
- Verifier 2: `.pm-row.sel` e `.pm-pt-row.primary` (usam `--accent-soft`) ficavam claro-no-claro no dark. Causa: **`app.jsx` fixava `--accent`/`--accent-2`/`--accent-soft` INLINE no `<html>` sempre com o valor CLARO (do tweak de hue), e o inline VENCE o `[data-theme=dark]` do tokens.css** → accent-soft claro em TODA tela dark.
- **Cura (desacoplar hue × luminância):** tokens.css agora define accent/-hi/-soft/-line com `oklch(L C var(--accent-h, 295))` nos DOIS blocos (luminância dona do tema); `app.jsx` seta só `--accent-h` (hue) inline + `--bubble-me`. Removidos os pins inline de accent/-2/-soft. Robusto a QUALQUER troca de tema (não só via Tweak). Hosts: `ds-v6/tokens.css?v=v6-3` · `app.jsx?v=accentdark2`.
- Prova: sem inline accent-soft · light=0.945 · dark=0.33 · alterna sem travar · pm-row.sel contraste 0.64. **Bug era app-wide (toda tela dark com fundo accent-soft); o fix conserta todas.**
- **REGRA:** NUNCA fixar cor com luminância inline app-wide (vence o tema). Tweak de cor = girar só o HUE via `--accent-h`; luminância sempre nos blocos de tema do tokens.css.

## P1 — ACABAMENTO 9.75 ([W] "p1" · 2026-06-12)
Tela já era sólida/funcional; P1 = elevar craft sem rebuild. Entregue:
- **Lentes nas seções do drawer** (assinatura 9.75 = gramática do show/Create reskin): `<span class="pm-lens">{ícone}</span>` antes de cada `prod-bom-h` — hash(OEM)/refresh(equiv)/doc(ficha)/tag(preços)/grid(SKUs)/truck(fornecedores)/layers(BOM). Quadradinho 22px accent-soft, radius-sm. Confirmado computado light(0.945/0.55) + dark(0.33/0.70), 3 lentes no 1º produto.
- **Empty-state padrão DS:** `.pm-empty-ico` círculo 48px com sombra + título fs-5 + descrição + botão "Limpar filtros" (era texto cru). ✓ visto.
- **Estados limpos:** busca com foco `var(--focus)`; **seleção via `box-shadow:inset 3px` (robusta)** + accent-soft, `::before` só pro hover (cor da categoria); micro-física `--ease`+`--t-1` em todas transições (era linear .12s).
- **Tokenização das cruas remanescentes do drawer** (prod-page-extras.css?v=p1): 12× `'IBM Plex Mono'`→`var(--font-mono)`; supp-table best/high/mid/low + var-table low (oklch verdes/âmbares cruas)→`--ok/warn/danger`; `.prod-best-badge` oklch verde+white→pill invertida `--ok-fg`/`--ok-bg` (theme-safe). Restam ~14 oklch cruas em `.prod-grade-*`/`.prod-stock-badge`/`.prod-compat-*` = view **grade-Mecânica DORMENTE** (não renderiza nesta tela; fora do escopo).
- **Floor + radius:** 9.5px→`var(--fs-1)` no eyebrow; containers 6px→`--radius`/`--radius-md`; thumb→`--radius-sm`.
- Hosts: `produtos-page.jsx?v=p1` · `prod-mec.css?v=p1b` · `prod-page-extras.css?v=p1`.
- **LIÇÃO REFORÇADA:** `getComputedStyle(el,'::before')` após `classList.add` via JS leu transparente mesmo com a regra correta no CSSOM (specificity maior, var resolvendo) — pseudo-elemento pós-mutação-JS é não-confiável no iframe (= L-lição paint). Resolvi trocando ::before-seleção por box-shadow inset (à prova de bala) em vez de depurar a medição.

## Aberto / próximo
- P2 (DataGrid shared) aguardando aval do [W] — é a resposta real ao "componente shared".
- Cruas dormentes da grade-Mecânica (~14 oklch) — tokenizar se/quando a view for ativada.
- Pendentes antigos: "plano de contas deve ir para…" · "sync endereço cliente↔venda".

## Aberto / próximo
- P1 (polish 9.75) e **P2 (DataGrid shared)** aguardando aval do [W] — P2 é o que responde "componente shared" de verdade.
- Pendentes antigos: "plano de contas deve ir para…" · "sync endereço cliente↔venda".
