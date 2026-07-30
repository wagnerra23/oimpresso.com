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
