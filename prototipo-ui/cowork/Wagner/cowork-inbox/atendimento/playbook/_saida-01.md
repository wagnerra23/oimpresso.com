---
sessao: "01"
titulo: Render órfão /atendimento/inbox — redirect pra Caixa Unificada preservando a query
autor: "[CL]"
data: "2026-10-01"
base: origin/main 0f0ff8a8e
---

# _saida-01 · Render órfão /atendimento/inbox

## Decisão aplicada
D1 respondida por [W] em 2026-10-01 (`_DECISOES-W-2026-10-01.md`): **redirecionar** `/atendimento/inbox` pra Caixa Unificada, preservando `?thread=`, `?channel_id=`, `?tab=`. O `00-INDICE.md` ainda marca D1 como `respondida: false` — não editei o índice (é do Cowork); a edição pedida está no próprio arquivo de decisões.

## Medido antes de mexer (condição da D1)
- **Page não existe.** `git ls-files` nas duas raízes que o resolver do Inertia mescla (`resources/js/app.tsx`: `./Pages/**/*.tsx` + `../../Modules/*/Resources/js/Pages/**/*.tsx`): 41 arquivos sob `Pages/Atendimento/`, nenhum `Inbox/Index.tsx`.
- **A rota viva não chamava o `index()`.** Desde o cutover de 2026-05-15 a rota era `Route::redirect('/inbox', '/atendimento/caixa-unificada', 301)`. O `index()` (que renderizava a Page inexistente) estava fora do HTTP, vivo só em testes que o chamavam direto.
- **A query se perdia.** O comentário da rota dizia "Query string preserved automaticamente pelo Laravel Route::redirect". Lido em `vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php`: a URL de destino é montada com `toRoute($route, $parameters)`, e `$parameters` só traz variáveis de PATH. Ou seja, `/atendimento/inbox?thread=5` caía na Caixa sem a conversa. Consumidor afetado: `Csat/Index.tsx:277` (`href=/atendimento/inbox?thread=…`).
- `curl` sem sessão em prod devolve `302 → /login` (o middleware `auth` responde antes do redirect), então o comportamento autenticado **não foi medido em prod**; a conclusão sobre a query vem da leitura do `RedirectController`.

## Entregue
- `InboxController::index` → `redirect()->route('atendimento.caixa-unificada.index', $query, 301)`, com a query inteira. `channel_id` é mantido e também copiado pra `account_id`, que é o nome que a Caixa lê pro mesmo filtro (com a mesma checagem de ACL de canal). `account_id` explícito vence.
- Rota: `Route::redirect` → `Route::get('/inbox', [InboxController::class, 'index'])`, mesmo nome `atendimento.inbox.index`. Errata do comentário falso, com data.
- `send`, `updateTags`, `blockContact` e demais endpoints **intocados**.
- Imports órfãos removidos (`Inertia`, `Response`, `CentrifugoTokenIssuer`).
- `Modules/Whatsapp/Tests/Feature/InboxRedirectPreservaQueryTest.php` (5 casos, sem DB) + registro em `.github/ci-sqlite-pest.list`. O caso 001 lê a tabela de rotas: com o `Route::redirect` antigo, a ação seria o `RedirectController` e ele falharia.
- `tests/Feature/Architecture/OrphanRenderGateTest.php`: entrada `Atendimento/Inbox/Index` sai da allowlist (a allowlist só encolhe; agora está vazia).

## Fora do prefixo, e por quê
- `InboxQueueDerivationTest.php` (roda na lane sqlite) chamava `InboxController::index` e lia as props da listagem. Com o `index` virando redirect ele quebraria. Passou a exercitar `CaixaUnificadaController::index`, que é dono da mesma regra tag → fila na tela viva (mesma assinatura). Prova no CI do PR.
- Os 3 arquivos de teste citados acima também estão fora do `prefixo` da thread; sem eles o PR deixaria um required vermelho ou um gate com entrada morta.

## Provas do json
| prova | resultado |
|---|---|
| `nao_contem` `Inertia::render('Atendimento/Inbox/Index'` em `InboxController.php` | não contém (`git grep` rc=1 no padrão do render) |

## Pendente (não feito de propósito)
1. **Código morto no `InboxController`**: os helpers que só o `index()` usava (`buildConversationsPayload`, `buildStatsPayload`, `buildAvailableChannelsPayload`, `buildAvailableTagsPayload` etc.) ficaram. Remover é outro intent; precisa varrer quem mais os chama.
2. **4 testes que chamam `InboxController::index` direto e não rodam em lane nenhuma** (`CanalFilaIsolationTest`, `InboxFiltersTest`, `InboxMultiPhoneFilterTest`, `MediaInboundProcessedTest`): já eram verdes por não-execução; agora quebrariam se alguém os ligasse. Eles testam parâmetros do Inbox legado (`within_24h`, `orderBy`, `channel_id`) que a Caixa nomeia diferente — migrar é reescrever, não trocar a classe.
3. **Índice do Cowork**: D1 segue `respondida: false` no json até o Cowork aplicar `_DECISOES-W-2026-10-01.md`.

## Errata do índice
- O índice diz "Não verifiquei se o resolver de Page mapeia `Inbox` → `CaixaUnificada`". Verificado: não mapeia (não há alias no `resolve` do `app.tsx`). E a rota já não passava pelo `index()` — o render órfão era código morto, e o defeito vivo era a query perdida no `Route::redirect`.

## PR
(preenchido no corpo do PR)
