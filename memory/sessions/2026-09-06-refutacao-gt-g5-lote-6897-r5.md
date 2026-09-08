---
date: "2026-09-06"
topic: "Refutação GT-G5 r5 do lote #6897 (7 gap.md de tela + 7 map.json + índices regenerados) — 101 itens, 9 erros confirmados, reprovado"
authors: ["C"]
prs: [6897]
outcomes:
  - "Âncoras (28), gates (8), regeneração dos 7 mapas e scan PII (0 hits, 7/7 controles) todos CONFIRMADOS"
  - "9 linhas de Ação REFUTADAS em Compras (3), Sells/vendas-create (4) e Sells/vendas-index (2): truncagem que perde item enumerado da prosa ou a restrição Tier 0 / _pendente_ que ela anexa"
  - "error_rate 8.91% ≥ 2% → lote REPROVADO; os 5 arquivos reescritos à mão (Cliente r3, KB r4, Produto r5, RecurringBilling r2, vendas-index r2 parcial) passam — o defeito é sistemático nos 2 que ficaram na derivação r1"
---

# Refutação GT-G5 — lote PR #6897 · rodada r5

> Refutador em sessão fresca (worktree `compras-migration-complete-15fd56`, branch `claude/q6-gap-md-tabela-e-11-mapas`, `origin/main` = `26ac293f46`). Modelo: Fable 5.1. Nenhum arquivo `memory/sessions/*refutacao*` foi aberto (os 4 do branch existem no diff global, mas não foram lidos). Tudo medido contra `origin/main` por `git ls-tree` / `git show`, não contra o texto do PR.

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador nem das rodadas r1–r4)
- [x] Modelo de tier SUPERIOR ao gerador (refutador Fable 5.1; gerador [C] em opus/sonnet — ver ledger do PR)
- [x] Amostra: 100% anchors (tipo `anchors`); não há prosa destilada neste lote — a tabela é derivada e foi lida 100% (51/51 linhas)
- [x] Cada item verificado contra o código real em origin/main (`git ls-tree origin/main -- <path>`, `git show origin/main:<path>`), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê) — seção "Refutados" abaixo
- [x] Scan PII no diff (linhas `+` de `memory/requisitos`) — 7 padrões com controle positivo cada; 0 hits
- [x] `error_rate_pct` calculado: 8.91 (≥ 2 → reprovado)
- [ ] Entry no ledger `governance/sdd-verification-ledger.json` — **não escrita por este refutador** (mandato: só este arquivo). Fica pro gerador anexar no mesmo PR com `veredito: reprovado`

## Preâmbulo — o que o lote é, medido

- `git diff --name-status origin/main...HEAD -- memory/requisitos`: 7 `M` gap.md + 7 `A` map.json + 6 `_STATUS-GENERATED.md` (2 `A`, 4 `M`). `_processo/PLANS-INDEX-GENERATED.md` **não aparece no diff** — regenerado idêntico ao main (`git diff --stat` vazio); `plans-index --check` rc=0 confirma.
- Revertidos blob-idênticos ao main (`git rev-parse HEAD:<f>` == `origin/main:<f>`): `_DesignSystem/pageheader-canon-v3-gap.md` (`28a788ec…`), `_DesignSystem/sidebar-v3-unificado-gap.md` (`3b7266c6…`), `OficinaAuto/kanban-producao-gap.md` (`0b6dbdda…`), `Crm/clientes-gap.md` (`04961622…`). Nenhum dos 4 tem `.map.json` em HEAD (`git ls-files '*.map.json'` = 19, os 7 novos + 12 já em main). ✓
- Fora de `memory/requisitos`, o branch só toca `governance/sdd-scorecard-baseline.json` e os 4 session logs de refutação. `prototipo-ui/**` intocado → o `prototipo_sha` foi computado sobre os mesmos bytes que o main.

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados |
|---|---|---|---|
| G1 — `partes[].prototipo.arquivo` / `vivo.arquivo` existem em main + não revogados pelo charter (2 paths distintos × 7 mapas) | 14 | 14 | 0 |
| G2 — frontmatter `prototipo` + `tela_viva` existem em main e são lidos por `fmVal` (7 × 2) | 14 | 14 | 0 |
| G3a — coluna Ação reflete o veredito da prosa (51 linhas) | 51 | 42 | **9** |
| G3b — `acao` do map.json == texto da tabela (regen byte-a-byte, 7 mapas) | 7 | 7 | 0 |
| G4 — `requisitos-status.mjs <Mod> --check` ×6 + `plans-index.mjs --check` | 7 | 7 | 0 |
| G5 — `design-code-map-check.mjs --check --strict` | 1 | 1 | 0 |
| G6 — scan PII (7 padrões, controle positivo cada) | 7 | 7 | 0 |
| **Total** | **101** | **92** | **9** |

### G1 — âncoras dos mapas (14/14 CONFIRMADO)

Todos os 14 paths distintos devolvem blob em `git ls-tree origin/main` (7 `prototipo-ui/cowork/*-page.jsx` + 7 `resources/js/Pages/**/{Index,Create}.tsx`; `Pages/kb/` é minúsculo em main, casa com o map). Charter dono de cada tela (em main) + `node prototipo-ui/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork`:

| Tela | Charter | Veredito |
|---|---|---|
| Cliente/Index | `related_prototype: prototipo-ui/cowork/clientes-page.jsx` (l.5). A única "REVOGADA" do charter (l.78) é a PTDP Onda 1 (BrunaGreeting/SavedViews), não o protótipo | ✓ |
| Compras/Index | `related_prototype: prototipo-ui/cowork/compras-page.jsx` (l.14; charter `status: draft`) | ✓ |
| kb/Index | `related_prototype: prototipo-ui/cowork/kb-page.jsx` (l.23) | ✓ |
| Produto/Index | `related_prototype: n/a (herda PT-01)` + `bundle_source: produtos-page.jsx` — `ancora.mjs` resolve a âncora pelo bundle (`[-page.jsx (bundle · bundle_source)] produtos-page.jsx`); coexistência declarada legítima (§5 2026-08-28 (c)) | ✓ |
| RecurringBilling/Index | `related_prototype: prototipo-ui/cowork/cobranca-recorrente-page.jsx` (l.15) | ✓ |
| Sells/Create | charter **não declara** `related_prototype` nem `bundle_source` (`ancora.mjs`: "⚠️ charter sem related_prototype nem -page.jsx"). Não é revogação — é ausência. O gap.md em main já nomeia `vendas-create-page.jsx` (l.5/l.9, 439 linhas = as 439 do arquivo em `prototipo-ui/cowork/`, commit `9da73296d3`). CONFIRMADO, com observação: a tela segue sem âncora no charter | ✓ obs |
| Sells/Index | `related_prototype: prototipo-ui/cowork/vendas-page.jsx (formalizado 2026-07-09 …)` (l.7) — `ancora.mjs` resolve pro path | ✓ |

**Observação (não é item):** `Sells/Index` passa a ter **dois** map.json apontando pra mesma tela e mesmo protótipo — `Sells/vendas.map.json` (já em main, `gap_fonte: vendas-gap.md`, 6 partes: menu-visoes, drawer-fiscal, foco-comissao, saved-tree, painel-ia, devolucoes-relatorios) e o novo `Sells/vendas-index.map.json` (8 partes por região). São dois gap.md distintos em main pra mesma tela; `escolherGap` desambigua por slug exato (`Sells/vendas` ≠ `Sells/vendas-index`) e o `design-code-map-check` não acusa. Não conta como erro do lote (o gap.md preexistia), mas é duplicação de dono do tema que [W] pode querer reconciliar.

### G2 — frontmatter (14/14 CONFIRMADO)

`prototipo-ui/gerar-contrato.mjs:36` `fmVal = (fm, key) => fm.match(new RegExp('^' + key + ':\\s*(.+)$', 'im'))` — lê exatamente as chaves `tela_viva` (l.123) e `prototipo` (l.124); `gerar-map.mjs:152-153` reusa. Os 7 frontmatters em HEAD trazem as duas chaves com os mesmos 14 paths do G1 (todos em main). Em `Produto/produtos-gap.md` o frontmatter **nasceu** neste PR (main não tinha `---`), com `id` mantido — `requisitos-status Produto` não está na lista do prompt, mas o map-check `--strict` passou.

### G3b — mapa derivado da tabela (7/7 CONFIRMADO)

`node prototipo-ui/gerar-map.mjs memory/requisitos/<Mod>/<tela>-gap.md` regerado pra cada um dos 7 e comparado ao commitado: **idêntico byte-a-byte fora `gerado_em`** (mesmo `prototipo_sha`, mesma contagem de partes, mesmo `acao`/`_acionavel` por parte). Logo o `acao` do map é literalmente o texto da coluna Ação — o que também significa que **todo defeito da tabela está no map**.

### G4 / G5 — gates (8/8 CONFIRMADO)

`requisitos-status.mjs --check` (gerado × commitado, docblock l.35) rc=0 para Crm, Estoque, Fiscal, Ponto, RecurringBilling, Repair. `plans-index.mjs --check` rc=0. `design-code-map-check.mjs --check --strict` rc=0 — "19 map.json … [OK] nenhum map.json com âncora quebrada ou sha stale"; lista os 4 gap.md sem map exatamente como o prompt descreve (Crm + 2 `_DesignSystem` + OficinaAuto).

### G6 — PII (0 hits; 7/7 controles positivos)

Corpus: 1.239 linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`. Cada padrão foi rodado também contra uma string sintética que casa (controle positivo) — todas devolveram 1. Os controles de CPF/CNPJ/telefone/e-mail são números/endereços fictícios; o de valor em reais é o símbolo seguido de dígitos (não reproduzido aqui — o hook `block-brl-values-in-memory` barra a forma literal, corretamente).

| Padrão | Regex | Hits no diff | Controle |
|---|---|---|---|
| CPF pontuado | `[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}` | 0 | 1 ✓ |
| CPF cru 11 díg. | `(^|[^0-9])[0-9]{11}([^0-9]|$)` | 0 | 1 ✓ |
| CNPJ pontuado | `[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}` | 0 | 1 ✓ |
| CNPJ cru 14 díg. | `(^|[^0-9])[0-9]{14}([^0-9]|$)` | 0 | 1 ✓ |
| Telefone BR | `\(?0?[1-9]{2}\)?[ -]?9?[0-9]{4}-[0-9]{4}` | 0 | 1 ✓ |
| E-mail | `[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}` | 0 | 1 ✓ |
| Valor em reais | `R\$ ?[0-9]` | 0 | 1 ✓ |

As 4 ocorrências do símbolo de reais nas linhas adicionadas são `R$ [redacted Tier 0]` (Compras KPIs, tabela + map) e "(R$ a recuperar)" sem dígito (RecurringBilling). **Observações fora do escopo do lote (pré-existentes em main, não adicionadas):** `Compras/compras-gap.md:30` em main carrega uma meta de mockup em reais **literal** (o lote redigiu na tabela, mas a linha-fonte segue em main); "WR2 Sistemas" e "Eliana [E] / Larissa" aparecem na coluna Estado de RecurringBilling como cópia da prosa de main (razão social de mockup + nomes já canônicos no CLAUDE.md — não são PII de CRM; registrado, não contado).

## Refutados — lista completa (9)

Critério aplicado, literal do prompt: REFUTADO se a Ação *inverte, inventa, omite, trunca item relevante ou reabre um veredito*; "Decidir." só *nos termos da prosa: esforço, restrições Tier 0, itens do §Veredito*. Contei como "item relevante" (i) item enumerado como gap real na prosa, (ii) restrição Tier 0 / regra mestre que a prosa anexa àquela parte, (iii) `_pendente_` que condiciona a **existência** do gap. Omissão só de esforço em parte "não toca valor" **não** foi contada (obs. abaixo). Todas as linhas citadas são de `origin/main`.

| # | Arquivo · linha da tabela | O que a Ação diz | O que a prosa em main diz | Porquê |
|---|---|---|---|---|
| 1 | `Compras/compras-gap.md` · **KPIs (4 cards)** | "Decidir. mesmos 4 KPIs … 3 com compra… Construir ou rejeitar por escrito." | l.33: "⚠️ **toca valor** … qualquer novo número exibido (% MoM, meta) exige Regra Mestre ANTES de exibir. **Marcar '⚠️ toca valor'**"; l.32 `_pendente_` payload; Veredito #4 (l.69) "(M, ⚠️ valor — só label visual)" | A prosa manda **marcar** ⚠️ toca valor; a Ação (e o `acao` do map, `_acionavel:true`) não marca. Restrição Tier 0 omitida |
| 2 | `Compras/compras-gap.md` · **Ações por linha** | "Decidir. mockup tem dropdown 9 opções … paridade declarada… Construir ou rejeitar por escrito." | l.48 "**_pendente_** confirmar quantas das 9 opções o vivo já implementa"; l.51 esforço "_pendente_ … ⚠️ Reembolso/Ver pagamentos **tocam valor** — Tier 0"; l.74 "_pendente_ ler `AcoesDropdown.tsx` — define se gaps 5 e 6 são **reais ou já cobertos**" | Afirma gap que a prosa diz não ter confirmado (o próprio lote trata `vendas-index` igual com "(_pendente_ confirmar, como a prosa diz)") e omite Tier 0 |
| 3 | `Compras/compras-gap.md` · **Drawer / Sheet — maior gap** | "Decidir. mockup tem drawer 5 tabs … tab Itens (tabela… Construir ou rejeitar por escrito." | l.54 "_pendente_ confirmar se o `Drawer.tsx` real já tem as 5 tabs ou só 2"; l.57 "**G** · ⚠️ **toca valor FORTE** … **Tier 0**: qualquer escrita aqui passa por FSM `ExecuteStageActionService` + Regra Mestre"; Veredito #6 idem | Mesmo defeito do #2, na parte que a prosa chama de Tier 0 FORTE |
| 4 | `Sells/vendas-create-gap.md` · **Grade de itens** | "Decidir. (1) m² por linha … (área × preço ×… Construir ou rejeitar por escrito." | l.64: "(1) … **G** · risco ALTO (Tier 0 valor). **(2)** Metadados fiscais NCM/CFOP — M · baixo. **(3)** Agrupamento MO/Peças + toggle aprovado/pendente (Oficina) — G · médio" | Truncagem perde os itens (2) e (3) inteiros e o "G · risco ALTO" |
| 5 | `Sells/vendas-create-gap.md` · **Pagamento** | "… (2) Parcelamento explícito (Nx) — _pendente_ … M… Construir ou rejeitar por escrito." | l.82: "… M · risco médio. **(3)** Aviso 'gera cobrança/boleto ao salvar' — depende do módulo Cobrança/RecurringBilling estar ligado; M · médio"; pendência l.147 | Item (3) perdido |
| 6 | `Sells/vendas-create-gap.md` · **Frete / entrega** | "… (2) Radio retirada/entrega … —… Construir ou rejeitar por escrito." | l.100: "… — P · baixo. **(3)** Detecção de município → MDF-e — ver Parte 7. **⚠️ custo de frete toca valor** — entra no total; mexer no fluxo exige dupla confirmação" | Item (3) e a restrição Tier 0 perdidos |
| 7 | `Sells/vendas-create-gap.md` · **Ações / rodapé** | "… (2) Label de ação contextual por vertical ('Salvar e gerar OS')… Construir ou rejeitar por escrito." | l.109: "… depende do fluxo OS. P. **(3)** Atalho F2 vs Ctrl+Enter — divergência de convenção, P" | Item (3) perdido — o mais fraco dos 9 (item pequeno); sem ele o placar é 8/101 = 7.9%, veredito idêntico |
| 8 | `Sells/vendas-index-gap.md` · **KPIs / cards de resumo** | "Decidir. · (a) PIX hoje com barra de progresso … Mockup tem a barrinha visual de… Construir ou rejeitar por escrito." | l.32 "**(b)** Card 'A receber' com CTA '→ ver estouradas' + `vd-kpi-alert`"; l.33 "(c) Comissões do mês"; **Veredito Adotar #2 (l.86) = (b)**, #3 = (a); Não adotar (l.99) = (c) | Truncagem perde (b), que é item **#2 da lista Adotar do §Veredito**, e (c), que o §Veredito fecha como "só com decisão de produto" |
| 9 | `Sells/vendas-index-gap.md` · **Paginação / footer / totalizadores** | "Decidir. barra de totalizadores … (refino [W] 2026-06-12 'preciso dos… Construir ou rejeitar por escrito." | l.68 "**P / baixo** … **cuidado Tier 0 cálculo de valor — apenas exibe número já computado pelo backend, não recalcula**"; Veredito #1 (l.85) "⚠️ Tier 0 cálculo: só **exibir** … não recalcular no front" | Restrição Tier 0 explícita no #1 do §Veredito perdida; o `acao` do map entra no contrato sem ela |

**Padrão dos 9:** todos estão nos 2 arquivos que **ficaram na derivação mecânica original** (Compras e vendas-create — as únicas tabelas sem "rN" no cabeçalho) mais 2 linhas de vendas-index cuja r2 só corrigiu as linhas `_pendente_`. O gerador copia a linha "Gap real" e corta em ~200 caracteres com "…"; onde a prosa põe esforço/Tier 0 **fora** dessa linha (Compras, bullet "Esforço/risco" separado) ou **depois** do corte (vendas-create, itens (2)/(3)), a Ação nasce sem. É o mesmo defeito que reprovou a r1 dos outros 5 arquivos, num eixo que as rodadas seguintes não cobriram — e a lição vale além do lote: o corte em N caracteres não é derivação, é amostragem.

## Confirmados com observação (não contados)

- **Compras · Header / Filtros / Tabela**: Ação omite esforço (P/P/M) — partes "não toca valor"; o corte no Header perde a ressalva "preservando crumbs/count/permissão/gate Wave 6" do Veredito #2.
- **KB · Lista de artigos**: cita "§6 Esforço" — a fonte é o 6º bullet da **Parte 4** (l.58 "sort por frescor = P"); o §Veredito não lista o sort no "Adotar", mas também não o põe em "NÃO adotar", e a Parte 4 o registra — sustentado. "Pinning" (l.55 "poderia ser útil") omitido.
- **KB · Editor**: "related = M" e "IA-summary = M" (l.67) omitidos — o §Veredito também os omite; fiel ao veredito.
- **Produto · Filtros**: (e) marca/OEM — prosa diz "_pendente_ confirmar schema" (l.41), Ação diz "fora até ADR/backend" (levemente mais restritivo). **Produto · Tabela**: (e) thumb/cor por categoria sem veredito em lugar nenhum; Ação omite. Prosa é internamente inconsistente (Parte 4 esforço "G" vs Veredito #3 "M") — Ação seguiu o Veredito, correto pela precedência.
- **Produto · KPIs / Drawer**: "Nada" representa "NÃO adotar **sem ADR/SPEC**" — `_acionavel:false` tira a região do contrato; coerente com o §Veredito, mas quem reabrir precisa saber que o "não" é condicional.
- **RecurringBilling · Filtros**: a linha da tabela-fonte diz `— · —`, mas o ADOTAR-PARCIAL #1 (l.23-24) + Conclusão (l.53 "_pendentes_ de decisão do Wagner") sustentam o "Decidir." **· Ações/drawer**: a Ação copia "lateral overlay + footer fixo" como ideia, enquanto a prosa (l.39) diz que overlay-vs-3-col "é decisão já tomada no charter" e só a aba IA é cosmética — fiel à célula "única ideia visual" da fonte, mas a fonte se contradiz.
- **Sells/vendas-create · Fiscal**: perde "Adoção exige decisão de produto (ADR)" (l.91) — restrição de processo, não Tier 0; "G · integra NfeBrasil/FSM" presente. **· Totais**: perde "Apenas descrever, não mexer em cálculo" — a regra mestre está presente.
- **Cliente (7 linhas)**: "banner, l.19" é linha de **HEAD** (= l.16 em main, o frontmatter cresceu 3 linhas) — correta hoje, apodrece no próximo edit. A alegação "(que já registra: não adotar o card Faturamento)" **verificada**: `PARIDADE-area-cliente-diagnostico-e-ondas.md:199` em main.

## Regra de aceite

`error_rate = 9 / 101 = 8.91%` ≥ 2% → **lote REPROVADO inteiro** (§2.6: volta ao gerador; re-verificação do lote todo na próxima rodada, não só dos 9). PII 0.

```json
{"itens_verificados": 101, "erros_confirmados": 9, "error_rate_pct": 8.91, "pii_hits": 0, "veredito": "reprovado"}
```
