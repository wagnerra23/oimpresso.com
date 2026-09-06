---
date: "2026-09-06"
topic: "Refutação GT-G5 do lote PR #6926 — 8 gap.md + 8 map.json do Essentials (documents · todo · messages · reminders · knowledge · holidays · settings · tipos) (veredito: reprovado)"
authors: ["C"]
prs: [6926]
outcomes:
  - "534 itens verificados · 15 erros confirmados · error_rate 2,81% (≥2%) → lote REPROVADO"
  - "Os 4 erros do lote irmão (#6917) foram de fato corrigidos em grau alto: citações deslocadas caíram de 18/110 para 5/197; ranges de map de 10/44 para 2/84; ZERO linha de tabela quebrada por pipe; acao == tabela em 55/55"
  - "O erro que sobra é de OUTRA classe — sonda que responde a pergunta errada (§5 2026-08-13): `Todo/Index.tsx` TEM filtro de período (`start_date`/`end_date`, :267-283 + `ToDoController.php:106-108`) e o lote o declara AUSENTE porque mediu `date_from`/`date_to`/`periodo`; carimba Decidir. e orça '2 campos + range' num controller que nem existe com esse nome"
  - "Claims de ausência com número falso: `grid`=1 e `dia`=4 em Reminders (lote diz 0 de cada); '31 contratos ativos' (são 28 pelo critério do próprio gate; 31 é o nº de entradas do diretório, incl. schema + intent); 'a lista do vivo não tem lastro escrito' (o charter do Reminders tem Non-Goal literal defendendo a lista)"
  - "Ação × canon: Decidir. sobre paridade existente (filtro de período) e Ação do drawer de Tarefas sustentada em citação FALSA do charter (os 3 itens fora de escopo são OS/cliente · versionamento · notificação — não 'comentário, documentos da tarefa')"
  - "Âncoras data-contract (3/3), prototipo_sha (8/8 não-stale, regeneração idêntica), --strict rc=0, frontmatter, ids, state files (sha 16/16) e scan PII: 0 erros"
---

# Refutação GT-G5 — lote PR #6926 (`claude/gap-essentials-8-telas`)

> Protocolo: [`PROTOCOLO-REFUTADOR-BACKFILL.md`](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2/§3/§4.
> Base medida: `origin/main` = `c1292448ee` · merge-base do branch = `ad376ed239` (main está 10 commits à frente; nenhum deles toca `prototipo-ui/cowork/`, `resources/js/Pages/Essentials/`, `cowork-inbox/{essenciais,hrm}` nem `prototipo-ui/contrato/` — `git diff --stat ad376ed239 origin/main -- <paths>` vazio) · HEAD do lote = `58e21c116f` · repo **não** raso (`git rev-parse --is-shallow-repository` = false).
> Refutador: Claude (Fable 5.1) em sessão fresca, worktree `heuristic-bose-8ee418`, sem contexto do gerador. ⚠️ O worktree estava com o HEAD **no próprio commit do lote**; os 12 arquivos "vivo"/"protótipo" foram exportados de `origin/main` via `MSYS_NO_PATHCONV=1 git show origin/main:<path>` para o scratchpad e conferidos por `git hash-object` = `git rev-parse origin/main:<path>` (12/12 idênticos ao disco — o branch não toca nenhum deles, diff vazio).
> Calibração: li [`6897`](2026-09-06-refutacao-gt-g5-lote-6897.md) e [`6917`](2026-09-06-refutacao-gt-g5-lote-6917.md) inteiros e procurei ativamente os 4 erros do lote irmão (citação deslocada · range em outro bloco · pipe em code-span · Nada sobre decisão pendente).

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador — refutador `fable`; gerador assinado `[C]` no commit (o ledger preenche)
- [x] Amostra: 100% anchors (tipo `anchors`) — sem prosa destilada, sem seleção aleatória, logo sem seed
- [x] Cada item verificado contra `origin/main` (`git show`, `git ls-tree`, `sed -n` sobre as cópias de main; canon lido inteiro), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII nas 1.412 linhas `+` do diff, 7 padrões, cada um com controle positivo — 0 hits
- [x] `error_rate_pct` calculado: **2,81%** (≥ 2 → reprovado)
- [ ] Entry no ledger `governance/sdd-verification-ledger.json` — **não escrita por mim** (mandato: só este arquivo); o parent adiciona a entry apontando pra este path

## Escopo real do diff (medido)

`git diff --stat origin/main...HEAD` = **18 arquivos** (+1412 −39): 16 novos em `memory/requisitos/Essentials/` (8 `*-gap.md` + 8 `*.map.json`) + 2 modificados em `scripts/design-sync/state/` (`application-report.json`: 8 entries Essentials `anchored→compared`, contadores 59→51 / 7→15; `applications.json`: +8 entries com `mapSha256`/`targetSha256`). Confere com o enunciado. Partes por mapa: 8 · 8 · 7 · 8 · 5 · 6 · 6 · 7 = **55**, batendo com as linhas das 8 tabelas.

## Tabela por grupo

| # | Grupo | Itens | Erros | Como mediu |
|---|---|---|---|---|
| 1 | Citações `arquivo:linha` nos 8 gap.md (Documents 31 · Todo 32 · Messages 21 · Reminders 25 · Knowledge 30 · Holidays 25 · Settings 20 · Tipos 13) | 197 | **5** | `sed -n '<n>p'` sobre as cópias de `origin/main`; `:NNN` resolvido pelo contexto textual (Vivo/Protótipo), não por proximidade |
| 2 | Claims de ausência, contagens e afirmações substantivas (a)(b)(c) | 36 | **5** | `rg --hidden -g '!.git/**'` com rc lido e controle positivo; `ancora.mjs --list`; `Routes/web.php`; `git ls-files` |
| 3 | Coluna **Ação** × canon (55 partes) | 55 | **2** | LEIA-ME + 6 charters do intake + 5 contratos + `essentials-tipos.contract.json` + playbook 03/07/08 + 8 charters vivos, lidos inteiros |
| 4 | Os 8 `.map.json`: schema (8) · `prototipo.arquivo` (8) · `vivo.arquivo` (8) · 3 `vivo.ancora` · `prototipo_sha` (8) · `--strict` (1) · ranges proto (46) · ranges vivo (38) · `acao` == tabela (55) | 175 | **3** | `design-code-map-check --check --strict` · `gerar-map.mjs` regenerado e diffado · `grep data-contract` · `sed -n` em cada range |
| 5 | Integridade das 8 tabelas (`awk` contando pipes por linha, `FNR`) | 8 | 0 | controle positivo: linha sintética com pipe em code-span → `pipes=5` detectado |
| 6 | Scan PII + BRL (7 padrões) nas linhas `+` | 7 | 0 | grep com controle positivo cada |
| 7 | Frontmatter: `fmVal` (8) · js-yaml estrito (8) · `tela_viva` existe (8) · `prototipo` existe (8) · `id` sem colisão (8) | 40 | 0 | `fmVal` de `gerar-contrato.mjs:36` · js-yaml · `doc-id-index --check-collisions` (0 em 2608) |
| 8 | State files: `applications.json` `mapSha256`/`targetSha256` (8×2) recomputados | 16 | 0 | sha256 do arquivo no disco == registrado, 16/16; report 8/8 `compared` |
| | **Total** | **534** | **15** | **error_rate = 2,81%** |

## Grupo 1 — REFUTADOS (5 de 197)

Régua igual à do #6917: a linha citada tem de conter o que a prosa diz que ela contém; linha ao lado ou range que deixa de fora parte do que descreve = erro. Ranges que só sobram 1-2 linhas num bloco vizinho (fecho, banner) ficam como **ressalva**, não erro — o critério é "aponta pra OUTRO bloco / perde conteúdo descrito", não "±1 na borda".

| Gap · citação | O que está na linha (origin/main) | Onde está de fato |
|---|---|---|
| `todo-index-gap.md:34` — *"o protótipo distingue os dois (`:174` traz a frase longa de onboarding citando `essentials.assign_todos`)"* | `essenciais-page.jsx:174` = `{Object.entries(E.PRIO).map(...)}` (o `<select>` de Prioridade da toolbar) | `:206` (o `desc` do `<Vazio variante="first">`). O **map** da mesma parte acerta (`203-206`) — dois processos, um mediu, o outro não |
| `documents-index-gap.md:30` — *"Vivo: `:250` alterna Memos/Documentos no cabeçalho e `:286` alterna a descrição"* | `Documents/Index.tsx:286-288` = o ternário do **estado vazio** (*"Nenhum memo criado ainda." / "Nenhum arquivo enviado ainda."*) | a descrição do cabeçalho alterna em `:252-256` (*"Avisos e memorandos internos em texto." / "Arquivos compartilhados com a equipe."*). O estado vazio tem linha própria na tabela (map `286-292`) |
| `reminders-index-gap.md:32` — *"`fOrig` filtrando `l.origem` (`:449`, `:457`)"* | `:457` = `const dias = new Date(ano, mes + 1, 0).getDate();` | o filtro `visiveis = lemb.filter((l) => !fOrig \|\| l.origem === fOrig)` está em `:459` |
| `knowledge-index-gap.md:28` — *"`aside.ess-kb-nav` (`:148`) com os 3 níveis em `:152-157`"* | `essenciais-extras.jsx:152` já é o `.map((s) =>` das **seções**; o range cobre seção (`:154`) e artigo (`:156`) | o botão da **categoria** (1º nível) está em `:151`, fora do range — mesma família do `:107-111` que pulava `Matrícula` no #6917 |
| `settings-index-gap.md:33` — *"As 10 chaves do protótipo (`hrm-extras.jsx:572-591`)"* | `:572-591` vai do `<div className="hrm-campos">` do card Licenças até a abertura do card Regras — contém **8** chaves (`:573-574`, `:579-580`, `:585-588`) | `is_location_required` (`:593`) e `calculate_sales_target_commission_without_tax` (`:594`) ficam **fora**. As 10 chaves ocupam `:573-594` |

**Ressalvas não contadas (registradas):** regiões que terminam no banner/comentário do bloco seguinte — `FormArquivo :401-443` (fecha em `:441`; `:443` é o banner LEMBRETES), `FormLembrete :538-564` (`:562`/`:564` banner MENSAGENS), `Mensagens :565-622` (`:620`/`:622` banner SHELL), `BaseConhecimento :127-188` (`:186`/`:188` comentário Config); `todo :218` cita "Concluir/Excluir" e `:218` só tem Excluir (Concluir `:217`); `reminders :509` é o rótulo do evento, não a célula do dia (`:500-505`); os "AlertDialog de exclusão" citados em `Todo :27-45`, `Reminders :14-21` e `Holidays :23-30` apontam **imports**, não o uso (`Holidays :383-395`); `documents :259` é o guard `tab === 'documents'`, o botão está em `:260`. Nenhuma dessas aponta pra outro bloco.

## Grupo 2 — claims de ausência e contagens (36 itens · 5 REFUTADOS)

Todas re-rodadas com `rg --hidden -g '!.git/**'`, exit code lido (rc=1 = não achou; rc≠0/1 = falha) e controle positivo no mesmo arquivo antes de aceitar zero.

| Claim do lote | Medido | Veredito |
|---|---|---|
| `Checkbox` / `onCheckedChange` em `Todo/Index.tsx` = 0 | 0, rc=1 (controle: `Select` = 34) | ✅ (2) |
| `search` / `busca` / `filtro` / `Input` em `Knowledge/Index.tsx` = 0 | 0, rc=1 nos 4 | ✅ (4) |
| `KpiCard` / `KpiGrid` / `sort` em `Holidays/Index.tsx` = 0 | 0, rc=1 nos 3 | ✅ (3) |
| `grid` / `calend` / `cal-g` / `dia` em `Reminders/Index.tsx` = **"0 ocorrência de cada um"** | `calend`=0 · `cal-g`=0 ✅ · **`grid`=1** (`:207` `className="grid grid-cols-1 md:grid-cols-3"` no form do Dialog) · **`dia`=4** (`:22` alert-**dia**log · `:32` **dia**log · `:77` `dialogOpen` · `:188` `<Dialog`) | ❌❌ (2 de 4) — a claim substantiva (nenhum calendário) é verdadeira; os **números** são falsos como escritos (§5 2026-07-17: número sem comando ao lado) |
| `origem` / `origin` em `Reminders/Index.tsx` = 0 | 0, rc=1 | ✅ (2) |
| `date_from` / `date_to` / `periodo` em `Todo/Index.tsx` = 0 | 0, rc=1 nos 3 — **literalmente verdadeiro** | ✅ (3) |
| **"Filtro por período de início — AUSENTE. No vivo, os 5 filtros são de enum/pessoa"** e *"`:228` … 5 selects em grid"* | `Todo/Index.tsx:267-283`: `<Input id="start_date" type="date">` (rótulo **De**) + `<Input id="end_date" type="date">` (**Até**); `activeFilters` inclui `filtros.start_date \|\| filtros.end_date` (`:190-191`); `ToDoController.php:106-108` aplica `whereDate('date', '>=' start_date) … '<=' end_date`. A grid `md:grid-cols-5` tem **3** `<Select>` + **2** `<Input type="date">` — não "5 selects" | ❌ **sonda respondeu a pergunta errada** (§5 2026-08-13): mediu os nomes do protótipo (`de`/`ate`/"periodo") e não os do vivo (`start_date`/`end_date`, 8 hits). A capacidade **existe** |
| `Atrasada` em `Todo/Index.tsx` = 0 | 0, rc=1 | ✅ |
| `Marcar tudo como lido` em `Messages/Index.tsx` = 0 | 0, rc=1 (controle: `Enviar` = 1) | ✅ |
| `estimated_hours` / `assigned_by` só na interface TS, 2 hits em `:65-66` | `rg -n` → exatamente `:65` e `:66`, nada no JSX | ✅ |
| `related_prototype` presente em 4 charters (Documents · Messages · Todo · Settings) e ausente em 3 (Knowledge · Reminders · Holidays); Tipos com valor direto | frontmatter dos 8 em `origin/main`: 4× `n/a (...)`, 3× sem a chave, Tipos = `prototipo-ui/cowork/hrm-page.jsx (subview "tipos" · copy literal)` | ✅ (2) |
| **"os 31 contratos ativos incluem `essentials-tipos`, `essentials-licencas` e `essentials-metas` — nenhum dos 5 dos essenciais"** (repetido em 4 gap.md) | `ls prototipo-ui/contrato/` = **31 entradas**, das quais `contract.schema.json` e `financeiro-unificado.intent.json` **não são contratos** e `EXEMPLO.contract.json` é exemplo; `*.contract.json` = 29; critério do próprio gate (`scripts/contrato-de-tela.mjs:437` `ativo = git ls-files "*.contract.json"` excl. EXEMPLO e design-docs) = **28** | ❌ 31 é contagem de entradas do diretório, chamada de "contratos ativos" (1 claim, 4 sites). A parte substantiva — os 3 `essentials-*` estão lá e os 5 dos essenciais não — é verdadeira |
| (c) os 5 contratos dos essenciais vivem em `cowork-inbox/essenciais/contrato/` e não em `prototipo-ui/contrato/` | `ls` dos dois diretórios: 5/5 lá, 0/5 aqui | ✅ |
| `/essentials/settings` não está nas rotas | `Modules/Essentials/Routes/web.php:66` `Route::get('/settings', …)` está dentro de `Route::prefix('hrm')` (`:58`) → `/hrm/settings`; o grupo `prefix('essentials')` (`:7`) não tem `settings` (controle: `leave-type` = 1) | ✅ |
| `ancora.mjs --list`: `/essentials/reminder → essenciais-page.jsx`; `/essentials/knowledge-base/{id}` = "tela de detalhe bespoke … não segue um dos 5 Padrões" | saída literal do comando | ✅ (2) |
| **Reminders: "não há artefato do lado do vivo defendendo a lista … a lista do vivo não tem lastro escrito"** | `Reminders/Index.charter.md` §Non-Goals: *"❌ NÃO renderiza calendário full-month grid (**decisão UX: listagem é mais prática diário**)"*. O frontmatter de fato não tem `related_prototype` (essa metade é verdadeira), mas o charter **tem** lastro escrito pela lista | ❌ claim de ausência refutada pelo próprio charter que o gap diz ter medido |
| (a) `hrm-extras.jsx::Config` tem 10 campos (não 12) e as 10 chaves estão em `Settings/Index.tsx:22-31` | `Campo`/`U.Texto`/`flag` em `:573 :574 :579 :580 :585 :586 :587 :588 :593 :594` = **10**; `interface Settings` `:22-31` = as mesmas 10, na ordem citada | ✅ — a errata ao playbook 07 (*"12 campos"*, l.13) procede |
| (b) dois `Config` (`essenciais-extras.jsx:189` e `hrm-extras.jsx:553`); nota *"Uma configuração, dois lugares"* em `essenciais-extras.jsx:223` | `grep -n "function Config"` → exatamente essas 2 linhas; `:223` = `<Nota tone="info" title="Uma configuração, dois lugares">` | ✅ (2) |
| `Enviar` presente no vivo (1 ocorrência) · `created_at_human` renderizado como sub-linha `:320-322` · `hrm-page.jsx:2` "Espelha o topnav de nav_hrm.blade" | 1 · `:322` `criada {t.created_at_human}` · literal | ✅ (3) |

**Ressalva não contada:** *"das 11 medidas"* (Reminders) e *"Única das 11 telas"* (Tipos) — o lote tem 8 telas e o report tem 8 entries Essentials; o denominador 11 não vem com comando (pode ser 8 + Licenças + Metas + Ponto, mas isso é adivinhar).

## Grupo 3 — coluna Ação × canon (55 partes · 2 REFUTADOS)

Procurei as duas direções: *Decidir.* sobre decisão fechada e *Nada* sobre gap real. O gerador leu a coluna de veredito desta vez — os "Nada — decidido" (Settings/permitir-marcação-web ← thread 07 §3(i)), "Nada — layout" (Holidays/KPIs, Settings/cards ← régua do playbook 08 l.18) e os 7 "Nada" do Tipos (contrato + playbook 03 `nao_toca` + `_pendente_w`) batem com o canon. Os 2 erros:

| Tela · parte | Ação no lote | Canon / vivo | Veredito |
|---|---|---|---|
| Todo · **Filtro por período de início** | **Decidir.** *"É comportamento (filtrar) … Custo: 2 campos + range no `EssentialsTodoController` — não é de passagem."* | O vivo **já tem** os 2 campos (`Todo/Index.tsx:267-283`) e o range no controller (`ToDoController.php:106-108`). O contrato `tarefas` lista `filtros-periodo` como seção — e ela existe. Bônus: `EssentialsTodoController` **não existe** em `origin/main` (0 hits em `git ls-tree -r`); o controller é `ToDoController.php` | ❌ **Decidir. sobre paridade existente** — a inversão exata do erro do #6917 (Nada sobre pendente). Ressalva menor: o vivo só aplica o range quando **ambos** os campos vêm preenchidos (`filled('start_date') && filled('end_date')`), o protótipo aceita um só (`de \|\| ate`) — isso seria um "Decidir" honesto, mas não é o que a célula diz |
| Todo · **Drawer de detalhe** | **Decidir.** *"O charter do intake liga o detalhe aos 3 itens fora de escopo que aguardam [W] (**comentário, documentos da tarefa, vínculo com OS**). Abrir o drawer sem eles entrega uma casca"* | `Tarefas.charter.md` §Fora de escopo lista **vínculo tarefa ↔ OS/cliente · versionamento de documento · canal de notificação**. Comentários e documentos da tarefa estão **dentro** do escopo (§Seções 5: *"Drawer de detalhe: situação · prazo · descrição · histórico · documentos · comentários"*). E o vivo tem `Todo/Show.tsx` (rota `/essentials/todo/{id}`, linkada em `Index.tsx:317`; `ancora --list`: *"tabs comentários/anexos/atividades"*) — a célula "nenhum painel de detalhe" descreve só o Index | ❌ Ação sustentada em **citação falsa do canon** (2 dos 3 itens inventados) e com o vivo à frente omitido. "Decidir" (drawer × página) até é defensável; a justificativa não |

Aceitos com nota: Reminders/forma — *Decidir.* é coerente com UI-0029 (eixo FORMA: protótipo > charter), mas a premissa *"não há artefato defendendo a lista"* é falsa (contada no Grupo 2); Settings/guarda-de-permissão — o charter vivo já documenta `authorizeAdmin($businessId)`, então o "medir o controller" é redundante, mas o Decidir (mensagem com motivo) segue válido; Holidays/colunas — Decidir por causa da coluna `Dias` acoplada à ordenação, aceito.

## Grupo 4 — os 8 `.map.json` (175 itens · 3 REFUTADOS)

**O que confere (0 erros):** schema válido nos 8 (`--check --strict` **rc=0**, sem DRIFT nem stale em nenhum dos 28 maps do repo); `prototipo.arquivo` (4 fontes, blobs em `origin/main`; `n/a` nas 7 partes do Tipos e em 2 partes sem lado proto — legítimo); `vivo.arquivo` (8 `.tsx` em main); **as 3 âncoras do Tipos existem** como `data-contract` (`cabecalho` `:135` · `toolbar` `:149` · `lista` `:169`; `grep -c` = 1 cada); `prototipo_sha` **não-stale** nos 8 (regeneração com `gerar-map.mjs` devolve os mesmos 4 valores — `3e0b0aa398f8` ×4 · `86cb09835456` · `1b1cc5c4264f` ×2 · `aa114049dd5b`); **`acao`/`status`/`_acionavel` == tabela em 55/55** (a regeneração não diverge em nenhum campo derivado — o erro nº3 do #6917 não reincidiu). Nota: `gerar-map.mjs` avisa em 4 dos 8 que *"a âncora computada do charter não cita <proto>"* — é o `related_prototype: n/a` que os gap.md explicam (coexistência por desenho, §5 2026-08-28); não é drift.

**Ranges (os erros):**

| Map · parte | Lado | Range | O que cobre | Onde está de fato |
|---|---|---|---|---|
| settings · `cobertura-das-chaves-de-configuracao` | proto | `572-591` | 8 das 10 chaves | `:573-594` (faltam `:593-594`) — o mesmo erro da citação do Grupo 1, agora no artefato que a Fase 4 consome |
| settings · `chave-de-presenca-dentro-do-hrm` | proto | `583-589` | o card Tolerância (`grace_*` ×4, `:585-588`) | a 5ª chave de presença que a própria célula nomeia, `is_location_required`, está em `:593` (card Regras) |
| todo · `filtro-por-periodo-de-inicio` | vivo | `n/a` / `n/a` | — | `Todo/Index.tsx:267-283` — `vivo.arquivo: n/a` afirma ausência de um bloco que existe; `status: decidir-w` herda o erro |

Ressalvas não contadas (contêm o descrito, sobram 1-3 linhas num bloco vizinho): documents `botao-de-criacao` vivo `259-272` (entra 2 linhas nas Tabs), `estado-vazio` vivo `286-292` (entra no `<table>`), `colunas` proto `352-355` (`:355` é `const acoes`), `formulario-de-upload` proto `401-443` (banner); messages `compositor` proto `607-616` (`:616` é `</Card>`); reminders `navegacao-de-mes` proto `485-490` (`:490` é o `<select>` de origem), `forma-da-tela` vivo `146-190` (o Card fecha em `:185`; `:188-190` já é o Dialog), `criar-editar-excluir` proto `538-564` (banner); settings `agrupamento-em-cards` proto `571-591` (cobre as 4 aberturas de card, não o corpo do último). E uma inconsistência de **derivação**, não do lote: `holidays.kpis-do-topo` sai `status: "vivo-a-frente"` e `settings.agrupamento-em-cards` sai `"paridade"` para o mesmo texto de Ação *"Nada — layout, e a régua…"* — o protótipo é quem tem os KPIs, então "vivo-a-frente" está semanticamente errado, mas é o `gerar-map.mjs` que carimba.

## Grupo 5 — tabelas (8 · 0 erros)

`awk '/^\|/{n=gsub(/\|/,"|"); if(n!=4) print FILENAME":"FNR" pipes="n}' memory/requisitos/Essentials/*-gap.md` → **vazio, rc=0**. Controle positivo: arquivo sintético com `` `x|y` `` numa célula → `pipes=5` impresso. O erro nº3 do #6917 (pipe em code-span quebrando a coluna Ação) **não reincidiu** — os 8 arquivos escapam ou evitam `|` dentro de crase.

## Grupo 6 — scan PII + BRL (0 hits)

Base: `git diff origin/main...HEAD | grep '^+'` → **1.412 linhas**. Controle positivo = arquivo sintético com um exemplar de cada formato (não reproduzido aqui — o scan do CI é cego a contexto).

| Padrão | Hits | Controle |
|---|---|---|
| CPF pontuado · CPF cru 11 dígitos · CNPJ · e-mail · cifrão seguido de dígito · nomes (Larissa · Martinho · ROTA LIVRE · WR2 · Eliana) | 0 (rc=1 em todos) | 1 cada |
| Telefone BR | **5 falsos-positivos** (rc=0) — todos runs de dígitos dentro de `targetSha256`/`mapSha256` hex nos 2 state files (mesmo FP do #6917); 0 telefones | 2 |

## Grupo 7 — frontmatter (40 · 0 erros)

Os 8 frontmatters parseiam por `fmVal` (`gerar-contrato.mjs:36`, regex `^key:\s*(.+)$` com `im`) **e** por js-yaml estrito; `tela_viva` e `prototipo` apontam paths existentes (16/16, no disco e em `origin/main`); `id: requisitos-essentials-*-gap` ×8 → `doc-id-index.mjs --check-collisions` **0 colisão em 2608 ids**; sem CRLF. Ressalva idêntica ao #6917: `gerado_em: 2026-09-06` sem aspas → js-yaml devolve `Date`, não string (`fmVal` não se importa; `memory-schema-preflight` manda datas entre aspas; não há schema de gap.md fiado a gate).

## Grupo 8 — state files (16 · 0 erros)

`applications.json`: as 8 entries Essentials têm `mapSha256` == sha256 do `.map.json` no disco e `targetSha256` == sha256 do `.tsx` (16/16). `application-report.json`: 8/8 Essentials em `lifecycleState: compared`; contadores `anchored 59→51`, `compared 7→15` coerentes com +8.

## Padrão do erro (o que mudou vs. o irmão, e o que sobrou)

1. **Os 4 erros do #6917 foram corrigidos de verdade.** Citações deslocadas: 18/110 → **5/197**; ranges em outro bloco: 10/44 → **2/84**; pipe em code-span: 1 → **0**; `acao` contaminado: 1 → **0**. O autor aprendeu a medir a linha — a maioria das 197 citações confere ao número.
2. **O que sobrou é de outra classe: a sonda que responde a pergunta errada (§5 2026-08-13).** O caso mais caro é o filtro de período do Todo: o gerador mediu `date_from`/`date_to`/`periodo` (vocabulário do protótipo) num arquivo que usa `start_date`/`end_date`, obteve 0, e a partir do 0 fabricou *ausência*, *Decidir.*, *custo* e um *controller que não existe*. Mesma família: contar `grid`/`dia` como "0 de cada" sem olhar o resultado (1 e 4), chamar 31 entradas de diretório de "31 contratos ativos" (28 pelo critério do gate), e afirmar "sem lastro escrito" tendo aberto o charter que carrega o lastro.
3. **Citação de canon de memória** (LC-08 no eixo prosa): a Ação do drawer do Todo cita "os 3 itens fora de escopo" e nomeia dois que **não estão** na lista — comentário e documentos da tarefa estão dentro da §Seções 5 do mesmo charter.
4. **Ranges de protótipo que terminam antes do último item da lista que descrevem** (Settings ×2) — o mesmo vetor do #6917 (`:107-111` pulando `Matrícula`), em dose menor.

Correção honesta: reescrever a linha *Filtro por período* do Todo (gap + map, `vivo` → `267-283`, Ação → paridade, com o "ambos preenchidos" como ressalva real se quiser um Decidir honesto); corrigir a justificativa do drawer citando a §Seções 5 e o `Todo/Show.tsx`; consertar os 5 números/linhas do Grupo 1 e os 2 ranges do Settings; trocar "31 contratos ativos" pelo comando + 28; retirar "não tem lastro escrito" do Reminders (o Non-Goal existe — o argumento válido é UI-0029, que já está na célula); regenerar os maps com `gerar-map.mjs --atualizar` depois. Depois, re-refutar o lote inteiro (§2.6). A sensibilidade do veredito: sem os 2 termos `grid`/`dia` → 13/534 = 2,43%; sem também `:286` e o drawer → 11/534 = 2,06%; só Grupo 1 → 5/197 = 2,54%; só ranges de map → 3/84 = 3,57%; só Grupo 3 → 2/55 = 3,64%. Reprovado em todos os recortes, mas o lote está a **um conserto** do aceite — não é o caso do irmão.

## Comandos reproduzíveis

```bash
git fetch origin && git rev-parse --is-shallow-repository                      # false
git merge-base origin/main HEAD; git rev-list --count origin/main..HEAD HEAD..origin/main   # ad376ed239 · 1 · 10
git diff --stat origin/main...HEAD                                              # 18 arquivos
git diff --stat ad376ed239 origin/main -- prototipo-ui/cowork/essenciais-page.jsx prototipo-ui/cowork/essenciais-extras.jsx prototipo-ui/cowork/hrm-page.jsx prototipo-ui/cowork/hrm-extras.jsx resources/js/Pages/Essentials/ prototipo-ui/design-docs/cowork-inbox/essenciais/ prototipo-ui/design-docs/cowork-inbox/hrm/ prototipo-ui/contrato/   # vazio
for p in prototipo-ui/cowork/essenciais-page.jsx resources/js/Pages/Essentials/Todo/Index.tsx; do [ "$(git hash-object $p)" = "$(git rev-parse origin/main:$p)" ] && echo same; done
MSYS_NO_PATHCONV=1 git show origin/main:prototipo-ui/cowork/essenciais-page.jsx | sed -n '174p;206p;449p;457p;459p;151,157p'
MSYS_NO_PATHCONV=1 git show origin/main:prototipo-ui/cowork/hrm-extras.jsx | sed -n '572,594p'
MSYS_NO_PATHCONV=1 git show origin/main:resources/js/Pages/Essentials/Documents/Index.tsx | sed -n '250,256p;285,289p'
MSYS_NO_PATHCONV=1 git show origin/main:resources/js/Pages/Essentials/Todo/Index.tsx | sed -n '186,191p;228p;267,283p;317p'
rg -n 'start_date|end_date' Modules/Essentials/Http/Controllers/ToDoController.php | head -3      # :106-108
git ls-tree -r origin/main --name-only | grep -c EssentialsTodoController                        # 0
rg --hidden -g '!.git/**' -n 'grid|dia' resources/js/Pages/Essentials/Reminders/Index.tsx; echo rc=$?   # 5 linhas, rc=0
rg --hidden -g '!.git/**' -c 'date_from|date_to|periodo' resources/js/Pages/Essentials/Todo/Index.tsx; echo rc=$?   # rc=1
rg --hidden -n 'full-month' resources/js/Pages/Essentials/Reminders/Index.charter.md             # Non-Goal :21
ls prototipo-ui/contrato/ | wc -l; ls prototipo-ui/contrato/*.contract.json | grep -vc EXEMPLO; git ls-files "*.contract.json" | grep -vi EXEMPLO | grep -v design-docs | wc -l   # 31 · 28 · 28
awk 'NR>=1 && NR<=70 && /prefix|Route::get\(.\/settings/ {print NR": "$0}' Modules/Essentials/Routes/web.php   # :58 prefix hrm → :66 /settings
node prototipo-ui/ancora.mjs --list | grep -E 'essentials|hrm/'
node scripts/governance/design-code-map-check.mjs --check --strict; echo rc=$?                   # 0
node scripts/governance/doc-id-index.mjs --check-collisions; echo rc=$?                          # 0
for f in documents-index todo-index messages-index reminders-index knowledge-index holidays-index settings-index tipos; do node prototipo-ui/gerar-map.mjs memory/requisitos/Essentials/$f-gap.md | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{const a=JSON.parse(s);console.log(a.prototipo_sha,a.partes.map(p=>p.acao.slice(0,12)).join("|"))})'; done
for a in cabecalho toolbar lista; do grep -c "data-contract=\"$a\"" resources/js/Pages/Essentials/Tipos.tsx; done   # 1 1 1
awk '/^\|/{n=gsub(/\|/,"|"); if(n!=4) print FILENAME":"FNR" pipes="n}' memory/requisitos/Essentials/*-gap.md; echo rc=$?   # vazio, 0
git diff origin/main...HEAD | grep '^+' | grep -nE '[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}'; echo rc=$?   # 1
```

## Resultado

```json
{"itens_verificados": 534, "erros_confirmados": 15, "error_rate_pct": 2.81, "pii_hits": 0, "veredito": "reprovado"}
```
