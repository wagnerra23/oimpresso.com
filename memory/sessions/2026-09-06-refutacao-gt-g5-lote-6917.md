---
date: "2026-09-06"
topic: "Refutação GT-G5 do lote PR #6917 — 3 gap.md + 3 map.json do Ponto (Dashboard/Index · Espelho/Index · Espelho/Show) (veredito: reprovado)"
authors: ["C"]
prs: [6917]
outcomes:
  - "256 itens verificados · 32 erros confirmados · error_rate 12,50% (≥2%) → lote REPROVADO"
  - "Erro sistemático: citações `arquivo:linha` DESLOCADAS (±1 a ±4 linhas, nas duas direções) — 18 das 110 citações dos gap.md e 10 dos 44 ranges dos map.json não apontam pra linha que descrevem; concentrado em `ponto-page.jsx` e em `Espelho/Index.tsx`"
  - "Linha de tabela QUEBRADA por `|` dentro de code-span (`Drawer|Sheet|NSR|hash`) — a coluna Ação do Drawer do dia virou `Sheet` no gap.md E no map.json (`acao: \"Sheet\"`, `_acionavel: true`)"
  - "Coluna Ação × canon: 1 erro em 25 — os 2 painéis extras do Dashboard viraram 'Nada — vivo à frente' enquanto o `Dashboard-visual-comparison.md` os lista como decisão PENDENTE de [W] (item 2 de 'O que NÃO decidir')"
  - "Âncoras `data-contract` (9/9), paths de frontmatter, `prototipo_sha`, claims de ausência (6/7) e scan PII: 0 erros"
---

# Refutação GT-G5 — lote PR #6917 (`claude/gap-ponto-3-telas`)

> Protocolo: [`PROTOCOLO-REFUTADOR-BACKFILL.md`](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2/§3/§4.
> Base medida: `origin/main` = `4fbab283a7` (= merge-base do branch) · HEAD do lote = `37ead00f24` · repo **não** raso (`git rev-parse --is-shallow-repository` = false).
> Refutador: Claude (Fable 5.1) em sessão fresca, worktree `heuristic-bose-8ee418`, sem contexto do gerador. ⚠️ O worktree estava com o HEAD **no próprio commit do lote** (disco = branch, conferido por `git hash-object` nos 8 arquivos); todo lado "vivo"/"protótipo" foi lido de `origin/main` via `git show`, e o branch **não toca** `prototipo-ui/`, `resources/js/Pages/Ponto/`, `Modules/Ponto/` nem `scripts/` (diff vazio).
> Calibração: li o [`2026-09-06-refutacao-gt-g5-lote-6897.md`](2026-09-06-refutacao-gt-g5-lote-6897.md) (§Grupo 3 + §Padrão do erro) e os cabeçalhos/vereditos de r8 e r9, como o mandato pedia.

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador — refutador `fable`; o gerador vem assinado `[C]` no commit (opus/sonnet pelo padrão do repo; o ledger preenche)
- [x] Amostra: 100% anchors (tipo `anchors`) — não há prosa destilada; sem seleção aleatória, logo sem seed
- [x] Cada item verificado contra `origin/main` (`git show origin/main:<path>` com `MSYS_NO_PATHCONV=1`, `git ls-tree`, `sed -n '<n>p'` sobre cópias de main), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII nas 580 linhas `+` do diff, 6 padrões + nomes, cada um com controle positivo — 0 hits
- [x] `error_rate_pct` calculado: **12,50%** (≥ 2 → reprovado)
- [ ] Entry no ledger `governance/sdd-verification-ledger.json` — **não escrita por mim** (mandato: só este arquivo); o parent adiciona a entry apontando pra este path

## Escopo real do diff (medido)

`git diff --stat origin/main...origin/claude/gap-ponto-3-telas` = **8 arquivos** (+580 −15): 6 novos em `memory/requisitos/Ponto/` (`dashboard-index-gap.md` + `.map.json` · `espelho-index-gap.md` + `.map.json` · `espelho-show-gap.md` + `.map.json`) e 2 modificados em `scripts/design-sync/state/` (`application-report.json`: 3 entries `anchored→compared`, contadores 62→59/4→7; `applications.json`: +3 entries com `mapSha256`/`targetSha256`). Confere com o enunciado.

## Tabela por grupo

| # | Grupo | Itens verificados | Erros confirmados | Como mediu |
|---|---|---|---|---|
| 1 | Citações `arquivo:linha` + referências a canon nos 3 gap.md (Dashboard 38 · Espelho/Index 33 · Espelho/Show 39) | 110 | **18** | `sed -n '<n>p'` sobre `git show origin/main:<path>`; canon lido inteiro |
| 2 | Claims de ausência (varreduras contadas) | 7 | **1** | `rg --hidden -g '!.git/**'` com rc e controle positivo por sonda |
| 3 | Coluna **Ação** × canon citado (25 partes) | 25 | **1** | `Dashboard-visual-comparison.md` · `COLAR-NO-CODE-ponto-ondas.md` · 2 contratos · playbook 08 · 3 charters |
| 4 | Os 3 `.map.json`: schema (3) · `prototipo.arquivo` (3) · `vivo.arquivo` (3) · 9 `vivo.ancora` · `prototipo_sha` (3) · `--strict` (1) · ranges proto (23, excl. `n/a`) · ranges vivo (21) · `acao` == tabela (25) | 91 | **11** | `design-code-map-check --check --strict` · `gerar-map.mjs` (regeneração) · `grep -c data-contract` · `sed -n` nos ranges |
| 5 | Scan PII + BRL (6 padrões + nomes) nas linhas `+` | 7 | 0 | grep com controle positivo cada |
| 6 | Frontmatter (parse `fmVal` + js-yaml estrito ×3 · 7 paths · 3 ids) + estrutura das 3 tabelas | 16 | **1** | node/js-yaml · `doc-id-index --check-collisions` · awk contando barras verticais por linha |
| | **Total** | **256** | **32** | **error_rate = 12,50%** |

## Grupo 1 — REFUTADOS (18 de 110)

Régua: a linha citada tem de conter o que a prosa diz que ela contém. Linha ao lado (±1) **conta como erro** — é o mandato ("linha fabricada ou deslocada = ERRO") e é o que a doutrina do repo chama de âncora que "apodrece no primeiro refactor" (§5 2026-07-26). Todas as linhas abaixo foram impressas direto de `origin/main`.

### `memory/requisitos/Ponto/dashboard-index-gap.md` — 6 de 38

| Citação no gap | O que está na linha (origin/main) | Onde está de fato |
|---|---|---|
| `ponto-page.jsx:37` (`Nota contrato="painel-nota-fechamento"`) | `:37` = `<>` | `:38` |
| `Dashboard/Index.tsx:155` ("a copy `DIVERGENCIA` do contrato está em :155") | `:155` = `O que trava o fechamento de {mes}` (título) | `DIVERGENCIA` em `:164` e `:170` |
| `ponto-page.jsx:50` (sub-rótulo `limite {N}h/dia (Art. 59)`) | `:50` = KPI **Faltas hoje** | `:51` (KPI HE do mês) |
| `ponto-page.jsx:57-77` (fila de aprovações) | `:57` é o `acao=` do Card (a abertura com `contrato=` está em `:56`); `:74` é linha vazia e `:75-77` já são o Card **Atividade recente** | `:56-73` (o próprio map.json diz 56-73) |
| `ponto-page.jsx:79-88` (atividade recente) | `:79` = `<b>{m.nome}</b>` (meio do map); `:86-88` = fechamento do `pt-cols-2`, `<Legal />` e `</>` | `:75-85` (o map diz 75-85) |
| `ponto-page.jsx:89` (`<Legal />` "acionado no ponto-page.jsx:89") | `:89` = `);` | `:87` (o map diz 87) |

Nota: o gap afirma que o comentário `Dashboard/Index.tsx:120` "cita a região do protótipo (`ponto-page.jsx:37` …)". O comentário cita a **âncora de símbolo** (`§Nota contrato="painel-nota-fechamento"`, com a instrução *"nunca linha — re-localize com grep"*). O `:37` é invenção do gap, e errada.

### `memory/requisitos/Ponto/espelho-index-gap.md` — 12 de 33

| Citação no gap | O que está na linha (origin/main) | Onde está de fato |
|---|---|---|
| `Espelho/Index.tsx:83` (`htmlFor="mes"`) | `:83` = `id="mes"` | `htmlFor="mes"` em `:79` |
| `Espelho/Index.tsx:74-79` (`<Input placeholder="Buscar … (em breve)" disabled>`) | `:74` = `disabled` (o `placeholder` fica em `:73`, **fora** do range); `:77-79` já são o bloco do mês (`<label htmlFor="mes">`) | `:72-76` |
| `ponto-page.jsx:120-121` (busca funcional) | `:120` = `</select></div>` da Escala | label `:121` + `<input id="es-q">` `:122` |
| `ponto-page.jsx:105` ("filtra `nome + matricula`") | `:105` = `return true;` | `:103` |
| `ponto-page.jsx:103` ("filtrando por `escala_atual_id`") | `:103` = o filtro de **busca** | `:102` |
| `ponto-page.jsx:127` (contador "N dias em divergência na competência") | `:127` = `</div>` | `:126` |
| "`Dashboard/Index.tsx` calcula `divergencias` para a nota de fechamento" | o `.tsx` **não calcula** nada — passa `kpis?.divergencias_mes ?? 0` (`:234`) para `NotaFechamento`; a prop é `Kpis.divergencias_mes` (`:36`) | quem calcula é `DashboardController.php:105` (`'divergencias_mes' => ApuracaoDia::where(...)`) |
| `Espelho/Index.tsx:107-111` (5 colunas Matrícula · Colaborador · CPF · E-mail · Espelho) | `:107` = Colaborador (Matrícula está em `:106`, fora); `:111` = `</tr>` | `:106-110` |
| `Espelho/Index.tsx:96-98` (*"Nenhum colaborador com controle de ponto ativo."*) | `:96` = `<CardContent>`, `:97` = `{rows.length === 0 ? (`, `:98` = `<div …>` — a frase **não está no range** | `:99` (bloco `:97-100`) |
| `ponto-page.jsx:131` (*"Nenhum colaborador com esse filtro nesta competência."*) | `:131` = `{pg.fatia(lista).map(` | `:130` |
| `ponto-page.jsx:155` (`Pager`) | `:155` = `</>` | `:153` |
| `ponto-page.jsx:108` ("15 por página") | `:108` = `totalDiverg = …` | `:107` (`usePagina(lista.length, 15)`) |

### `memory/requisitos/Ponto/espelho-show-gap.md` — 0 de 39

Todas as citações do Show conferem: as 5 âncoras `data-contract` (`:179` · `:220` · `:243` · `:281` · `:389`), os rótulos (`:187-196` · `:221-226` · `:250`/`:258` · `:303-309` · `:390`/`:395`/`:396`/`:399`/`:402`), os comentários (`:178` · `:216-217` · `:162`), a rota `/{id}/imprimir` (`:147`), `only: ['mes','totais','linhas']` (`:117`), os tooltips de `origem` (`:353` · `:504`) e, no protótipo, `EspelhoShow`/`GradeMes`/`DiaDrawer`/`FolhaEspelho` (`:329-445` · `:160-206` · `:209-269` · `:272-326`), `:411-412`, `:415`, `:367`, `:343`, `:245`, `:345-354`. Ressalvas não contadas: (a) *"os 8 rótulos do contrato saem em :187-196"* lista 7 — o 8º item de `copy` do contrato é o título *"Dados do colaborador"*, em `:181`; a paridade literal se sustenta; (b) *"`<input type="month">` `:166`"* — o elemento é `:163-169` e `:166` é o `value`; dentro do elemento, aceito.

## Grupo 2 — claims de ausência (7 itens · 1 REFUTADO)

Todas re-rodadas com `rg --hidden -g '!.git/**'`, exit code lido (rc=1 = "não achou"; rc≠0/1 = falha) e controle positivo antes de aceitar zero.

| Claim do lote | Medido | Controle positivo | Veredito |
|---|---|---|---|
| (a) `[Aa]nular` em `resources/js/Pages/Ponto/**` = 0 | 0 hits, **rc=1** | mesmo padrão no repo inteiro: 318 arquivos | ✅ |
| (b) `AnularMarcacaoRequest` = 10 hits, todos em `memory/**` + o próprio arquivo; zero em controller/rota/Page | 10 linhas em 6 arquivos: `Modules/Ponto/Http/Requests/AnularMarcacaoRequest.php` (:11, :38) · `SUPERFICIE.md:37` · `README.md:173` · `CHANGELOG.md` (:33, :38, :42, :43) · `AUDIT-SENIOR-2026-05-25.md:154` · **`espelho-show-gap.md:29` (o próprio lote)**. Em `origin/main`: `git grep -l` = 5 arquivos. Zero em controller/rota/Page | `git ls-tree origin/main` acha o blob | ✅ (o "10" só fecha contando o próprio gap.md; a claim substantiva — órfão — é verdadeira) |
| (c) `Modules/Ponto/Http/routes.php:39-41` = só 3 GET, sem anulação | `:39` index · `:40` show · `:41` imprimir, todos `Route::get`; `grep -ci anul` no arquivo = 0 (rc=1) | as rotas POST de aprovações aparecem em `:45-47` | ✅ |
| (d) `divergenc` = 0 em `Espelho/Index.tsx` | 0, **rc=1** | mesmo padrão em `Show.tsx` = 10 | ✅ |
| (e) `escala` = 0 em `Espelho/Index.tsx` | 0 (com `-i`), **rc=1** | `Show.tsx` = 3 | ✅ |
| (f) `Drawer\|Sheet\|NSR\|hash` em `Show.tsx` só como tooltip | 0 hits, **rc=1** — o padrão não casa em lugar nenhum; `origem` aparece em `:62` (interface) + `:353`/`:504` (title) | `origem` casa 3× | ✅ (a redação "0 fora desses dois tooltips" é imprecisa: é 0 em todo o arquivo; os tooltips têm `origem`, não NSR/hash) |
| (g) "O `MarcacaoService` tem a regra (**10 ocorrências de `anula*`**)" | `rg -c 'anula'` = **7 linhas / 8 ocorrências**; com `-i` = **12 linhas / 13 ocorrências**; `rg -o -i 'anul'` = 13. **Nenhuma variante devolve 10** | — | ❌ número não reproduzível (§5 2026-07-17: número sem comando ao lado) |

## Grupo 3 — coluna Ação × canon (25 partes · 1 REFUTADO)

Procurei ativamente as duas direções do erro do #6897 — *Decidir.* onde já foi decidido e *Nada* onde há gap real não decidido — contra o que o canon **diz**, não contra a prosa do gap.

**Fontes lidas inteiras em `origin/main`:** `Dashboard-visual-comparison.md` (§3 escala 12,5→16px · §4 header · §5 sub-nav · §6 blocos · §7 vazios · §"O que NÃO decidir": *"Três coisas dependem do [W]: 1. A escala … 2. Os 2 painéis extras … 3. As 3 abas ausentes"*) · `COLAR-NO-CODE-ponto-ondas.md` (§0 lei 1 append-only e lei 5 *"Autoridade de navegação = shell.menu → PontoSubNav → PageHeaderTabs … divergência declarada, e o dono é a produção"* · §1 frente 5 âncora `Espelho/Show.tsx (botão Anular)` ⛔ [W] 1–4 · §4.3 *"Competência fechada desabilita Anular no Espelho/Show"* · §5 `ponto_escalas` na lista de tabelas · RESÍDUO decisões 1–4 = estado da competência · permissão · exceções assinadas · reabrir) · `ponto-painel.contract.json` (4 seções, copy `DIVERGENCIA`, 6 KPIs) · `ponto-espelho.contract.json` (`tela: Show`, `alvo` inclui o Index, 5 seções todas do Show, `_pendente_w` "quantos meses ficam navegáveis") · playbook `08-feriados-puxar.md` (a régua *"só vira pedido se for comportamento, nunca layout"* está no item 3 da lista "O que esta thread faz", l.18 — não há heading "§3") · os 3 charters (todos `draft`, Non-Goals **não aprovados** por [W]; o de `Espelho/Index` deixa *"Confirmar se a busca 'em breve' entra no escopo"* como pendência).

| Tela · parte | Ação no lote | Canon | Veredito |
|---|---|---|---|
| Dashboard · Nota / KPIs / Fila / Atividade / Rodapé legal | Nada — paridade | contrato 4 seções + ordem; `data-contract` nas 4; vc §6 "fiéis" | ✅ (5) |
| Dashboard · **Gráfico "Últimos 7 dias" + painel "O que precisa da sua atenção"** | **Nada — vivo à frente.** "Registrado para não virar bug" | vc §"O que NÃO decidir" **item 2**: *"produção evoluiu além da âncora. **Ou a âncora incorpora, ou eles saem.** Não assumir que 'extra = errado'"* — listado entre as *"três coisas [que] dependem do [W]"* | ❌ **"Nada" sobre decisão PENDENTE de [W]**. O próprio lote tratou o **item 1** da mesma lista (escala) como *Decidir.* e o item 2 como *Nada* — a inconsistência é a prova |
| Dashboard · Estados vazios | Nada — vivo à frente | vc §7 *"Não é gap: é produção à frente"* (fora da lista de decisões) | ✅ |
| Dashboard · Escala tipográfica | Decidir. (aponta o item 1) | vc item 1, pendente | ✅ |
| Dashboard · Header | Decidir. (frente 5 travada) | vc §4 diverge; nenhum charter/contrato decide o subtítulo; `Fechamento` = frente 5 ⛔ [W] 1–4 | ✅ |
| Dashboard · Sub-navegação | Nada — divergência declarada, dono é a produção | COLAR §0 lei 5 (literal) + §7 item 4; vc item 3 "capacidade não-construída" | ✅ |
| Espelho/Index · Filtro de mês | Nada — vivo à frente | `type=month` ⊃ `<select>` de 2 meses; `_pendente_w` segue aberto | ✅ |
| Espelho/Index · Busca / Escala / Só divergência / Colunas | Decidir. (×4) | charter draft; pendência explícita da busca; nada decide escala/divergência/colunas; `ponto_escalas` é tabela canônica (COLAR §5) | ✅ (4) |
| Espelho/Index · Estado vazio / Paginação | Nada | vc §7 cita a frase literal; paginação `paginate(25)` + `only` | ✅ (2) |
| Espelho/Show · 5 seções do contrato + Navegação de mês | Nada — paridade (a folha: "vivo à frente na rota de impressão") | 5 `data-contract` presentes; copy literal; `/imprimir` em `routes.php:41` | ✅ (6) |
| Espelho/Show · Drawer do dia | Decidir. (na prosa) | gap real; charter Non-Goal *"não edita nem cria marcações"* não fecha um drawer só-leitura; COLAR frente 5 | ✅ na prosa — **mas a célula Ação da tabela está quebrada** (ver Grupo 6) |
| Espelho/Show · Anular | Decidir. (frente 5 ⛔ [W] 1–4) | COLAR §1 nomeia o botão como âncora da frente 5; §4.3; RESÍDUO 1–4 | ✅ |

## Grupo 4 — os 3 `.map.json` (91 itens · 11 REFUTADOS)

**O que confere (0 erros):** schema válido nos 3 (o `--strict` parseia e não acusa nada do Ponto); `prototipo.arquivo` = `prototipo-ui/cowork/ponto-page.jsx` (blob em `origin/main`, último commit `f02102261d` 2026-08-20) e `vivo.arquivo` (3 `.tsx`, blobs em main; `n/a` só nas partes ausentes no vivo, legítimo); **as 9 âncoras declaradas existem** como `data-contract="<id>"` (`grep -c` = 1 em cada; controle negativo `nao-existe` = 0): `painel-nota-fechamento` · `painel-kpis` · `painel-fila-aprovacoes` · `painel-atividade` · `espelho-dados-colaborador` · `espelho-totais` · `espelho-modo-visao` · `espelho-apuracao-diaria` · `espelho-folha-impressao`; `prototipo_sha = sha256:587a1e3f2479` nos 3, **não-stale** (recomputado por `gerar-map.mjs --atualizar` → mesmo sha, e os 3 maps regenerados saem byte-idênticos exceto `gerado_em`). `related_prototype` dos 3 charters = `ponto-page.jsx` (não revogado).

**`node scripts/governance/design-code-map-check.mjs --check --strict` → rc=1**, com **1 DRIFT — e não é do lote:** `memory/requisitos/Cliente/clientes.map.json` STALE (`salvo=sha256:8f284ad79fb3 · atual=sha256:2be4c00c452a`). Causa em `origin/main`: `#6893` (`2ae9a5a064`, 06:19) re-exportou `prototipo-ui/cowork/clientes-page.jsx` e `#6897` (`01a4044c0e`, 06:31) entrou 12 min depois com o sha velho — o branch do lote **não toca** nenhum dos dois arquivos. O item conta como ✅ pro lote (0 drift no Ponto), mas o gate vai sair vermelho no PR até alguém rodar `gerar-map.mjs Cliente/Index --atualizar` no main.

**Ranges de linha (os erros):** o `_doc` do map diz que o range vivo é informativo, mas "TODO em arquivo/linhas = âncora ainda não preenchida (grep -n real, **nunca fabricar**)" — range que aponta pra outro bloco é o que a regra proíbe.

| Map · parte | Lado | Range no map | O que o range cobre em origin/main | Onde está de fato |
|---|---|---|---|---|
| dashboard · `header-titulo-subtitulo-e-acoes` | proto | `448-476` | `function PontoPage` até `let corpo = null` — estado, hooks e cálculo de `abas`; **nenhuma linha do header** | `MP.Header` em `:495-505` |
| espelho-show · `totalizadores-do-mes` | proto | `397-408` | começa no 2º KPI (`Atraso`), pula a abertura `data-contract="espelho-totais"` (`:395`) e engole a `Nota` de divergências (`:404-407`) | `:395-402` |
| espelho-index · `busca-por-nome-matricula` | proto | `120-121` | `:120` = `</select></div>` da Escala | `:121-122` |
| espelho-index · `estado-vazio` | proto | `131` | `{pg.fatia(lista).map(` | `:130` |
| espelho-index · `paginacao` | proto | `155` | `</>` | `:153` (`<Pager>`) |
| dashboard · `grafico-ultimos-7-dias-e-painel-o-que-precisa-da-sua-atencao` | vivo | `396-479` | `layout` export + skeletons (`KpiSkeleton`…`RowsSkeleton`) + corpo do `BarChart7Days` — **não** o Card "Últimos 7 dias" nem o `AlertInbox` | Card `:315-327` · `<AlertInbox>` `:376-378` |
| dashboard · `estados-vazios` | vivo | `438-479` | fim do `RowsSkeleton` + `BarChart7Days` — nada de estado vazio | *"Nenhuma intercorrência aguardando decisão."* `:354-358`; *"nenhuma marcação hoje"* `:257` |
| espelho-index · `busca-por-nome-matricula` | vivo | `74-79` | começa em `disabled` (o `placeholder` está em `:73`, fora) e termina no `<label htmlFor="mes">` | `:72-76` |
| espelho-index · `colunas-da-tabela` | vivo | `107-111` | pula `Matrícula` (`:106`) e termina em `</tr>` | `:106-110` |
| espelho-index · `estado-vazio` | vivo | `96-98` | `<CardContent>` + o ternário — a frase está em `:99`, fora do range | `:97-100` |

**`acao` × tabela (1 erro):** `espelho-show.map.json` · `drawer-do-dia-marcacoes-com-nsr-origem-rep-hash` → `"acao": "Sheet"`, `"_acionavel": true`. Reproduzido em sessão fresca: `node prototipo-ui/gerar-map.mjs memory/requisitos/Ponto/espelho-show-gap.md` (sem `--atualizar`) deriva exatamente `acao=Sheet` — a causa é a tabela-fonte (Grupo 6). O map registra como ação uma palavra solta, e a Fase 4 (`consumir-map.mjs`) é quem consome esse campo. Ressalva não contada: `folha-de-impressao` tem `status: "paridade"` com `acao: "Nada — vivo à frente…"` (status e ação discordam; sem consequência mecânica hoje).

## Grupo 5 — scan PII + BRL (0 hits)

Base: `git diff origin/main...origin/claude/gap-ponto-3-telas | grep '^+'` → **580 linhas**. Controle positivo = arquivo sintético com um exemplar de cada formato (não reproduzido aqui — o scan do CI é cego a contexto).

| Padrão | Regex | Hits | Controle |
|---|---|---|---|
| CPF pontuado | `[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}` | 0 (rc=1) | 1 |
| CPF cru 11 dígitos | `(^\|[^0-9])[0-9]{11}([^0-9]\|$)` | 0 (rc=1) | 1 |
| CNPJ | `[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}` | 0 (rc=1) | 1 |
| Telefone BR | `\(?[0-9]{2}\)?[ -]?9?[0-9]{4}[ -]?[0-9]{4}` | **6 falsos-positivos** (rc=0) — todos runs de dígitos dentro de `mapSha256`/`targetSha256` hex nos 2 state files (`a862e6089108…`, `fa0bb3d3aa2c…`, `a7439b370b73…`); 0 telefones | 1 |
| E-mail | `[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}` | 0 (rc=1) | 1 |
| Valor em reais | cifrão seguido de dígito | 0 (rc=1) | 1 |
| Nomes (Larissa · Martinho · ROTA LIVRE · WR2 · Eliana) | — | 0 (rc=1 via `PIPESTATUS`) | `ponto-page.jsx` casa 1× (`ROTA LIVRE`, mock do protótipo) |

## Grupo 6 — frontmatter + estrutura (16 itens · 1 REFUTADO)

- Os 3 frontmatters parseiam por `fmVal` (regex `^key:` de `gerar-contrato.mjs:36`) **e** por js-yaml estrito. Ressalva: `gerado_em: 2026-09-06` sem aspas → js-yaml devolve `Date` (`2026-09-06T00:00:00.000Z`), não string — `fmVal` não se importa; a `memory-schema-preflight` manda datas entre aspas. Não contado (não há schema de gap.md fiado a gate).
- Paths: `prototipo` (×3), `tela_viva` (×3) e `comparacao` (Dashboard) existem no disco e em `origin/main`. Arquivos sem CRLF.
- `id`: `requisitos-ponto-{dashboard-index,espelho-index,espelho-show}-gap` — `doc-id-index.mjs --check-collisions` → **0 colisão em 2599 ids**, rc=0.
- **Estrutura das tabelas (1 erro):** `awk` contando `|` por linha de tabela (esperado 4 = 3 colunas): `espelho-show-gap.md:28` tem **7**. A célula "Estado no vivo" contém `` `Drawer|Sheet|NSR|hash` `` sem escapar — GFM parte a linha em 7 células; a coluna **Ação** renderiza `Sheet`, e o texto real da ação (*"**Decidir.** É leitura de dado que o vivo já tem…"*) cai numa 7ª coluna inexistente. Mesma família do achado extra #1/#2 do #6897 (pipe em code-span), agora atingindo a coluna que vira `acao` no map.

## Padrão do erro (sistemático, não pontual)

1. **Citação de linha por memória, não por `grep -n`.** 18/110 citações dos gap.md e 10/44 ranges dos maps estão deslocados — e nas **duas** direções (+1, −1, −2, +4, e um bloco inteiro errado no header do proto), então não é um shift de versão do arquivo: `ponto-page.jsx` não muda desde 2026-08-20 e o `prototipo_sha` bate. É o gerador escrevendo o número "de cabeça". Sintoma que denuncia: o **map** do Dashboard tem os ranges certos (`56-73`, `75-85`, `87`) enquanto a **prosa** do mesmo lote cita `57-77`, `79-88`, `89` — dois processos, um mediu, o outro não.
2. **`|` dentro de code-span em tabela** — reincidência do #6897, agora com dano funcional (`acao: "Sheet"`).
3. **Contagem sem comando** ("10 ocorrências de `anula*`") — §5 2026-07-17.
4. Grupo 3 quase limpo (1/25): o gerador aprendeu a ler a coluna de veredito. O erro que sobrou é o espelho do anterior — *Nada* sobre item que o canon lista como decisão pendente de [W].

Correção honesta: re-derivar **todas** as citações `arquivo:linha` por `grep -n` real (o RUNBOOK já manda), escapar `\|` em code-span (ou trocar por `/`), regenerar os 3 maps com `gerar-map.mjs --atualizar` depois de corrigir a tabela do Show, reclassificar os 2 painéis extras do Dashboard como *Decidir.* apontando o item 2 do `Dashboard-visual-comparison.md`, e trocar "10 ocorrências" pelo comando + número. Depois, re-refutar o lote inteiro (§2.6) — erro de método espalhado, não 32 células.

## Comandos reproduzíveis

```bash
git fetch origin && git rev-parse --is-shallow-repository            # false
git diff --stat origin/main...origin/claude/gap-ponto-3-telas         # 8 arquivos
git diff --stat origin/main origin/claude/gap-ponto-3-telas -- prototipo-ui/ resources/js/Pages/Ponto/ Modules/Ponto/ scripts/   # vazio
MSYS_NO_PATHCONV=1 git show origin/main:prototipo-ui/cowork/ponto-page.jsx | sed -n '37,38p;50,51p;56,57p;73,77p;87,89p;102,108p;120,131p;153,155p;395,408p;448p;476p;495,505p'
MSYS_NO_PATHCONV=1 git show origin/main:resources/js/Pages/Ponto/Dashboard/Index.tsx | sed -n '120p;131p;138p;155p;164p;170p;231p;234p;315,327p;354,358p;396,479p'
MSYS_NO_PATHCONV=1 git show origin/main:resources/js/Pages/Ponto/Espelho/Index.tsx | sed -n '72,79p;82,83p;96,100p;106,111p'
rg --hidden -g '!.git/**' -n '[Aa]nular' resources/js/Pages/Ponto/; echo rc=$?                 # 1
rg --hidden -g '!.git/**' -n 'AnularMarcacaoRequest'                                             # 10 linhas / 6 arquivos (incl. o gap.md)
rg --hidden -n 'divergenc' resources/js/Pages/Ponto/Espelho/Index.tsx; echo rc=$?               # 1
rg --hidden -n -i 'escala'  resources/js/Pages/Ponto/Espelho/Index.tsx; echo rc=$?              # 1
rg --hidden -n 'Drawer|Sheet|NSR|hash' resources/js/Pages/Ponto/Espelho/Show.tsx; echo rc=$?    # 1
rg -c 'anula' Modules/Ponto/Services/MarcacaoService.php; rg -c -i 'anula' Modules/Ponto/Services/MarcacaoService.php   # 7 · 12 (não 10)
for a in painel-nota-fechamento painel-kpis painel-fila-aprovacoes painel-atividade; do grep -c "data-contract=\"$a\"" resources/js/Pages/Ponto/Dashboard/Index.tsx; done
for a in espelho-dados-colaborador espelho-totais espelho-modo-visao espelho-apuracao-diaria espelho-folha-impressao; do grep -c "data-contract=\"$a\"" resources/js/Pages/Ponto/Espelho/Show.tsx; done
node scripts/governance/design-code-map-check.mjs --check --strict; echo rc=$?                  # 1 — DRIFT só em Cliente/clientes.map.json (main)
node scripts/governance/doc-id-index.mjs --check-collisions; echo rc=$?                          # 0
node prototipo-ui/gerar-map.mjs memory/requisitos/Ponto/espelho-show-gap.md | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>console.log(JSON.parse(s).partes[6].acao))'   # Sheet
awk '/^\|/{n=gsub(/\|/,"|"); if(n!=4) print FILENAME":"NR" pipes="n}' memory/requisitos/Ponto/*-gap.md    # espelho-show-gap.md:28 pipes=7
git log --format='%h %ad %s' --date=iso origin/main | grep -nE '#6893|#6897'                     # 6893 (06:19) antes de 6897 (06:31)
git diff origin/main...origin/claude/gap-ponto-3-telas | grep '^+' | grep -nE '[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}'; echo rc=$?   # 1
```

## Resultado

```json
{"itens_verificados": 256, "erros_confirmados": 32, "error_rate_pct": 12.5, "pii_hits": 0, "veredito": "reprovado"}
```

Só o Grupo 1 (citações `arquivo:linha`): 18/110 = 16,36%. Só os ranges dos maps: 10/44 = 22,73%. Só o Grupo 3: 1/25 = 4,00%. Excluindo os 5 ranges de proto e os 5 de vivo dos maps (se alguém os considerar "informativos"): 22/246 = 8,94% — segue reprovado em qualquer recorte.
