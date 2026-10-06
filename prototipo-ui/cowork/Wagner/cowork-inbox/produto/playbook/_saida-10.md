---
sessao: "10"
titulo: "Produto/Cadastros — criar/editar em drawer — saída da thread"
autor: "[CL]"
criado: 2026-10-05
base: 3aea4a20da
thread: 10-cadastros-form.md
veredito: "em curso — PR-a e PR-b entregues no main (squash #8721, commit cd212e2c3d, 2026-10-05). Falta a comparação do lado da Page e o smoke de UI das 5 abas."
---

# _saída 10 · Criar/editar em drawer

**Placar:** entregue 5 de 6 abas com drawer (Unidades, Marcas, Categorias, Variações, Garantias) ·
ausente Grupos de preço (fora da ficha, ver Pendências).

> **Atualização 2026-10-06 — o PR-b já está no `main`.** A primeira versão deste recibo dizia "PR-b não
> começou". Era verdade quando foi escrita, e ficou velha no mesmo dia. O PR-b ([#8724](https://github.com/wagnerra23/oimpresso.com/pull/8724))
> foi mergeado às 19:24Z na branch do PR-a (`claude/produto-thread10-cadastros-form`), não no `main`.
> O PR-a ([#8721](https://github.com/wagnerra23/oimpresso.com/pull/8721)) entrou no `main` às 21:55Z por squash,
> com o head no commit de merge do #8724 (`d7c84071df`). O squash `cd212e2c3d` leva os dois.
> Conferido: `git diff cd212e2c3d d7c84071df` sai vazio na Page, no drawer, no `TaxonomyController` e no teste.
> Na fila, o PR-b é a thread **11** do índice (`11-cadastros-form-b.md`), e o recibo dela é o `_saida-11.md`.

## Abertura
- Dependências: thread 02 (#8371, #8375) e 03 (#8668 e o PR das abas) mergeadas. Placar no início: `10 [proximo]`.
- Sem colisão: `dup-detector --path` livre nos 3 arquivos do prefixo, nenhuma sessão viva com "thread 10" no título.
- `nao_toca` respeitado: `governance/design/contracts/produto-cadastros.contract.json` não foi editado;
  `contrato-de-tela` saiu `✅ limpo` depois da mudança.

## PR-a · Unidades + Marcas — [#8721](https://github.com/wagnerra23/oimpresso.com/pull/8721)

| arquivo | o quê |
|---|---|
| `Pages/Produto/Cadastros/_components/CadastroDrawer.tsx` | drawer (Sheet 760, PT-02). Títulos, rótulos, ajudas e placeholders de `produto-cadastros.jsx` (AbaUnidades :315-334 · AbaMarcas :430-436). Salvar desabilitado sem os obrigatórios. |
| `Pages/Produto/Cadastros/Index.tsx` | Novo/Editar de Unidades e Marcas abrem o drawer. As outras 4 abas seguem no link da Blade. Salvar recarrega só a aba. |
| `app/Http/Controllers/UnitController.php` | props `base_id`, `multiplicador` (no formato do modal clássico), `oficina` por marca e `oficina` da tela. `store`/`update` recusam `base_unit_id` de outro negócio ou da própria unidade. |
| `tests/Feature/Produto/ProdutoCadastrosContratoTest.php` | UC-PCADAP-17..19 (tenant 98 × 99), lane `estoque-pest`. |
| `…/Index.casos.md` · `…/Index.charter.md` | UCs novos, R2 parcial, Non-Goals recortados ao que falta. |

**Mesmas rotas de gravação:** `POST/PUT /units` e `POST/PUT /brands`, com os mesmos nomes de campo do
modal clássico. O drawer manda JSON com `X-Requested-With` (o `update` só responde a ajax).

**Estoque (regra mestre):** o múltiplo vai como o operador digita e o `num_uf` do servidor lê, como já
fazia no modal. O drawer de edição abre com o valor no formato do modal clássico (`1000`, `0,5`) e
desligar o múltiplo manda `define_base_unit=0` explícito, o mesmo contrato do UC-PCADAP-12. A dupla
prova (digitado × reaberto-e-salvo-sem-mexer) está no UC-PCADAP-17 e a tabela antes→depois no corpo
do PR. Nenhum registro existente muda: o PR não migra dado.

**Tier 0 achado no caminho:** antes, `store` e `update` gravavam qualquer `base_unit_id` vindo do
form, inclusive de outro negócio. Agora respondem `success: false` e não gravam (UC-PCADAP-18).

## PR-b · Categorias + Variações + Garantias — [#8724](https://github.com/wagnerra23/oimpresso.com/pull/8724) (no `main` via squash do #8721)

| arquivo | o quê |
|---|---|
| `Pages/Produto/Cadastros/_components/CadastroDrawer.tsx` | o drawer passa a cobrir 5 tipos. Copy e campos do protótipo (AbaVariacoes :219-232 · AbaCategorias :379-393 · AbaGarantias :468-478). |
| `Pages/Produto/Cadastros/Index.tsx` | cinco abas abrem o drawer. Grupos de preço segue no link da Blade. |
| `app/Http/Controllers/TaxonomyController.php` | **Tier 0:** `store`/`update` conferem o `parent_id`. Só aceita categoria principal do mesmo negócio e tipo, nunca a própria categoria, e uma categoria com filhas não vira filha. |
| `app/Http/Controllers/UnitController.php` | props `valor_ids` (variação) e `duracao_n`/`duracao_tipo` (garantia), para o drawer abrir preenchido. |
| `tests/Feature/Produto/ProdutoCadastrosContratoTest.php` | UC-PCADAP-20..22 (tenant 98 × 99). Veredito em `scripts/casos-test-results.json`: `pass` nos três. |

**Mesmas rotas de gravação:** `POST/PUT /taxonomies`, `/variation-templates` e `/warranties`, com os
nomes de campo do modal clássico. `VariationTemplateController` e `WarrantyController` não foram tocados.

**Regras mantidas:**
- Excluir categoria **segue recusando** quando ela tem filhas ou produtos (`_saida-02`, E-CATEGORIA 05/10). O drawer não mexe nisso.
- Valor de variação já gravado não sai pelo drawer, porque o servidor só renomeia (`edit_variation_values`). O ✕ fica desabilitado nesse caso, com o motivo no título.

**Valor e estoque:** o PR-b não toca valor nem estoque. Unidade e múltiplo estão no PR-a. Renomear
uma variação propaga o nome para os produtos, como o modal clássico já fazia.

## Desvio declarado — forma do contêiner

A ficha pede "drawer PT-02 do protótipo". O protótipo **não tem drawer**: `produto-cadastros.jsx`
desenha um modal central (`FormModal`, 560/620px), conferido no Cowork vivo em 2026-10-05
(`get_file` devolve o mesmo `FormModal`). Segui a ficha na forma (drawer PT-02, 760px) e o
protótipo no conteúdo. **Pedido ao Cowork:** confirmar o drawer na ficha e no protótipo, ou mandar
voltar ao modal central (troca só o contêiner).

## Comparação (prova do índice)

`design-diff --probe` rodado no protótipo (servido do espelho com o `_ds_bundle.js` de
`prototipo-ui/design-system/`), aba Unidades, "Nova unidade" aberto, tema dark:

| papel | protótipo |
|---|---|
| título do form (`h3`) | 17px · 600 |
| primário (Salvar) | bg `oklch(0.7 0.15 295)` · texto `oklch(0.14 0.02 295)` |
| campos | Nome * · Símbolo * (ajuda) · Aceita quantidade decimal · Cadastrar como múltiplo de uma unidade base |

**Lado da Page: NÃO MEDIDO.** Não havia app local nesta sessão (Herd parado) e a Page só existe em
produção depois do merge, que é do [W]. O `--compare --check` fica para depois do deploy; até lá não
há veredito de fidelidade.

## Pendências
1. ~~**PR-b**~~ entregue (ver acima).
2. **Grupos de preço** ficou fora dos dois PRs da ficha e ainda abre o modal da Blade.
   Pedido ao Cowork: dizer se ganha uma thread própria.
3. **Comparação do lado da Page** (`design-diff --compare`) das 5 abas: **não feita**. O PR-a e o PR-b
   já estão em produção, então ela pode rodar agora.
4. **Smoke de UI pós-deploy com screenshot** das 5 abas: **não feito**. O #8724 saiu com
   `no-ui-smoke` ("smoke visual junto do PR-a") e esse smoke não tem recibo.
5. **Desvio do contêiner:** o corpo do #8724 registra *"Decisão [W] 2026-10-05: drawer PT-02 mantido"*.
   Falta o protótipo/ficha do Cowork refletirem o drawer (o `FormModal` segue lá).
6. **Visual-regression:** a tela não tem baseline própria e nenhuma foi regravada (ADR 0409).
