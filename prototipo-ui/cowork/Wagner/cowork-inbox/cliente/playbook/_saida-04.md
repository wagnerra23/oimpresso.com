---
sessao: "04"
titulo: "Aposentar o dual-render /contacts → /cliente — saída da thread"
autor: "[CL]"
data: 2026-10-13
base: origin/main 07e8891354
thread: 04-dual-render.md
veredito: "entregue no código — /contacts?type=X redireciona para /cliente?type=X (lista); Show desligado por decisão [W]; smoke em produção pendente do merge."
---
# _saida-04 · /contacts → /cliente (lista)

## Caminho real da config (a ficha pediu)
- Chave: `config/mwart.php` → `cliente_index.enabled` = `env('MWART_CLIENTE_INDEX', false)`, com allowlist
  `cliente_index.business_ids` = `MWART_CLIENTE_INDEX_BIZ`.
- Estado em produção, pelo `governance/prod-flags.json` (gerado por `php artisan governance:prod-flags --write` no host
  de produção): `Cliente/Index` = `["*"]`, ligado para todas as empresas. ⚠️ O arquivo é de 2026-09-15. O desenho
  abaixo não depende dele estar fresco: o `/cliente` chama o mesmo `ContactController::index`, que segue escolhendo
  React ou Blade pela chave. Quem vê Blade hoje continua vendo Blade.

## O que mudou
O dono do redirecionamento já existia: `app/Http/Middleware/RedirectLegacyContacts.php`, dormente desde o #1289
(comentado no `app/Http/Kernel.php`, esperando o canário aprovado pelo [W]). Nada novo foi construído ao lado dele.
- **Kernel:** o middleware entra no grupo `web`.
- **Lista (D1 + D3):** `GET /contacts?type=<tipo>` → `302 /cliente?<a mesma query>`, para os 6 tipos que o `/cliente`
  aceita, inclusive `supplier`. Só quando a chave está ligada para a empresa. Fica fora: AJAX legado (DataTables JSON)
  e partial reload do Inertia, que seguem servidos em `/contacts`.
- **302, não 301:** o browser guarda o 301 sem prazo; desligar a chave não desfaria o redirect para quem já passou.
- **Show retirado (decisão [W] 2026-10-13):** o ramo do Show saiu do middleware (a 1ª versão o desligava por
  constante, e o PHPStan acusou o `&&` sempre falso; código morto não é registro de decisão). Só a lista nesta thread. Observação: o
  `ContactController@show` já manda para `/cliente?contact_id=…` quando a chave do Show está ligada.
- O menu segue apontando para `/contacts?type=all` e passa a cair em `/cliente?type=all`; o item ativo do sidebar já
  considera o segmento `cliente` (`AdminSidebarMenu.php`), então nada muda nele.

## Teste
`tests/Feature/Cliente/RedirectLegacyContactsTest.php`, reescrito e **fora da quarentena**: a versão antiga afirmava
"não é 301", o que um 403 ou um 409 também satisfazem. Agora cada caso afirma status e destino, e cada "não
redireciona" tem o controle que redireciona. Tenant 98 (ADR 0358). Casos: cliente e fornecedor redirecionam com a
query; o destino abre `Cliente/Index`; chave desligada e empresa fora da lista não redirecionam; partial reload, AJAX
legado, tipo inválido e POST não redirecionam; Show desligado com um cliente real do tenant. Lane `cliente-pest`
(descobre `tests/Feature/Cliente/`).

## Fora
- Smoke em produção: depois do merge e do deploy, logado em biz=1 (o redirect exige sessão).
- Cutover do Show: decisão [W].
- Apontar o link do menu direto para `/cliente` (economiza um salto): `AdminSidebarMenu.php`, fora do prefixo.
