---
sessao: "_saida-01"
thread: "01 · L1+L7 · API de licença sem escopo de negócio (::all() e find($id))"
dono: "[CL]"
data: 2026-09-30
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main 7201ce433
---
# _saida-01

## Entregue
`Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php`:
- `index()` (era `:243` `Licenca_Computador::all()`) → só os equipamentos do negócio do token.
- `show()` / `update()` / `destroy()` (eram `:267` · `:293` · `:310` `::find($id)`) → `find` dentro do mesmo
  escopo. Equipamento de outro negócio responde **404 com a mesma mensagem** do inexistente.
- `update()`: o `find` escopado passou para **antes** da validação (as regras `unique`/`exists` vazariam a
  existência do id), e recusa com 404 um `business_id` diferente do do equipamento (não move equipamento
  entre negócios pela API).
- Escopo num helper `doNegocioDoToken()`: token sem `business_id` → escopo **vazio** (`1 = 0`), nunca
  `whereNull`, que casaria linhas órfãs.

**Não mudou:** rotas (`Modules/Connector/Routes/api.php` intocado — está no `nao_toca`), formato de resposta,
`ProcessaDadosCliente`, `processarComEmpresa`, `processarApenasHd`, `saveEquipamento`.

Teste: `Modules/Connector/Tests/Feature/LicencaComputadorApiEscopoTest.php` (lane `modules-pest` · Connector),
6 casos — index só do negócio A · show B=404/A=200 · update B=404 sem alterar · destroy B=404 e o registro
fica · token sem negócio não vê nada (com linha órfã no sqlite) · HD cadastrado segue recebendo
`S;Cliente e equipamento liberados`. Tenant 98 (A) × 1 (B), nunca biz=4. Hermético na lane sqlite
`:memory:` (cria as tabelas mínimas só se ausentes e as derruba no `afterEach`).

## Prova do json
- `nao_contem` `Licenca_Computador::all()` em `${CAPI}/LicencaComputadorController.php` — `grep` no branch: 0.
  Também 0 ocorrência de `Licenca_Computador::find(`.

## Errata ao índice (não editei o `00-INDICE.md`)
- **L1/L7 eram latentes, não expostos.** O índice diz que `::all()` "devolve equipamento de todos os
  negócios a qualquer token `auth:api`". Medido: `index/show/update/destroy`/`store` deste controller
  **não têm rota** — `grep -rn LicencaComputadorController` em `routes/` + `Modules/*/Routes/` acha só
  `processa-dados-cliente` e `salvar-equipamento/{business_id}` apontando para ele. O conserto vale (método
  público sem escopo vira vazamento no dia em que alguém registrar um `Route::resource`), mas não havia
  vazamento ativo por esses quatro métodos.

## Pendente (fora do escopo da ficha — decisão [W])
1. **`salvar-equipamento/{business_id}` (roteado, chamado pelo desktop):** grava equipamento no
   `business_id` que vem da **URL**, sem conferir contra o token. Esse sim é escrita cross-tenant viva.
   Não toquei: muda o comportamento de um endpoint que o Delphi usa, e não sei se o token do desktop é de
   usuário por cliente ou de um usuário central WR — escopar pelo token pode quebrar o campo. Precisa
   dessa resposta antes.
2. **`store()`** (sem rota) aceita qualquer `business_id` do payload (`StoreLicencaComputadorRequest`).
   Fica para a mesma decisão.
3. `processarApenasHd` atualiza o HD em **todos** os negócios por desenho (L4 do índice) — não mexi.

## PR
#PR_NUM
