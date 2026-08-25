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

Este RUNBOOK cobre a onda 1 · **PR-1 (acervo)** e **PR-2 (trilha)**. As outras duas vistas do
charter chegam nos PRs seguintes.

**A barra de abas nasceu no PR-2**, com a segunda vista — não antes: aba que não leva a lugar
nenhum é promessa, não navegação. Ela navega por rota (`?tab=`), o vocabulário de URL que o
projeto já usa em Financeiro, Fiscal/Dfe e Cliente — não por estado local, senão o link não é
compartilhável e o botão voltar do navegador mente.

| Vista | PR | Estado |
|---|---|---|
| Acervo | PR-1 | entregue (2026-08-24) |
| Trilha (`arquivos_audit_log`, read-only) | PR-2 | **esta entrega** |
| Retenção (`summary()` + `preview()`, dry-run puro) | PR-3 | pendente — depende da decisão [W] na proposta `arquivos-retencao-ui-aviso-titular` |
| Cofre (health-check + dedupe + curador) | PR-4 | pendente |

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
  cronológica, sem nenhuma ação de linha.

Só a vista **aberta** é computada no servidor: o controller registra `Inertia::defer` para uma
prop ou para a outra, nunca as duas. `defer` adia a execução, mas o cliente busca **todas** as
props deferidas no segundo request — registrar as duas faria quem está na trilha pagar o
`paginate` do acervo.

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

Nenhum endpoint novo foi inventado — a regra 3 do pedido zero-toque é ligar o que existe.
A trilha não abriu rota, não abriu model e não abriu tabela: lê a que está lá desde a Sprint 1.

## 9. Multi-tenant + LGPD

- **Tier 0 (ADR 0093):** `business_id` vem da **sessão**, nunca do request — nas duas vistas.
  **Mas a defesa é oposta em cada uma, e confundi-las é o erro caro:**
  - **acervo** lê pelo model `Arquivo`, que tem global scope. Ali o controller **não** repete
    o `where` de propósito: duplicar esconderia uma quebra do scope.
  - **trilha** lê `arquivos_audit_log` via `DB::table` — **sem model, sem scope**. Ali o
    `where` explícito **é** a defesa, e removê-lo é vazamento cross-tenant.
- **Fail-closed na trilha:** sem `business_id` na sessão ela devolve vazio. O global scope do
  model faz `if ($businessId !== null)` e, sem sessão, deixa passar sem filtro — aposta que
  o `authorize()` da Request cobre lá, e que **não** se repete aqui.
- **Zero `withoutGlobalScopes`** em nenhum dos caminhos.
- **Sem PII na vista de governança (LGPD Art. 37):** `storage_path` e MD5 **não** saem do
  controller — o assert que defende isso passou a cobrir o arquivo INTEIRO no PR-2, não só o
  método da linha do acervo. Na trilha, o arquivo aparece como `#id`, nunca pelo nome.
  O **payload sai resumido**: ele é o conteúdo da auditoria (por que o link foi emitido, de
  qual IP foi consumido, que política apagou o quê) e é o mesmo que o `arquivos:audit-log` já
  mostra. Esta tela é o "controle de acesso" que o docblock do `Arquivo` cita: a permission
  `arquivos.access` nasce `false`.
- **`biz=4` (ROTA LIVRE) nunca em teste** — tenant fictício 98, adversário 99 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

## 10. Smoke check pós-deploy

1. `curl -sv https://oimpresso.com/arquivos 2>&1 | grep '^< HTTP'` → **302** pra login sem sessão.
2. Logado com `arquivos.access` → **200** e a tabela renderiza.
3. Logado **sem** a permission → **403**.
4. Screenshot 1280 e 1440 sem scroll horizontal — **nas duas abas**.
5. Conferir na tela: nenhum caminho de storage, nenhum MD5.
6. `/arquivos?tab=trilha` → **200**, aba Trilha ativa, eventos em ordem decrescente.
7. Clicar em Acervo e voltar em Trilha: a URL muda (`?tab=`), o botão voltar do navegador
   funciona, e a página 2 da trilha continua na trilha (o `tab` viaja na paginação).
8. Na trilha: **nenhum** botão de editar ou apagar linha, e nenhum nome de arquivo — só `#id`.
9. Ação inválida nunca dá 500, e os dois casos são **diferentes de propósito**:
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
- ❌ Não registrar as duas props deferidas de uma vez: o cliente busca todas as deferidas
  no mesmo request, e a vista fechada passaria a custar.

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
| Prazo sem a lei ao lado | `sub_destination` fora de `retention_days_policy` — cai no `default` (90d). Se a coluna inteira vier vazia, o suspeito é o `mergeConfigFrom`: sem ele `config('arquivos.*')` é `null` e a política some (foi o bug de 2026-08-24) |

## 13. Refs

- Charter: [`Index.charter.md`](../../../resources/js/Pages/Arquivos/Index.charter.md)
- Casos: [`Index.casos.md`](../../../resources/js/Pages/Arquivos/Index.casos.md)
- SPEC: [`SPEC.md`](SPEC.md) US-ARQ-013
- ADRs: [0123](../../decisions/0123-modules-arquivos-backbone.md) · [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [0360](../../decisions/0360-deprecacao-admin-center-supersede-0122.md) · [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- Protótipo: `prototipo-ui/cowork/arquivos-page.jsx`
- Defer: [`RUNBOOK-inertia-defer-pattern.md`](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)
- Trilha — o outro leitor do mesmo log: `php artisan arquivos:audit-log --suspicious`
  ([`AuditLogCommand`](../../../Modules/Arquivos/Console/Commands/AuditLogCommand.php)).
  A tela **não** duplica os detectores dele (link assinado sem usuário, exclusão em série,
  rapid-fire do mesmo IP): ela mostra o log, ele varre padrão.
- Lane que prova: [`arquivos-pest.yml`](../../../.github/workflows/arquivos-pest.yml) —
  `ArquivosAdminControllerTest` já está na allowlist desde 2026-08-25.
