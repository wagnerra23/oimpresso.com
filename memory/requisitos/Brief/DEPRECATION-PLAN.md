---
id: requisitos-brief-deprecation-plan
---

# DEPRECATION-PLAN — Modules/Brief

> **Status:** 📋 Planejado · **Owner:** [W] · **Decisão:** [W] 2026-07-30 (*"todos esses eu vou deletar"*)
> **Ordem no conjunto:** **3º de 6** — [proposal da ordem topológica](../../decisions/proposals/2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md)
> ⚠️ **O módulo mais barato em código e o mais caro em processo.** 35 arquivos, 4 acopladores — e sustenta uma skill **Tier A always-on**.

## ⚠️ ERRATA 2026-07-30 — o plano declara **0 cron**. Existe **1**, e ele roda em `live`.

> Origem: revisão adversarial pedida por [W]. Não altera o veredito do corpo — acrescenta um
> pré-requisito que faltava.

`brief:generate` → `Modules/Brief/Console/Commands/GenerateBriefCommand.php`. Medido pelo **oráculo
de runtime**, não por parse do `Kernel.php` (lápide §5 2026-07-17 — gate de ambiente tem ≥2 formas):

```php
// prod, APP_ENV=live → 110 schedules registrados · 108 rodam
// brief: 1 registrado, 1 roda
```

É ele que produz os **438** briefs de `mcp_briefs`. **Desligar/realocar o cron entra na E3**, junto
com a tool — senão o comando fica órfão gravando numa tabela sem dono.

Fica registrado também o achado que a revisão trouxe e que **muda o destino**: `Modules/Brief` e o
`BriefDiarioAgent` (dentro de `Modules/Jana`) são **dois produtos com o mesmo nome** e não se
referenciam. O receptor do `brief-fetch` proposto no corpo (**Jana**) é contestado — ver proposta
[#5073](https://github.com/wagnerra23/oimpresso.com/pull/5073), que aponta **Forja**.

## ⚠️ ERRATA 2026-07-30 (2ª) — o receptor é **Forja**, não Jana; e 5 medições do corpo estão erradas

> Origem: execução do plano parada na **E1**, retomada quando [W] apontou que a Forja tinha sido
> postada (*"Forja foi postado reconfira"*). **Nada de código foi removido nem movido** nesta rodada.
>
> **Recibo:** medido em `origin/main` @ `522a392272` (2026-07-30, **re-medido** — a 1ª passada foi
> em `14b0b85061` e envelheceu em 14 commits **no meio da própria análise**), com `git grep`/`git
> ls-tree` contra a **ref remota**, não contra checkout local. O clone da sessão estava **raso**
> (`--is-shallow-repository=true`) e **−101 commits** — nenhuma data de `git log` foi usada como
> recibo (lápide §5 2026-07-24).

### 1. O receptor: **Forja** — e ele passou a existir no meio desta análise

O corpo aponta **Jana**; as duas propostas mergeadas apontam **Forja**:

| Fonte | Diz |
|---|---|
| corpo deste plano | `brief-fetch` → **Jana** |
| [`mcp-e-forja-jana-e-usuario`](../../decisions/proposals/2026-07-30-mcp-e-forja-jana-e-usuario.md) ([#5072](https://github.com/wagnerra23/oimpresso.com/pull/5072)) | errata explícita: → **Forja** |
| [`brief-se-divide-em-dois`](../../decisions/proposals/2026-07-30-brief-se-divide-em-dois.md) ([#5073](https://github.com/wagnerra23/oimpresso.com/pull/5073)) | → **Forja** |

**A medição dá razão a Forja, por um eixo que o corpo não usou — tenancy:**

| | |
|---|---|
| `mcp_briefs` | **9 colunas, nenhuma `business_id`** (`database/schema/mysql-schema.sql`) — estado singleton do projeto |
| conteúdo | cycle · HITL · PRs · ADRs · SDD · charters = estado de **desenvolvimento**, zero dado de cliente |
| telas | **0** `.tsx`; consumidores são hooks/skills/6 agentes do `.claude` |
| `brief.access` | concedida **só em biz=1** |
| contraste | a Jana **é** tenant — `BriefDiarioAgent` recebe `businessId` no constructor |

Pôr o Brief dentro da Jana instala infra de desenvolvimento **sem tenant** dentro do módulo de IA
**do cliente** — o erro estrutural que o #5072 diagnostica e que produziu o incidente de 2026-07-29
(save de papel de tenant apagou os 17 `jana.mcp.*`).

**Destravou no mesmo dia:** [#5089](https://github.com/wagnerra23/oimpresso.com/pull/5089) executou a
**F5** do #5072 — o módulo `ProjectMgmt` foi renomeado para `Modules/Forja` (rename completo; o nome
antigo não existe mais na árvore, por isso não é citado aqui como path). O receptor **existe** e já
tem `Console/`, `Services/`, `Http/`, `Providers/`.

**A F4 NÃO foi executada:** `Modules/Forja/Mcp/` não existe e `Modules/Jana/Mcp/` segue com os **44**
arquivos (servidor + 40 tools). Isso não bloqueia — só define o corte:

| Peça | Vai para | Por quê |
|---|---|---|
| 4 `Services/` + 3 `Console/Commands/` + `Http/` + `Routes/` | **`Modules/Forja/`** direto | a Forja já tem essas pastas; um movimento só, sem duplo-move |
| `Mcp/Tools/BriefFetchTool` | **`Modules/Forja/Mcp/Tools/`** | `OimpressoMcpServer.php:63` muda **um token** (`\Modules\Brief\…` → `\Modules\Forja\…`). Registro **cross-módulo por FQN já é o padrão vigente** — aquela linha é exatamente isso hoje. Quando a F4 mover `Mcp/` pra Forja, vira referência relativa: limpeza, não migração. |

**Precedente operacional:** o #5089 é a mesma operação e teve de tocar `phpunit.xml`,
`phpstan-baseline.neon`, `modules_statuses.json` e o `deadlink-gate` (`.mjs` + `.test.mjs`) — os
mesmos arquivos que o item 2 abaixo aponta como invisíveis à varredura por namespace. Serve de
checklist pronto para a E4.

### 2. As 5 medições erradas do corpo

| O corpo/prompt afirma | Medido |
|---|---|
| `ManufacturingHealthCommand` **quebra** (R3) — *"patch obrigatório"* | É **`@see` em docblock** (`ManufacturingHealthCommand.php:30`, *"pattern referência"*). **Não quebra.** Vira referência morta — higiene de doc, não runtime. |
| `system-map.mjs` **quebra** (R2) | **Não quebra.** Ele deriva o censo parseando o array `$tools = [` do `OimpressoMcpServer` (`parseToolsRegistry`, `system-map.mjs:490`). O único `Brief` do módulo no `.mjs` é **comentário de exemplo** (L481); os demais `Brief` são `BRIEFING.md` e `BriefDiario` — outros assuntos. |
| `LeaseBriefSectionService` depende de `Modules\Governance` — buraco, *"não medi de onde vem o lease"* | **Medido: depende de `Modules\Jana\Services\WorkLease\WorkLeaseService`** (`LeaseBriefSectionService.php:8`). **Jana sobrevive → este buraco não existe.** A mesma afirmação errada está no R2 do #5073. |
| **0 cron** (corpo) · **1 cron** (1ª errata) | **2**, ambos `->environments(['live'])`: `brief:generate` (`Kernel.php:989`, 6×/dia) **e** `skills:tier-review` (`Kernel.php:237`, trimestral, [ADR 0095](../../decisions/0095-skills-tiers-convencao-interna.md)). O filtro da 1ª errata buscou por `brief` e não casa `skills:tier-review`. |
| **4 acopladores** | 4 **por namespace** (`git grep -lF 'Modules\Brief\'`). A varredura de namespace **não vê** o que referencia por **path**: `phpunit.xml:53` (registra `./Modules/Brief/Tests/Feature` como suíte), `.github/ci-sqlite-pest.list:70`, `phpstan-baseline.neon` (L1033 · L1039), `README.md:62`, + **19 `@see`** em docblock (Governance ×17 · Jana ×2). |

**Dos 4 "acopladores", só 1 é dependência de código real:** `Modules/Jana/Mcp/OimpressoMcpServer.php:63`
(`\Modules\Brief\Mcp\Tools\BriefFetchTool::class`). Um é teste que sai com o Governance (#6); dois são
docblock.

### 3. O acoplamento que o corpo não mediu: a direção **outbound**

A Fase 3 varreu só **quem consome o Brief** (inbound). Medindo o inverso — `git grep -n 'use Modules'
-- 'Modules/Brief/**/*.php'` — o `GenerateBriefCommand` importa **8 serviços de `Modules\Governance`**,
todos injetando seção no brief (`SddBriefLine` · `PlanHealthBriefLine` · `ShippedLogBriefLine` ·
`AdrReviewBriefLine` · `AdrPendenteBriefLine` · `ObraParadaBriefLine` · `ExposicaoTier0BriefLine` ·
`AgentOutcomeBriefSection`), mais 1 da Jana (`TasksSemDonoBriefLine`).

**Consequência que independe do receptor escolhido:** quem receber o `GenerateBriefCommand` herda
dependência dura de `Modules\Governance`. Quando o Governance cair no **#6**, o brief perde **8 das
~10 seções** — a menos que esses 8 serviços ganhem receptor antes. Isso não é risco do Governance:
é pré-requisito da E3 **deste** plano, e o corpo não o registrava.

### 4. `SkillTierReviewCommand` — segue **sem receptor**, agora com o custo medido

O corpo o lista como *"decisão [W] · sem receptor natural"*. Medido, ele **não é comando morto**:
tem cron **trimestral em `live`** (`skills:tier-review`, `Kernel.php:237`, `quarterlyOn(1, '06:40')`),
emite relatório append-only sob `memory/governance/` e implementa o loop telemetria→tier da
[ADR 0095](../../decisions/0095-skills-tiers-convencao-interna.md). Fora de `Modules/Brief` ele tem
**zero consumidor** (`git grep -lF 'SkillTierReviewCommand'` → nenhum).

[W] não decidiu o destino nesta rodada. **Fica declarado, não decidido** — aposentá-lo exigiria
emenda à ADR 0095, e inventar receptor é proibido.

### 5. O que esta errata **não** muda

O veredito de que o módulo sai segue de [W]. A Fase 4 segue **PRESERVE em tudo** — este plano
continua sem `DROP` de tabela. O resíduo *"CT 100 não medido"* segue **fechado** pelo adendo da
[ordem topológica](../../decisions/proposals/2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md)
(não repetir o número aqui — [proibições §5 2026-07-17](../../proibicoes.md)).

### Nota de método

Os itens 2 e 3 têm **uma causa só**: a Fase 3 mediu com um instrumento que responde uma pergunta
**parecida** com a que interessava — `git grep -lF 'Modules\Brief\'` responde *"quem importa o
namespace"*, não *"o que quebra se a pasta sumir"*. Namespace não vê path (`phpunit.xml`), não vê
docblock, e não vê a direção contrária. Classe **LC-08** do ledger.

## Fase 1 — Inventário

**Gerado:** [`SUPERFICIE.md`](SUPERFICIE.md) — **35 arquivos em 9 papéis** (`module-surface.mjs Brief --write`). Frescor 2026-07-30: `--check` **exit 0**.

Contornos: **0** telas `.tsx` · 2 arquivos em `Routes/` · **1 tool MCP** (`brief-fetch`) · **0** cron em `Kernel.php`.

## Fase 2 — Estado em produção (medido ANTES de planejar)

**Sistema medido:** `APP_ENV=live` · `u906587222_oimpresso` · 385 tabelas · **2026-07-30**.
**Controle positivo:** `business=82` · `users=124` · `transactions=75.255`.

| Tabela | Estado |
|---|---|
| `mcp_briefs` | **438 linhas** |
| `mcp_weekly_digests` | existe · 0 linhas |
| `mcp_doc_summaries` | existe · 0 linhas |

⚠️ **`Modules/Brief` não DECLARA nenhuma migration própria** — `Schema::create` do módulo devolve vazio. As 3 tabelas acima têm **outro dono** (`Modules/Jana`). **Corolário duro:** quem apagar este módulo **não deve apagar `mcp_briefs`** sem antes medir quem mais escreve nela. Apagar código ≠ apagar dado, e aqui os dois donos são diferentes.

## Fase 3 — Acoplamento externo

`git grep -lF 'Modules\Brief\'` fora da pasta → **4 arquivos**:

| Acoplador | Natureza |
|---|---|
| `Modules/Governance/Tests/Feature/AgentOutcomeBriefSectionServiceTest.php` | teste — sai com o Governance (6º) |
| `Modules/Jana/Mcp/OimpressoMcpServer.php` | **registra a tool `brief-fetch`** — Jana **sobrevive** |
| `Modules/Manufacturing/Console/Commands/ManufacturingHealthCommand.php` | Manufacturing **sobrevive** |
| `scripts/governance/system-map.mjs` | gerador do `PAINEL-SISTEMA` — **sobrevive** |

**Dois dos quatro sobrevivem ao conjunto** — logo exigem patch, não morrem de graça.

## Fase 4 — Decisão por tabela

| Tabela | Decisão | Por quê |
|---|---|---|
| `mcp_briefs` | **PRESERVE** — não é do Brief | 438 linhas, dono é `Modules/Jana`. Mexer aqui é escopo do Jana, que sobrevive. |
| `mcp_weekly_digests` | **PRESERVE** (dono Jana) | 0 linhas, mas a decisão não é deste plano |
| `mcp_doc_summaries` | **PRESERVE** (dono Jana) | idem |

**Este plano não dropa nada.** É o único dos 6 assim.

## Destino por função — realocação

> Medido 2026-07-30 nos **3 consumidores sobreviventes** (`grep` do símbolo importado, não do namespace — o namespace diz que depende, o símbolo diz **de quê**):
> `Jana/Mcp/OimpressoMcpServer.php` → `Modules\Brief\Mcp\Tools\BriefFetchTool` · `Manufacturing/Console/Commands/ManufacturingHealthCommand.php` → `Modules\Brief\Console\Commands\BriefHealthCommand` · `scripts/governance/system-map.mjs` → path da tool (censo).

| Peça | Módulo dono correto | Base da decisão |
|---|---|---|
| **`Mcp/Tools/BriefFetchTool`** | **Jana** ⛔ Tier A | quem **registra** já é o `OimpressoMcpServer` da Jana; e `mcp_briefs` (438 linhas) **já é tabela da Jana** ([ADR 0091](../../decisions/0091-daily-brief.md)). A tool muda de pasta, não de servidor nem de dono do dado. Zero migração de schema. |
| `Services/BriefGeneratorService` + `Services/BriefValidator` + `ValidationResult` | **Jana** | produzem o conteúdo de `mcp_briefs`; separar produtor do dado seria criar a fronteira que o módulo já não sustenta |
| `Services/LeaseBriefSectionService` | **Jana** · ⚠️ depende de `Modules\Governance\` | é a seção de *lease* do brief e importa Governance, que morre no 6º. **Realocar não basta — a seção precisa de receptor pro sinal também**, ou sai do brief. Não medi de onde vem o lease. |
| `Console/Commands/GenerateBriefCommand` | **Jana** | é o cron que alimenta `mcp_briefs` (`brief:generate`, schedule) |
| **`Console/Commands/BriefHealthCommand`** | **Jana** · ⚠️ **acoplador vivo fora do conjunto** | o `ManufacturingHealthCommand` referencia esta classe, e **Manufacturing não está na lista de deleção**. É o único acoplamento do conjunto com módulo de **produto**. Patch obrigatório, ou o health do Manufacturing quebra. |
| `Console/Commands/SkillTierReviewCommand` | **decisão [W]** | revisa tier de skill — tema de `.claude/skills/`, que não é módulo Laravel. Sem receptor natural. |
| Referência em `scripts/governance/system-map.mjs` | **atualizar, não mover** | é o censo de tools MCP; aponta pro path da tool. Muda com o path novo. |

**Padrão:** 5 de 7 vão pra **Jana**, e o argumento não é conveniência — **a tabela já é dela e o servidor que registra a tool já é dela**. O Brief, medido, é a pasta do produtor de um dado que nunca foi seu.

**Os 2 buracos reais** (não invento receptor): a origem do sinal de *lease* e o `SkillTierReviewCommand`.

## Fase 5 — Riscos Tier 0

| # | Risco | Severidade | Mitigação |
|---|---|---|---|
| **R1** | **`brief-fetch` morre e o protocolo de sessão vai com ele.** É skill **Tier A always-on** (`CLAUDE.md` passo 1) + hook `SessionStart` + [ADR 0091](../../decisions/0091-daily-brief.md). Todo agente do time começa a sessão por ela. | **ALTA — a mais grave dos 6** | Decidir o receptor da tool **antes** da E3. Candidato natural: `Modules/Jana`, que já a registra no `OimpressoMcpServer`. |
| **R2** | `system-map.mjs` referencia o namespace → o gerador do `PAINEL-SISTEMA` quebra, e com ele o inventário vivo de módulos | média | Patch no `.mjs` na mesma leva |
| **R3** | `ManufacturingHealthCommand` quebra (módulo vivo) | média | Patch ou remoção da seção de brief |
| **R4** | PII / cross-tenant / volume | **nenhum** | o módulo não tem tabela própria |

Nenhum check **required** cita Brief.

## Roadmap

| Etapa | O que | Gate [W] |
|---|---|---|
| **E1** | **Decidir o destino do `brief-fetch`** (R1) — migrar pro Jana ou aposentar a skill Tier A | ✋ **[W] decide — bloqueia tudo** |
| **E2** | Patch nos 2 sobreviventes: `system-map.mjs` + `ManufacturingHealthCommand` | ✋ [W] aprova |
| **E3** | Migrar a tool MCP pro receptor + atualizar `CLAUDE.md` (passo 1) e o hook `SessionStart` | ✋ [W] aprova |
| **E4** | Remover `Modules/Brief/` + rotas + `modules_statuses.json` | ✋ [W] aprova |
| **E5** | Smoke real: `brief-fetch` responde pelo receptor (ou a skill sai do CLAUDE.md) | ✋ [W] confere |
| **E6** | Lápide §5 + `BRIEFING` final | — |

**Sem DROP de tabela em nenhuma etapa.**

## Resíduo honesto

- **CT 100 não medido** — e aqui importa mais que nos outros: o MCP server (`mcp.oimpresso.com`) é quem **serve** a tool.
- **Não medi quem mais escreve em `mcp_briefs`** além do Brief. Antes de qualquer decisão sobre a tabela isso precisa de medição própria — a afirmação "o dono é o Jana" vem das migrations, não de rastrear escrita.
- **Pest não rodado** (Tier 0 → CT 100).
