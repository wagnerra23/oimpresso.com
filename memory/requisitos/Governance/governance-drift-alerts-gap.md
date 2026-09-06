---
id: requisitos-governance-drift-alerts-gap
tela: governance/DriftAlerts (/governance/drift)
prototipo: prototipo-ui/cowork/governance-page.jsx + governance-telas.jsx
tela_viva: resources/js/Pages/governance/DriftAlerts.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — governance/DriftAlerts

> Protótipo = porte REVERSO do vivo (governance-page.jsx:1-3 "Espelha as telas vivas"; governance-telas.jsx:2-3 "Espelha … DriftAlertsController (scan de SCOPE.md × filesystem)"; retrato de ~2026-08-23). Fase 1 = PARIDADE. Charter: `resources/js/Pages/governance/DriftAlerts.charter.md` (Non-Goals respeitados, nunca reabertos).

**Veredito:** VIVO-À-FRENTE com 1 item a decidir — o vivo tem links de remediação e lista de alertas persistidos que o retrato não desenha; o retrato acrescenta só a nota de "escopo ilegível" (YAML).

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header / PageHeader | `DriftAlerts.tsx:65-69` — `<PageHeader icon="alert-triangle" title="Drift Alerts" description=…>` (Art. 7, `bin/check-scope.php`, cron Enforcement #5); layout `AppShellV2` em `:225`. Mockup: `governance-page.jsx:403-418` (h1 `TITULOS.drift` "Governança — drift de escopo" + subtítulo + selo) | Nada — paridade (títulos adaptados) |
| Abas do shell (sub-navegação) | `DriftAlerts.tsx:64` `<GovernancaSubNav active="drift" />` (key `drift` declarada nos ghosts do DataController — comentário em `:63`). Mockup: `governance-page.jsx:24-30` + `:420-424` | Nada — paridade |
| KPIs (4 cards com tone dinâmico) | `DriftAlerts.tsx:71-98` — Controllers em drift · Módulos com drift (`de N total`) · Sem SCOPE.md · Total módulos; `tone` warning/success conforme `> 0` (`:74`, `:80`, `:87`). Mockup: `governance-telas.jsx:169-173` (mesmos 4; `zero ? success : warning`) | Nada — paridade |
| Skeleton / carga diferida | Vivo: render síncrono — `Deferred／defer` → 0 hits em `DriftAlerts.tsx`; props chegam prontas (`:55-60`). Mockup: `governance-telas.jsx:162-163` (`setTimeout 800ms`) + `:178` (`Esqueleto`) e KPIs em "—" até `pronto` (`:170-172`) | Nada — mock/harness do protótipo (o atraso é `setTimeout`, não capacidade); o charter §UX Anti-patterns já registra "atualmente síncrono — fica pra otimização" |
| Seção "Drift detectado em runtime" (lista por módulo) | `DriftAlerts.tsx:100-158` — `Card` + `Badge "N módulos"` (`:104-106`), um `Alert destructive` por módulo com `Badge "N de M controllers"` (`:117-119`), lista mono dos não-declarados (`:122-128`), hint das 3 saídas (`:129-131`) e **dois botões-link** "Abrir SCOPE.md" / "Ver controllers" para o GitHub (`:132-151`, `GH_BLOB` em `:45`). Mockup: `governance-telas.jsx:176-197` (mesma estrutura: módulo, selo "N não declarados", total, lista mono, parágrafo das 3 saídas; `Abrir／github／href` → 0 no range) | Nada — vivo à frente (links de remediação direto pro SCOPE.md e pra pasta de controllers só existem no vivo) |
| Estado "zero drift" | `DriftAlerts.tsx:109-110` `<EmptyState icon="check-circle" title="Sem drift" …>`. Mockup: `governance-telas.jsx:179-180` (`A.Vazio variant="done"` "Os N módulos batem com o declarado") | Nada — paridade |
| Nota "Escopo ilegível" (SCOPE.md com YAML inválido) | Vivo: nenhuma mensagem na tela — `yaml／ilegível／parse` → 0 hits em `DriftAlerts.tsx`; `Props` (`:33-43`) não traz lista de erros de parse. O serviço registra `\Log::error('DriftAlertService: YAML parse falhou em SCOPE.md', …)` em `Modules/Governance/Services/DriftAlertService.php:154` e segue. Mockup: `governance-telas.jsx:198-203` (`A.Nota tone="warn"` listando módulo + erro, "fica fora da comparação; o erro vai para o log") | **Decidir.** O mockup (`governance-telas.jsx:198-203`) exibe na tela o módulo cujo SCOPE.md não parseou; o vivo (`DriftAlerts.tsx:100-158`) só loga (`DriftAlertService.php:154`). O charter §UX Targets pede "mensagem clara quando YAML parse falha (log estruturado, UI não quebra)" — decide o log, não decide a UI. Exige expor o erro no payload. Construir ou rejeitar por escrito. |
| Seção "Módulos sem SCOPE.md" | `DriftAlerts.tsx:160-184` — `Card` renderizado só quando `length > 0`, `Badge` com contagem (`:165`), cada módulo é botão-link mono pro GitHub (`:170-179`). Mockup: `governance-telas.jsx:207-212` (chips estáticos `gov-chip`, sempre renderizado, com sub explicando "não é drift zero") | Nada — vivo à frente (chips com link; o vivo omite o card quando vazio) |
| Seção "Histórico (mcp_alertas — últimos 30d)" | `DriftAlerts.tsx:186-220` — `Card` com `Badge` de contagem (`:190-192`), **lista** de `persisted_alerts` (severidade via `severityVariant` `:48-53`, módulo, detalhe, data — `:202-217`) e vazio "Sem alertas persistidos… Fase 3.5 pendente" (`:195-200`). Mockup: `governance-telas.jsx:214-217` (só o vazio `A.Vazio variant="offline"`, explicando que o enum de `mcp_alertas` não tem a categoria) | Nada — vivo à frente (o vivo já renderiza a lista quando o cron persistir; o retrato desenha só o vazio) |
| Ações proibidas (auto-fix · suprimir · ignorar · edit inline de SCOPE.md) | Vivo: nenhuma — `Suprimir／ignorar／snooze` → 0 hits em `DriftAlerts.tsx`. Mockup: também não desenha (só leitura, `governance-telas.jsx:159-223`) | Nada — Non-Goal do charter (❌ Auto-fix · ❌ Suprimir alertas · ❌ Botão "ignorar drift") |

## Recibos de ausência
- `grep -nEi 'Deferred|defer' resources/js/Pages/governance/DriftAlerts.tsx` → 0
- `grep -nEi 'yaml|ilegível|parse' resources/js/Pages/governance/DriftAlerts.tsx` → 0
- `grep -nEi 'Suprimir|ignorar|snooze' resources/js/Pages/governance/DriftAlerts.tsx` → 0
- `sed -n 159,223p prototipo-ui/cowork/governance-telas.jsx ／ grep -cEi 'Abrir|github|href'` → 0 (ausência no mockup, sustenta o "vivo à frente" dos links)
