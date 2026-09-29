---
date: "2026-09-29"
hour: "19:30 BRT"
duration: "2h"
topic: "Refutação GT-G5 r2 do lote #8194 (30 arquivos em memory/requisitos — 25 map.json + 5 gap.md): 982 itens, 78 refutados (7,9%), PII 0 — REPROVADO"
authors: ["C"]
outcomes:
  - "Lote #8194 r2 REPROVADO: 78 de 982 itens refutados (error_rate 7,94% ≥ 2%) — 25 ranges de protótipo errados, 44 âncoras vivas de linha stale e 9 células acao que contradizem origin/main ou citam linha errada"
  - "Erros NOVOS do lote (linhas que o próprio diff escreveu): 6 ranges de Holidays deslocados uniformemente (-37) sobre âncoras já erradas; 4 vereditos de Governance re-emitidos como 'Decidir' quando o gap.md de origin/main registra CONSTRUÍDO/REJEITADO em 2026-09-09 (#7089); vivo de 'chave-de-presenca' apontando pra linhas sem as chaves aposentadas"
  - "Scan PII: 0 hits em 201 linhas +, 7 de 7 controles positivos casaram; paths 369/369 existem em origin/main; prototipo_sha 25/25 batem com o gerador; ancora.mjs resolve ✓ nas 25 telas"
prs: [8194]
us: []
related_adrs: []
---

# Session log 2026-09-29 — Refutação GT-G5 r2 · lote #8194

## TL;DR

**REPROVADO.** 982 itens verificados · **78 refutados** · error_rate **7,94 %** (limiar < 2 %) · PII **0 hits** (7/7 controles positivos). A maioria dos refutados é âncora de LINHA (protótipo ou vivo) que **não contém o que a parte afirma** — parte delas já estava errada em `origin/main` e o lote carimbou `gerado_em: 2026-09-29` sem re-grepar; 6 ranges de Holidays foram deslocados por aritmética uniforme (-37) em vez de `grep -n` real; e 4 células `acao` de Governance foram **re-emitidas** com `Decidir… Construir ou rejeitar por escrito` quando o próprio `gap.md` em `origin/main` já registra o item **CONSTRUÍDO** ou **REJEITADO por escrito** em 2026-09-09.

## Cabeçalho

| Campo | Valor |
|---|---|
| Base (`origin/main`) | `a71c2f2d052fc2c4b5f5009e9b59a93b337e3f21` |
| HEAD | `ab6d020db7993a592a0b9db214af430fddd3c0fe` |
| Repo raso | `false` (`git rev-parse --is-shallow-repository`) |
| Sessão fresca | sim — instância nova; **não** abri `memory/sessions/*refutacao*` (inclusive a r1 que está no diff deste PR) nem `memory/handoffs/` de hoje; não li corpo do PR/commit como evidência |
| Tier | refutador Fable 5.1; gerador do lote assinado `[CL]` (tier não declarado no lote — assumido ≤ Opus) |
| Working tree ao fim | limpo, exceto este arquivo |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Fable 5.1 — tier máximo disponível)
- [x] Amostra: 100 % anchors (todo path, toda `prototipo_sha`, todo range de protótipo e de vivo, toda célula `acao`/`status`, toda linha `+` dos gap.md); sem prosa amostrada — lote é tipo *anchors*
- [x] Cada item verificado contra `origin/main` (`git ls-tree`, `git show origin/main:<path>`, `git grep … origin/main`); ranges de protótipo verificados contra o protótipo importado no HEAD (o PR traz o handoff (43) junto — `prototipo_sha` é do conteúdo do HEAD)
- [x] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [x] Scan PII no diff — 7 padrões × controle positivo, 0 hits
- [x] `error_rate_pct` calculado: 7,94 → **não** é < 2
- [ ] Entry no ledger — **não escrevi** (mandato: reprovado devolve os refutados e para)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → **30 arquivos**, todos `M`: 25 `*.map.json` + 5 `*-gap.md` (Essentials 4, Governance 4, Ponto 22). O PR também altera 5 protótipos (`governance-page.jsx`, `hrm-extras.jsx`, `hrm-page.jsx`, `ponto-page.jsx`, `ponto-telas.jsx`) — por isso ranges de protótipo são medidos contra o HEAD; vivo (`resources/js/**`, `Modules/**`) não muda no PR e é medido contra `origin/main`.

Partes nos 25 maps: **194** · ranges de protótipo preenchidos: **173** · ranges de vivo preenchidos: **170** (31 com `data-contract`, 139 só-linha).

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---:|---:|---:|---|
| 1. Âncora existe em origin/main (gap_fonte + prototipo.arquivo + vivo.arquivo) | 369 | 369 | 0 | `git ls-tree origin/main -- <path>` por path; controle negativo `memory/requisitos/Ponto/nao-existe-xyz.map.json` → 0 linhas; controle positivo `relatorios-index.map.json` → 1 |
| 2. `prototipo_sha` bate com o gerador + `ancora.mjs` resolve ✓ | 50 | 50 | 0 | `node scripts/design/gerar-map.mjs <gap.md>` (esqueleto, stdout) → sha igual nos 25; `node scripts/design/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork/Wagner` → `âncora ✓` nas 25, nenhuma REVOGADA/MIS-ANCHOR |
| 3. Range de protótipo contém a parte | 173 | 148 | **25** | abri cada range no HEAD (`awk`) e localizei o conteúdo real com `grep -n`; refutado quando o range não contém o elemento que a parte/`acao` nomeia |
| 4. Range de vivo contém a parte | 170 | 126 | **44** | `git show origin/main:<path>` + `grep -n` do conteúdo real; `data-contract` conferido por `includes` (31/31 presentes); refutado só quando não há `data-contract` **e** a linha citada não contém o que se afirma (ou passa do EOF) |
| 5. `acao`/`status` × prosa × código | 194 | 185 | **9** | célula comparada com a coluna Ação do gap.md (esqueleto do gerador), com o banner do gap.md em origin/main, com o charter e com o código em origin/main |
| 6. Linhas `+` dos 5 gap.md (12 linhas de tabela) | 12 | 12 | 0 | cada afirmação conferida: PRs `gh pr view` (8076/8078/8124/8128/7884 MERGED), ADR 0014 §Emenda 2026-09-24, docblock do `EssentialsSettingsController`, blocos do protótipo no HEAD |
| 7. Máquinas derivadas | 7 | 7 | 0 | rc literal abaixo; os 2 rc=1 são ausência **pré-existente** em origin/main (não é achado do lote) |
| 8. Scan PII (7 padrões) | 7 | 7 | 0 | node sobre as 201 linhas `+`; 7/7 controles positivos casaram |
| **Total** | **982** | **904** | **78** | error_rate = 78 / 982 = **7,94 %** |

## REFUTADOS

Legenda: **[NOVO]** = linha escrita/alterada pelo próprio diff do lote; **[HERDADO+CARIMBADO]** = já errado em origin/main, não tocado pelo diff, mas re-emitido sob `gerado_em: 2026-09-29` (canon/código de origin/main é mais novo que o gap.md — o lote carimbou a data de hoje sem re-medir).

### Grupo 3 — ranges de protótipo (25)

#### memory/requisitos/Essentials/holidays-index.map.json · 6 partes [NOVO]
O lote aplicou **-37** uniforme a ranges que já estavam **6 linhas antes** do alvo em origin/main (o próprio gap.md de 2026-09-06 cita `:424-429` para o `<select>`, mas em origin/main `hrm-page.jsx:424-428` era o `<Kpis>` e o select vivia em `:430-435`). Resultado no HEAD (`hrm-page.jsx`, 595 linhas):
- `filtro-por-localidade` `387-392` — contém `<Kpis>` (387-391) + abertura do toolbar (392); o `<select aria-label="Filtrar por localidade">` está em **393-398**.
- `criar-editar-excluir-feriado` `395-416` — contém "Novo feriado" (404) mas **não** Editar/Excluir (botão `excluir(f)` em **425**); o gap afirma "Editar e Excluir" no range.
- `ordenacao-por-nome-inicio-dias` `399-402` — são os `<input type="date">`/Limpar/contador; os `<button class="mod-sort">` estão em **408, 409, 411**.
- `colunas-da-tabela` `399-403` — idem; os `<th>` estão em **408-412**.
- `kpis-do-topo` `381-385` — é o fim de `excluir()` + `return (`; `<Kpis items=` está em **387-391**.
- `aviso-ao-excluir` `374` — é `const salvar = (item) => {`; o `window.confirm("Excluir …")` está em **380**.
Prova de método: a citação `H.dias(a.ini,a.fim)` na `acao` de `colunas-da-tabela` foi corrigida para `:366` (certa — grep real), enquanto os ranges receberam só a aritmética.

#### memory/requisitos/Governance/governance-audit.map.json · 4 partes [HERDADO+CARIMBADO] (`governance-telas.jsx` não muda no PR)
- `botao-limpar-filtros` `106` — é `<div className="gov-kpis …" data-contract="auditoria-kpis">`; o botão `Limpar filtros` está em **102** (`temFiltro && <button … onClick={limpo}>`).
- `kpis` `110-114` — 110-111 fecham a div anterior, 112-114 abrem a tabela; os `A.Kpi` estão em **106-109**.
- `estado-vazio` `117-120` — `) : (<>` + `<table`; o `A.Vazio variant="filtered"` está em **114-116**.
- `rodape-do-teto` `147-149` — `)}</div></>`; o `<p className="gov-teto">` está em **143-145**.

#### memory/requisitos/Governance/governance-drift-alerts.map.json · 5 partes [HERDADO+CARIMBADO]
- `skeleton-carga-diferida` `162-163` — linha em branco + `return (`; o `setTimeout(… 800)` está em **159**.
- `estado-zero-drift` `179-180` — `DRIFT.map` + `<li>`; o `A.Vazio variant="done"` está em **175-176**.
- `nota-escopo-ilegivel` `198-203` — começa no `</A.Nota>` de fechamento; a nota "Escopo ilegível" está em **196-198**.
- `secao-modulos-sem-scope-md` `207-212` — só o fecho (207-208); a `Secao "Módulos sem SCOPE.md"` é **203-208**; 210-212 já é o Histórico.
- `secao-historico` `214-217` — `</>);}` + linha vazia; a `Secao "Histórico"` é **210-213**.

#### memory/requisitos/Governance/governance-dashboard.map.json · 2 partes [NOVO] (lote deslocou -1 ranges já errados)
- `lista-narrativas-brain-a-24h` `138-145` — é o `SAUDE.map` de KPIs + `A.Vazio offline`; a `<ul className="gov-narrativas">` está em **146-153**.
- `secao-mcp-fonte-ausente` `205-206` — é o seletor de período (`data-contract="mcp-periodo"`); o `A.Vazio variant="offline" title="mcp_audit_log não existe"` está em **209-210**.

#### memory/requisitos/Governance/governance-policies.map.json · 1 parte [NOVO]
- `lista-agrupada-por-categoria` `301-305` — é `busca` + início do `filtradas` (o filtro de busca, que a parte `busca-…` também cita como "filtra"); o agrupamento por categoria (`cats` useMemo) está em **309-313**.

#### memory/requisitos/Ponto/dashboard-index.map.json · 1 parte [NOVO]
- `header-titulo-subtitulo-e-acoes` `529-537` (`ponto-page.jsx`, 539 linhas) — o `<MP.Header modulo="Ponto" papel=…>` começa em **523**; o range pega só a prop `acoes` (529) e depois `Tabs`, `pt-body`, `avisoNode`, `window.PontoPage = PontoPage` (537). Título/subtítulo (524-525) ficam fora.

#### memory/requisitos/Ponto/espelho-index.map.json · 4 partes [HERDADO+CARIMBADO] (`ponto-page.jsx` só muda ≥ :452)
- `busca-por-nome-matricula` `121-122` — botão Limpar + linha vazia; o `PtCampo label="Buscar"` está em **119**.
- `colunas-da-tabela` `129` — `const t = D.totaisEspelho(…)`; `Tabela cols=[…]` está em **126**.
- `estado-vazio` `130` — `const bh = …`; o `<Vazio colSpan={9}>` está em **127**.
- `paginacao` `153` — `);`; o `<Pager …>` está em **150**.

#### memory/requisitos/Ponto/espelho-show.map.json · 2 partes [HERDADO+CARIMBADO]
- `seletor-de-modo` `410-413` — `dias.map` da tabela; o `CliSeg ariaLabel="Modo de visão"` (`data-contract="espelho-modo-visao"`) está em **407**.
- `navegacao-de-mes` `343` — `setDias((ds) => …` dentro de `anular`; `anterior/proximo` estão em **339-340**.

### Grupo 4 — ranges de vivo só-linha (44) — todos [HERDADO+CARIMBADO] salvo indicação

Regra aplicada: só refutei parte **sem** `data-contract` cuja linha citada não contém o que a parte afirma (conferido com `git show origin/main:<path>` + `grep -n`). Os vivos mudaram DEPOIS do `gerado_em` dos gap.md (Governance 2026-09-06 → `Audit.tsx`/`Policies.tsx` em 2026-09-09 #7089; Ponto 2026-09-28 → vários em 2026-09-28/29) e o lote re-emitiu os maps com `gerado_em: 2026-09-29`.

#### Essentials/settings-index.map.json
- `chave-de-presenca-dentro-do-hrm` vivo `Settings/Index.tsx:26-30` **[NOVO]** — a parte é sobre `grace_*`/`is_location_required`; em origin/main :26 é `calculate_sales_target_commission_without_tax`, :27 `}`, :29-30 `interface Props`. As 5 chaves saíram em `ad5733dc43` (2026-09-24, #7884). O lote pôs `prototipo: n/a` e afirma "aposentadas no vivo", mas deixou o vivo apontando pra linhas que não as têm — devia ser `n/a`.
- `agrupamento-em-cards` vivo `Settings/Index.tsx:63-180` — o arquivo tem **159** linhas: range passa do EOF (o próprio lote tratou "range passa do fim" como inválido nos `_nota` de `governance-page.jsx`).

#### Governance/governance-audit.map.json (`Audit.tsx`, 245 linhas; `PageHeader` :95, `GovernancaSubNav` :94, `KpiGrid` :101, `EmptyState` :187, `<table` :201)
- `header-pageheader` `79-83` → `hasFilter`/`clearFilters`.
- `abas-do-shell` `78` → comentário sobre default `'24h'`.
- `nota-registro-imutavel` `82` → linha vazia (a frase "Append-only enforced via trigger" está em :98).
- `filtro-resultado-com-4-valores` `148-150` → `SelectItem` de endpoints; o select de status (`value="error"`) está em :166.
- `kpis` `85-89` → opções do `router.get`.
- `tabela-de-entries` `166-197` → select de status + `EmptyState`; a tabela começa em :201.
- `estado-vazio` `161-164` → `SelectTrigger`; `EmptyState` em :187.
- `rodape-do-teto` `202-204` → `<thead>` "Quando".

#### Governance/governance-policies.map.json (`Policies.tsx`, 201 linhas; `PageHeader` :94, `SubNav` :93, `KpiGrid` :100, `AlertTitle` :116, `const toggle` :75, `toast.` :85, `EmptyState` :146)
- `header-pageheader` `71-75` → fim do `useMemo` + `const toggle`.
- `abas-do-shell` `70` → `),`.
- `kpis` `77-82` → `setPendingId`/`router.post`.
- `aviso-alternar-nao-deixa-rastro` `74` → linha vazia (o aviso EXISTE em :116-118 — ver Grupo 5).
- `linha-da-regra` `102-110` → `KpiCard`s + comentário.
- `toggle-por-linha` `52-66` → estados + início do `useMemo`.
- `feedback-do-toggle` `62` → `const q = search.trim()`.
- `estado-vazio-sem-regras` `84-85` → rollback + `toast.error`.

#### Ponto/aprovacoes-index.map.json
- `dialogo-de-aprovar` `Aprovacoes/Index.tsx:510-533` → é o `AlertDialog open={bulkOpen}` ("Aprovar em lote"); o diálogo individual `approveTarget !== null` começa em **:536**.

#### Ponto/banco-horas-index.map.json
- `acao-por-linha` `BancoHoras/Index.tsx:154-155` → `</table></div>`; o link "Movimentos" está em :146-147.
- `ordenacao` `BancoHorasController.php:41` → `->with('colaborador.user…')`; `orderByDesc('saldo_minutos')` está em **:42**.
- `estado-vazio` `Index.tsx:122-126` → `<thead>`; `<EmptyState` em :114.
- `rodape-legal` `Index.tsx:69` → `<Deferred`; `Portaria`/`append-only` → **0 hits** no arquivo (a parte diz "protótipo à frente": vivo devia ser `n/a`).

#### Ponto/banco-horas-show.map.json (`Show.tsx`, 388 linhas, reescrito em 2026-09-28)
- `cabecalho-do-colaborador` `98-112` → componente auxiliar (`<Stack …>` de KPI); "Voltar aos saldos" está em **:195**, `data-contract="bancohoras-colaborador"` em :187.
- `kpis-do-extrato` `114-125` → comentários/consts de CSS; "Saldo atual" em **:209**.
- `aviso-append-only` `171-178` → montagem do `subtitulo`/`tomSaldo`; "append-only"/"Portaria" em :214/:374.

#### Ponto/colaboradores-index.map.json (`Index.tsx`, 237 linhas)
- `estados-vazios` `103-116` → input de busca + select; `<EmptyState` em **:126**.
- `paginacao` `180-201` → badges Sim/Não + fim do tbody; `links.map` em **:210**.
- `nota-de-vinculacao-com-o-hrm` `79` → `const activeChips = [`; "HRM" em :134.
- `cpf-e-pis-redigidos` `144-153` → `<thead>` (só o rótulo "CPF / PIS"); a redação `redigirDigitos(c.cpf)` está em **:169-171**.

#### Ponto/dashboard-index.map.json (`Dashboard/Index.tsx`, 498 linhas, mudou em 2026-09-29 #8116/#8118)
- `rodape-legal` `382-392` → `DashboardIndex.layout` + skeleton; a nota "Portaria MTP 671/2021" está em **:365-375**.
- `sub-navegacao` `223` → `<KpiGrid cols={6} data-contract="painel-kpis">`; `<PontoAreaHeader active="dashboard">` em **:209**.

#### Ponto/escalas-index.map.json (`Escalas/Index.tsx`, 301 linhas, reescrito)
- `barra-e-acao-primaria` `65` → `permite_banco_horas: boolean;` (interface); "Nova escala" em :110/:122.
- `remover-escala` `47-52` → consts de pílula/`TIPOS_ESCALA`; o `AlertDialog` de remoção está em **:276** (comentário D-ESC-DESTROY em :87).
- `editar` `119-121` → link `/ponto/escalas/create`; "Editar" em **:201**.
- `paginacao` `150-164` → `EmptyState` + `<thead>`; `links.map` em **:240**.
- `estado-vazio` `72-83` → interfaces `Paginated`/`Props`; `<EmptyState` em **:147**.

#### Ponto/espelho-index.map.json (`Espelho/Index.tsx`, 160 linhas)
- `busca-por-nome-matricula` `72-76` → label/Input do mês; o input "Buscar … (em breve)" `disabled` está em **:65-66**.
- `estado-vazio` `97-100` → `<thead>`; o vazio está em :91.

#### Ponto/importacoes-index.map.json
- `data-da-importacao` `Importacoes/Index.tsx:119-121` → link "Ver"; `created_at_human` está em **:114-115**.

#### Ponto/importacoes-show.map.json (`Show.tsx`, 197 linhas, mudou em 2026-09-29 #8124/#8118)
- `tempo-real-enquanto-processa` `46-52` → `interface Props` + `estadoVariant`; o `setInterval`/`router.reload({only:['importacao']})` está em **:57-58**.
- `alerta-de-erro` `82-88` → botão "Baixar original"; o `<Alert variant="destructive">` está em **:91-95**.

#### Ponto/intercorrencias-index.map.json (`Index.tsx`, 243 linhas)
- `acao-por-linha` `203-207` → `</td></tr>))}</tbody></table>`; o link "Ver" está em **:199-200**.
- `linha-clicavel` `203-207` → idem (a parte afirma "só o link Ver (:203-207)").

### Grupo 5 — `acao`/`status` (9)

#### Governance/governance-audit.map.json · `botao-limpar-filtros` [NOVO — célula re-emitida]
Afirma `status: gap-parcial` · "Ação de reset dos 4 filtros num clique … **ausente** no bloco de filtros `Audit.tsx:92-156` e no vazio `:161-164`. Construir ou rejeitar por escrito." — origin/main `Audit.tsx:80-90` tem `hasFilter` + `clearFilters` e `:196` `action={hasFilter ? <Button onClick={clearFilters}>Limpar filtros</Button>}`; commit `7d82c38681` (2026-09-09, #7089). O dono da prosa, `governance-audit-gap.md:16` em origin/main, já diz **"Limpar filtros — CONSTRUÍDO"**. A célula reabre veredito registrado e contradiz o código.

#### Governance/governance-audit.map.json · `rodape-do-teto` [HERDADO+CARIMBADO]
Cita "mockup `governance-telas.jsx:111` e `:147-149`" e "rodapé `Audit.tsx:202-204` e KPIs `:85-89`" — `:111` é linha vazia (o KPI `de ${filtrado.length} no período` está em :107), `:147-149` são fechos (`gov-teto` em :143-145), `Audit.tsx:202-204` é `<thead>` e `:85-89` são opções do `router.get`. Nenhuma das 4 citações contém o que se afirma.

#### Governance/governance-policies.map.json · `aviso-alternar-nao-deixa-rastro` [NOVO — célula re-emitida]
Afirma `gap-parcial` · "o `Policies.tsx:68-119` **não tem nota nenhuma** entre KPIs e lista" — origin/main `Policies.tsx:113-118`: `<Alert>` com `<AlertTitle>Alternar não deixa rastro</AlertTitle>` (commit `7d82c38681`, #7089). `governance-policies-gap.md:17` em origin/main: **"Aviso 'Alternar não deixa rastro' … verificada antes de virar UI"** (construído). Ainda cita `Policies.charter.md:62` para o TODO de `mcp_governance_rule_history` — o TODO está em **:63** (:62 é "Esconder rules disabled").

#### Governance/governance-policies.map.json · `busca-de-politicas-vazio-no-results` [NOVO — célula re-emitida]
Afirma `gap-parcial` · "Busca local … **ausente** no `Policies.tsx:84-118`" e vivo `n/a` — origin/main `Policies.tsx:54` `useState('')` de `search`, `:58-73` `filteredGroups` filtrando `rule_key/name/description/category`, `:130-131` `<input type="search" placeholder="Buscar por chave, nome ou categoria…">`. `governance-policies-gap.md:16`: **"Busca local. `Policies.tsx` filtra … em memória"** (construído em #7089).

#### Governance/governance-dashboard.map.json · `regua-conformidade-…-selo-auto-declarado` [NOVO — célula re-emitida]
Afirma `gap-parcial` · "Decidir … Construir ou rejeitar por escrito." — `governance-dashboard-gap.md:15` em origin/main: **"Decidido em 2026-09-09 — régua 'Conformidade por artigo' REJEITADA nesta leva, por escrito"** (+ razão em :17-21). A célula reabre decisão registrada no próprio dono do inventário.

#### Ponto/banco-horas-show.map.json · `ajuste-manual` [NOVO — célula re-emitida]
"viola o Non-Goal 'não recalcula o saldo' (`Show.charter.md:39`)" — em origin/main `BancoHoras/Show.charter.md:39` é `(memory/requisitos/Ponto/banco-horas-show.map.json).`; o Non-Goal "❌ Não recalcula/reescreve o saldo" está em **:48**.

#### Ponto/banco-horas-show.map.json · `kpis-do-extrato` [HERDADO+CARIMBADO]
`status: divergencia-declarada` · "Emenda de charter proposta (dona: thread 27). Não é pedido de código antes da emenda. O Non-Goal … (`Index.charter.md:60`)" — o charter em origin/main (`Show.charter.md:40-42`) registra **"Construído depois, no mesmo dia … o controller entrega `acordo` (teto, piso, prazo) … Defendido por `UC-BHSHOW-05`"** e `Show.tsx:215-216` renderiza "Teto do acordo" e "Prazo de compensação". Canon mais novo vence: a divergência fechou. E `Index.charter.md:60` é linha vazia — o "❌ Não expira crédito na tela" está em **:66**.

#### Ponto/banco-horas-index.map.json · `saldos-por-colaborador` [HERDADO+CARIMBADO]
"`BancoHorasController.php:45` transforma a linha" — :45 é linha vazia em origin/main (a `transform` do `getCollection()` não está nessa linha).

#### Ponto/espelho-index.map.json · `filtro-so-com-divergencia-contador-da-competencia` [HERDADO+CARIMBADO]
Cita "`Dashboard/Index.tsx:234`, `divergencias={kpis?.divergencias_mes ?? 0}`, prop declarada em `:36`" — em origin/main :234 é `label="Presentes agora"` e :36 é o comentário `/** Pendentes com prioridade URGENTE … */` (arquivo mudou em 2026-09-29, #8116/#8118). `DashboardController.php:105` (`whereMonth`) confere.

## Observações não contadas

- **Erros novos vs herdados.** Dos 78, **16** são linhas que o próprio diff escreveu (Holidays ×6, Dashboard-Gov ×2, Policies lista ×1, Ponto Dashboard header ×1, Settings chave ×1, 4 células de Governance re-emitidas, `ajuste-manual` ×1 — e o `:366` correto de Holidays prova que o grep real era possível). Os outros **62** já estavam errados em origin/main e foram carimbados com `gerado_em: 2026-09-29`; o mandato trata isso como erro do lote (canon/código mais novo que a prosa). Se o gerador quiser contestar essa classe, a régua a discutir é a do RUNBOOK ("range de linha é INFORMATIVO") — mas o próprio lote usou "range passa do fim = inválido" nos `_nota` de `governance-page.jsx`, e `consumir-map.mjs` (Fase 4) lê esses ranges.
- **`acao` ≠ célula do gap.md em 19 partes** (esqueleto do gerador vs map) — é o comportamento documentado de `--atualizar` ("acao enriquecida à mão"); só contei quando o veredito/afirmação diverge, não a redação.
- **Governance/Audit e DriftAlerts** citam `governance-telas.jsx` no map enquanto o gap/`ancora.mjs` resolvem `governance-page.jsx` (bundle) — `prototipo_sha` não cobre `governance-telas.jsx`. Pré-existente, não contado.
- **Essentials/Settings**: `ancora.mjs` resolve `hrm-page.jsx` (bundle) e `n/a (herda PT-02)`, enquanto o map ancora em `hrm-extras.jsx`. Pré-existente, não contado.
- `settings-index-gap.md` linhas 32-35 (não tocadas pelo lote) seguem afirmando "10 de 10 chaves", "`:573-594`", "Tolerâncias :127 · Comportamentos :172" (arquivo tem 159 linhas) — fóssil datado de 2026-09-06 que agora contradiz a linha 3 da mesma tabela (aposentadas). Não contado (prosa não alterada), mas a tabela se contradiz.
- `requisitos-status.mjs Essentials|Governance --check` rc=1 porque `_STATUS-GENERATED.md` **não existe em origin/main** para esses módulos (`git ls-tree` → só `Ponto/_STATUS-GENERATED.md`). Não é achado do lote.
- `design-code-map-check.mjs --check --strict` sai rc=0 mas lista os maps "linha-only (frágil)" e não verifica conteúdo de range — a máquina não pegaria nenhum dos 69 refutados de Grupo 3/4.
- Erro meu de sondagem, corrigido antes de contar: a 1ª versão do scan de reais montou o padrão com o símbolo sem escape (âncora de fim de linha) — o controle positivo caiu e denunciou; refeito por charCode (§5 2026-08-19: par de barra colapsa no transporte por heredoc).
- Não chamei `brief-fetch` (skill Tier A) de propósito: o brief carrega handoffs/sessões de hoje, que o mandato proíbe abrir.

## Scan PII

Sobre as **201** linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`:

| Padrão | Hits | Controle positivo casou |
|---|---:|---|
| CPF pontuado (`ddd.ddd.ddd-dd`) | 0 | sim |
| CPF cru (11 dígitos isolados) | 0 | sim |
| CNPJ (`dd.ddd.ddd/dddd-dd`) | 0 | sim |
| Telefone BR (DDD + 8/9 dígitos com separador) | 0 | sim |
| Telefone cru (10-11 dígitos isolados) | 0 | sim |
| E-mail | 0 | sim |
| Valor em reais (símbolo da moeda seguido de dígito, espaço opcional) | 0 | sim (controle negativo `R 12` não casou) |

Nomes de cliente do CRM: nenhum nas linhas `+` (os únicos nomes são de persona interna já presentes em origin/main nos protótipos, fora do diff de `memory/requisitos`). **pii_hits = 0 · controles 7/7.**

## Máquinas derivadas (rc literal)

| Comando | rc |
|---|---:|
| `node scripts/governance/design-code-map-check.mjs --check --strict` | 0 |
| `node scripts/governance/requisitos-status.mjs Ponto --check` | 0 |
| `node scripts/governance/requisitos-status.mjs Essentials --check` | 1 (arquivo inexistente também em origin/main) |
| `node scripts/governance/requisitos-status.mjs Governance --check` | 1 (idem) |
| `node scripts/governance/plans-index.mjs --check` | 0 |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | 0 (0 colisões em 2869 ids) |
| `node scripts/governance/maquinas-inventario.mjs --check` | 0 (614 máquinas, 0 faltando, 0 ghost) |

## Comandos reproduzíveis

```bash
git rev-parse HEAD origin/main; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos | wc -l            # 30
# G1 — existência de cada path citado (369) em origin/main
git ls-tree origin/main -- resources/js/Pages/Essentials/Settings/Index.tsx      # 1 linha
git ls-tree origin/main -- memory/requisitos/Ponto/nao-existe-xyz.map.json       # 0 linhas (controle negativo)
# G2 — sha do gerador × map, e âncora de design
node scripts/design/gerar-map.mjs memory/requisitos/Essentials/holidays-index-gap.md | node -e 'process.stdin.on("data",d=>console.log(JSON.parse(d).prototipo_sha))'
node scripts/design/ancora.mjs Essentials/Holidays/Index --staging prototipo-ui/cowork/Wagner
# G3 — Holidays: onde o conteúdo está de verdade no HEAD
grep -n 'Kpis items\|hrm-toolbar\|Filtrar por localidade\|ord("nome")\|window.confirm(`Excluir\|H.dias(a.ini' prototipo-ui/cowork/Wagner/hrm-page.jsx
git show origin/main:prototipo-ui/cowork/Wagner/hrm-page.jsx | awk 'NR>=409&&NR<=455{print NR": "$0}'
# G4/G5 — Governance: código e prosa em origin/main
git show origin/main:resources/js/Pages/governance/Audit.tsx | awk 'NR>=80&&NR<=90||NR==196{print NR": "$0}'
git grep -n 'Alternar não deixa\|type="search"\|filteredGroups' origin/main -- resources/js/Pages/governance/Policies.tsx
git grep -n 'CONSTRUÍDO\|REJEITADA\|Busca local' origin/main -- memory/requisitos/Governance/governance-*-gap.md
git log --format='%h %cd %s' --date=short -1 origin/main -- resources/js/Pages/governance/Policies.tsx   # 7d82c38681 2026-09-09
# G5 — linhas citadas
git show origin/main:resources/js/Pages/Ponto/BancoHoras/Show.charter.md | awk 'NR==39||NR==48{print NR": "$0}'
git show origin/main:Modules/Ponto/Http/Controllers/BancoHorasController.php | awk 'NR>=41&&NR<=45{print NR": "$0}'
# G6 — PRs citados nos gap.md
gh pr view 8078 --json state,mergedAt; gh pr view 8124 --json state,mergedAt; gh pr view 8128 --json state,mergedAt; gh pr view 7884 --json state,mergedAt
git grep -n 'APOSENTADAS em 2026-09-24' origin/main -- Modules/Essentials/Http/Controllers/EssentialsSettingsController.php
# G8 — PII (padrões por charCode, sem literal do símbolo de moeda)
git diff origin/main...HEAD -- memory/requisitos | grep '^+' | grep -v '^+++' | wc -l   # 201
```

```json
{"itens_verificados": 982, "erros_confirmados": 78, "error_rate_pct": 7.94, "pii_hits": 0, "veredito": "reprovado"}
```
