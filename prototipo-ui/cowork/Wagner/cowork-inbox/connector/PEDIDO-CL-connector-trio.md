# PEDIDO PARA O CODE — Conector (API): trio, tradução Blade → Inertia, consertos e limpeza

> **De:** [CC] (F1, protótipo) · **Para:** [CL] (F3, Inertia/React real) · **Data:** 2026-08-19 · **rev. 2** (decisões [W] aplicadas)
> **F1 pronto:** `prototipo-ui/cowork/connector/` (`connector-page.jsx` · `connector-api.jsx` · `connector-page.css`)
> **Trio nesta pasta:** `Index.charter.md` · `Index.casos.md` · `connector-api.contract.json` · `ApiClientsPanelTest.php`
> **Leitura que embasa tudo** (main @`728f789b8fb1`): `Modules/Connector/Resources/views/clients/index.blade.php`, `Http/Controllers/{ClientController,ConnectorController,DataController,InstallController}.php`, `Http/Requests/StoreOauthClientRequest.php`, `Routes/{web,api}.php`, `Services/DelphiSyncService.php`, `Console/Commands/ConnectorHealthCommand.php`, `module.json`, `Resources/lang/pt/lang.php`.
> ⚠️ Nada aqui está commitado: as tools de GitHub do Cowork são read-only. Ponte = cola zero-toque ou Issue `cowork-intake`.

---

## 0. Decisões [W] — 2026-08-19, **fechadas** (não reabrir)

| # | Decisão | O que isso manda fazer |
|---|---|---|
| **D1** | **Fica em `superadmin`.** Emitir/excluir credencial de API não se delega. | Remover `connector.access` do catálogo (`DataController::user_permissions`). Nada de gate novo. |
| **D2** | **Excluir revoga em cadeia.** | `destroy` revoga os `oauth_access_tokens` do client na mesma transação; a confirmação mostra a contagem. |
| **D3** | **Sem rotação de segredo — e não pode passar a existir.** | Nenhum endpoint `rotate`, nenhum "editar segredo". Credencial comprometida = excluir + emitir nova. |
| **D4** | **Regenerar chaves sai da tela.** | Remover o botão (já saiu do F1), `ClientController::regenerate` e `Route::get('/regenerate')`. A operação vive no servidor. |
| **D6** | **Ninguém vê segredo — nem o administrador.** | Fecha o **caminho de leitura**: remover `makeVisible('secret')`, nenhuma rota devolve `secret`, valor exibido só na resposta do POST de criação, selo na lista. **NÃO ligar `Passport::hashClientSecrets()`**, não migrar a coluna. |
| **D7** | **Licenças/equipamentos é do Officeimpresso, com permissão de suporte** (assunto diferente da API). | Sai do escopo do Conector; ver `PROPOSTA-licencas-equipamentos.md`. L1 e L2 (vazamento cross-tenant e senha em claro) são conserto imediato, sem esperar tela. |
| **D5** | **`/docs` é substituído** pela aba Documentação (catálogo lido das rotas). | Remover o ghost `/docs` do `modifyAdminMenu`. |

Resta só o gate de [W2]: screenshot da tela Inertia em produção → charter vira `status: live`.

---

## 1. Ondas (ordem de aplicação)

Numeradas `CONN-O*` pra não colidir com as "Wave N" de backend do repo.

> 🔴 **Restrição dura que atravessa todas as ondas ([W] 2026-08-19):** o **WR Comercial (Delphi) já está instalado nos clientes e não pode ser alterado**. Credencial emitida **nunca** para de autenticar. Está proibido: hash de `client_secret`, rotação compulsória, validade de client, troca de grant, mudança no contrato de resposta da API. Endurecer segurança aqui = fechar caminho de **leitura** e de **escrita indevida** (quem vê, quem apaga, o que vai pro log) — nunca mexer no que o app em campo envia ou espera. Qualquer onda que exija reconfigurar cliente em campo volta pra [W] antes de rodar.
>
> **Ordem sugerida:** O1 → O2 → O2b → O3 → O4 → O5 → O7 → O8 → O9.

### CONN-O1 · Prova mínima (nasce vermelha)
- `ApiClientsPanelTest.php` desta pasta → `Modules/Connector/Tests/Feature/`.
- `Index.charter.md` → `Modules/Connector/Resources/js/Pages/Api/Index.charter.md`; `Index.casos.md` ao lado.
- `connector-api.contract.json` → `prototipo-ui/contrato/`, **advisory** no `contrato-de-tela.yml`.
- Sair: suíte roda; vermelhos nomeados = UC-CONN-12 (revogação), UC-CONN-14 (rota de regenerar), UC-CONN-15 (view inexistente) + o teste do catálogo de permissões.

### CONN-O2 · Segurança da ação (backend, sem UI)
- `destroy`: revoga `oauth_access_tokens` do client + apaga a linha, **em transação**; devolve a contagem revogada pro toast (D2).
- `install/uninstall/update` saem de GET (POST/DELETE com CSRF).
- Auditoria em criar e excluir, com `client_id` e **sem** segredo no log.
- Sair: UC-CONN-12 verde; nenhuma ação destrutiva em GET no módulo.

### CONN-O2b · Fechar a leitura do segredo ([W] D6) — **sem tocar no valor guardado**

- Remover `makeVisible('secret')` do `ClientController::index` e garantir que **nenhuma** rota do painel (lista, detalhe, export, log) devolva `secret`.
- `store()` devolve o segredo **uma única vez** na resposta da criação (flash/prop), nunca gravado em log nem re-consultável.
- Auditoria de criação/exclusão registra `client_id` e autor — **jamais** o segredo.
- ⛔ **Não fazer:** `Passport::hashClientSecrets()`, migração da coluna `secret`, rotação forçada, validade de client. Isso invalidaria o WR Comercial já instalado nos clientes — proibição de [W].
- Sair: `test_nenhuma_rota_do_painel_devolve_o_segredo` verde, `test_segredo_nao_e_hasheado_credencial_em_campo_continua_valendo` verde, `test_client_preexistente_ainda_obtem_token` verde, UC-CONN-02 e UC-CONN-24 verdes.

### CONN-O3 · Tradução da tela (Blade → Inertia)
- Criar `Modules/Connector/Resources/js/Pages/Api/{Index,Docs,Health,Module}.tsx` a partir do F1.
- `ClientController::index` devolve Inertia com as props do charter (`clients` com `active_tokens_24h`, `can:{create,delete}`, `is_demo`, `module`, `endpoints_count`, `health`).
- Segredo oculto por padrão: `makeVisible('secret')` só na rota que a ação de revelar chama — nunca no payload inicial.
- Copy PT-BR do contrato, sem inglês (`Clients`, `Client Secret`, `Create Client`, `Regenerate doc` morrem aqui).
- Confirmação de exclusão com a contagem real de tokens (PT-04).
- Sair: `contrato:check` passa (seções + copy + ordem + **proibições**); UC-CONN-01..11, 13, 16..20 verdes.

### CONN-O4 · Menu e rotas mortas
- `primary.href` do menu deixa de apontar `/connector/client/create` (view inexistente) e passa a abrir o painel com o modal (UC-CONN-15).
- Remover o ghost `/docs` e apontar pra aba Documentação (D5).
- Remover `connector.access` do `user_permissions` (D1) e revogar a chave onde já foi concedida.
- Sair: UC-CONN-15 verde; nenhum 500 no caminho principal do menu.

### CONN-O5 · **Apagar o legado** (só depois que a O3 estiver em produção)

Ordem: converter → screenshot aprovado por [W2] → **então** apagar. Não apagar antes: o Blade é o fallback enquanto a Inertia não está no ar.

| Apagar | Por quê |
|---|---|
| `Resources/views/clients/index.blade.php` | substituído pela `Api/Index.tsx` |
| `Resources/views/layouts/master.blade.php` | única razão de existir era a view acima |
| `ClientController::create/show/edit/update` | stubs que devolvem views inexistentes (`connector::create/show/edit`) |
| `ClientController::regenerate` + `Route::get('/regenerate')` | D4 |
| `ConnectorController` (classe inteira) + `Route::get('/api', ...)` apontando pra ela | devolve `connector::index`, view que não existe; a rota `/connector/api` passa a ser servida pelo `ClientController::index` |
| `Route::resource('/client', ...)` reduzida a `index`, `store`, `destroy` | os outros verbos deixam de existir |
| `DataController::user_permissions` (chave `connector.access`) | D1 |
| chaves de lang mortas nos 16 idiomas: `clients`, `client_secret`, `create_client`, `regenerate_doc` | a UI passa a ser React em PT-BR; manter só `connector`, `connector_module`, `documentation` (usadas no menu) |
| `Resources/assets/js/app.js` e `Resources/assets/sass/app.scss` | arquivos de 0 byte, nunca compilados |

- Sair: `blade-migration-census` acusa o módulo com **0 telas Blade vivas**; `php artisan route:list --path=connector` sem rota órfã; nenhum `view('connector::` no código.

### CONN-O7 · "quem está usando esta credencial" (prop nova)

- `oauth_access_tokens` tem `user_id`, `client_id` e `updated_at`: dá pra listar, por client, **quais colaboradores** têm acesso aberto e **quando foi o último uso** — sem tabela nova.
- Prop nova por client: `tokens: [{ user_name, last_used_at, expires_at }]` (top 5 + contagem do resto).
- Na tela: a linha ganha detalhe expansível (ou drawer PT-02) com essa lista; a confirmação de exclusão passa a nomear **quem** perde acesso, não só quantos.
- Por quê: é o que falta pra revogar com segurança — hoje o superadmin decide no escuro ("posso excluir? quem cai?"). Fecha o gancho do UC-CONN-10.
- Sair: caso novo UC-CONN-21 (escrever junto: a lista é do client e do negócio, nunca cross-tenant) verde.

### CONN-O8 · saúde com histórico (fonte de dado)

- Hoje o `connector:health` só escreve no log em texto; a aba Saúde mostra o último valor e declara isso.
- Publicar o resultado em `memory/governance/connector-health.json` (padrão dos outros `governance/*.json`): uma entrada por execução com `date`, os três valores, `issues[]` e a taxa de desvio do `DelphiSync`.
- Na tela: série de 14 dias por check (`Chart` do DS) + "última execução" + lista dos desvios do dia (HD não cadastrado, CNPJ órfão, formato desconhecido).
- Por quê: sem isso ninguém vê tendência — e é justo a tendência que mostra cliente legado mandando formato novo sem avisar.
- Sair: a aba deixa de dizer "vem do último registro" e passa a citar a data da execução; UC-CONN-20 ganha o caso da série.

### CONN-O9 · DS vivo
- Trocar as peças que sobraram do shell por `DataTablePro`, `TabBar`, `PageHeader`, `Modal`, `Input` do DS.
- Sair: `ds:report` sem violação nova.

---

---

## 1.b Fora do Conector — proposta separada

`PROPOSTA-licencas-equipamentos.md` nesta pasta: não existe tela pra consultar licença/equipamento do WR Comercial em módulo nenhum, e a leitura do `LicencaComputadorController` achou **dois problemas de segurança que não deveriam esperar pela tela** — `index()` devolve `Licenca_Computador::all()` sob `auth:api` (vazamento cross-tenant, Tier 0/ADR 0093) e `senha`/`contra_senha` do cliente são gravadas em claro. O modelo é do Officeimpresso: precisa decisão de escopo de [W] antes de eu abrir a tela.

---

## 2. Pedidos de DS

1. **Campo de segredo** — somente-leitura com máscara + revelar + copiar num primitivo só (montei com `code` + dois botões `cnx-mini`).
2. **`StatusBadge kind="integracao"`** — `dentro-do-limiar` · `abaixo-do-limiar` · `instalado` · `nao-instalado` (usei `mod-badge` do shell).
3. **`Input`** — falta `ref`/seleção; o campo de nome ficou nativo (mesmo pedido já registrado no módulo de notificações).

---

## 3. O que NÃO mexer

- Contratos de resposta do Delphi (`S;…`, `N;…`, `VersaoNova;VersaoMinObrigatoria`, JSON do registrar) — ADR 0021, cliente em campo parseia literal.
- `throttle:120,1`, `auth:api`, `log.delphi`, `timezone` no grupo da API.
- `password_client=1` / `personal_access_client=0` / `redirect=http://localhost` na criação.
- O passo `passport:install --force` do `InstallController` (é o que cria as chaves) — só ganha aviso na UI.
- Segredo de client existente: **não se altera** (D3) e **não se invalida** — nem por hash, nem por migração, nem por rotação ([W] 2026-08-19).
- Nada que obrigue a reconfigurar o WR Comercial instalado no cliente. Se uma onda parecer exigir isso, ela volta pra [W] antes de rodar.

---

## 4. Checklist pós-merge

- [ ] `ApiClientsPanelTest` 100% verde.
- [ ] `contrato:check` do `connector-api.contract.json` promovido de advisory a required.
- [ ] `connector:health --detail` ainda ≥ 20 rotas.
- [ ] `php artisan route:list --path=connector | grep GET` sem ação destrutiva.
- [ ] `grep -r "connector::" app/ Modules/` sem resultado (view Blade toda apagada).
- [ ] `grep -r "connector.access" Modules/` sem resultado.
- [ ] Nenhum `client_secret` no HTML inicial da tela **nem em qualquer rota do painel**.
- [ ] `grep -r "makeVisible('secret')" Modules/` sem resultado.
- [ ] `grep -rn "hashClientSecrets" Modules/ app/` sem resultado.
- [ ] Um client emitido **antes** das ondas ainda obtém token em `POST /oauth/token` (regressão do Delphi em campo).
- [ ] Screenshot aprovado por [W2] → charter `status: live`.
