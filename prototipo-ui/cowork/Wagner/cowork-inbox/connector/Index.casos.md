---
id: modules-connector-resources-js-pages-api-index-casos
charter: Index.charter.md
page: /connector/api
status: draft
last_validated: "2026-08-19"
---

# Casos de uso — /connector/api (Conector · API)

Formato Dado/Quando/Então com critério de aceite. Rastreabilidade: cada caso aponta a regra (R) ou o achado (A) do charter. ❌ = o caso **nasce vermelho** contra o `main` de hoje (é o conserto pedido, não regressão do F1).

## Lista e leitura

**UC-CONN-01 — a lista é do meu negócio** (R1)
Dado que existem clients OAuth de dois negócios no banco;
Quando o superadmin do negócio 164 abre `/connector/api`;
Então vê só os clients cujo `user.business_id` é 164, e só os de `password_client=1`.
*Aceite:* nenhum `client_id` de outro negócio no payload da Inertia.

**UC-CONN-02 ❌ — segredo não é exibível** (R2, A1 · [W] D6)
Dado um client existente;
Quando abro a lista, o kebab ou qualquer rota do painel;
Então não há caminho nenhum que devolva o segredo — a coluna mostra "guardado como hash · não é exibível";
E o payload da Inertia não contém o campo `secret`.
*Aceite:* nasce vermelho (hoje o controller chama `makeVisible('secret')`). O valor **permanece guardado e válido** no banco — fechar a leitura não pode invalidar credencial instalada.

**UC-CONN-03 — tokens ativos por client** (R1)
Dado um client com 11 tokens não revogados e tocados nas últimas 24 h;
Quando a lista carrega;
Então a coluna mostra `11` em algarismos tabulares; com zero, mostra travessão.
*Aceite:* a contagem exclui revogado e vencido.

## Criar

**UC-CONN-04 — nome é obrigatório** (R3)
Dado o formulário de criação aberto;
Quando o nome está vazio;
Então Salvar fica desabilitado e a mensagem é "O nome do client OAuth é obrigatório.".
*Aceite:* mesma frase do servidor (`StoreOauthClientRequest::messages`), sem inglês.

**UC-CONN-05 — nome tem teto** (R3)
Dado um nome com 192 caracteres;
Quando tento salvar;
Então a recusa é "O nome do client não pode ultrapassar 191 caracteres." e nada é gravado.
*Aceite:* recusa no servidor mesmo se a UI for burlada.

**UC-CONN-06 — nome repetido avisa e não bloqueia** (R3)
Dado que já existe "WR Comercial — balcão ROTA LIVRE";
Quando digito o mesmo nome;
Então a tela avisa que vai ficar duplicado e explica o custo ("depois ninguém sabe qual revogar"), sem impedir.
*Aceite:* o aviso é texto, não erro de validação.

**UC-CONN-07 — credencial aparece uma vez, pra copiar** (R5)
Dado que salvo o client;
Então o painel mostra `client_id` e `client_secret` com botão de copiar e a instrução de guardar;
E o client entra na lista com segredo oculto.
*Aceite:* segredo de 40 caracteres devolvido **uma única vez** na resposta do POST; `redirect` = `http://localhost`; `password_client=1`; `personal_access_client=0`; `revoked=false`; `user_id` = quem está logado. A tela avisa que não haverá segunda chance.

**UC-CONN-08 — criar é de superadmin** (R4)
Dado um usuário sem `superadmin`;
Quando ele faz POST em `/connector/client`;
Então a resposta é 403 antes do controller executar.
*Aceite:* a recusa vem do `authorize()` do FormRequest, não de checagem na view.

**UC-CONN-09 — não se delega por permissão** (R10, A2 · [W] D1)
Dado um usuário com a permissão `connector.access` e **sem** `superadmin`;
Quando abre `/connector/api` ou tenta criar client;
Então recebe 403 — emitir credencial de API é de superadmin;
E a permissão `connector.access` não existe mais no catálogo do módulo.
*Aceite:* caso **negativo** (403 é o correto); `DataController::user_permissions` não declara mais a chave.

## Excluir e revogar

**UC-CONN-10 — excluir pede confirmação com consequência** (R6, A5)
Dado um client com 11 tokens ativos;
Quando aciono Excluir;
Então a confirmação nomeia o client, diz que ninguém mais pede token novo com ele e que os 11 tokens já emitidos valem até vencer.
*Aceite:* a contagem é a real do client, não texto fixo.

**UC-CONN-11 — excluir é do meu negócio** (R1, R6)
Dado um client do negócio 200;
Quando o superadmin do 164 tenta excluí-lo pelo id;
Então nada é apagado.
*Aceite:* a consulta do `destroy` filtra por `business_id`.

**UC-CONN-12 ❌ — excluir revoga em cadeia** (A3)
Dado um client com tokens ativos;
Quando ele é excluído;
Então os `oauth_access_tokens` daquele client ficam `revoked=1`;
E a confirmação avisa quantos acessos vão cair.
*Aceite:* nasce vermelho contra o `main` de hoje; a revogação em cadeia é ratificada por [W] (D2) e roda na mesma transação.

## Chaves da plataforma

**UC-CONN-13 — a tela não regenera chaves** (R7, A4 · [W] D4)
Dado o painel do Conector;
Quando procuro por regenerar chaves ou "regenerar documentação";
Então não existe ação disso na interface;
E a aba Módulo diz, em texto, que regenerar é operação de servidor e derruba todo app externo.
*Aceite:* nenhum botão nem item de kebab; nenhuma rota de UI chama `passport:install`.

**UC-CONN-14 ❌ — a rota de regenerar deixa de existir** (A7 · [W] D4)
Dado que a ação saiu da tela;
Quando faço GET ou POST em `/connector/regenerate`;
Então a resposta é 404 em qualquer verbo.
*Aceite:* nasce vermelho (hoje `Route::get` responde 302); `ClientController::regenerate` é removido junto.

**UC-CONN-15 ❌ — o primário do menu abre alguma coisa** (A6)
Dado o item primário "Novo API client" do menu do módulo;
Quando abro `/connector/client/create`;
Então recebo a tela de criação (ou 404 declarado), nunca 500.
*Aceite:* nasce vermelho — `create()` devolve `connector::create`, view que não existe.

## Módulo, ambiente e API

**UC-CONN-16 — demo recusa e explica** (R8)
Dado `APP_ENV=demo`;
Quando abro a tela;
Então nenhum client é listado e a tela diz que a função está desligada na demonstração.
*Aceite:* nenhum segredo no payload.

**UC-CONN-17 — instalar avisa do passo do Passport** (R11)
Dado o módulo não instalado;
Quando aciono Instalar;
Então a confirmação diz que roda as 2 migrações e depois gera as chaves de OAuth, e que apps com credencial antiga param.
*Aceite:* a ação só executa depois do confirmar.

**UC-CONN-18 — menu depende de instalação ou pacote** (R9)
Dado um negócio sem `connector_module` no pacote;
Quando o usuário não-superadmin navega;
Então o grupo Conector não aparece na navegação.
*Aceite:* mesma condição do `modifyAdminMenu`.

**UC-CONN-19 — catálogo bate com as rotas** (R12)
Dado o catálogo da aba Documentação;
Quando comparo com as rotas registradas com prefixo `connector/api`;
Então todo item do catálogo existe nas rotas e a contagem exibida é a contagem real.
*Aceite:* teste percorre `app('router')->getRoutes()`; divergência reprova.

**UC-CONN-20 — saúde mostra limiar e origem** (R12)
Dado o último registro do `connector:health`;
Quando abro a aba Saúde;
Então cada check mostra valor, limiar (`≥1`, `≥1`, `≥20`), de onde vem o número e o que significa ficar abaixo;
E a tela declara que não executa o comando.
*Aceite:* rotas abaixo de 20 pinta o cartão como fora do limiar.

**UC-CONN-23 — perder o segredo tem caminho** ([W] D6)
Dado que o app perdeu a credencial e o segredo não pode ser lido;
Quando abro o kebab do client;
Então a ação oferecida é "Emitir credencial nova", com a ordem certa: emitir → configurar no app → excluir a antiga.
*Aceite:* não existe "revelar"; a orientação de ordem aparece antes de qualquer exclusão.

**UC-CONN-24 — credencial instalada nunca para de autenticar** ([W] 2026-08-19)
Dado um client emitido antes de qualquer onda deste módulo, em uso pelo Delphi;
Quando as ondas de segurança são aplicadas (leitura fechada, auditoria, rotas em POST);
Então o mesmo `client_id` + `client_secret` continua obtendo token em `POST /oauth/token`;
E nenhuma migração altera a coluna `secret`.
*Aceite:* teste de regressão pega o segredo de um client pré-existente e autentica **depois** das mudanças; `Passport::hashClientSecrets()` não é chamado em nenhum provider.

## Estados de tela

**UC-CONN-21 — primeira vez explica o que é a credencial**
Dado um negócio sem nenhum client emitido;
Quando abro o painel;
Então em vez de tabela vazia vejo o motivo (o que é um API client, com exemplos reais: WR Comercial no balcão, aplicativo do técnico) e a ação "Criar o primeiro API client".
*Aceite:* `EmptyState variant="first"` do DS; a ação abre o mesmo formulário do botão da toolbar.

**UC-CONN-22 — demonstração recusa na tela, não só no servidor** (R8)
Dado `APP_ENV=demo`;
Quando abro o painel;
Então o aviso diz que credenciais estão desligadas nesta base, os KPIs zeram, o botão de criar fica indisponível e o atalho `n` não abre nada;
E nenhum segredo aparece.
*Aceite:* `EmptyState variant="no-perm"`; o payload da Inertia não traz `secret`.

## Rastreabilidade

| Caso | Regra/Achado | Nasce vermelho |
|---|---|---|
| 01, 03, 11 | R1 | — |
| 02 | R2 · A1 | — |
| 04, 05, 06, 07 | R3 · R5 | — |
| 08 | R4 | — |
| 09 | R10 · A2 | — (caso negativo) |
| 10 | R6 · A5 | — |
| 12 | A3 | ❌ |
| 13 | R7 · A4 | — |
| — | D3 (sem rotação de segredo) | non-goal: nenhum caso |
| 14 | A7 | ❌ |
| 15 | A6 | ❌ |
| 16 | R8 | — |
| 17 | R11 | — |
| 18 | R9 | — |
| 19, 20 | R12 | — |
| 21 | estado de tela (primeira vez) | — |
| 23 | D6 (segredo não se lê) | — |
| 24 | restrição dura (Delphi em campo) | — |
| 02 | R2 · A1 · D6 | ❌ |
| 22 | R8 · estado de demonstração | — |
