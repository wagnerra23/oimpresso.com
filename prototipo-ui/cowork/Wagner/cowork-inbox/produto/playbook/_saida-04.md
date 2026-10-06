---
sessao: "04"
titulo: "Produto/Etiquetas — saída da thread"
autor: "[CL]"
criado: 2026-10-06
base: 73ba361e01
thread: 04-etiquetas.md
veredito: "entregue — a tela vira Inertia atrás de flag MWART por negócio (biz=1 primeiro); a folha impressa segue em /labels/preview, mesmo código. Ligar a flag em produção é com [W]."
---

# _saída 04 · Imprimir etiquetas

## Abertura
- **A-P1 já estava fechado** quando a thread começou: `print_labels.access` nas 3 rotas e o `Barcode` do `preview()` escopado por `business_id` ou `NULL` (404 fora disso), com teste 98 × 99 — thread 01, #8349. Conferido no `main` `73ba361e01`; nada refeito.
- **D4** respondida no índice ("vários modelos de folha; o catálogo de modelos é o alvo"): o seletor da tela lista os modelos de etiqueta do cadastro `/barcodes` do negócio e os do sistema, o mesmo recorte do `preview()`.

## O que entrou

| PR | o quê |
|---|---|
| [#8749](https://github.com/wagnerra23/oimpresso.com/pull/8749) | `/labels/add-product-row` devolve a linha em JSON (só com Accept JSON; a Blade segue recebendo o HTML) com o texto do preço que a impressão sairia, por grupo e por tipo — as MESMAS chamadas de `preview()` (`getDetailsFromVariation`, `getVariationGroupPrice`). Teste UC-PETQ-02..05. Mergeado com ok do [W]. |
| [#8774](https://github.com/wagnerra23/oimpresso.com/pull/8774) | Page `Produto/Etiquetas/Index` (+ charter, casos UC-PETQ-01..05, contrato `produto-etiquetas` copiado do `_saida-07`, runbook MWART). `show()` atrás da flag `mwart.produto_etiquetas` (decisão [W] 2026-10-06). "Imprimir" abre `/labels/preview` com os mesmos parâmetros da Blade. Mergeado. |

## Regra mestre de valor
A etiqueta impressa não mudou: `preview()` e `preview_2.blade.php` intocados. A tela só reapresenta o preço.

```
caso                                    impresso antes  impresso depois  tela antes          tela depois
sem grupo, Com imposto                  20,00           20,00            não mostrava preço  20,00
grupo Atacado (15,5), imposto 10%, Sem  14,09           14,09            não mostrava preço  14,09
```
Dois caminhos no UC-PETQ-02: à mão (15,5 × 100 / 110 = 14,0909) e a mesma chamada de `ProductUtil` que `preview()` faz.

## Flag (ligar é com [W], depois do merge)
```
MWART_PRODUTO_ETIQUETAS=true
MWART_PRODUTO_ETIQUETAS_BIZ=1
```
`_BIZ` vazio = todas as empresas. Sem a flag, `/labels/show` segue na Blade; `?classico=1` força a Blade.

## Provas do json
- `resources/js/Pages/Produto/Etiquetas/Index.tsx` existe ✔
- `LabelsController.php` contém `Inertia::render('Produto/Etiquetas/Index'` ✔
- `governance/design/contracts/produto-etiquetas.contract.json` existe ✔

## Pendente / decisão [W]
1. **Grupo sem preço cadastrado derruba a folha impressa** (`num_format('')` cai no `catch` do `preview()`). Não consertado: muda a impressão. A prévia mostra "sem preço no grupo".
2. **A impressão ignora parte do que a tela oferece:** `preview_2.blade.php` não usa o "Corpo (pt)" e não imprime lote, validade nem data de embalagem. A tela mantém os controles (como a Blade e o protótipo); a prévia desenha só o que sai no papel. Achado para o Cowork: o protótipo mostra esses campos na etiqueta.
3. **Sem US no SPEC para Etiquetas:** o charter fica sem `related_us` (advisory). Criar a US é backlog [W].
4. Fora desta thread: UC-ETQ-02 do F1 (vários produtos vindos da seleção do índice — `/labels/show` aceita um `product_id` ou `purchase_id`) e a prova de impressão R7 (marcas de corte, tira CMYK), que o DS do app não tem.
5. Smoke em produção só depois de [W] ligar a flag no biz=1.

## Nota para o Cowork
- A tela usa cm na cota da prévia: é a unidade que o `preview()` usa para `width`/`height` do modelo; o protótipo mostra mm.
