# Jana — ERRATA da rodada 1: a camada esquecida

**Leitura no `main` HOJE (2026-08-27, árvore `b5cef0050046`)** de: `resources/js/Pages/Jana/**` (24 arquivos), `Modules/Jana/Http/routes.php`, `Modules/Jana/Resources/permissions.php`, `prototipo-ui/contrato/jana-painel.contract.json`, `Modules/Jana/Http/Controllers/*`, workflows e testes.

**Veredito: a rodada 1 não pode ir pro Code como está.** O que faltou não é detalhe de desenho — é a **camada de lei e contrato que já existe no git**: permissão, rota real, charter/casos ratificados, contrato de tela ativo no CI e gates nomeados. O pacote anterior tratava a tela como se fosse desenho novo em terreno vazio. Não é: é tela viva sob CI, com copy ratificada por [W].

Esta é a mesma falha que [W] atribuiu ao Code ("não entende as instruções"): **o pedido mandava construir sem dizer sob quais leis** — e sem isso o executor inventa. A causa estava no pedido, não no executor.

---

## 1. A rota não é `/jana`. É `/ia`. (o pedido inteiro apontava pro lugar errado)

`Modules/Jana/Http/routes.php`: grupo `/ia`, nomes `jana.index`, `jana.chat.index`, `jana.pro.index`, `jana.memoria.index`. Eu pedi ao Code "o nome dos props que a rota `/jana` injeta hoje" — pergunta sobre uma rota inexistente. Um executor obediente teria criado a rota.

Middleware do grupo, literal:

```php
'middleware' => ['web','SetSessionData','auth','language','timezone','AdminSidebarMenu','CheckUserLogin','throttle:120,1','can:jana.access'],
```

E há controllers de verdade — `IndexController@index`, `ChatController@index`, `ProController@index` —, não closures. O contrato de dados da §3 do pacote vale, mas o destino é `IndexController::index()`, e o comentário do `routes.php` registra que `can:jana.access` "existia e não valia nada" até 2026-07-27: **é regressão fácil de reintroduzir num rewrite**.

## 2. A camada esquecida, nomeada: **permissão / entitlement**

`permissions.php` declara **24 permissões** no grupo "Copiloto" com `risk` e `requires`. O pacote da rodada 1 **não citou uma**. Consequência real de reescrever 4 telas sem essa camada: ou a tela vaza o que era gated, ou trava quem tinha acesso — e nenhum gate visual (botão escondido) substitui o `can:` do servidor.

Mínimo que cada tela precisa declarar:

| Tela | Gate | Efeito na UI |
| --- | --- | --- |
| Index (Painel) | `jana.access` (grupo) | sem ela a rota nem abre |
| Metas (seção do Painel) | `jana.metas.manage` (`medium`) | sem ela: leitura, **sem** criar/editar/reapurar/fonte — o `JanaMetaDrawer` abre em modo leitura, não desaparece |
| Chat | `jana.chat` | sem ela: Painel sem CTA "Conversar com a Jana" e sem `/ia/conversa` |
| Pro | ADR 0140 (produto comercial) + `can:jana.superadmin` no preview admin (`JanaProController`) | são **duas** telas: a do cliente e o preview admin. O pacote tratava como uma |
| Memória | `jana.mcp.memory.manage` é `critical` (soft-delete LGPD) | apagar fato não é botão comum |
| Custos/admin | `jana.admin.custos.view` (`high`) | não vaza no Painel do cliente |

## 3. Memória e Fontes **não são do módulo Jana** no backend

```php
Route::get('/memoria',  [\Modules\KB\Http\Controllers\MemoriaController::class, 'index']);
Route::get('/metas/{id}/fonte', [\Modules\KB\Http\Controllers\FontesController::class, 'show']);
```

Dono é **`Modules\KB`**. Meu mapa punha `Memoria.tsx` como Jana puro — quem for mexer em props/estados de Memória mexe no KB, com a fronteira do ADR 0366 (`jana ↔ forja ↔ governance/KB`) valendo. Isso muda de quem é o PR-4.

## 4. Charter e casos são **lei**, e são 154 KiB — "reescrever junto" era ordem sem tamanho

> **CORRIGIDO 2026-08-27** pela conferência do [CL]: eu escrevi **172 KB** e o número é **158.159 B = 154,5 KiB**. Erro meu de soma, não de leitura. A conclusão não muda; o número, sim.

| Arquivo | Tamanho |
| --- | --- |
| `Chat.casos.md` | 48,4 KB |
| `Index.casos.md` | 37,2 KB |
| `Index.charter.md` | 25,6 KB (v6) |
| `Chat.charter.md` | 12,0 KB |
| `Memoria.casos.md` | 12,2 KB |
| `Pro.charter.md` · `Pro.casos.md` · `Memoria.charter.md` | 9,3 · 8,3 · 5,2 KB |

São UCs numerados e citados de fora (`UC-JPAIN-10` aparece no contrato de tela; os scorecards `memory/governance/scorecards/screens/jana-*.yaml` pontuam contra eles). **Reescrever = apagar lei e quebrar referência cruzada.** Recomendo trocar a ordem de [W] "reescrever junto" por: **emendar** o charter (seções que mudam, com data e motivo) e **acrescentar** casos novos — nunca substituir o arquivo. Se [W] mantiver "reescrever", é decisão dele por escrito, ciente de que os scorecards zeram.

## 5. O contrato de tela **já existe** — e o PR-5 mandava criar

`prototipo-ui/contrato/jana-painel.contract.json` (7,5 KB) está **ativo no CI** (`contrato-de-tela.yml`), com **5 seções / 7 strings** de copy pinada (eu havia escrito "5 copies" — são 5 seções e 7 strings; correção do [CL]), `ordem` derivada do layout e três `_pendente_w` abertos:

1. título da tela — "Jana — Dashboard" no shell vs aba "Painel" (2 lugares sobrando);
2. botão "Exportar relatório (em breve)" — some, `disabled` com motivo, ou entrega?
3. brief on/off + hora, áudio/TTS e retenção existem no `jana-merge.jsx` §JmConfigDrawer e **nenhum é honrado pelo back** — é produto, não wiring.

O contrato diz na cara: **"a COPY de cada seção é lei [W]"**. Meu PR-5 ("criar os 4 `contrato/jana-*.contract.json`") mandaria o Code sobrescrever copy ratificada. Correto: **estender** este arquivo (e criar só os 3 que faltam: chat, memória, pro) e **não tocar** nas 5 copies sem despacho de [W].

Além disso o contrato registra a ressalva viva: o `jana-merge.jsx` cita **6 `Analise*Service` que não existem no repo** — "as regras VISUAIS dele valem, o que ele diz sobre FONTE DE DADO não". Meu pacote disse "o protótipo Cowork manda" sem essa ressalva; é ela que impede o Code de sair criando 6 services fantasmas.

## 6. Meu "fonte da verdade" estava defasado — **mas eu medi errado** (corrigido 2026-08-27)

> **Errata da errata.** Eu comparei **caracteres** (o que o leitor local devolve) com **bytes** (o que o git relata) — em arquivo PT-BR cheio de acento, isso infla a diferença. Números reais, medidos em UTF-8:

| Arquivo | `main` | local Cowork | delta |
| --- | --- | --- | --- |
| `cowork/jana-merge.jsx` | 58.381 B | **57.185 B** | −1.196 B |
| `cowork/chat-jana.jsx` | 34.835 B | **33.321 B** | −1.514 B |
| `cowork/jana-pro.jsx` | 8.222 B | 8.222 B | **idênticos** |
| os 3 `.css` | — | — | **idênticos** |

O que sobrevive do achado: **há drift, e é só nos dois `.jsx`** — 2,7 KB que o `main` tem e a cópia deste projeto Cowork não. É o cache velho previsto no `CLAUDE.md`, não um espelho abandonado. E a worktree do [CL] está **em sinc** com o `main` (ele mediu 58.381 nos dois), então o atraso é **do Cowork pro git**, não do git pra ele.

O que cai: a acusação de L-42 e a tabela com 56.535 B. `prototipo-ui/cowork/jana/` **nunca esteve no git** — era pasta que eu criei neste projeto ontem e já apaguei; não há nada pra reverter no `main`.

Caminho do conserto (aceito a recomendação do [CL]): **`--export-from`**, nunca cópia à mão, e nunca editar o espelho — ele é build-only, o durável nasce no Cowork vivo e desce.

## 7. Gates: existem por nome, e dois testes quebram no rewrite

O pacote dizia "lint · typecheck · visreg". O real:

- **6 workflows**: `jana-pest.yml`, `jana-conversas-gate.yml`, `jana-logica-pura-pest.yml`, `jana-ragas-gate.yml`, `jana-ragas-canary.yml`, `jana-recall-eval.yml`.
- **2 testes de front que uma reescrita quebra**: `tests/jana-chat-conversas.test.tsx`, `tests/jana-pro-voltar.test.tsx` (o segundo trava o "voltar" do Pro — comportamento, não estilo).
- **4 scorecards** `jana-{index,chat,memoria,pro}.yaml` + `memory/scorecards/jana.yaml` (16 KB).
- **Visreg tem seeder próprio**: `database/seeders/VisregJanaChatSeeder.php`.
- **Âncora é máquina**: `node prototipo-ui/ancora.mjs Jana/Index` resolve o ponteiro protótipo↔tela — não se decide por nota.
- **3 skills do Code**: `.claude/skills/jana-arch`, `jana-brief-concierge`, `jana-recall-flow`. Elas mandam no Code; um pedido que as ignora compete com elas.

## 8. Cinco docs de pedido do Jana já estão no git

`prototipo-ui/design-docs/cowork-inbox/`: `JANA-MODULO-ONDAS-PR-2026-08-09.md` (25,4 KB), `JANA-CICLO-COMPLETO-PRODUCAO-2026-08-13.md` (21,7 KB), `JANA-PAINEL-DARK-PARIDADE-2026-08-12.md`, `JANA-FASE2`, `JANA-FUSAO`. Meu plano de 5 PRs reinventou plano existente. A rodada 2 precisa **partir do plano de ondas** e dizer o que ficou pendente dele — não abrir numeração nova.

## 9. "Copiloto" não existe mais — e está vivo na UI (achado de [W], 2026-08-27)

O nome é **Jana**. `Copiloto` foi renomeado por **ADR 0088** (módulo) e **ADR 0092** (tabela), e a skill `migrar-modulo` registra por escrito que o rename foi **PHP-only de propósito**: *"blast radius de mudar fachada de uma vez é alto demais (…) 30 `Inertia::render('Copiloto/...')`)"* — cada dimensão da fachada ficaria pra "PR-3+ posterior, com ADR sub-decisão". **Esses PRs não vieram.** O legado sobrou em três camadas, e uma delas é cliente-facing:

**(a) Permissão — a camada que eu esqueci é justamente a que carrega o nome morto.** `Modules/Jana/Resources/permissions.php`: cabeçalho `Permission Registry — Copiloto`, `'group' => 'Copiloto'`, `'icon' => 'compass'`, e **as 24 labels começam com "Copiloto:"** — `Copiloto: acessar módulo`, `Copiloto: usar chat IA`, `Copiloto: gerenciar metas e fontes`, `Copiloto: superadmin de plataforma`… Essas labels **são** o que a tela de permissões mostra a quem monta um perfil de acesso. Ou seja: o cliente configura permissão de um módulo que, na tela dele, se chama Jana.

⚠️ **As chaves NÃO se tocam.** `jana.access`, `jana.chat`, … já foram renomeadas (`copiloto.mcp.*` → `jana.mcp.*`, #4853) e o `governance-script-tests.yml` guarda a cicatriz: um `givePermissionTo('copiloto.mcp.use')` sobrou apontando pra permissão inexistente e **quebrou todo o onboarding**. Mexer em `key` = incidente. Mexer em `group`/`label` = copy.

**(b) Copy em outra tela.** `Pages/Cliente/Index.tsx:2017` tem o botão **"Falar com Copiloto →"**, com `title="Abre o Copiloto (Jana)…"` — a UI explica o nome novo pelo nome velho. Aparece também no `Cliente/Index.charter.md` (4×) e no `_drawer/IATab.tsx` ("Copiloto de cliente"). Não é tela do Jana, mas é a mesma dívida, e **é onde o cliente lê**.

**(c) Ponteiro podre dentro do próprio arquivo que eu ia reescrever.** O cabeçalho do `Pages/Jana/Chat.tsx` declara:

```
//   tela: /copiloto          ← a rota é /ia/conversa
//   module: Copiloto         ← é Jana
```

E `Cliente/Index.charter.md:83` manda pra `/jana/chat?context=cliente:{id}` — rota que **nunca existiu** (o `.tsx` já usa `/ia/chat`). **Foi daí que saiu o `/jana` do meu pacote da rodada 1:** eu li o vocabulário podre e repeti. Um executor lendo esses cabeçalhos erra do mesmo jeito — o "Code não entende as instruções" tem essa parcela de causa no repo, não nele.

**Proposta: PR-0 "só nome", antes de qualquer desenho** (baixo risco, alto ganho de clareza):

1. `permissions.php`: `group: 'Jana'` + 24 labels `Jana: …`. **Chaves intocadas.** Conferir se algum teste/scorecard casa a string `'Copiloto'` do grupo antes de trocar.
2. `Cliente/Index.tsx` + `IATab.tsx`: "Falar com a Jana →" / "Copiloto de cliente" → "Jana neste cliente". (Copy de tela cliente-facing = decisão [W]; o contrato de `Cliente/Index` pode ter isso pinado — checar antes.)
3. Cabeçalhos e charters: `/copiloto` → `/ia`, `module: Copiloto` → `Jana`, e o `/jana/chat` do charter do Cliente → `/ia/chat`.
4. **Não** renomear as 3 skills (`jana-arch` ainda tem `name: copiloto-arch` no frontmatter — renomear frontmatter de skill quebra invocação; é PR próprio, com o dono da skill).

---

## O que eu preciso de [W] antes da rodada 2 (3 respostas)

**As duas técnicas o [CL] já respondeu, e eu aceito as duas:** (1) **emendar**, não reescrever — o casos-gate G-2 exige UC citado por teste, e a precedência é teste verde > casos > charter > SPEC; reescrever o meio com os testes de pé inverte a ordem. (3) **não puxar por cima** — o delta real é 2,7 KB nos dois `.jsx` e o caminho é `--export-from`.

Sobra **uma** pra [W], e é a de copy — os três `_pendente_w`:

1. **Charter/casos: emendar ou reescrever?** (recomendo emendar — reescrever zera 172 KB de UC e os scorecards)
2. **Os 3 `_pendente_w` do contrato** (título "Painel" vs "Dashboard"; botão "em breve"; config que o back não honra) — sem isso o Painel não fecha.
3. **Puxo o espelho do `main` sobre o build local do Cowork?** (o do git é ~5,7 KB maior; o local é cache velho de 24/08)

## O que muda no plano

PR-1 vira **"trazer o espelho e resolver a âncora"** (não faxina de pasta). A faxina de `Pages/Jana/components/` continua válida, mas depois — e `AssistantUiChat.tsx` só sai com `rg` provando zero import, porque 16 KB de chat paralelo pode ser o caminho do `jana-chat-tools-live` (handoff 2026-07-17), não lixo.
