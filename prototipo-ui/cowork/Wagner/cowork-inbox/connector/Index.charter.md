---
id: modules-connector-resources-js-pages-api-index-charter
page: /connector/api
component: Modules/Connector/Resources/js/Pages/Api/Index.tsx
related_prototype: PT-01 (índice) + PT-04 (confirmação destrutiva)
owner: wagner
status: draft
last_validated: "2026-08-19"
parent_module: Connector
related_adrs: [21, 93, 155, 180, 190, 286, 300]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/connector/connector-page.jsx"
  blueprint_screenshot_approval: "pendente [W2]"
  derived_screens: [Api/Index (clients), Api/Docs, Api/Health, Api/Module]
  divergence_from_blueprint: "nenhuma — PT-01 lista + PT-04 confirmação; sem drawer (o client tem 4 campos)"
related_us: [US-CONN-001, US-CONN-013]
---

# Page Charter — /connector/api (DRAFT)

> **Status:** draft. O F1 existe (`prototipo-ui/cowork/connector/connector-page.jsx`); a tela viva **não** — hoje `/connector/api` é Blade/AdminLTE (`connector::clients.index`) com DataTables, modal do Bootstrap e `Form::open` no excluir. Vira `live` quando [W2] aprovar o screenshot da tela Inertia em produção.
> Backend canon: `Modules\Connector\Http\Controllers\ClientController` (`index/store/destroy/regenerate`) + `Http\Requests\StoreOauthClientRequest`.
> Middleware da rota: `web · SetSessionData · auth · language · timezone · AdminSidebarMenu · throttle:60,1`. As rotas de instalação usam `throttle:30,1` e o grupo `authh`.
> Autorização vigente: `auth()->user()->can('superadmin')` inline em `index`, `destroy`, `regenerate` + `authorize()` do FormRequest.

## Mission

Uma tela para **quem entra na empresa por API**: emitir credencial pra um app externo, ver quem está usando, e cortar o acesso quando o app sai de operação — sem abrir tabela do Passport e sem derrubar a integração que está em campo.

## Goals — Features (faz)

- Lista dos clients OAuth do negócio (`password_client=1`), com nome, `client_id`, tokens ativos nas últimas 24 h e quem criou.
- Criar client com **nome obrigatório** (até 191 caracteres) e aviso quando o nome repete um existente.
- Segredo **exibido uma única vez**, na criação ([W] 2026-08-19): a lista mostra selo "guardado · não é exibível" e nenhuma tela ou rota devolve o valor depois — nem para o administrador. O valor **continua guardado e válido**: credencial em campo (Delphi/WR Comercial) não pode parar de autenticar, nunca. Perdeu? emite outra e exclui a antiga.
- Excluir client em confirmação (PT-04) que **diz a consequência**: token já emitido continua valendo até vencer.
- Aba **Documentação**: catálogo dos endpoints `connector/api/**` lido do arquivo de rotas (método · rota · controller · observação), o caminho do `POST /oauth/token` e as regras transversais (`auth:api`, `throttle:120,1`, `log.delphi`, `timezone`).
- Aba **Saúde**: os três checks do `connector:health` com limiar, origem do número e o que significa falhar.
- Aba **Módulo**: estado, versão (`config('connector.module_version')`), migrações, instalar/atualizar/desinstalar declarando o `passport:install --force` pós-migração.

## Non-Goals — Features (NÃO faz)

- ❌ Editor de escopos/permissões por token — Passport aqui é password grant sem scopes.
- ❌ Client de autorização por navegador (`redirect` real, PKCE, `personal_access_client`).
- ❌ Painel de tráfego por endpoint (latência, taxa de erro) — isso é observabilidade, não esta tela.
- ❌ Emitir token pelo painel (o token nasce no app, com usuário e senha do colaborador).
- ❌ Gerar documentação (`scribe:generate` está comentado no controller; o catálogo aqui é leitura de rotas).
- ❌ **Regenerar as chaves do Passport pela tela** ([W] 2026-08-19): é comando de operação no servidor — derruba todo app externo, em todos os negócios.
- ❌ **Rotar/alterar o segredo de um client existente** ([W] 2026-08-19): credencial comprometida é excluída e emitida de novo; o contrato do desktop em campo não se altera.
- ❌ Delegar emissão de credencial por permissão ([W] 2026-08-19): fica em `superadmin`.
- ❌ **Revelar ou copiar o segredo de um client existente** ([W] 2026-08-19): não existe caminho de leitura na tela nem na API interna; `makeVisible('secret')` sai do controller.
- ❌ **Qualquer mudança que invalide credencial já instalada** ([W] 2026-08-19, restrição dura): hash de secret, rotação forçada, expiração de client, mudança de grant. O Delphi já está nos clientes e não pode ser alterado — autenticação existente não para, nunca.
- ❌ Cadastro de licença/equipamento Delphi — é do módulo Officeimpresso, não do Conector.

## UX targets

- Cabe em 1280px sem scroll horizontal com a sidebar de 256px aberta.
- Segredo nunca aparece sem uma ação do usuário (revelar ou copiar).
- Toda ação destrutiva nomeia o alcance: um client, ou a plataforma inteira.

## Anti-hooks (NÃO faz automaticamente)

- ❌ Não grava em GET (o `regenerate` vigente **é GET** — a tradução vira POST, UC-CONN-14).
- ❌ Não revela segredo no HTML inicial da página.
- ❌ Não regenera chave nenhuma sem confirmação.
- ❌ Não expõe enum/coluna crua (`password_client`, `revoked`, `personal_access_client`) na interface.
- ❌ Não cria client em nome de outro usuário: `user_id` é sempre quem está logado.

## Regras de domínio (12)

| # | Regra | Onde vive |
|---|---|---|
| R1 | A lista mostra só clients do negócio da sessão: `oauth_clients` × `users.business_id` com `password_client=1` | `ClientController::index` |
| R2 | Segredo **nunca** entra na resposta da listagem: `makeVisible('secret')` sai do controller. O valor aparece só no retorno do POST de criação ([W] 2026-08-19). **`Passport::hashClientSecrets()` fica desligado** — hash invalidaria as credenciais já instaladas no Delphi | `ClientController::index/store` |
| R3 | Criar client exige `name` (string, até 191). Sem nome, 422 | `StoreOauthClientRequest::rules` |
| R4 | Criar é operação de superadmin — o FormRequest recusa antes do controller (403) | `StoreOauthClientRequest::authorize` |
| R5 | O client nasce com `secret` de 40 caracteres, `redirect=http://localhost`, `password_client=1`, `personal_access_client=0`, `revoked=false`; o segredo volta **uma vez** na resposta da criação | `ClientController::store` |
| R6 | Excluir apaga a linha de `oauth_clients` do negócio; **não** toca em `oauth_access_tokens` | `ClientController::destroy` |
| R7 | Regenerar chaves da plataforma **não é ação de tela** ([W] 2026-08-19): `regenerate()` e a rota GET saem do módulo | `ClientController::regenerate` (a remover) |
| R8 | Em ambiente demo (`config('app.env') == 'demo'`) a tela mostra recusa e não lista nada | `index` + `clients/index.blade.php` |
| R9 | O menu do módulo só aparece se o módulo estiver instalado (superadmin) ou se o pacote da assinatura tiver `connector_module` | `DataController::modifyAdminMenu` |
| R10 | Emitir/excluir credencial é `superadmin` ([W] 2026-08-19); a permissão órfã `connector.access` **sai** do catálogo | `DataController::user_permissions` (a remover) |
| R11 | Instalar o módulo roda as migrações e depois `passport:install --force` | `InstallController::postMigrationSteps` |
| R12 | A API externa responde sob `auth:api` + `throttle:120,1` + `log.delphi`; o contrato de resposta do Delphi é string literal (`S;…`/`N;…`) e não pode mudar | `Routes/api.php` + `DelphiSyncService` |

## Achados de auditoria (A1–A7 — entram como pedido, não como regra a preservar)

| # | Achado | Prova no código | Risco |
|---|---|---|---|
| A1 | Segredo impresso em texto puro na tabela e `makeVisible('secret')` no controller | `clients/index.blade.php` `{{$client->secret}}` + `ClientController::index` | vazamento por screenshot/ombro/log → [W] mandou fechar o caminho de leitura, sem tocar no valor guardado (D6) |
| A2 | `connector.access` declarada e nunca verificada | `DataController::user_permissions` × `ClientController` | sugere delegação que [W] decidiu não existir → remover a permissão |
| A3 | Excluir client não revoga tokens emitidos | `destroy()` sem `oauth_access_tokens` | acesso continua até o token vencer |
| A4 | Botão "regenerate_doc" executa `passport:install --force` | `regenerate()` com `scribe:generate` comentado | derruba toda integração → botão e rota removidos ([W] D4) |
| A5 | Excluir sem confirmação e sem aviso de consequência | `Form::open(... 'method' => 'delete')` direto no botão | perda de acesso por clique errado |
| A6 | `create()`/`show()`/`edit()` devolvem views inexistentes (`connector::create`, `connector::show`, `connector::edit`); o primário do menu aponta `/connector/client/create` | `ClientController` + `DataController` `primary.href` | 500 no caminho principal do menu |
| A7 | As rotas de install/uninstall/update são **GET** (o `regenerate` sai junto) | `Routes/web.php` | ação destrutiva por URL (pré-fetch, histórico, link colado) |

## Dados / props / estado

```
Api/Index (Inertia)
  clients: Array<{ id: number, name: string, secret: string|null,
                   created_at: string, user_name: string, active_tokens_24h: number }>
  can: { create: bool, delete: bool }                      // superadmin nos dois ([W] D1)
  is_demo: bool
  module: { installed: bool, version: string, migrations: number }
  endpoints_count: number                                  // derivado das rotas connector/api
  health: { tokens_active_24h: number, licencas_recent_24h: number,
            routes_registered: number, checked_at: string|null }
```

Estado local da tela: aba (`clients|docs|saude|modulo`), busca, segredo revelado por linha, modal de criação, confirmação de exclusão, confirmação de regeneração, painel de credencial recém-criada, aviso fugaz.

## Decisões [W] 2026-08-19 (fecham as pendências)

| # | Decisão | Consequência |
|---|---|---|
| D1 | **Fica em superadmin.** Emitir/excluir credencial de API não se delega. | `connector.access` sai do catálogo (`DataController::user_permissions`); UC-CONN-09 vira caso negativo — 403 é o comportamento correto. |
| D2 | **Excluir revoga em cadeia.** | `destroy` revoga os `oauth_access_tokens` do client no mesmo ato, com a contagem na confirmação (UC-CONN-12). |
| D3 | **Sem rotação de segredo.** Não existe e não pode passar a existir. | Nenhum endpoint de rotação; credencial comprometida = excluir + emitir nova. Non-goal registrado. |
| D4 | **Regenerar chaves sai da tela.** | Botão removido do F1; `ClientController::regenerate` e `Route::get('/regenerate')` removidos; a operação vive no servidor. |
| D6 | **Ninguém vê segredo — nem o administrador.** | Fecha o **caminho de leitura**, não o valor: `makeVisible('secret')` removido, nenhuma rota devolve `secret`, a lista mostra selo, o valor aparece só na resposta do POST de criação. **Sem `hashClientSecrets()`** e sem migração de coluna. |
| D7 | **Licenças/equipamentos é do Officeimpresso, com permissão de suporte** — assunto diferente da API. | A proposta sai do escopo do Conector: tela no módulo Officeimpresso, permissão própria do suporte (não superadmin). |
| D5 | **`/docs` é substituído** pela aba Documentação (catálogo lido das rotas). | Item de menu removido no `DataController::modifyAdminMenu`. |

### Pendência que resta

- **[W2]** screenshot da tela Inertia em produção (gate `golden_live`, ADR 0107) → charter vira `status: live`.

## Restrição dura — o Delphi em campo ([W] 2026-08-19)

O WR Comercial (Delphi) está instalado nos clientes e **não pode ser alterado**. Disso decorre, para qualquer onda deste módulo:

- Credencial já emitida **continua autenticando indefinidamente**. Nada de hash de `client_secret`, rotação compulsória, validade de client ou troca de grant.
- O contrato de resposta da API não muda (ADR 0021): `S;…` / `N;…`, `VersaoNova;VersaoMinObrigatoria`, JSON do registrar.
- Endurecimento de segurança aqui é **remover caminho de leitura e de escrita indevida** (quem vê, quem apaga, o que é logado) — nunca mexer no que o app em campo envia ou espera.
- Exclusão de client segue permitida e revoga em cadeia (D2): é ação deliberada do administrador sobre um app que saiu de operação, não efeito colateral de migração.

## Testes (mínimos)

`Modules/Connector/Tests/Feature/ApiClientsPanelTest.php` — 16 casos derivados de UC-CONN-01..16 (ver `Index.casos.md`). Nascem vermelhos em UC-CONN-12 (revogação em cadeia), UC-CONN-14 (rota de regenerar removida) e UC-CONN-15 (rota de criação sem view). UC-CONN-09 é caso negativo: 403 é o comportamento ratificado.
