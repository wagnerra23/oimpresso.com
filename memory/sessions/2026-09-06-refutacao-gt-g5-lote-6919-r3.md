---
date: "2026-09-06"
topic: "Refutação GT-G5 r3 do lote #6919 (7 gap.md + 7 map.json — Backup, Dashboard, Cobrança, KB v2, PaymentGateway, Superadmin, Suporte): 394 itens medidos contra origin/main, 2 erros (ranges derivados), 0 PII — aprovado"
authors: ["C"]
prs: [6919]
outcomes:
  - "394 itens verificados em 6 grupos contra origin/main 5baadae608 · 2 refutados (ambos range de linha DERIVADO no map.json que cita linha errada) · error_rate 0,51% < 2%"
  - "Scan PII nas 1130 linhas + do diff: 0 hits nos 7 padrões, 7/7 controles positivos casaram"
  - "Máquinas: design-code-map-check --check --strict rc=0 · knowledge-drift --check rc=0 · doc-id-index rc=0 · consumir-map rc=0 nas 7 telas (forma canônica) · requisitos-status/plans-index vermelhos são pré-existentes em origin/main, não do lote"
---

# Refutação GT-G5 — lote #6919 · rodada r3

**Base** `origin/main` = `5baadae608` · **HEAD** = `78c368142b` · `git rev-parse --is-shallow-repository` = **false** (datas de `git log` valem como recibo) · sessão **fresca** (instância nova, zero contexto do gerador; nenhum `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje foi aberto; corpo do PR/commit não foi lido).

**Mandato:** provar que o lote está errado. Default = refutado quando a evidência não fecha.

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier superior — refutador = Fable 5.1 (tier máximo disponível); o gerador não é conhecido desta sessão por construção
- [x] Amostra: **100 % anchors** (todo path, toda chave de frontmatter, toda linha da tabela, todo range dos maps); não há prosa destilada amostrada (tipo = anchors)
- [x] Cada item verificado contra `origin/main` (`git ls-tree` / `git show origin/main:` / `git grep … origin/main`), nunca contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII no diff (7 padrões + controle positivo por padrão) — 0 hits
- [x] `error_rate_pct` calculado e < 2
- [ ] Entry no ledger — **não é desta rodada** (o workflow escreve; este artefato só devolve o veredito)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → **14 arquivos, todos `A`** (7 `*-gap.md` + 7 `*.map.json`), exatamente os listados no mandato. O PR inteiro tem 20 arquivos: os outros 6 são 2 evidências r1/r2 (não abertas), o charter `Financeiro/Cobranca/Index.charter.md` (ponteiro `related_prototype`), `scripts/design-sync/state/{applications,application-report}.json` e `scripts/governance/knowledge-drift.mjs` (lookbehind `Pages/`).

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| 1 · Âncora existe em origin/main | 65 | 65 | 0 | `git ls-tree origin/main -- <path>` para cada path citado (frontmatter `prototipo`/`tela_viva`/`comparacao`, `map.json` `prototipo.arquivo`/`vivo.arquivo`, arquivos citados na prosa, 7 charters donos, scripts citados). Controle negativo: `resources/js/Pages/Backup/NAO_EXISTE_controle_negativo.tsx` → MISSING |
| 2 · Não revogada e lida pelo leitor real | 28 | 28 | 0 | `node prototipo-ui/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork` nas 7 telas (âncora ✓ em todas; Suporte = `n/a` declarado + bundle, como a prosa diz); grep `REVOGAD|MIS-ANCHOR` nos 7 charters de origin/main (0 revogações — o único hit, kb v2 `:223`, é prosa sobre um teste); regeneração do esqueleto com `gerar-map.mjs` (7×): `prototipo_sha`, `tela`, `gap_fonte`, ids, `acao`, `_acionavel` **idênticos** aos 7 maps |
| 3 · Ação × veredito × código | 126 | 126 | 0 | 49 linhas de tabela + 77 afirmações de cabeçalho, cada uma aberta em `origin/main` na linha citada (detalhe em "Comandos") |
| 4 · Célula/derivado íntegro | 143 | 141 | **2** | 49 `acao` == célula (diff chave a chave contra o esqueleto regenerado); 94 ranges `prototipo.linhas`/`vivo.linhas` abertos início/fim em origin/main |
| 5 · Máquina derivada | 25 | 25 | 0 | rc literal de cada `--check`; sha256 dos 7 maps e 7 fontes vs `applications.json` |
| 6 · PII | 7 | 7 | 0 | 7 padrões sobre as 1130 linhas `+`, cada um com controle positivo sintético |
| **Total** | **394** | **392** | **2** | **error_rate = 0,51 %** |

## REFUTADOS

### 1. `memory/requisitos/Suporte/suporte-empresas.map.json` · parte `estados-erro-carregando` · `prototipo.linhas: "103-106"`

- **Afirmação do lote:** o bloco do protótipo desta parte vai de 103 a 106; a `acao` da mesma parte (e a célula do gap) diz *"Região do mockup: `suporte-page.jsx:103-107`"* e nomeia **dois** estados: `Vazio variant="error"` e `Skeleton` de 5 linhas.
- **O que origin/main diz** (`git show origin/main:prototipo-ui/cowork/suporte-page.jsx`): `:103` `{estado === "erro"` · `:104-105` `<Vazio variant="error" …` · `:106` `: estado === "carregando"` · **`:107`** `? <div className="sup-lista">{Skeleton ? <Skeleton variant="row" count={5} /> …`.
- **Porquê é erro do lote:** o range derivado **trunca** o bloco e deixa de fora a única linha que contém o `Skeleton` que a parte afirma; contradiz a própria `acao` (`:103-107`). É "cita linha errada" no artefato que o `design-code-map-check` lê.

### 2. `memory/requisitos/KB/kb-index-v2.map.json` · parte `faixa-de-indicadores` · `vivo.linhas: "451-455"`

- **Afirmação do lote:** o ponto no vivo é `Index.v2.tsx:451-455`; a `acao` da mesma parte diz *"O `Index.v2.tsx:449-452` grava a razão e a data: Wagner, 2026-05-17"*.
- **O que origin/main diz** (`git show origin/main:resources/js/Pages/kb/Index.v2.tsx`): `:449` `{/* KPIs movidos pro modal "Dashboard" …` · **`:450`** `Wagner 2026-05-17: cards no topo ocupavam ~150px sem ROI suficiente;` · `:451-452` fim do comentário · `:453` vazia · `:454` `{/* Search bar … */}` · `:455` `<div className="relative">` (início da **busca**, outra parte).
- **Porquê é erro do lote:** o range começa no meio do comentário, **omite a linha da decisão datada** que a `acao` cita como evidência do veredito "decisão registrada", e invade o bloco da parte vizinha. A prosa (`:449-452`) está certa; o derivado está errado.

## Observações não contadas como erro

- **`consumir-map.mjs Pages/Modules/Index` → rc=1** ("map não encontrado"); **`Modules/Index` → rc=0**. O gap/map de Superadmin usam `tela: "Pages/Modules/Index (/modulos)"` por causa do `MOD_REF_RE` do knowledge-drift (medido: em origin/main o regex não tinha o lookbehind `Pages/`, então `Modules/Index` nu viraria ghost). O comentário do próprio gap já diz que o basename é a chave do `escolherGap` e que só o `ancora.mjs` aceita as duas formas — consistente; mas quem for à Fase 4 tem que chamar `Modules/Index`, não `Pages/Modules/Index`.
- **`requisitos-status.mjs --check` rc=1 em Backup/Dashboard/PaymentGateway/Superadmin/Suporte** ("`_STATUS-GENERATED.md` não existe"): o arquivo **não existe em origin/main** nesses 5 módulos (`git ls-tree` → MISSING) — vermelho pré-existente, não do lote. Financeiro e KB rc=0.
- **`plans-index.mjs --check` rc=1**: o `--write` só adiciona `RecurringBilling/cobranca-recorrente-planos-gap.md` (24→25 pendentes), arquivo que já está em origin/main e fora deste lote. Drift pré-existente. Restaurei o índice com `git checkout --` (árvore limpa).
- **Dashboard `git rev-list --count --since=2026-09-03 origin/main`**: a prosa registra "era 234"; medi **227** contra `5baadae608`. A prosa já declara o número volátil e deixa o comando — não é claim; anotado porque um contador que "diminui" indica que o gerador mediu contra um `origin/main` diferente do base desta rodada.
- **KB proto ranges** (`busca 1036-1044`, `dialogos 1045-1086`, `composer 1068-1078`) terminam sistematicamente na linha de comentário-cabeçalho do bloco seguinte. Contêm o bloco certo; sloppy, não errado.
- **Cobrança, drawer:** "o charter reforça **duas** delas como Non-Goal" — literal, só *Estorno real* (`:62`) está entre as 4 ações removidas; *Export CSV/PDF* (`:56`) é export da lista, não "Baixar PDF" do boleto. Leitura frouxa, veredito (`decisão registrada`) intacto.
- **Superadmin "(7 PRs)"** restateia o `README.md` do inbox (*"7 PRs na ordem"*), embora `PEDIDO-PARA-CODE.md` cite PR-1…PR-8. O lote copia o dono; não inventou.
- **`applications.json`:** `mapSha256` e `sourceSha256` dos 7 registros novos batem com sha256 dos arquivos em HEAD (7/7 OK) — o map gravado é o map do diff.
- **Frescor:** as 7 declarações de frescor (6 "verificado 2026-08-27, fora da rodada 7/258"; KB "verificado 2026-09-06") batem literalmente com o que `ancora.mjs` imprime.
- **Charter Cobrança (fora de `memory/requisitos`, mas base do gap):** a prosa "duas cópias, a antiga é a do charter" fecha em origin/main — `prototipos/payment-gateway-ui/cobranca-page.jsx` 830 ln / 48 567 B, `pg-cobranca-page.jsx` 998 ln / 58 317 B; namespace `oimpresso.financeiro.cobranca.` só na nova (`:27-29`) e em `Index.tsx:74`; `CheatSheet` `:341`/`AiResumoMes` `:912` só na nova; `Index.tsx:476`/`:477` montam ambos; os dois arquivos nasceram no mesmo commit `9da73296d3` (2026-06-23, #3259); só `pg-cobranca-page.jsx` está em `active-bundle.json`.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos` = 1130)

| Padrão | Hits | Controle positivo casou |
|---|---|---|
| CPF pontuado (`ddd.ddd.ddd-dd`) | 0 | sim |
| CPF cru (11 dígitos isolados, fronteira não-hex) | 0 | sim |
| CNPJ (`dd.ddd.ddd/dddd-dd`) | 0 | sim |
| Telefone BR (DDD + 4/5-4) | 0 | sim |
| Telefone cru (10–11 dígitos isolados) | 0 | sim |
| E-mail | 0 | sim |
| Valor em reais (símbolo seguido de dígito — padrão não reproduzido aqui) | 0 | sim |

Nomes de cliente do CRM (Larissa, Martinho, ROTA LIVRE, Vértice, WR Comunicação, Ateliê, Placas Norte, Eliana): **0** nas linhas `+` — os mocks do `suporte-page.jsx:16-22` são citados por número de linha, não copiados. `grep` rc=1 em todos (vazio real, não falha), controles 7/7.

## Comandos reproduzíveis

```bash
git rev-parse HEAD origin/main; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos
# G1
for p in <paths>; do git ls-tree origin/main -- "$p"; done   # + controle negativo NAO_EXISTE
# G2
for t in Backup/Index Home/Index Financeiro/Cobranca/Index kb/Index.v2 Settings/PaymentGateways/Index Modules/Index Suporte/Empresas; do node prototipo-ui/ancora.mjs "$t" --staging prototipo-ui/cowork; done
for g in <gap.md>; do node prototipo-ui/gerar-map.mjs "$g" > regen.json; done   # diff chave a chave vs lote
# G3/G4 (exemplos)
git show origin/main:prototipo-ui/cowork/suporte-page.jsx | sed -n 103,107p
git show origin/main:resources/js/Pages/kb/Index.v2.tsx | sed -n 449,455p
git show origin/main:Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/_components/DrawerGateway.tsx | sed -n "1p;259,263p"
git grep -l PageHeader origin/main -- "resources/js/Pages/**/*.tsx" | wc -l   # 116 ; ls-tree -r ... | grep -c .tsx → 412
git grep -c -E "suporte/log|Suporte/Log|LogAcessos" origin/main -- . | wc -l   # 1 (só prototipo-ui) ; controle "suporte/empresas" → 15
# G5
node scripts/governance/design-code-map-check.mjs --check --strict   # rc=0
node scripts/governance/knowledge-drift.mjs --check                   # rc=0
node scripts/governance/doc-id-index.mjs --check-collisions           # rc=0 (0 colisão em 2633 ids)
for t in <telas>; do node prototipo-ui/consumir-map.mjs "$t"; done    # rc=0 ×7 (Modules/Index; Pages/Modules/Index → rc=1)
node scripts/governance/requisitos-status.mjs <Mod> --check           # Financeiro/KB rc=0 · demais rc=1 pré-existente
node scripts/governance/plans-index.mjs --check                       # rc=1 pré-existente (RecurringBilling)
# G6
git diff origin/main...HEAD -- memory/requisitos | grep "^+" | grep -v "^+++" > plus.txt ; grep -E -c "<padrão>" plus.txt   # ×7, + controle sintético
```

```json
{"itens_verificados": 394, "erros_confirmados": 2, "error_rate_pct": 0.51, "pii_hits": 0, "veredito": "aprovado"}
```
