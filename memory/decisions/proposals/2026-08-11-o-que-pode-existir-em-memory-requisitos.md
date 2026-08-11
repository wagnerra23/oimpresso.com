---
status: proposal
title: "O que pode existir em memory/ — a declaração que falta, por que não é derivável, e a redundância de 3 camadas"
proposed_by: Claude (auditoria a pedido de [W] — \"tem várias pastas que não deveriam existir, tá bagunçado\") — decisão [W]
proposed_at: 2026-08-11
relates_to:
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0088-module-rename-php-only
  - 0298-teto-de-governanca-anti-proliferacao-gates
  - 0314-poda-gates-onda-2-lei-fusoes
---

# PROPOSAL — o que pode existir em `memory/requisitos/`

## 0. Como reproduzir tudo que está aqui

```bash
ls -d Modules/*/ | wc -l                                    # 32 módulos reais
ls -d memory/requisitos/*/ | wc -l                          # 79 pastas
node scripts/governance/knowledge-drift.mjs | head -14      # o batimento: "75 módulos"
node scripts/governance/module-group-resolve.mjs --all      # papéis resolvidos por pasta
```

Medido em `origin/main`, 2026-08-11, clone completo (`--is-shallow-repository` = false).

## 1. O conceito declarado, e o desvio

[`scripts/governance/document-placement.json`](../../../scripts/governance/document-placement.json) (`ratified: true`, [W] 2026-07-22):

> `memory/requisitos/` = **`canonical`** — *"docs de modulo/produto (SPEC/RUNBOOK/BRIEFING/topicos) — ficam com o modulo"*

**79 pastas para 32 módulos.** O desvio não é uniforme — são 4 naturezas diferentes empilhadas no mesmo diretório:

| balde | n | o que é |
|---|---:|---|
| A · módulo vivo no disco | 32 | conceito cumprido |
| B · meta (`_prefixo`) | 7 | `_DesignSystem`, `_Governanca`, `_Ideias`… |
| C · feature-wish com ADR | 3 | `Autopecas` (0125), `Comissao` (0151), `Pcp` (0152) |
| D · módulo morto **com** decisão | 6 | `Accounting`, `Admin`, `ADS`, `Brief`, `SRS`, `TeamMcp` |
| E · nome sem módulo, **sem** decisão | 7 | `BI`, `Chat`, `Copiloto`, `Dashboard`, `Grow`, `LaravelAI`, `PontoWr2` |
| F · nunca foi `Modules/<X>` | 24 | `Sells`, `Produto`, `Cliente`… + órfãos |

**C e D não são bagunça — são decisão registrada.** A `ADS/` e a `SRS/` estão preservadas de propósito como canon histórico ([§5 2026-08-02](../../proibicoes.md) e [§5 2026-07-29](../../proibicoes.md)); apagá-las desfaz decisão. `Autopecas` é a doutrina da [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) funcionando (*"hipótese sem sinal vira ADR de feature wish"*).

## 2. Por que isto NÃO vira detector derivado — medido, para não ser re-proposto

A tentação óbvia é uma coluna no batimento: *"a pasta corresponde a algo vivo?"*. **Testei os 4 sinais derivados disponíveis contra a verdade apurada à mão** (11 órfãs × 20 áreas legítimas):

| sinal candidato | pega órfã | **falso-positivo em área legítima** |
|---|---:|---:|
| `BRIEFING.md` presente | 0/11 | 1/20 |
| `SPEC.md` presente | 5/11 | 8/20 |
| `resources/js/Pages/<X>/` viva | 10/11 | **8/20 (40%)** |
| `Modules/<X>` existe | 11/11 | **20/20** |

Nenhum discrimina. O melhor reprova 40% das áreas legítimas — `Infra`, `Mcp`, `Garantia`, `Tarefas` não têm tela **e estão certas**. É a família de guard sintático já morta 5× no §5 (allowlist-de-pasta 06-30 · `@scope` 07-09 · vocabulário 130 FP 07-16 · `toHaveKey` 100% FP 07-26 · `toContain` 07-28).

`SCOPE.md` foi testado e **não existe em nenhuma das 72** (ele ainda mora em `Modules/<X>/`; o [#5568](https://github.com/wagnerra23/oimpresso.com/pull/5568) é que está movendo) — zero poder discriminante hoje.

E o dono existente, [`module-group-resolve.mjs`](../../../scripts/governance/module-group-resolve.mjs), mostra por quê:

```
BI          1/10        Orcamento   1/10
Chat        1/10        User        1/10
Copiloto    2/10        Garantia    2/10
```

**Órfã e área legítima têm a mesma assinatura na árvore.** Não é limitação do script: a diferença entre `Orcamento` (área que [W] quer construir) e `BI` (ideia morta) é **intenção**, e intenção não está na árvore. Logo a peça que falta é uma **declaração**, não um detector.

## 3. Quem deveria ter avisado — e não avisou

O `knowledge-drift` (**"BATIMENTO DO CONHECIMENTO"**) já enumera **exatamente estas pastas** e chama todas de "módulo" (`75 módulos`), mas só fica vermelho quando o **texto cita** um `Modules/<X>` inexistente. Resultado:

```
🟢 BI          2  1  ok
🟢 Autopecas   4  1  ok
```

Verde. Ele mede *drift de citação*, nunca *correspondência com a realidade* — e o próprio vocabulário dele ("módulo") embute a confusão.

Precedente idêntico, escrito no [`ZELADOR.md`](../../../scripts/governance/ZELADOR.md): o `memory-health` dizia *"o vencimento é cobrado pelo ZELADOR"*, o charter nunca mencionou, e **16 de 30 advisory venceram sem um aviso** — classe LC-15 (mecanismo que anuncia o que não implementa). Mesma forma aqui.

## 4. A correção do meu próprio diagnóstico (evidência > inferência)

A primeira leitura do balde E dizia "7 mortas". **Ao abrir os arquivos, 4 têm conteúdo vivo** e apagá-las perderia trabalho:

| pasta | o que tem dentro | veredito |
|---|---|---|
| `Dashboard` | `RUNBOOK-home-index.md` — *"Tela `/home` (Dashboard pós-login)"* | **tela VIVA** — é área legítima, não ideia morta |
| `Copiloto` | `JANA-PRO-PRODUCT-PLAN.md` + `RUNBOOK-jana-pro-concierge.md` | conteúdo do **Jana Pro** — consolidar em `Jana/` ([ADR 0088](../0088-module-rename-php-only.md) já renomeou Copiloto→Jana) |
| `LaravelAI` | `ARCHITECTURE`, `GLOSSARY`, `adr/`, `audits/` | camada A da stack de IA ([ADR 0035](../0035-stack-ai-canonica-wagner-2026-04-26.md)) — conteúdo real |
| `PontoWr2` | 8 arquivos | sobra do rename Ponto ([ADR 0088](../0088-module-rename-php-only.md)) — consolidar em `Ponto/` |
| `BI`, `Chat`, `Grow` | só `BRIEFING` + `COMPARATIVO_CONCORRENCIA` | **material de benchmark** — mesma forma de `FinanceiroAvancado`/`Marketplaces` |

**Nenhuma das 7 é "apagar".** São *consolidar* (4) ou *declarar como benchmark/wish* (3).

## 5. A declaração proposta

Cada pasta de `memory/requisitos/<X>/` passa a ter **uma** classe declarada:

| classe | significado | quem entra hoje |
|---|---|---|
| `modulo` | existe `Modules/<X>/` no disco | os 32 |
| `area` | área de produto/domínio sem módulo próprio | `Sells`, `Produto`, `Cliente`, `Estoque`, `Atendimento`, `Orcamento`, `Garantia`, `Infra`, `Mcp`, `Inventory`, `Purchase`, `StockAdjustment`, `StockTransfer`, `Site`, `Suporte`, `Tarefas`, `User`, `Dashboard` |
| `wish` | hipótese parkada, **ancorada em ADR** ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)) | `Autopecas`, `Comissao`, `Pcp`, `FinanceiroAvancado`, `Marketplaces`, `BI`, `Chat`, `Grow`, `EvolutionAgent`, `MemoriaAutonoma` |
| `tombstone` | módulo morto preservado como canon histórico | `Accounting`, `Admin`, `ADS`, `Brief`, `SRS`, `TeamMcp` |
| `meta` | `_prefixo` | os 7 |

Sobram **4 que exigem ato, não classe**:

| pasta | ato proposto |
|---|---|
| `Copiloto` | consolidar em `Jana/` (rename da ADR 0088) |
| `PontoWr2` | consolidar em `Ponto/` (rename da ADR 0088) |
| `LaravelAI` | consolidar em `Jana/` ou `reference/` — **decisão [W]** |
| `Modules` | `UI-CATALOG.md` "auto-gerado bulk W31-10" — artefato gerado em lugar errado; investigar origem antes de mover |

**Regra que passa a valer:** pasta nova em `memory/requisitos/` nasce com classe declarada. Sem classe = órfã, e aparece.

## 6. O que muda depois de ratificado

Com a lista declarada, o check fica **trivial e FP-zero por construção** (compara contra declaração, não infere): estender o `knowledge-drift` com a coluna que consome a lista. Nasce **advisory** ([ADR 0275](../0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md)); promoção exige mordida provada + flip [W] ([ADR 0336](../0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)).

**Não construir antes da ratificação** — um check que consome lista não-ratificada mede a opinião de um agente.

## 7. O que esta proposal NÃO decide

- **`TaskRegistry` e `TeamMcp`** → já tratados no §1.5 da [proposal de 2026-08-04](2026-08-04-politica-requisitos-forja.md). Não re-decido aqui.
- **`ADS` e `SRS`** → preservação já decidida por lápide §5. Esta proposal só as *declara* `tombstone`.
- **Classificação de "fronteira" no `catalog-graph`** → em voo no [#5582](https://github.com/wagnerra23/oimpresso.com/pull/5582).
- **Onde `SCOPE.md` mora** → em voo no [#5568](https://github.com/wagnerra23/oimpresso.com/pull/5568) (ADR 0374).

## 8. `memory/` inteira — a redundância é real e tem 3 camadas

[W] 2026-08-11: *"acho que tem redundância na memória e os conceitos não estão bem estruturados"*. **Procede, e é medível.**

### 8.1 O mesmo assunto em 3 lugares

```bash
node -e "…"   # ver §0; conta pastas × arquivos soltos × memory/modulos
```

`memory/requisitos/` tem **79 pastas + 31 arquivos `.md` SOLTOS na raiz**. Cruzando com `memory/modulos/`:

| forma | n | exemplos |
|---|---:|---|
| **assunto em 3 lugares** — `modulos/X.md` + `requisitos/X.md` + `requisitos/X/` | **14** | `Jana`, `Fiscal`, `Cms`, `Connector`, `Woocommerce`, `Superadmin`, `Spreadsheet`, `ProductCatalogue`, `AssetManagement`, `Officeimpresso`, `Dashboard`, `BI`, `Chat`, `Grow` |
| solto na raiz espelhando `modulos/`, sem pasta | 9 | `AiAssistance`, `Boleto`, `Help`, `IProduction`, `Knowledgebase`, `Officeimpresso1`, `Writebot`, `codecanyon-…` |
| nome nos dois diretórios (`modulos/X.md` ↔ `requisitos/X/`) | **42** | — |

Note que os 14 incluem **módulos vivos** (`Jana`, `Fiscal`, `Woocommerce`) — não é dívida só de defunto: é a estrutura.

### 8.2 Por que os dois diretórios continuam existindo

Não é esquecimento. O registro marca `memory/modulos/` como **`rule: never`** (ref [#4690](https://github.com/wagnerra23/oimpresso.com/pull/4690)) — *"veredito NÃO-migrar: path-contracts/entrelaçado com append-only"*. E o [`requisitos/INDEX.md`](../../requisitos/INDEX.md) declara que **complementa** `memory/modulos/` (*"spec técnica"* × *"valor de negócio"*).

O problema não é a existência dos dois — é que **`memory/modulos/` é fóssil auto-declarado** (congelado em 2026-05-29, gerado por `php artisan module:specs` **através de 3 branches**), então a "spec técnica" de metade dos nomes descreve um mundo que não existe. Complementaridade entre um vivo e um fóssil é redundância na prática.

### 8.3 O que a medição NÃO acusou (para não inflar)

`node scripts/governance/doc-id-index.mjs --check-collisions` → **0 colisão em 2359 ids**. No nível de *identidade declarada* não há duplicata: os 3 lugares têm ids distintos. A redundância é de **assunto**, não de id — e por isso nenhum gate a enxerga.

### 8.4 As 23 pastas de `memory/` contra o registro

- **2 não declaradas**: `memory/feedback/` (só um `.gitkeep` — **vazia**) e `memory/scorecards/` (5 `.yaml`). A segunda cria quase-homonímia com `memory/governance/scorecards/` — o mesmo vetor de `dominio` × `dominios` que já é lápide §5.
- **4 declaradas `canonical` mas congeladas em 2026-06-08** com 1–2 arquivos: `cycles` (1), `templates` (1), `mwart-inventory` (2), `audits` (7).

### 8.5 O ato proposto (decisão [W])

1. **Os 31 soltos na raiz de `requisitos/`** — ou viram `requisitos/<X>/BRIEFING.md` (quando há pasta), ou saem. Hoje são a camada mais gratuita das três.
2. **`memory/modulos/`** — o `rule: never` continua valendo pro *path*, mas as **51 folhas não herdam o banner de fóssil do `INDEX.md`**: quem abre `memory/modulos/TeamMcp.md` direto não tem sinal nenhum. Banner por folha, forward-only (nunca em lote — [§5 2026-07-12](../../proibicoes.md)).
3. **`memory/feedback/`** (vazia) e **`memory/scorecards/`** — declarar no registro ou remover.

Registrado aqui para não ser redescoberto do zero; nenhum destes atos foi executado.
