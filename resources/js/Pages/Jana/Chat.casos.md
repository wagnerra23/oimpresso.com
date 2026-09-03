---
id: resources-js-pages-jana-chat-casos
casos: Jana Conversa · histórico · teclado · acessibilidade · /ia/conversa
irmaos: Chat.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-chat.md (runbook)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-09-03"
---

# Casos de uso — /ia/conversa (Chat da Jana)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Derivados do `Chat.charter.md` (§Goals/§Automation Anti-hooks) e do protótipo
> `prototipo-ui/cowork/jana-merge.jsx` §`JmConversa` (âncora de símbolo) — **não** do `Chat.tsx`.
> Derivar do código seria tautológico (§5 2026-06-05).
>
> **Por que este arquivo nasceu em 2026-08-17:** ele não existia. O Chat é `tier: A`, `status: live`
> e é — palavras do próprio charter — *"o único ponto de IA conversacional cliente-facing do
> oimpresso"*. Estava sem **um** UC escrito, enquanto o Painel vizinho já tinha nove. Os 4 UCs
> abaixo **não** foram inventados: eles carimbam 15 testes que **já existiam e já passavam**
> (`tests/jana-chat-conversas.test.tsx`), rodados nesta data — `15 passed`. O que faltava era o
> contrato por cima deles.

> **Revalidação 2026-08-26 — PR #6298 (DS onda 1), head `b011221e50`.** O diff é só de markup
> (`Badge` canon nas pílulas de dificuldade + `EmptyState` nos dois vazios do `ConvSidePanel`);
> as 5 strings de copy dos vazios seguem literais.
>
> **UC-01..04 e 11 (frontend):** `tests/jana-chat-conversas.test.tsx` — **18 de 18** rodado em
> 2026-08-26, e a lane `Jana · histórico de conversas` passou neste sha.
>
> **UC-05..10 (servidor):** medido em 2026-08-26 com `scripts/governance/test-lane-coverage.mjs
> --json`, os três testes que os defendem (`ChatAntiHooksTier0Test`, `ChatAntiHooksAcaoTest`,
> `ChatTokensTurnoTest`) estavam na lista de **órfãos de lane** — o `aguarda run verde` era
> inalcançável, como o comentário do `jana-pest.yml` já registrava desde 2026-08-17. Rodados à mão
> no CT 100 (`oimpresso-staging`, `main @ c01ee7615`) na mesma data: o `AcaoTest` fechou **2
> vermelhos** — UC-09 (tool sem declaração de permissão) e UC-10 (CPF em plain text no
> `startTrace`) — que são os mesmos achados datados de 2026-08-17, decisão [W] pendente; e o
> `Tier0Test` **não mediu**: os 4 casos morreram no bootstrap com `Call to a member function
> connection() on null`. Erro de instrumento não é veredito sobre o comportamento, então
> UC-05..08 seguem `🧪`, não `❌`. Nenhum dos dois vem deste PR, que só toca `.tsx`.

## UC-JCHAT-01 — O filtro filtra de verdade, e são DUAS abas de propósito
Status: 🧪 (`tests/jana-chat-conversas.test.tsx` — 4 casos sob o describe que cita este UC) — ✅ volta quando o manifesto G-7 capturar o veredito (a lane passou a emitir JUnit em 2026-08-24); enquanto nao capturar, 🧪 e o status honesto

A lista de conversas tem **duas** abas: **Todas** (tudo que não está arquivado) e **Arquivadas**.
"Todas" **esconde** a arquivada; "Arquivadas" mostra **só** ela.

⚠️ **Duas abas é DECISÃO, não lacuna.** O protótipo desenha quatro (`todas` · `minhas` ·
`compartilhadas` · `arquivadas`); o charter v3 reduziu para duas, e um dos testes crava isso pelo
nome: *"só existem 2 abas — Minhas/Compartilhadas foram removidas"*. Havia uma **fachada** — abas
que abriam um empty state "Em breve" — e ela foi removida. Outro teste guarda a remoção.
**Restaurar as 4 abas é reintroduzir a fachada**, não ganhar paridade com a âncora.

**Pronto quando:** cada aba mostra exatamente o seu conjunto, existem 2 abas, e nenhuma exibe "Em breve".

## UC-JCHAT-02 — `J`/`K` andam entre CONVERSAS, respeitando o filtro
Status: 🧪 (mesmo arquivo — 3 casos; o describe cita este UC) — ✅ volta quando o manifesto G-7 capturar o veredito (a lane passou a emitir JUnit em 2026-08-24); enquanto nao capturar, 🧪 e o status honesto

`J` desce e `K` sobe **na lista de conversas** — não entre mensagens da thread. O charter registra a
correção de rota da v3 (*"era 'entre mensagens'"*) e o motivo, que é de negócio e não de estilo:
*"Larissa/Wagner trabalham no teclado"* — trocar de conversa é o que se faz o dia todo.

A navegação **respeita o filtro ativo**: na aba Arquivadas, `J`/`K` não pulam para uma conversa ativa.

**Pronto quando:** `J`/`K` percorrem a ordem visual da lista e nunca saem do conjunto filtrado.

## UC-JCHAT-03 — Os atalhos não sequestram o teclado de quem está digitando
Status: 🧪 (mesmo arquivo — 3 casos sob o UC-02 + 4 sob o UC-03) — ✅ volta quando o manifesto G-7 capturar o veredito (a lane passou a emitir JUnit em 2026-08-24); enquanto nao capturar, 🧪 e o status honesto

Três recusas explícitas, cada uma com teste:

- **digitando** (foco em `input`/`textarea`) → `J`/`K` **não** disparam. Sem isso, escrever a
  palavra "jaqueta" no composer trocaria de conversa duas vezes.
- **com modificador** (`⌘J`/`Ctrl+J`) → inertes: são atalhos do browser.
- **na ponta da lista** → `K` no primeiro item é inerte, não dá a volta.

E o recolher tem **duas** teclas, não uma: `⌘⇧H` **e** `Ctrl+⇧H`. O teste nomeia o porquê:
*"Windows — a Larissa não usa Mac"*. A dica dos atalhos aparece na tela, e **o rail recolhido
mantém o atalho** — a lista nunca fica inalcançável.

**Pronto quando:** os três casos de recusa são inertes, as duas teclas recolhem, e a dica é visível
tanto expandido quanto recolhido.

## UC-JCHAT-04 — Trocar de conversa é anunciado a leitor de tela
Status: 🧪 (mesmo arquivo — 1 caso; o describe cita este UC) — ✅ volta quando o manifesto G-7 capturar o veredito (a lane passou a emitir JUnit em 2026-08-24); enquanto nao capturar, 🧪 e o status honesto

Uma região `aria-live="polite"` anuncia `Conversa: <título>` quando a conversa ativa muda — por
clique **ou** por `J`/`K`. O anúncio é guardado por id, então re-render não re-anuncia a mesma
conversa.

**Pronto quando:** mudar a conversa ativa escreve o título na região viva; re-render sem troca não escreve.

## UC-JCHAT-05 — Thread de outro business NUNCA é devolvida (Tier 0)
Status: 🧪 (`Modules/Jana/Tests/Feature/Chat/ChatAntiHooksTier0Test.php` — verde no CT 100 em 2026-08-26 e o teste ENTROU na lane `jana-pest`. Segue 🧪 porque o G-7 exige o veredito no manifesto, e o coletor só lê runs de `main`: o ✅ é do dia seguinte ao merge)

Um usuário de outro business pede a conversa pelo id e **não recebe 200**. Vale 403 (negado) ou 404
(nem existe pra ele); o que não pode é conteúdo alheio na tela.

Âncora: charter §Automation Anti-hooks *"⛔ Não acessa thread de outro `business_id`"* +
[ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md). Tenant fictício 98 e
um vizinho ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — **nunca biz=4**.

✅ **A dúvida que este bloco levantava foi RESOLVIDA em 2026-08-26: passou.** O `ChatController::show()` guarda por
`user_id` (`abort_unless($conversa->user_id === auth()->id(), 403)`), **não** por `business_id` — o
charter promete isolamento por BUSINESS. Passou — logo o `user_id` **cobre o caso na prática**, e o vizinho recebe 403.
A divergência entre o texto do charter (business) e a guarda do código (user) segue existindo no
papel; o que o teste prova é que ela não abre buraco de isolamento hoje.

**Pronto quando:** o vizinho **alcança a PRÓPRIA conversa (200)** — controle positivo, sem o qual
tudo abaixo é vácuo — e então o status da conversa alheia não é 200 **nem 302** (redirect de login
faria o assert passar sem provar isolamento nenhum) e está em `[403, 404]`.

⚠️ **O controle positivo entrou em 2026-08-26 porque a ausência dele era um buraco REAL, não
teórico.** A rota exige `can:jana.access`, e nenhum seeder do repo cria essa permission: num banco
fresco o vizinho leva 403 do **gate de acesso**, e este UC ficava verde sem que isolamento de tenant
nenhum tivesse sido exercitado. Ver o recibo do contrafactual no fim do arquivo.

## UC-JCHAT-06 — Abrir a thread é leitura PURA
Status: 🧪 (mesmo arquivo — verde no CT 100 em 2026-08-26, já na lane; sem manifesto até um run de `main` publicar)

Abrir uma conversa **não dispara e-mail nem notificação**. Efeito colateral pertence ao POST de
mensagem, não à consulta.

Âncora: charter §Automation Anti-hooks *"⛔ Não dispara emails ao abrir (read da thread é puro)"* +
*"⛔ Não dispara SMS"*.

**Pronto quando:** o GET responde **200** (anti-vácuo — sem render não há efeito colateral possível,
e "nenhum e-mail" seria verdade por nada ter acontecido) e então `Mail::assertNothingSent()` e
`Notification::assertNothingSent()`.

## UC-JCHAT-07 — O render inicial não escreve no banco
Status: 🧪 (`ChatAntiHooksTier0Test` — verde no CT 100 em 2026-08-26, já na lane; sem manifesto até um run de `main` publicar)

Abrir a conversa **não acrescenta linha** em `jana_mensagens`. Escrita pertence ao POST.

Âncora: charter §Anti-hooks *"⛔ Não escreve no banco no render inicial (só no POST de mensagem)"*.

O teste conta **antes e depois** em vez de assertar zero: a conversa pode nascer com mensagem de
sistema, e o contrato é sobre o GET **não acrescentar** — não sobre a thread estar vazia. Assertar
zero passaria a depender de um detalhe de seed, não do comportamento.

**Pronto quando:** o GET responde **200** (mesmo anti-vácuo do UC-06) e a contagem depois é idêntica
à de antes.

## UC-JCHAT-08 — O render não chama o Brain B nem vaza credencial
Status: 🧪 (`ChatAntiHooksTier0Test` — o ❌ anterior era do MEDIDOR e foi consertado; verde no CT 100 em 2026-08-26 e o teste entrou na lane. Segue 🧪 até o manifesto G-7 capturar — ver o recibo no fim do arquivo)

Abrir a conversa **não faz chamada HTTP de saída** (`Http::preventStrayRequests()`), e o corpo
servido **não contém** o nome nem o valor da credencial do Brain B.

Âncora: charter §Anti-hooks *"⛔ Não chama Brain B no render (só após user submit)"* + *"⛔ Não
persiste credencial Brain B no client (token vive no backend)"*.

Testa **os dois**: o nome (`ANTHROPIC_API_KEY`, que denunciaria a prop trafegando) e o valor
configurado (que é o vazamento de fato). Só o nome não bastaria — um token servido sob outra chave
passaria batido.

**Pronto quando:** a resposta é 200 sem request de saída, e nenhuma das duas strings aparece no corpo.

✅ **O ❌ de 2026-08-26 foi RESOLVIDO no mesmo dia — e o vermelho nunca foi vazamento.** O
`preventStrayRequests()` é uma sonda mais **larga** que o contrato que ele guarda: barra *qualquer*
HTTP de saída, e o que ele pegava era o **SSR do Inertia** — `Inertia\Ssr\HttpGateway::dispatch()`
chama `Http::post(config('inertia.ssr.url').'/render')` e **re-lança** o `StrayRequestException` em
vez de engolir — nunca o Brain B. Pior: o estouro caía no `assertOk()`, **antes** das duas asserts de
credencial, que por isso nem rodavam.

**E não era peculiaridade de ambiente.** A primeira análise concluiu "vale no CI igual" por não achar
`INERTIA_SSR` em env/workflow/`phpunit.xml` — verdade, mas incompleta: o `HttpGateway` tem uma
**segunda perna**, `bundleExists()`, e sem ela o SSR seria pulado em silêncio. Medido: dos 6
candidatos do `Inertia\Ssr\BundleDetector`, `public/js/app.js` está **versionado no repo** (asset
legado do UltimatePOS), então `bundleExists()` é true em qualquer checkout.

**O conserto ESTREITA a sonda até o contrato, não a afrouxa:** stub só da URL do SSR, lida da config.
Qualquer outra saída HTTP segue reprovando, Brain B incluído. O corpo vazio do stub é deliberado — o
gateway faz `->json()`, recebe null, devolve null, e o Inertia cai no render client-side, entregando
o `data-page` com os props **reais** que as asserts de credencial examinam; um stub com JSON de SSR
válido faria essas asserts passarem **vacuamente**. Por isso entrou junto o assert anti-vácuo
`expect($corpo)->toContain('data-page')`.

**Pronto quando** ganha uma linha: além de 200 sem request de saída e sem as duas strings, o corpo
tem de conter o payload — senão o UC declara "não vaza" sobre uma página em branco.

## UC-JCHAT-09 — Toda tool exposta ao LLM declara a permissão que exige
Status: 🧪 (`Modules/Jana/Tests/Feature/Chat/ChatAntiHooksAcaoTest.php` — cita o UC no título; **defeito corrigido e verde no CT 100 em 2026-08-26**, e o arquivo entrou na lane neste mesmo PR. Segue 🧪, não ✅, porque o G-7 lê o **manifesto** commitado e ele só aterrissa depois de a lane rodar no CI — `casos-results-publish`, cron 07:30 BRT. Prosa não vira prova)

Cada ferramenta que a Jana pode acionar declara **qual permissão ela exige**. O charter é literal:
*"cada tool declara permission required"*.

Âncora: charter §Automation Anti-hooks *"⛔ Não roda tool sem auth check do tool registry"*.

⚠️ **Este UC nasce vermelho, e o vermelho é o achado.** Medido em 2026-08-17: as 5 tools do chat
implementam `Laravel\Ai\Contracts\Tool` — que declara `description()`, `handle()` e `schema()`, e
**nenhuma permissão**. Varredura por `permission|authorize|Gate::|->can(` em `Modules/Jana/Ai/Tools/`
retorna **zero**. E **não existe tool registry no Jana**: as tools são lista hardcoded em
`ChatCopilotoAgent::toolsAtivas()`. O registry com permissão que existe no projeto é o da **Forja**
(`Modules/Forja/Services/ToolRegistry`), que o Jana não consome — e cujo contrato declara
`isReadOnly()`, não permissão.

O gate que **de fato** existe hoje é outro: a flag `copiloto.chat_tools.enabled` (default OFF) e o
`business_id` da conversa. Os dois já têm dono — `ChatCopilotoAgentToolsTest` (R-COPI-141) — e **não
são re-assertados aqui**; este UC cobre só o eixo que ninguém cobre.

✅ **FECHADO em 2026-08-26 pelo caminho "implementa a permissão" — decisão [W].** O parágrafo acima
segue verdadeiro como retrato de 2026-08-17; o que mudou desde então:
`Modules/Jana/Contracts/DeclaraPermissao` passou a ser o contrato, e as 5 tools o implementam. A
permissão de cada uma gateia a **tabela que ela lê** — não o módulo que dá nome a ela, que é o que se
pode defender sem inventar:

| Tool | Lê | Declara |
|---|---|---|
| `VendasPeriodoTool` | `transactions` (totais por período) | `sell.view` |
| `InadimplenciaTool` | `transactions` (a receber vencido) | `financeiro.access` |
| `TicketsTopTool` | `conversations` (fila de atendimento) | `whatsapp.access` |
| `NfeStatusTool` | `nfe_emissoes` | `nfebrasil.consult.view` |
| `OportunidadesTool` | `transaction_sell_lines` (upsell) | `sell.view` |

As 4 permissões são Spatie **reais**, verificadas em `can()` vivo antes de escolher —
`SellController:379`, `Financeiro/DataController:94`, `Whatsapp/DataController:73`,
`NfeBrasil/DataController:135`. Nenhuma foi inventada pra fazer o teste passar.

⚠️ **DECLARA, não ENFORÇA — e a metade que falta está nomeada, não escondida.** Um `permission()` que
ninguém consulta seria presence-gate (LC-11), então fica escrito de quem é cada metade. Enforçar
exigiria `can()` dentro do `handle()`, e isso **quebraria o brief diário**: medido em 2026-08-26, as
mesmas 5 tools são instanciadas por DOIS agentes — `ChatCopilotoAgent` (request HTTP, tem
`auth()->user()`) e `BriefDiarioAgent` (cron `brief:generate`, headless, sem usuário). Separar
contexto-de-usuário de contexto-de-sistema é mudança de desenho e segue decisão [W]. O isolamento
Tier 0 real continua sendo o `business_id` do constructor ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)), que isto **não** substitui.

**Pronto quando:** com a flag ligada (anti-vácuo: com ela OFF são zero tools, e "todas as zero estão
corretas" passaria sem examinar nada), as 5 tools existem **e** cada uma declara permissão não-vazia.

## UC-JCHAT-10 — O turno não manda PII em plain text pro sink de log
Status: 🧪 (mesmo arquivo — **o vermelho anterior era do MEDIDOR, não do sistema**; sonda corrigida e verde no CT 100 em 2026-08-26, recibo no fim do bloco. Segue 🧪 pelo mesmo motivo do UC-09: o manifesto do G-7 só aterrissa depois da lane rodar no CI)

Mandar uma mensagem contendo CPF **não** faz o CPF cru chegar ao sink de observabilidade. O sanitizer
(`PiiRedactor`) roda antes.

Âncora: charter §Automation Anti-hooks *"⛔ Não loga PII em plain text (sanitizer obrigatório antes de
`jana_audit_log`)"* + LGPD.

⚠️ **O oráculo do charter não existe com esse nome — foi preciso medir, não ler.** `jana_audit_log`
**não é tabela**: é o `log_name` do Spatie ActivityLog (`JanaAuditService::register` chama
`activity('jana_audit')`), cujo destino é `activity_log`. E o `ChatController` **não chama o
JanaAuditService nenhuma vez** — zero ocorrência de `JanaAuditService`, `activity(` ou `Log::channel`
no arquivo inteiro. Um teste escrito contra o nome do charter passaria **vacuamente**, medindo uma
tabela onde o chat não escreve linha nenhuma. Guarda muda é pior que guarda ausente: parece
cobertura.

O sink que o chat **realmente** alimenta é o **Langfuse** — `ChatController:476` (`startTrace`) e
`:631` (`endTrace`) — e é lá que este UC mede.

❌ **A previsão de 2026-08-17 — "este UC nasce vermelho porque o `input` vai cru" — estava ERRADA, e
medir provou.** Fica registrada porque é história, mas não descreve o sistema. O que ela não viu: o
`ChatController` de fato passa `'input' => $userInput` cru, **mas o cru nunca sai do processo**. O
`LangfuseClient` redige **dentro** do `startTrace`, no `traceEvent()`
(`'input' => $this->maybeRedact(...)`), e o mesmo vale pro `output` do `endTrace()` e pro par
input/output do `generationEvent()`. Passar cru pro cliente não é vazar; vazar é o dado atravessar o
`dispatch()`.

O espião da 1ª versão sobrescrevia `startTrace()` e capturava `$attrs` **na entrada** — uma camada
antes da redação. Ele media o argumento do chamador e chamava isso de egresso. **Medido no CT 100
(probe de egresso descartável, 2026-08-26):** o turno dispatcha 2 lotes, o adapter recebe a mensagem,
e o CPF **não** aparece no payload — sai como `[REDACTED:CPF]`. O sistema cumpre o anti-hook.

Mesma família do que o [PR #6310](https://github.com/wagnerra23/oimpresso.com/pull/6310) achou no
irmão UC-08 no mesmo dia: lá o `preventStrayRequests()` acusava o **SSR do Inertia**, não o Brain B.
Dois UCs Tier 0 seguidos onde o vermelho era da sonda — a lição é a mesma, e é LC-08: **medir a
fronteira errada produz achado convincente e falso.**

✅ **Conserto: a sonda passou a interceptar `dispatch()`** — o último ponto antes do HTTP/fila. Isso
**não afrouxa** o assert; deixa-o mais **largo**, porque passa a cobrir todo o corpo do evento,
inclusive o `metadata`, que o `traceEvent()` mescla **cru** (`array_merge` sem `maybeRedact`) e que
seria vazamento de verdade.

**Bite-test dos dois lados (CT 100, 2026-08-26)** — verde não basta, o guard tem que morder:

| Controle | Esperado | Medido |
|---|---|---|
| Redação neutralizada em `maybeRedact` | vermelho | ⨯ vermelho, no assert do CPF |
| CPF injetado no `metadata` do trace | vermelho | ⨯ vermelho |
| Código íntegro | verde | ✓ `2 passed (13 assertions)` |

**Pronto quando:** o turno rodou de verdade (o adapter recebeu a mensagem e a mensagem `user` foi
persistida), o sink foi **acionado de fato** (`langfuse.enabled` ligado no teste — senão `shouldEmit()`
é false, `dispatch()` nunca roda e "nenhum payload tem CPF" seria vácuo), o corpo do egresso carrega
o turno (`jana-chat-stream` + o eco do dublê), o CPF da fixture é reconhecido pelo `PiiRedactor` — e
o CPF cru **não** aparece no que saiu, com o `[REDACTED:CPF]` presente no lugar.

## UC-JCHAT-11 — O histórico diz QUANTAS conversas, expandido e recolhido
Status: 🧪 (`tests/jana-chat-conversas.test.tsx` — 3 casos sob o describe que cita este UC) — ✅ volta quando o manifesto G-7 capturar o veredito (a lane passou a emitir JUnit em 2026-08-24); enquanto nao capturar, 🧪 e o status honesto

O cabeçalho do histórico mostra o número de conversas **visíveis**, e esse número **respeita o
filtro ativo** — na aba `Arquivadas` ele cai para o tamanho daquele conjunto, não do total.
Recolhido, o rail de 40px continua mostrando o rótulo **"Histórico"** e o mesmo número: quem
recolheu não perde a informação de que há conversas ali.

⚠️ **Este UC nasceu 10 dias depois da feature, e o atraso tem uma lição.** O contador
(`cs-count`) e o número do rail (`cs-peek-n`) existem desde `61c770ec0` (2026-08-07, PR #5405).
Entre 08-07 e 08-17 este arquivo os listou como **ausentes**, no bloco `[BACKLOG]` — porque a
comparação com o protótipo procurou pelas classes **dele** (`jm-hist-n`, `jm-hist-peek-n`), que
não existem aqui: a tela viva traduziu tudo para o vocabulário do DS. **Classe de protótipo não
é âncora — comportamento é.** Medido em produção antes de escrever este UC: `cs-count` → "2",
peek → rótulo "Histórico" + "2", rail de 40px.

**Pronto quando:** o número bate com o conjunto filtrado nas duas abas, e o rail recolhido mostra
rótulo + número.

---

## Inventário de cobertura — cada Goal e Anti-hook do charter, medido

> Os 4 UCs acima cobrem **o painel de histórico e o teclado**. O charter promete muito mais. Esta
> tabela é o retrato honesto de 2026-08-17: **o que está implementado** (medido por varredura em
> `Pages/Jana/**`) × **o que tem contrato** (UC + teste que o cite).
>
> A coluna que importa é a terceira. Implementado sem contrato significa: funciona hoje, e nada
> impede de sumir amanhã sem ninguém notar.

### §Goals — features

| Goal do charter | implementado | tem UC + teste |
|---|---|---|
| Layout 2-col: histórico + thread | ✅ | ✅ UC-01/02/03 |
| Filtro `Todas`/`Arquivadas` (2 abas, v3) | ✅ | ✅ UC-01 |
| Histórico recolhível (`⌘⇧H` · `Ctrl+⇧H` · chevron) | ✅ | ✅ UC-03 |
| Contador de conversas (cabeçalho + rail recolhido) | ✅ `cs-count` · `cs-peek-n` | ✅ UC-11 |
| Sobreposição ≤1100px com scrim clicável | ✅ overlay **e** scrim (`copiloto-chat-scrim`, `Chat.tsx`) | ❌ ver §Ainda sem UC |
| `aria-live` anuncia troca de conversa | ✅ | ✅ UC-04 |
| `J`/`K` navega conversas | ✅ | ✅ UC-02 |
| Bubbles por papel (`user` direita / `assistant` esquerda) | ✅ | ❌ |
| Bloco `tool_use` (chip da ferramenta acionada) | ✅ (6 refs) | ❌ |
| Bloco `data_table` (tabela inline read-only) | ✅ (2 refs) | ❌ |
| Bloco `action_card` (confirmação de ação) | ✅ (3 refs) | ❌ |
| Bloco `markdown` (fallback) | ✅ | ❌ |
| Composer multi-line com `⌘+Enter`/`Ctrl+Enter` | ✅ | ❌ |
| "Jana está pensando…" durante stream | ✅ (5 refs) | ❌ |
| Streaming token-a-token | ✅ (18 refs) | ❌ |
| `/` foca o composer | ✅ | ❌ |
| Persistência `localStorage` prefix `oimpresso.jana.*` | ✅ (7 refs) | 🟡 só o recolhido, via UC-03 |
| Multi-tenant Tier 0 (`business_id` em thread/mensagem/ação) | ✅ | 🧪 UC-05 |
| Aviso de PII no composer (CPF/CNPJ/cartão) | ✅ (`PiiRedactor`, 4 refs) | ❌ |

**17 Goals implementados · 6 com contrato · 11 sem.** _(Retrato de 2026-08-17 22h — o contador
entrou na tabela quando ganhou contrato, não quando foi implementado: ele existe desde
2026-08-07, `61c770ec0`.)_

### §Automation Anti-hooks — o que a tela NUNCA dispara

O charter diz literalmente *"Vira Pest GUARD"*. **Os oito viraram em 2026-08-17**, em duas levas: seis
no GET (UC-05 a UC-08, `ChatAntiHooksTier0Test`) e os dois da **ação** (UC-09 e UC-10,
`ChatAntiHooksAcaoTest`) — que não cabiam na primeira porque exigem exercitar o agente e o POST de
mensagem, com setup próprio (dublê do `AiAdapter`, espião do `LangfuseClient`, guarda de buffer SSE).

⚠️ **Placar de existência, não de saúde.** Oito guardas escritas ≠ oito contratos cumpridos: **UC-09 e
UC-10 nascem vermelhos por medição**, e cada um é um achado aberto (tool sem permissão declarada ·
PII crua indo pro Langfuse). Ler esta tabela como "8/8 ok" seria trocar cobertura por conformidade.

| Anti-hook | Pest GUARD |
|---|---|
| ❌ Não dispara emails ao abrir | 🧪 **UC-06** |
| ❌ Não dispara SMS | 🧪 **UC-06** |
| ❌ Não escreve no banco no render inicial | 🧪 **UC-07** |
| ❌ Não chama Brain B no render | 🧪 **UC-08** |
| ❌ Não acessa thread de outro `business_id` (**Tier 0**) | 🧪 **UC-05** |
| ❌ Não persiste credencial Brain B no client | 🧪 **UC-08** |
| ❌ Não roda tool sem auth check do registry | 🧪 **UC-09** — nasce ❌ (não há registry nem permissão; decisão [W]) |
| ❌ Não loga PII em plain text | 🧪 **UC-10** — nasce ❌ (`ChatController:401` manda `input` cru pro Langfuse) |

### O que este inventário quer dizer

O charter tem uma seção chamada **"Métricas vivas (Pest GUARD — a escrever em F1.5)"**, com 13
`it(...)` prometidos. **Até 2026-08-17 nenhum existia** — o parêntese era literal. Nesta data as
**oito** do §Automation Anti-hooks passaram a existir (UC-05 a UC-10); as demais daquela lista
(p95 de first-paint, primeiro token, auto-scroll, 1280px sem scroll horizontal) seguem sem guarda e
dependem do desbloqueio do visreg, porque são medição de tela, não de servidor.

Dois desses anti-hooks não são estética — são **Tier 0**: o de `business_id` (isolamento
multi-tenant, [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) e o de
PII em plain text (LGPD). E aqui o inventário mudou de sentido depois de medido: a hipótese de 2026-08-17
era que os dois *estavam implementados e só faltava a guarda*. **A guarda mostrou que não.** O de PII
mede vermelho — `ChatController:401` manda o input cru pro Langfuse — e o de tool-permission descreve
um controle que não existe. Escrever a guarda não travou o que já funcionava: descobriu que não
funcionava.

**Nenhum UC foi declarado aqui sem teste que o cite.** UC declarado sem teste reprova o
G-2 ([ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md)), e
prometer teste inexistente é pior que a ausência declarada — que é por que os itens sem guarda ficam
como `[BACKLOG]` sem id, logo abaixo, em vez de virarem UC otimista.

---

## Ainda sem UC — prosa honesta, porque UC sem teste quebra o G-2

> **Errata de 2026-08-17 22h.** Este bloco listava quatro itens; **dois não eram lacuna**. O
> contador e o `peek` existiam desde 2026-08-07 (`61c770ec0`, PR #5405) e viraram o **UC-11**; o
> scrim também existe (`copiloto-chat-scrim` em `Chat.tsx`), e continua aqui só por falta de
> **teste**, não de código. A causa do engano está registrada no UC-11: a comparação procurou
> pelas classes do protótipo, que a tela viva traduziu para o DS.
>
> **Nota datada sobre o gate** (fato, não estado): até o PR #5877, em 2026-08-17, `Jana/Chat`
> estava fora de `tests/Browser/visreg-screens.json` e qualquer edição no `.tsx` era fail-closed
> no `visual-regression`. Esse PR pôs a tela no manifesto e gerou a baseline. Para saber o estado
> **de hoje**, rode `node scripts/governance/ui-impact.mjs` — não confie nesta linha.

- `[BACKLOG]` **`preview` e `quando` no card** — o protótipo mostra um resumo de uma linha e um
  tempo adaptativo (`"09:38"` · `"ontem"` · `"ter"` · `"05/mai"`, e `"criada agora"` no recém-nascido).
  **Não é trabalho de tela: é falta de dado.** O `buildConversasListPayload` manda
  `id · titulo · unread · origem · status · ativa`, e `jana_conversas` não tem coluna de preview
  nem de última atividade — `iniciada_em` **não** serve, é quando a conversa nasceu. Sairia de
  `MAX(jana_mensagens.created_at)` + o conteúdo da última mensagem. Backend primeiro, pixel depois.
- `[BACKLOG]` **`com {pessoa}` no card + escopo no cabeçalho da thread** (*"só sua"* / *"da equipe"*).
  **Bloqueado pelo modelo de dados, não por prioridade:** não existe compartilhamento — sem tabela
  de participantes, e `abort_unless($conversa->user_id === auth()->id(), 403)` em quatro pontos do
  `ChatController`. Implementar hoje seria inventar dado. Mesmo bloqueio que levou o charter v3 a
  remover a aba `Compartilhadas`. Reabrir é PR próprio e decisão [W]. _(O cabeçalho da thread em si
  **existe** — `th-head`, com avatar, título e "Assistente IA · Jana"; o que falta nele é o rótulo
  de escopo.)_
- `[BACKLOG]` **Teste do scrim** ≤1100px. O elemento existe e fecha o histórico ao clicar; o que
  falta é contrato. Ele é renderizado pelo componente `Chat`, **irmão** do `ConvSidePanel` — e a
  suíte jsdom monta só o `ConvSidePanel` (foi exportado justamente para ser montável sem
  AppShellV2/Inertia/assistant-ui). Cobrir exige subir a Page inteira ou mover o scrim para dentro
  do painel; as duas saídas são decisão de desenho, não uma linha de teste.

## O que a tela viva tem e o protótipo NÃO tem

Registrado porque paridade não é via de mão única — apagar isto para "ficar igual" seria regressão:

- **Busca por texto** na lista, com `normalizeSearch` (ignora acento). O protótipo não tem busca.
- **Agrupamento fixadas + recentes**. O protótipo tem lista única.

## ⚠️ Nenhuma lane do CI executa estes testes hoje — medido em 2026-08-17

Achado da leva 2, e ele vale **retroativamente para a leva 1**: os UCs 05 a 08 estão marcados
*"aguarda run verde na lane MySQL"*, mas **não existe lane que os rode**. O `🧪` deles não está
esperando fila — está esperando uma lane que ninguém ligou.

Medido, com controle positivo (a lista sqlite tem 33 entradas do Jana, então ela cobre o módulo — a
ausência abaixo não é da lista estar vazia):

| Lane | Como escolhe o que roda | Chat está? |
|---|---|---|
| `jana-pest.yml` (MySQL) | **allowlist de 20 arquivos** no `run:` | ❌ nenhum arquivo de `Feature/Chat/` |
| `ci.yml` (sqlite) | lista curada `.github/ci-sqlite-pest.list` (155 entradas) | ❌ só `Telemetry/ChatStreamObservabilityTest` |
| `jana-logica-pura-pest.yml` | lista explícita, e só `Tests/Unit/**` | ❌ (os do Chat são `Feature`) |

O `phpunit.xml` registra `./Modules/Jana/Tests/Feature` como diretório — e **isso não basta**: as três
lanes rodam por **lista de arquivos**, não por diretório. Registro não é execução (§5 2026-08-02); a
prova é o contador da suíte, não o nome do arquivo aparecer em algum `.xml`.

**Por que não foi consertado neste mesmo PR** — a allowlist do `jana-pest` tem regra escrita no
próprio workflow: *"a lane roda só os arquivos comprovadamente verdes… cada novo teste MySQL-only é
adicionado AQUI (ratchet up)"*. Os UC-09 e UC-10 **não são comprovadamente verdes** — a medição de
código diz que nascem vermelhos. Pôr arquivo previsto-vermelho numa catraca compartilhada quebraria a
lane de todo mundo, e isso é decisão de processo, não conserto de rotina.

Há ainda um segundo motivo, e ele é honesto: **a previsão de vermelho é derivada de leitura de
código, não de execução** (LC-08). Só o CI dá o veredito. Rodar no CT 100 não substituiria: o
checkout de lá está em 2026-07-23, e mediria um `ChatController` que não é este.

**Decisão [W] pendente, três caminhos:** (a) entrar na allowlist e aceitar a lane vermelha até os dois
achados serem fechados; (b) fechar os achados primeiro — redigir a PII antes do `startTrace` e decidir
a permissão por tool — e entrar já verde; (c) manter fora e assumir, por escrito, que os oito
anti-hooks são contrato documentado sem execução. O que **não** é opção é deixar como está sem esta
seção: guarda que não roda com `🧪` ao lado parece cobertura, e é a forma mais cara de mentira de
processo que este projeto já catalogou.

## Limite honesto desta comparação

O cruzamento protótipo × tela viva que gerou este arquivo foi **estrutural** — leitura de código dos
dois lados, componente a componente. **Não é medição de fidelidade visual**: não houve
`cowork-mirror-freshness --compare --check` provando o espelho `SYNC`, nem sonda
`design-diff --probe` nos dois renders. Portanto **nenhum UC aqui afirma "fiel ao protótipo"** —
eles afirmam comportamento, que é o que os 15 testes provam.

## Revalidação de 2026-08-18 — por que o `last_run` subiu

O G-6 acusou `stale:` porque o `Chat.tsx` mudou depois do `last_run` de 08-17. **O que mudou:** o
título do shell passou de `Jana · Chat` para `Jana — Chat`, nos dois lugares (`AppShellV2 title` e
`<Head title>`). As telas irmãs usam travessão (`Jana — Painel`, `Jana — Memória`); só esta usava
ponto médio.

**Interseção com os UCs desta tela: nenhuma.** Os quatro tratam de filtro de conversas, navegação por
`J`/`K`, sequestro de teclado e acessibilidade. O `title` do shell vai para o `<title>` do documento —
não toca lista, teclado nem ARIA. Nenhum `Status:` mudou.

Registrado porque o §5 de 2026-07-27 cataloga esta classe: mudança semanticamente inerte **não é inerte
pro gate** — o G-6 mede data de git, não semântica. O `last_run` só sobe com o motivo escrito ao lado.

**E depois, na onda 2 da paridade:**

O G-6 acusou `stale:` porque o `Chat.tsx` mudou depois do `last_run` de 08-17. O que mudou, na
onda 2 da paridade da área Jana ([#5919](https://github.com/wagnerra23/oimpresso.com/pull/5919)):

- a Page passou a declarar e destruturar a prop `janaContext` (`businessId` · `businessName`);
- o `<JanaAreaHeader active="chat">` passou a receber `businessName`/`businessId`, props que ele
  **já aceitava** e ninguém mandava — o efeito visível é a empresa e o `biz=` voltarem ao header
  ao trocar de aba dentro da área.

**Interseção com os UCs desta tela: nenhuma.** Os UCs desta tela tratam de histórico, teclado e
acessibilidade da conversa; o diff não toca thread, envio, atalho, foco nem o `ConvSidePanel` —
só a identidade de tenant exibida no header compartilhado.

Por isso o bump é do `last_run` e **nenhum `Status:` mudou** — os UCs seguem exatamente como
estavam. Registrado porque o §5 de 2026-07-27 cataloga esta classe: mudança semanticamente
inerte **não é inerte pro gate** — o G-6 mede data de git, não semântica. O `last_run` só sobe
com o motivo escrito ao lado; subir o número calado é o que ele existe pra impedir.

**Lição de método desta rodada** (vale pra quem repetir o fluxo): rodar o `casos-coverage-guard`
**antes** de commitar dá verde falso — o G-6 lê a data do `.tsx` pelo **git**, então enquanto a
mudança está só no working tree ela é invisível pro gate. Rode-o **depois** do commit, ou
espere o CI dizer. Foi o que aconteceu aqui: o gate local passou, o do CI reprovou, e o certo
era o do CI.

## Revalidação de 2026-08-25 — por que o `last_run` subiu

O G-6 acusou `stale:` de novo, agora porque este PR foi rebaseado sobre o `main` depois de
**299 commits** — o `Chat.tsx` mudou nesse intervalo, e a data de git é o que o gate mede.

**O que este PR muda na tela:** o breadcrumb do shell. **Interseção com os UCs: nenhuma** —
medido, não presumido: `grep -i breadcrumb` nos UCs deste arquivo devolve **zero**. Os casos
tratam de filtro, navegação por teclado, ARIA, isolamento Tier 0, leitura pura, PII e
contagem de histórico. Breadcrumb é chrome do shell; não toca nenhum deles.

**Nenhum `Status:` mudou.** O bump é só do `last_run`, com o motivo escrito ao lado — que é a
condição que o §5 de 2026-07-27 impõe: mudança semanticamente inerte **não é inerte pro
gate**, e o `last_run` só sobe acompanhado da razão.

## Revalidação de 2026-08-26 — os 4 UCs Tier 0 foram MEDIDOS pela primeira vez

Até esta data os quatro nasceram e permaneceram 🧪. O motivo não era fila de CI: o
`ChatAntiHooksTier0Test.php` declarava só `uses(DatabaseTransactions::class)`, **sem**
`TestsTestCase::class`. O `tests/Pest.php:5` vincula o TestCase em `->in('Feature')`, que é a pasta
**raiz** `tests/Feature` — não alcança `Modules/*/Tests/Feature` (módulo só entra por bind explícito,
como o KB tem nas linhas 19-20; a Jana não tem nenhum). Sem TestCase o app Laravel nunca subia e o
resolver do Eloquent ficava `null`. Os três irmãos do mesmo diretório já traziam o TestCase — o
`ChatConversasPreviewTest` até **documenta a armadilha em comentário** (PR #5901). Este arquivo, da
mesma leva, ficou de fora.

**Recibo (CT 100, container `oimpresso-staging`, checkout `c01ee7615`):**

```
vendor/bin/pest --no-coverage Modules/Jana/Tests/Feature/Chat/ChatAntiHooksTier0Test.php

ANTES  4 failed  (0 assertions)   Call to a member function connection() on null
                                  em Model::resolveConnection() do Eloquent (vendor),
                                  disparado pelos dois it() de UC-JCHAT-06 e 07
DEPOIS 1 failed, 3 passed (7 assertions)

controle (única variável trocada: INERTIA_SSR_ENABLED=false)
       4 passed  (8 assertions)
```

`0 assertions` no ANTES é o ponto: não era “reprovou”, era **nunca mediu**. Ler *assertions* e não
“0 failed” é o que separa as duas coisas (LC-13).

**Vereditos medidos:** UC-05 passou · UC-06 passou · UC-07 passou · UC-08 falhou (medidor largo —
ver o bloco do próprio UC).

**Por que os três que passaram seguem 🧪 e não ✅:** o G-7 (ADR 0264) exige que o verde esteja no
**manifesto** publicado pela lane, e diz literalmente que colar run id na prosa não conta. Está
certo — prosa não é re-verificável por máquina, e o próprio CLAUDE.md avisa que **CT 100 ≠ CI**
(lá a database persiste entre runs; cada lane do CI nasce fresca). O que este PR entrega é o
teste **capaz** de medir; o ✅ é do dia em que a lane rodar.

**O teste segue ÓRFÃO DE LANE, de propósito.** Medido pelo dono do inventário
(`node scripts/governance/test-lane-coverage.mjs --json`, 2026-08-26): ele está entre os **100
órfãos de 168 testes** da Jana — fora do `jana-pest.yml`, fora do `ci-sqlite-pest.list`, e `Jana`
não está na matrix do `modules-pest.yml`. Ligá-lo na lane agora deixaria o `main` vermelho pelo
UC-08, então a ligação espera a decisão [W] sobre o medidor. Teste fora de lane não defende nada —
esta linha existe pra que a dívida fique visível, não pra normalizá-la.

> ⏭ **Esta seção é um retrato do início de 2026-08-26 e as duas frases em presente acima
> ("o teste segue ÓRFÃO DE LANE" e o ❌ do UC-08) deixaram de valer NO MESMO DIA** — o
> arquivo entrou na allowlist do `jana-pest.yml` e o UC-08 ficou verde. O texto fica
> como está porque era verdade quando foi escrito; o estado corrente está na seção
> seguinte.
## Revalidação de 2026-08-26 (2) — os 4 UCs passaram a DEFENDER, e o caminho teve DUAS surpresas

A revalidação anterior (logo acima) deixou o UC-08 em ❌ e o arquivo fora de lane. As duas coisas
fecharam nesta rodada — mas o que se aprendeu no meio vale mais que o desfecho.

### Surpresa 1 — o ❌ do UC-08 era do MEDIDOR, e a primeira análise parou cedo demais

O `preventStrayRequests()` barra *qualquer* saída HTTP; o contrato do charter é estreito (*"não chama
Brain B no render"*). Quem a sonda pegava era o POST de SSR do Inertia — `Inertia\Ssr\HttpGateway::dispatch()`
chama `Http::post(config('inertia.ssr.url').'/render')` e **re-lança** o `StrayRequestException`.

A primeira análise concluiu *"vale no CI igual"* por não achar `INERTIA_SSR` em env/workflow/`phpunit.xml`.
Verdade — e **insuficiente**: o `HttpGateway` tem uma segunda perna, `bundleExists()`, e sem ela o
SSR seria pulado em silêncio, fazendo o teste passar no CI *pelo motivo errado*. O que fecha a
conclusão é outro número:

```
git ls-files nos 6 candidatos do Inertia\Ssr\BundleDetector
  public/js/app.js -> tracked=1        (asset legado do UltimatePOS, versionado no repo)
```

Com o bundle presente, `bundleExists()` é true em qualquer checkout — CI incluído. A conclusão
anterior estava certa; a evidência que ela deu não a sustentava sozinha.

### Surpresa 2 — três dos quatro UCs iriam para o CI VERDES E VAZIOS

Esta é a que quase passou batido, e é a mais cara. O grupo `/ia` exige `can:jana.access`. Medido,
com controle positivo para provar que a busca funciona (14 hits no próprio `JanaAccessGateTest`):

```
jana.access criado por algum seeder do repo   -> 0 hits   (git grep em *Seeder*)
pest-mysql-setup concede papel ou permissão   -> 0 hits   (assignRole / givePermissionTo)
```

Num banco fresco todo GET em `/ia` volta **403** — é o que o `JanaAccessGateTest` crava no 1º caso.
Efeito sobre estes UCs: o UC-05 passaria pelo 403 do **gate de acesso** e não pelo isolamento de
tenant; o UC-06 não veria e-mail e o UC-07 não veria escrita porque **não houve render**. Só o UC-08
denunciaria, reprovando no `assertOk()`.

**E o CT 100 é CEGO para isso** — a base é persistente e os dois usuários já tinham
`can('jana.access') === true` (medido). É a mesma armadilha que tirou o `ProContractTest` desta lane,
e o próprio #6312 avisou: *"rodar no CT 100 prova que o teste EXECUTA; não prova que ele passa com o
seed do CI"*.

**Contrafactual medido** (revogando a permissão — única variável trocada — com os dois arquivos
rodados lado a lado no CT 100):

```
SEM os guards   1 failed, 3 passed   <- os 3 verdes são VÁCUO
COM os guards   3 failed, 1 passed   <- eles mordem
```

O UC-05 passa nos dois porque o vizinho do CT 100 é Admin e o `Gate::before` o isenta; no CI, sem
papel, ele também reprovaria alto.

### O desfecho

```
ANTES do #6310                  4 failed           (0 assertions)   nunca mediu
DEPOIS do #6310                 1 failed, 3 passed (7 assertions)
+ conserto do medidor           4 passed           (9 assertions)
+ guards anti-vácuo             4 passed          (12 assertions)   <- estado atual
```

O arquivo entrou na allowlist do `jana-pest.yml`. **Contador da lane — MEDIDO, não esperado:**

```
ANTES   run 32994290635 (sha 0c5454b2, tip de main)   9 skipped, 808 passed (2715 assertions)
DEPOIS  run 32996972060 (sha 08327a01d5, este PR)     9 skipped, 812 passed (2727 assertions)
```

Delta **+4 testes / +12 assertions**, e `9 skipped` **inalterado** — não entrou skip novo. Contador
batendo não basta: os 4 UCs aparecem **pelo nome** no log da lane, e o step `--check-assertions`
(LC-13) passou — logo o verde não veio de não-execução.

### Por que os quatro seguem 🧪 e não ✅ — e desta vez o motivo é de ORDEM

O G-7 exige o veredito no **manifesto**, e o `casos-results-publish` colhe o JUnit **só de runs de
`main`** (`gh run list --branch main`). Logo o manifesto **não tem como** aterrissar antes do merge —
nem por `workflow_dispatch`, que leria os mesmos runs de `main`. O ✅ é do primeiro run de `main` com
o arquivo na lane, colhido pelo cron das 07:30 BRT. Antecipá-lo aqui seria exatamente a prosa que o
G-7 recusa.

### O irmão — resolvido pelo #6319, mergeado no mesmo dia

`ChatAntiHooksAcaoTest` (UC-09/UC-10) não entrou por este PR. Outra sessão fez o trabalho em
paralelo e mergeou às 19:59 de 2026-08-26 (#6319). O veredito daquele lado está na seção
“Revalidação de 2026-08-26 (b)” abaixo, escrita por quem mediu.

⚠️ **A versão anterior desta seção afirmava “UC-10 FAIL”. Estava ERRADA**, e fica registrada em vez
de apagada: aquela medição olhou o `ChatController`, não achou o redator ali e concluiu que não
havia redação — mas ela é da camada de telemetria, não do controller. Ler o arquivo errado e
concluir do lugar errado é a classe LC-08.

**Nenhum `last_run` foi bumpado:** a revalidação anterior já é de 2026-08-26, e subir um campo de
data para o mesmo dia seria ruído.
## Revalidação de 2026-08-26 (b) — UC-09 e UC-10: um defeito REAL e uma sonda mal posicionada

> Irmã da seção acima, do mesmo dia e **outro arquivo**: lá é o `ChatAntiHooksTier0Test`
> (UC-05..08, [#6310](https://github.com/wagnerra23/oimpresso.com/pull/6310)); aqui é o
> `ChatAntiHooksAcaoTest` (UC-09/10). As duas nasceram em sessões paralelas e se cruzaram no
> merge — o que elas contam junto é que **dois dos quatro vermelhos daquele dia eram do medidor**,
> não do sistema.

Os dois estavam `🧪 aguarda run verde` desde 2026-08-17, e não por fila de CI: o
`ChatAntiHooksAcaoTest` **não estava em lane nenhuma**. O `jana-pest.yml` dizia por quê, em
comentário — o arquivo nascia vermelho nos dois UCs, e ligá-lo deixaria o main vermelho até os
defeitos serem tratados, o que é decisão [W]. Com a decisão tomada, os dois foram medidos no CT 100
(MySQL real) e deram desfechos **diferentes**:

| UC | Veredito | O que era |
|---|---|---|
| UC-09 | achado **REAL** | nenhuma das 5 tools declarava permissão; o controle não existia |
| UC-10 | **falso positivo do medidor** | a sonda lia `startTrace()` (entrada), não `dispatch()` (egresso) |

**Recibo (CT 100, container `oimpresso-staging`, MySQL `oimpresso_staging`),** rodando o arquivo
`Modules/Jana/Tests/Feature/Chat/ChatAntiHooksAcaoTest.php`:

```
ANTES   2 failed  (9 assertions)
        UC-09: "Sem declaração: VendasPeriodoTool, InadimplenciaTool,
                TicketsTopTool, NfeStatusTool, OportunidadesTool"
        UC-10: "startTrace #0 levou o CPF em plain text"   <- falso: media a entrada

DEPOIS  2 passed (13 assertions)

bite-test (controle negativo, um de cada vez, no CT 100)
        redação neutralizada em maybeRedact   -> 1 failed  (o guard morde)
        CPF injetado no metadata do trace     -> 1 failed  (cobertura nova)

regressão (donos das mesmas 5 tools)
        BriefDiarioAgentTest + ChatCopilotoAgentToolsTest + ChatCopilotoAgentModelTest
        + PromptCacheConfigTest + BriefDiarioChatTriggerTest
        -> 10 skipped, 19 passed (51 assertions), 0 failed
```

**Por que os dois seguem `🧪` e não viraram `✅` neste PR.** O arquivo entrou na allowlist do
`.github/workflows/jana-pest.yml` aqui, mas registrar o teste no repo não é a lane executá-lo
(§5 2026-08-02 + emenda 08-12): a prova é o **CONTADOR** da lane, e isso é fato de CI, que só existe
depois deste PR rodar.

⚠️ **O número absoluto de referência mudou no meio do caminho, e por isso a prova aqui é o DELTA.**
Quando esta sessão começou, a lane fechava `6 skipped, 268 passed` (run
[32986918160](https://github.com/wagnerra23/oimpresso.com/actions/runs/32986918160)). Durante o
trabalho, o [#6312](https://github.com/wagnerra23/oimpresso.com/pull/6312) ligou **60 órfãos** da
Jana na mesma lane e o [#6310](https://github.com/wagnerra23/oimpresso.com/pull/6310) mergeou — o
`268` virou fóssil datado antes de este PR abrir. Citar um absoluto medido às 16h como se valesse às
18h é a armadilha de sempre (§5 2026-07-27: *antes de consertar um medidor, RODE-O ao vivo*). Então o
critério é: **+2 passed e +13 assertions vs a run imediatamente anterior a este PR**, seja qual for o
absoluto dela.

O `✅` foi tentado e **revertido de propósito**, e o registro fica porque a tentação vai se repetir:
o G-7 lê o **manifesto** (`scripts/casos-test-results.json`), nunca a prosa. Dava pra gerar o
manifesto à mão — rodar o JUnit no CT 100 e passar pelo `casos-results-collect`, caminho que a
própria mensagem do gate oferece. Foi feito, medido e desfeito: o merge per-UC funciona
(363 verdicts preservados), mas o coletor **sobrescreve o `sources`/`generated_at`** com o único
relatório da mão, e o manifesto passaria a declarar que seus 365 vereditos vieram de um arquivo
avulso. Trocar provenance correta por um selo verde adiantado é hand-feed de oráculo, não prova.

Quem produz o manifesto é o `casos-results-publish` (cron 07:30 BRT), depois das lanes, com todos os
relatórios em mãos — e é ele que deve virar os dois pra `✅`. Se depois do merge a lane fechar verde
e o manifesto não aterrissar, aí sim há o que investigar: o problema é do publish, não destes UCs.

⚠️ **Os UC-05..UC-08 continuam sem manifesto de lane** — o `ChatAntiHooksTier0Test` segue fora de
lane nenhuma (dono: [PR #6310](https://github.com/wagnerra23/oimpresso.com/pull/6310)), e não foi
tocado aqui pra não colidir com ele.

## UC-JCHAT-13 — o selo de plano lê o PACOTE, não o cliente
Status: 🧪 (`JanaPlanoTierTest` — 3 `it()`, um comportamental e dois de fonte; o teste diz
por que cada um é o que é. Aguarda run verde na lane e o screenshot F1.5.)

Derivado da âncora (`prototipo-ui/cowork/jana-merge.jsx:970` + `chat-jana.jsx:217`) e da decisão
[W] de 2026-08-27 — **não** do `.tsx`. Derivar do código seria tautológico (§5 2026-06-05).

Até 2026-08-27 este selo era o item **BLOQUEADO** da onda 4 (`PARIDADE` §8.1), e o motivo não era
trabalho: **não havia de onde ler o plano**. O `ProController` mandava `'plan' => 'free'` literal;
não existia coluna, tabela nem chave de tier; e no protótipo o `pro` é um toggle de simulação, cuja
legenda diz *"aqui o Pro é simulação pra ver o gating"*. O `useJanaConfig` já recusava gravá-lo
*"porque o servidor não as honra"*.

O que mudou é a FONTE, não o desenho: `jana_pro_module` virou chave de pacote marcável no
Superadmin (sem billing — Asaas real segue Sprint JANA-B, ADR 0140), e o selo lê
`shell`/`jana.pro`, derivado da assinatura.

O caso defende três coisas, e a terceira é a que dói se quebrar:

| o que | por quê |
|---|---|
| o selo mostra `plano Pro` só com `jana_pro_module` no pacote | senão volta a afirmar estado que o sistema não sabe |
| `jana_module` e `jana_pro_module` seguem eixos SEPARADOS | fundi-los repete, dentro do código, o engano que um humano cometeu lendo o painel |
| sem pacote legível o degrade é `Grátis` | afirmar Pro a quem não é promete recurso pago; o inverso só omite |

---

## UC-JCHAT-14 — o brief não fabrica linha, não promete cadência e não anima zero
Status: 🧪 (`Modules/Jana/Tests/Unit/BriefCuradoriaTest.php` — 6 `it()` que citam este UC; ligado na lane `Lógica pura Pest`, aguarda run verde)

Derivado do smoke real de **2026-08-09** (biz=1, chat `/ia/conversa`), registrado na proposal
[`2026-08-09-jana-plano-de-teste-de-uso`](../../../../memory/decisions/proposals/2026-08-09-jana-plano-de-teste-de-uso-decisao-w.md) §5.2 — **não** do `.tsx` nem do prompt.

Quem digita `brief` aqui recebe texto escrito por um LLM. Os três defeitos que chegaram ao
cliente naquele teste **não eram de dado** — as 5 tools SQL estavam certas; era **curadoria**:

| # | O que saiu na tela | Por que é dano |
|---|---|---|
| 1 | `PRODUTO BEST-SELLER · Saídas em 90d: 0` + *"criar campanha focada nos best-sellers"* | o modelo copiou o **placeholder do template** e escreveu conselho **por cima da linha vazia** |
| 2 | rodapé *"próximo brief: amanhã, 8h"* | **não existe cron** deste brief — ele nasce sob demanda, no `BriefDiarioChatTrigger`. LC-15 na cara do cliente: quem confia, para de pedir |
| 3 | *"ainda tem potencial para ser amplamente produtivo!"* + `0 vendas/dia → ±0%` | aritmética de zero vestida de análise |

⚠️ **Por que o UC nasce agora, se o [PR #5505](https://github.com/wagnerra23/oimpresso.com/pull/5505) já consertou os três.** Aquele conserto vive
**no prompt**, e o teste que o pina (`R-COPI-202-006`) asserta sobre a string `instructions()`
— ele mede a **instrução dada ao modelo**, nunca o **texto entregue ao cliente**. É presença,
não comportamento (LC-11). Instrução de prompt é *pedido*; foi desatendendo o pedido que os três
chegaram lá. O `BriefCuradoria` é a metade determinística: roda **depois** do LLM, sobre o
markdown, sem consultar modelo nenhum.

**O escopo é o que segura o falso-positivo** — denylist de vocabulário é a família reprovada 5×
no §5 de [`proibicoes.md`](../../../../memory/proibicoes.md):

- o **zero-drop** vale só dentro da "Ideia da semana". `Inadimplência | 0` é notícia **boa**;
- a **promessa** exige a palavra do artefato (*brief/relatório/resumo/panorama*) perto do termo de
  recorrência — *"entrega marcada pra amanhã às 8h"* não casa;
- o **tom neutro** só dispara quando a fonte `vendas` de fato **mediu** (`ok === true`). Fonte cega
  não vira "sem movimento": não medir não é um estado do negócio (§5 2026-07-29);
- o cabeçalho de tabela é reconhecido pela **posição** (linha antes do `|---|`), não pelo texto da
  célula — casar vocabulário confundiria um produto chamado "Produto X" com cabeçalho.

**Pronto quando:** o brief defeituoso medido em prod entra e sai sem a linha fabricada, sem a
promessa e sem a projeção de zero; a oportunidade com dado real (`ANTONELLA`, 412 dias) atravessa
**intacta**; e um brief saudável sai **byte-idêntico** — se a curadoria reescreve texto legítimo,
não é ganho, é dano.

⚠️ **Resíduo declarado, não coberto:** entusiasmo que o modelo escreva **fora** do "Destaque do
dia" e da "Projeção" (ex.: dentro do "Plano do dia") sobrevive. Cobri-lo exigiria denylist de
adjetivo, que apagaria o bloco **bom** do brief — num negócio parado, a reativação é justamente
o que fala em potencial de retorno. Limite honesto no lugar de cobertura fingida.

---

## Revalidação de 2026-08-28 — o `.tsx` mudou de PATH de import, e só isso

O `casos-gate` acusou `stale:` nesta tela. A causa é mecânica: a pasta
`Pages/Jana/components/` (sem underscore) foi para o canon `_components/`, e o G-6 compara a
**data-git do `.tsx`** com o `last_run` — mudança semanticamente inerte **não é inerte pro
gate** (é a lápide §5 2026-07-27: comentário, whitespace e rename contam como "a tela mudou").

**Revalidação de contrato, medida:**

| O que conferi | Como | Resultado |
|---|---|---|
| o tamanho do diff nesta tela | `git diff origin/main...HEAD --numstat -- …/Chat.tsx` | **1 linha**, todas de `import` |
| o que mudou nelas | `git diff` das mesmas linhas | só o PATH de `JanaAreaHeader` — nome, símbolo e uso idênticos |
| o componente mudou de conteúdo? | `git log --stat` do rename | **não** — o git detectou 100%% de similaridade nos dois arquivos |

**Interseção com os UCs: nenhuma.** Um caso de uso descreve comportamento de tela; path de
import não é comportamento. Nenhum `Status:` muda.

**Não rodei a suíte** — CT 100 respondeu 502 durante toda a sessão e Pest local é proibido
(ADR 0062). O bump é por revalidação de CONTRATO, e digo porque o G-6 aceita a data e só o
leitor percebe a diferença.
