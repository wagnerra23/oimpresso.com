---
date: "2026-09-06"
topic: "Refutação GT-G5 rodada 2 do lote PR #6926 — 8 gap.md + 8 map.json do Essentials, pós-correção dos 15 achados da r1 (veredito: aprovado pela régua · NÃO mergeável como está)"
authors: ["C"]
prs: [6926]
outcomes:
  - "712 itens verificados · 10 erros confirmados · error_rate 1,40% (< 2%) → APROVADO pela régua do protocolo — mas com 2 bloqueadores mecânicos de merge que a régua não mede (abaixo)"
  - "Dos 15 achados da r1: 11 corrigidos por inteiro no gap.md; 4 corrigidos no gap.md mas NÃO no map.json — o `acao` de 4 partes (todo `filtro`, todo `drawer`, reminders `forma`, settings `cobertura`) ainda carrega o texto refutado (`EssentialsTodoController` inexistente · 'comentário, documentos da tarefa' · 'não tem lastro escrito'). O commit de fix tocou só 2 dos 8 maps, e `gerar-map.mjs --atualizar` faz `antiga.acao || nova.acao` — o velho vence, medido"
  - "Erro novo no map: `holidays.kpis-do-topo` status `vivo-a-frente` INVERTIDO (o protótipo é quem tem os KPIs; o vivo não) — a r1 atribuiu ao gerador, mas `status` é preenchido à mão (gerar-map nasce `pendente-mapeamento` e `fundir` preserva o humano)"
  - "Claims intactas/novas: '5 selects em grid' no Todo (são 3 `Select` + 2 `Input type=date`, resíduo do achado 8 da r1); '2 hits cada' para `estimated_hours`/`assigned_by` (1 hit cada, `grep -o`)"
  - "Bloqueador de merge fora da régua: os 2 state files conflitam com `origin/main` (`git merge-tree` = CONFLICT nos dois) e, tomados do lado do lote, regrediriam 23 telas (19 compared→anchored em Repair/Governance/RecurringBilling/Superadmin + 4 tested→validated) e apagariam 19 entries de `applications.json` — base envelheceu (§5 2026-08-03); precisa rebase + `--mark-compared` de novo"
  - "Citações `arquivo:linha`: 225/225 conferem (0 erros); Ação × canon 55/55; tabelas, PII, frontmatter, ids, `--strict` rc=0, `prototipo_sha` 8/8, `data-contract` 3/3: 0 erros"
---

# Refutação GT-G5 — rodada 2 — lote PR #6926 (`claude/gap-essentials-8-telas`)

> Protocolo: [`PROTOCOLO-REFUTADOR-BACKFILL.md`](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2/§3/§4.
> Rodada 1: [`2026-09-06-refutacao-gt-g5-lote-6926.md`](2026-09-06-refutacao-gt-g5-lote-6926.md) (reprovado, 2,81%, 15 erros / 534). Lida inteira antes de qualquer medição.
> Base medida: `origin/main` = `7bff2ca69d` · merge-base = `ad376ed239` · HEAD do lote = `534b2f4714` (2 commits: `58e21c116f` lote + `534b2f4714` fix) · `main` está **13** commits à frente (era 10 na r1) · repo **raso** (`git rev-parse --is-shallow-repository` = true — nenhuma data de `git log` usada como recibo, §5 2026-07-24).
> Refutador: Claude (Fable 5.1) em sessão fresca, worktree `heuristic-bose-8ee418`, sem contexto do gerador nem da r1 além do arquivo dela. ⚠️ O worktree estava com o HEAD **no próprio commit do lote**; os 22 arquivos "vivo"/"protótipo"/charter/controller foram exportados de `origin/main` via `MSYS_NO_PATHCONV=1 git show origin/main:<path>` para o scratchpad e conferidos por `git hash-object` = `git rev-parse origin/main:<path>` — **22/22 idênticos ao disco** (o branch não toca nenhum; `git diff --stat ad376ed239 origin/main -- <paths>` vazio, incl. `Modules/Essentials/`).
> Calibração: li a r1 inteira, o protocolo §2/§3/§4, o [`6917`](2026-09-06-refutacao-gt-g5-lote-6917.md) (via `git show origin/main:` — não existe no disco deste worktree) e procurei ativamente os 4 erros do irmão (citação deslocada · range em outro bloco · pipe em code-span · `acao` contaminado) **e** os 15 da r1.

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador — refutador `fable`; o commit de fix declara gerador Opus 5
- [x] Amostra: 100% anchors (tipo `anchors`) — sem prosa destilada, sem seleção aleatória, logo sem seed
- [x] Cada item verificado contra `origin/main` (cópias exportadas + hash conferido; canon lido inteiro), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII nas 1.592 linhas `+` do diff (agora inclui a evidência da r1), 7 padrões, cada um com controle positivo — 0 hits reais (1 falso-positivo: a própria tabela da r1 listando os NOMES dos padrões)
- [x] `error_rate_pct` calculado: **1,40%** (< 2 → aprovado pela régua; sensibilidade abaixo)
- [ ] Entry no ledger `governance/sdd-verification-ledger.json` — **não escrita por mim** (mandato: só este arquivo); o parent adiciona a entry apontando pra este path

## Escopo real do diff (medido)

`git diff --stat origin/main...HEAD` = **19 arquivos** (+1592 −39): 16 novos em `memory/requisitos/Essentials/` + `memory/sessions/2026-09-06-refutacao-gt-g5-lote-6926.md` (evidência da r1) + 2 modificados em `scripts/design-sync/state/`. O commit de fix (`534b2f4714`) tocou **6 gap.md** (documents · knowledge · messages · reminders · settings · todo — holidays e tipos intactos), **2 map.json** (settings · todo) e os 2 state files (`recordedAt` dos 8 + `mapSha256` de 2). Partes por mapa: 8 · 8 · 7 · 8 · 5 · 6 · 6 · 7 = **55**, batendo com as 8 tabelas.

## Tabela por grupo

| # | Grupo | Itens | Erros | Como mediu |
|---|---|---|---|---|
| 1 | Citações `arquivo:linha` nos 8 gap.md (Todo 34 · Documents 31 · Messages 21 · Reminders 28 · Knowledge 33 · Holidays 26 · Settings 39 · Tipos 13) | 225 | **0** | extrator (`cit.js`) resolvendo `:NNN` pelo contexto textual da frase (Vivo/Protótipo/nome de arquivo, nunca proximidade), imprimindo a(s) linha(s) das cópias de `origin/main`; os `:NNN` que o extrator não resolveu (regiões de cabeçalho, `:384`/`:125` do Documents, `:397` do Holidays) conferidos à mão |
| 2 | Claims de ausência, contagens e afirmações substantivas (Todo 15 · Documents 8 · Messages 3 · Reminders 11 · Knowledge 10 · Holidays 7 · Settings 10 · Tipos 11) | 75 | **2** | `rg --hidden -g '!.git/**' -c` com rc lido e controle positivo por arquivo; `grep -o \| wc -l` para contagem de ocorrências; `ancora.mjs --list`; `Routes/web.php`; `git ls-tree origin/main`; LEIA-ME + 6 charters do intake + 5 contratos + `essentials-tipos.contract.json` + playbooks 03/07/08 + 8 charters vivos, lidos inteiros |
| 3 | Coluna **Ação** × canon (55 partes), nas duas direções | 55 | **0** | mesma leitura do canon do Grupo 2 |
| 4 | Os 8 `.map.json`: schema (8) · `prototipo.arquivo` (8) · `vivo.arquivo` (8) · 3 `vivo.ancora` · `prototipo_sha` (8) · `--strict` (1) · ranges proto (46) · ranges vivo (37) · `acao` == tabela (55) · `status` coerente com o conteúdo (55) · `_acionavel` == `ehAcionavel(acao)` (55) | 284 | **6** | `design-code-map-check --check --strict` · `gerar-map.mjs` regenerado do gap.md atual e diffado campo a campo · `grep -c data-contract` · primeira/última linha de cada range impressa · varredura de formato de `linhas` nos 28 maps do repo |
| 5 | Integridade das 8 tabelas (`awk` contando pipes por linha, `FNR`) | 8 | 0 | controle positivo: linha sintética com pipe em code-span → `pipes=5` |
| 6 | Scan PII + BRL (7 padrões) nas linhas `+` | 7 | 0 | grep com controle positivo cada (7/7 detectados no arquivo sintético) |
| 7 | Frontmatter: `fmVal` (8) · js-yaml estrito (8) · `tela_viva` existe em disco e em `origin/main` (8) · `prototipo` idem (8) · `id` sem colisão (8) | 40 | 0 | `fmVal` de `gerar-contrato.mjs:36` · js-yaml `CORE_SCHEMA` · `git ls-tree origin/main` · `doc-id-index --check-collisions` (0 em 2609) |
| 8 | State files: `mapSha256`/`targetSha256` recomputados (8×2) · **frescor da base** dos 2 arquivos contra `origin/main` de hoje | 18 | **2** | sha256 em node · `git merge-tree --write-tree origin/main HEAD` · diff dos conjuntos `lifecycleState` e das entries entre lote e main |
| | **Total** | **712** | **10** | **error_rate = 1,40%** |

## Status dos 15 achados da r1 — um a um

Numeração da r1 (Grupo 1: 5 citações · Grupo 2: 5 claims · Grupo 3: 2 Ações · Grupo 4: 3 ranges de map). "Corrigido" = a linha/afirmação nova confere na fonte de `origin/main`; "mal-corrigido" = a correção existe mas é incompleta ou introduziu inconsistência; "intacto" = não tocado.

| # | Achado r1 | Status | Evidência (origin/main) |
|---|---|---|---|
| 1 | todo `:174` → estado vazio de onboarding | ✅ corrigido → `:206` | `essenciais-page.jsx:206` = `? "A lista de afazeres é do escritório inteiro: quem atribui usa a permissão essentials.assign_todos…"` (o `desc` do `<Vazio variante="first">`, `:203-207`). Map `estado-vazio` proto `203-206` já estava certo |
| 2 | documents `:286` → descrição do cabeçalho | ✅ corrigido → `:252-256` | `Documents/Index.tsx:252` `<p className="text-sm text-muted-foreground mt-1">` · `:254` `'Avisos e memorandos internos em texto.'` · `:255` `'Arquivos compartilhados com a equipe.'` · `:256` `</p>` — e a célula agora cita as duas frases literais |
| 3 | reminders `:457` → filtro `fOrig` | ✅ corrigido → `fOrig` (`:449`) filtrando `l.origem` (`:459`) | `:449` `const [fOrig, setFOrig] = useState("");` · `:459` `const visiveis = lemb.filter((l) => !fOrig \|\| l.origem === fOrig);` |
| 4 | knowledge `:152-157` pulava a categoria | ✅ corrigido → `:151-157` (categoria `:151` · seção `:154` · artigo `:156`) | `essenciais-extras.jsx:151` `<button className={\`ess-kb-b cat …\`}` · `:154` `…ess-kb-b sec…` · `:156` `…ess-kb-b art…`. Map `arvore` proto `148-158` (contém) |
| 5 | settings `:572-591` cobria 8 das 10 chaves | ✅ corrigido → `:573-594`, com as 10 linhas listadas | `hrm-extras.jsx` `:573` `leave_ref_no_prefix` · `:574` `leave_instructions` · `:579` `payroll_ref_no_prefix` · `:580` `essentials_todos_prefix` · `:585-588` `grace_*` ×4 · `:593` `is_location_required` · `:594` `calculate_sales_target_commission_without_tax` — **10**, e nada entre `:575-578`/`:581-584`/`:589-592` é chave. ⚠️ **Colateral:** a lista de linhas entrou na célula Ação do gap.md, mas o `acao` do `settings-index.map.json` ficou com o texto anterior (sem a lista) → conta no Grupo 4 |
| 6 | `grid` = "0" (era 1) | ✅ corrigido → `grid` = 1 em `:207`, declarado como `grid-cols-1 md:grid-cols-3` do form | `rg -n grid Reminders/Index.tsx` → **1** linha, `:207` `<div className="grid grid-cols-1 md:grid-cols-3 gap-3">`, rc=0 |
| 7 | `dia` = "0" (era 4) | ✅ corrigido → `dia` = 4, "são `Dialog`/`dialogOpen`" | `rg -n dia` → `:22` (`alert-dialog`) · `:32` (`dialog`) · `:77` (`dialogOpen`) · `:188` (`<Dialog`) = **4**, rc=0. A célula agora mede `calend`=0 · `cal-g`=0 · `role="grid"`=0 (rc=1 nos três) — claim substantiva verdadeira e números certos |
| 8 | Filtro de período do Todo declarado AUSENTE (sonda com vocabulário do protótipo) | ⚠️ **mal-corrigido** — a célula do gap.md está certa; o `acao` do map e uma frase da linha vizinha não | **Célula corrigida confere:** `Todo/Index.tsx:267-283` = `<div className="space-y-1">` · `:268` `<Label htmlFor="start_date"…>De</Label>` · `:276` `…htmlFor="end_date"…>Até` · `:283` `/>`; `ToDoController.php:106-108` = `if ($request->filled('start_date') && $request->filled('end_date')) { $query->whereDate('date','>=',…)->whereDate('date','<=',…)`; protótipo `:182` `ess-toolbar2` · `:183` `Período de início` · e o "aceita um só" está em `:56-59` (`if (!de && !ate) return true; if (de && d < new Date(de)) return false; if (ate && d > new Date(ate)) return false`). **O que ficou:** (a) o `todo-index.map.json` parte `filtro-por-periodo-de-inicio` ainda tem `acao: "Decidir. É comportamento (filtrar), logo vira pedido … Custo: 2 campos + range no \`EssentialsTodoController\` — não é de passagem."` — o texto refutado, com o controller inexistente (`git ls-tree -r origin/main` → 0); (b) a linha **Toolbar de filtros** (`todo-index-gap.md:28`) segue dizendo *"`:228-...` com 5 selects em grid"* — `:228` é `md:grid-cols-5`, mas dentro dela há **3** `<Select` + **2** `<Input type="date">` (contado em `:228-284`) |
| 9 | "31 contratos ativos" (4 sites) | ✅ corrigido → **28**, com o comando | `git ls-files "prototipo-ui/contrato/*.contract.json"` = 29, sem `EXEMPLO` = **28**; critério do gate (`contrato-de-tela.mjs:428-438`: `ls-files "*.contract.json"` excl. `EXEMPLO` e `design-docs/`) = **28**. Os 3 `essentials-*` estão lá; os 5 do intake estão em `cowork-inbox/essenciais/contrato/` (5/5) e não em `prototipo-ui/contrato/` (0/5). Texto idêntico nos 4 gap.md (documents `:22` · todo `:21` · messages `:23` · reminders `:23`) |
| 10 | Reminders "a lista do vivo não tem lastro escrito" | ⚠️ **mal-corrigido** — gap.md certo, map com o texto velho | **Gap.md confere:** `Reminders/Index.charter.md:40` = `- ❌ NÃO renderiza calendário full-month grid (decisão UX: listagem é mais prática diário)` e `:22` = `…Substitui o calendário FullCalendar legado por listagem ordenada cronologicamente — padrão consistente com outras telas migradas (Todo, Holidays).` (ressalva: a célula chama de "§Objetivo"; o heading em `:20` é `## Mission`, não existe "Objetivo" no arquivo — linha e quote certos, rótulo da seção errado). **O que ficou:** `reminders-index.map.json` parte `forma-da-tela-grade-do-mes-lista`, `acao` ainda = *"…E aqui não há artefato do lado do vivo defendendo a lista … a lista do vivo não tem lastro escrito…"* — a claim refutada, intacta no artefato que a Fase 4 consome |
| 11 | Ação do filtro: *Decidir.* sobre paridade existente | ⚠️ **mal-corrigido** — gap.md certo, map com o texto velho | Gap.md: *"Decidir. A capacidade está entregue; o que falta decidir é a semântica do range aberto…"* — é o "Decidir honesto" que a r1 pediu; a divergência `filled && filled` × `de \|\| ate` é real (medida acima) e "a tela não avisa" é verdadeiro (nenhuma mensagem em `:267-283`). Map: ver #8 — `acao` velho |
| 12 | Ação do drawer: citação falsa do canon | ⚠️ **mal-corrigido** — gap.md certo, map com o texto velho | Gap.md agora: *"Existe como PÁGINA, não como drawer … `Todo/Show.tsx`, linkada em `Index.tsx:317` … Nada disso está entre os 3 itens que aguardam [W] no `LEIA-ME.md` (vínculo tarefa ↔ OS/cliente · versionamento de documento · canal de notificação)"*. Confere: `Index.tsx:317` `<Link href={\`/essentials/todo/${t.id}\`}`; `Show.tsx` existe em `origin/main` (674 linhas; `type Tab = 'comments' \| 'documents' \| 'activities'` em `:117`); `ancora.mjs --list` literal: *"dados da tarefa + tabs comentários/anexos/atividades"*; `LEIA-ME.md:14-16` lista exatamente os 3; `Tarefas.charter.md:34-37` os mesmos 3. Map `drawer-de-detalhe`: `acao` ainda = *"O charter do intake liga o detalhe aos 3 itens fora de escopo que aguardam [W] (comentário, documentos da tarefa, vínculo com OS)…"* — a citação falsa, intacta |
| 13 | map settings `cobertura` proto `572-591` | ✅ corrigido → `573-594` | primeira linha `:573` `Campo … leave_ref_no_prefix`, última `:594` `flag("calculate_sales_target_commission_without_tax"…` |
| 14 | map settings `chave-de-presenca` proto `583-589` | ✅ corrigido no **conteúdo** → `"585-588 e 593"` (`:585` `grace_before_checkin` … `:593` `is_location_required`) · ⚠️ **formato fora da convenção** | Varredura de `linhas` nos **28 maps / 336 ranges** do repo: **15** são multi-range, e **14** usam vírgula (`"145,158"`, `"53-72,156-167"`, `"598-615,682,755,2029"` — Arquivos · Compras · Financeiro); este é o **único** com `" e "`. Nenhum consumidor parseia (`consumir-map.mjs` só imprime; `--strict` rc=0), então não quebra hoje — mas um parser por vírgula, o dia que existir, lê isto como 1 token. Contado como erro de forma no Grupo 4 |
| 15 | map todo `filtro` vivo `n/a` | ✅ corrigido → `resources/js/Pages/Essentials/Todo/Index.tsx` `267-283` | confere (acima). `status: decidir-w` agora é coerente com a Ação corrigida do gap.md — mas o `acao` do map não é (#8/#11) |

**Resumo:** 11 corrigidos por inteiro · 4 mal-corrigidos (#8, #10, #11, #12 — todos pelo mesmo mecanismo: o gap.md mudou e o map não) · 0 intactos. **Causa medida:** o commit de fix só tocou `settings-index.map.json` e `todo-index.map.json` (diff do commit), e nos dois só os campos `linhas`. O caminho que o docblock do map manda usar — `gerar-map.mjs --atualizar` — **não propaga** correção de Ação: `fundirComExistente` faz `acao: antiga.acao || nova.acao` (`gerar-map.mjs:218`); teste isolado com esqueleto `acao: "TEXTO NOVO"` × existente `acao: "TEXTO VELHO"` devolve `"TEXTO VELHO"`. Ou seja, corrigir o gap.md e rodar o comando canônico deixa o map com o texto refutado — o conserto exige editar o `acao` dos 4 maps à mão (ou regenerar do zero e re-preencher `linhas`/`status`).

## Grupo 1 — citações (225 · 0 erros)

Régua da r1 e do #6917: a linha citada tem de conter o que a prosa diz. Todas as 225 conferem ao número — incluindo as 5 corrigidas (#1-#5 acima) e as regiões de cabeçalho que a r1 não listou (`Tarefas :33-231` → `:33` `function Tarefas({ view })`; `Arquivos :338-400` → `:338` `function Arquivos({ modo })`; `Lembretes :444-537` → `:444` `function Lembretes()`; `Mensagens :565-622` → `:565` `function Mensagens()`; `Feriados :385-466` → `:385` `function Feriados()` … `:466` `}`; `BaseConhecimento :127-188` → `:127` `function BaseConhecimento()`; `Config :553` → `function Config()`). Duas que exigiram contexto além da frase: documents `:384` (`uploadForm.errors.name`) e `:125` (`onError: () => toast.error('Falha no upload.')`) são do **vivo**, como a prosa diz; holidays `:397` (`H.dias(a.ini, a.fim)`) é do **protótipo** (`hrm-page.jsx`), como o vocabulário diz.

**Ressalvas não contadas (as mesmas da r1, todas ainda presentes — nenhuma aponta pra outro bloco):** documents `:259` é o guard `tab === 'documents'`, o botão em `:260`; reminders `:509` é o rótulo do evento; reminders `:487` é só o botão do mês (as setas ‹ › estão nas linhas vizinhas); todo `:218` só tem Excluir (Concluir em `:217`); os "AlertDialog de exclusão" em Todo `:27-45`, Reminders `:14-21`, Holidays `:23-30` apontam imports; ranges que terminam em banner (`FormArquivo :401-443`, `FormLembrete :538-564`, `Mensagens :565-622`, `BaseConhecimento :127-188`); reminders "§Objetivo (`:22`)" onde o heading é `## Mission`. Nova: messages `:114-118` — `:114-117` é a cauda do `fetchNew`, o `setInterval` está em `:118` (contém o descrito).

## Grupo 2 — claims (75 · 2 REFUTADOS)

Todas re-rodadas com `rg --hidden -g '!.git/**'`, rc lido (rc=1 = zero; rc≠0/1 = falha) e controle positivo no mesmo arquivo. Conferem: Todo `Checkbox`/`onCheckedChange`/`Atrasada` = 0 (controle `Select` = 34); Knowledge `search`/`busca`/`filtro`/`Input` = 0 (controle `Button` = 13); Holidays `KpiCard`/`KpiGrid`/`sort` = 0 (controle `Dialog` = 41); Reminders `origem`/`origin` = 0, `calend`/`cal-g`/`role="grid"` = 0, `grid` = 1, `dia` = 4; Messages `Marcar tudo como lido` = 0, `Enviar` = 1 (`:266`); 28 contratos (×4 sites); `function Config` em exatamente `essenciais-extras.jsx:189` e `hrm-extras.jsx:553`; a nota `:223` e a frase de `:224` (*"Tolerância de marcação, prefixo da folha e meta de venda continuam em HRM · Configurações — o controller é o mesmo"*) literais; `/essentials/settings` fora das rotas (o `settings` está em `Route::prefix('hrm')`, `web.php:58/66`); `related_prototype`: 4× `n/a` (Documents · Todo · Messages · Settings), 3× sem chave (Reminders · Knowledge · Holidays), Tipos com valor direto; `data-contract` `cabecalho`/`toolbar`/`lista` = 1 cada em `Tipos.tsx:135/149/169`; os 3 itens do `LEIA-ME.md:14-16` e do `Tarefas.charter.md:35-37`; thread 07 "12 campos" (`:13`), §3(i)/(ii) (`:18`), §PARAR SE (`:22`); thread 08 "não foi lido" (`:13`), régua comportamento×layout (`:18`), "RESÍDUO 4" (`:17`); playbook 03 `nao_toca: @destroy` (`:7`), §B reusar (`:18`), §A ordem (`:13`); `_pendente_w` ×2 e `_nota_fonte` do `essentials-tipos.contract.json`; a 1ª seção do `BaseConhecimento.charter.md` é Busca (`:15`) e "busca sem resultado" é estado (`:22`); o `Lembretes.charter.md` lista "canal de notificação" (`:34`); `EssentialsSettingsController` existe e `authorizeAdmin` está em `:40/:64/:101`.

| Claim do lote | Medido | Veredito |
|---|---|---|
| Todo · Toolbar (`todo-index-gap.md:28`): *"`:228-...` com **5 selects** em grid: Status (`:233`), Prioridade (`:245`), Atribuído a (`:258`)"* | `:228` = `<div className="grid grid-cols-1 md:grid-cols-5 gap-3">`; entre `:228-284`: **3** `<Select` (`:229`, `:241`, `:254`) + **2** `<Input type="date">` (`:269`, `:277`). A grid tem 5 colunas, não 5 selects | ❌ **intacto desde a r1** (a r1 registrou "não '5 selects'" dentro do achado 8; a correção reescreveu a linha de baixo e deixou esta) |
| Todo · Colunas (`:30`): *"`estimated_hours` e `assigned_by` aparecem só na interface TypeScript … (**2 hits cada**, ambos em `:65-66`)"* | `grep -o estimated_hours \| wc -l` = **1** · `assigned_by` = **1** (`rg -n` → só `:65` e `:66`, nada no JSX — a parte substantiva é verdadeira) | ❌ número falso como escrito (1 hit cada; "2" só como total). A r1 leu "2 hits" como total e aceitou — pela régua que ela mesma aplicou a `grid`/`dia` (*"números falsos como escritos"*), conta |

**Ressalvas não contadas:** *"das 11 medidas"* (Reminders `:18`) e *"Única das 11 telas"* (Tipos `:11`) seguem sem comando (r1 já ressalvou); reminders `:22` rotulado "§Objetivo" (heading = `## Mission`); a 5ª chave "de presença" como o gap conta (`grace_*` ×4 + `is_location_required`) confere com a thread 07 `:18`.

## Grupo 3 — coluna Ação × canon (55 · 0 erros)

Procurei as duas direções. As 4 células reescritas pelo fix ficaram coerentes com o canon: Todo/filtro — *Decidir.* sobre a semântica do range aberto (divergência real e medida; o contrato `tarefas` tem `filtros-periodo` como seção, e a capacidade existe nos dois lados); Todo/drawer — *Decidir.* como decisão de **forma** (o `Tarefas.charter.md` §Seções lista **5. Drawer de detalhe** e **6. Tela cheia (todo/show)** — o protótipo tem os dois, o vivo só a página; comentários/anexos entregues no `Show.tsx`); Reminders/forma — *Decidir.* com os dois lastros (Non-Goal `:40` × `Lembretes.charter.md:9` "um calendário"), coerente com UI-0029; Settings/cobertura — *Nada* + errata ao playbook (10 chaves medidas × "12 campos" da thread, `:13`). Os 51 restantes batem com o que a r1 já aceitou (Nada-decidido ← 07 §3(i); Nada-layout ← 08 `:18`; os 7 Nada do Tipos ← contrato + 03 `nao_toca`; Decidir de copy ← contratos do intake com a copy literal divergente, todas conferidas: `Todos os documentos`/`Todas as notas` × `Documentos`/`Memos`; `Adicionar lembrete` × `Novo lembrete` (`:142`); `Escreva uma mensagem` × `Digite sua mensagem…` (`:234`); `Mensagens` × `Mural de mensagens` (`:167`)).

## Grupo 4 — os 8 `.map.json` (284 · 6 REFUTADOS)

**O que confere (0 erros):** `--check --strict` **rc=0** (28 maps, sem DRIFT nem stale); `prototipo.arquivo`/`vivo.arquivo` apontam blobs de `origin/main` (ou `n/a` legítimo nas 7 partes do Tipos e nas partes sem lado); as 3 âncoras do Tipos existem como `data-contract`; `prototipo_sha` **não-stale** nos 8 (regeneração devolve os mesmos 4 valores: `3e0b0aa398f8` ×4 · `86cb09835456` · `1b1cc5c4264f` ×2 · `aa114049dd5b`); `_acionavel` == `ehAcionavel(acao)` em **55/55**; todos os **83 ranges** (46 proto + 37 vivo) abrem no bloco que descrevem — primeira e última linha impressas e conferidas, incluindo os 3 corrigidos (`573-594` · `585-588 e 593` · `267-283`). Ressalvas de borda iguais às da r1 (documents `botao-de-criacao` vivo `259-272` entra 2 linhas nas Tabs; `estado-vazio` `286-292` entra no `<table>`; `titulo-do-card` vivo `250-258` — `:258` já é `<div className="flex gap-2">`; todo `toolbar` vivo `219-272` sobrepõe 6 linhas com `filtro` `267-283`; reminders `drawer` proto `519-526` começa numa linha vazia, o `<Drawer` é `:521`; `navegacao-de-mes` `485-490` termina no `<select>` de origem); nenhuma aponta pra outro bloco. `todo.drawer-de-detalhe` vivo `n/a`: aceito com nota — o drawer de fato não existe no `Index.tsx`; a capacidade está em `Show.tsx`, que é outra tela e outro map.

| Map · parte | Campo | O que está no map | O que a tabela do gap.md diz / o que é verdade | Veredito |
|---|---|---|---|---|
| todo · `filtro-por-periodo-de-inicio` | `acao` | *"Decidir. É comportamento (filtrar), logo vira pedido … Custo: 2 campos + range no `EssentialsTodoController` — não é de passagem."* | gap.md: *"Decidir. A capacidade está entregue; o que falta decidir é a semântica do range aberto…"*. `EssentialsTodoController` não existe (0 em `git ls-tree -r origin/main`; o controller é `ToDoController`) | ❌ `acao` ≠ tabela, e o texto é a claim refutada na r1 |
| todo · `drawer-de-detalhe` | `acao` | *"Decidir. O charter do intake liga o detalhe aos 3 itens fora de escopo … (comentário, documentos da tarefa, vínculo com OS)…"* | gap.md: *"Decidir. Drawer × página é decisão de forma … Nada disso está entre os 3 itens que aguardam [W] no `LEIA-ME.md`…"*. Os 3 itens reais: OS/cliente · versionamento · notificação | ❌ `acao` ≠ tabela, citação falsa do canon intacta |
| reminders · `forma-da-tela-grade-do-mes-lista` | `acao` | *"…E aqui não há artefato do lado do vivo defendendo a lista … a lista do vivo não tem lastro escrito…"* | gap.md: *"…E a lista do vivo **tem lastro escrito**: … Non-Goal literal (`:40`)…"* | ❌ `acao` ≠ tabela, claim de ausência refutada intacta |
| settings · `cobertura-das-chaves-de-configuracao` | `acao` | *"…Medido hoje: o protótipo tem 10, não 12…"* | gap.md: *"…Medido hoje: o protótipo tem 10 (`:573` `:574` `:579` `:580` `:585` `:586` `:587` `:588` `:593` `:594`), não 12…"* | ❌ `acao` ≠ tabela (colateral do fix #5 — sem claim falsa, mas o campo derivado divergiu) |
| holidays · `kpis-do-topo` | `status` | `"vivo-a-frente"` | Ação: *"Nada — layout…"*; Estado: *"**Ausente.** Protótipo `:418-422`: 3 KPIs … `KpiCard`/`KpiGrid` no vivo = 0"*. Quem está à frente é o **protótipo** | ❌ status semanticamente invertido. A r1 registrou como "inconsistência de derivação, não do lote — é o `gerar-map.mjs` que carimba"; **não é**: `gerar-map.mjs:172` nasce `status: 'pendente-mapeamento'` e `fundirComExistente` (`:217`) preserva o valor humano — o `vivo-a-frente` foi escrito pelo autor. Comparar com `knowledge.acoes-de-autoria` e `tipos.nota-destroy` (vivo à frente de verdade, `vivo-a-frente` certo) e `messages.mural` (vivo à frente no polling, `paridade`) — o vocabulário de status não está uniforme, mas só o do Holidays está errado |
| settings · `chave-de-presenca-dentro-do-hrm` | `prototipo.linhas` | `"585-588 e 593"` | conteúdo certo; formato único no repo — 14 dos 15 multi-ranges dos 28 maps usam vírgula | ❌ forma (ver #14 acima) |

## Grupo 5 — tabelas (8 · 0)

`awk '/^\|/{n=gsub(/\|/,"|"); if(n!=4) print FILENAME":"FNR" pipes="n}' memory/requisitos/Essentials/*-gap.md` → vazio, rc=0. Controle positivo: `| a | \`x|y\` | c |` → `pipes=5`. As 4 células reescritas escapam o `\|` dentro de crase (`filled('start_date') && …`, `de \|\| ate`).

## Grupo 6 — PII + BRL (7 padrões · 0 hits)

Base: `git diff origin/main...HEAD | grep '^+'` = **1.592** linhas (inclui a evidência da r1). CPF pontuado · CPF cru 11 dígitos (excluindo vizinhança hex) · CNPJ · e-mail · cifrão+dígito · telefone BR · nomes de cliente do CRM (lista do PROTOCOLO): **0** hits reais; controle positivo 7/7. Único match: a linha da **tabela da r1** que lista os nomes dos padrões procurados (`grep` cego a contexto) — falso-positivo por construção, não PII. O padrão de telefone que dava 5 FP em hex na r1 deu 0 aqui com o padrão ancorado.

## Grupo 7 — frontmatter (40 · 0)

Os 8 parseiam por `fmVal` e por js-yaml (`CORE_SCHEMA` → `gerado_em` string; no schema default vira `Date`, ressalva idêntica à da r1); `tela_viva`/`prototipo` existem em disco **e** em `origin/main` (16/16); ids `requisitos-essentials-*-gap` ×8 → `doc-id-index --check-collisions` **0 colisão em 2609**; sem CRLF.

## Grupo 8 — state files (18 · 2 REFUTADOS)

**Consistência interna (16 · 0):** `applications.json` — `mapSha256` == sha256 do `.map.json` em disco e `targetSha256` == sha256 do `.tsx`, **16/16**; `application-report.json` — 8/8 Essentials em `lifecycleState: compared`.

**Frescor da base (2 · 2):** `git merge-tree --write-tree origin/main HEAD` → **CONFLICT (content)** em `scripts/design-sync/state/application-report.json` **e** em `applications.json`. Três commits de `main` desde o merge-base tocam `state/` (`2bcd8658c4` #6920 · `e556453ebf` #6916 · `195514e06a` #6908) — o primeiro **já estava** no `main` que a r1 mediu (`c1292448ee`), os outros dois não. Diff dos conjuntos: o `application-report.json` do lote diverge do de `main` em **31** telas — as 8 do Essentials (`anchored→compared`, esperado) e **23** que o lote **regride**: 19 `compared→anchored` (Repair ×7 · governance ×5 · Superadmin ×4 · RecurringBilling ×3) + 4 `validated→tested` (Fiscal ×3 · Arquivos); contadores `main` = `anchored 40 / compared 26`, lote = `51 / 15`. O `applications.json` do lote tem **19** entries a menos que o de `main` (as mesmas 19 telas). Resolver "pelo lado do lote" apagaria trabalho de 3 PRs mergeados; resolver "pelo lado de main" perde os 8 entries do Essentials. O caminho é **rebase + `--mark-compared` de novo** (os state files são derivados). É a lápide §5 2026-08-03 (*"base envelheceu sozinha"*): o lote estava certo na hora do commit; não está mais.

## Padrão do erro (o que a rodada 2 mostra)

1. **A correção parou no gap.md.** Os 15 achados foram atacados no artefato de prosa e conferem lá — o autor mediu (`sed -n`) antes de reescrever, e as 5 citações + 2 ranges + 3 claims + 2 Ações estão certas. Mas o `acao` do map é **campo derivado da tabela** (gerar-map copia a coluna Ação), e o fix regenerou só `linhas` em 2 maps; os outros 6 ficaram com o `mapSha256` da r1. Resultado: o artefato que a Fase 4 consome (`consumir-map.mjs`) ainda diz que o `EssentialsTodoController` precisa de 2 campos, que o drawer depende de "comentário, documentos da tarefa" e que a lista do Reminders "não tem lastro escrito". É o erro nº3 do #6917 (`acao` contaminado), que a r1 mediu em 0/55 e agora está em **4/55**.
2. **O comando canônico não conserta isso.** `gerar-map.mjs --atualizar` preserva `antiga.acao || nova.acao` — testado: o texto velho vence sempre. O docblock do map manda rodar `--atualizar` quando stale, e quem seguir a instrução mantém o `acao` refutado. Não é defeito do lote, é do mecanismo — mas é o mecanismo que explica por que 4 dos 15 ficaram pela metade, e vale registro (é o dono do tema: estender `fundirComExistente` pra re-derivar `acao` do esqueleto quando o gap.md mudou, sem mexer no que é humano — `linhas`/`status`/`_nota*`).
3. **`status` é humano, não derivado** — e a r1 errou a atribuição. `holidays.kpis-do-topo = vivo-a-frente` foi escrito à mão, invertido. Um erro; mas a lição é que "status coerente com o conteúdo" precisa entrar na régua do refutador como item próprio (aqui entrou: 55 itens, 1 erro).
4. **Base envelheceu.** Os 2 state files conflitam com `main` e regrediriam 23 telas. Não é conteúdo das 8 telas; é o que faz o PR não ser mergeável mesmo aprovado.

## Veredito e sensibilidade

Pela régua do protocolo (§2.6, `< 2%`): **10 / 712 = 1,40% → aprovado**. Recortes: sem o frescor de base (que a r1 não media) → 8/710 = **1,13%**; só Grupo 4 → 6/284 = **2,11%**; só `acao` == tabela → 4/55 = **7,27%**; só Grupo 2 → 2/75 = 2,67%; contando cada claim falsa dentro dos 4 `acao` stale como item separado (+4: controller inexistente · capacidade ausente · citação falsa · lastro) → 14/712 = **1,97%**; essa leitura dura sobre o denominador da r1 (536 = os 534 dela + 2 de frescor) → 14/536 = **2,61%, reprovado**. Declaro a minha régua: cada item verificável contado **uma** vez, o campo `acao` é um item — 1,40%. O parent escolhe se prefere a da r1.

**⚠️ O que a régua não mede e bloqueia o merge de qualquer jeito:** (a) conflito de conteúdo nos 2 state files — `git merge-tree` reprova; (b) 4 maps carregando texto refutado que o `--atualizar` não vai limpar. Ambos são consertos **mecânicos** (rebase + `--mark-compared`; editar `acao` de 4 partes + `status` de 1 + a frase "5 selects" + "2 hits cada" + o formato do range), sem re-derivação de conteúdo — e depois `design-code-map-check --check --strict` + o diff `acao`==tabela (comando abaixo) provam o fechamento. Não vejo necessidade de uma rodada 3 de conteúdo; vejo necessidade de uma conferência mecânica dos 5 campos de map após o conserto.

## Comandos reproduzíveis

```bash
git fetch origin && git rev-parse --is-shallow-repository                        # true (raso — sem datas de git log)
git rev-parse --short HEAD origin/main; git merge-base origin/main HEAD          # 534b2f4714 · 7bff2ca69d · ad376ed239
git rev-list --count origin/main..HEAD; git rev-list --count HEAD..origin/main   # 2 · 13
git diff --stat ad376ed239 origin/main -- prototipo-ui/cowork/ resources/js/Pages/Essentials/ Modules/Essentials/ prototipo-ui/design-docs/cowork-inbox/essenciais/ prototipo-ui/design-docs/cowork-inbox/hrm/ prototipo-ui/contrato/   # vazio
git show 534b2f4714 --stat                                                       # 6 gap.md · 2 map.json · 2 state
for p in <22 paths>; do MSYS_NO_PATHCONV=1 git show origin/main:$p > $S/main/$p; [ "$(git hash-object $S/main/$p)" = "$(git rev-parse origin/main:$p)" ] && [ "$(git hash-object $p)" = "$(git rev-parse origin/main:$p)" ] && echo same; done   # 22/22
sed -n '203,207p;449p;459p;56,59p;182,189p' prototipo-ui/cowork/essenciais-page.jsx
sed -n '148,158p' prototipo-ui/cowork/essenciais-extras.jsx
sed -n '571,597p' prototipo-ui/cowork/hrm-extras.jsx
sed -n '248,257p;125p;384p' resources/js/Pages/Essentials/Documents/Index.tsx
sed -n '186,191p;228,284p;302,309p;317p' resources/js/Pages/Essentials/Todo/Index.tsx
sed -n '228,284p' resources/js/Pages/Essentials/Todo/Index.tsx | grep -c '^\s*<Select$\|^\s*<Select '; sed -n '228,284p' resources/js/Pages/Essentials/Todo/Index.tsx | grep -c 'type="date"'   # 3 · 2
sed -n '100,112p' Modules/Essentials/Http/Controllers/ToDoController.php         # :106 filled && filled
grep -o estimated_hours resources/js/Pages/Essentials/Todo/Index.tsx | wc -l; grep -o assigned_by resources/js/Pages/Essentials/Todo/Index.tsx | wc -l   # 1 · 1
git ls-tree -r origin/main --name-only | grep -c EssentialsTodoController        # 0
sed -n '20,22p;37,40p' resources/js/Pages/Essentials/Reminders/Index.charter.md; grep -c Objetivo resources/js/Pages/Essentials/Reminders/Index.charter.md   # ## Mission · Non-Goal :40 · 0
rg --hidden -g '!.git/**' -n 'grid|dia' resources/js/Pages/Essentials/Reminders/Index.tsx; echo rc=$?   # 5 linhas (1+4), rc=0
rg --hidden -g '!.git/**' -c 'role="grid"|calend|cal-g' resources/js/Pages/Essentials/Reminders/Index.tsx; echo rc=$?   # rc=1
git ls-files "prototipo-ui/contrato/*.contract.json" | grep -vi EXEMPLO | wc -l; git ls-files "*.contract.json" | grep -vi EXEMPLO | grep -v design-docs | wc -l   # 28 · 28
cat -n prototipo-ui/design-docs/cowork-inbox/essenciais/LEIA-ME.md | sed -n '13,16p'; sed -n '34,37p' prototipo-ui/design-docs/cowork-inbox/essenciais/Tarefas.charter.md
node prototipo-ui/ancora.mjs --list | grep -E 'essentials|hrm/'
node scripts/governance/design-code-map-check.mjs --check --strict; echo rc=$?   # 0
node scripts/governance/doc-id-index.mjs --check-collisions                      # 0 em 2609
# acao == tabela (55): split das linhas '| ' do gap.md × partes[].acao, normalizando ** e espaços → 4 divergentes
node $S/acao-vs-tabela.js                                                        # todo#2 · todo#6 · reminders#1 · settings#1
# fundirComExistente preserva o acao velho:
node $S/fundir-test.mjs                                                          # "TEXTO VELHO" — NAO propaga
# formato de linhas nos 28 maps: 336 ranges · 15 multi-range · 14 com vírgula · 1 com " e "
node $S/ranges.js
awk '/^\|/{n=gsub(/\|/,"|"); if(n!=4) print FILENAME":"FNR" pipes="n}' memory/requisitos/Essentials/*-gap.md; echo rc=$?   # vazio · 0
git diff origin/main...HEAD | grep '^+' | grep -v '^+++' | grep -cE '[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}'   # 0
git merge-tree --write-tree origin/main HEAD | grep -E 'CONFLICT'                # 2 (application-report.json · applications.json)
git log --oneline ad376ed239..origin/main -- scripts/design-sync/state/         # 195514e06a · e556453ebf · 2bcd8658c4
git merge-base --is-ancestor 2bcd8658c4 c1292448ee && echo ja-estava-na-r1       # sim (os outros 2, não)
MSYS_NO_PATHCONV=1 git show origin/main:scripts/design-sync/state/application-report.json > $S/rep-main.json; node $S/state-diff.js $S   # 31 divergentes · 19 entries só no main
```

## Resultado

```json
{"itens_verificados": 712, "erros_confirmados": 10, "error_rate_pct": 1.40, "pii_hits": 0, "veredito": "aprovado"}
```
