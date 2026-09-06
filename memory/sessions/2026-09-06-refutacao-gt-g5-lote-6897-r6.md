---
date: "2026-09-06"
topic: "Refutação GT-G5 rodada 6 — PR #6897 (5 gap.md de tela + 5 map.json + derivados) — 62 itens, 2 refutados (3,2%), PII 0 → REPROVADO"
authors: ["C"]
prs: [6897]
outcomes:
  - "2 erros confirmados: KB 'Lista' cita §6 Esforço (é Parte 4 l.58) · Produto 'Tabela' afirma 'vivo não expõe cost' (ProductController l.441 expõe)"
  - "60 itens confirmados: 10 paths, 10 chaves de frontmatter, 32/34 linhas, 7 checks de status/plans, map-check strict"
  - "PII: 0 hits em 927 linhas adicionadas, 6/6 padrões com controle positivo"
---

# Refutação GT-G5 · rodada 6 · PR #6897 (`claude/q6-gap-md-tabela-e-11-mapas`)

> Protocolo: [`PROTOCOLO-REFUTADOR-BACKFILL.md`](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2–§4.
> Refutador em sessão fresca, sem ler nenhuma rodada anterior (r1–r5 existem no branch; ignoradas por instrução).
> Base medida: `origin/main` = `26ac293f46` (fetch feito na sessão; `is-shallow-repository=false`) · HEAD = `86240898ee`.
> Lote = `git diff --name-status origin/main...HEAD -- memory/requisitos` → 16 arquivos: 5 `*-gap.md` (M), 5 `*.map.json` (A), 6 `_STATUS-GENERATED.md` (Crm A · RecurringBilling A · Estoque/Fiscal/Ponto/Repair M). `_processo/PLANS-INDEX-GENERATED.md` NÃO aparece no diff (blob idêntico ao main).

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador; arquivos `*refutacao*` não abertos)
- [x] Modelo de tier SUPERIOR ao gerador — refutador `claude-fable-5-1`; gerador assinado `[C]` nos 11 commits (modelo não declarado no commit; fable é o tier máximo da tabela §2.3, portanto ≥ qualquer gerador Anthropic)
- [x] Amostra: 100% anchors (tipo `anchors` — todos os paths, chaves, linhas e checks; sem seleção aleatória, seed n/a)
- [x] Cada item verificado contra `origin/main` (`git ls-tree`, `git show origin/main:<path>`, `git grep origin/main`), não contra o diff
- [x] Cada REFUTADO com path + linha + porquê (abaixo)
- [x] Scan PII no diff (linhas `+`), com controle positivo por padrão
- [x] `error_rate_pct` calculado = **3,23** (≥ 2 → reprovado)
- [ ] Entry no ledger `governance/sdd-verification-ledger.json` — **não escrita por este refutador** (instrução: escrever UM arquivo de evidência; a entry é do gerador/PR)

## Pré-condições do lote (fora da contagem)

| Verificação | Resultado |
|---|---|
| Prosa dos 5 gap.md intacta | `git diff` mostra **0 linhas removidas** em cada um (Cliente 120→137, Compras 77→93, KB 92→108, Produto 90→110, RB 53→72); só frontmatter + seção nova |
| Produto ganhou frontmatter inteiro | correto: em `origin/main` o arquivo **não tinha** frontmatter (começa em `# Gap — Produto/Index`); HEAD tem 1 bloco só, com `id` preservado |
| Revertidos blob-idênticos a `origin/main` | `_DesignSystem/pageheader-canon-v3-gap.md` · `sidebar-v3-unificado-gap.md` · `OficinaAuto/kanban-producao-gap.md` · `Sells/vendas-create-gap.md` · `vendas-index-gap.md` · `vendas-gap.md` · `Crm/clientes-gap.md` → **7/7 IGUAL** (`git rev-parse origin/main:<f>` = `HEAD:<f>`); nenhum `.map.json` fora dos 5 |
| Re-geração `gerar-map.mjs --stdout` × commitado | **5/5 IDÊNTICO** (ignorando `gerado_em`); `prototipo_sha` bate (8f284ad79fb3 · 1096106a9f83 · 3f2c78b83b65 · 07a4cea7662a · 40e51d3a1e6a) → `acao` do map = texto da tabela, `_acionavel` coerente com "Decidir."/"Nada" |
| Integridade de célula | 34/34 linhas das tabelas com exatamente 4 pipes (3 células) |
| Working tree | limpo (`git status --short` vazio) |

## Grupo 1 — `.map.json`: paths existem em `origin/main` e protótipo não revogado (10 itens)

| Mapa | `prototipo.arquivo` | `vivo.arquivo` | `ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork` | Veredito |
|---|---|---|---|---|
| Cliente | `prototipo-ui/cowork/clientes-page.jsx` ✓ ls-tree | `resources/js/Pages/Cliente/Index.tsx` ✓ | âncora ✓ `[bundle · bundle_source] clientes-page.jsx`; charter l.5 `related_prototype` = mesmo path. Revogação do charter (l.78) é de componentes PTDP (BrunaGreeting/SavedViews), não do protótipo | 2 CONFIRMADO |
| Compras | `compras-page.jsx` ✓ | `Compras/Index.tsx` ✓ | âncora ✓ (heurística startsWith); charter l.14 `related_prototype` = mesmo path | 2 CONFIRMADO |
| KB | `kb-page.jsx` ✓ | `kb/Index.tsx` ✓ | âncora ✓ `bundle_source`; charter l.23 `related_prototype` = mesmo path | 2 CONFIRMADO |
| Produto | `produtos-page.jsx` ✓ | `Produto/Index.tsx` ✓ | charter l.5 `related_prototype: n/a (herda PT-01…)` **mas** l.6 `bundle_source: produtos-page.jsx` + l.18 `blueprint_cowork` + l.178 → `ancora.mjs` resolve ✓ `[bundle · bundle_source] produtos-page.jsx` e classifica o `n/a` como "declaração legítima" (coexistência por desenho, §5 2026-08-28 c). O aviso do `gerar-map.mjs` ("âncora do charter não cita…") lê só `related_prototype` — limitação do gerador, não revogação | 2 CONFIRMADO (ressalva) |
| RecurringBilling | `cobranca-recorrente-page.jsx` ✓ | `RecurringBilling/Index.tsx` ✓ | âncora ✓ `bundle_source`; charter l.14/15 `visual_source`/`related_prototype` = mesmo path | 2 CONFIRMADO |

**10/10 confirmados.**

## Grupo 2 — frontmatter `tela_viva` / `prototipo` existem e são lidos pelo leitor real (10 itens)

- Leitor: `prototipo-ui/gerar-contrato.mjs` **idêntico** em HEAD e `origin/main` (`git diff --stat` vazio). `fmVal` (l.36) lê `^key:` case-insensitive; l.123 `pagesPath(fmVal(fm,'tela_viva'))`, l.124 `fmVal(fm,'prototipo')` — as duas chaves adicionadas são exatamente as consumidas.
- Execução real (`node prototipo-ui/gerar-contrato.mjs <gap.md>`), 5/5 resolvem `alvo` + `fonte` a partir do frontmatter:

| gap.md | `alvo` | `fonte` | ambos existem no main |
|---|---|---|---|
| Cliente | `resources/js/Pages/Cliente` | `prototipo-ui/cowork/clientes-page.jsx` | ✓ ✓ |
| Compras | `resources/js/Pages/Compras` | `prototipo-ui/cowork/compras-page.jsx` | ✓ ✓ |
| KB | `resources/js/Pages/kb` | `prototipo-ui/cowork/kb-page.jsx` | ✓ ✓ |
| Produto | `resources/js/Pages/Produto` | `prototipo-ui/cowork/produtos-page.jsx` | ✓ ✓ |
| RecurringBilling | `resources/js/Pages/RecurringBilling` | `prototipo-ui/cowork/cobranca-recorrente-page.jsx` | ✓ ✓ |

**10/10 confirmados.**

## Grupo 3 — coluna "Ação" × veredito da prosa em `origin/main` (34 itens)

Prosa lida integralmente via `git show origin/main:<gap.md>` (Cliente 120 ln · Compras 77 · KB 92 · Produto 90 · RB 53). Linhas citadas = numeração do main.

### Cliente (7 linhas) — 7 CONFIRMADO
Todas "Nada — … banner, l.19 … dono vigente `PARIDADE-area-cliente-diagnostico-e-ondas.md` (que já registra: não adotar o card Faturamento)".
- Banner (main l.11–23; HEAD l.19 = "**registro datado de 2026-06-30 medido por rota inválida** — não é base para decidir hoje") supersede a Síntese l.106–112 (ADOTAR-PARCIAL #2/#3, ADR feature-wish #1) — "Nada" é o veredito vigente do próprio arquivo. ✓
- Afirmação sobre o dono vigente **provada**: `PARIDADE-area-cliente-diagnostico-e-ondas.md` existe no main e l.199 diz literalmente *"**Não** adotar o card 'Faturamento +12% vs ontem' do mockup"*. ✓

### Compras (6 linhas) — 6 CONFIRMADO
| Linha | Âncora na prosa (main) |
|---|---|
| Header → adotar #2, P, conferir `os-page-h*` no css | §Veredito l.67 + Parte 1 l.27 ✓ |
| KPIs → adotar #4, só label, Regra Mestre, `_pendente_` payload | l.69 + l.32–33 ✓ |
| Filtros/Tabs → adotar #1, `.length` em memória | l.66 + l.39 ✓ |
| Tabela → adotar #3, `_pendente_` `items_count`/XML | l.68 + l.44–45 ✓ |
| Ações → adotar #5 `_pendente_` ler `AcoesDropdown.tsx`, Tier 0 reembolso/pagamentos | l.70 + l.48–51 ✓ |
| Drawer → adotar #6, G, `ExecuteStageActionService` + Regra Mestre, sessão limpa por tab | l.71 + l.57 ✓ |

### KB (6 linhas) — 5 CONFIRMADO · 1 REFUTADO
| Linha | Âncora | Veredito |
|---|---|---|
| Header → só "Perguntar ao KB" + "Saúde" (#4, M/G); Trilhas/Troubleshooter/Composer NÃO | §Veredito l.88 + l.90; Parte 1 l.28/31 | ✓ |
| Navegação → Recentes+Favoritos+tags (#2, tags `_pendente_`); categorias de gráfica NÃO | l.86 + l.90; Parte 2 l.37/40 | ✓ |
| Busca → ⌘K mantendo server-side (#3); trocar = regressão | l.87 + l.90; Parte 3 l.49 | ✓ |
| **Lista de artigos** → "só sort por frescor (P — **§6 Esforço**: dado já existe)" | A frase "sort por frescor (`updated_at`/`indexed_at`) = **P** (dado já existe)" está na **Parte 4** (`### 4. Lista…`, l.58). A **§6** é `### 6. Drawer / modais auxiliares`, cujo Esforço (l.76) diz *"Saúde do KB = M; IA = M/G. Demais = não-adotar"* — não sustenta a Ação. Agravante: o cabeçalho da tabela promete "cada Ação cita a seção e o item do §Veredito", e sort-por-frescor **não consta** de "Adotar" #1–#4 (l.85–88) | **REFUTADO** — recibo aponta pra seção errada |
| Editor → TOC+prev/próximo (#1) + histórico funcional (#3, `history_count`); comentários/Composer NÃO | l.85, l.87, l.90; Parte 5 l.65/67 | ✓ |
| Drawer → Saúde adaptado (#4, M); Troubleshooter/Apresentação/Imprimir/anexar-OS NÃO | l.88 + l.90; Parte 6 l.73/76 | ✓ |

### Produto (6 linhas) — 5 CONFIRMADO · 1 REFUTADO
| Linha | Âncora | Veredito |
|---|---|---|
| Header → adotar #1 (Parte 1, P), "disponíveis/esgotados" só se estoque vier nos rows | §Veredito l.80 + Parte 1 l.21 | ✓ |
| KPIs → Nada, NÃO adotar sem ADR/SPEC (Parte 2, G, qtd×preço×custo, Regra Mestre) | l.85 + l.31 | ✓ |
| Filtros → (a)+(f) #2, (c)+(d) #3; (b) Stockbar `_pendente_` threshold; (e) marca/OEM schema | l.81–82, l.86; Parte 3 l.39/41 | ✓ |
| **Tabela / Cards** → "… NÃO adotar … (Tier 0; **o vivo não expõe cost/qty hoje**)" | Código em `origin/main`: `app/Http/Controllers/ProductController.php` `buildRows` l.441 `'cost' => $cost` (de `MIN(v.dpp_inc_tax)`), l.442 `'margin' => $margin` (calculada), l.443 `'stockQty' => null`; `resources/js/Pages/Produto/Index.tsx` l.54–56 `cost/margin/stockQty` em `ProdutoRow`. O vivo **expõe `cost` e `margin`** no payload; só `qty` é null. A prosa (l.51) diz *"exige backend (fornecedores, custo, qty) que o vivo não expõe hoje (**ProdutoRow tem cost/margin/stockQty** mas sem fornecedores/variantes)"* — a Ação **descartou o parêntese corretivo** e trocou o item ausente (fornecedores/variantes) por "cost", produzindo afirmação falsa contra o código (família da lápide §5 2026-08-12: abreviação que induz erro). O veredito NÃO adotar em si está correto | **REFUTADO** — justificativa inventada/invertida vs código |
| Ações → drawer-on-click = paradigma, Parte 5, M, "precisa charter/Wagner" | l.61 + l.90 | ✓ |
| Drawer → Nada, Parte 6, G/alto, multiplicadores = regra de negócio | l.71 + l.87 | ✓ |

### RecurringBilling (9 linhas) — 9 CONFIRMADO (1 ressalva não contada)
| Linha | Âncora | Veredito |
|---|---|---|
| Header/hero → Nada `— · —` | tabela main l.33 | ✓ (desc. do vivo truncada com "…", veredito intacto) |
| KPIs → Decidir micro-gap "A recuperar", P-M, Tier 0 só propor | l.35 + Notas Tier 0 l.48 + Conclusão l.53 | ✓ |
| Filtros → ADOTAR-PARCIAL #1 PeriodBar, só visual, P, gate F1.5+Wagner | l.24 (omite "Não urgente"/"vivo já cobre" — não altera o veredito) | ✓ |
| Tabela → Nada | l.37 | ✓ |
| Ações/drawer → Decidir "única ideia visual: overlay + aba IA + footer", P, gate F1.5 | l.39 (célula Gap + Esforço copiadas). **Ressalva:** o "Por quê" da mesma linha diz que overlay×3-col *"é decisão já tomada no charter (3-col base)"* e o ADOTAR-PARCIAL #2 (l.25) restringe a "aba ✦ IA"; charter `RecurringBilling/Index.charter.md` l.24/39/94 fixa 3-col. A prosa se contradiz na própria linha; a Ação reproduz a metade "ideia visual". Não contado como erro do lote (fonte auto-contraditória), registrado pra [W] | ✓ (ressalva) |
| Criação → Nada | l.40 | ✓ |
| Editar → Nada, ⚠️ toca valor | l.41 | ✓ |
| Abas → Nada | l.42 | ✓ |
| Domínio → Nada | l.43 | ✓ |

**Grupo 3: 32/34 confirmados · 2 refutados.**

## Grupo 4 — derivados por comando (7 itens)

| Comando | rc |
|---|---|
| `requisitos-status.mjs Crm --check` | 0 |
| `requisitos-status.mjs Estoque --check` | 0 |
| `requisitos-status.mjs Fiscal --check` | 0 |
| `requisitos-status.mjs Ponto --check` | 0 |
| `requisitos-status.mjs RecurringBilling --check` | 0 |
| `requisitos-status.mjs Repair --check` | 0 |
| `plans-index.mjs --check` | 0 |

**7/7 confirmados.**

## Grupo 5 — `design-code-map-check.mjs --check --strict` (1 item)

rc = **0** — "[OK] nenhum map.json com âncora quebrada ou sha stale. 2 âncora(s) TODO pendente(s) (não é drift)". Lista os 6 gap.md sem map (Crm, OficinaAuto, Sells×2, _DesignSystem×2) como candidatos — coerente com o escopo declarado do lote. **1/1 confirmado.**

## Grupo 6 — scan PII nas linhas adicionadas

Corpus: `git diff origin/main...HEAD -- memory/requisitos` filtrado a linhas `+` (sem `+++`) = **927 linhas**. Cada padrão rodado com `grep -cE` no corpus e, com as MESMAS flags, numa fixture sintética de controle (1 exemplar por classe; 2 pra reais):

- CPF pontuado `[0-9]{3}[.][0-9]{3}[.][0-9]{3}-[0-9]{2}` → diff **0** · controle 1 ✓
- CPF cru (11 dígitos isolados por não-dígito/borda) → diff **0** · controle 1 ✓
- CNPJ `[0-9]{2}[.][0-9]{3}[.][0-9]{3}/[0-9]{4}-[0-9]{2}` → diff **0** · controle 1 ✓
- Telefone BR (DDD opcional entre parênteses, 9 opcional, `dddd-dddd`) → diff **0** · controle 1 ✓
- E-mail `[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+[.][A-Za-z]{2,}` → diff **0** · controle 1 ✓
- Valor em reais `R[$] ?[0-9]` → diff **0** · controle 2 ✓

Nomes: o diff repete da prosa já em main "WR2 Sistemas", "Padaria, Auto Posto…" (mock declarado fictício, RB l.43/50) e "Eliana [E] / Larissa" (time interno / cliente piloto, já canon). Nenhum nome de cliente do CRM novo. **pii_hits = 0.**

## Lista COMPLETA dos REFUTADOS

1. **`memory/requisitos/KB/kb-gap.md` — tabela derivada, linha "Lista de artigos / docs"** (e `kb.map.json` parte `lista-de-artigos-docs`, mesmo texto). Ação cita "§6 Esforço: dado já existe". Evidência: `origin/main:memory/requisitos/KB/kb-gap.md` l.58 (Parte 4) contém a frase; l.76 (Parte 6 Esforço) não a contém e fala de Saúde/IA. Recibo aponta pra seção errada; item também ausente do §Veredito "Adotar" #1–#4 (l.85–88) que o cabeçalho da tabela promete citar.
2. **`memory/requisitos/Produto/produtos-gap.md` — linha "Tabela / Cards (lista)"** (e `produtos.map.json` parte `tabela-cards`). Ação afirma "o vivo não expõe cost/qty hoje". Evidência: `origin/main:app/Http/Controllers/ProductController.php` l.441 `'cost' => $cost` e l.442 `'margin' => $margin` (populados; só l.443 `'stockQty' => null`); `origin/main:resources/js/Pages/Produto/Index.tsx` l.54–56. A prosa-fonte (l.51) diz explicitamente "ProdutoRow tem cost/margin/stockQty" — a Ação descartou esse parêntese e substituiu o item realmente ausente (fornecedores/variantes) por "cost".

Ambos são erros de **âncora/recibo** (a decisão Decidir/Nada de cada linha está correta); pelo §2.6 o lote reprova inteiro e o gerador re-verifica todas as linhas, não só as duas.

## Ressalvas (não contadas)

- RB "Ações por linha / drawer": prosa auto-contraditória (l.39 Gap+Esforço × l.39 Por quê + l.25) — decisão de redação pra [W], não erro do gerador.
- Produto: `gerar-map.mjs` avisa que o charter "não cita" o protótipo porque lê só `related_prototype`; `ancora.mjs` (porta canônica) resolve via `bundle_source`. Limitação do gerador, não do lote.
- RB linhas "Nada": descrição do vivo truncada com "…" (Header, Tabela, Criação) — veredito `— · —` íntegro; a `_doc` do map declara a tabela como âncora de região, não como fonte.

## Contagem

| Grupo | Itens | Confirmados | Refutados |
|---|---|---|---|
| 1 map paths + revogação | 10 | 10 | 0 |
| 2 frontmatter ↔ leitor | 10 | 10 | 0 |
| 3 Ação × prosa | 34 | 32 | 2 |
| 4 status/plans --check | 7 | 7 | 0 |
| 5 map-check strict | 1 | 1 | 0 |
| **Total** | **62** | **60** | **2** |

```json
{"itens_verificados": 62, "erros_confirmados": 2, "error_rate_pct": 3.23, "pii_hits": 0, "veredito": "reprovado"}
```
