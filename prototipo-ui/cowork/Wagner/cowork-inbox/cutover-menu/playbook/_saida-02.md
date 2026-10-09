---
sessao: "_saida-02"
thread: "02 · Vendas: Rascunhos, Cotações, Assinaturas — React como padrão"
dono: "[CL]"
data: 2026-10-09
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main ff67c8356
---
# _saida-02

## Entregue
Decisão D1 ([W] 2026-10-07): *"por tela: React vira padrão, Blade só com ?classico=1"*.
Molde `UnitController@index`.

- `SellController@getDrafts` e `@getQuotations`, `SellPosController@listSubscriptions`:
  GET comum (o menu) abre a Page React; `?classico=1` abre o Blade; AJAX sem `X-Inertia`
  segue no caminho de antes.
- **Três defeitos das Pages consertados junto** — virar padrão uma tela que não funciona
  seria correção inerte (LC-30):
  1. **Rascunhos listava vazio.** A Page buscava os dados em `/sells/drafts`, que devolve
     HTML; o `res.json()` falhava e a lista ficava vazia. Agora lê
     `/sells/draft-dt?is_quotation=0`, o mesmo endpoint do DataTable do Blade.
  2. **Assinaturas, visita Inertia recebia JSON.** O ramo `ajax()` vinha antes do
     `X-Inertia`, e o Inertia manda `X-Requested-With` junto (§5 2026-09-08). A decisão
     React/Blade agora vem antes do ramo AJAX.
  3. **Assinaturas, parar/retomar dava 404 calado.** A Page chamava
     `/sells/recurring-toggle/{id}`; a rota real é `GET /toggle-subscription/{id}`.

## Provas
`tests/Feature/CutoverMenu/VendasListasSemXInertiaTest.php` (6 casos), ligado na lane MySQL
`sells-pest.yml` (allowlist + os dois filtros de path): GET comum → Page das 3 telas;
`?classico=1` → Blade das 3; visita Inertia real → Page das 3; fetch de Assinaturas → JSON;
o endereço de dados de Rascunhos devolve JSON de DataTable; o toggle de Assinaturas existe no
registro de rotas vivo. Pest local é proibido: o veredito é a lane.

## Para o [W] antes do merge (cutover)
- **Paridade de filtros:** o Blade de Rascunhos/Cotações tem filtros (local, cliente,
  usuário, período). As Pages React listam tudo, com busca por texto, sem esses filtros.
  Com React como padrão, quem usa o filtro passa a precisar do `?classico=1`.
- **Clientes:** vale para todas as empresas, incluindo a ROTA LIVRE. Não há flag por
  empresa nesta thread (D1 = por tela, não por empresa).

## venda-menu/Q2 fica superada — volta ao Cowork
A Q2 do `venda-menu` pede a chave `mwart.vendas_cotacoes` (desligada, ligada por empresa).
A D1 do `cutover-menu` (07/10) é posterior e decide outra regra para a mesma tela: React como
padrão, sem chave. Fazer as duas seria contraditório. Não escrevi `_saida-Q2` e não editei o
índice (lei do espelho): o Cowork decide se a Q2 sai do `venda-menu` ou se a D1 muda.
