---
date: "2026-09-06"
topic: "Refutação GT-G5 r2 do lote PR #6914 (gap.md + map.json Estoque/Manufacturing, 13 arquivos) — 298 itens, 11 refutados (3,69%), 0 PII — REPROVADO"
authors: ["C"]
prs: [6914]
outcomes:
  - "Lote REPROVADO: 11 erros confirmados em 298 itens (error_rate 3,69% ≥ 2%) — todos da classe 'cita linha errada' ou 'símbolo que o vivo não tem'; nenhum inverte veredito, nenhum reabre Non-Goal, nenhuma âncora quebrada"
  - "Máquinas: requisitos-status Manufacturing/Estoque --check rc=0 · design-code-map-check --check --strict rc=0 · doc-id-index --check-collisions rc=0 · gerar-map skeleton/--atualizar idênticos (ids/acao/sha) · ancora.mjs ✓ nas 6 telas"
  - "PII: 0 hits em 1.484 linhas '+', 7/7 controles positivos casaram; 2 vermelhos NÃO do lote (plans-index --check e doc-id-index --check são drift pré-existente/advisory) registrados como observação"
---

# Refutação GT-G5 · rodada r2 · lote do PR #6914

**Refutador:** instância nova, sessão fresca (worktree `claude/quizzical-hugle-38b479`), tier Fable 5.1. Sem contexto do gerador nem das rodadas anteriores.
**Base medida:** `origin/main` = `7bff2ca69d` no início; durante a rodada uma sessão paralela deu `fetch` + merge no mesmo worktree (`HEAD f43aa38e5b → 8a204f59c6` às 16:06:22, `origin/main → 5baadae608`). Conferido: `git diff --stat 7bff2ca69d origin/main -- <todas as fontes citadas>` = **vazio** e `git diff --stat f43aa38e5b HEAD -- memory/requisitos` = **vazio** — as medições valem para os dois pares de refs.
**Repo raso:** `true` no início (`git rev-parse --is-shallow-repository`), `false` depois do merge externo. Nenhuma data de `git log` foi usada como recibo.
**Evidências anteriores abertas:** nenhuma (`memory/sessions/*refutacao*` e `memory/handoffs/` de hoje não foram lidos; o corpo do PR não foi lido).
**Árvore:** limpa ao fim (`git status --short` vazio, exceto este arquivo). Os `staged` que apareceram entre duas sondagens (`scripts/design-sync/**`, `tests/Browser/DesignSmoke/**`) eram da sessão paralela e foram commitados por ela — não toquei.

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (Fable 5.1 — tier máximo disponível)
- [x] Amostra: **100% anchors** (todo path, toda chave de frontmatter, toda linha de tabela, todo US-id; 237 citações `arquivo:linha` abertas uma a uma) — prosa destilada não se aplica (tipo `anchors`), seed não necessária
- [x] Cada item verificado contra `origin/main` (`git ls-tree` / `git show origin/main:<path>` extraídos para scratch), nunca contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII no diff (7 padrões + controle positivo) — 0 hits
- [x] `error_rate_pct` calculado = **3,69** (≥ 2 → REPROVADO)
- [ ] Entry no ledger — **não é deste papel**: refutador não escreve no ledger; reprovado → volta pro gerador

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → 13 `A` (4 gap.md + 4 map.json em `Estoque/`, 2 gap.md + 2 map.json + `_STATUS-GENERATED.md` em `Manufacturing/`). O diff completo tem 18 arquivos: os 5 restantes são 2 session logs de refutação (não abertos) e 3 state files (`scripts/design-sync/state/*.json`, `.cowork-freshness-ledger.json`).

Partes por map: adj-create 10 · adj-index 13 · xfer-create 12 · xfer-index 15 · mfg-index 12 · mfg-recipes 14 = **76** partes = 76 linhas de tabela nos gap.md (batem 1:1 por id).

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---:|---:|---:|---|
| 1 · Âncora existe em origin/main | 69 | 69 | 0 | `git ls-tree origin/main -- <path>` para 38 paths distintos (5 jsx, 9 tsx, 6 charters, 5 casos.md, SPEC, 3 ADRs, proibicoes, ledger, 5 scripts, controllers/service, casos Estoque, PR #6927 via `gh`, 2 charters Officeimpresso/Logs) + 17 pares `prototipo.arquivo`/`vivo.arquivo` dos maps + 12 chaves `prototipo:`/`tela_viva:`; **controle negativo** `resources/js/Pages/NAO-EXISTE/Controle.tsx` → MISS. Nenhuma âncora de fundação (`Components/**`) apontada pra consumidor. 7 US-ids `US-MANU-001..007` existem no SPEC (`:18,71,105,140,176,219,240`) |
| 2 · Não revogada / lida pelo leitor real | 30 | 30 | 0 | `node prototipo-ui/ancora.mjs <tela> --staging prototipo-ui/cowork` nas 6 telas → `âncora ✓` (5 via `bundle_source`, Recipes via `related_prototype` + heurística) · charters sem `REVOGADA`/`MIS-ANCHOR` (grep, 0 hits; controle positivo `related_prototype` casa) · `gerar-map.mjs <gap>` (esqueleto, stdout) vs map committed: `tela`/`gap_fonte`/`prototipo_sha`/ids/`acao`/`_acionavel`/`vivo.arquivo`/`status` **idênticos** nos 6 · `--atualizar` in-place → byte-idêntico nos 6 · `design-code-map-check --check --strict` rc=0 (sha não stale) |
| 3 · Ação × veredito · afirmação sobre código | 104 | 93 | **11** | 76 linhas de tabela + 25 parágrafos de cabeçalho + 3 blocos derivados do `_STATUS-GENERATED.md`; **237 citações `arquivo:linha` abertas** contra a cópia de origin/main (`sed -n` + `nl`); varreduras contadas re-rodadas (`grep -c`) — todas batem: `os-page-h`=0 no Index.tsx, `order` 4 linhas/11 casamentos, `Excluir`/`AlertDialog`/`mfg-modal`/`mfg-toast`=0 no Recipes.tsx, `CliTabs`=0 nos 2 espelhos, sort/paginação=0 nos 3 Index.tsx (rc=1 + controle positivo `PageHeader`=2) |
| 4 · Célula íntegra | 82 | 82 | 0 | 6 gap.md: toda linha `\|` tem NF=5 (0 pipes soltos), 0 code-span com backtick ímpar; 76 `acao` == célula "Ação" (esqueleto do gerador reproduz o committed) |
| 5 · Máquina derivada | 6 | 6 | 0 | rc lido do `node` (arquivo, não do `tail`): `requisitos-status Manufacturing --check` **0** · `requisitos-status Estoque --check` **0** · `design-code-map-check --check --strict` **0** · `doc-id-index --check-collisions` **0** · `plans-index --check` **1** (pré-existente, ver obs.) · `doc-id-index --check` **1** (advisory, ver obs.) |
| 6 · PII | 7 | 7 | 0 | 1.484 linhas `+`; 7 padrões; **7/7 controles positivos** casaram; 0 hits; 0 nome de cliente CRM |
| **Total** | **298** | **287** | **11** | **error_rate = 11/298 = 3,69%** |

## REFUTADOS (11)

### R1 · `Estoque/stock-adjustment-create-gap.md` · L15 "Região do protótipo"
- **Afirmação:** "`BuscaProduto` (`:12-66`) e `LinhasItens` (`:68-150`)" — citados logo após `estoque-page.jsx:641-657`.
- **origin/main diz:** pela convenção do próprio doc (bare `:N` = último arquivo nomeado), resolve para `estoque-page.jsx:12-66` = `irRota`/helpers e `:68-150` = `Painel`. Os componentes vivem em `estoque-forms.jsx:12-64` e `:68-146`.
- **Porquê é erro:** linha citada não contém o que se afirma (arquivo errado por convenção do doc). A mesma linha 23 cita certo (`estoque-forms.jsx:12-66`), o que prova que o autor sabia o arquivo.

### R2 · `Estoque/stock-transfer-create-gap.md` · L15 "Região do protótipo"
- **Afirmação:** "`BuscaProduto` (`:12-66`) e `LinhasItens` (`:68-150`)" após `estoque-page.jsx:657-676`.
- **origin/main diz:** idem R1 — `estoque-page.jsx:12-66`/`68-150` não contêm esses componentes.

### R3 · `Estoque/stock-adjustment-index-gap.md` · linha "Header / PageHeader"
- **Afirmação:** "cabeçalho de módulo (`MP.Header`, l.583-591) com papel, contexto e refresh".
- **origin/main diz:** `estoque-page.jsx:580-581` = `{MP.Header && <MP.Header modulo="Estoque" papel=…`, `:582` = `contexto={…}`, `:583` = `atualizadoAs`, `:584` = `onRefresh`, `:585-590` = glyph/acoes. O identificador `MP.Header`, `papel` e `contexto` estão **fora** de 583-591.
- **Porquê é erro:** 2 dos 3 atributos afirmados + o próprio identificador não estão nas linhas citadas.

### R4 · `Estoque/stock-adjustment-index-gap.md` · linha "Rodapé de somatórios" (+ `acao` idêntica no map.json)
- **Afirmação:** "quatro números … (`estoque-page.jsx:285-292`, **cálculo em `:245-246`**)".
- **origin/main diz:** `:243` = `const somaAj = …`, `:244` = `const somaRec = …`, `:245` = linha vazia, `:246` = `const exportar = (tipo) => {`.
- **Porquê é erro:** o cálculo citado não está nas linhas citadas (off-by-2, e a 246 é outra função).

### R5 · `Estoque/stock-transfer-create-gap.md` · linha "Limpeza de linhas ao trocar a origem"
- **Afirmação:** "trocar `location_id` mantém as linhas (`Create.tsx:206-210` só faz `setData`)".
- **origin/main diz:** `StockTransfer/Create.tsx:202` = `onChange={(e) => form.setData('location_id', e.target.value)}`; `:206-208` = `localOptions.map(...)`, `:209` = `</select>`, `:210` = `</div>`.
- **Porquê é erro:** as linhas citadas não contêm o `setData`; a afirmação (verdadeira) mora na 202.

### R6 · `Estoque/stock-transfer-create-gap.md` · linha "Lote e validade nas linhas"
- **Afirmação:** "bloqueia o salvamento quando o produto tem lotes e nenhum foi escolhido (`semLote`, `:271`)".
- **origin/main diz:** `estoque-forms.jsx:271` = `const dispDe = (l) => …`; `semLote` está em `:273`.

### R7 · `Estoque/stock-transfer-create-gap.md` · linha "Frete e totais"
- **Afirmação:** "o `help` *'Entra no custo do material que chega no destino'* no campo de frete (`:334`)".
- **origin/main diz:** `estoque-forms.jsx:333` = `<Input label="Frete" … help="Entra no custo do material que chega no destino." />`; `:334` = `<Textarea label="Observação" …>`.

### R8 · `Estoque/stock-transfer-index-gap.md` · linha "Coluna 'Mexeu no saldo?'" (+ `prototipo.linhas: "310"` no map.json)
- **Afirmação:** "O protótipo tem a mesma coluna (`estoque-page.jsx:310`, célula em `:348`)".
- **origin/main diz:** `:310` = `{ key: "status", label: "Status", … }`; `{ key: "saldo", label: "Mexeu no saldo?", width: 140 }` é a **`:311`**. A célula `:348` está certa.
- **Porquê é erro:** a linha citada contém outra coluna; e o map.json carrega o mesmo `310` como âncora do protótipo.

### R9 · `Manufacturing/manufacturing-index-gap.md` · linha "Paginação"
- **Afirmação:** "pagina de 10 em 10 … (`manufacturing-producao.jsx:77-85`, `POR_PAG` em `:31`)".
- **origin/main diz:** `:29` = `const POR_PAG = 10;`; `:31` = `const pagina = Math.min(pag, nPags);` (não contém `POR_PAG`).

### R10 · `Manufacturing/manufacturing-recipes-gap.md` · linha "Abas do módulo"
- **Afirmação:** "O comentário do vivo (`:190-194`) registra que a aba Configurações deixou de ser âncora crua para rota Blade — correção nascida de uso ([F] clicou e saiu do SPA)".
- **origin/main diz:** `Recipes.tsx:187-188` = "*Até 2026-09-04 esta aba era uma **âncora crua** apontando pra rota Blade legada*", `:189` = "*foi o que o **[F]** viu ao clicar em Configurações*"; `:190-191` = "*cutover … PENDENTE … esta aba só deixa de contradizer as irmãs*", `:192-194` = o `<Link>`.
- **Porquê é erro:** tudo que a célula afirma está em 187-189, fora do range citado.

### R11 · `Manufacturing/manufacturing-recipes-gap.md` · linha "Impressão de fichas"
- **Afirmação (coluna "Estado no vivo"):** "`MfgFichaPrint` presente, com e sem custo (`Recipes.tsx:406`, `:544-548`)".
- **origin/main diz:** `grep -c MfgFichaPrint Recipes.tsx` = **0**. O vivo importa `FichaPrint` de `./_components/FichaPrint` (`Recipes.tsx:27`) e renderiza `<FichaPrint itens=… semCusto=…>` em `:422`. `MfgFichaPrint` é o nome do **protótipo** (`manufacturing-page.jsx:294`, `window.MfgFichaPrint`).
- **Porquê é erro:** afirma sobre o código um símbolo que o código não tem (a capacidade existe; o nome é do mockup rotulado como "vivo" — item 4 do mandato).

## Observações não contadas

- **Ledger "rodada 38" (3 cabeçalhos: adj-index, mfg-index, mfg-recipes):** a entrada #38 existe só em HEAD (origin/main tem 37); `staleList` = `["officeimpresso-page.jsx","oficina-page.jsx"]` ✓ e `unchecked` = **número 256**, não lista — a frase "*lista `estoque-page.jsx` entre os 256 unchecked*" é imprecisa (o ledger conta, não lista; o arquivo não é mencionado na entrada). A conclusão "*a rodada 38 não é recibo deste arquivo*" está **correta**; por isso não contei como erro. O veredito STALE (`TabBar` × `window.CliTabs`) vem do Cowork vivo e **não é mensurável contra origin/main** — fica fora de `itens_verificados`.
- **"28 KB" (adj-index L15):** `estoque-page.jsx` em origin/main tem **43.996 bytes**; `manufacturing-page.jsx` 20.722; `estoque-forms.jsx` 36.568. O número afirmado não bate com nenhum espelho. Como se refere ao retorno do `get_file` (vivo), não é mensurável aqui — registrado, não contado.
- **`plans-index --check` rc=1** — o `--write` acrescentaria só `RecurringBilling/cobranca-recorrente-planos-gap.md` (blob `ba95ee9eb2`, já em origin/main): drift **pré-existente**, não causado pelo lote (os 6 gap.md deste lote não entram no índice de planos).
- **`doc-id-index --check` rc=1** — o `--write` acrescentaria 131 ids, dos quais **6 são do lote** (`requisitos-estoque-stock-*-gap`, `requisitos-manufacturing-*-gap`) e 125 são sessions/handoffs/ADRs de 09-01..09-03 já em origin/main. O único invocador em CI é `--check-collisions` (advisory, `governance-script-tests.yml:1054`; rc=0). O lote não regenerou o índice, mas nenhum gate o exige — observação, não erro.
- **`gerar-map.mjs` avisa em stderr** nos 5 gaps ancorados por `bundle_source`: "*âncora computada do charter não cita <1º arquivo do frontmatter `prototipo:`>*". É limitação do gerador (compara só `related_prototype`), não do lote; o esqueleto sem `--atualizar` escolheria `arquivosPrototipo[0]` para 15 partes onde o committed aponta o arquivo **certo** (ex.: `pre-preenchimento` → `estoque-page.jsx`, onde `baixarVencido` de fato está) — o `--atualizar` preserva isso por desenho.
- **`_STATUS-GENERATED.md`:** `--check` rc=0 (em dia). A tabela "Backlog — US ainda não entregue" lista as 7 US com status `desconhecido` mesmo tendo 5 com `**Implementado em:**` real (SPEC `:30,85,118,149,186`) — é comportamento do gerador, idêntico em Compras/ComunicacaoVisual/Financeiro/Fiscal/Jana em origin/main; derivado, não erro do lote.
- **Ranges com folga ±1-3 linhas** (aceitos, contêm o afirmado): `estoque-forms.jsx:157-249` (função vai a 250), `:12-66` (64), `:68-150` (146), `:253-356` (355), `:403-484` (Folha vai a 501, mas barcode/assinaturas estão em 467-472), `estoque-page.jsx:641-657` (655), `:539-555` (538-553), `:248-256` (exportar 246-253), `manufacturing-page.jsx:74-79` (`Th` 72-77), `Recipes.tsx:131-137` (`Th` 130-138).
- **Não reabre veredito / Non-Goal / decisão [W]:** conferido — Non-Goals do `Manufacturing/Index.charter.md:37-40` (3 ativos) e `Recipes.charter.md:64` preservados; "Atualizar preço de venda" fica como decisão [W] pendente (`Recipes.tsx:387-392`); Tier 0 valor/estoque carimbado em toda linha "Decidir" que toca quantidade/custo/saldo.
- **Sessão paralela no mesmo worktree** (merge externo às 16:06:22): nenhuma interferência no lote (diff vazio); registrado por transparência.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`, 1.484 linhas)

| Padrão | Hits no diff | Controle positivo casou? |
|---|---:|---|
| CPF pontuado | 0 | sim |
| CPF cru (11 dígitos isolados) | 0 | sim |
| CNPJ | 0 | sim |
| Telefone BR | 0 | sim |
| Telefone cru (10-11 dígitos) | 0 | sim |
| E-mail | 0 | sim |
| Valor em reais (símbolo + dígito) | 0 | sim |
| Nomes de cliente CRM (Larissa/Martinho/Rota Livre/WR2/Eliana) | 0 | — |

`pii_controles_positivos_ok = 7/7`.

## Comandos reproduzíveis

```bash
# escopo
git diff --name-status origin/main...HEAD -- memory/requisitos
# existência + controle negativo
git ls-tree origin/main -- prototipo-ui/cowork/estoque-page.jsx resources/js/Pages/NAO-EXISTE/Controle.tsx
# fontes de origin/main (dot-path via hash)
MSYS_NO_PATHCONV=1 git show origin/main:prototipo-ui/cowork/estoque-page.jsx | sed -n '243,246p;310,311p;580,591p'
MSYS_NO_PATHCONV=1 git show origin/main:prototipo-ui/cowork/estoque-forms.jsx | sed -n '271,273p;333,334p'
MSYS_NO_PATHCONV=1 git show origin/main:resources/js/Pages/StockTransfer/Create.tsx | sed -n '202p;206,210p'
MSYS_NO_PATHCONV=1 git show origin/main:prototipo-ui/cowork/manufacturing-producao.jsx | sed -n '29,31p'
MSYS_NO_PATHCONV=1 git show origin/main:resources/js/Pages/Manufacturing/Recipes.tsx | grep -nE 'MfgFichaPrint|FichaPrint' ; sed -n '187,194p'
# âncora / leitor real
for t in StockAdjustment/Create StockAdjustment/Index StockTransfer/Create StockTransfer/Index Manufacturing/Index Manufacturing/Recipes; do node prototipo-ui/ancora.mjs $t --staging prototipo-ui/cowork; done
node prototipo-ui/gerar-map.mjs memory/requisitos/Estoque/stock-transfer-index-gap.md > /tmp/skel.json   # comparar ids/acao/sha
# máquinas (rc do node, não do tail)
node scripts/governance/requisitos-status.mjs Manufacturing --check; echo rc=$?
node scripts/governance/design-code-map-check.mjs --check --strict; echo rc=$?
node scripts/governance/doc-id-index.mjs --check-collisions; echo rc=$?
node scripts/governance/plans-index.mjs --check; echo rc=$?
# PII (padrão de reais descrito, não reproduzido)
git diff origin/main...HEAD -- memory/requisitos | grep -E '^\+' | grep -vE '^\+\+\+' > /tmp/plus.txt
```

```json
{"itens_verificados":298,"erros_confirmados":11,"error_rate_pct":3.69,"pii_hits":0,"veredito":"reprovado"}
```
