---
sessao: "10"
titulo: "Produto/Cadastros — criar/editar em drawer — saída da thread"
autor: "[CL]"
criado: 2026-10-05
base: 3aea4a20da
thread: 10-cadastros-form.md
veredito: "em curso — PR-a (Unidades + Marcas) aberto em #8721, merge do [W] (toca estoque). PR-b (Categorias, Variações, Garantias) não começou."
---

# _saída 10 · Criar/editar em drawer

**Placar:** entregue 2 de 6 abas com drawer (Unidades, Marcas) · ausentes Categorias, Variações e
Garantias (PR-b da ficha) e Grupos de preço (fora da ficha, ver Pendências).

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
1. **PR-b** (ficha): Categorias, Variações e Garantias no drawer. Categorias mantém a recusa com filhas
   ou produtos (`_saida-02`).
2. **Grupos de preço** não aparece em nenhum dos dois PRs da ficha, mas também abre o modal da Blade.
   Pedido ao Cowork: dizer se entra no PR-b.
3. **Comparação do lado da Page** depois do deploy do PR-a.
4. **Visual-regression:** a tela não tem baseline própria e nenhuma foi regravada (ADR 0409).
