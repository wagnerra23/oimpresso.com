---
date: "2026-09-06"
topic: "Refutacao GT-G5 rodada 4 do lote PR #6897 (7 gap.md com tabela de partes + 8 map.json + derivados) — 95 itens, 6 erros, REPROVADO"
authors: ["C"]
prs: [6897]
outcomes:
  - "Lote REPROVADO: error_rate 6.32% (6/95) — Produto/produtos-gap.md re-deriva a coluna DESCRICAO (Gap real) e omite o veredito NAO-adotar-sem-ADR do §Veredito em 4 partes"
  - "Crm/clientes.map.json gerado CONTRA o banner do proprio gap_fonte (2026-08-24: 'por que ainda NAO ha clientes.map.json e por que gerar um hoje seria pior que nao ter')"
  - "Nota de derivacao em 3 gap.md aponta pra session log que nao existe em HEAD nem em origin/main (so untracked no worktree — a evidencia da r1 nao sobe com o PR)"
  - "PII scan: 0 hits em 1414 linhas adicionadas, 6 padroes com controle positivo"
related_adrs: ["0324-identidade-prototipo-por-conteudo", "0344-two-strikes-cobre-processo"]
---

# Refutação GT-G5 — lote PR #6897 (rodada 4)

> Protocolo: [`memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md`](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2–§4.
> Refs medidas: `origin/main` = `26ac293f46` · `HEAD` (branch `claude/q6-gap-md-tabela-e-11-mapas`) = `dffc0cd374`.
> Sessão fresca — nenhum arquivo `memory/sessions/*refutacao*` foi aberto; veredito derivado do zero.

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador nem das rodadas r1–r3)
- [x] Modelo de tier SUPERIOR ao gerador — refutador `fable` (Fable 5.1); gerador `[C]` sem modelo declarado no commit; o ledger deve registrar o modelo real do gerador (§4.2: teto de política `opus`)
- [x] Amostra: 100% anchors (tipo `anchors`) — todos os paths de todos os 8 map.json, todas as chaves de frontmatter dos 7 gap.md, todas as 51 linhas das 7 tabelas derivadas
- [x] Cada item verificado contra `origin/main` (`git ls-tree origin/main`, `git show origin/main:<path>`, charters donos), não contra o diff
- [x] Cada REFUTADO com evidência (path + linha/commit + porquê) — seção "Refutados" abaixo
- [x] Scan PII nas 1414 linhas adicionadas, 6 padrões, cada um com controle positivo — 0 hits
- [x] `error_rate_pct` = 6.32 (≥ 2 → lote REPROVADO)
- [ ] Entry no ledger `governance/sdd-verification-ledger.json` — **não escrita por mim** (mandato: só este arquivo de evidência); o gerador adiciona no mesmo PR com `veredito: reprovado`

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → 21 arquivos: 7 `*-gap.md` (M) + 8 `*.map.json` (A) + 6 `_STATUS-GENERATED.md` (2 A, 4 M). Fora de `memory/requisitos`: só `governance/sdd-scorecard-baseline.json` (absorção `distiller_freshness` 11→12).

Confirmado antes de tudo:

- Os 3 gap.md ditos REVERTIDOS (`_DesignSystem/pageheader-canon-v3-gap.md`, `_DesignSystem/sidebar-v3-unificado-gap.md`, `OficinaAuto/kanban-producao-gap.md`) têm blob **idêntico** a `origin/main` (`git rev-parse origin/main:<f>` = `HEAD:<f>`: `28a788ecf9`, `3b7266c6e7`, `0b6dbddaf4`) e **não** há `.map.json` em `_DesignSystem/` nem `OficinaAuto/`. Os commits `5f57a63841`/`dffc0cd374` tocaram e reverteram — diff final limpo.
- `_processo/PLANS-INDEX-GENERATED.md`: sem diff vs `origin/main` (regenerado = idêntico); `plans-index.mjs --check` rc=0.
- Os 8 map.json foram **regenerados** por mim com `node prototipo-ui/gerar-map.mjs <gap_fonte>` e comparados chave a chave com os commitados: **0 diferenças** em todos (inclusive `prototipo_sha`, `partes[]`, `acao`). O esqueleto é fiel ao gerador; o defeito, onde há, está na **tabela-fonte**.

## Tabela por grupo

| # | Grupo | Itens | Confirmados | Refutados |
|---|---|---|---|---|
| 1 | map.json — paths existem em origin/main + não revogados pelo charter dono (+1 item map-level Crm) | 15 | 14 | **1** |
| 2 | gap.md — `tela_viva`/`prototipo` existem e são lidos por `fmVal`/`resolveGap` | 14 | 14 | 0 |
| 3 | tabela derivada — coluna Ação × veredito da prosa, por linha (+1 item: nota de derivação) | 52 | 47 | **5** |
| 4 | `requisitos-status.mjs <Mod> --check` ×6 + `plans-index.mjs --check` | 7 | 7 | 0 |
| 5 | `design-code-map-check.mjs --check --strict` | 1 | 1 | 0 |
| 6 | scan PII (6 padrões, controle positivo cada) | 6 | 6 | 0 |
| | **Total** | **95** | **89** | **6** |

### Grupo 1 — detalhe (14 paths + 1 map-level)

`git ls-tree origin/main -- <path>` = 1 linha para todos os 14 paths distintos (controle negativo `prototipo-ui/cowork/NAO-EXISTE-page.jsx` → 0; positivo `package.json` → 1):

| map | prototipo.arquivo | vivo.arquivo | charter dono (origin/main) | veredito |
|---|---|---|---|---|
| Cliente/clientes | `prototipo-ui/cowork/clientes-page.jsx` | `resources/js/Pages/Cliente/Index.tsx` | `Cliente/Index.charter.md:5` `related_prototype: clientes-page.jsx` (a revogação da l.78 é de componentes PTDP, não do protótipo) | CONFIRMADO ×2 |
| Compras/compras | `compras-page.jsx` | `Compras/Index.tsx` | `Compras/Index.charter.md:14` related_prototype | CONFIRMADO ×2 |
| KB/kb | `kb-page.jsx` | `kb/Index.tsx` | `kb/Index.charter.md:23` related_prototype | CONFIRMADO ×2 |
| Produto/produtos | `produtos-page.jsx` | `Produto/Index.tsx` | `Produto/Index.charter.md:5` `related_prototype: n/a (herda PT-01)` + `:6 bundle_source: produtos-page.jsx`; `ancora.mjs Produto/Index` resolve `[-page.jsx (bundle)] produtos-page.jsx` — não revogado | CONFIRMADO ×2 |
| RecurringBilling/cobranca-recorrente | `cobranca-recorrente-page.jsx` | `RecurringBilling/Index.tsx` | `RecurringBilling/Index.charter.md:15` related_prototype | CONFIRMADO ×2 |
| Sells/vendas-create | `vendas-create-page.jsx` | `Sells/Create.tsx` | `Sells/Create.charter.md`: **sem** related_prototype nem bundle_source (`ancora.mjs Sells/Create` → "registre o protótipo"); não há revogação (grep MIS-ANCHOR só acha `Repair/ProducaoOficina/Index.charter.md:14`) | CONFIRMADO ×2 (ressalva R-1) |
| Sells/vendas-index | `vendas-page.jsx` | `Sells/Index.tsx` | `Sells/Index.charter.md:7` related_prototype (formalizado 2026-07-09) | CONFIRMADO ×2 |
| Crm/clientes | `TODO` (todas as 11 partes) · `prototipo_sha: sem-arquivo` | `TODO` | — | **REFUTADO** (map-level, ver R-A) |

### Grupo 2 — detalhe

`fmVal` (`prototipo-ui/gerar-contrato.mjs:36`) lê `^<key>:\s*(.+)$` do bloco `frontmatterBlock`; `gerar()` (`:123-124`) consome `tela_viva` e `prototipo`; `gerar-map.mjs:154-155` idem. Os 7 gap.md em HEAD têm `tela`/`prototipo`/`tela_viva` no frontmatter (1 bloco `---` cada, `id:` único — o `Produto` não tinha frontmatter em main e ganhou um bloco íntegro). Os 14 valores são exatamente os 14 paths do grupo 1 → existem em origin/main. 14/14 CONFIRMADO.

### Grupo 3 — detalhe por arquivo (51 linhas + nota)

Fonte comparada: `git show origin/main:<gap.md>` (prosa intacta — a tabela é só APPEND).

| gap.md | linhas | veredito |
|---|---|---|
| Cliente/clientes-gap.md | 7 | 7 CONFIRMADO — todas "Nada — banner de invalidade (l.19)"; l.19 em HEAD é literalmente *"registro datado de 2026-06-30 medido por rota inválida — não é base para decidir hoje"*; o dono citado `PARIDADE-area-cliente-diagnostico-e-ondas.md:199` diz de fato *"**Não** adotar o card 'Faturamento +12% vs ontem'"* |
| Compras/compras-gap.md | 6 | 6 CONFIRMADO — §Veredito ADOTAR-PARCIAL lista as 6 partes em "Adotar (1–6)"; "Decidir." coerente (ressalva R-3: partes 5/6 são `_pendente_` na prosa e a célula perde isso no truncamento) |
| KB/kb-gap.md | 6 | 6 CONFIRMADO — cada Ação cita §Veredito adotar #1–#4 e a lista "NÃO adotar" (l.84-90) corretamente (ressalva R-4: "§6 Esforço" na linha Lista aponta pra Parte **4** l.58, não pra §6) |
| Produto/produtos-gap.md | 6 | **2 CONFIRMADO (Header, Ações por linha) · 4 REFUTADO** (KPIs, Filtros, Tabela, Drawer) — ver R-B..R-E |
| RecurringBilling/cobranca-recorrente-gap.md | 9 | 9 CONFIRMADO — "Nada — `— · —`" bate com a coluna Esforço·risco (l.33-43); os 2 "Decidir." são exatamente ADOTAR-PARCIAL #1 (l.24) e "única ideia visual" (l.39) + micro-gap Tier 0 (l.35) com o qualificador preservado |
| Sells/vendas-create-gap.md | 9 | 9 CONFIRMADO — 9 "Decidir." = 9 "Gap real" com ⚠️ Tier 0 preservado onde a prosa marca (l.40, l.49, l.85); nada da lista "Não adotar" (l.115-118: centavos, toggle, método único) foi reaberto |
| Sells/vendas-index-gap.md | 8 | 8 CONFIRMADO — Header "Nada — divergência intencional" = l.27/l.94-96; Decidir nos itens Adotar #1–#4 (l.84-88) e `_pendente_` nos que a prosa manda "verificar antes" (l.90-92) |
| nota "Derivada MECANICAMENTE…" (Cliente, RecurringBilling, Sells/Index) | 1 | **REFUTADO** — ver R-F |

## Refutados (lista completa, com evidência)

### R-A · `memory/requisitos/Crm/clientes.map.json` — map gerado CONTRA o banner do próprio `gap_fonte`

- **Evidência:** `git show origin/main:memory/requisitos/Crm/clientes-gap.md` l.20: `> ## ⚠️ 2026-08-24 — por que ainda NÃO há clientes.map.json (e por que gerar um hoje seria pior que não ter)`; l.22-25: *"Ficou de fora de propósito: o protótipo que esta análise leu não é o protótipo que está no espelho hoje, e um map ancora os dois lados por arquivo+linha — ancorar o veredito de junho no artefato de agosto seria afirmar correspondência que ninguém verificou"*; l.48-50: *"**O que falta pra existir um map:** reler a Fase 1 contra o espelho atual (...), **não converter esta tabela**. É trabalho de análise, não de geração."* Frontmatter l.4: `prototipo: prototipo-ui/prototipos/clientes/  # ⚠️ PATH APAGADO em 2026-06-23 (e8b49f4b63)`.
- **O que o lote fez:** `A memory/requisitos/Crm/clientes.map.json` (commit `6f208c82b3`), gerado exatamente por "converter esta tabela" — 11 partes com `prototipo.arquivo: TODO`, `vivo.arquivo: TODO`, `prototipo_sha: sem-arquivo`. O gap.md não foi tocado (segue dizendo que o map não deve existir) — o repo passa a carregar um artefato e um banner que se contradizem.
- **Por que é erro (critério do item 3, aplicado ao map):** "reabre um veredito (inclusive banner de invalidade do próprio arquivo)". O map contribui cobertura "23/23" ao `design-code-map-check` sem UMA âncora — é cobertura de forma. Corrigir = remover o map (e deixar o gap na fila de candidatos, como o banner pede) OU refazer a Fase 1 contra o espelho e só então gerar.

### R-B · `Produto/produtos-gap.md` — linha **KPIs / Totalizadores** (Ação omite o veredito NÃO-adotar)

- **Ação no lote:** `**Decidir.** Conceito de totalizadores financeiros de inventário (valor/custo em estoque, margem média) — ausente no vivo. É feature analítica nova. Construir ou rejeitar por escrito.`
- **Prosa (origin/main):** l.31 `**G** · ⚠️ **toca estoque** ... **Tier 0 cálculo de valor/estoque** ... **Só descrever visualmente; backend de agregação = _pendente_** ... Não adotar no olho.`; §Veredito l.84-85: `**NÃO adotar sem ADR/SPEC + backend + dupla-confirmação (⚠️ Tier 0 valor/estoque):** - Totalizadores financeiros de inventário — Parte 2.`
- **Por quê:** a Ação copia a linha `Gap real` (l.29 — DESCRIÇÃO) e descarta a linha `Esforço/risco` (l.31 — VEREDITO) e o §Veredito. No `produtos.map.json` (que só carrega `acao`) a parte `kpis-totalizadores` sai **sem** Tier 0, sem ADR, sem dupla-confirmação: `grep -c "Tier 0|dupla|NÃO adotar" produtos.map.json` = **0**. É a mesma falha que o próprio lote registra como motivo da reprovação r1 nos outros arquivos ("ler a coluna de DESCRIÇÃO em vez da de VEREDITO") — o Produto ficou na derivação r1.

### R-C · `Produto/produtos-gap.md` — linha **Drawer de detalhe**

- **Ação no lote:** `**Decidir.** Drawer inteiro é escopo novo grande: tabela de preços por nível, fornecedores/cotação, variantes-SKU, BOM, OEM, ficha técnica. Nenhum existe no vivo. Construir ou rejeitar por escrito.`
- **Prosa:** l.71 `**G** · alto · ⚠️ toca estoque/valor em vários pontos ... **Tier 0 cálculo de valor** ... Drawer **NÃO adotar no olho**; cada cálculo = _pendente_ de backend + prova dupla.`; §Veredito l.87: `- Drawer rico: tabela de preços 4 níveis, fornecedores/cotação, variantes-SKU, BOM — _Parte 6_.` (lista NÃO adotar sem ADR/SPEC).
- **Por quê:** idem R-B — veredito omitido; a parte `drawer-de-detalhe` do map fica "construir ou rejeitar" sem o gate.

### R-D · `Produto/produtos-gap.md` — linha **Filtros / Busca** (mistura item NÃO-adotar como Decidir sem gate + truncamento derruba item ADOTAR)

- **Ação no lote (e `acao` do map, idêntica):** `**Decidir.** (a) Filtro por tipo (...); (b) Stockbar de estoque ⚠️; (c) sort por coluna; (d) toggle view lista/cards; (e) busca por mais campos (marca/OEM — _depende de schema ter esses… Construir ou rejeitar por escrito.`
- **Prosa:** §Veredito l.81-82 adota **(a) tipo + (f) "Limpar filtros"** (#2) e **(c) sort + (d) toggle** (#3); l.86 põe **(b) Stockbar** em `NÃO adotar sem ADR/SPEC + backend + dupla-confirmação`.
- **Por quê:** (1) `(b) Stockbar` entra no "Decidir" sem citar que o §Veredito já o fechou como NÃO-adotar-sem-ADR (o ⚠️ sobrevive, o veredito não); (2) o truncamento em `…` corta **`(f) "Limpar filtros"`**, que é item ADOTAR #2 — no map: `grep -c Limpar produtos.map.json` = **0**. A célula omite um veredito de adoção e reabre um de não-adoção na mesma linha.

### R-E · `Produto/produtos-gap.md` — linha **Tabela / Cards (lista)**

- **Ação no lote:** `**Decidir.** (a) Modo lista densa (...); (b) faixa de preço quando há variantes; (c) coluna custo/margem/fornecedor ⚠️; (d) coluna estoque por linha ⚠️; (e) thumb/cor por… Construir ou rejeitar por escrito.`
- **Prosa:** l.51 `Colunas custo/margem/estoque/esgotados ⚠️ toca estoque/valor — Tier 0, exige backend ... que o vivo não expõe hoje`; §Veredito l.82 adota lista densa+sort **"(colunas custo/margem ficam de fora até backend)"**; l.86 e l.88: `Stockbar + coluna estoque/esgotados` e `Faixa de preço por variantes, custo/margem por fornecedor` = **NÃO adotar sem ADR/SPEC + backend + dupla-confirmação**.
- **Por quê:** (b), (c), (d) são itens da lista NÃO-adotar apresentados como "Decidir/Construir ou rejeitar" sem o gate; truncamento derruba (f) prateleira (`_negócio real pendente_`) e (g) seção Esgotados (também NÃO-adotar). Mesma classe de R-D.

### R-F · Nota de derivação em 3 gap.md aponta para session log INEXISTENTE

- **Texto adicionado** (Cliente, RecurringBilling, Sells/vendas-index): `... ver memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897.md`.
- **Evidência:** `git ls-tree HEAD -- memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897.md` → **0**; `git ls-tree origin/main -- ...` → **0**; `git status --short memory/sessions` → `??` (o arquivo existe **só untracked** no worktree, junto com os `-r2`/`-r3` — nenhum commitado). Ou seja: o PR, como está, sobe canon apontando pra evidência que **não sobe com ele**. (Não abri nenhum deles — só medi existência, conforme o mandato.)
- **Por quê:** ponteiro quebrado em doc canônico; como é path em prosa (não link markdown), o `deadlink-gate` não pega. Contado como **1** erro (mesmo defeito ×3 ocorrências).

## Ressalvas (NÃO contadas como erro — decisão do gerador/[W])

- **R-1 · Sells/Create ganha âncora que nenhum charter sustenta.** O frontmatter novo de `vendas-create-gap.md` declara `prototipo: prototipo-ui/cowork/vendas-create-page.jsx` e o map ancora as 9 partes nele; `Sells/Create.charter.md` (origin/main) não tem `related_prototype` nem `bundle_source` (`ancora.mjs Sells/Create` → "registre o protótipo"). O `gerar-map` só WARNa quando o charter cita OUTRO arquivo (`gerar-map.mjs:326-336`), então passa mudo. É fiel à prosa do gap (l.9: "Mockup: .../vendas-create-page.jsx"), e o arquivo existe no espelho desde `9da73296d3` (2026-06-23) — mas §5 2026-08-28 (b) manda decidir proveniência par-a-par, e aqui a âncora nasceu só no gap.md. Não é erro do lote; é decisão pendente.
- **R-2 · 25 de 51 células Ação truncadas em `…`** (Compras 5/6, Produto 2/6, RecurringBilling 4/9, vendas-create 8/9, vendas-index 6/8). Onde o texto cortado é só descrição, não muda veredito (por isso não contei); onde corta item de veredito (Produto), contei em R-D/R-E. O truncamento a ~200 chars é do gerador da tabela — vale trocar por "Decidir. <item(s) do §Veredito>" (como o KB r4 fez) em vez de copiar+cortar.
- **R-3 · Compras partes 5 e 6:** prosa diz `_pendente_` (ler `AcoesDropdown.tsx`/`Drawer.tsx` antes); a Ação diz "Decidir. ... Construir ou rejeitar por escrito" sem o `_pendente_` (o vendas-index r2 preservou "(_pendente_ confirmar, como a prosa diz)"). Coerente com "Adotar #5/#6" do §Veredito, então CONFIRMADO — mas a assimetria entre arquivos é sinal de derivação não-uniforme.
- **R-4 · KB linha "Lista":** cita "§6 Esforço: dado já existe" — o texto está na **Parte 4** (`### 4. Lista de artigos / docs`, l.58 `sort por frescor = **P** (dado já existe)`), não na §6 (Drawer). Se "§6" = 6º bullet da parte, é ambíguo; se = seção 6, é ponteiro errado. Conteúdo correto, citação frágil.
- **R-5 · `governance/sdd-scorecard-baseline.json` `nota_absorcao_2026_09_06`:** diz *"Os outros 9 modulos tocados (Compras, Crm, KB, OficinaAuto, Produto, RecurringBilling, Sells, _DesignSystem)"* — enumera **8**, e `OficinaAuto`/`_DesignSystem` **não são mais tocados** no diff final (revertidos em `5f57a63841`/`dffc0cd374`); os tocados de fato incluem Estoque/Fiscal/Ponto/Repair (`_STATUS`). A medição 11→12 (+Cliente) não depende disso, mas a prosa da nota ficou stale dentro do próprio PR. Fora de `memory/requisitos`, fora dos 6 grupos — registro, não contagem.
- **R-6 · Crm map = cobertura de forma.** Mesmo que o R-A seja resolvido refazendo a Fase 1, hoje o `design-code-map-check` conta o Crm como "tela com map" tendo 0 âncoras e `prototipo_sha: sem-arquivo`; a régua "cobertura 23/23" do PR mede presença de arquivo, não âncora.

## Grupos 4–6 — recibos

```
requisitos-status Crm --check rc=0 · Estoque rc=0 · Fiscal rc=0 · Ponto rc=0 · RecurringBilling rc=0 · Repair rc=0
plans-index --check rc=0
design-code-map-check --check --strict rc=0  ("[OK] nenhum map.json com âncora quebrada ou sha stale")
```

Scan PII — `git diff origin/main...HEAD -- memory/requisitos | grep '^+'` → 1414 linhas; cada padrão rodado também contra um arquivo de controle com 1 exemplar fictício:

| padrão | hits nas adicionadas | controle positivo |
|---|---|---|
| CPF pontuado (3.3.3-2) | 0 | 1 |
| CPF cru (11 dígitos isolados) | 0 | 1 |
| CNPJ (2.3.3/4-2) | 0 | 1 |
| telefone BR (DDD opcional + 4/5-4) | 0 | 2 |
| e-mail | 0 | 1 |
| valor em reais (`R$` seguido de dígito, com/sem espaço) | 0 | 2 |

Únicas ocorrências de `R$` nas linhas adicionadas: `R$ [redacted Tier 0]` (Compras KPIs, 3×) e `(R$ a recuperar)` (RecurringBilling, texto sem número). O valor de meta da prosa de origin/main (`compras-gap.md:30`) **não** foi copiado — foi redigido (commit `75cca7ccb5`). Nomes de cliente: só os canônicos públicos do próprio canon (ROTA LIVRE/Larissa/WR2 já em `memory/`), nenhum nome de contato do CRM.

## Veredito

**REPROVADO** — 6 erros em 95 itens (6.32% ≥ 2%). Pelo §2.6, o lote volta ao gerador e a **re-verificação cobre o lote inteiro**. O erro sistemático está em **um** arquivo (Produto: derivação r1 nunca refeita, ao contrário de Cliente/RecurringBilling/Sells-Index r2 e KB r4) + **um** artefato que o próprio canon proibia (Crm map) + **um** ponteiro quebrado repetido. Os outros 5 gap.md/7 map.json passaram limpos.

```json
{"itens_verificados": 95, "erros_confirmados": 6, "error_rate_pct": 6.32, "pii_hits": 0, "veredito": "reprovado"}
```
