---
sessao: "_saida-04"
thread: "04 · Ações de estado fora de GET (toggle-block, businessbloqueado, install/*)"
dono: "[CL]"
data: 2026-10-01
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main 0754c7213
---
# _saida-04

## Entregue
`Modules/Officeimpresso/Routes/web.php` (a ficha citava `:44`/`:49`/`:64-65`; no main lido as linhas andaram):
- `licenca_computador/{id}/toggle-block` e `licenca_computador/businessbloqueado/{id}` passaram de `Route::get`
  para **`Route::post`** (mesmos nomes de rota). GET agora responde **405**. Sem GET de confirmação, porque
  todos os chamadores foram trocados no mesmo PR.
- `install/uninstall` e `install/update` viraram `Route::match(['get','post'])`; `install` já tinha POST. O GET
  das três só mostra uma **confirmação sem efeito** com formulário POST + CSRF; a ação roda no POST. Mesmo
  padrão do Connector (#8344) e do Arquivos (#8359). Como as URLs aceitam POST, o `/manage-modules` (#8363)
  passa a montar formulário POST sozinho.

**Regra de bloqueio inalterada.** `LicencaComputadorController::toggleBlock`/`businessbloqueado` não foram
tocados: mesmas guardas (`licencas.gerenciar` / `empresa.gerenciar`), mesmo service, mesmo redirect.
Só o verbo mudou. A API do desktop (`Modules/Connector/.../Api`) não é tocada.

**Chamadores (varredura `git grep` no repo inteiro por `toggle-block|toggleBlock|business.bloqueado|businessbloqueado`
fora de `memory/`, `prototipo-ui/`, `.claude/`):** 6 sites de chamada, todos trocados —
- `Resources/views/licenca_computador/computadores.blade.php` — botão Bloqueada/Liberada da empresa + toggle por máquina → `<form method=POST>@csrf<button>`.
- `Resources/views/licenca_computador/index.blade.php` — toggle por máquina → form POST.
- `Resources/views/licenca_log/index.blade.php` — 3 variantes (desbloq. empresa / desbloq. máquina / bloq. máquina) → form POST; o `confirm()` passou do `onclick` para `onsubmit`.
- `Resources/js/Pages/Officeimpresso/Logs/_components/MaquinasTable.tsx` — `router.visit(url)` → `router.post(url, {})` (diálogo de confirmação mantido).
- Os outros hits são `toggleBlock()` do Whatsapp (outra função, sem relação) e testes.
Nenhum chamador fora do módulo Officeimpresso (Suporte/Empresas e Suporte/Visao não chamam essas rotas).

**Fora do prefixo da thread (necessário para não quebrar o botão nem a prova):**
`Http/Controllers/InstallController.php` (confirmação do GET), `MaquinasTable.tsx` (chamador React),
`Logs/Index.charter.md` (anti-hook dizia em presente que as rotas "são Route::get" — virou fato datado),
`Tests/Feature/LicencasAcessoPermissionTest.php` e `tests/Feature/Officeimpresso/OfficeimpressoRoutesTest.php`.

Testes (lane `officeimpresso-pest`, MySQL): os 4 casos existentes de toggle/businessbloqueado passaram de GET
para POST; 3 casos novos — GET nas duas rotas de bloqueio dá 405 para quem **tem** permissão (controle
positivo: o mesmo usuário por POST chega ao controller) · GET de install/uninstall/update como superadmin
devolve a confirmação com form POST e **não** altera `officeimpresso_version` · as 3 URIs de install aceitam
POST. Tenant do `seededTenant()`, nunca biz=4. Prova = CI do PR (sem Pest local, por regra).

## Prova do json
- `nao_contem` `Route::get('/licenca_computador/{id}/toggle-block'` em `Modules/Officeimpresso/Routes/web.php` — 0.
- `nao_contem` `Route::get('/licenca_computador/businessbloqueado/{id}'` em `Modules/Officeimpresso/Routes/web.php` — 0.

## Pendências / observações (não editei o `00-INDICE.md`)
- `memory/requisitos/Officeimpresso/logs-parity.md` linha D1 ainda descreve a conversão GET→POST como futura
  ("a GET só sai no F5"). Fechada aqui; o doc não foi tocado (fora do prefixo).
- `Modules/Officeimpresso/Tests/Feature/ScaffoldTest.php`, `SmokeRoutesTest.php` e o `OfficeimpressoRoutesTest`
  não estão na allowlist da lane `officeimpresso-pest` — continuam sem rodar no CI (dívida anterior a esta thread).

## PR
Branch `claude/officeimpresso-thread-04` (1 thread = 1 PR).
