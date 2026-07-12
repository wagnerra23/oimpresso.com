---
page: /ia/admin/qualidade
component: resources/js/Pages/Jana/Admin/Qualidade/Index.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Jana
related_adrs: [114, 101, 93, 49, 50]
tier: B
charter_version: 1
---

# Page Charter — /ia/admin/qualidade (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Jana/Http/Controllers/Admin/QualidadeController@index` (rota `jana.admin.qualidade.index`, permissão `copiloto.mcp.usage.all` — Wagner/superadmin). Trend das métricas de qualidade da memória IA lido de `copiloto_memoria_metricas`.

---

## Mission
Dar a Wagner/superadmin a leitura ao vivo da qualidade da memória/recuperação da IA — se as 8 métricas obrigatórias + 3 RAGAS estão acima dos gates canônicos (ADR 0049/0050). Mostra KPIs da última leitura com status de gate (verde/vermelho) por business, trend em sparklines por métrica na janela escolhida, e uma tabela de runs recentes. Serve pra decidir quando calibrar HyDE/Reranker/RRF e se uma evolução de camada está liberada (Recall@3 ≥ 0.80 é bloqueante).

---

## Goals — Features (faz)
- KPI cards por business (`KpiGrid`/`KpiCard`) da última leitura: Recall@3, Precision@3, MRR, Faithfulness, Latência p95, Tokens/interação, Contradições, Cross-tenant — cada um com status de gate (✅/🔴) vs alvo.
- Filtros de janela (7/30/60/90 dias) e de business (`Todos`, `Plataforma (NULL)`, ou business específico) com botão Aplicar.
- Tabela de trend com sparkline SVG inline por business × métrica (8 métricas), marcando as críticas (gate ADR 0049).
- Tabela detalhada das últimas ~30 runs em `copiloto_memoria_metricas` ordenadas por data desc.
- Mostra tamanho do gabarito de avaliação (total + por categoria) usado no eval.
- Partial reload (`router.get` com `only: ['series','kpis','filtros']`) ao aplicar filtro — gates e gabarito não retrafegam.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não anota HITL ("essa resposta foi boa?") — explicitamente adiado para V2/Cycle 02 (comentado na própria tela).
- ❌ Não dispara alertas de drift a partir da tela — adiado para V2.
- ❌ Não roda o cálculo das métricas — depende do cron `copiloto:metrics:apurar` (23:55) / `copiloto:eval --persist`; a tela só visualiza.
- ❌ Não edita gates nem alvos pela UI (constantes canônicas ADR 0049/0050 no controller).

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (`AppShellV2` layout + `JanaAreaHeader active="qualidade-jana"`).

---

## Automation hooks (faz)
- Cron diário 23:55 (`copiloto:metrics:apurar`) alimenta `copiloto_memoria_metricas` que a tela lê — atualização passiva.
- `copiloto:eval --persist` contra o gabarito popula Recall/Precision/MRR/Faithfulness que aparecem aqui.
- Filtro dispara partial reload server-driven das séries/KPIs.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling nem auto-refresh — depende do cron externo; troca de filtro é a única re-busca.
- ❌ Não muta dados em GET — read-only.
- ❌ Cross-business é intencional aqui (visão superadmin `copiloto.mcp.usage.all`), NÃO viola scope de tenant — a tela é de plataforma, não do business logado.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar escopo do V2 (HITL anotação + drift alerts) pra não vazar pro charter live antes de existir
