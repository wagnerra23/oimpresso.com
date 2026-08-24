<!-- HANDOFF · Cowork [CC] → Claude Code [CL] · cole 1× -->
# Financeiro · Review visual do [W] (todas as rodadas) → produção

> **Origem:** comentários do [W] no protótipo Cowork, rodadas 16/06/2026. **Substitui** `PROMPT_PARA_CODE_FINANCEIRO-REVIEW-W-2RODADAS.md` (consolidação completa). É **separado** da Onda 2 (Tribunal) — aquela tem ponte própria (`PROMPT_PARA_CODE_FINANCEIRO-ONDA2-TRIBUNAL.md`).
> **Natureza:** quase tudo é **CSS + pequenas props**. Régua de gate: cor só por token (`--accent/--pos/--warn/--neg/--text-*`), `color-mix(in oklab,…)` ok, nada de hex cru, nada de `rounded-xl+`.
> **Onde vive:** drawer + tabela + toolbar do Financeiro são **inline no `resources/js/Pages/Financeiro/Unificado/Index.tsx`**. Localize cada ponto; os de CSS vão pro bundle do Financeiro (onde moram `.fin-*`). Marquei `⚠confirmar` onde não reli o `@main` linha-a-linha.
> **Referência exata:** URLs no fim (`financeiro.css` + `financeiro-page.jsx` do protótipo) — espelho de intenção, traduza pro padrão TSX/tokens.

---

## BUG real (corrigir mesmo no @main) — dot do StatusBadge
No mapa de estilos do badge de status da linha, os dots usavam classe **inválida** `bg-[var(--pos-soft)]0` (sufixo "0" quebra o token) → **o pontinho não pintava**, dando aparência "desfocada/sem cor".
**Fix:** dot = cor sólida do estado: `recebido/pago → bg-[var(--pos)]` · `vencendo → bg-[var(--warn)]` · `atrasado → bg-[var(--neg)]` · `pendente → bg-[var(--text-3)]`. E peso do badge `font-medium → font-semibold`.
⚠confirmar se o `@main` ainda tem o sufixo "0"; se já corrigiu, pular.

---

## 1 · Seleção da linha: cinza → roxo claro · ⚠confirmar
A linha selecionada usava `--accent-soft` puro, que lê como **cinza**. 
**Fix:** `.row-selected { background: color-mix(in oklab, var(--accent) 14%, var(--surface)); box-shadow: inset 3px 0 0 var(--accent); }` — lavanda nítida + acento roxo na borda esquerda.

## 2 · DirIcon (ícone de direção da linha) "podrinho"
Era quadradinho com traço grosso. **Fix:** virar **círculo** (`rounded-full`), `strokeWidth 2 → 1.75`, glifo 1px menor, container `size+7`.
Ref: `financeiro-page.jsx` → `DirIcon`.

## 3 · Linhas decorativas coloridas no header das lentes do drawer · cadeira wayfinding do [W]
[W] pediu "uma linha verdinha / amarelinha clara / roxa clara" nos títulos das lentes. 
**Fix:** under-line curta (26×2px, radius 2) sob o `<h4>` de cada lente, cor por domínio via classe `fin-lens-h4-{hue}`:
- Vínculos → `accent` (roxa) · Conciliação → `pos`/`muted` · Fiscal → `warn` (amarela) · Cobrança → `pos`/`neg` conforme estado.
- `background: color-mix(in oklab, var(--TOM) 80%, var(--surface))`.
Passar `hue` do estado da lente pro `<h4>` (o componente `LensSection` já recebe `hue`/`tone`; só propagar pra classe do h4).
Ref: `financeiro.css` → `.fin-lens-h4*`.

## 4 · Histórico "muito cizudo" — tipografia mais leve
**Fix:** no histórico/auditoria do drawer: nome do autor `font-weight 700 → 500`; ícone de evento peso 500; título "Histórico" `font-weight 600`, sem uppercase/tracking, cor `--text-2`.
Ref: `financeiro.css` → `.fin-drawer-wide .fin-audit-*`.

## 5 · Ficha de campos: "duas linhas poluído"
O cartão da ficha tinha borda **+** o divisor da seção = linha dupla. 
**Fix:** `.fin-kv-card` sem borda/fundo/radius (`background:transparent; border:none; padding:4px 2px 6px`) — uma linha só (a da seção). (Já alinhado com Reichenstein da Onda 2; manter consistente — não reintroduzir o cartão lavanda.)

## 6 · Botões de ícone do header do drawer "sem CSS" (◂ ▸ ✕)
Tinham só hover. **Fix:** `.fin-dw-nav-btn { border:1px solid var(--border); background:var(--surface); }` + hover `sunken/text-3`. Estado de repouso visível.

## 7 · Sidebar: remover 6 itens do grupo "Mais"
[W] marcou "remover" em: **Copiloto · MemCofre · Arquivos · Connector · Team MCP · SRS**. **Manter** "Site (CMS)" (não foi marcado) e os demais.
**Onde:** o array de itens de nav (no repo: a config da sidebar / `nav` items). Remover esses 6 ids.
⚠confirmar nomes/ids equivalentes no `@main` (no protótipo: `copiloto, memcofre, arquivos, connector, teammcp, srs`).

## 8 · Contador dos filtros (toolbar) sem cor → cor do estado
O número (A receber **9**, A pagar **7**, Pagas **4**, Só atrasados **1**) ficava neutro/cinza porque `--cb-hue` só era setado no estado **ativo**. 
**Fix (2 partes):**
- **markup:** setar `style={{ '--cb-hue': s.hue }}` **sempre** (não só quando `on`); no toggle "Só atrasados" idem com hue 25.
- **css:** `.fin-filter-ct { color: oklch(0.50 0.14 var(--cb-hue,145)); font-weight:600; background:transparent; }` e no `.on` `color: oklch(0.40 0.15 var(--cb-hue))` — **mesmo tratamento on/off, sem pílula branca** (a pílula branca desalinhava on×off).
- hues: receber/recebidas = 145 (verde) · pagar/pagas — atenção: ver nota abaixo · atrasados = 25 (vermelho).
Ref: `financeiro-page.jsx` → `FilterBar` (labels com `hue`) · `financeiro.css` → `.fin-filter-ct`.

## 9 · Botão "Limpar" filtros sem estilo + chips desalinhados
"Limpar" era texto sublinhado solto; chips tinham alturas diferentes. 
**Fix:** `.fin-filter-clear` vira botão (`border:1px solid var(--border); background:var(--surface); height:28px; box-sizing:border-box; padding:0 11px; radius 5px`) + hover. Garantir que `.fin-filter-cb`, `.fin-filter-toggle` e `.fin-filter-clear` compartilhem **a mesma altura (28px) e box-sizing** → linha alinhada.

## 10 · Lente Caixa/A receber/A pagar (segmented do hero): altura
Histórico de idas-e-vindas — o pedido final do [W]: **mesma altura do botão "Novo lançamento" (30px)**, e o segmento selecionado **não pode inchar** os outros. 
**Fix:** `.fin-lens-seg { height:30px; box-sizing:border-box; }` + `.fin-lens-btn { padding:0 12px; display:inline-flex; align-items:center; }` (sem padding vertical que aumentava só o ativo). Os 3 segmentos com mesma altura; selecionado = só mudança de fundo.

---

## Ordem sugerida
1. **BUG dot** + **#8 contador** + **#9 Limpar** + **#10 lente** (toolbar/hero — alto impacto visual, baixo risco).
2. **#1 seleção** + **#2 DirIcon** (tabela).
3. **#3 linhas das lentes** + **#4 histórico** + **#5 ficha** + **#6 nav-btns** (drawer).
4. **#7 sidebar** (remover 6).

## Nota sobre hue de "pagar/pagas"
No protótipo "Pagas" usa azul e "A pagar" vermelho. **Confirme com o [W]** se ele quer pagar/pagas em vermelho (saída de caixa) ou manter azul pra "pagas/quitadas". Não invente — pergunte ou siga o que o `@main` já usa.

_CC não commita (read-only no git). Proposta; o Code valida contra o `main`, confirma os `⚠`, aplica. Vetos da Larissa da Onda 2 (não remover ícones de seção, não mexer na tipografia do valor) continuam valendo._
