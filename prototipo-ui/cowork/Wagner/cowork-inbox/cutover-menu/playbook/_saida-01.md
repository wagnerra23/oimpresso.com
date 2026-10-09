---
sessao: "_saida-01"
thread: "01 · Produtos — React como padrão"
dono: "[CL]"
data: 2026-10-09
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main fe17bb457
---
# _saida-01

## Entregue: a lista
Decisão D1 ([W] 2026-10-07): *"por tela: React vira padrão, Blade só com ?classico=1"*.
Molde `UnitController@index`.

- `ProductController@index`: GET comum (o menu) abre a Page `Produto/Index`; `?classico=1`
  abre o Blade; o DataTable do Blade (AJAX sem `X-Inertia`) segue recebendo o JSON.
- **Defeito consertado junto.** O ramo `ajax()` vinha antes do `X-Inertia`, e o Inertia
  manda `X-Requested-With` junto (§5 2026-09-08). Efeito: a visita Inertia e o reload das
  props deferidas (`kpis`, `rows`, `categorias`) recebiam o JSON do DataTable, e a lista
  React ficava no esqueleto. A decisão React/Blade agora vem antes do ramo AJAX.

## Não entregue: o novo produto (`@create`)
`/products/create` continua no Blade pelo menu. Dois motivos medidos:

1. **A Page React não envia preço.** O `store()` monta a variação com `single_dpp`,
   `single_dsp` e `profit_percent`; nenhum dos três sai de `Produto/Create.tsx`
   (`Create.casos.md`, § sobre preço). Com o React como padrão, produto cadastrado pelo menu
   nasceria sem custo e sem preço de venda. É cálculo de valor: entra na REGRA MESTRE.
2. **A US-PROD-029 ([F] 2026-08-24) protege o cadastro da Larissa** (ROTA LIVRE): o
   cadastro novo ganha endereço e controller próprios, e o caminho dela não muda.

O teste trava esse estado (último caso). Para virar: o React do cadastro mandar preço
(decisão [F] em `Create.casos.md`: Non-Goal ou bug) e a US-PROD-029 andar.

## Provas
`tests/Feature/CutoverMenu/ProdutoSemXInertiaTest.php` (6 casos), ligado na lane MySQL
`estoque-pest.yml` (os dois filtros de path + `echo ... >> /tmp/run.txt`): GET comum → Page;
`?classico=1` → Blade; visita Inertia real → Page; reload de props deferidas → as props, não o
DataTable; AJAX sem `X-Inertia` → JSON do DataTable; GET comum do novo → Blade. Pest local é
proibido: o veredito é a lane.

Afirmações que ficaram falsas, corrigidas com nota datada: `ProdutoIndexContratoTest.php`
(cabeçalho) e `Produto/Index.casos.md` (escopo). Ficam com o texto de antes, por serem do
cadastro ou datadas: `SPEC.md` US-PROD-029 e `SDD-tela-cadastro-produto-v1.0.md` F7.

## Para o [W] antes do merge (cutover)
- Vale para todas as empresas, incluindo a ROTA LIVRE (D1 = por tela).
- Filtros: o Blade filtra por categoria, unidade, imposto, marca e local
  (`product/index.blade.php:41-86`); a lista React tem busca, categoria e "mostrar inativos".
  Quem usa os outros filtros passa a precisar do `?classico=1`.
- A thread pedia lista **e** novo. Só a lista foi; o novo depende de [F] (acima).
