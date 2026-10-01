---
id: modules-connector-resources-js-pages-api-index-casos
casos: Connector · API clients · /connector/client
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a tela que emite e corta o acesso dos apps externos (WR Comercial em campo). Segredo vazado na lista ou credencial de outro negócio visível é incidente; credencial instalada que para de autenticar quebra cliente que não pode ser atualizado.
owner: wagner
last_run: "2026-10-01"
---

# Casos de Uso & Aceite — Connector · API clients (`/connector/client`)

> **Âncora:** [charter](./Index.charter.md) (regras R1–R12, achados A1–A7, decisões [W] D1–D7 de
> 2026-08-19). Derivados da cópia do Cowork (`cowork-inbox/connector/Index.casos.md`), não do
> `Index.tsx`. Erratas aplicadas na thread 04: a rota da lista é `/connector/client`; UCs em
> `## UC-`. Todos `🧪` até o run MySQL — o teste faz `markTestSkipped` em SQLite.
> Teste: `Modules/Connector/Tests/Feature/ApiClientsPanelTest.php`.

## UC-CONN-01 · A lista é do meu negócio · `must` `[T0]`

**Dado** clients OAuth de dois negócios **Quando** o superadmin abre `/connector/client` **Então**
vê só os clients cujo `user.business_id` é o da sessão, e só `password_client=1`.

Status: 🧪

## UC-CONN-02 · O segredo não é exibível · `must` `[T0]`

**Dado** um client existente **Quando** abro a lista ou o detalhe **Então** nenhum caminho devolve
o segredo — a coluna mostra "guardado · não é exibível" e o payload não tem `secret`. O valor
segue guardado e válido no banco ([W] D6).

Status: 🧪

## UC-CONN-03 · Tokens ativos por client · `should`

**Dado** um client com tokens **Quando** a lista carrega **Então** `active_tokens_24h` conta só os
não revogados, não vencidos e tocados nas últimas 24 h; zero aparece como travessão.

Status: 🧪

## UC-CONN-04 · Nome é obrigatório · `must`

**Dado** o formulário de criação **Quando** o nome está vazio **Então** a recusa é "O nome do
client OAuth é obrigatório." — mesma frase do `StoreOauthClientRequest`. O teto de 191
caracteres segue a mesma regra no servidor.

Status: 🧪

## UC-CONN-07 · A credencial aparece uma vez, pra copiar · `must`

**Dado** que salvo o client **Então** ele nasce com segredo de 40, `redirect=http://localhost`,
`password_client=1`, `personal_access_client=0`, `revoked=false` e `user_id` de quem está logado;
o segredo volta **uma vez**, num bloco copiável que fica até o usuário fechar (não em toast).

Status: 🧪

## UC-CONN-08 · Criar é de superadmin · `must` `[T0]`

**Dado** um usuário sem `superadmin` **Quando** faz POST em `/connector/client` **Então** recebe 403
do `authorize()` do FormRequest.

Status: 🧪

## UC-CONN-09 · Não se delega por permissão · `must`

**Dado** um usuário com `connector.access` e sem `superadmin` **Quando** abre a lista **Então**
recebe 403. Comportamento **vigente**, não mais proibição: a D1 ([W] 2026-08-19) foi revogada
por [W] 2026-10-01 (2ª rodada) — delegar a funcionário do negócio operador é permitido. Se a
delegação vier para este painel, este caso muda no mesmo PR.

Status: 🧪

## UC-CONN-11 · Excluir é do meu negócio · `must` `[T0]`

**Dado** um client de outro negócio **Quando** o superadmin tenta excluí-lo pelo id **Então** nada
é apagado nem revogado.

Status: 🧪

## UC-CONN-12 · Excluir revoga em cadeia · `must`

**Dado** um client com tokens ativos **Quando** é excluído **Então** os access tokens e os refresh
tokens deles ficam revogados na mesma transação, e a confirmação avisa que os acessos caem.

Status: 🧪

## UC-CONN-16 · Demonstração recusa · `must`

**Dado** `APP_ENV=demo` **Quando** abro a tela **Então** nenhum client é listado, o botão de criar
fica indisponível e nenhum segredo vai no payload.

Status: 🧪

## UC-CONN-13 · A tela não regenera chaves · `must`

**Dado** o painel **Então** não há ação de regenerar chaves; `ClientController::regenerate` sai
(thread 05).

Status: 🧪

## UC-CONN-14 · A rota de regenerar deixa de existir · `must`

**Dado** GET ou POST em `/connector/regenerate` **Então** 404 (thread 05).

Status: 🧪

## UC-CONN-15 · O primário do menu não estoura · `must`

**Dado** `/connector/client/create` **Então** nunca 500 (thread 05).

Status: 🧪

## UC-CONN-19 · Catálogo bate com as rotas · `should`

**Dado** as rotas com prefixo `connector/api` **Então** são pelo menos 20, o KPI "Endpoints
publicados" mostra a contagem real, e o catálogo da aba Documentação é esse mesmo conjunto —
toda linha existe nas rotas, nenhuma é escrita à mão (thread 04 PR-b).

Status: 🧪

## UC-CONN-25 · A aba Módulo mostra o estado medido · `should`

**Dado** o painel **Quando** abro a aba Módulo **Então** versão (`config('connector.module_version')`),
número de migrações do módulo e situação de instalação vêm do servidor, não de texto fixo; instalar,
atualizar e desinstalar levam às confirmações do `InstallController` (o GET não executa nada).

Status: 🧪

## Backlog (sem teste ainda — não são UC até ganharem um)

- [BACKLOG] Nome repetido avisa e não bloqueia ("depois ninguém sabe qual revogar") — implementado na tela, sem teste.
- [BACKLOG] Confirmação de excluir nomeia o client e diz que os acessos caem — implementada na tela, sem teste.
- [BACKLOG] Primeira vez: estado vazio explica o que é a credencial e oferece "Criar o primeiro API client" — implementado, sem teste.
- [BACKLOG] Perder o segredo tem caminho: kebab oferece "Emitir credencial nova", nunca "revelar" — implementado, sem teste.
- [BACKLOG] Credencial instalada nunca para de autenticar (`POST /oauth/token` com client pré-existente) — há teste sem id de UC.
- [BACKLOG] Aba Saúde mostra os três checks do `connector:health` com limiar e origem — hoje só o que a tela mede ao abrir (rotas, tokens do negócio); licenças em 24 h fica "não medido aqui" até o histórico da thread 08. Sem teste.
- [BACKLOG] Aba Módulo avisa do `passport:install --force` antes de instalar/atualizar — implementado na tela, sem teste.
- [BACKLOG] O menu leva à aba Documentação (`/connector/client?aba=docs`) — implementado no `DataController`, sem teste.
- [BACKLOG] Menu depende de instalação ou de `connector_module` no pacote — thread 05.
