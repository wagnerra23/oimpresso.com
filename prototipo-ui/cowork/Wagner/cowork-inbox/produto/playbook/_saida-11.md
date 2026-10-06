---
sessao: "11"
titulo: "Produto/Cadastros — criar/editar em drawer, PR-b — saída da thread"
autor: "[CL]"
criado: 2026-10-06
base: a8e0624504
thread: 11-cadastros-form-b.md
veredito: "entregue no main (squash #8721, commit cd212e2c3d), com a prova de comparação NÃO MEDIDA."
---

# _saída 11 · PR-b — Categorias · Variações · Garantias em drawer

**Placar:** entregue 3 de 3 abas da ficha com drawer (Categorias, Variações, Garantias). Somando o PR-a,
a tela tem 5 de 6 abas com drawer; Grupos de preço fica fora da ficha.

## Correção do `_saida-10`: o PR-b já estava entregue

O `_saida-10.md` ainda diz *"PR-b (Categorias, Variações, Garantias) não começou"*. Era verdade
quando foi escrito e envelheceu no mesmo dia. Esta correção fica aqui, e não lá, porque o `_saida-10`
já foi verificado contra o Cowork e editá-lo no Code reabre o check do espelho (§5 2026-09-24).

- O PR-b saiu como [#8724](https://github.com/wagnerra23/oimpresso.com/pull/8724) e foi mergeado às 19:24Z
  de 2026-10-05 na branch do PR-a (`claude/produto-thread10-cadastros-form`), **não** no `main`.
- O PR-a [#8721](https://github.com/wagnerra23/oimpresso.com/pull/8721) entrou no `main` às 21:55Z por
  squash, com o head no commit de merge do #8724 (`d7c84071df`). O squash `cd212e2c3d` leva os dois.
- Conferido em 2026-10-06: `git merge-base --is-ancestor d7c84071df origin/main` é falso (o commit não
  está no main), mas `git diff cd212e2c3d d7c84071df` sai vazio na Page, no drawer, no
  `TaxonomyController` e no teste. O conteúdo está todo no main.

## O que o PR-b entregou

| arquivo | o quê |
|---|---|
| `Pages/Produto/Cadastros/_components/CadastroDrawer.tsx` | o drawer do PR-a passa a cobrir 5 tipos. Copy e campos do protótipo (AbaVariacoes :219-232 · AbaCategorias :379-393 · AbaGarantias :468-478). |
| `Pages/Produto/Cadastros/Index.tsx` | cinco abas abrem o drawer. Grupos de preço segue no link da Blade. |
| `app/Http/Controllers/TaxonomyController.php` | **Tier 0:** `store`/`update` conferem o `parent_id`. Só aceitam categoria principal do mesmo negócio e tipo, nunca a própria categoria, e categoria com filhas não vira filha. |
| `app/Http/Controllers/UnitController.php` | props `valor_ids` (variação) e `duracao_n`/`duracao_tipo` (garantia), para o drawer abrir preenchido. |
| `tests/Feature/Produto/ProdutoCadastrosContratoTest.php` | UC-PCADAP-20..22 (tenant 98 × 99). Veredito em `scripts/casos-test-results.json`: `pass` nos três. |

As rotas de gravação são as mesmas: `POST/PUT /taxonomies`, `/variation-templates` e `/warranties`,
com os nomes de campo do modal clássico. `VariationTemplateController` e `WarrantyController` não
foram tocados.

**Regras mantidas:**
- Excluir categoria com subcategorias ou produtos **segue recusando** (E-CATEGORIA, 05/10 · `_saida-02`).
- Valor de variação já gravado não sai pelo drawer, porque o servidor só renomeia (`edit_variation_values`).
  O ✕ fica desabilitado, com o motivo no título.

**Valor e estoque:** não toca nenhum dos dois. Unidade e múltiplo estão no PR-a.

**Contêiner:** o corpo do #8724 registra *"Decisão [W] 2026-10-05: drawer PT-02 mantido"*. O protótipo
continua desenhando um modal central (`FormModal`).

## Pendências
1. **Prova `comparacao`** (drawer do protótipo × Page) das 5 abas: **NÃO MEDIDA**, e por isso a thread
   não é `feito` no placar. As abas já estão em produção, então o `design-diff --compare` pode rodar.
2. **Smoke de UI pós-deploy com screenshot**: não feito. O #8724 saiu com `no-ui-smoke` ("junto do PR-a"),
   e esse smoke não tem recibo.
3. **Grupos de preço** ficou fora da ficha e ainda abre o modal da Blade. Pedido ao Cowork: dizer se ganha
   uma thread própria.
4. **Protótipo/ficha do Cowork** refletirem o drawer PT-02 decidido pelo [W].

## Abertura (2026-10-06)
A sessão foi aberta para fazer o PR-b. Antes de qualquer edit, o `git log --remotes` no prefixo mostrou o
#8724 já mergeado, a sessão parou e virou este recibo.
