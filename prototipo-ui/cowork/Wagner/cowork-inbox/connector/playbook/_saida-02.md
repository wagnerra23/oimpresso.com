---
sessao: "_saida-02"
thread: "02 · CONN-O2 · excluir revoga tokens em transação; install/uninstall/update fora de GET"
dono: "[CL]"
data: 2026-10-01
base_lida: wagnerra23/oimpresso.com@main e2533866f
---
# _saida-02

## Entregue
- `Modules/Connector/Http/Controllers/ClientController.php`
  - `destroy`: acha o client **só** se o dono for do negócio da sessão (`users.business_id`);
    numa `DB::transaction` revoga os `oauth_access_tokens` ativos do client, revoga os
    `oauth_refresh_tokens` desses tokens e apaga a linha do client. Devolve a contagem em
    `status.revoked_tokens`. Client alheio ou inexistente: nada é revogado nem apagado.
  - Auditoria (`activityLog`) de criar e excluir com `client_id` + nome + contagem — **sem segredo**.
    Falha do log não desfaz a ação.
- `Modules/Connector/Http/Controllers/InstallController.php`: `index`/`uninstall`/`update` só agem
  no POST. O GET devolve uma confirmação **sem efeito** com formulário POST + CSRF.
- `Modules/Connector/Routes/web.php`: `install/uninstall` e `install/update` aceitam POST
  (`install` já aceitava).
- `ApiClientsPanelTest.php`: +4 casos (refresh revogado + contagem · client alheio não tem token
  revogado · GET de uninstall não desativa · as 3 ações aceitam POST).

## Provas do json, medidas no branch
1. `ClientController.php` contém `oauth_access_tokens` — ✅
2. UC-CONN-12 verde — **NÃO MEDI**: o arquivo faz `markTestSkipped` em SQLite e a lane do módulo
   é SQLite; o run MySQL é no CT 100, que esta sessão não toca.

## Placar
`02 [em curso]` — único motivo: "depende de 01 (não feita)". A 01 foi entregue no #8336; fica
`em curso` porque a prova "charter em `Pages/Api`" foi para a 04 por causa da IT2 (ver `_saida-01`,
errata 1). Quando o Cowork mover essa prova, a 01 e esta viram `feito`.

## Decisões técnicas (não [W]) e por quê
- **O GET não virou 405.** A tela `/manage-modules` (`ModulesController` + `install/modules/index.blade.php`,
  fora do prefixo desta thread) monta `<a href>` para estas três rotas. Com POST puro, o botão do
  Conector daria 405. Por isso o GET continua registrado, mas só mostra a confirmação; a ação
  (que no Conector roda `passport:install --force` e derruba o WR Comercial) exige POST.
- **Refresh token entra na cadeia.** O WR Comercial usa password grant; revogar só o access
  deixaria o refresh pedir outro (D2 = "revoga em cadeia").
- **Formato da API não mudou.** Nada em `Routes/api.php` nem `Http/Controllers/Api/`.

## Pendente
- Trocar os `<a href>` de `/manage-modules` por formulário POST: é núcleo (`app/` + `resources/views/install/`),
  vale para todos os módulos e não está no prefixo de nenhuma thread do Connector.
- Os outros módulos seguem com install/uninstall/update em GET (mesma base `BaseModuleInstallController`).

## PR
(preenchido no corpo do PR)
