---
id: resources-js-pages-jana-chat-casos
casos: Jana Conversa · histórico · teclado · acessibilidade · /ia/conversa
irmaos: Chat.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-chat.md (runbook)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-18"
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

## UC-COPI-CHAT-01 — O filtro filtra de verdade, e são DUAS abas de propósito
Status: ✅ (`tests/jana-chat-conversas.test.tsx` — 4 casos sob o describe que cita este UC)

A lista de conversas tem **duas** abas: **Todas** (tudo que não está arquivado) e **Arquivadas**.
"Todas" **esconde** a arquivada; "Arquivadas" mostra **só** ela.

⚠️ **Duas abas é DECISÃO, não lacuna.** O protótipo desenha quatro (`todas` · `minhas` ·
`compartilhadas` · `arquivadas`); o charter v3 reduziu para duas, e um dos testes crava isso pelo
nome: *"só existem 2 abas — Minhas/Compartilhadas foram removidas"*. Havia uma **fachada** — abas
que abriam um empty state "Em breve" — e ela foi removida. Outro teste guarda a remoção.
**Restaurar as 4 abas é reintroduzir a fachada**, não ganhar paridade com a âncora.

**Pronto quando:** cada aba mostra exatamente o seu conjunto, existem 2 abas, e nenhuma exibe "Em breve".

## UC-COPI-CHAT-02 — `J`/`K` andam entre CONVERSAS, respeitando o filtro
Status: ✅ (mesmo arquivo — 3 casos; o describe cita este UC)

`J` desce e `K` sobe **na lista de conversas** — não entre mensagens da thread. O charter registra a
correção de rota da v3 (*"era 'entre mensagens'"*) e o motivo, que é de negócio e não de estilo:
*"Larissa/Wagner trabalham no teclado"* — trocar de conversa é o que se faz o dia todo.

A navegação **respeita o filtro ativo**: na aba Arquivadas, `J`/`K` não pulam para uma conversa ativa.

**Pronto quando:** `J`/`K` percorrem a ordem visual da lista e nunca saem do conjunto filtrado.

## UC-COPI-CHAT-03 — Os atalhos não sequestram o teclado de quem está digitando
Status: ✅ (mesmo arquivo — 3 casos sob o UC-02 + 4 sob o UC-03)

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

## UC-COPI-CHAT-04 — Trocar de conversa é anunciado a leitor de tela
Status: ✅ (mesmo arquivo — 1 caso; o describe cita este UC)

Uma região `aria-live="polite"` anuncia `Conversa: <título>` quando a conversa ativa muda — por
clique **ou** por `J`/`K`. O anúncio é guardado por id, então re-render não re-anuncia a mesma
conversa.

**Pronto quando:** mudar a conversa ativa escreve o título na região viva; re-render sem troca não escreve.

## UC-COPI-CHAT-05 — Thread de outro business NUNCA é devolvida (Tier 0)
Status: 🧪 (`Modules/Jana/Tests/Feature/Chat/ChatAntiHooksTier0Test.php` — cita o UC no título; aguarda run verde na lane MySQL)

Um usuário de outro business pede a conversa pelo id e **não recebe 200**. Vale 403 (negado) ou 404
(nem existe pra ele); o que não pode é conteúdo alheio na tela.

Âncora: charter §Automation Anti-hooks *"⛔ Não acessa thread de outro `business_id`"* +
[ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md). Tenant fictício 98 e
um vizinho ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — **nunca biz=4**.

⚠️ **Este UC pode nascer vermelho, e isso é o achado.** O `ChatController::show()` guarda por
`user_id` (`abort_unless($conversa->user_id === auth()->id(), 403)`), **não** por `business_id` — o
charter promete isolamento por BUSINESS. Se passar, o `user_id` cobre o caso na prática; se falhar,
o teste achou o buraco que o anti-hook descreve. Os dois desfechos são informação.

**Pronto quando:** o status não é 200 **nem 302** (anti-vácuo: redirect de login faria o assert
passar sem provar isolamento nenhum) e está em `[403, 404]`.

## UC-COPI-CHAT-06 — Abrir a thread é leitura PURA
Status: 🧪 (mesmo arquivo — cita o UC no título; aguarda run verde)

Abrir uma conversa **não dispara e-mail nem notificação**. Efeito colateral pertence ao POST de
mensagem, não à consulta.

Âncora: charter §Automation Anti-hooks *"⛔ Não dispara emails ao abrir (read da thread é puro)"* +
*"⛔ Não dispara SMS"*.

**Pronto quando:** `Mail::assertNothingSent()` e `Notification::assertNothingSent()` após o GET.

## UC-COPI-CHAT-07 — O render inicial não escreve no banco
Status: 🧪 (`ChatAntiHooksTier0Test` — cita o UC no título; aguarda run verde)

Abrir a conversa **não acrescenta linha** em `jana_mensagens`. Escrita pertence ao POST.

Âncora: charter §Anti-hooks *"⛔ Não escreve no banco no render inicial (só no POST de mensagem)"*.

O teste conta **antes e depois** em vez de assertar zero: a conversa pode nascer com mensagem de
sistema, e o contrato é sobre o GET **não acrescentar** — não sobre a thread estar vazia. Assertar
zero passaria a depender de um detalhe de seed, não do comportamento.

**Pronto quando:** a contagem depois do GET é idêntica à de antes.

## UC-COPI-CHAT-08 — O render não chama o Brain B nem vaza credencial
Status: 🧪 (`ChatAntiHooksTier0Test` — cita o UC no título; aguarda run verde)

Abrir a conversa **não faz chamada HTTP de saída** (`Http::preventStrayRequests()`), e o corpo
servido **não contém** o nome nem o valor da credencial do Brain B.

Âncora: charter §Anti-hooks *"⛔ Não chama Brain B no render (só após user submit)"* + *"⛔ Não
persiste credencial Brain B no client (token vive no backend)"*.

Testa **os dois**: o nome (`ANTHROPIC_API_KEY`, que denunciaria a prop trafegando) e o valor
configurado (que é o vazamento de fato). Só o nome não bastaria — um token servido sob outra chave
passaria batido.

**Pronto quando:** a resposta é 200 sem request de saída, e nenhuma das duas strings aparece no corpo.

## UC-COPI-CHAT-09 — Toda tool exposta ao LLM declara a permissão que exige
Status: 🧪 (`Modules/Jana/Tests/Feature/Chat/ChatAntiHooksAcaoTest.php` — cita o UC no título; aguarda run verde)

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

Fechar isto é decisão [W], não conserto silencioso: ou implementa permissão por tool, ou revoga a
linha do charter. Enquanto não decidir, o vermelho é o registro honesto da distância entre a lei e o
código.

**Pronto quando:** com a flag ligada (anti-vácuo: com ela OFF são zero tools, e "todas as zero estão
corretas" passaria sem examinar nada), as 5 tools existem **e** cada uma declara permissão não-vazia.

## UC-COPI-CHAT-10 — O turno não manda PII em plain text pro sink de log
Status: 🧪 (mesmo arquivo — cita o UC no título; aguarda run verde)

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

O sink que o chat **realmente** alimenta é o **Langfuse** — `ChatController:395` (`startTrace`) e
`:550` (`endTrace`) — e é lá que este UC mede.

⚠️ **Este UC também nasce vermelho.** `ChatController:401` passa `'input' => $userInput` **cru**, sem
`PiiRedactor` no caminho; o `endTrace` faz o mesmo com o `output`. Se o teste falhar, ele achou
**vazamento real de PII para observabilidade** — e o conserto é redigir antes de montar o payload,
nunca afrouxar o assert.

**Pronto quando:** o turno rodou de verdade (o adapter recebeu a mensagem e a mensagem `user` foi
persistida), o sink foi acionado (senão não há payload pra examinar), o CPF da fixture é reconhecido
pelo `PiiRedactor` (senão o contrato não está sendo exercitado) — e nenhum payload de `startTrace`
ou `endTrace` contém o CPF cru.

## UC-COPI-CHAT-11 — O histórico diz QUANTAS conversas, expandido e recolhido
Status: ✅ (`tests/jana-chat-conversas.test.tsx` — 3 casos sob o describe que cita este UC)

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
`<Head title>`). As telas irmãs usam travessão (`Jana — Dashboard`, `Jana — Memória`); só esta usava
ponto médio.

**Interseção com os UCs desta tela: nenhuma.** Os quatro tratam de filtro de conversas, navegação por
`J`/`K`, sequestro de teclado e acessibilidade. O `title` do shell vai para o `<title>` do documento —
não toca lista, teclado nem ARIA. Nenhum `Status:` mudou.

Registrado porque o §5 de 2026-07-27 cataloga esta classe: mudança semanticamente inerte **não é inerte
pro gate** — o G-6 mede data de git, não semântica. O `last_run` só sobe com o motivo escrito ao lado.
