---
date: "2026-09-06"
topic: "Refutação GT-G5 do lote PR #6897 — 10 gap.md com tabela de partes + 11 map.json + 6 _STATUS-GENERATED (veredito: reprovado)"
authors: ["C"]
prs: [6897]
outcomes:
  - "141 itens verificados · 19 erros confirmados · error_rate 13,48% (≥2%) → lote REPROVADO"
  - "Erro sistemático: a coluna Ação da tabela derivada carimba **Decidir.** em partes cuja prosa diz 'nada a fazer / vivo à frente / decisão já registrada' — concentrado em RecurringBilling (6/9), sidebar (6/17) e pageheader (4/12)"
  - "Âncoras (paths dos 11 map.json + frontmatter dos 10 gap.md), 7 geradores --check, design-code-map-check --strict e scan PII: 0 erros"
---

# Refutação GT-G5 — lote PR #6897 (`claude/q6-gap-md-tabela-e-11-mapas`)

> Protocolo: [`PROTOCOLO-REFUTADOR-BACKFILL.md`](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2/§3/§4.
> Base medida: `origin/main` = `26ac293f46` (merge-base do branch) · HEAD do lote = `bf3ac6719a` · repo **não** raso (`git rev-parse --is-shallow-repository` = false).
> Refutador: Claude (Fable 5.1) em sessão fresca, worktree `compras-migration-complete-15fd56`, sem contexto do gerador.

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador — refutador `fable`; o gerador do lote não vem declarado no corpo do PR (o ledger deve preencher; se for `opus`, fable ≥ opus satisfaz)
- [x] Amostra: 100% anchors (paths, frontmatter, tabelas linha-a-linha, geradores) — não há prosa destilada neste lote, logo sem seleção aleatória
- [x] Cada item verificado contra `origin/main` (`git ls-tree origin/main -- <path>`, `git show origin/main:<path>`, `git log`), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [x] Scan PII nas linhas adicionadas do diff, com controle positivo por padrão — 0 hits
- [x] `error_rate_pct` calculado: **13,48%** (≥ 2 → reprovado)
- [ ] Entry no ledger `governance/sdd-verification-ledger.json` — **não escrita por mim** (mandato: só este arquivo de evidência); o parent adiciona a entry apontando pra este path

## Escopo real do diff (medido, não do PR body)

`git diff --name-only origin/main...HEAD -- memory/requisitos` = **27 arquivos**: 10 `*-gap.md` (M), 11 `*.map.json` (A), 6 `_STATUS-GENERATED.md` (Crm A · RecurringBilling A · Estoque/Fiscal/Ponto/Repair M). Fora de `memory/requisitos`: `governance/sdd-scorecard-baseline.json` (M).

⚠️ **`_processo/PLANS-INDEX-GENERATED.md` NÃO está no diff** — o enunciado do lote o cita como regenerado. Não é erro de arquivo (o `plans-index --check` sai 0), mas a descrição do lote afirma um arquivo que não foi tocado.

## Tabela por grupo

| # | Grupo | Itens verificados | Erros confirmados | Como mediu |
|---|---|---|---|---|
| 1 | Paths dos 11 `.map.json` (`prototipo.arquivo` + `vivo.arquivo` ≠ TODO, 1 por path distinto por mapa) | 17 | 0 | `git ls-tree origin/main -- <path>` (controle negativo: path inexistente → vazio) |
| 2 | Frontmatter dos 10 `-gap.md` (`tela_viva` + `prototipo` existem; parse com `fmVal` de `gerar-contrato.mjs` + js-yaml estrito) | 28 | 0 (1 ressalva pré-existente) | `fmVal` regex + `js-yaml` sobre HEAD e sobre `origin/main` |
| 3 | Tabelas derivadas — coluna **Ação** × prosa da seção correspondente no MESMO arquivo (1 por linha) | 88 | **19** | leitura da prosa em `origin/main` linha a linha |
| 4 | `requisitos-status.mjs <Mod> --check` ×6 + `plans-index.mjs --check` | 7 | 0 | rc=0 nos 7 |
| 5 | `design-code-map-check.mjs --check --strict` | 1 | 0 | rc=0 (valida schema + `prototipo_sha` por contentHash) |
| 6 | Scan PII (linhas `+` do diff, 2.064 linhas) | — | 0 hits | 6 padrões, cada um com controle positivo |
| | **Total** | **141** | **19** | **error_rate = 13,48%** |

### Detalhe do grupo 1 (17 paths, todos existem em origin/main)

Cliente (2) · Compras (2) · KB (2) · OficinaAuto/kanban (2) · Produto (2) · RecurringBilling (2) · Sells/vendas-create (2) · Sells/vendas-index (2) · `_DesignSystem/pageheader-canon-v3` (1: `resources/js/Pages/Cliente/Index.tsx`) · `Crm/clientes` (0 — todas as 11 partes em TODO) · `_DesignSystem/sidebar-v3-unificado` (0 — todas as 17 partes em TODO). Contagem de partes de cada mapa bate com o número de linhas da tabela-fonte (7/6/11/6/8/6/9/9/8/12/17).

### Detalhe do grupo 2 (28 itens)

- 8 gap.md com `prototipo` + `tela_viva` reais: 16 paths existem; 8 frontmatters parseiam (fmVal **e** js-yaml). `Produto/produtos-gap.md` ganhou frontmatter **novo** (`id: requisitos-produto-produtos-gap`) — sem colisão (`doc-id-index.mjs --check-collisions` rc=0).
- `pageheader-canon-v3-gap.md`: `prototipo: TODO` (pulado); `tela_viva` composto → 4 paths existem (`PageHeader.tsx`, `PageHeaderPrimary.tsx`, `index.ts`, `Pages/Cliente/Index.tsx`); `fmVal` lê tudo. **Ressalva (não conta como erro do lote):** js-yaml estrito falha (`bad indentation of a mapping entry`) por causa do `: ` dentro do valor de `tela_viva` — linha **não tocada** pelo PR e igualmente inválida em `origin/main` (medido: `(3:119)` lá, `(4:119)` aqui, mesmo defeito). O lote mexeu nesse frontmatter e não corrigiu; o consumidor real (`fmVal`) não quebra.
- `sidebar-v3-unificado-gap.md`: `prototipo: TODO`; `tela_viva` é lista YAML de 4 paths — todos existem; js-yaml OK; `fmVal('tela_viva')` devolve `null` (lista), coerente com o mapa em TODO.
- `prototipo_nota` (pageheader + sidebar): a data/commit da remoção **confere** — `9da73296d3 2026-06-23` deletou `prototipo-ui/prototipos/pageheader-canon-v3/` e `.../sidebar-v3-unificado/visual-source.html`. Imprecisões menores (não contadas): (a) diz *"pasta prototipo-ui/prototipos/ expurgada"*, mas a pasta ainda tem **12 arquivos** em `origin/main` (4 subpastas: compras-grade-matrix, financeiro-prova-viva, inventario-migracao, perfil) — expurgadas foram as subpastas citadas; (b) a nota do pageheader cita o valor anterior truncado no meio da palavra (`b-v2-roxo-kpi`) sem reticência.

## Grupo 3 — REFUTADOS (lista completa, 19)

Regra declarada no cabeçalho de cada tabela: *"**Decidir.** repete o gap real já escrito na seção correspondente; 'Nada' = vivo à frente / paridade já registrada. Em conflito, a prosa vence."* Cada item abaixo viola essa regra contra a prosa do **mesmo arquivo** em `origin/main`.

### `memory/requisitos/Cliente/clientes-gap.md` — 1 de 7

| Linha | Ação na tabela | Prosa (origin/main) | Porquê |
|---|---|---|---|
| Header / identidade | **Decidir.** counter numérico dentro de cada tab… Construir ou rejeitar por escrito | §A1: *"→ **não é gap, é decisão explícita.** Anti-regressão: não re-adicionar."* · *"Esforço/risco: — (nada a fazer)"* · §Anti-regressões: *"Counter dentro da tab (removido de propósito 2026-05-25)"* | Ação reabre como "Decidir" uma decisão que a prosa marca como fechada e anti-regressão → inverte o veredito. Mapa: `clientes.map.json` `header-identidade._acionavel: true` |

### `memory/requisitos/RecurringBilling/cobranca-recorrente-gap.md` — 6 de 9

Prosa: *"Veredito: **MOCKUP-STALE**"* · *"**nada de funcional a adotar**"* · *"**Não enfileirar aplicação de tela.**"* Coluna "Esforço · risco" do mapa-por-parte = `— · —` em 8 das 9 partes. A tabela derivada carimba **Decidir.** nas 9.

| Linha | Ação na tabela | Prosa (origin/main) | Porquê |
|---|---|---|---|
| Header / hero | **Decidir.** Mockup tem eyebrow… | `— · — (nada a fazer)` · *"Mockup tem subtítulo com domínio errado (WR2 gráfica). Vivo deriva do presenter real."* | vivo à frente → deveria ser "Nada" |
| Tabela / lista | **Decidir.** Mockup: linha = star, avatar… | *"Mockup é subconjunto visual do vivo."* `— · —` | idem |
| Criação (Nova assinatura) | **Decidir.** botão "Nova assinatura" sem drawer (não implementado) | *"Mockup nem tenta; vivo é Onda 21 live."* `— · —` | o "gap" é do mockup, não do vivo; Ação inverte |
| Editar cobrança | **Decidir.** Ausente no mockup. | *"Ausente no mockup. Vivo: `EditSubscriptionDrawer`"* `— · —` | idem — "Decidir. Ausente no mockup" não é gap real |
| Abas Planos/Faturas/Config | **Decidir.** placeholders honestos… | *"Mockup admite que não fez; vivo já fez."* `— · —` | idem |
| Domínio / dados | **Decidir.** mock gráfica WR2… | `— · — (não importar dados)` · Tier 0: *"Nenhum dado/CNPJ do mockup deve entrar no app"* | Ação propõe decidir importar o que a prosa proíbe |

Aceitos (com nota): KPIs (micro-gap "A recuperar" P-M Tier 0 existe na seção), Filtros/busca (ADOTAR-PARCIAL #1 PeriodBar), Ações por linha/drawer (ADOTAR-PARCIAL #2 aba IA) — mas ver defeito de formatação abaixo.

### `memory/requisitos/Sells/vendas-index-gap.md` — 2 de 8

| Linha | Ação na tabela | Prosa (origin/main) | Porquê |
|---|---|---|---|
| Ações por linha | **Nada — vivo à frente (STALE no mockup).** | §5: *"**Gap potencial:** atalhos de ação direto na linha sem abrir drawer — _pendente_ confirmar em `SellsTabelaUnificada`."* | A prosa NÃO diz vivo à frente nem stale; diz gap potencial pendente. Ação inventa o veredito e apaga o `_pendente_` |
| Header / PageHeader | **Decidir.** sub-nav em página via `VdModNav`… | §1: *"**não é gap claro**, é divergência de navegação já decidida… **Não adotar sem decisão Wagner.**"* · §Veredito: listado em *"**Não adotar (divergência intencional já decidida)**"* | (borderline) o veredito final arquiva como "Não adotar/já decidida" → "Nada — decisão já registrada"; contado como erro pela mesma régua da Cliente/A1 |

### `memory/requisitos/_DesignSystem/pageheader-canon-v3-gap.md` — 4 de 12

Prosa §3: *"O componente vivo **já é o canon** nas partes nucleares (P1, P3, **P5**, P6, P7, **P9**, **P11**)"* · *"Onde propõe ALÉM do canon (precisa ADR + decisão): P4, P8, P7, P12"* · Recomendação: *"executar só o PASSO 0 (doc)… Tudo além (P4/P7/P8 + SPEC §7-§28) requer decisão"*.

| Linha | Ação na tabela | Prosa (origin/main) | Porquê |
|---|---|---|---|
| Counter por tab (P5) | **Decidir.** DIVERGE intencionalmente… | *"Decisão consciente registrada no código (Cliente/Index L900)… **não re-adicionar** sem sinal"* · P5 na lista "já é canon" | decisão registrada → "Nada" |
| Modo NAV vs FOCO (P9) | **Decidir.** FALTA no protótipo v3… | *"Vivo `PageHeader` já suporta modo FOCO trivialmente… **Só falta documentar**"* · P9 na lista "já é canon" | a falta é do protótipo, não do vivo; vivo cobre → "Nada" |
| Filtros (P10) | **Decidir.** EXPLORAÇÃO abandonada… | *"As 5 variantes não são canon de nada… **Ignorar pra fins de fundação.**"* Esforço `—` | ignorar ≠ decidir |
| As 3 famílias (P11) | **Decidir.** DECISÃO JÁ TOMADA — protótipo é histórico… | *"**Não há gap; é decisão fechada.**"* · P11 na lista "já é canon" | a própria Ação diz "DECISÃO JÁ TOMADA" e pede pra decidir |

Aceitos com nota: P2 Container (prosa diz vivo adiante **mas** *"decidir se sticky+blur entra é decisão Claude Design"* — Decidir defensável); P7 Ghosts está nas DUAS listas da prosa (canon **e** propõe-além) — "Nada — paridade" bate com "PARIDADE ~90%", ambiguidade é da prosa.

### `memory/requisitos/_DesignSystem/sidebar-v3-unificado-gap.md` — 6 de 17

Prosa §Ordem sugerida: *"**NÃO fazer (seriam regressões): #6/#7/#11/#17** — o código vivo está MAIS recente que o protótipo. #9/#12/#13/#14 — vivo tem features além; preservar."* · §Veredito: *"Os gaps reais e legítimos são **3**: #1 Tema, #4 Busca, #5 Pinned (+ #8 hints, menor)"*.

| Linha | Ação na tabela | Prosa (origin/main) | Porquê |
|---|---|---|---|
| Grupos de navegação (#6) | **Decidir.** 5 grupos + 3 shortcuts… | *"**NÃO regredir 8→5**"* · na lista NÃO fazer | regressão declarada → "Nada" |
| Labels dos grupos (#7) | **Decidir.** TOPO/VENDER/OPERAR… | *"governança (vivo vence)"* · na lista NÃO fazer | idem |
| Ícones dos itens (#9) | **Decidir.** glyphs unicode… (DIVERGE (vivo melhor)) | *"**NÃO trocar Lucide por glyph** (regressão)"* | a própria Ação diz "vivo melhor" e pede pra decidir |
| Hue por grupo (#11) | **Decidir.** `--gh` por `data-group`… (vivo mais elaborado) | *"governança (vivo vence)"* · na lista NÃO fazer | idem |
| Topbar / breadcrumb (#17) | **Decidir.** `page-topbar` com crumbs… (vivo decidiu remover) | *"**NÃO ressuscitar topbar**"* · Wagner 2026-05-17 | decisão registrada → "Nada" |
| Tema (light/dark) (#1) | **Decidir.** Sidebar light creme (`--bg-sb` 0.985) (DIVERGE) | Prosa (2026-06-23): *"bloqueado em decisão Wagner"* — **coerente com a prosa**, mas a decisão foi tomada depois: [ADR UI-0023](../requisitos/_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md) (accepted **2026-07-16**, Wagner textual *"sidebar é como esta black então. apague os conflitos em definitivo"*), supersede UI-0019/0009/0014; fonte da verdade `resources/css/cockpit.css:187` bloco `Sidebar — DARK FIXO`. CLAUDE.md: *"Sidebar PRETA (dark-fixo) — DEFINITIVO"* | Tabela **derivada em 2026-09-06** carimba `_acionavel: true` ("Construir ou rejeitar por escrito") sobre decisão [W] DEFINITIVA há 7 semanas. Contado como erro por evidência de canon em origin/main, não por conflito com a prosa (a prosa também está stale — `governanca:` ainda lista UI-0009/0014 como CANON) |

### Padrão do erro (sistemático, não pontual)

Nos 3 arquivos mais afetados a tabela-fonte da prosa tem uma coluna de **veredito** separada da coluna de **descrição do gap** (RecurringBilling: "Gap real" × "Esforço · risco `— · —`"; sidebar: "Mudou/Falta" × "Risco: governança (vivo vence)"; pageheader: "Estado" × "Risco/Governança"). O gerador copiou a coluna de **descrição** para "Ação" e prefixou **Decidir.** sem ler a coluna de **veredito** — por isso todas as 9 partes do RecurringBilling viraram "Decidir" num arquivo cujo veredito é "nada de funcional a adotar". É erro de prompt/derivação, espalhado: §2.6 do protocolo manda o lote inteiro voltar, não só as 19 linhas.

## Achados extras (fora da contagem — registrados, não contados)

1. **Célula "Estado no vivo" com conteúdo do mockup rotulado como vivo** — `RecurringBilling` linha "Ações por linha / drawer": a prosa tinha `tabs Detalhes\|IA` (pipe escapado); o gerador cortou no `\|` e o resto da descrição do **mockup** (*"timeline (3 eventos mock estáticos), footer … (botões sem ação)"*) entrou na célula como *"Vivo à frente: IA, card próxima cobrança…"*. Afirma sobre o vivo o que a prosa diz do mockup. A Ação da mesma linha ficou truncada (*"header + tabs Detalhes\ Construir…"*).
2. **Célula truncada em code-span** — `RecurringBilling` linha "Abas Planos/Faturas/Config": `(`/recurring-billing/planos` corta no `|` interno de `planos|faturas|configuracoes`. Pipe dentro de crase não foi escapado na derivação.
3. **js-yaml estrito falha em `pageheader-canon-v3-gap.md`** — pré-existente (mesmo erro em origin/main), linha `tela_viva` não tocada; `fmVal` lê. Sem `id:` nesse frontmatter (também pré-existente).
4. **`doc-id-index.mjs --check` sai 1** no branch — drift **de main** (≈40 docs de 09-01→09-03 sem `--write`), não do lote; o CI roda `--check-collisions` (rc=0 aqui) por decisão registrada no próprio workflow. Arquivo restaurado após a sondagem (`git checkout -- governance/doc-id-index.json`, árvore limpa).
5. **PR body vs diff**: `PLANS-INDEX-GENERATED.md` citado como regenerado, ausente do diff (ver §Escopo).

## Scan PII (grupo 6)

Base: `git diff origin/main...HEAD -- memory/requisitos | grep '^+'` → **2.064 linhas** adicionadas.

| Padrão | Regex | Hits no diff | Controle positivo (prova que a sonda casa) |
|---|---|---|---|
| CPF pontuado | `[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}` | 0 (rc=1) | fixture `123.456.789-09` → 1 |
| CPF cru (11 dígitos isolados) | `(^\|[^0-9])[0-9]{11}([^0-9]\|$)` | 0 (rc=1) | fixture `12345678901` → 1 |
| CNPJ | `[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}` | 0 (rc=1) | fixture `12.345.678/0001-99` → 1 |
| Telefone BR | `\(?[0-9]{2}\)?[ -]?9?[0-9]{4}[ -]?[0-9]{4}` | 0 (rc=1, sem exclusões) | fixture `(48) 99999-1234` → casa |
| E-mail | `[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}` | 0 (rc=1) | `memory/regras-time.md` → 1 (e-mail da WR2) |
| Valor em reais | `R\$ ?[0-9]` | 0 (rc=1) | fixture com o cifrão seguido de dígito (não reproduzida aqui — hook `block-brl-values-in-memory` barra o literal) → 1 |

Observação: a tabela do Compras traz a string `R$ [redacted Tier 0]` copiada da prosa — já redigida, não casa `R\$ ?[0-9]`. Nomes de cliente do CRM: nenhum (os nomes que aparecem são persona interna `[E]`/`Larissa` e mocks do protótipo já presentes na prosa de origin/main).

## Comandos reproduzíveis

```bash
git fetch origin main && git diff --name-status origin/main...HEAD
for m in Crm Estoque Fiscal Ponto RecurringBilling Repair; do node scripts/governance/requisitos-status.mjs $m --check; echo rc=$?; done
node scripts/governance/plans-index.mjs --check; echo rc=$?
node scripts/governance/design-code-map-check.mjs --check --strict; echo rc=$?
git ls-tree origin/main -- prototipo-ui/cowork/clientes-page.jsx   # ×17 paths
git log --diff-filter=D -1 --format='%h %ad %s' --date=short -- prototipo-ui/prototipos/pageheader-canon-v3/index.html
git diff origin/main...HEAD -- memory/requisitos | grep '^+' | grep -nE '[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}'; echo rc=$?
```

## Resultado

```json
{"itens_verificados": 141, "erros_confirmados": 19, "error_rate_pct": 13.48, "pii_hits": 0, "veredito": "reprovado"}
```

Sem os 2 itens borderline (Sells/Index "Header" e sidebar "Tema" por canon): 17/141 = 12,06% — segue reprovado. A correção honesta não é editar 19 células: é re-derivar a coluna Ação lendo a coluna de **veredito** da prosa (não a de descrição), regenerar os 11 `.map.json` com `gerar-map.mjs --atualizar`, escapar `|` em code-span, e re-refutar o lote inteiro (§2.6).
