---
date: "2026-09-06"
topic: "Refutação GT-G5 r7 — lote PR #6897 (5 gap.md de tela + 5 map.json + derivados)"
authors: ["C"]
prs: [6897]
outcomes:
  - "67 itens verificados contra origin/main (26ac293f46) · 3 REFUTADOS · error_rate 4,48% · PII 0 → lote REPROVADO"
  - "Compras: Drawer 5 tabs e AcoesDropdown com 9 ações existem em main desde 2026-05-21 (#1315) — a tabela restateia 'vivo tem 2 tabs' / 'possivelmente subset' como Estado no vivo datado de hoje"
  - "Produto/Filtros: Ação inventa gate 'ADR' para busca marca/OEM que a prosa não exige ('P se campos existirem'); products.brand_id existe no schema"
---

# Refutação GT-G5 — rodada 7 · PR #6897 (`claude/q6-gap-md-tabela-e-11-mapas`)

> Protocolo: [PROTOCOLO-REFUTADOR-BACKFILL.md](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2, §3, §4.
> Sessão fresca, zero contexto do gerador; os `*refutacao*` de rodadas anteriores no branch **não foram abertos** (refutação do zero).
> Base medida: `HEAD ee09283d10` × `origin/main 26ac293f46` (fetch feito na sessão; `git rev-parse --is-shallow-repository = false`).

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (refutador: Fable 5.1 · gerador: Claude Code [C] das rodadas r1–r6, tier ≤ opus; fable > opus)
- [x] Amostra: 100% anchors (tipo `anchors`; sem prosa destilada amostrada — não há BRIEFING no lote)
- [x] Cada item verificado contra o código real em origin/main (`git ls-tree`, `git show origin/main:<path>`, `git log origin/main`), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [x] Scan PII no diff (linhas adicionadas em `memory/requisitos`, 927 linhas) com controle positivo por padrão — 0 hits
- [x] `error_rate_pct` calculado = **4,48** (≥ 2 → reprovado)
- [ ] Entry no ledger — **não cabe a esta rodada** (refutador só lê/mede; a entry vai com o lote corrigido)

## Escopo medido (o que o diff `origin/main...HEAD -- memory/requisitos` contém)

| Arquivo | Natureza |
|---|---|
| `Cliente/clientes-gap.md` · `Compras/compras-gap.md` · `KB/kb-gap.md` · `Produto/produtos-gap.md` · `RecurringBilling/cobranca-recorrente-gap.md` | frontmatter (`tela`/`prototipo`/`tela_viva`) + seção "Tabela de partes (derivada …)" |
| `Cliente/clientes.map.json` · `Compras/compras.map.json` · `KB/kb.map.json` · `Produto/produtos.map.json` · `RecurringBilling/cobranca-recorrente.map.json` | gerados por `prototipo-ui/gerar-map.mjs` |
| `_STATUS-GENERATED.md` de Crm · Estoque · Fiscal · Ponto · RecurringBilling · Repair | regenerados por comando |

Conferido também: `_DesignSystem/*-gap.md` (2), `OficinaAuto/kanban-producao-gap.md`, `Sells/vendas-*-gap.md` (2) — `git diff --stat origin/main HEAD` vazio (blob-idênticos ao main) e **nenhum** `map.json` novo (o `Sells/vendas.map.json` presente em HEAD já existe em origin/main). `Crm/clientes-gap.md` intocado e sem `map.json` em HEAD (0). `memory/_processo/PLANS-INDEX-GENERATED.md` idêntico ao main. Fora do escopo `memory/requisitos` mas no diff: `governance/sdd-scorecard-baseline.json` (`distiller_freshness` 11→12 + nota de absorção) — ver Observações.

## Resultado por grupo

| # | Grupo | Itens | CONFIRMADO | REFUTADO |
|---|---|---|---|---|
| 1 | Âncoras dos `.map.json` (`prototipo.arquivo` + `vivo.arquivo`, 1 por path distinto por mapa) | 10 | 10 | 0 |
| 2 | Frontmatter `tela_viva` / `prototipo` dos 5 gap.md (existe em main + lido por `fmVal` em `gerar-map.mjs:153-155`, importado de `gerar-contrato.mjs`) | 10 | 10 | 0 |
| 3 | Tabela derivada: 1 item por linha (Cliente 7 · Compras 6 · KB 6 · Produto 6 · RB 9) + 1 item por mapa "acao do map == célula da tabela" | 39 | 36 | **3** |
| 4 | `requisitos-status.mjs <Mod> --check` × 6 + `plans-index.mjs --check` | 7 | 7 | 0 |
| 5 | `design-code-map-check.mjs --check --strict` | 1 | 1 | 0 |
| — | **Total** | **67** | **64** | **3** |

### Grupo 1 — detalhe (todos existem em origin/main; protótipo não revogado pelo charter dono)

| Mapa | `prototipo.arquivo` (blob main) | `vivo.arquivo` (blob main) | Charter dono → âncora (`ancora.mjs --staging prototipo-ui/cowork`) |
|---|---|---|---|
| Cliente | `prototipo-ui/cowork/clientes-page.jsx` (3531b5f) | `resources/js/Pages/Cliente/Index.tsx` (a686cc6) | `related_prototype` ✓ + bundle ✓ (a "REVOGADA" do charter l.78 é a PTDP Onda 1 — BrunaGreeting/SavedViews —, não o protótipo) |
| Compras | `prototipo-ui/cowork/compras-page.jsx` (6511d8f) | `resources/js/Pages/Compras/Index.tsx` (e444863) | `related_prototype` ✓ + bundle ✓ |
| KB | `prototipo-ui/cowork/kb-page.jsx` (b3f006d) | `resources/js/Pages/kb/Index.tsx` (ce2ceb8) | `related_prototype` ✓ + bundle ✓ |
| Produto | `prototipo-ui/cowork/produtos-page.jsx` (c9b5e86) | `resources/js/Pages/Produto/Index.tsx` (f3eb4c9) | `related_prototype: n/a (herda PT-01…)` = declaração legítima; **âncora ✓ via `bundle_source: produtos-page.jsx`** — coexistência prevista (§5 2026-08-28 c). O WARN do `gerar-map` ("âncora computada não cita produtos-page.jsx") é do cross-check por basename, não revogação |
| RecurringBilling | `prototipo-ui/cowork/cobranca-recorrente-page.jsx` (401733b) | `resources/js/Pages/RecurringBilling/Index.tsx` (cb49755) | `related_prototype` ✓ + `visual_source` ✓ + bundle ✓ |

### Grupo 3 — prova mecânica "acao do map == tabela"

Re-gerei os 5 mapas com `node prototipo-ui/gerar-map.mjs <gap.md>` (stdout → scratchpad, `git status` limpo depois) e comparei JSON-a-JSON com o commitado, ignorando só `gerado_em`: **5/5 idênticos** (mesmo `prototipo_sha`, mesmas `partes[].acao`). Logo o texto de `acao` é o da célula "Ação" por construção.

## REFUTADOS — lista completa com evidência

### R1 · Compras · linha "Ações por linha (dropdown)" — Estado no vivo falso; Ação mantém `_pendente_` que o código já responde

- **Célula "Estado no vivo":** *"vivo usa AcoesDropdown em component separado, **possivelmente subset**"*.
- **Código em origin/main:** `resources/js/Pages/Compras/components/AcoesDropdown.tsx` tem as **9** ações do mockup — `label:` em l.100 Ver · l.109 Impressão · l.115 Editar · l.127 Excluir · l.135 Rótulos · l.142 Ver pagamentos · l.151 Reembolso de compra · l.158 Atualizar status · l.170 Elementos pendentes de notificação. Criado em **c58d371912 2026-05-21** (#1315 *"Drawer 5 tabs + Ações dropdown + Paridade Blade"*) — **40 dias antes** de a prosa nascer (`compras-gap.md` criado em 6fe53ef6ad 2026-06-30).
- **Por quê é erro do lote (não só da prosa):** a coluna se chama "Estado no vivo" e a seção é datada 2026-09-06; restatear como estado de hoje um `_pendente_` que o código contradiz é a classe §5 2026-07-17 (restatear fato que outro sistema sabe melhor) + 2026-09-01 (afirmação em doc canon vira instrução). A Ação **"Decidir. `_pendente_` — §Veredito adotar #5: ler `AcoesDropdown.tsx` antes… para saber se o gap 5 é real ou já coberto"** é fiel à prosa, mas o gap 5 **está coberto** e a tabela o deixa aberto como decisão.

### R2 · Compras · linha "Drawer / Sheet — maior gap" — "vivo tem 2 tabs" é falso; o "maior gap" já está fechado em main

- **Célula "Estado no vivo":** *"vivo tem **2 tabs** (Waves 6+ abertas)"*.
- **Código em origin/main:** `resources/js/Pages/Compras/components/Drawer.tsx` l.22-26 e l.169-173 declaram **5 tabs** — `resumo` · `itens` · `documentos` · `pagamentos` · `historico` (com contadores `itemCount`, `payments.length`, `timeline.length`). Mesmo commit c58d371912 (2026-05-21). O comentário de cabeçalho do `Index.tsx` l.3 (*"Drawer 5 tabs … ficam pra Waves 6+"*) é o fóssil que a prosa leu em vez de abrir o componente.
- **Por quê é erro do lote:** a Ação **"Decidir. `_pendente_` — §Veredito adotar #6 só depois de ler `Drawer.tsx`… (Parte 6 Esforço/risco, G)"** planeja esforço **G** e sessão limpa por tab para construir o que existe. Item 3 do prompt: *"afirma algo sobre o código que o código contradiz"* — a célula afirma 2 tabs; são 5.

### R3 · Produto · linha "Filtros / Busca" — Ação inventa gate "ADR" para (e) marca/OEM

- **Ação:** *"(e) marca/OEM depende de schema: **fora até ADR/backend**"*.
- **Prosa (origin/main `produtos-gap.md`):** l.39 *"(e) busca por mais campos (marca/OEM — depende de schema ter esses campos: pendente)"*; l.41 *"Busca marca/OEM = **P se campos existirem** (_pendente_ confirmar schema)"*; §Veredito l.90 *"Pendências a confirmar: schema expõe marca/OEM…?"*. (e) **não** está na lista "NÃO adotar sem ADR/SPEC" (l.84-88) — essa lista é para valor/estoque.
- **Código:** `database/schema/mysql-schema.sql` l.2657 — `products.brand_id` existe (marca é campo do core). A pendência da prosa era resolvível por leitura do schema; a Ação a fecha para o lado errado ("fora") e acrescenta "ADR" que nenhuma linha da prosa exige. Critério: *"inventa"*.

## Observações (não contadas como erro)

- **Compras · Header:** a Ação diz *"Conferir antes se `cowork-compras-bundle.css` tem `os-page-h*`"* — medido: **0** ocorrências em `resources/css/cowork-compras-bundle.css` (origin/main). A Ação está hedgeada ("conferir"), então não é erro, mas a resposta já é conhecida: não tem.
- **Compras · KPIs/Tabela:** `interface Row` em `Index.tsx` (origin/main) **não** tem `items_count`/`has_xml` — o `_pendente_` dessas duas linhas segue aberto de verdade (CONFIRMADO).
- **Compras · Filtros/Tabs:** tab "Cancelados" segue ausente (só o `Stage 'cancelada'` no type, l.14) — CONFIRMADO.
- **KB:** botão *"N versões"* segue `disabled title="Em breve (O11)"` (`kb/Index.tsx` l.482) e o backend tem `history_count` (`KbController.php:229`) e rota `kb.versions.index` (`routes.php:92`) — a Ação "histórico funcional — §Veredito #3" está correta.
- **KB · Header:** a Ação atribui "Tier 0/ADR 0061" ao bloco Trilhas/Troubleshooter/Composer; na prosa o ADR 0061 sustenta só o Composer (Trilhas/Troubleshooter são "fora de escopo"). O cabeçalho do §NÃO adotar é "(Tier 0 / fora de escopo)", então a citação conjunta é aceitável — registrado como imprecisão, não erro.
- **Cliente:** "banner, l.19" confere em HEAD (l.19 = *"registro datado de 2026-06-30 medido por rota inválida"*); `PARIDADE-area-cliente-diagnostico-e-ondas.md` existe em main e registra em l.199 *"Não adotar o card 'Faturamento +12% vs ontem'"* — CONFIRMADO.
- **RecurringBilling:** `failed_count`, `NewSubscriptionDrawer` (l.868), `EditSubscriptionDrawer` (l.880), POST `/favorite` (l.452/801), `DetailDrawer`+`JanaPanel`, `SubscriptionIndexPresenter.php`, pastas `Planos/Faturas/Configuracoes` — todos existem em origin/main. As Ações "Nada — `— · —`" truncam a célula com "…" explícito sem perder o veredito nem o "⚠️ toca valor"; a barra vertical da prosa virou `／` (fullwidth) para não quebrar a tabela — transformação de render, não de sentido.
- **Produto:** `ProdutoRow` tem `cost`/`margin`/`stockQty` (`Index.tsx` l.54-56) e o arquivo segue com 456 linhas, sem filtro por tipo/sort/toggle/drawer — as demais 5 linhas conferem.
- **Baseline fora do escopo:** `governance/sdd-scorecard-baseline.json` — a `nota_absorcao_2026_09_06` diz *"Os outros **9** modulos tocados (…)"* e lista **8** nomes. Inconsistência interna de contagem numa nota de governança; não faz parte de `memory/requisitos`, não contado.
- **Padrão sistêmico:** as 2 refutações de Compras têm a mesma raiz — a prosa de 2026-06-30 declarou `_pendente_` sobre componentes que já existiam; a tabela "derivada" herdou e carimbou a data de hoje. O conserto não é só na célula: ou a tabela registra o estado medido em main ("gap 5/6 coberto desde #1315") ou não se chama "Estado no vivo".

## Scan PII (linhas adicionadas em `memory/requisitos`, 927 linhas)

| Padrão | Forma (resumo) | Hits no diff | Controle positivo (fixture) |
|---|---|---|---|
| CPF pontuado | `ddd.ddd.ddd-dd` | 0 | 1 |
| CPF cru | 11 dígitos isolados | 0 | 1 |
| CNPJ | `dd.ddd.ddd/dddd-dd` | 0 | 1 |
| Telefone BR | `(dd) 9dddd-dddd` / `dddd-dddd` | 0 | 1 |
| E-mail | `x@y.tld` | 0 | 1 |
| Valor em reais | `R$` seguido de dígito | 0 | 1 |

Os únicos nomes que aparecem nas linhas novas ("WR2 Sistemas", "Padaria, Auto Posto…", persona "Eliana [E] / Larissa") são mock do protótipo/persona interna já presentes na prosa de origin/main — não são PII nova. `R$ a recuperar` (RB · KPIs) não carrega dígito.

## Comandos (grupos 4 e 5) — todos `rc=0`

`requisitos-status.mjs --check`: Crm 0 · Estoque 0 · Fiscal 0 · Ponto 0 · RecurringBilling 0 · Repair 0 · `plans-index.mjs --check` 0 · `design-code-map-check.mjs --check --strict` 0 (*"nenhum map.json com âncora quebrada ou sha stale"*; lista os 6 gap.md sem map — Crm, OficinaAuto, Sells ×2, _DesignSystem ×2 — coerente com o recuo declarado do lote).

## Veredito

```json
{"itens_verificados": 67, "erros_confirmados": 3, "error_rate_pct": 4.48, "pii_hits": 0, "veredito": "reprovado"}
```
