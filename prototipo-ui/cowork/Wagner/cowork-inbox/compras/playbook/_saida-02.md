---
thread: "02 · Margem sem fonte"
dono: "[CC]"
estado: feito — saída 2
base_lida: wagnerra23/oimpresso.com@main 68e071305601 (2026-09-24)
prefixo_tocado: compras-page.jsx (items-tbl) · oimpresso.com.html (?v=cmp9a11y-b)
---
# _saida-02 · Coluna "Margem" saiu do drawer

## Evidência (lida no turno)
Busca `margem|margin|lucro|sell_price|venda` em `resources/js/Pages/Compras/` @68e071305601: **11 ocorrências, nenhuma é dado** — 10 são `marginTop/marginLeft/marginBottom` de CSS inline em `Index.tsx`/`components/Drawer.tsx` e 1 é texto de `Index.casos.md:254`. O `Drawer.tsx` de produção não recebe nem mostra margem nem preço de venda.

## Decisão: saída 2 (a produção não entrega)
- Removidos o `<th>Margem</th>` e o `<td>+{it.margin}%</td>` do `items-tbl` (`compras-page.jsx:647/657`).
- Colunas do `items-tbl`: **6 → 5** (Produto · Qtd · Custo unit. · Total · Venda). Contado no fonte; o render não foi medido nesta sessão (T1 fica com o verificador).
- Nada calculado no front ficou no lugar.

## ⚠️ Mesma classe, fora do escopo desta thread
A coluna **Venda** (`it.sellPrice`) também **não tem fonte** no `Drawer.tsx` de produção (mesma busca: zero `sell_price`/`venda`). A thread mandou manter as 5 restantes intactas, então não removi. Proposta: thread `compras/02b` com a mesma saída — ou [W] declara que "Venda" vem de outro lugar (cadastro do produto) e a fonte fica escrita.

## Não tocado
`main` · `compras-grade-matrix.jsx` · layout, tokens e larguras das outras colunas. A frase do painel "destrava custo, margem e o pagamento" (`compras-page.jsx:173`) é texto sobre o processo, não número — ficou.
