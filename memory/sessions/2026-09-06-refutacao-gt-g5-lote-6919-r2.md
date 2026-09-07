---
date: "2026-09-06"
topic: "Refutação GT-G5 rodada r2 do lote #6919 (7 gap.md + 7 map.json, anchors) — 393 itens contra origin/main, 2 refutados (0,51%), PII 0 hits com 7/7 controles — aprovado"
authors: ["C"]
prs: [6919]
outcomes:
  - "393 itens verificados em 6 grupos contra origin/main 7bff2ca69d; 2 refutados (0,51% < 2%) — veredito aprovado"
  - "REFUTADO 1: Dashboard/home-index.map.json `acao` de grade-por-abas truncada no pipe escapado do code-span (perde pré-condição e veredito); REFUTADO 2: Financeiro/cobranca-index-gap.md afirma que o protótipo põe 'N em aberto' no subtítulo do cabeçalho — o Header do pg-cobranca-page.jsx:148-160 não tem subtítulo"
  - "PII: 0 hits nos 7 padrões sobre 1130 linhas `+`, 7/7 controles positivos casaram; árvore limpa; nenhuma evidência anterior aberta"
---

# Refutação GT-G5 · rodada r2 · lote PR #6919

- **Base:** `origin/main` = `7bff2ca69d85b22c54a22f3722a6c53b0519049c` · **HEAD:** `6e1fc82085b65cd52c2deb794f7f57ab36f19124`
- **Repo raso:** `git rev-parse --is-shallow-repository` = `true` → nenhuma data de `git log` usada como recibo (o hook `block-instrumento-sem-porta-viva` barrou a única tentativa, corretamente); datas de commit vieram da API do GitHub (`gh api repos/.../commits/<sha>`).
- **Sessão fresca:** sim — instância nova, sem contexto do gerador nem das rodadas anteriores. `memory/sessions/*refutacao*` (inclusive a r1, que está no diff do PR) e `memory/handoffs/` de hoje **não foram abertos**. Corpo do PR / commit message **não** foram lidos como evidência.
- **Refutador:** Claude (tier fable) · gerador desconhecido de propósito.

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (fable — teto da tabela)
- [x] Amostra: 100% anchors (tipo do lote = anchors; toda parte do map, toda chave de frontmatter, toda linha de tabela, todo path citado)
- [x] Cada item verificado contra o código real em `origin/main` (`git ls-tree` / `git show origin/main:<path>` / `git grep … origin/main`), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII no diff — 7 padrões × controle positivo (tabela abaixo)
- [x] `error_rate_pct` calculado e < 2
- [ ] Entry no ledger — **não é do refutador** (mandato: não escrever no ledger)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → 14 arquivos `A`, os mesmos 14 do mandato (Backup · Dashboard · Financeiro · KB · PaymentGateway · Superadmin · Suporte, cada um `<tela>-gap.md` + `<tela>.map.json`). O PR toca ainda 5 arquivos fora do lote (a r1 de refutação — não aberta —, `Financeiro/Cobranca/Index.charter.md`, 2 estados do design-sync e `scripts/governance/knowledge-drift.mjs`); o charter e o knowledge-drift foram lidos **como código** (diff), porque o lote faz afirmações sobre eles.

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| 1 · Âncora existe em origin/main | 142 | 142 | 0 | `git ls-tree origin/main -- <path>` para 99 `prototipo.arquivo`/`vivo.arquivo` dos 7 maps (1 `n/a` declarado, não contado), 15 chaves de frontmatter (`prototipo` + `tela_viva` ×7, `comparacao` ×1), 28 paths citados na prosa (KpiCard, DataTable, 6 componentes Cobranca, cópia antiga cobranca, 9 componentes KB, migration kb_subcategories, 3 charters KB, ConfirmToggleModal, gateway-shared, modulos.contract.json, cowork-inbox/modulos, kb-gap.md, kb.map.json). Controle negativo `resources/js/Pages/NAO/EXISTE.tsx` → MISSING. Nenhuma âncora de fundação (`Components/**`) usada como `vivo.arquivo` — as duas citadas (KpiCard, DataTable) são pré-condição na prosa, não âncora |
| 2 · Não revogada · lida pelo leitor real | 49 | 49 | 0 | `node prototipo-ui/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork` resolve `âncora ✓` nas 7 telas (Suporte: `n/a` declarado + bundle `suporte-page.jsx`, exatamente o que o gap diz); `grep -ci 'REVOGAD\|MIS-ANCHOR'` nos 7 charters donos = 0 (o único hit, kb/Index.v2:223, é "teste revogado", não âncora; controle positivo: 2 charters no repo usam REVOGADA); esqueleto regenerado com `node prototipo-ui/gerar-map.mjs <gap.md>` e comparado chave a chave — `tela`, `gap_fonte`, `prototipo_sha`, `version` e lista de `id` iguais nos 7 (35 chaves) |
| 3 · Ação × veredito · afirmação sobre código | 83 | 82 | **1** | 50 linhas de tabela + 33 afirmações de prosa/cabeçalho (erratas do inventário Dashboard, tabela das 2 cópias Cobranca, tabela de 8 linhas KB v1×v2, rotas/controllers/US KB, Non-Goals dos 5 charters, decisões D1–D4 Modules, `_nota_recorte`, natureza da fonte Suporte). Cada linha citada aberta em `origin/main` e conferida: `data-contract` (Backup 5/5, Home 5+1, Modules 5), contagens (`spark` -o = 11; `toggleSort`/`Drawer` = 0; `PageHeader` 116/412 exato; `density\|height` = 0; "graph" 4× Graphtec; 7 `th` nos dois lados PG; 830/998 ln e 48.567/58.317 bytes das cópias), commits via API (`5b51e2917f` 2026-09-03 #6690 · `a58db24d23` 2026-09-04 #6763 · `9da73296d3` 2026-06-23 único commit dos 2 paths), rotas (`/auditoria` existe; `/kb`→`KbController@index`, `/kb/v2`→`indexV2`; grupo `suporte` só `empresas`/`{business}`/`acessar-como` — sem rota de log) |
| 4 · Célula íntegra · `acao` == célula | 100 | 99 | **1** | 50 células "Ação" lidas por inteiro (pipe, code-span, reticência) + 50 `acao` do map comparadas ao esqueleto regenerado e à célula |
| 5 · Máquina derivada | 12 | 12 | 0 | rc literais abaixo |
| 6 · PII | 7 | 7 | 0 | 7 padrões × 1130 linhas `+`, cada um com controle positivo |
| **Total** | **393** | **391** | **2** | error_rate = 2/393 = **0,51%** |

## REFUTADOS

### R1 · `memory/requisitos/Dashboard/home-index.map.json` · parte `grade-por-abas` · chave `acao`

- **Afirmação do lote:** o map "declara" a ação da parte (o `_doc` do próprio arquivo diz que a Fase 4 consome via `consumir-map.mjs`).
- **O que o arquivo carrega:** a `acao` termina em `grep -c 'density\` — corta aí. A célula da tabela em `home-index-gap.md:38` continua: "…em `Components/shared/DataTable.tsx` devolve **0**. É o item #14 do inventário … Adotar significa estender um componente do Design System, decisão [W]. Construir ou rejeitar por escrito."
- **Causa (medida):** o code-span da célula contém um pipe escapado (`\|`); `parsePartes` (gerar-contrato.mjs, reusado por gerar-map.mjs) corta a célula nele. Regenerar com `node prototipo-ui/gerar-map.mjs memory/requisitos/Dashboard/home-index-gap.md` reproduz a mesma `acao` truncada — é o que o lote embarcou, não drift posterior.
- **Por que é erro do lote (grupo 4):** code-span truncado que **engole o veredito** ("Construir ou rejeitar por escrito") e a pré-condição inteira; `acao` do map ≠ célula da tabela; e `design-code-map-check --check --strict` **não pega** (rc=0), então o defeito segue calado até a Fase 4. Conserto trivial: escrever a sonda sem pipe na célula (ex.: `grep -c -e density -e height`) e regenerar o map.

### R2 · `memory/requisitos/Financeiro/cobranca-index-gap.md:62` · linha "Cabeçalho" · afirmação sobre o protótipo

- **Afirmação do lote:** "A contagem 'N em aberto' está no subtítulo, **como no protótipo**."
- **O que origin/main diz:** `prototipo-ui/cowork/pg-cobranca-page.jsx:148-160` monta `<Header title="Cobrança" breadcrumb={breadcrumb} right={…}/>` — **sem subtítulo**. O `breadcrumb` (`:118-124`) é `'Maio 2026 · ROTA LIVRE'` + filtros ativos. `qtd_aberto` aparece no protótipo só no KPI (`:173`), na aba (`:184`) e no funil (`:390`); `grep -n 'em aberto'` (minúsculo) no arquivo devolve 0 linhas (rc=1; controle `'Em aberto'` → 3 linhas). O vivo, sim, tem `subtitle={<>{kpis.aberto.qtd} em aberto · …</>}` (`Index.tsx:201`).
- **Por que é erro do lote (grupo 3):** afirma paridade de uma capacidade do cabeçalho que o protótipo **não desenha** — é o vivo à frente nesse ponto, não paridade. O veredito da linha ("Nada — paridade") não muda de ação, mas a justificativa carimba no protótipo algo que ele não tem, e o map (`acao` idêntica) herda a afirmação.

## Observações (não contadas como erro)

- `requisitos-status.mjs <Mod> --check` sai **rc=1** para Backup, Dashboard, PaymentGateway, Superadmin e Suporte ("`_STATUS-GENERATED.md` não existe") — estado **pré-existente em origin/main** (`git ls-tree` vazio para os 5) e o script não lê `*-gap.md`; não é regressão do lote. Financeiro e KB: rc=0.
- Na base `origin/main` o charter de `Financeiro/Cobranca` ainda aponta `prototipos/payment-gateway-ui/cobranca-page.jsx` (a cópia de 830 ln); o gap declara isso por extenso e o PR corrige o ponteiro no mesmo diff (`Index.charter.md`, 2 linhas). Os números da tabela das 2 cópias (830/998 ln · 48.567/58.317 bytes · `localStorage`/`AiResumoMes`/`CheatSheet` só na segunda — na antiga o único hit é o comentário `:27` · mesmo commit `9da73296d3`) batem todos.
- `scripts/governance/knowledge-drift.mjs` ganha lookbehind `(?<!Pages\/)` no `MOD_REF_RE` no mesmo PR — o comentário de cabeçalho do gap de Superadmin explica o porquê e o `--check` sai 0 NOVOS. Se mexer no detector no mesmo PR do lote é aceitável não é escopo desta refutação; fica registrado.
- KB v2: as citações de linha dos botões (`:375-383`, `:394-403`, `:404-413`, `:441-450`) estão deslocadas de 1 a 7 linhas em relação ao início de cada `<Button>` (Trilhas 373-381, Dashboard 393-401, Troubleshooter 402-410, Novo SOP 434-443), mas cada intervalo citado **contém** o rótulo afirmado — não conta como "linha errada".
- Backup: `KpiCard.tsx:77-86` cobre `icon/description/delta/action/selected`; `tone`/`size` vêm do `VariantProps` em `:74`. A afirmação (o componente expõe esses e não expõe `hero/spark/progress`) é verdadeira; o intervalo é só apertado demais.
- PG: 7 `th` nos dois lados (o vivo diz "Health", o protótipo "Health · latência"; latência renderizada em `Index.tsx:241`) — confirmado como o gap descreve.
- Nomes nas linhas `+`: só "Wagner" (2×, time interno). Nenhum nome de cliente do CRM.

## Máquina derivada — rc literais

| Comando | rc | Saída |
|---|---|---|
| `node scripts/governance/design-code-map-check.mjs --check --strict` | 0 | 27 map.json · "[OK] nenhum map.json com âncora quebrada ou sha stale. 9 âncora(s) TODO" |
| `node prototipo-ui/gerar-map.mjs --selftest` | 0 | SELFTEST OK |
| `node scripts/governance/plans-index.mjs --check` | 0 | em dia (8 registrados, 24 pendentes) |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | 0 | 0 colisão em 2611 ids |
| `node scripts/governance/knowledge-drift.mjs --check` | 0 | 0 NOVOS |
| `node scripts/governance/requisitos-status.mjs Financeiro --check` · `KB --check` | 0 · 0 | em dia |
| `… Backup/Dashboard/PaymentGateway/Superadmin/Suporte --check` | 1 ×5 | `_STATUS-GENERATED.md` não existe (pré-existente, ver observações) |

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos` · 1130 linhas)

| Padrão | Hits | Controle positivo |
|---|---|---|
| CPF pontuado | 0 | casou |
| CPF cru (11 dígitos isolados) | 0 | casou |
| CNPJ | 0 | casou |
| Telefone BR formatado | 0 | casou |
| Telefone cru (10–11 dígitos) | 0 | casou |
| E-mail | 0 | casou |
| Valor em reais (símbolo + dígito; padrão montado sem literal) | 0 | casou |

7/7 controles OK · **pii_hits = 0**.

## Comandos reproduzíveis

```bash
git rev-parse HEAD origin/main --is-shallow-repository
git diff --name-status origin/main...HEAD -- memory/requisitos
for t in Backup/Index Home/Index Financeiro/Cobranca/Index kb/Index.v2 Settings/PaymentGateways/Index Modules/Index Suporte/Empresas; do node prototipo-ui/ancora.mjs "$t" --staging prototipo-ui/cowork; done
node prototipo-ui/gerar-map.mjs memory/requisitos/Dashboard/home-index-gap.md   # acao de grade-por-abas sai truncada
git show origin/main:prototipo-ui/cowork/pg-cobranca-page.jsx | awk 'NR>=118&&NR<=124 || NR>=148&&NR<=160'
git show origin/main:prototipo-ui/cowork/pg-cobranca-page.jsx | grep -n 'em aberto'   # 0 linhas (rc=1); controle: grep -n 'Em aberto' → :173 :184 :390
git ls-tree origin/main -- resources/js/Pages/NAO/EXISTE.tsx   # controle negativo: vazio
gh api repos/wagnerra23/oimpresso.com/commits/9da73296d3 --jq .commit.committer.date
git diff origin/main...HEAD -- memory/requisitos | grep '^+' | grep -v '^+++' > plus.txt   # base do scan PII
node scripts/governance/design-code-map-check.mjs --check --strict; echo rc=$?
```

```json
{"itens_verificados": 393, "erros_confirmados": 2, "error_rate_pct": 0.51, "pii_hits": 0, "veredito": "aprovado"}
```
