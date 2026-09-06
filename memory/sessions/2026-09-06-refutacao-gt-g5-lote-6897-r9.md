---
date: "2026-09-06"
topic: "Refutação GT-G5 r9 — lote PR #6897 (5 gap.md com tabela de partes + 5 map.json + índices regenerados)"
authors: ["C"]
prs: [6897]
---

# Refutação GT-G5 · rodada r9 · lote PR #6897

> Protocolo: `memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md` §2–§4. Sessão fresca (worktree `compras-migration-complete-15fd56`, branch `claude/q6-gap-md-tabela-e-11-mapas`, HEAD `bd57cd8334`, `origin/main` `26ac293f46`). Nenhum arquivo `*refutacao*` de rodada anterior foi aberto. Refutador: Fable 5.1 (tier acima de opus). Tipo `anchors`, amostra 100%.

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (fable > opus)
- [x] Amostra: 100% anchors (tipo `anchors`; sem prosa destilada no lote)
- [x] Cada item verificado contra `origin/main` (`git ls-tree`, `git show`, `git grep origin/main`), não contra o texto do PR
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê)
- [x] Scan PII no diff (linhas `+` de `memory/requisitos`) com controle positivo por padrão — 0 hits
- [x] `error_rate_pct` calculado (1,35%) e < 2
- [ ] Entry no ledger `governance/sdd-verification-ledger.json` — NÃO é deste refutador (só lê e escreve este arquivo); fica pro gerador/parent no mesmo PR

## Lote medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` = 16 arquivos: 5 `*-gap.md` (M), 5 `*.map.json` (A), `Crm/_STATUS-GENERATED.md` (A), `Estoque|Fiscal|Ponto|Repair/_STATUS-GENERATED.md` (M), `RecurringBilling/_STATUS-GENERATED.md` (A). Os gap.md de `_DesignSystem` (2), `OficinaAuto` (1) e `Sells` (2) **não aparecem no three-dot diff** → blob-idênticos ao main; nenhum map.json novo pra eles (`git ls-tree HEAD` só mostra `Sells/vendas.map.json`, que **já existe em origin/main**). `Crm/clientes-gap.md` intocado e sem map.json — confirmado pelo próprio `design-code-map-check` (lista os 6 gap.md sem map).

## Resultado por grupo

| Grupo | Itens | Confirmados | Refutados |
|---|---|---|---|
| 1. Âncoras dos `.map.json` (prototipo.arquivo + vivo.arquivo por mapa, + não-revogação no charter) | 10 | 10 | 0 |
| 2. Frontmatter `tela_viva` + `prototipo` (existe em main + lido por `fmVal`/`pagesPath`/`resolverArquivosPrototipo`) | 10 | 10 | 0 |
| 3a. Linhas das tabelas derivadas (Ação × veredito da prosa × código vivo) | 34 | 33 | **1** |
| 3b. `acao` do map.json == texto da tabela (regeneração `gerar-map.mjs` → 0 chaves divergentes, sha idêntico) | 5 | 5 | 0 |
| 4. `requisitos-status.mjs <Mod> --check` ×6 + `plans-index.mjs --check` | 7 | 7 | 0 |
| 5. `design-code-map-check.mjs --check --strict` | 1 | 1 | 0 |
| 6. Scan PII (7 padrões com controle positivo) | 7 | 7 | 0 |
| **Total** | **74** | **73** | **1** |

### Grupo 1 — âncoras (todas CONFIRMADAS)

`git ls-tree origin/main` devolve blob para os 10 paths: `prototipo-ui/cowork/{clientes,compras,kb,produtos,cobranca-recorrente}-page.jsx` e `resources/js/Pages/{Cliente,Compras,kb,Produto,RecurringBilling}/Index.tsx`. `node prototipo-ui/ancora.mjs <Tela> --staging prototipo-ui/cowork` resolve `âncora ✓` nas 5 telas: 4 por `related_prototype` do charter; **Produto/Index** declara `related_prototype: n/a (herda PT-01…)` mas resolve `âncora ✓ [-page.jsx (bundle · bundle_source)] produtos-page.jsx` — coexistência prevista (§5 2026-08-28 c), **não é revogação**. A única "REVOGADA" no charter de Cliente (l.78) é a PTDP Onda 1 (BrunaGreeting/SavedViews), não o protótipo. `prototipo_sha` dos 5 mapas bate com o recomputado hoje (`8f284ad79fb3` · `1096106a9f83` · `3f2c78b83b65` · `07a4cea7662a` · `40e51d3a1e6a`).

### Grupo 2 — frontmatter (todas CONFIRMADAS)

`fmVal` (`prototipo-ui/gerar-contrato.mjs:36`) casa `^tela_viva:` / `^prototipo:` por regex; `pagesPath` (`:40-43`) extrai o path `resources/js/Pages/...`; `gerar-map.mjs:78-80` extrai o `.tsx` do `tela_viva` e `:62-76` os `.jsx` do `prototipo`. Prova de que são lidos: a regeneração dos 5 mapas a partir dos gap.md reproduziu `tela`, `gap_fonte`, `prototipo.arquivo` e `vivo.arquivo` **idênticos** aos commitados. `Produto/produtos-gap.md` não tinha frontmatter em main; o `id: requisitos-produto-produtos-gap` novo segue a convenção dos 21 demais gap.md.

### Grupo 3a — as 34 linhas

**Cliente (7/7 CONFIRMADAS).** Todas "Nada" ancoradas no banner de invalidade — em HEAD a frase *"registro datado de 2026-06-30 medido por rota inválida — não é base para decidir hoje"* está na **l.19** (citada certo; em main seria l.16, mas a Ação cita o próprio arquivo). Dono vigente `PARIDADE-area-cliente-diagnostico-e-ondas.md` existe em main e registra na **l.199**: *"Não adotar o card 'Faturamento +12% vs ontem'"* — exatamente o que a Ação afirma.

**Compras (5/6).** Header→#2 (l.70; `<header className="hd"` em `Index.tsx:239`; `os-page-h` **0 ocorrências** em `cowork-compras-bundle.css`, logo o "conferir antes" é honesto) ✓ · KPIs→#4 (l.72/36) ✓ · Filtros→#1 (l.69/42; sem tab Cancelados no `Index.tsx`, só o tipo `Stage='cancelada'` l.14) ✓ · Tabela→#3 (l.71/78; `items_count`/`has_xml` 0 ocorrências no Index/Controller → `_pendente_` correto) ✓ · Ações por linha: `AcoesDropdown.tsx` tem **9 labels em l.100–170** (Ver·Impressão·Editar·Excluir·Rótulos·Ver pagamentos·Reembolso de compra·Atualizar status·Elementos pendentes de notificação) ✓ · **Drawer → REFUTADA** (abaixo).

**KB (6/6).** Header→#4 (l.91) + NÃO adotar (l.93) ✓ · Navegação→#2 (l.89) ✓ · Busca→#3 (l.90/52) ✓ · Lista: "sort por frescor = P, dado já existe" (l.61) e de fato não consta do #1–#4 ✓; `indexed_at`/`updated_at` no tipo do `Index.tsx:54-55` ✓ · Editor→#1 (l.88) + histórico #3 (`history_count` em `KbController.php:229` e botão `disabled title="Em breve (O11)"` em `Index.tsx:482`) ✓ · Drawer→#4 (l.91) ✓.

**Produto (6/6).** Header→#1 (l.87/28) ✓ · KPIs "Nada" (l.38/92) ✓ · Filtros→#2/#3 (l.88-89) + Stockbar (l.48) + schema: `brand_id` em `mysql-schema.sql:7577`, dentro de `CREATE TABLE products` (l.7569–7664) ✓; `OEM` **0 ocorrências** no schema ✓ · Tabela→#3 (l.89) + NÃO adotar (l.93-95); `ProdutoRow` tem `cost/margin/stockQty` (`Index.tsx:54-56`) ✓ · Ações por linha (l.68 "precisa charter/Wagner") ✓ · Drawer "Nada" (l.78/94) ✓.

**RecurringBilling (9/9).** As 7 linhas `— · —` da tabela da prosa (l.36-46) viraram "Nada — `— · —`" ✓; as 2 sub-linhas `→` (micro-gap KPI l.38 · única ideia visual l.42) foram fundidas na parte-mãe como "Decidir" com o esforço literal da prosa ✓; Filtros cita ADOTAR-PARCIAL #1 (l.27) ✓ e a prosa l.56 confirma que é decisão pendente do Wagner. Código: `failed_count`/"Retentado falhos" (2 ocorrências cada em `Pages/RecurringBilling`), `NewSubscriptionDrawer` (3), `EditSubscriptionDrawer` (2), `POST /recurring-billing/{id}/favorite` (`SubscriptionFavoriteController.php:23`), `GET /contacts/search` (`Routes/web.php:39`), rotas `faturas`/`configuracoes`/`planos` (`Routes/web.php:86-96`) — tudo em main.

### Grupo 3b — map ↔ tabela

`node prototipo-ui/gerar-map.mjs <gap.md>` rodado nos 5 arquivos e comparado chave a chave com o commitado: **0 chaves divergentes** em todos (`acao`, `_acionavel`, `partes[]`, `prototipo_sha`, `gerado_em`). Logo `acao` do map == célula Ação da tabela (sem `**`), por construção (`gerar-map.mjs:173`).

### Grupos 4 e 5

`requisitos-status.mjs --check`: Crm 0 · Estoque 0 · Fiscal 0 · Ponto 0 · RecurringBilling 0 · Repair 0. `plans-index.mjs --check` 0. `design-code-map-check.mjs --check --strict` 0 — *"[OK] nenhum map.json com âncora quebrada ou sha stale"*.

## REFUTADOS (lista completa)

### R-1 · Compras · linha "Drawer / Sheet — maior gap" (`compras-gap.md` tabela r6; `compras.map.json` parte `drawer-sheet-maior-gap`)

A Ação afirma ter lido o código e conclui: *"Resta do §Veredito #6: **trilho FSM** + footer de ações por estágio + atalhos fiscais"*. **O trilho FSM já existe no vivo.** `origin/main:resources/js/Pages/Compras/components/Drawer.tsx`:

- l.12–19: `const STAGES` com **6 estágios** (rascunho · pedido · transito · recebido · conferido · pago), cada um com label e ícone;
- l.197–209: `<div className="fsm-track">` mapeando `STAGES` em `fsm-step` com classes `done`/`now` por `stageIdx(compra.status)` — é literalmente o *"trilho FSM visual (`fsm-track` com 6 estágios done/now)"* que a prosa §6 (l.57) descreve como gap.

O que de fato NÃO existe (conferido): footer de ações por estágio — o rodapé (l.234–241) só tem "Total da compra" + botão "Fechar"; `Registrar pagamento`/`Pagar agora` = 0 ocorrências em `Pages/Compras`; atalhos fiscais adiados (l.501, l.520). Ou seja, a Ação acerta as negativas e as 5 abas/`initialTab`, mas **inverte o trilho FSM** (código contradiz) — item 3(b). O `map.json` herda o mesmo texto. Correção: remover "trilho FSM" do resíduo do #6 (ou registrar que já está coberto, como fez com as abas).

## Observações (não contam como erro)

- `node scripts/governance/doc-id-index.mjs --check` sai **rc=1** ("índice em drift vs corpus"), mas o drift é **pré-existente**: um `--write` de teste (revertido com `git checkout --` em seguida, árvore limpa) mostrou +91/−9 entradas, quase todas de `memory/sessions|handoffs|decisions` de 2026-09-01/02. O lote acrescenta 1 id novo (`requisitos-produto-produtos-gap`) sem regenerar o índice; o modo `--check` não é invocado no CI (`governance-script-tests.yml:1013` explica). Fica registrado; não é defeito do lote.
- Cabeçalho da tabela de Cliente diz "(derivada …, r2)" e no corpo "Cliente (r3)" — inconsistência de rótulo de rodada, sem efeito nas linhas.
- KB/Header: "Trilhas, Troubleshooter e Composer/Novo artigo: NÃO adotar (§Veredito, **Tier 0/ADR 0061**)" — o Tier 0/ADR 0061 da prosa cobre só o Composer; Trilhas/Troubleshooter são "fora de escopo". O veredito (NÃO adotar) está certo; a razão está agregada.
- RecurringBilling: as Ações "Nada" truncam com "…" a descrição do vivo copiada da prosa; o veredito citado (`— · —`) fica íntegro em todas.
- Nomes que aparecem nas linhas `+` (WR2 Sistemas, Larissa, Eliana [E]) já existem na prosa de `origin/main` (ex.: `cobranca-recorrente-gap.md:19,33`) — são persona/mocks do protótipo e time, não PII de cliente nova.

## Scan PII (linhas `+` do diff em `memory/requisitos`, 927 linhas)

| Padrão | Hits no diff | Controle positivo |
|---|---|---|
| CPF pontuado `\d{3}\.\d{3}\.\d{3}-\d{2}` | 0 | 1 |
| CPF cru (11 dígitos isolados) | 0 | 1 |
| CNPJ `\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}` | 0 | 1 |
| Telefone BR `(\d{2}) 9?\d{4}-\d{4}` | 0 | 1 |
| Telefone cru (10–11 dígitos) | 0 | 1 |
| E-mail | 0 | 1 |
| Valor em reais (`R$` seguido de dígito) | 0 | 1 |

Controle = linha sintética com um exemplar de cada padrão, passada pelo mesmo `grep -E`; 7/7 casaram. **pii_hits = 0.**

## Veredito

74 itens · 1 erro confirmado · **1,35%** < 2% · PII 0 → **aprovado** (com a correção R-1 recomendada no mesmo PR, já que "trilho FSM" no map.json vira instrução errada pra Fase 4).

```json
{"itens_verificados": 74, "erros_confirmados": 1, "error_rate_pct": 1.35, "pii_hits": 0, "veredito": "aprovado"}
```
