---
id: sessions-2026-07-30-deprecacao-modulos-governanca-chips
title: "Deprecação dos módulos de governança — 6 PRs, 10 chips, e 4 medições minhas refutadas"
topic: "Deprecação dos módulos de governança (Admin/Brief/SRS saem, ProjectMgmt→Forja, Auditoria fica) + 4 refutações de medição minha"
date: "2026-07-30"
type: session
authority: canonical
lifecycle: ativo
owner: W
---

# Sessão 2026-07-30 — deprecação dos módulos de governança

## TL;DR

[W] perguntou *"quais outros módulos estão destinados à morte?"*. A sessão terminou com **6 PRs meus mergeados**, **10 chips** abertos (6 executados por sessões paralelas no mesmo dia) e o parque de módulos caindo de **37 → 34**. Mas o resultado que mais importa não é o placar: **quatro medições minhas foram refutadas por adversário ou por [W]**, e duas delas quase produziram ato irreversível.

## O que foi entregue (meus PRs, todos mergeados)

| PR | O quê |
|---|---|
| [#5056](https://github.com/wagnerra23/oimpresso.com/pull/5056) | 4 docs canônicos param de restatear a contagem de módulos (`44/36` era censo **cross-branch**, não inventário) |
| [#5061](https://github.com/wagnerra23/oimpresso.com/pull/5061) | inventário de deprecação dos 5 módulos restantes |
| [#5067](https://github.com/wagnerra23/oimpresso.com/pull/5067) | `## Destino por função — realocação` em Admin · ADS · Auditoria · Brief · TeamMcp |
| [#5079](https://github.com/wagnerra23/oimpresso.com/pull/5079) | **Auditoria FICA** — SCOPE v2 + SDD completo, plano de deprecação deletado |
| [#5081](https://github.com/wagnerra23/oimpresso.com/pull/5081) | apaga as 10 lápides `Modules/<X>/BRIEFING.md`, reponta 5 vínculos + 1 teste Pest |
| [#5082](https://github.com/wagnerra23/oimpresso.com/pull/5082) | mata o presente-falso de 6 módulos já removidos |

## As 4 refutações — o conteúdo real desta sessão

### 1. Auditoria: medi a tabela PRÓPRIA de um módulo que vive da tabela de OUTRO

Recomendei `Auditoria` como **primeiro a deletar** — *"delete mais barato do conjunto, risco de dado zero"* — porque `auditoria_audit_notes` tem **0 linhas e não existe em banco nenhum** (medido em prod, staging e CT 100, três vezes, sempre certo).

A tabela própria **nunca foi o valor do módulo**. Ele é o **leitor e revertedor** da trilha por-registro do `activity_log`: **117.510 linhas, última escrita 2026-07-30 11:22:14** — escrevendo no mesmo dia em que eu ia deletar.

O sinal estava na minha mão e eu o descartei: dos 7 arquivos em `tests/Feature/Auditoria/`, classifiquei **6 como "não são do módulo, sobrevivem"** — e esses 6 são exatamente a prova de que a capacidade funciona.

Quem barrou: **[W] por conhecimento de domínio** (*"ele registra as alterações em cada registro é super importante"*) + o hook `block-destructive`, que recusou o `git rm`. **Nenhuma medição minha teria pego.**

**Raiz documental, e é o achado mais reaproveitável:** o `SCOPE.md` do próprio módulo declarava em `not_contains` que *"Activity Log per-Model → trait nos próprios Models"* e que ele consome *"eventos consolidados, não cada UPDATE"* — enquanto `GET /auditoria/{activityId}` e `POST /{activityId}/revert` sempre foram por-registro. **O doc expulsou o núcleo do módulo, e foi ele que sustentou o "módulo vazio".**
→ Lápide §5 + LC-08 #33.

### 2. `module:specs`: medi INVOCADOR quando a pergunta era CONSUMIDOR

Propus matar o gerador porque `git grep` em `Kernel.php`/`.github/`/`scripts/`/`package.json`/`.claude/` deu vazio. O adversário achou **3 consumidores**: 4 testes Pest + injeção de dependência em 2 classes — e os comandos são **auto-registrados** por `$this->load()`, logo nunca apareceriam num grep por nome.

Também propus apagar `memory/modulos/`, que é **path-contract lido em runtime** (`GenerateModuleRequirementsCommand:105,115` faz discovery pelo filename) e está `rule: "never"` no `document-placement.json` — registro ratificado por [W] em 2026-07-22, com limite explícito de não re-propor.

Agravante: eu **citei a lápide da claim-de-ausência no próprio mandato do adversário** e não a apliquei em mim. → LC-08 #31.

### 3. `head -20` numa listagem lido como ausência de arquivo

Anunciei ao [W] **em tabela** que o TeamMcp *"não tem plano"*. Tem — 95 linhas, e a Fase 2 dele é melhor que a que eu ia escrever. O `git ls-files | grep DEPRECATION-PLAN | head -20` cortou a linha (ordena depois de `NFSe`). Quem me salvou foi o `Write` recusando com *"file has not been read yet"*. → LC-08 #32.

### 4. Brief: 3 erros meus na seção de realocação, achados pela sessão do chip

A [errata 2ª](../requisitos/Brief/DEPRECATION-PLAN.md) do plano do Brief refuta:

| Eu afirmei | Medido |
|---|---|
| `ManufacturingHealthCommand` **quebra** — "patch obrigatório" | é **`@see` em docblock**. Não quebra. Higiene de doc, não runtime |
| `LeaseBriefSectionService` é **buraco sem receptor** (dependia de Governance) | depende de `Modules\Jana\Services\WorkLease\WorkLeaseService`. **Jana sobrevive → o buraco não existe** |
| receptor = **Jana** (5 de 7 peças) | **Forja**, por um eixo que eu não usei: **tenancy**. `mcp_briefs` tem 9 colunas e **nenhuma `business_id`** — é estado singleton de projeto. A Jana **é** tenant. Pôr infra de desenvolvimento sem tenant dentro do módulo de IA do cliente é o erro estrutural que produziu o incidente de 29/07 |

E o corpo declarava **0 cron**; são **2**, ambos `->environments(['live'])`.

## O que as sessões paralelas executaram no mesmo dia

`ProjectMgmt` → **`Modules/Forja`** ([#5089](https://github.com/wagnerra23/oimpresso.com/pull/5089)) · **Brief absorvido pela Forja** ([#5098](https://github.com/wagnerra23/oimpresso.com/pull/5098)) · 6 telas órfãs do MemCofre removidas ([#5088](https://github.com/wagnerra23/oimpresso.com/pull/5088)) · fósseis `01-project-overview.md` e `03-architecture.md` apagados · **errata da ADR 0354** · endpoints `/api/mcp` da Jana → Forja ([#5101](https://github.com/wagnerra23/oimpresso.com/pull/5101)).

O rename resolveu uma decisão que estava travada: a **Forja** era o único item do plano do TeamMcp *"sem receptor derivável"*.

## Medições que fecharam resíduo antigo

**CT 100 não existe como banco separado.** Os 6 planos carregavam *"CT 100 não medido — pré-requisito da E3"*. Medido: o MCP server do CT 100 aponta pro **banco do Hostinger** (`srv1818.hstgr.io` / `u906587222_oimpresso`); o staging (`oimpresso_staging`, 377 tabelas) também não tem as tabelas procuradas. **Não há terceiro banco** — as 5 tabelas de observabilidade do Governance nunca rodaram em lugar nenhum.

**O required da ADR 0354 nunca existiu.** A ADR declara `teammcp-pest` promovido a required em 27/07. Proteção viva: **34 contexts, nenhum é ele**. Canon afirmando enforcement inexistente — classe LC-10. Errata escrita por sessão paralela.

**O corpus do `fact-anchor` está certo em ser estreito.** Rodei o `factAnchorScan` sobre 533 docs (BRIEFING · charter · SCOPE · reference · SPEC): **149 hits**. Line-by-line, **6 são mentira**. O resto é menção histórica datada — e o controle-negativo (`decisions/`, 365 docs) deu **275 hits**, todos legítimos. Ampliar o corpus reprovaria 51 hits de feature-wish e 16 de Classe B.

## Erros de método que não viraram conclusão (pegos a tempo)

- `execSync` no Windows roda em `cmd.exe`, onde **aspas simples não são aspas** — minha 1ª medição deu `docs=0` em todos os tiers e eu quase reportei "corpus limpo". Peguei olhando o denominador.
- `Modules/Project` é **prefixo** de `Modules/ProjectMgmt`: dos 43 "Project", **35 eram ProjectMgmt vivo**.
- Chamei o CI de verde com **11 checks pendentes** — o GT-G3 estava entre eles.
- Dois alertas de CI eram falso-alarme: `UI Lint` morreu no `composer install` (MyFatoorah devolveu **503**) e o `Pest (KB)` ainda estava **em andamento** quando o evento chegou.

## Estado ao fechar

**Módulos:** 34 (eram 37). Saíram: Admin · Brief · SRS · MemCofre. Renomeado: ProjectMgmt → Forja.
**Ficam por decisão [W]:** Auditoria (revertida na execução).
**Ainda na fila:** ADS · Governance · TeamMcp · Vestuario.

**4 decisões [W] travando os deletes:** destino final do `brief-fetch` (parcialmente resolvido pela Forja) · ARCHIVE ou DROP das 36.607 linhas do dual-brain do ADS (100% `outcome='cancelled'`) · receptor dos **12 checkers** do Governance (o `MultiTenantScopeChecker` é o único varredor que exige `HasBusinessScope` em Model novo) · e o **Vestuario**, que é produção da ROTA LIVRE e cujo chip para na medição de propósito.

## Pendência que ninguém pagou

O `GenerateBriefCommand` importa **8 serviços de `Modules\Governance`**. Quando o Governance cair, o brief perde **8 das ~10 seções** — a menos que esses 8 ganhem receptor antes. Não é risco do Governance: é pré-requisito da E3 do Brief, e o corpo do plano não o registrava.
