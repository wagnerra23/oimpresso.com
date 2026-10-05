---
sessao: "_saida-03"
thread: "03 · senha/contra_senha — sai do fillable, colunas ficam (D4 = NÃO dropar)"
dono: "[CL]"
data: 2026-10-05
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main cb1fe1d6f4
---
# _saida-03

## Decisão usada
A ficha 03 manda *"só com D4 — migração dropando as colunas + tirar de `fillable`"*. A D4 foi respondida
duas vezes: a 1ª rodada (`_DECISOES-W-2026-10-01.md`) dizia "sim"; a **2ª rodada
(`_DECISOES-W-2026-10-01b.md`) revoga: [W] *"não"* — NÃO dropar `senha`/`contra_senha`, a thread 03
não roda a migration destrutiva.** O json do índice já registra `"resposta": "NÃO dropar senha/contra_senha"`.

Por isso esta thread entrega **só a metade não destrutiva**: tirar os dois campos do `fillable`.
**Nenhuma migration foi escrita.** Não há PR-b de drop. Se [W] mudar a D4, o drop volta a ser uma
thread própria, em PR separado (o deploy roda `migrate --force` automático, então o merge da migration
já é o drop em produção).

O "parar de gravar" (PR-a do pedido) **já estava feito** pela thread 02, [#8365](https://github.com/wagnerra23/oimpresso.com/pull/8365)
(mergeado 2026-10-01): a API do desktop (`saveEquipamento`) não grava os dois campos e a resposta ao Delphi não mudou.

## Entregue
`Modules/Officeimpresso/Entities/Licenca_Computador.php`:
- `senha` e `contra_senha` saem de `$fillable`. Efeitos:
  - nenhum `fill()`/`create()`/`update($array)` consegue mais gravar segredo por mass-assignment;
  - o `LogsActivity` (`logFillable`) deixa de poder levar a senha ao `activity_log`, mesmo se algum
    caminho futuro marcar o campo como dirty.
- O docblock, que dizia que os segredos eram "automaticamente fillable", foi corrigido para o estado real.

**O que o desktop Delphi manda (conferido antes de mexer):** o `LICENCIAMENTO` continua trazendo `SENHA`
e `CONTRA_SENHA`. A gravação é por `setAttribute` + `salvarSemSegredosDoDesktop()` (thread 02), que
**não passa pelo `fillable`**, então o eco na resposta segue igual. Os validators da API
(`StoreLicencaComputadorRequest`, `update`) não têm esses campos, e `LicencaService::criar/atualizar`
não tem chamador. Nenhum leitor dos dois campos foi encontrado. O `git grep` em `Modules/` só achou
docs/charter/casos que proíbem exibir.

Teste: `Modules/Officeimpresso/Tests/Feature/LicencaComputadorSemSegredoNoFillableTest.php`, com 3 casos:
`isFillable` falso para os dois, com âncora positiva em `hostname`; `fill()` ignora os segredos e mantém o
resto; `activity_log` da alteração existe e traz `hostname` (sem vácuo), mas não traz `senha`/`contra_senha`
nem os valores. Tenant fictício 98, nunca biz=4. Não há consulta nova, logo não há eixo cross-tenant a
testar (é atributo de model). O teste entrou na lane `officeimpresso-pest.yml` (allowlist MySQL).
A prova do teste é o CI do PR: não rodei Pest local, por regra.

## Prova do json
- `nao_contem` `'contra_senha'` em `${MOD}/Entities/Licenca_Computador.php`: `grep -c` = 0. Também
  `'senha'` = 0.

## Placar
entregue 1 de 1 PR da thread (só a metade de `fillable`; o drop fica fora por D4).

## Errata / observações (não editei o `00-INDICE.md`)
- O título da thread no índice ("Dropar colunas senha/contra_senha") ficou **desatualizado** pela D4 da
  2ª rodada. Sugestão ao Cowork: renomear para "senha/contra_senha fora do fillable (colunas ficam, D4)".
- `${CAPI}/LicencaComputadorController.php` (fora do prefixo, `nao_toca`) tem o comentário
  *"As colunas seguem na tabela até a thread 03 (D4) dropá-las por migration"*. Pela D4 isso não vai
  acontecer, e o comentário ficou falso. Não toquei. Fica para quem tiver o `${CAPI}` no prefixo.
- Dado histórico: as linhas antigas seguem com a senha nas colunas, e o `activity_log` guarda as alterações
  antigas. Limpeza é decisão [W] (LGPD), fora desta ficha (já anotado na `_saida-02`).

## PR
o PR que traz este recibo, na branch `claude/officeimpresso-thread-03-senha-desktop`.
