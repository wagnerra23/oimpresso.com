---
page: /ia/memoria
component: resources/js/Pages/Jana/Memoria.tsx
owner: wagner
status: draft
last_validated: "2026-09-03"
parent_module: Jana
parent_adr: memory/decisions/0052-memoria-jana-3-angulos-faturamento.md
related_prototype: prototipo-ui/cowork/jana-merge.jsx
related_adrs: [31, 33, 35, 36, 37, 52, 61, 93, 94, 131]
related_charters:
  - resources/js/Pages/Jana/Chat.charter.md
related_us: [US-COPI-148]
related_runbook: memory/requisitos/Jana/RUNBOOK-memoria.md
related_casos:
  - resources/js/Pages/Jana/Memoria.casos.md
tier: A
charter_version: 4
permissao: jana.access
lgpd_sensitive: true
---

# Page Charter — `/ia/memoria`

> **Status:** `live` — implementada e em uso prod biz=1 desde 2026-04. Charter retroativo Wave M 2026-05-16.
>
> **LGPD-sensitive** — gestão de **fatos persistentes** sobre o business. Tudo aqui é PII-adjacent.

---

## Mission

Tela LGPD-first onde dono/gestor **vê, edita e apaga fatos** que a Jana lembrou sobre o business (`copiloto_memoria_facts`). Cumpre direito de acesso + retificação + esquecimento (LGPD Art. 18). Sem essa tela, memória vira black-box → quebra confiança + compliance.

Audiência primária: **dono/gestor do business** (Wagner, Larissa). Acesso `business_id` scoped strict — fato cross-tenant = bug Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).

---

## Goals

- Listar fatos com filtro por `categoria`, busca fulltext em `fato`, sort por `valid_from DESC`
- Editar fato inline (texto + categoria + relevância) com `activitylog` registrando autor/quando/motivo
- Apagar fato (soft delete `deleted_at`) com **confirmação inline na própria linha** ("Apagar é irreversível." · Apagar/Manter) — apaga embeddings Meilisearch async via job
- Mostrar `origem` do fato (chat / brief auto / inserção manual) — transparência
- Wagner como superadmin vê fatos cross-business via toggle `?escopo=plataforma` (audit log)

## Non-Goals

- ⛔ Bulk delete sem confirmação individual — LGPD exige consentimento granular
- ⛔ Export CSV de fatos PII sem audit log (futuro: `MemoriaController@export` com log obrigatório)
- ⛔ Insert manual de fato sem origem rastreável — toda criação registra `origem` e `user_id`
- ⛔ Mostrar fato de outro business mesmo pra superadmin sem flag explícita

## UX targets

- Render < 250ms p95 com `Inertia::defer()` em `fatos` paginated
- Empty state "Jana ainda não aprendeu nada sobre seu negócio" + CTA Chat
- Edit mode toggle inline (sem rota separada) — `useForm` Inertia
- Confirmação de delete **inline**, explicitando "Apagar é irreversível." ao lado do fato em questão
- Mobile responsivo — accordion por categoria

## Anti-hooks

- ⛔ Render texto fato sem `PiiRedactor` se contém CPF/CNPJ — Tier 0 LGPD
- ⛔ Update direto sem `activitylog` — quebra audit trail LGPD Art. 18
- ⛔ Forget físico (`forceDelete()`) sem job async — embeddings Meilisearch precisam expurgar consistente
- ⚠️ **A trava de escrita NÃO existe** — gap declarado, não anti-hook: ver §Gap de permissão.

## Gap de permissão — ABERTO, decisão [W]

Até a v2 isto era um Anti-hook: *"⛔ Permitir edit por user sem permissão `copiloto.memoria.manage`"*.
**Medido em 2026-09-02: a trava nunca existiu, e a key também não.**

| o que o charter afirmava | o que foi medido |
|---|---|
| a key `copiloto.memoria.manage` | **não existe** — `Modules/Jana/Resources/permissions.php` tem **22 keys, todas `jana.*`**; os 2 únicos hits do repo estavam dentro deste charter |
| que "editar sem ela" seria barrado | o `MemoriaController` (que mora em `Modules/KB`) **não checa permissão nenhuma** — a única defesa é o `can:jana.access` do grupo `/ia` ([`routes.php:50`](../../../../Modules/Jana/Http/routes.php)) |

**Por que `jana.mcp.memory.manage` NÃO serve como substituta** — é a candidata óbvia (única key
com "memory" no nome) e a que a [emenda do Cowork de 2026-08-27](../../../../prototipo-ui/design-docs/cowork-inbox/JANA-CASOS-EMENDA-PERMISSAO-2026-08-27.md)
assumiu. Ela é de **outro acervo**:

| eixo | `jana.mcp.memory.manage` | esta tela |
|---|---|---|
| acervo | `mcp_memory_documents` (Modules/**Forja**) — docs do git sincronizados | `jana_memoria_facts` (Modules/**Jana**) — fatos sobre o negócio |
| escopo declarado | `business_required: false` · `admin_only: true` (`McpScopesSeeder:170-178`) | business-scoped strict (ver Mission) |
| audiência | *"Wagner/superadmin v1"* — descrição literal do scope | dono/gestor do business |
| uso vivo hoje | gate coarse do **módulo KB inteiro** (8 controllers + 6 FormRequests, contados) | — |

Reusá-la acoplaria *"corrigir um fato errado da Jana"* a *"acesso ao KB de governança inteiro"*, e
poria uma permissão `admin_only` de plataforma como pré-requisito de um direito LGPD do titular
dentro do próprio tenant.

**Criar key nova é token novo = decisão [W].** Proposta: `jana.memoria.manage`, risk `critical`,
`requires: ['jana.access']`, aplicada em `PATCH`/`DELETE /ia/memoria/{id}` — o `GET` fica sob
`jana.access`, porque *ver* é o direito de acesso (Art. 18) e *corrigir/apagar* é escrita.

⚠️ **Enquanto [W] não decide, esta tela não tem trava de escrita**: quem tem `jana.access` edita e
apaga. O `UC-MEM-08` ([`Memoria.casos.md`](Memoria.casos.md)) **trava esse limite** — quando a trava
existir, ele quebra, e é esse o sinal pra atualizar este charter (e promover o `UC-JPERM-07`, que
segue ⬜ de propósito).

---

## Skills relevantes

`brief-first` (Tier A) · `multi-tenant-patterns` (Tier A) · `jana-recall-flow` (Tier B) · `jana-arch` (Tier B) · `commit-discipline` (Tier A — PII em commit nunca)

## Charter version log

- v1 (2026-05-16) — Charter retroativo Wave M boost Modules/Jana 64→78
- **v2 (2026-08-07)** — O Goal do `motivo` e o Anti-hook do `activitylog` **passaram a valer**:
  até aqui o charter mandava registrar "autor/quando/motivo" e o código validava só `fato`
  (0 hits de `motivo` na Page, medido). Agora o servidor rejeita edição sem motivo e a trilha
  sai em `activity_log` (`jana_memoria_fato_editado` / `_esquecido`), com o motivo redigido por
  `PiiRedactor`. Defendido por `MemoriaEdicaoMotivoTest` (UC-MEM-01..05) na lane `jana-pest.yml`.
  Junto: nasceram o [RUNBOOK](../../../../memory/requisitos/Jana/RUNBOOK-memoria.md) (a tela estava
  live desde 2026-04 **sem** F1 PLAN) e o [`Memoria.casos.md`](Memoria.casos.md), fechando o trio.
  URL corrigida `/copiloto/memoria` → `/ia/memoria` (a rota migrou em duas fases; o charter ficou
  no prefixo antigo). **Removidos os ids fantasma** `US-COPI-MEM-005/008/012` do `related_specs`:
  não existem no SPEC da Jana (0 hits, medido) — mesmo padrão do `US-JANA-PAINEL-001` que a onda 1
  da US-COPI-148 pegou. **Errata do próprio autor (mesma sessão):** a 1ª redação dizia "nada foi
  posto no lugar — inventar id pra satisfazer lint seria teatro". Isso confundia duas perguntas
  diferentes. O `related_us` mede o **join US→tela** ("User Stories que esta tela atende", per o
  schema), não "qual US implementa esta mudança". A `US-COPI-148` atende esta tela de fato: é dona
  da aba Memória (renomeou o vocabulário na onda 2) e o DoD dela pede "charters fundidos com
  `casos.md` por aba" — que é exatamente o `Memoria.casos.md` criado aqui. Linkar é honesto;
  o que seria teatro é inventar um id inexistente, e não é o caso.

- **v3 (2026-09-02)** — **A permissão deste charter era fantasma.** O frontmatter declarava
  `permissao: copiloto.memoria.manage` e um Anti-hook proibia editar sem ela; a key **não existe**
  (22 keys no registry, todas `jana.*`) e o Controller não checa permissão nenhuma. Corrigido pro
  gate REAL (`jana.access`) e o Anti-hook virou §Gap de permissão — **declarar o buraco vale mais
  que prometer uma trava que não existe**, porque a promessa ensina o próximo executor que a tela
  está protegida. O gap #24 do [AUDIT-GAPS](../../../../memory/requisitos/Jana/AUDIT-GAPS-2026-08-10.md).
  Qual key trava a escrita é decisão [W]; o limite atual ficou travado por `UC-MEM-07`/`UC-MEM-08`
  ([`MemoriaPermissaoTest`](../../../../Modules/Jana/Tests/Feature/Http/MemoriaPermissaoTest.php),
  na allowlist da lane `jana-pest.yml`).
  ⚠️ **Fonte a montante NÃO consertada, e é de propósito:** o `Modules/Jana/module.json` afirma
  *"URL/permissions/config keys mantêm prefixo legacy `copiloto.*`"* — falso em 2 de 3 (gap #31), e
  a origem plausível desta e da key fantasma do `Index.charter.md`. Fica fora deste PR porque a
  `description` é propagada pra `memory/modulos/Jana.md` por gerador com guarda, e a mesma frase
  está em `Modules/Ponto/module.json` — consertar 1 de N à mão é outro intent.

- **v4 (2026-09-03)** — **O `AlertDialog` deste charter era o PERDEDOR, e a onda 4 tocou a região.**
  O charter pedia `AlertDialog` em dois pontos (Goal do apagar + UX target); a tela usa confirmação
  **inline na própria linha** desde sempre, por decisão registrada no `Memoria.tsx` (o `confirm()`
  nativo sai do fluxo, é bloqueante e não diz *qual* fato) e já contratada no
  [`Memoria.casos.md`](Memoria.casos.md). Pela regra de precedência (`teste verde > casos > charter
  > SPEC`, [proibicoes.md](../../../../memory/proibicoes.md)), o charter perde — e a mesma regra
  manda **corrigir o perdedor no MESMO PR** que alguém tocar a região. A onda 4 reescreveu
  exatamente essa região (as ações da linha), então a correção sai aqui em vez de virar dívida.
  O [`Memoria-visual-comparison.md`](../../../../memory/requisitos/Jana/Memoria-visual-comparison.md)
  §R7 já apontava o charter como perdedor desde 2026-08-17 e dizia "fica registrado, não mexido
  nesta leva" — esta é a leva.

  **Junto, a onda 4 da paridade** (forma da linha do fato + largura), com dois UCs novos e teste
  que os cita: `UC-MEM-09` (ações em TEXTO, não ícone mudo) e `UC-MEM-10` (o fato antes da meta),
  em `tests/jana-memoria-linha.test.tsx`, ligados à lane `jana-conversas-gate.yml` — que é o home
  declarado dos specs jsdom da Jana. Os deltas medidos contra a âncora (raio 8px × 10px, padding
  12px × 11/13, corpo 13.5px × 13px) estão tabelados no `casos.md`, não escondidos.

  ⚠️ **NÃO mexido aqui, e de propósito:** o §Gap de permissão segue aberto (decisão [W]), e o
  `status: draft` do frontmatter × `live` do corpo segue por resolver — promover é ato [W].

> ⚠️ **Divergência de status não resolvida aqui:** o frontmatter diz `draft`, o corpo diz `live`
> ("em uso prod biz=1 desde 2026-04"). Promover `draft→live` é decisão [W], não do agente — fica
> registrado, não mexido.
