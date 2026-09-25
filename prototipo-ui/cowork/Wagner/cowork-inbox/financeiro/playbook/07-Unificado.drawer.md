---
thread: "07"
modulo: financeiro
view: Unificado
secao: drawer
prefixo: [resources/css/fin-cowork.css, resources/css/fin-ia.css, resources/js/Pages/Financeiro/Unificado/Index.tsx]
base_lido: 2c115a5ca250
revisado: 2026-09-25 (conferência de CSS contra o main — ver §4)
medido: 2026-09-25
depende: ["00"]
---
# 07 · Financeiro/Unificado — Drawer do lançamento (acabamento)

> Execução: `/onda Financeiro/Unificado drawer --thread 07` (o `pedido.mjs` monta os 4 blocos). Antes: thread **00** tem de ter criado `governance/design/targets/financeiro--unificado.alvo.json`, senão exit 2. Teste do estranho: tudo que precisa está aqui + no `main`.

## 1 · Escopo
Só **acabamento** do drawer de detalhe (`data-contract="drawer-detalhe"`): header, hero (valor/estado), abas, lentes do corpo, rodapé de ações e **aba IA**. **Não** muda conteúdo, dados, ordem das seções, atalhos (J/K/R/Esc) nem regras de negócio. Pedido de [W] em 2026-09-25: "os acabamentos estão feios" + "aba da IA — cores dos componentes".

## 2 · A11y do alvo (medido no protótipo, 2026-09-25)
| # | checagem | resultado |
|---|---|---|
| A1 | `role="dialog"` + `aria-label` no painel | ✓ `dialog` / "Detalhe do lançamento" |
| A2 | botão sem nome acessível | ✓ **0** de 20 |
| A3 | fechar com nome | ✓ `aria-label="Fechar (Esc)"` |
| A4 | navegação J/K com nome | ✓ "Título anterior (K)" / "Próximo título (J)" |
| A5 | foco visível | ✓ `outline: 2px solid var(--accent)` em `button:focus-visible` |
| A6 | emoji na UI | ✓ removidos (`💬`, `✦` da aba) — ícone Lucide `Sparkles` |
| A7 | alvo de toque <24px | ✗ **4 botões** (botões inline de copiar/editar das linhas chave-valor) — **não corrigido**, ver §7 |
| A8–A12 | contraste, ordem de tab, trap de foco, esc, leitor de tela | não medidos — o `Sheet` do vivo (Radix) já cobre trap/esc; o executor roda a bateria no vivo |

## 3 · Medição do alvo
Condições: `oimpresso.com.html` rota `financeiro`, `<html class="cockpit dark">`, `__oiLazyDone=true`, nós 2104/2104 (duas leituras iguais), `getComputedStyle`. **Sanidade:** largura do painel = 560 ✓ (valor conhecido).

| elemento | propriedade | ALVO |
|---|---|---|
| painel | width | 560px |
| painel | font-family | "IBM Plex Sans", … |
| header | height · padding | 56px · `0 12px 0 20px` |
| fechar | tamanho · raio | 28×28 · 6px · fundo transparente |
| nav J/K | height | 28px — **um** grupo com borda 1px `--border`, raio 6px, botões internos 24px sem borda |
| hero | padding · background-image | `18px 20px 16px` · **none** (sem gradiente/radial) |
| lente (seção) | padding · border-top | `16px 0` · 1px `--border` (sem caixa, sem fundo) |
| ícone de lente | caixa | nenhuma — só glifo `--text-3` |
| veredito | radius | 8px |
| grid chave-valor | gap | `14px 24px` · sem card em volta |
| rodapé | height | 60px |
| botões do rodapé | height | 32 / 32 / 32 / 32 — secundárias borda 1px, primária à direita após espaçador |
| primária (Recebi/Paguei) | background · radius | `oklch(0.7 0.15 295)` (= `--accent` dark) · 6px |
| _linhas abaixo = aba IA do **protótipo** (`vd-ai-*`); o vivo usa outro markup — ver §4.3_ | | |
| **Aba IA** · banner | background · image · borda · texto | `color-mix(accent 8%)` · **none** · `color-mix(accent 24%)` · `--text` |
| Aba IA · bloco | background · border-left · opacity | transparente · **1px** (era 3px accent) · 1 |
| Aba IA · bloco vazio | borda | tracejada, ícone `--text-3` sobre `--sunken` |
| Aba IA · h3 | color | `--text-3` (era `--vd-ai` a 85%) |
| Aba IA · stat | valor | `--font-mono` tabular |

Antes (mesma sessão): banner IA **branco** no dark (`linear-gradient(--vd-ai-soft → --surface)` + `.vd-ai-block{background:white}` em `vendas.css`), hero com radial lavanda, lentes com caixinhas coloridas no ícone, rodapé com alturas mistas.

## 4 · Âncora de implementação (`main` @`2c115a5ca250`, lido 2026-09-25)
### 4.1 · TSX
- `resources/js/Pages/Financeiro/Unificado/Index.tsx:2057` — `SheetContent` do drawer (`fin-cowork fin-curadoria fin-drawer-wide`, `data-contract="drawer-detalhe"`).
- `Index.tsx:2155` hero `fin-dw-hero` · `:2257-2258` aba IA (`fin-drawer-tab fin-drawer-tab-ai`) · `:2632` rodapé `fin-drawer-footer fin-drawer-footer-sticky` · `:687` `DrawerLens` · `:678` `DrawerLensChip`.
- `Index.tsx:2700-2721` — corpo da aba IA: `div.fin-ai-panel` com **`FinAnomalyDetector`** e **`FinPartyHistory`** (importados em `:48-49`).

### 4.2 · Cascata de CSS do drawer — **6 folhas, não 2** (`resources/css/inertia.css`)
Ordem de `@import` = ordem de vitória (mesma especificidade, a de baixo ganha):
1. `cowork-canon-financeiro-bundle.css` (194.798 B) — `.fin-drawer-wide` `:4033`, `.fin-drawer-footer` `:4045`, `.fin-dw-hero` `:4772`, `.fin-ai-panel h3` `:4381`, `vd-ai-*` `:3169-3313`
2. `fin-curadoria.css` (12.158 B)
3. `fin-ia.css` (10.020 B) — **dona da aba IA no vivo**
4. `fin-output.css` (42.072 B) — `.fin-drawer-wide` `:751` e `:899` (padding 22px), `.fin-drawer-footer` `:758`, `.fin-ai-panel` `:776`
5. `fin-cowork.css` (31.734 B) — **última a carregar**; já documenta conflito com `fin-output.css:685` (`.fin-drawer-tabs`) em `:556`
6. `fin-mobile.css` (2.140 B)
⇒ Editar o bundle (1ª) **não vence** `fin-output`/`fin-cowork`. O override vai em **`fin-cowork.css`** (drawer) e **`fin-ia.css`** (aba IA).

### 4.3 · Aba IA — o vivo **não usa** `vd-ai-*`
O protótipo renderiza `vd-ai-banner`/`vd-ai-block`/`vd-ai-stats` (`financeiro-ai.jsx` → estilos de `vendas.css`). O vivo renderiza `.fin-anomaly*` + `.fin-party-*` (+ `.fin-digest*`) de `fin-ia.css`, **com cor crua clara**:
- `.fin-anomaly-ic{background:white}`; `.fin-anomaly-*{background:oklch(0.96 …)}`
- `.fin-digest-toggle{background:white}`, `.fin-digest-card{background:white}`, `.fin-digest-top{background:white}`
- `.fin-party-*` em `--fin-text/--fin-line/--fin-bg-soft`, que o hotfix de `inertia.css` (fim do arquivo) fixa em `:root` com valores **claros** — não viram no dark.
É o mesmo defeito que corrigi deste lado (branco no dark), só que em outras classes. A troca no vivo é **por token** nessas classes, não portar `vd-ai-*`.

## 5 · Mudança pretendida
Fonte deste lado: `financeiro-drawer.css` (escopo `.fin-dw2`, só tokens) + 5 trocas pontuais em `financeiro-page.jsx` (Drawer):
1. painel ganha `role="dialog"` + `aria-label` (no vivo: conferir se o `SheetContent` Radix já expõe — não duplicar);
2. botão fechar ganha `aria-label="Fechar (Esc)"`;
3. contador de comentários na aba sem emoji (`aria-label` "N comentários");
4. aba IA: `✦` → ícone `Sparkles` 12px;
5. espaçador antes da primária no rodapé (primária sempre à direita).
No vivo:
- **drawer (header/hero/lentes/rodapé)** → regras em `fin-cowork.css` sob `.fin-cowork .fin-drawer-wide` (sem inventar `.fin-dw2`); remover a regra `padding 22px` de `fin-output.css:899` (alvo = 20px) em vez de sobrescrever;
- **aba IA** → em `fin-ia.css`, trocar `white`/`oklch(0.96 …)` por `var(--surface)`/`color-mix(in oklab, var(--warn|--neg|--pos|--accent) 8%, transparent)` e `--fin-text/--fin-line/--fin-bg-soft` por `--text`/`--border`/`--sunken` — mesmo tratamento do §3 (tinta 8% + borda 24%, sem branco, sem borda esquerda grossa).

## 6 · Átomos a reusar (não recriar)
`Sheet`/`SheetContent` (Components/ui) · `Button` variantes `outline`/`default` para rodapé · ícones `lucide-react` (`Sparkles`, `X`, `ChevronUp/Down`, `Check`, `Eye`, `Printer`) · tokens `--accent`, `--border`, `--sunken`, `--text-2/3`, `--pos/--warn/--neg`, `--sh-2`, `--fs-1…5`, `--font-mono`. Nenhuma cor crua.

## 7 · O que a ancoragem NÃO resolve
- **Seis folhas no mesmo drawer** (§4.2). Esta thread escolhe `fin-cowork.css` + `fin-ia.css`; consolidar as outras quatro é outra thread.
- **Markup da aba IA diverge** protótipo × vivo (§4.3): o protótipo tem "Perguntar à IA" (LLM) e 4 stats; o vivo tem detector de anomalia + histórico com 5 recentes, sem LLM. Qual é o alvo de **conteúdo** é decisão de [W] — esta thread só troca **cor**.
- **`--fin-*` fixos em `:root`** (hotfix Lightning CSS em `inertia.css`) valem para o light; para dark precisam de par `[data-theme="dark"]` ou sair de uso.
- **`R$ 1.2k` vs `R$ 1,2k`** na aba IA (stats "Total acumulado" × "Ticket médio") — formatação curta inconsistente em `financeiro-ai.jsx` (`_fmtShort`). Bug de protótipo, não corrigido nesta thread.
- **Anel do passo atual do stepper** (círculo "1 Emitido") aparece com contorno duplo no dark — pertence a `OiFsmStepper`/`FsmStepper` do DS, fora do prefixo.
- **4 alvos <24px** (A7) — botões inline de copiar/editar; exigem decisão de tamanho mínimo para o grid denso da Eliana.
- `!important` no CSS do protótipo existe só para vencer a pilha antiga deste lado; **não levar `!important` para o vivo**.

## 8 · Provas / DoD
- medição: `getComputedStyle` no vivo (dark) = §3, linha a linha;
- a11y: A1–A12 no vivo, zero botão anônimo;
- PR ≤300 linhas, só os 2 arquivos do prefixo;
- **T7** `design-diff --compare --check` nos dois renders com prod deployada — antes disso nada de "igual ao design".
- fecha com `_saida-07.md` (recibo do Code; [CC] não edita).

## 9 · Não toca
`_components/**` (FinAnomalyDetector, FinPartyHistory, FinTroubleshooter etc. só herdam o estilo — nenhum TSX delas muda), `cowork-canon-financeiro-bundle.css`, `fin-output.css` exceto a remoção de `:899`, controllers, rotas, `Modules/**`, IA de Vendas (`sells-cowork-ia.css`).

## 10 · Saída / rollback
Rollback = reverter o PR (só CSS + 5 atributos/elementos no TSX). Deste lado: remover o `<link>` de `financeiro-drawer.css?v=dw3` do host e a classe `fin-dw2` do `aside` volta o drawer ao estado anterior.
