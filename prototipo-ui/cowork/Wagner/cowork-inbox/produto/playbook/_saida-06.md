---
sessao: "06"
titulo: "Produto/AtualizarPreco — saída da thread"
autor: "[CL]"
criado: 2026-10-01
base: 81141329b
thread: 08-atualizar-preco.md
veredito: "entregue — a tela vira Inertia com conferência (antes → depois sem gravar); exportar e aplicar seguem nas mesmas rotas e no mesmo código. Achado Tier 0 no import() legado fica para [W]."
---

# _saída 06 · Atualizar preço por planilha

## Abertura
O placar marcava `pendente` por dois motivos, os dois cobertos pelas exceções da leva:
- **01 "não feita"**: PR #8349 mergeado; as 3 provas `->can('` passam. O único motivo restante é D1/D2, respondidas em `_DECISOES-W-2026-10-01.md` (exceção 1).
- **07 "não feita"**: o contrato desta tela estava guardado no `_saida-07.md`, barrado pela lei "contrato só entra com a Page". Copiado sem mudar a copy (exceção 3).

## O que entrou

| arquivo | o quê |
|---|---|
| `app/Http/Controllers/SellingPriceGroupController.php` | `updateProductPrice()` → `Inertia::render('Produto/AtualizarPreco/Index')` (grupos ativos do negócio, total de linhas deferido, `erro` lido de `notification`); `?classico=1` mantém a Blade. `import()` ganhou **só** um desvio no topo: `conferir=1` → `conferirPlanilha()`. O corpo do `import()` não mudou. |
| `conferirPlanilha()` / `fotoPrecos()` | A conferência roda o próprio `import()` dentro de uma transação e desfaz. Devolve antes → depois por SKU e preço, só deste negócio, e alerta SKU que também existe em outro negócio. Não tem parse, arredondamento nem gravação própria. Gate `product.update`. |
| `resources/js/Pages/Produto/AtualizarPreco/Index.tsx` (+ charter + casos) | 2 passos + conferência + 4 instruções. A planilha sobe como arquivo; o navegador não lê nem envia número. "Aplicar preços" só habilita com conferência aceita e sem alerta, e envia o mesmo arquivo para `/import-product-price`. |
| `governance/design/contracts/produto-atualizar-preco.contract.json` | copiado do `_saida-07`. `contrato-de-tela --contract` limpo; `--map --check` rc 0. |
| `memory/requisitos/Produto/_telas/RUNBOOK-produto-atualizar-preco.md` | runbook MWART (o hook exige antes da Page). |
| `tests/Feature/Produto/ProdutoAtualizarPrecoContratoTest.php` | UC-PATPRC-01..05, tenant 98 × 99. |
| `.github/workflows/estoque-pest.yml` | controller + Page no trigger da lane, como a thread 02 fez (fora do prefixo: sem isso mexer no controller não dispara o teste, §5 2026-08-02). |
| `SUPERFICIE.md` · `_STATUS-GENERATED.md` | regenerados. |

## Regra mestre de valor — as duas provas
- **(a) caminho antigo × novo** (UC-PATPRC-02): a mesma planilha aplicada pelo formulário da Blade e por visita Inertia, a partir do mesmo estado, grava `sell_price_inc_tax`, `default_sell_price`, `profit_percent` e o preço do grupo idênticos; números concretos `1234.56` / `999.9`. Planilha com `1.234,56` / `999,90` é recusada pelos dois sem gravar.
- **(b) prévia** (UC-PATPRC-03): a conferência lista antes → depois por SKU e o banco termina igual ao início.
- Veredito: CI do PR (não rodei Pest local nem no CT 100, conforme a thread).

## Provas do json
- `resources/js/Pages/Produto/AtualizarPreco/Index.tsx` existe ✔
- `SellingPriceGroupController.php` contém `Inertia::render('Produto/AtualizarPreco/Index'` ✔

## Pendente / decisão [W]
1. **P0 Tier 0 no `import()` legado — NÃO consertado aqui.** `Variation::where('sub_sku', …)->first()` não filtra negócio: SKU repetido entre negócios grava o preço no cadastro de outro negócio. Consertar muda a forma de gravar preço (regra mestre) → é decisão [W]. Mitigação desta thread: a conferência alerta o SKU (`outro_negocio`/`ambiguo`) e a tela bloqueia "Aplicar". A Blade (`?classico=1`) e o POST direto seguem expostos.
2. **`export` e `import` não checam permissão** (só a página checa `product.update`). Fora do escopo "troca de tela"; decisão [W] junto do item 1.
3. **Preço de venda não é validado como número** no `import()` (só os de grupo). Para `1.234,56` o UC-PATPRC-02 mede que nada é gravado; a mensagem que o usuário recebe nesse caso não foi medida (hipótese: erro técnico, não "preço não numérico"). Mudar é mexer no parse → [W].
4. A ficha pede "import recusa linha com SKU/variação alterados"; o `import()` atual só recusa SKU inexistente. Não implementado (mudaria o import). Charter registra como Non-Goal.
5. UC-PRC-04 ("Editar preço na tela" → `add-selling-prices`) é por produto; a tela aponta para `/products/unificado`, onde a ação por produto existe.
6. Alvo/medida: **NÃO MEDI** (`alvo:medir` não rodado — ambiente sem `node_modules` neste worktree).

## PR
_preenchido no PR_
