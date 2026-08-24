<!-- HANDOFF · Cowork [CC] → Claude Code [CL] · cole 1× -->
# Financeiro · Onda 2 (Tribunal dos Mestres) → produção

> **Origem:** método "O Tribunal" rodado no Financeiro (`O Tribunal - Metodo de revisao de telas.html` · `Financeiro - Tribunal aplicado (Onda 2).html`). [W] aprovou levar a Onda 2 pra produção.
> **Régua:** não é bug — é **mérito**. As mudanças fazem o drawer/lista **liderar com a conclusão, comparar o número e parar de se auto-elogiar**.
> **⚠ Estado @main:** o drawer e a tabela do Financeiro são **inline no `resources/js/Pages/Financeiro/Unificado/Index.tsx`** (143 KB; não há componente `FinDrawer`/`FinLens` separado). Não li o arquivo inteiro linha-a-linha neste turno — **localize cada ponto e confirme antes de aplicar**. O que está `✓lido @main` vs `⚠confirmar` está marcado abaixo.
> **Referência de implementação (protótipo Cowork):** as URLs no fim trazem o `financeiro-page.jsx` + CSS com a versão exata do comportamento/markup. Use como espelho de intenção — não copie classes vanilla, traduza pro padrão TSX/tokens do repo.

---

## Régua de gate (vale pra TODAS as mudanças)
- Cor só por **token semântico** (`--neg/--warn/--pos/--accent` ou os `text-*` do tema) — nunca hex/oklch cru (`ui:lint` R1 · `conformance-gate`).
- `color-mix(in oklab, var(--x) N%, …)` é permitido e é o que o protótipo usa pros tints.
- Nada de `rounded-xl+` novo (eslint `ds/*`).

---

## 1 · Veredito inferido no topo do drawer  · cadeira **Victor** · ⚠ adição nova
**Intenção:** a tela conclui pelo usuário antes de qualquer varredura. Primeiro elemento do corpo do drawer (acima de Vínculos), 1 linha + sub.
**Lógica (do estado do título):**
- liquidado **e** com NF → `pos` · "Nada pendente." / "Pago, conciliado e com NF vinculada."
- liquidado **sem** NF → `warn` · "Pago, mas sem NF." / "Falta vincular o documento fiscal."
- aberto **e** vencido → `neg` · "Vencida há Nd — cobrar." / nome da contraparte
- aberto **e** ≤3d → `warn` · "Vence em Nd." (ou "Vence hoje — preparar cobrança.")
- aberto futuro → `muted` · "Em aberto — vence em Nd." / "Nada urgente por agora."
**Onde:** topo do corpo do drawer (`Sheet`/painel do título) no `Index.tsx`, antes da seção de vínculos.
**Tokens:** banner com `bg = color-mix(var(--tom) ~10%, surface)` + `inset ring ~26%`; ícone redondo preenchido com o tom.
**Ref:** `financeiro-page.jsx` → `fin-verdict` · `financeiro.css` → `.fin-verdict*`.

## 2 · Comparação "vs média" no valor · cadeira **Tufte** · ⚠ adição nova
**Intenção:** tirar o número do isolamento. Linha pequena sob o valor do hero: `↑ +5% vs média em Banner · 7 títulos`.
**Regra anti-slop (importante):** só renderiza com **≥2 pares reais** (mesma categoria + mesmo kind, valor>0) no conjunto carregado. Sem pares → **não mostra** (nada de número fake). Tom **neutro** (seta + %), sem verde/vermelho de valência.
**Onde:** logo abaixo do valor no hero do drawer. Precisa do conjunto de títulos no escopo (no protótipo passei `allRows` pro drawer; no repo, o título já vive numa lista paginada — **confirme** se há base suficiente client-side; se a comparação exigir agregado do backend, vira proposta de endpoint, não bloqueia o resto).
**Ref:** `financeiro-page.jsx` → bloco `fin-vs-avg`.

## 3 · Selo → dado (remover badge que só re-anuncia sucesso) · **Tufte/Rams** · ⚠ confirmar no @main
**Intenção:** badge de status que apenas repete o que o corpo já prova = tinta não-dado. Remover **só os redundantes de sucesso**; manter os que carregam info nova.
- Lente **Conciliação**: liquidado → **tirar** "100% match" (o corpo já diz `±R$ 0,00 · ±0 dias`). Aberto → **manter** "aguardando".
- Lente **Fiscal**: com NF → **tirar** "NF vinculada" (o corpo mostra o nº da NF). Sem NF → **manter** "sem NF" (warn — é um buraco real).
- Lente **Cobrança**: liquidado → **tirar** "encerrada" (o corpo diz "Título liquidado — cobrança encerrada"). Aberto → **manter** "em atraso"/"PIX gerado".
**Onde:** os 3 cabeçalhos de lente no drawer do `Index.tsx` (passar `status` condicional a `null`).

## 4 · Item terminal: stepper FSM → resumo de 1 linha · cadeira **Victor/Rams** · ⚠ confirmar no @main
**Intenção:** título **liquidado** não gasta ~80px com stepper de 4 etapas todas marcadas. Vira 1 linha: `✓ Emitido → Liquidado · 4 etapas · no prazo` (ou `· Nd de atraso` se `paid_at > due`).
**Onde:** no hero do drawer, onde hoje o stepper completo é renderizado pra todos os estados — condicionar: `settled ? <resumo 1 linha> : <stepper completo>`.
**Ref:** `financeiro-page.jsx` → `fin-fsm-done` · `financeiro.css` → `.fin-fsm-done`.

## 5 · Acento de AÇÃO na linha da lista · **Victor/Saarinen** · ⚠ adição nova
**Intenção:** a Eliana acha os títulos que pedem ação entre dezenas **sem abrir**. Acento de 3px na borda esquerda da linha: **vencido = `--neg`**, **vencendo (≤3d, não pago) = `--warn`**, resto = nada.
**Onde:** `<tr>` da tabela no `Index.tsx` — `box-shadow: inset 3px 0 0 var(--neg/--warn)` na 1ª `<td>` (border-collapse não respeita `border-left` no `tr`).
**Ref:** `financeiro.css` → `.fin-table-card tr.fin-row-act-neg/-warn > td:first-child`.

## 6 · Ficha de campos sem a caixa colorida · cadeira **Reichenstein** · ⚠ confirmar no @main
**Intenção:** a queixa é "caixa **colorida** fazendo o trabalho da tipografia". **Tirar a cor** (fundo lavanda + borda accent), manter **estrutura neutra discreta** (fios topo/baixo) pra não flutuar em branco (pedido do [W] 06-11). Tipografia agrupa.
**Onde:** o wrapper dos KV no drawer (no protótipo `.fin-kv-card`) → de `bg lavanda + border accent + radius` para `transparent + border-top/bottom hairline + sem radius`.
**⚠ Larissa NÃO vetou este; [W] tem a palavra** (foi ele que pediu a caixa). Se [W] preferir manter a caixa, mude só a **cor** lavanda→neutra e pare aí.

---

## ⛔ NÃO FAZER (vetos da operadora Larissa — registrados no método)
- **NÃO** remover os ícones-quadradinho coloridos das seções do drawer. Rams pediu; a Larissa **vetou** — ela acha a lente certa pela cor, correndo, 50×/dia.
- **NÃO** "simplificar" a tipografia do valor (inteiro grande + centavos/prefixo pequenos). É o que ela lê de longe no balcão. Padrão do gabarito 9.75 fica.
- Realidade da operadora &gt; teoria do mestre. Esses dois são lei.

## Já está no @main (não refazer)
- **Tom da sparkline por sinal** (`tone={saldo>=0?'pos':'neg'}`) — **✓lido @main na Onda 1**. O host estava atrás; produção já vira vermelho no saldo negativo. (A Onda 1 já cobriu o número grande do hero ficar `--neg`.)

---

## Ordem sugerida
1. **#3 selo→dado** e **#4 FSM-resumo** (mexem em condicional de render, baixo risco).
2. **#1 veredito** (componente novo no topo do corpo).
3. **#5 acento na lista** (CSS + classe na row).
4. **#6 ficha sem caixa** — confirmar preferência do [W] antes.
5. **#2 comparação** — se a base client-side bastar; senão, vira proposta de agregado backend (não bloqueia 1–5).

_CC não commita (read-only no git). Esta é a proposta; o Code valida contra o `main`, confirma os pontos `⚠`, e aplica. Os vetos da Larissa são inegociáveis._
