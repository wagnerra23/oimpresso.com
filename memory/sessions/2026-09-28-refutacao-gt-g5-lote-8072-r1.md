---
date: "2026-09-28"
topic: "Refutação GT-G5 r1 do lote #8072 (7 gap.md + 7 map.json do Ponto — Aprovações, Intercorrências, Banco de horas, Escalas): 299 itens, 4 erros (1,34%), 0 PII"
authors: ["C"]
outcomes:
  - "Lote medido 100% contra origin/main e4289e688: 150 âncoras existem, 7 telas resolvem âncora ✓ sem revogação, 54 linhas de tabela conferidas linha a linha no código"
  - "4 erros confirmados: 3 `acao` de map.json carregam a cauda da coluna 'Estado no vivo' (o parser não honra `\\|`) e o cabeçalho de aprovacoes-index-gap.md erra a contagem/data das divergências consertadas no protótipo"
  - "error_rate 1,34% < 2 e pii_hits 0 (7/7 controles positivos) — veredito pela fórmula: aprovado; os 4 itens ficam listados para conserto"
prs: [8072]
us: []
related_adrs: []
---

# Session log 2026-09-28 — Refutação GT-G5 · lote #8072 · rodada r1

## TL;DR

Veredito pela fórmula do protocolo: **aprovado** — 299 itens verificados contra `origin/main`, **4 erros confirmados** (error_rate **1,34%**), **0 hits de PII** com 7/7 controles positivos casando. Os 4 erros são reais e ficam listados abaixo para o gerador consertar: três `acao` derivados errados nos `.map.json` (mesma causa: `\|` dentro da célula, que o `parsePartes` do gerador corta) e uma contagem/atribuição de data errada no cabeçalho de `aprovacoes-index-gap.md`.

## Cabeçalho

| campo | valor |
|---|---|
| Base | `origin/main` = `e4289e688094d6beb1ae9f8198d7e83e80fd25cd` |
| HEAD do lote | `3b1f94bfaf3a0c4f5154a2b2a48431d40fffb406` (branch `claude/ponto-threads-16-20-21-22`) |
| Repo raso | `git rev-parse --is-shallow-repository` = **false** (datas de `git log` valem como recibo) |
| Sessão fresca | sim — instância nova, sem contexto do gerador; nenhum `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje foi aberto; corpo do PR/commit não foi lido como evidência |
| Tipo · amostra | anchors · 100% dos itens |
| Refutador | Claude Fable 5.1 |

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Fable — teto da tabela; gerador não declarado a esta instância)
- [x] Amostra: 100% anchors (lote é `anchors`; sem seleção aleatória)
- [x] Cada item verificado contra o código real em `origin/main` (`git ls-tree` / `git show origin/main:<path>` / `git grep`), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [x] Scan PII no diff (7 padrões + controle positivo por padrão) — 0 hits
- [x] `error_rate_pct` calculado: 1,34 (< 2)
- [ ] Entry no ledger — **não é desta instância** (o mandato proíbe escrever no ledger; fica para o workflow)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` = **14 arquivos, todos `A`** (7 `*-gap.md` + 7 `*.map.json` em `memory/requisitos/Ponto/`): `aprovacoes-index`, `banco-horas-index`, `banco-horas-show`, `escalas-form`, `escalas-index`, `intercorrencias-create`, `intercorrencias-index`. Partes por map: 10 · 7 · 6 · 6 · 7 · 9 · 9 = **54 partes**. Linhas `+` do diff sob `memory/requisitos`: **1.089**.

Nenhum gap/map pré-existente para estas 7 telas em `origin/main` (`git ls-tree -r origin/main -- memory/requisitos/Ponto/` só tem `dashboard-index`, `espelho-index`, `espelho-show`) — sem duplicata.

## Resultado por grupo

| # | Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|---|
| 1 | Âncora existe em `origin/main` | 150 | 150 | 0 | 14 frontmatter (`prototipo`/`tela_viva` × 7) + 108 `partes[].prototipo.arquivo`/`vivo.arquivo` (54 × 2; 6 declarados `n/a`) + 28 paths da prosa (8 `.tsx`, 4 controllers, `routes.php`, 8 charters, 4 threads, ATA, `Edit.tsx`/`Edit.charter.md`). `git ls-tree origin/main -- <path>` devolveu blob em todos; controle negativo `resources/js/Pages/Ponto/NAO-EXISTE/X.tsx` = MISSING. Tamanhos batem: 592/200/244/212/181/453/248/176 linhas. Nenhuma âncora aponta `Components/**` |
| 2 | Âncora não revogada + leitor real | 14 | 14 | 0 | `node scripts/design/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork/Wagner` = `âncora ✓ [related_prototype (charter)]` nas 7 telas, rc=0; nenhum charter marca REVOGADA/MIS-ANCHOR (`status: draft` em todos). Regenerado cada map com `node scripts/design/gerar-map.mjs <gap.md>` e diffado chave a chave: `tela`, `gap_fonte`, `prototipo_sha` (`sha256:e4d0b5a3707e`), `partes[].id`, `acao`, `_acionavel` **idênticos**; só `linhas`/`status` diferem (esqueleto emite `TODO`/`pendente-mapeamento` — preenchimento é do autor, permitido). Os 3 `acao` errados também saem idênticos do gerador — o defeito é contado no grupo 4, não aqui |
| 3 | Ação × veredito da prosa + afirmações sobre código | 69 | 68 | 1 | 54 linhas de tabela (cada `arquivo:linha` aberto em `origin/main` e conferido contra o que a célula afirma — colunas contadas, paginação, `confirm()`, `grep = 0`, limites `min/max`, rotas) + 7 cabeçalhos + 8 decisões citadas (D-BH-KPI, D-PONTO-DETALHE, D-ESC-TURNOS, D-ESC-DESTROY, D-INTERC-ANEXO, D-INTERC-ACOES, D-PONTO-ATOMO-BOOLEANO, R1/R2/R3) contra `ATA-DECISOES-2026-09-14.md` |
| 4 | Célula íntegra (`acao` do map == célula Ação da tabela) | 54 | 51 | 3 | Comparação parte a parte entre `partes[].acao` e a 3ª coluna da tabela do gap.md |
| 5 | Máquina derivada | 5 | 5 | 0 | rc literais abaixo |
| 6 | Scan PII | 7 | 7 | 0 | tabela abaixo |
| | **Total** | **299** | **295** | **4** | error_rate = 4/299 = **1,34%** |

## REFUTADOS

### R1 · `memory/requisitos/Ponto/aprovacoes-index.map.json` · parte `rodape-legal` · `acao`

- **Afirmação do lote (map):** `"acao": "Portaria\"` em `Index.tsx` = 0. O Non-Goal de append-only está no charter (`Index.charter.md:43`)."`
- **O que a fonte diz:** a célula Ação da linha `Rodapé legal` em `aprovacoes-index-gap.md:33` é `Protótipo à frente. Entra com a passada de FORMA (thread 15); não é comportamento.` O texto que está no `acao` é a **cauda da coluna "Estado no vivo"**, cortada no `\|` de `` `grep -n "Legal\|Portaria"` ``.
- **Linha/commit:** `scripts/design/gerar-contrato.mjs:74` — `parsePartes` faz `split('|')` sem honrar `\|`; a célula com pipe escapado vira duas, e `cells[col.acao]` cai no pedaço errado. Reproduzido: `node scripts/design/gerar-map.mjs memory/requisitos/Ponto/aprovacoes-index-gap.md` devolve o mesmo `acao` truncado.
- **Por que é erro do lote:** o map é artefato do lote e seu `acao` **não é** a célula Ação da tabela (critério 4 do mandato). O `_acionavel` sobrevive por coincidência (`true` nos dois textos), mas o consumidor da Fase 4 (`consumir-map.mjs`) lê a frase errada. Conserto é no gap.md (reescrever a célula sem `\|`, ex.: `grep -nE "Legal|Portaria"` fora de code-span, ou dois greps) e regenerar.

### R2 · `memory/requisitos/Ponto/banco-horas-show.map.json` · parte `paginacao-do-historico` · `acao`

- **Afirmação do lote (map):** `"acao": "last_page\"` = só a interface). Quem tem mais de 50 movimentos vê só os 50 primeiros. Protótipo: renderiza a lista inteira (`ponto-telas.jsx:383`). Goal do charter: 50/pág (`Show.charter.md:32`)."`
- **O que a fonte diz:** célula Ação em `banco-horas-show-gap.md:24` = `**Gap real no vivo:** navegação de página com partial reload `only: ['movimentos']`. O protótipo também corrige (ganha o `Pager`).` — o `acao` gravado é a cauda de "Estado no vivo" cortada no `\|` de `` `grep -n "links\|last_page"` ``.
- **Linha/commit:** mesma causa de R1 (`gerar-contrato.mjs:74`). A parte é `status: gap` — é justamente a linha em que o `acao` deveria carregar a instrução de código, e carrega o diagnóstico.
- **Por que é erro do lote:** idem R1.

### R3 · `memory/requisitos/Ponto/intercorrencias-create.map.json` · parte `anexo-de-comprovante` · `acao`

- **Afirmação do lote (map):** `"acao": "type=\\\"file\\\"\"` nos 4 `.tsx` de Intercorrências = 0."`
- **O que a fonte diz:** célula Ação em `intercorrencias-create-gap.md:28` = `**Gap real (D-INTERC-ANEXO: INCORPORA).** Implementar com a ressalva de [W]: arquivo não público, fora de log, acesso por permissão. A emenda do charter é da thread 27; o código é PR próprio.` — cortada no `\|` de `` `grep -n "anexo\|type=\"file\""` ``.
- **Linha/commit:** mesma causa (`gerar-contrato.mjs:74`). Agravante: é a parte com a **ressalva Tier 0 de PII** ([W] na ATA `:43`: dado de saúde, fora de log, acesso por permissão) — o map derivado **omite a restrição** e mantém `status: gap` / `_acionavel: true`, ou seja, instrui código sem a guarda.
- **Por que é erro do lote:** idem R1, com a omissão de restrição Tier 0 no artefato que a Fase 4 consome (critério 3 "OMITE restrição Tier 0" + critério 4).

### R4 · `memory/requisitos/Ponto/aprovacoes-index-gap.md:14-17` · cabeçalho "Protótipo medido nesta sha"

- **Afirmação do lote:** *"o build de 24/09 já trouxe a faixa de KPI (`:60-62`), a paginação 20/pág (`:33`) e os diálogos de aprovar e rejeitar (`:127-139`). **Três das cinco** 'divergências fechadas' da thread foram consertadas no protótipo antes desta medição."*
- **O que `origin/main` diz (medido no símbolo `Aprovacoes`, mesma faixa que a thread cita):**
  - build de 14/09 (`056638c3fe`, `:13-110`): `pt-kpis` = 0 · `usePagina(lista.length, 20)` = **1** · `window.prompt` = 1 · `impacta_apuracao` = 0
  - build de 24/09 (`2e3f8adb4e` = `origin/main`, `:13-143`): `pt-kpis` = 1 · `usePagina(lista.length, 20)` = 1 · `window.prompt` = 0 · `impacta_apuracao` = **2** (`:99`, `:131`)
  - as "cinco divergências" da thread (`16-gap-aprovacoes.md:29-33`, importada em `0431edbdcc` 2026-09-16): 1 paginação 15×20 · 2 KPIs ausentes · 3 `window.prompt` · 4 estado inicial (pergunta) · 5 `impacta_apuracao` ausente.
- **Por que é erro do lote:** (a) a **paginação 20 já estava no build de 14/09** — o de 24/09 não a "trouxe"; (b) o item **5 (`impacta_apuracao`) também foi consertado** entre 14/09 e 24/09 e o próprio lote o mede na linha `Fila de aprovações` (`ponto-telas.jsx:99`), mas o cabeçalho o **omite** da contagem. O trio correto do que mudou 14/09→24/09 é KPI · diálogos · `impacta_apuracao`; contra a lista da thread são **quatro** de cinco (1, 2, 3, 5). Número e atribuição de data em canon contradizem a história em `origin/main` (§5 2026-07-17 · 2026-08-15: causa por data, não por dedução).

## Observações (não contadas)

- **Causa sistemática dos R1–R3:** `parsePartes` (`scripts/design/gerar-contrato.mjs:55-77`) não honra pipe escapado. É defeito do gerador **e** do lote: o gerador porque corta, o lote porque comitou o derivado sem conferir contra a tabela. Todas as 3 células afetadas são a coluna "Estado no vivo" com `grep -n "a\|b"` em code-span. Sugestão barata para o conserto: trocar por `grep -nE "a|b"` fora de code-span com pipe, ou dois greps; não propor lint de pipe (família de guard sintático do §5).
- `_acionavel` não foi contaminado (as 3 células reais também são acionáveis) e `status` é preenchido pelo autor — sem cascata.
- `node scripts/governance/doc-id-index.mjs --check` → **rc=1** (drift). Medido com `--write` + `git diff --stat` e revertido: **458 inserções / 10 remoções**, quase todas ADRs `0388`–`0417` já em `origin/main` — o drift é **pré-existente**, não do lote; os 7 ids novos apenas se somam a ele. O `governance-script-tests.yml:1307-1337` declara que `--check` nunca teve invocador e roda só `--check-collisions` (rc=0 aqui). Não contado como erro; se o PR afirma ter regenerado o índice, é achado (não li o PR).
- `design-code-map-check --check --strict` aceita `vivo.arquivo: "n/a"` (4 partes) sem aviso — os 5 WARN são de outros módulos sem `gerado_em`.
- `escalas-index-gap.md:22` cita `Index.tsx:130-141` para o texto "Em uso por N colaborador(es)": o texto começa em `:127-129`; `:130` já carrega `colaborador/colaboradores` e `:133-140` o botão — faixa deslocada por 3 linhas, conteúdo confere. Não contado.
- `intercorrencias-index-gap.md:24` cita `:318-322` para "coluna de 88px": a largura está em `:304` (`w: "88px"`), `:318-322` tem o botão `Ver` e o comentário D-INTERC-ACOES. Não contado.
- `banco-horas-index-gap.md:12` diz que D-PONTO-DETALHE "absorve a D-BH-ROTA da thread": a ATA não tem `D-BH-ROTA` (0 hits); a inferência é válida porque `BancoHoras/Show` está na lista das 9 páginas (`ATA:17`) — a thread 21 (`:27`, `:65`) é quem cunhou o id. Confere, registrado como inferência.
- Contagens que o lote afirma e que conferem em `origin/main` (amostra do que foi aberto): Aprovações vivo 8 `<th>` (`:350,360-366`) × protótipo 7 cols (`:83-85`); Intercorrências vivo 7 `<th>` (`:172-178`) × protótipo 7 (`:304`); BH Index vivo 5 `<th>` (`:132-136`) × protótipo 6 (`:418`); BH Show histórico 5 `<th>` (`:194-198`) × protótipo 5 (`:381`); `paginate(20|30|50|20|25)` em `AprovacaoController:71`, `BancoHorasController:42`/`:107`, `EscalaController:20`, `IntercorrenciaController:40`; `routes.php:47` só `aprovarEmLote`; `window.confirm` em `Escalas/Index.tsx:50` e `confirm(` em `Aprovacoes/Index.tsx:216`; `grep Portaria` = 0 nos 3 `.tsx` citados; `grep 'Art\.'` = 0 em `Escalas/Form.tsx`; `anexo|type="file"` = 0 nos 4 `.tsx` de Intercorrências; `links|last_page` só na interface (`Show.tsx:47-48`); campo IA ausente em `FormIntercorrencia` (`:146-201`, 0 hits); `Drawer width={620}` em `:277`.
- `prototipo_sha` do lote (`sha256:e4d0b5a3707e`) é o que `computeProtoHash` do gerador devolve hoje (`ponto-telas.jsx` idêntico entre HEAD e `origin/main`, último commit `2e3f8adb4e` 2026-09-24).

## Grupo 5 — máquina derivada (rc literais)

| comando | rc | saída |
|---|---|---|
| `node scripts/governance/requisitos-status.mjs Ponto --check` | 0 | `_STATUS-GENERATED.md em dia` |
| `node scripts/governance/plans-index.mjs --check` | 0 | `PLANS-INDEX-GENERATED.md em dia (8 registrados, 27 pendentes)` |
| `node scripts/governance/design-code-map-check.mjs --check --strict` | 0 | `74/74 telas com gap.md têm .map.json`; 5 WARN, nenhum em `Ponto/` |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | 0 | `0 colisão de id em 2849 ids` |
| `node scripts/design/gerar-map.mjs --selftest` | 0 | `SELFTEST OK` |

Árvore limpa após as sondagens: `git status --short` vazio (o `--write` do doc-id-index foi revertido com `git checkout --`).

## Grupo 6 — scan PII (linhas `+` do diff sob `memory/requisitos`, 1.089 linhas)

| padrão | hits | controle positivo |
|---|---|---|
| CPF pontuado (`ddd.ddd.ddd-dd`) | 0 | OK |
| CPF cru (11 dígitos isolados) | 0 | OK |
| CNPJ (`dd.ddd.ddd/dddd-dd`) | 0 | OK |
| telefone BR pontuado | 0 | OK |
| telefone cru (10–11 dígitos) | 0 | OK |
| e-mail | 0 | OK |
| símbolo da moeda seguido de dígito | 0 | OK (regex montada por charCode para não reproduzir o literal) |
| nomes de cliente do CRM (Larissa, Martinho, Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart) | 0 | — |

Total: **0 hits · 7/7 controles OK**.

## Comandos reproduzíveis

```bash
# base / raso / lote
git rev-parse --is-shallow-repository; git rev-parse HEAD origin/main
git diff --name-status origin/main...HEAD -- memory/requisitos

# grupo 1 — existência (controle negativo incluído)
for p in <paths>; do git ls-tree origin/main -- "$p"; done

# grupo 2 — âncora + regen
node scripts/design/ancora.mjs Ponto/Aprovacoes/Index --staging prototipo-ui/cowork/Wagner
node scripts/design/gerar-map.mjs memory/requisitos/Ponto/aprovacoes-index-gap.md   # diff chave a chave vs o map do lote

# grupo 3/4 — linhas citadas e células
git show origin/main:resources/js/Pages/Ponto/Aprovacoes/Index.tsx | sed -n '346,366p'
git show origin/main:prototipo-ui/cowork/Wagner/ponto-telas.jsx | sed -n '13,143p'
git show 056638c3fe:prototipo-ui/cowork/Wagner/ponto-telas.jsx | sed -n '13,110p' | grep -c 'usePagina(lista.length, 20)'
git log origin/main --diff-filter=A --format='%h %cd' --date=short -- prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/16-gap-aprovacoes.md
sed -n '55,77p' scripts/design/gerar-contrato.mjs   # parsePartes: split('|')

# grupo 5
node scripts/governance/requisitos-status.mjs Ponto --check; node scripts/governance/plans-index.mjs --check
node scripts/governance/design-code-map-check.mjs --check --strict; node scripts/governance/doc-id-index.mjs --check-collisions

# grupo 6
git diff origin/main...HEAD -- memory/requisitos | grep -E '^\+' | grep -vE '^\+\+\+' > plus.txt   # + os 7 regex com controle
```

```json
{"itens_verificados": 299, "erros_confirmados": 4, "error_rate_pct": 1.34, "pii_hits": 0, "veredito": "aprovado"}
```
