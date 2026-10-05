---
sessao: "_saida-03"
thread: "03 · senha/contra_senha — sem código: gravação já parada (#8365), drop vetado (D4), fillable travado pelo gate Tier 0"
dono: "[CL]"
data: 2026-10-05
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main 683ad09f4d
---
# _saida-03

## Veredito
**Thread fechada sem PR de código**, por decisão [W] 2026-10-05. As três partes da ficha têm destino já dado:

| parte | estado | onde |
|---|---|---|
| parar de gravar `senha`/`contra_senha` vindos do desktop | **feito** | thread 02, [#8365](https://github.com/wagnerra23/oimpresso.com/pull/8365) (mergeado 2026-10-01) |
| migration dropando as colunas | **vetado** | D4 da 2ª rodada (`_DECISOES-W-2026-10-01b.md`), [W]: *"não"* |
| tirar os dois campos do `fillable` | **não entregue**: travado pelo gate Tier 0 | ver abaixo |

## Por que o `fillable` não saiu
Tentei em [#8660](https://github.com/wagnerra23/oimpresso.com/pull/8660), fechado sem merge. O PR tirava os dois campos do
`$fillable` de `Licenca_Computador` e trazia um teste. A lane required `No hardcode business_id (Tier 0)`
(`MultiTenantScopeArchitectureTest`) reprovou:

> Model alterado ainda depende de tolerância grandfathered (Tier 0 ADR 0093) em 1 arquivo(s):
> `Modules/Officeimpresso/Entities/Licenca_Computador.php`. Ação: aplicar escopo automático de
> business neste mesmo PR.

O model está em `governance/multi-tenant-scope-baseline.json` (grandfathered). **Qualquer edição no arquivo acorda a
dívida.** Pagar a dívida não cabe nesta thread:
- a tela de licenças é operada **cross-tenant por desenho**. O superadmin e, pela D1, os funcionários do negócio 1
  veem e bloqueiam máquinas de outros negócios: `viewLicencas`, `toggleBlock`, `show`, `update`, `destroy`, e
  `LicencaService::listarPorEmpresa/alternarBloqueio/atualizar/remover`;
- o `ScopeByBusiness` filtra pela sessão e confinaria esses operadores ao negócio 1. Seriam precisos
  `withoutGlobalScope` com `// SUPERADMIN:` em uns 6 caminhos, mais um teste cross-tenant: uma thread Tier 0
  própria, com mudança de comportamento das telas;
- a API do Delphi **não** seria afetada, porque não tem sessão e o escopo não filtra.

A alternativa de declarar o model em `governance/multi-tenant-global-model-contract.json` é exceção ao ADR 0093.
Ela exige decisão [W] e não foi escolhida.

**Risco residual, medido:** nenhum caminho faz mass-assignment de `senha`/`contra_senha`. Os validators da API não
têm os campos, `LicencaService::criar/atualizar` não tem chamador com eles, e a gravação do desktop é por `setAttribute`
com os segredos descartados (thread 02). O ganho do `fillable` seria defesa em profundidade, não conserto de vazamento.

## Prova do json
- `nao_contem` `'contra_senha'` em `${MOD}/Entities/Licenca_Computador.php`: **não cumprida**. O arquivo segue com
  `'contra_senha'` no `fillable`, de propósito, pelos motivos acima.

## Placar
entregue 0 de 1 PR de código da thread · 2 de 3 partes da ficha resolvidas por outro caminho (#8365 + D4).

## Errata / observações (não editei o `00-INDICE.md`)
- O título da thread no índice ("Dropar colunas senha/contra_senha") e a prova `nao_contem 'contra_senha'` ficaram
  **desatualizados** pela D4. Sugestão ao Cowork: marcar a 03 como encerrada por D4 e mover a parte do `fillable`
  para uma thread futura "escopo de business no Licenca_Computador (operador cross-tenant)", se [W] quiser pagar
  essa dívida.
- `${CAPI}/LicencaComputadorController.php` tem o comentário *"As colunas seguem na tabela até a thread 03 (D4)
  dropá-las por migration"*. Pela D4 isso não acontece, e o comentário ficou falso. Fica para quem tiver `${CAPI}`
  no prefixo.
- Dado histórico (senhas antigas nas colunas e no `activity_log`): limpeza é decisão [W] (LGPD), já anotada na `_saida-02`.

## PR
este recibo vai no PR da branch `claude/officeimpresso-saida-03`. O código tentado ficou em #8660 (fechado).
