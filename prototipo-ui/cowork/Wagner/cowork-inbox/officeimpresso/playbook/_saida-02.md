---
sessao: "_saida-02"
thread: "02 · L2 · parar de gravar senha/contra_senha vindas do desktop"
dono: "[CL]"
data: 2026-10-01
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main 2472d5479
---
# _saida-02

## Entregue
`Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php` — `saveEquipamento()` (a ficha citava
`:195`/`:214`; no main lido as linhas eram `:238`/`:257`, andaram com #8358 e #8326):
- `senha` e `contra_senha` do LICENCIAMENTO **não vão mais pro banco**. INSERT: a chave sai do insert (coluna
  fica `NULL`). UPDATE: o valor já gravado é mantido (o campo não fica dirty, a coluna não muda).
- O desktop **continua podendo mandar** os dois campos: são aceitos e ignorados, a requisição não falha.
- **Resposta inalterada.** `processa-dados-cliente` responde string `S;…`/`N;…` e não depende dos campos.
  `salvar-equipamento/{business_id}` devolve o próprio model em JSON, e esse JSON **sempre ecoou** `senha` e
  `contra_senha` do payload. Para não mudar o wire, o helper `salvarSemSegredosDoDesktop()` tira os segredos
  só do que é gravado e remonta os atributos na **mesma ordem de chaves** com o eco do payload. O model
  termina sincronizado (sem dirty), então um save posterior também não grava o eco.
- Efeito colateral bom: o `LogsActivity` (logFillable + logOnlyDirty) **para de registrar a senha** no
  `activity_log`, porque o campo nunca fica dirty.

**Leitores dos campos (verificado antes de mexer):** `git grep` por `senha`/`contra_senha`/`SENHA` no repo
inteiro — nenhum fluxo LÊ `licenca_computador.senha|contra_senha` (nenhuma validação/login do desktop
compara a senha gravada, nenhuma view/Page exibe). Os únicos sites eram as duas gravações + `fillable` da
entidade + a migration. Por isso não houve motivo para parar.

Teste: `Modules/Connector/Tests/Feature/LicencaComputadorSegredoDesktopTest.php`, 4 casos — INSERT (coluna
NULL + lista **completa e ordenada** das chaves da resposta igual à do código antes da thread + eco dos
valores + model sem dirty) · UPDATE (coluna mantém `ANTIGA`, demais campos atualizam, resposta ecoa o
payload) · rota `salvar-equipamento` com usuário central aceita payload com senha e não grava · cross-tenant
(mesmo HD em 98 e 1: salvar em 98 não toca a linha do 1). Tenant 98 × 1, nunca biz=4. Hermético no sqlite.
Prova do teste = CI do PR (não rodei Pest local, por regra).

## Prova do json
- `nao_contem` `->contra_senha =` em `${CAPI}/LicencaComputadorController.php` — `grep`: 0.
- `nao_contem` `->senha =` em `${CAPI}/LicencaComputadorController.php` — `grep`: 0.

## Errata / observações ao índice (não editei o `00-INDICE.md`)
- **"byte a byte" provado estruturalmente, não por diff contra um run do código antigo:** o teste fixa a lista
  ordenada de chaves (ordem das atribuições do código antigo + `created_at`/`id` do INSERT) e os valores
  ecoados. Diferença de comportamento real, em direção boa: payload com `SENHA` >15 chars antes podia estourar
  a coluna `varchar(15)` (500 em MySQL estrito); agora não é gravado, então não estoura.
- A resposta de `salvar-equipamento` **continua ecoando** a senha que o próprio desktop mandou (manter o wire
  exige). Não é vazamento novo, mas é o motivo de não dar pra só tirar a chave. Se [W] quiser tirar o eco,
  é mudança de contrato Delphi (remover campo do response) — decisão [W], não desta thread.
- `memory/reference/contrato-delphi-inviolavel.md` §2.4 diz que `salvar-equipamento` responde `S;`/`N;`
  como o Gen 1. **No código, o sucesso devolve o model em JSON** (só a recusa do guard #8358 é `N;…`).
  Doc fora do prefixo — não toquei, fica registrado.

## Pendente
1. **Thread 03 (D4 aprovado):** dropar as colunas por migration + tirar de `fillable`
   (`Entities/Licenca_Computador.php`). Depois disso o eco continua funcionando (sai do payload, não do banco).
2. **Dado histórico:** as linhas já gravadas seguem com a senha nas colunas, e o `activity_log` guarda as
   alterações antigas de `senha`/`contra_senha` (o `LogsActivity` registrava). A 03 apaga as colunas; o
   `activity_log` precisa de limpeza própria — decisão [W] (LGPD), fora desta ficha.

## PR
(preenchido no corpo do PR)
