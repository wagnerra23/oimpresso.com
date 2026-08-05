---
id: resources-js-pages-governance-qualidade-ia-charter
page: /governance/qualidade-ia
component: resources/js/Pages/governance/QualidadeIa.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
related_runbook: memory/requisitos/Governance/RUNBOOK-qualidade-ia.md
owner: wagner
status: draft
last_validated: "2026-08-05"
parent_module: Governance
related_adrs: [366, 114, 104, 101, 93, 86, 49, 50]
tier: B
charter_version: 1
---

# Page Charter — /governance/qualidade-ia (DRAFT)

> **Status:** draft. Porte de `Jana/Admin/Qualidade/Index` para a Governança
> ([ADR 0366](../../../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md) §D-B — decisão [W]
> 2026-08-03: *eval é gate de conformidade, medido contra piso/baseline igual `module-grades` e `drift`*).
> Herda os Non-Goals/Anti-hooks do charter de origem, que **Wagner ainda não ratificou**; continua `draft`
> até ele aprovar (nenhuma inferência nova foi adicionada neste porte).
>
> Backend: `Modules\Governance\Http\Controllers\QualidadeIaController@index` (rota
> `governance.qualidade-ia.index`, permissão **`jana.mcp.usage.all`** — nome legado preservado de
> propósito, ver Anti-hooks). Trend das métricas de qualidade da memória IA lido de
> `copiloto_memoria_metricas`.

---

## Mission

Dar ao auditor ([W]/superadmin) a leitura ao vivo da qualidade da memória/recuperação da IA — se as 8
métricas obrigatórias + 3 RAGAS estão acima dos gates canônicos (ADR 0049/0050). Mostra KPIs da última
leitura com status de gate (verde/vermelho) por business, trend em sparklines por métrica na janela
escolhida, e tabela de runs recentes. Serve pra decidir quando calibrar HyDE/Reranker/RRF e se uma
evolução de camada está liberada (`Recall@3 ≥ 0.80` é bloqueante).

---

## Goals — Features (faz)

- Strip de sub-navegação da Governança (`GovernancaSubNav active="qualidade-ia"`) — lista vem dos ghosts do `DataController`.
- KPI cards por business (`KpiGrid`/`KpiCard`) da última leitura: Recall@3, Precision@3, MRR, Faithfulness, Latência p95, Tokens/interação, Contradições, Cross-tenant — cada um com status de gate vs alvo.
- Filtros de janela (7/30/60/90 dias) e de business (`Todos`, `Plataforma (NULL)`, ou business específico) com botão Aplicar.
- Tabela de trend com sparkline SVG inline por business × métrica (8 métricas), marcando as críticas (gate ADR 0049).
- Tabela detalhada das últimas ~30 runs em `copiloto_memoria_metricas` ordenadas por data desc.
- Mostra tamanho do gabarito de avaliação (total + por categoria) usado no eval.
- Partial reload (`router.get` com `only: ['series','kpis','filtros']`) ao aplicar filtro — gates e gabarito não retrafegam.

---

## Non-Goals — Features (NÃO faz)

> Herdados do charter de origem — **pendentes de ratificação [W]**, não são inferência nova deste porte.

- ❌ Não anota HITL ("essa resposta foi boa?") — adiado para V2/Cycle 02.
- ❌ Não dispara alertas de drift a partir da tela — adiado para V2.
- ❌ Não roda o cálculo das métricas — depende do cron `copiloto:metrics:apurar` (23:55) / `copiloto:eval --persist`; a tela só visualiza.
- ❌ Não edita gates nem alvos pela UI (constantes canônicas ADR 0049/0050 no controller).

---

## UX targets

- p95 < 1500ms (auditor) ; cabe em 1280px (ROTA LIVRE) ; `AppShellV2` + `GovernancaSubNav`.
- Filtros com `<Label htmlFor>` associado ao controle.

---

## Automation hooks (faz)

- Cron diário 23:55 (`copiloto:metrics:apurar`) alimenta `copiloto_memoria_metricas` que a tela lê — atualização passiva.
- `copiloto:eval --persist` contra o gabarito popula Recall/Precision/MRR/Faithfulness.
- Filtro dispara partial reload server-driven das séries/KPIs.

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ Não faz polling nem auto-refresh — depende do cron externo; troca de filtro é a única re-busca.
- ❌ Não muta dados em GET — read-only.
- ❌ Cross-business é intencional aqui (visão de plataforma sob `jana.mcp.usage.all`), **NÃO viola scope de
  tenant** — a tela é de plataforma, não do business logado (exceção Constituição Art. 6+8 preservada pela ADR 0366).
- ❌ **Não renomeia a permissão** no movimento de tela: o gate segue `jana.mcp.usage.all`.
  Rename exige ADR + migration própria.
- ❌ **Não reintroduz `Inertia::defer`** em `series`/`kpis` sem o wrap `<Deferred>` no frontend —
  o HOTFIX de 2026-05-25 existe porque defer sem wrap dá `TypeError undefined.filter` em prod.
- ❌ Não importa nada de `@/Pages/Jana/**`.

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks (pendência herdada — nunca foi ratificada na origem)
- [ ] Smoke visual 1280/1440 (screenshot) na rota nova
- [ ] Ghost `qualidade-ia` no `DataController` da Governança (senão a tela nasce órfã)
- [ ] Redirect 301 da rota antiga `/ia/admin/qualidade` ativo
- [ ] Confirmar escopo do V2 (HITL anotação + drift alerts) pra não vazar pro charter live antes de existir
- [ ] Dívidas herdadas conhecidas (sparkline com escala local que engana; cores HEX hardcoded fora do DS) — decidir se entram nesta onda ou viram task própria
