---
id: requisitos-arquivos-runbook-index
title: "RUNBOOK — Arquivos (`/arquivos`)"
module: Arquivos
tela: Arquivos/Index
owner: W
status: rascunho
last_validated: "2026-08-25"
preconditions:
  - "Usuário autenticado com a permission `arquivos.access` (declarada em `DataController::user_permissions`, default `false`)"
  - "`business_id` na sessão — o `Arquivo` aplica global scope por business (ADR 0093, Tier 0)"
  - "Módulo `arquivos_module` habilitado no pacote do business (Camada 1 — superadmin/packages)"
  - "Tabela `arquivos` migrada (8 migrations do módulo, Sprint 1)"
preconditions_short: permission arquivos.access, business_id na sessão, módulo habilitado
---

# RUNBOOK — Arquivos (`/arquivos`)

> **F1 PLAN do MWART (ADR 0104).** Escrito ANTES de codar a Page, como o hook
> `block-mwart-violation` exige — ele me barrou na primeira tentativa de escrever o `.tsx`,
> e estava certo.
>
> Trio da tela (já no `main`): [`Index.charter.md`](../../../resources/js/Pages/Arquivos/Index.charter.md) (lei) ·
> [`Index.casos.md`](../../../resources/js/Pages/Arquivos/Index.casos.md) (contrato de teste).
> US: **US-ARQ-013** · ADR mãe: [0123](../../decisions/0123-modules-arquivos-backbone.md).

## 1. Objetivo

Dar a quem responde pela conformidade um lugar pra ver **o que o sistema guardou, por quanto
tempo a lei manda guardar, o que já passou do prazo e quem tocou em quê**.

Arquivos guarda coisa que a lei manda guardar (XML de NF-e por 5 anos) junto com coisa que a lei
manda apagar (PII depois da finalidade). Sem tela, ninguém no negócio sabe qual é qual.

## 2. Persona principal

Wagner (escritório, 1440px) e Eliana (financeiro) — conformidade e custo de disco.
**Não é tela de balcão:** Larissa continua alcançando o anexo pela tela da OS.

## 3. Pré-requisitos

Ver `preconditions` no frontmatter. Em especial: `arquivos.access` **não tinha nenhum
consumidor no repo** até esta tela — a rota é o primeiro. Antes dela, a permission existia
declarada e nunca era exercida.

## 4. Fluxo principal (golden path)

1. Usuário com `arquivos.access` abre `/arquivos`.
2. `ArquivosAdminController@index` recebe a **`ListArquivosRequest`** — que já existia órfã
   desde a Sprint 1 e valida `bucket · owner_type · mime · from/to · per_page · q · with_trashed`.
3. Props: `filtros` + `politica` eager (baratas); **`acervo` via `Inertia::defer`** — tem
   `paginate` + eager-load de `arquivable`, então é o caso default do RUNBOOK de defer.
4. A Page renderiza chips de bucket, busca e a tabela; o `<Deferred>` mostra skeleton até o
   payload caro chegar.

## 5. Onda desta entrega, e o que fica pra depois

Este RUNBOOK cobre a **onda 1 COMPLETA** — as 4 vistas do charter: **PR-1 (acervo)**,
**PR-2 (trilha)**, **PR-4 (cofre)** e **PR-3 (retenção)**. Todas leitura pura.

**A barra de abas nasceu no PR-2**, com a segunda vista — não antes: aba que não leva a lugar
nenhum é promessa, não navegação. Ela navega por rota (`?tab=`), o vocabulário de URL que o
projeto já usa em Financeiro, Fiscal/Dfe e Cliente — não por estado local, senão o link não é
compartilhável e o botão voltar do navegador mente.

| Vista | PR | Estado |
|---|---|---|
| Acervo | PR-1 | entregue (2026-08-24) |
| Trilha (`arquivos_audit_log`, read-only) | PR-2 | entregue (2026-08-25) |
| Retenção (KPIs + política + regras, leitura pura) | PR-3 | **entregue (2026-08-25)** — a onda 1 fecha aqui |
| Cofre (espaço por disco + 3 achados) | PR-4 | entregue (2026-08-25) |

> ✅ **A onda 1 fechou em 2026-08-25 com a Retenção.** Esta tabela chegou a dizer que a vista
> *"depende da decisão [W] na proposta `arquivos-retencao-ui-aviso-titular`"*, e isso estava
> errado: a [proposta](../../decisions/proposals/arquivos-retencao-ui-aviso-titular.md) afirma
> em **duas** passagens que *"as ondas 0, 1 e 2 não dependem dela"* e lista *"retenção em leitura
> pura"* entre o que as ondas 0-2 entregam **mesmo se ela for rejeitada**. O que ela decide é a
> **onda 3** — rodar pela tela, avisar o titular, purgar. O RUNBOOK repetiu o fato com mais força
> do que a fonte afirma, e um restatement que endurece a fonte fabrica um bloqueio que ninguém
> decidiu.
>
> **A condição de conteúdo que a proposta impõe foi cumprida:** a vista diz, com todas as letras,
> que a execução é do comando **manual** — e não afirma isso de memória, mede no runtime
> (`Schedule::events()`) se o `arquivos:retention-cleanup` está agendado. Hoje não está; se um dia
> estiver, a frase muda sozinha. Deduzir "quem roda" lendo o Kernel seria a lápide de 2026-07-17.

### 5.3 Retenção — o que a vista faz, e por que ela conta em PHP

KPIs (vence em 30 · vence em 90 · no grace · passou do prazo), a política com a **base legal por
contexto** e a contagem de arquivos de cada um, mais os 4 cards de regra (grace · aviso · estratégia
· escopo). Leitura pura.

**O prazo de um arquivo não é um número só:** é o `retention_days` da própria linha quando existe e,
quando não, o do `sub_destination` na policy — a mesma precedência que o `linha()` do acervo aplica.
Reproduzir isso em SQL exigiria aritmética de data com CASE sobre 8 contextos, em dialeto
(`DATE_ADD` não existe na lane sqlite). Usar o `ArquivosRetentionService::summary()`, que já existe,
também não serve: ele responde por **prazo global**, e o charter pede POR CONTEXTO — número certo
respondendo a pergunta errada. Então o leitor traz 4 colunas por `chunkById` e conta em PHP, com a
regra do acervo. Exato e portável; o custo é linear no acervo, o mesmo conjunto que o Cofre percorre.

⚠️ **`Config/retention.php` NUNCA foi registrado no provider** — achado nesta entrega, mesmo defeito
do `config.php` em 2026-08-24. `grace_period_days`, `notice_period_days` e `strategy` viviam num
arquivo que `config()` não alcançava. Registrado agora em namespace **próprio**
(`arquivos_retention`), não fundido em `arquivos`: os dois arquivos declaram o prazo por contexto e
o `casos.md` os trata como **espelho**, onde divergir é achado de auditoria — fundir faria um
sobrescrever o outro em silêncio, matando justamente a chance de detectar a divergência.

### 5.1 Trilha — o que a vista faz, e a pegadinha que ela carrega

Lê `arquivos_audit_log` e mostra: **quando · ação · `#id` do arquivo · quem · detalhe**. Sem
escrita, sem job, sem mutação — igual à onda 1.

⚠️ **A tabela não tem model.** É `DB::table`, não Eloquent — logo **não existe global scope**
por business. Nessa vista o `where('...business_id', ...)` explícito **é** a defesa Tier 0
(ADR 0093), não uma repetição dela. Isso é o oposto da regra do acervo, que lê pelo model
`Arquivo` e onde repetir o `where` esconderia uma quebra do scope. O teste de contrato foi
partido em dois por causa disso: um assert proíbe o `where` no acervo, outro **exige** o
filtro na trilha, e um terceiro prova o isolamento com dois businesses de verdade (98 vs 99).

Duas escolhas que valem registrar, porque a próxima sessão vai querer refazê-las:

- **O arquivo aparece como `#id`, nunca pelo nome** — é o que o protótipo desenha
  (`arq: "#" + t.arq`) e o que mantém a vista alinhada ao Non-Goal do charter. Quem precisa
  do nome tem o acervo na aba ao lado.
- **Os chips de ação saem de um `GROUP BY` do próprio log**, não de uma lista escrita em PHP.
  O vocabulário é do ENUM da coluna, que já mudou 2× por migration (`signed_url_consumed` em
  2026-07-02, `exported` em 2026-08-10); uma cópia em código ficaria defasada calada na 3ª.
  Efeito colateral bom: só existe chip pra ação que aquele business registrou de fato.
- **As abas não têm badge de contagem.** O protótipo mostra uma porque tem tudo em memória;
  aqui custaria um `COUNT` eager na tabela inteira pra pintar número em aba que ninguém abriu.
  O número da vista aberta vai no subtítulo, de graça, vindo do paginador que já veio.

### 5.2 Cofre — o que a vista faz, e as três escolhas que valem registrar

Espaço **por disco** (contagem, bytes, quantos cifrados) e os **3 achados** que o charter nomeia:
arquivo acima do cap que o vault recusa · órfão sem `arquivable` · mesmo conteúdo repetido. Sem
escrita, sem job, sem mutação — como as outras duas.

**A agregação NÃO mora no controller**, e o motivo é um gate, não estilo: o achado de duplicado
agrupa por hash, e o `ArquivosAdminControllerTest` tem um assert que reprova a menção a hash ou a
caminho de disco em **qualquer** método daquele arquivo (LGPD Art. 37 — o charter proíbe os dois na
vista de governança). Escrever a query lá o deixaria vermelho, e afrouxá-lo pra caber seria trocar a
defesa pela conveniência. Então nasceu o [`CofreStatsReader`](../../../Modules/Arquivos/Services/CofreStatsReader.php),
e o que o gate protege de verdade — hash e caminho **não chegarem à tela** — passou a ser defendido
por assert **comportamental** sobre o payload que sai do leitor. Presence-gate no controller,
comportamento no payload: aperta, não afrouxa.

**Não é um método a mais no `CuradorStatsReader`**, que é o dono declarado das estatísticas do
pipeline Curador (US-ARQ-018). Duas razões concretas, ambas no docblock do leitor novo: o `fetch()`
dele resolve o tenant com `?? 1`, ou seja **sem sessão responde pelo business 1** — resposta errada
numa tela de governança; e a taxa de dedupe dele lê `arquivos_dedupe`, que é **cross-business por
desenho** (ADR 0123 §3). Nenhuma das 4 métricas do cofre existe lá.

As três escolhas que a próxima sessão vai querer refazer:

- **O Tier 0 aqui é o oposto do da trilha.** `arquivos` **tem** model, logo tem global scope: o
  leitor usa `Arquivo::query()` e **não** repete o `where`, igual ao acervo. O que se acrescenta é
  um **portão fail-closed** na entrada — sem `business_id` na sessão, retrato vazio — porque o
  global scope faz `if ($businessId !== null)` e deixaria a query passar sem filtro. O portão não
  duplica o filtro; recusa perguntar quando não se sabe por quem.
- **"Não medi" nunca vira "0 achados".** O payload carrega `disponivel`: zero com `true` é acervo
  limpo; `false` é ausência de resposta. Dizer "0 achados" sem ter medido é afirmar saúde — o mesmo
  defeito de um watchdog que reporta verde sem ter conseguido consultar.
- **Duplicado não afirma desperdício de disco.** O `attach()` já deduplica por hash dentro do
  business, então repetição aqui veio de outro caminho (o backfill de NF-e insere com
  `DB::table()->insert()`, sem passar pelo dedupe). E o caminho de gravação é derivado do próprio
  hash, então duas linhas do mesmo mês apontam pro **mesmo arquivo físico**. Como a diferença é
  medível, ela é medida: `caminhos` conta caminhos de storage distintos no grupo — 1 é registro
  repetido, mais de 1 é disco ocupado duas vezes. Somar bytes e chamar de economia seria inventar
  um número.

E duas coisas do protótipo que **ficaram de fora, de propósito**:

- **A barra de progresso dos cards de disco.** Lá ela é `bytes / 5 GB`, e 5 GB é número do mock: não
  existe quota por disco em `Config/config.php` (conferido). Uma barra sem denominador sugere um
  teto que ninguém definiu. Volta com significado no dia em que houver quota configurada.
- **O botão "Rodar dry-run do cleanup".** É a onda 3 (PR-8 da proposta), não a onda 1 — e a onda 1
  inteira é leitura pura.

## 6. Estados (loading / empty / error / success)

- **loading** — `<Deferred fallback>` com skeleton de 6 linhas (a prop cara não bloqueia a pintura).
- **empty** — `EmptyState` explicando que o acervo enche sozinho e que **esta tela não envia arquivo**.
  Na trilha, que o log enche sozinho e é append-only.
- **filtrado-vazio** — mesma tabela, zero linhas: o chip ativo é o que explica. Na trilha ele tem
  texto PRÓPRIO: com um chip de ação ativo, quem explica o vazio é o filtro ("nenhum evento de
  `restore` no período"), não o módulo. São histórias diferentes, e o mesmo texto pros dois
  mentiria numa delas.
- **sem-permissão** — o `can:arquivos.access` devolve 403 antes de renderizar.
- **success** — acervo: tabela com prazo **e base legal** por linha. Trilha: eventos em ordem
  cronológica, sem nenhuma ação de linha. Cofre: cards por disco + os 3 achados contados.
- **não-medido** — exclusivo do cofre, e diferente de vazio: sem `business_id` na sessão (ou sem a
  tabela), a vista diz que **ninguém olhou**, em vez de exibir zeros que parecem saúde.

Só a vista **aberta** é computada no servidor: o controller registra `Inertia::defer` para uma prop
só, nunca para as três. `defer` adia a execução, mas o cliente busca **todas** as props deferidas no
segundo request — registrar duas faria quem está na trilha pagar o `paginate` do acervo. Com a
terceira vista isso deixou de ser um `if/else` e virou `match`: no formato antigo o acervo estava no
`else`, então um valor novo de `tab` cairia nele por acidente em vez de por decisão. O teste
`o controller registra a prop de UMA vista so` invoca o `index()` e olha as chaves — a regra estava
escrita aqui desde o PR-2 e nada a defendia.

## 7. Atalhos de teclado

Nenhum nesta onda. A tela não está no `MENU_SHORTCUTS` do shell e não reivindica letra.

## 8. Dependências de API/backend

| Peça | Onde | Estado |
|---|---|---|
| `ListArquivosRequest` | `Modules/Arquivos/Http/Requests/` | **já existia** (Sprint 1) |
| `Arquivo` (global scope + SoftDeletes) | `Modules/Arquivos/Entities/` | já existia |
| `Config/config.php` → `retention_days_policy` (prazo por contexto) | `Modules/Arquivos/Config/` | já existia — **sem nenhum leitor** até esta tela |
| `mergeConfigFrom` no provider | `Modules/Arquivos/Providers/` | **novo** — o módulo nunca registrou o próprio config |
| `ArquivosAdminController` | `Modules/Arquivos/Http/Controllers/` | PR-1 · ganhou a trilha no PR-2 |
| rota `GET /arquivos` → `arquivos.index` | `Modules/Arquivos/Routes/web.php` | PR-1 — **a trilha NÃO abriu rota nova**: é `?tab=trilha` na mesma |
| tabela `arquivos_audit_log` (append-only, ADR 0123 §8) | `Modules/Arquivos/Database/Migrations/` | já existia — **sem nenhum leitor de UI** até o PR-2 |
| `tab` + `acao` na `ListArquivosRequest` | `Modules/Arquivos/Http/Requests/` | **novos no PR-2** — os 2 únicos campos acrescentados ao contrato de entrada |
| `CofreStatsReader` | `Modules/Arquivos/Services/` | **novo no PR-4** — ver §5.2 pra por que não é método do `CuradorStatsReader` nem do controller |
| `vault_max_file_size_mb` (o cap que o vault recusa) | `Modules/Arquivos/Config/config.php` | já existia — a tela **lê a config**, não escreve 50 |

Nenhum endpoint novo foi inventado — a regra 3 do pedido zero-toque é ligar o que existe.
Nem a trilha nem o cofre abriram rota, model ou tabela: leem o que está lá desde a Sprint 1. O
cofre só ampliou o vocabulário de `tab` com um valor.

## 9. Multi-tenant + LGPD

- **Tier 0 (ADR 0093):** `business_id` vem da **sessão**, nunca do request — nas três vistas.
  **Mas a defesa é oposta entre elas, e confundi-las é o erro caro:**
  - **acervo** lê pelo model `Arquivo`, que tem global scope. Ali o controller **não** repete
    o `where` de propósito: duplicar esconderia uma quebra do scope.
  - **trilha** lê `arquivos_audit_log` via `DB::table` — **sem model, sem scope**. Ali o
    `where` explícito **é** a defesa, e removê-lo é vazamento cross-tenant.
  - **cofre** lê pelo model, como o acervo — e **não** repete o `where`, pelo mesmo motivo.
- **Fail-closed na trilha e no cofre:** sem `business_id` na sessão os dois devolvem vazio. O
  global scope do model faz `if ($businessId !== null)` e, sem sessão, deixa passar sem filtro —
  aposta que o `authorize()` da Request cobre no acervo, e que **não** se repete nos outros dois.
  No cofre o vazio vem marcado (`disponivel: false`), porque ali zero também seria uma resposta
  plausível e errada.
- **Zero `withoutGlobalScopes`** em nenhum dos caminhos.
- **Sem PII na vista de governança (LGPD Art. 37):** `storage_path` e o hash **não** saem do
  controller — o assert que defende isso passou a cobrir o arquivo INTEIRO no PR-2, não só o
  método da linha do acervo. Na trilha, o arquivo aparece como `#id`, nunca pelo nome. No cofre,
  onde o leitor **precisa** do hash pra agrupar duplicado, a mesma proibição é cobrada de outro
  jeito: um assert serializa o payload inteiro e prova que nem o hash nem o caminho aparecem
  nele — com controle positivo, porque "não contém" passaria por engano num payload vazio.
  O **payload sai resumido**: ele é o conteúdo da auditoria (por que o link foi emitido, de
  qual IP foi consumido, que política apagou o quê) e é o mesmo que o `arquivos:audit-log` já
  mostra. Esta tela é o "controle de acesso" que o docblock do `Arquivo` cita: a permission
  `arquivos.access` nasce `false`.
- **`biz=4` (ROTA LIVRE) nunca em teste** — tenant fictício 98, adversário 99 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

## 10. Smoke check pós-deploy

1. `curl -sv https://oimpresso.com/arquivos 2>&1 | grep '^< HTTP'` → **302** pra login sem sessão.
2. Logado com `arquivos.access` → **200** e a tabela renderiza.
3. Logado **sem** a permission → **403**.
4. Screenshot 1280 e 1440 sem scroll horizontal — **nas três abas**. No cofre, conferir que os
   cards de disco se reflowam entre as duas larguras (é `Grid min="sm"`, auto-fit) em vez de
   espremer coluna.
5. Conferir na tela: nenhum caminho de storage, nenhum MD5.
6. `/arquivos?tab=trilha` → **200**, aba Trilha ativa, eventos em ordem decrescente.
7. Clicar em Acervo e voltar em Trilha: a URL muda (`?tab=`), o botão voltar do navegador
   funciona, e a página 2 da trilha continua na trilha (o `tab` viaja na paginação).
8. Na trilha: **nenhum** botão de editar ou apagar linha, e nenhum nome de arquivo — só `#id`.
9. `/arquivos?tab=cofre` → **200**, aba Cofre ativa, cards por disco e os 3 achados contados.
   Conferir na tela: **nenhum hash**, **nenhum caminho de storage** e **nenhum botão** — nem o
   "Rodar dry-run" que o protótipo desenha (é onda 3).
10. `?tab=retencao` → **422** pela validação (`Rule::in`), não um acervo silencioso. A aba não
    existe ainda, e uma URL que devolve 200 mostrando outra coisa mente.
11. Ação inválida nunca dá 500, e os dois casos são **diferentes de propósito**:
   - `?tab=trilha&acao=UPLOAD!` → barrado pela validação (`regex:/^[a-z_]+$/`), volta com erro.
   - `?tab=trilha&acao=acao_que_nao_existe` → **passa** na validação (é minúsculo com `_`) e
     devolve lista vazia. É o desenho: quem valida vocabulário é o ENUM da coluna, não uma
     cópia em PHP, e a URL digitada à mão não merece um 500.

## 11. O que NÃO fazer

- ❌ Não adicionar upload aqui — arquivo entra pelos módulos, via trait `HasArquivos`.
- ❌ Não servir arquivo do vault por `Storage::url` — sempre `DownloadController` (ADR 0123 §6).
- ❌ Não renderizar `storage_path`/MD5 na tela.
- ❌ Não mexer em `arquivos.download` (signed + `throttle:60,1`) nem nas 3 rotas Install (ADR 0024).
- ❌ Não usar o PageHeader antigo (`@/Components/shared/PageHeader`) — tela nova vai no canon
  `{ PageHeader } from '@/Components/PageHeader'` (ADR 0189/0190). O `pageheader-migration-guard`
  reprova adotante novo.
- ❌ Não dar `hard-delete` por esta tela.
- ❌ **Não pôr ação de linha na trilha** (editar, apagar, corrigir). `arquivos_audit_log` é
  append-only e nunca purgado, nem quando o arquivo é: alterar auditoria é incidente, não
  conserto (ADR 0123 §8).
- ❌ **Não tirar o `where` por `business_id` da trilha** achando que o global scope cobre —
  ele não existe ali: a tabela não tem model. Ver §9.
- ❌ Não escrever a lista de ações em PHP pra montar o filtro — o dono do vocabulário é o
  ENUM da coluna, e ele já mudou 2× por migration. Os chips saem de `GROUP BY` do log.
- ❌ Não registrar mais de uma prop deferida de uma vez: o cliente busca todas as deferidas
  no mesmo request, e a vista fechada passaria a custar.
- ❌ **Não mover a agregação do cofre pro controller** — o assert de LGPD daquele arquivo proíbe
  hash e caminho em qualquer método dele, e o duplicado agrupa por hash. Ver §5.2.
- ❌ **Não repetir o `where` por `business_id` no `CofreStatsReader`** — `arquivos` tem model e
  tem global scope; ali repetir esconde a quebra, ao contrário da trilha. O portão fail-closed da
  entrada **não** é o filtro: ele recusa perguntar, não filtra.
- ❌ Não pôr barra de progresso nos cards de disco enquanto não houver **quota configurada** — sem
  denominador ela inventa um teto. A do protótipo é `bytes / 5 GB`, número do mock.
- ❌ Não afirmar economia de disco a partir do duplicado sem olhar `caminhos`: o caminho é derivado
  do hash, então cópia do mesmo mês é o **mesmo** arquivo físico.
- ❌ Não declarar `retencao` no `Rule::in` do `tab` antes de a vista existir — a URL passaria a
  responder 200 caindo no acervo em silêncio.

## 12. Diagnóstico/Troubleshoot

| Sintoma | Causa provável |
|---|---|
| 403 com usuário admin | `arquivos.access` não marcada na função (Camada 3, `/roles/{id}/edit`) |
| Tela vazia com dados no banco | `business_id` da sessão diferente do dono das linhas — global scope funcionando |
| Skeleton eterno | a prop `acervo` (ou `trilha`) é `defer`; conferir se o partial reload não está pedindo `only:[]` sem ela |
| Trilha vazia com linhas no banco | o `business_id` da sessão não é o dono das linhas — ou não há sessão nenhuma, e aí o fail-closed devolve vazio de propósito (§9) |
| Trilha some ao paginar / volta pro acervo | o `tab` não viajou na query da paginação — ver `paraQueryTrilha` no `Index.tsx` |
| Chip de ação que você esperava não aparece | ele só existe se aquele business registrou aquele evento: os chips saem de `GROUP BY` do próprio log, não de uma lista fixa |
| Coluna "Quem" mostrando número em vez de nome | é o fallback do `COALESCE` — `users` do UltimatePOS não tem coluna `name`, e aquele usuário está sem `first_name`/`username`. Mesmo comportamento do `arquivos:audit-log` |
| Ação nova aparece com badge cinza | esperado: o `TOM_ACAO` mapeia as ações conhecidas e cai em `neutral` no resto, pra que a 3ª migration de enum não quebre a tela |
| Clicar num chip volta com erro de validação e não filtra | boolean cru na query. `qs.stringify({with_trashed:false})` gera `with_trashed=false`, e a regra `boolean` do Laravel **reprova a string `"false"`** (aceita `0/1/"0"/"1"` e os nativos). Normalizar pra `1 \| undefined` — é o que `paraNavegacao`/`paraQuery` fazem. Medido em 2026-08-25; era o estado do acervo desde o PR-1 |
| Cofre dizendo "não foi possível medir" | é o fail-closed: não há `business_id` na sessão (ou a tabela não existe neste ambiente). **Não** é acervo vazio — acervo vazio mostra a outra mensagem, e a distinção é de propósito |
| Cofre com card de disco que você não reconhece | esperado: os discos saem de `GROUP BY`, não de lista em PHP. Disco varia por ambiente (`local` em dev, `arquivos` no CT 100) e backfill pode ter gravado um terceiro |
| Cofre acusando duplicado que "não deveria existir" | é o achado funcionando: o `attach()` deduplica por hash dentro do business, então repetição veio de outro caminho — o backfill de NF-e insere direto. Olhe `caminhos`: 1 = registro repetido, mais de 1 = disco ocupado 2× |
| Vault com `cifrados` menor que `arquivos` | mesmo sinal do check #5 do `arquivos:health-check` — rodar `arquivos:reencrypt-vault`. Se o arquivo estiver acima do cap, ele vai continuar recusando: é o achado 1 da mesma tela |
| Achado mostrando só 5 arquivos | é o desenho: achado é sinal, não fila. A lista inteira é o Acervo. Os totais em cima do achado são o número real, não o tamanho da amostra |
| Prazo sem a lei ao lado | `sub_destination` fora de `retention_days_policy` — cai no `default` (90d). Se a coluna inteira vier vazia, o suspeito é o `mergeConfigFrom`: sem ele `config('arquivos.*')` é `null` e a política some (foi o bug de 2026-08-24) |

## 13. Refs

- Charter: [`Index.charter.md`](../../../resources/js/Pages/Arquivos/Index.charter.md)
- Casos: [`Index.casos.md`](../../../resources/js/Pages/Arquivos/Index.casos.md)
- SPEC: [`SPEC.md`](SPEC.md) US-ARQ-013
- ADRs: [0123](../../decisions/0123-modules-arquivos-backbone.md) · [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [0360](../../decisions/0360-deprecacao-admin-center-supersede-0122.md) · [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- Protótipo: `prototipo-ui/cowork/arquivos-page.jsx`
- Defer: [`RUNBOOK-inertia-defer-pattern.md`](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)
- Cofre — os comandos que AGEM sobre o que a vista aponta (ela só mostra):
  `arquivos:health-check` (os 5 sinais de integridade, incluindo os que a tela não cobre —
  arquivo sem file físico no disco e lag do audit log) · `arquivos:reencrypt-vault` ·
  `arquivos:dedupe-stats` (a taxa global, cross-business por desenho — ADR 0123 §3).
- Trilha — o outro leitor do mesmo log: `php artisan arquivos:audit-log --suspicious`
  ([`AuditLogCommand`](../../../Modules/Arquivos/Console/Commands/AuditLogCommand.php)).
  A tela **não** duplica os detectores dele (link assinado sem usuário, exclusão em série,
  rapid-fire do mesmo IP): ela mostra o log, ele varre padrão.
- Lane que prova: [`arquivos-pest.yml`](../../../.github/workflows/arquivos-pest.yml) —
  `ArquivosAdminControllerTest` já está na allowlist desde 2026-08-25.
