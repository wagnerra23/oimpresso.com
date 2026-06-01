---
spec: Charter Governance no KB
module: KB
status: ativo
risk: medium
migration_priority: P1
owner: wagner
created: 2026-06-01
adr: 0243-charter-governance-kb
related: [0101, 0149, 0150, 0236, 0241, 0242]
---

# SPEC — Charter Governance no KB

> Backlog executável do [ADR 0243](../../decisions/proposals/0243-charter-governance-kb.md). Faseado F1→F4. Formato US-CHTR-NNN (parseável pelo `RequirementsFileReader` → alimenta o próprio Module Charter — dogfooding).
> **Convenção:** toda US é Tier 0-safe (`business_id` + FK). PR ≤300 linhas (commit-discipline). `preflight-modulo` obrigatório antes de tocar `Modules/KB`.

---

## Fase 1 — Page charter governado (status + sugestão + autorização)

### US-CHTR-001 · Status workflow no nó charter

Como **owner de charter**, quero que o nó charter tenha lifecycle explícito (`draft → in_review → ratified → outdated → superseded`) pra saber se o contrato está decidido e vigente.

- [ ] `kb_nodes.status` aceita os novos valores (VARCHAR — sem ALTER doloroso; validação no Model)
- [ ] `ratified` = equivalente a "live/committed" do Oxide (descreve a tela COMO É hoje)
- [ ] transição `in_review → ratified` exige permissão `kb.charter.approve`
- [ ] badge de status visível na interface (clicável quando `outdated` → "re-verificar")
- [ ] transição auditada em `activity_log` + `mcp_audit_log`
- [ ] Pest: transição sem permissão → 403
- **Implementado em:** _[TODO]_

### US-CHTR-002 · Modo sugestão (contribuição supervisionada)

Como **membro do time MCP**, quero **propor** uma mudança/observação ancorada no charter sem editá-lo, pra contribuir de forma supervisionada.

- [ ] `kb_comments` ganha `kind` (`comment|suggestion|question|erratum`) + `status` (`proposed|under_review|accepted|rejected|merged`)
- [ ] propor exige `kb.charter.suggest` (≥ `kb.comment`)
- [ ] sugestão NÃO altera `body_blocks`/núcleo (invariante preservada)
- [ ] sugestão aparece ancorada no bloco (`block_idx`) na interface
- [ ] PII em texto livre é redigida/auditada (LGPD Art. 37 — `LogsActivity` já existe)
- [ ] Pest: sugestão em nó `is_editable=false` NÃO grava body_blocks
- **Implementado em:** _[TODO]_

### US-CHTR-003 · Aprovação / autorização

Como **owner**, quero aprovar/rejeitar uma sugestão **com comentário obrigatório**, pra autorizar o que entra.

- [ ] ação approve/reject exige `kb.charter.approve` + comentário não-vazio
- [ ] approve de sugestão de núcleo → enfileira "publicar = PR" (US-CHTR-020); anexo → publica bloco aprovado
- [ ] trilha de auditoria completa (quem/quando/o quê/comentário)
- [ ] inbox do owner lista sugestões `proposed`/`under_review`
- [ ] Pest: reject sem comentário → 422
- **Implementado em:** _[TODO]_

---

## Fase 2 — Module Charter read-only

### US-CHTR-010 · Tipo `module-charter` + bridge consolidado

Como **Wagner/agente**, quero um nó por módulo que consolide **meta · limite · backlog · changelog · saúde**, pra saber até onde o módulo vai num lugar só.

- [ ] `kb_nodes.type='module-charter'` (bridge read-only, `is_editable=false`)
- [ ] `RequirementsFileReader` migrado de `Modules/SRS` → `Modules/KB/Services/` (salvo da deprecação)
- [ ] consolida: Meta (`BRIEFING`/`SCOPE.purpose`) · Limite (`SCOPE.not_contains` + Non-Goals filhos) · Backlog (`SPEC` US + DoD%) · Changelog (`CHANGELOG`) · Saúde (`module:grade`)
- [ ] bridge job gera/atualiza 1 module-charter por pasta `memory/requisitos/<X>/`
- [ ] Pest cross-tenant biz=1 vs biz=99
- **Implementado em:** _[TODO]_

### US-CHTR-011 · Arestas de grafo `governs-module` + `parent-charter`

Como **navegante do KB**, quero o grafo ligar module-charter ↔ módulo ↔ page-charters, pra navegar a hierarquia.

- [ ] `kb_edges.edge_type` aceita `governs-module` e `parent-charter` (auto-derivados)
- [ ] page charter de tela de módulo X → `parent-charter` → module-charter de X
- [ ] visível no `kb/Graph`
- **Implementado em:** _[TODO]_

### US-CHTR-012 · Tela Module Charter (reusa tri-pane)

Como **Wagner**, quero ver o Module Charter na interface KB que já gosto, pra ler meta/limite/backlog/changelog com os atalhos de sempre.

- [ ] reusa AppShellV2 + PageHeader + KpiGrid + tri-pane de `kb/Index.tsx`
- [ ] painel: Meta · Limite · Backlog (com DoD% barra) · Changelog · Saúde (nota module:grade)
- [ ] atalhos `j/k/Enter/Esc//` preservados; markdown render
- [ ] conformidade DS (tokens roxo v4 — sem cor crua; mira ≥ Leader no SCREEN-GRADE)
- [ ] charter próprio da tela + Pest GUARD
- **Implementado em:** _[TODO]_

---

## Fase 3 — Enforcement / motor de Champion

### US-CHTR-020 · "Publicar = PR" no `.charter.md`

Como **owner**, quero que aprovar uma mudança de núcleo gere um PR no `.charter.md`, pra o git seguir como fonte da verdade (ADR 0061).

- [ ] sugestão `accepted` de núcleo → cria branch + PR (ou task MCP) editando o `.charter.md`
- [ ] merge → bridge re-sincroniza o nó em ≤15min → status volta `ratified`
- [ ] núcleo nunca muda sem passar pelo git
- **Implementado em:** _[TODO]_

### US-CHTR-021 · "Spectral para charters" (linter no CI)

Como **time**, quero que o CI rejeite charter fora do schema, pra contrato inválido não entrar.

- [ ] linter valida: Mission presente · Non-Goals não-vazio · Anti-hooks declarados · `owner` válido · UX targets mensuráveis
- [ ] **charter mínimo (Gloaguen):** linter alerta se o charter repete o que `module:grade`/stylelint já garante
- [ ] roda no CI; viola → PR falha (estilo `stylelint-gate`)
- **Implementado em:** _[TODO]_

### US-CHTR-022 · Pest GUARD dos Non-Goals

Como **guardião de escopo**, quero cada Non-Goal virar teste que falha se a tela faz, pra anti-alucinação enforced (ADR 0101 L3).

- [ ] gerador transforma Non-Goals do charter em casos Pest `it("não faz X")`
- [ ] ratchet (ADR 0236): baseline aceita dívida; CI só falha se piorar
- **Implementado em:** _[TODO]_

---

## Fase 4 — Maturidade / autonomia

### US-CHTR-030 · Cadência de re-verificação (anti-cemitério)

Como **owner**, quero cadência de re-verificação por charter, pra charter vencido virar tarefa e não ficar stale silencioso (padrão Guru).

- [ ] `verify_interval` por charter (sprint/mensal/trimestral/anual)
- [ ] vencimento → status `outdated` + tarefa MCP pro owner; re-verificar = 1 clique
- [ ] charter `outdated` + sem uso → fila de revisão (não auto-delete; Wagner decide)
- **Implementado em:** _[TODO]_

### US-CHTR-031 · Scorecard bronze→champion ligado ao module:grade

Como **Wagner**, quero o charter exibir maturidade (bronze/prata/ouro/champion) derivada de checks objetivos, pra saber a saúde sem opinião.

- [ ] checks pass/fail agrupados em níveis (Soundcheck-style) usando dimensões do SCREEN-GRADE/`module:grade`
- [ ] governança *tiered*: tela crítica exige nível alto; experimental não (OpsLevel-style)
- **Implementado em:** _[TODO]_

### US-CHTR-032 · Trilha de onboarding por módulo

Como **dev/agente novo**, quero uma trilha (`kb_paths`) que percorra Module Charter → Page Charters → ADRs do módulo, pra onboarding guiado.

- [ ] trilha auto-montada por módulo via edges
- [ ] `audience` configurável (dev novo / Wagner / agente IA)
- **Implementado em:** _[TODO]_

---

## Regras (Gherkin · invariantes Tier 0)

### R-CHTR-001 · Núcleo imutável
**Dado** um nó `type in (charter, module-charter)` com `is_editable=false`, **quando** qualquer ator tenta gravar `body_blocks` via Model/HTTP, **então** o `KbNodeObserver` lança `DomainException` e o PUT retorna 403/422.
- **Testado em:** `GovernanceInvariantsTest` (já cobre charter; estender pra module-charter) _[TODO]_

### R-CHTR-002 · Autorização obrigatória
**Dado** uma sugestão `proposed`, **quando** alguém sem `kb.charter.approve` tenta `accept`, **então** retorna 403 e o status não muda.
- **Testado em:** _[TODO]_

### R-CHTR-003 · Publicação só via git
**Dado** uma sugestão de núcleo `accepted`, **quando** publicada, **então** a mudança passa por PR no `.charter.md` (nunca UPDATE direto em `body_blocks`).
- **Testado em:** _[TODO]_

### R-CHTR-004 · Multi-tenant
**Dado** business A e B, **quando** A lista charters, **então** nenhum nó/sugestão/edge de B aparece (`business_id` scope).
- **Testado em:** `CrossTenantIsolationTest` (estender) _[TODO]_

---

## Não-objetivos (V1)
- ❌ Module Charter editável com dado próprio (read-only consolidado — anti-SRS)
- ❌ Tabela nova tipo `srs_entries`
- ❌ Charter `is_editable=true`
- ❌ Aprovação multi-estágio pesada em charter de baixo risco (governança tiered)
