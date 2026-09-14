---
id: governance-cowork-index-charter
page: /governance (painel · políticas · auditoria · drift · notas dos módulos)
component: prototipo-ui/cowork/governance/governance-page.jsx
status: draft
owner: wagner
last_validated: "2026-08-23"
parent_module: Governance
related_adrs: [0079, 0084, 0086, 0094, 0110, 0147, 0153, 0154, 0155, 0264, 0275, 0286, 0366]
tier: A
charter_version: 1
---

# Page Charter — módulo Governança (F1 Cowork)

> **[CC]** F1 visual, 2026-08-23. Uma tela do app único com cinco vistas — `governance` · `gov-politicas` · `gov-auditoria` · `gov-drift` · `gov-notas`. Nenhum `.html` novo.
>
> **Lido no `main` NESTE turno** (tree `dab8e0e65484`): `Modules/Governance/Http/routes.php`, `Resources/menus/topnav.php`, `Http/Controllers/{Dashboard,Policies,Audit,DriftAlerts}Controller.php`, `resources/js/Pages/governance/{Dashboard,Policies,Audit,DriftAlerts,ModuleGrades/Index}.charter.md`, `resources/js/Pages/governance/_shared/GovernancaSubNav.tsx`, `prototipo-ui/{FRESCOR-PRODUCAO-vs-PROTOTIPO,PRE-FLIGHT-TELA}.md`.
> ⚠️ Não commitado — as tools de GitHub aqui são read-only. Ponte = cola zero-toque ou Issue `cowork-intake`.
>
> **Frescor:** as cinco telas **já existem vivas** em `resources/js/Pages/governance/` com charter próprio. Este F1 **não as repinta**: reconstitui as mesmas seções no idioma do Cowork para (a) dar ao módulo uma vista única navegável no protótipo e (b) fechar os três buracos que os charters vivos declaram como lacuna honesta. Onde o vivo manda, este charter **cita** o charter vivo em vez de redecidir.

---

## Mission

Uma tela onde [W] responde, em ~5 min/dia, **"a regra está sendo cumprida?"** — a pergunta do módulo ([ADR 0366](../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md) §D-A). Cinco vistas, uma pergunta cada:

| Vista | Rota do vivo | Pergunta |
|---|---|---|
| Painel | `/governance/dashboard` | O que mudou desde ontem e o que espera decisão? |
| Políticas | `/governance/policies` | Quais regras estão valendo agora? |
| Auditoria | `/governance/audit` | Quem chamou o quê, quando, com que resultado? |
| Drift | `/governance/drift` | Onde o código andou sem o `SCOPE.md` andar junto? |
| Notas dos módulos | `/governance/module-grades` | Qual a maturidade de cada módulo? |

---

## Regras (R)

**R1 · A raiz não é a tela.** `/governance` responde `redirect('/ia', 302)` desde 2026-05-22. A tela de painel vive em `/governance/dashboard` (`governance.admin.dashboard.legacy`). O F1 mostra o endereço real no rodapé — nenhum link do protótipo pode sugerir que a raiz abre painel.

**R2 · Auditoria é somente leitura, e a tela declara isso.** `mcp_audit_log` é append-only por trigger MySQL ([ADR 0084](../../memory/decisions/0084-triggers-mysql-imutabilidade-mcp-audit-log.md)). Nenhum controle da vista escreve; um selo permanente diz "registro imutável — alterar uma linha é incidente P0".

**R3 · Teto de 200 linhas visível.** A consulta de auditoria tem limite duro de 200 entradas. O rodapé mostra o teto e manda refinar filtro; esconder o teto faria [W] concluir coisa errada de uma amostra.

**R4 · Período de auditoria não passa de 30 dias.** Presets `1h` · `24h` · `7d` · `30d`. Não existe "tudo".

**R5 · Alternar política é ação direta, com desfazer, sem modal.** Toggle é reversível: aplica na hora e confirma por aviso fugaz. Modal de confirmação aqui é atrito — proibido pelo charter vivo.

**R6 · Política desligada continua visível.** Ordenação: ativas primeiro, depois categoria, depois chave. Nunca esconder as desligadas — é delas que [W] precisa para reativar.

**R7 · Drift não tem "ignorar".** Cada divergência exige remediação explícita: declarar em `contains[]`, mover o controller, ou registrar em `drift_alerts[]`. Sem soneca, sem auto-fix — a tela não edita `SCOPE.md`.

**R8 · Notas dos módulos são leitura.** Nem editar peso de rubrica (isso é ADR 0154 append-only), nem disparar avaliação, nem guardar filtro no navegador.

**R9 · Multi-tenant declarado, não inventado.** As `mcp_*` são **cross-tenant por design** — exceção formal ao Tier 0 (Constituição Art. 6+8, coberta por `CrossTenantPolicyTest`). O F1 marca a tela como `superadmin · cross-tenant` e **não** finge filtro por negócio onde a coluna `business_id` não existe.

**R10 · Degradação graciosa é estado de tela, não erro.** Tabela ausente (`failed_jobs`, `jana_mensagens`, `jana_health_narratives`, `mcp_sdd_scorecard_history`, `mcp_audit_log`) → KPI em travessão ou vazio com motivo. Nunca tela quebrada, nunca zero mentiroso.

**R11 · O que é caro chega depois.** No vivo, `sdd` e `mcp` são `Inertia::defer`; notas dos módulos idem (o serviço faz I/O de filesystem em 34 módulos). O F1 representa isso com esqueleto de carregamento, não com número instantâneo.

**R12 · A seção MCP tem dono de permissão próprio.** Ela herda `jana.mcp.usage.all` — **não** `governance.dashboard.view`. Somar as duas seria mais restritivo que ontem e esconderia a seção de quem a via.

**R13 · Cor só por token.** `bg-red-100`, `emerald-500`, `amber-400` crus são violação medida (`ds/no-raw-palette-color`). Tom semântico do DS ou nada.

---

## Achados da leitura do `main` (A)

**A1 — a strip do módulo é nova e frágil.** `GovernancaSubNav` só renderiza se `shell.menu` trouxer a entry da Governança; a entry de sidebar está desligada desde 2026-05-25 e as telas eram alcançadas pelo ghost do hub Jana. Ou seja: hoje, dependendo do papel, o módulo **não tem navegação própria visível**. O F1 dá a strip como parte da tela.

**A2 — conformidade é constante, não medida.** `DashboardController` calcula `compliancePct` com uma soma literal `(7*10) + (2*5) + 0 = 80`. O número é um juízo escrito à mão, não uma apuração. O F1 rotula o KPI como *auto-declarado* e mostra a régua (7 artigos plenos · 2 parciais · 1 pendente) — número sem origem em painel de governança é o pior lugar possível para um número sem origem.

**A3 — o gate da seção MCP não tem teste.** Declarado como lacuna honesta no charter vivo do painel: nada cobre com/sem `jana.mcp.usage.all` nem a whitelist de `mcp_preset`. Entra como pedido pro [CL], não como cobertura fingida.

**A4 — alternar política não deixa rastro.** `mcp_governance_rule_history` está prometido para a "Fase 5+1" e não existe. Enquanto isso, a auditoria fica cega justamente para a mudança que altera enforcement em runtime. O F1 mostra o aviso na própria vista de Políticas — quem desliga precisa saber que ninguém vai lembrar disso depois.

**A5 — histórico de drift não é alimentado.** O cron de detecção (Enforcement #5) não roda, e `mcp_alertas` não aceita a categoria `module_drift` (enum sem o valor; exigiria migração + ADR). O card de histórico nasce vazio **com motivo escrito**, não com "nenhum resultado".

**A6 — o scan de drift é síncrono.** ~30 módulos + parse de YAML no primeiro render, alvo p95 de 1,2 s. É o candidato número 1 a defer da tela; enquanto não for, o F1 mostra o custo com esqueleto.

**A7 — a rubrica v3 tem colunas que nem todo módulo tem.** D6–D9 (perf, LGPD, segurança, observabilidade) mostram travessão quando o módulo ainda não foi avaliado na v3. Travessão ≠ zero: a tela precisa distinguir os dois, senão a média mente.

---

## Non-Goals — o que estas telas NÃO fazem

- ❌ Editar, apagar ou corrigir entrada de auditoria (append-only irrevogável — Art. 9).
- ❌ Criar política pela interface (MVP: só alternar; editor de JSON é iteração à parte).
- ❌ Alternar várias políticas de uma vez — uma por vez força reflexão.
- ❌ Auto-corrigir `SCOPE.md`, ou dar botão de silenciar drift.
- ❌ Editar peso de rubrica ou disparar avaliação de módulo.
- ❌ Exportar CSV/PDF de LGPD Art. 18 (declarado como próxima iteração no vivo).
- ❌ Acompanhamento em tempo real (sem WebSocket; atualização é manual).
- ❌ Absorver **Custos de IA** e **Qualidade IA** — existem no vivo sob `/governance`, mas com permissões `jana.*` próprias; entram em leva à parte, com leitura dos dois controllers.
- ❌ Mover `GovernancaService` ou as `Mcp*` para `Modules/Governance` — [ADR 0366](../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md) §D-C item 4 **não está autorizada**; o destino declarado delas é o Forja.
- ❌ Renomear `jana.mcp.usage.all` — rename de permissão revoga acesso em silêncio (ADR 0087): pede ADR + migração próprios.

---

## Anti-hooks de automação

- ⛔ **Consumir a prop `mcp` fora do `<Deferred>`** — é o incidente literal de 2026-05-25 (`TypeError undefined.find` em produção). Quem desestruturar direto reabre o bug.
- ⛔ **Inventar `business_id` nas `mcp_*`** — a coluna não existe, por design.
- ⛔ **Duplicar a lista de ghosts na tela** — a fonte é `DataController::modifyAdminMenu`; tela nova entra lá e aparece de graça.
- ⛔ **Publicar a conformidade como métrica apurada** enquanto for a soma literal do controller (A2).
- ⛔ **Prometer histórico de alternância de política** antes de `mcp_governance_rule_history` existir (A4).
- ⛔ **`href` no `KpiCard`** — a prop não existe; os dois usos atuais estão no baseline do tsc e não podem ganhar irmãos.
- ⛔ `role="tablist"` à mão, `<div className="flex …">` solto, `sessionStorage`, `localStorage` de filtro.

---

## Alvos de UX

- Primeira pintura das props leves < 800 ms; painel, políticas e auditoria abaixo disso. Drift assume 1,2 s (A6).
- Zero erro de console.
- Densidade de balcão: tabela com algarismos tabulares, linha de auditoria em 32 px.
- Cor: apenas tom semântico do DS (`success` · `warning` · `danger` · `info`) sobre o roxo `oklch(0.55 0.15 295)`.
- Trocar filtro de auditoria não recarrega o resto da tela.

---

## Anti-padrões de UX

- ❌ KPI montado com `Card` à mão — canon é `KpiCard` do DS.
- ❌ `h1` inline — canon é o cabeçalho de página do shell.
- ❌ Selo com cor crua sem tom.
- ❌ Vazio genérico ("nenhum resultado") onde existe motivo (A5, R10).
- ❌ Esconder política desligada, drift resolvido ou linha de auditoria com erro.
- ❌ Inglês em texto cliente-facing. Sem emoji.
- ❌ Modal em tela cheia para detalhe (PT-04 é só confirmação).

---

## Testes anti-regressão pedidos ao [CL]

- `GovernanceMcpSectionGateTest` — com e sem `jana.mcp.usage.all`; whitelist de `mcp_preset` (fecha A3).
- `AuditAppendOnlyTest` — trigger bloqueia UPDATE/DELETE (existe; citar no contrato).
- `PoliciesToggleTest` — alternar grava `enabled` + `updated_at`.
- `DriftDetectionTest` — módulo de fixture com controller não declarado.
- `ModuleGradeServiceV3SubDimensionsTest` — travessão ≠ zero em D6–D9 (fecha A7).

---

## Refs

- [ADR 0079](../../memory/decisions/0079-constituicao-oimpresso-7-camadas-governanca.md) Art. 7 · 8 · 9 — Module Charter, Policy Gating, Auditoria
- [ADR 0084](../../memory/decisions/0084-triggers-mysql-imutabilidade-mcp-audit-log.md) — imutabilidade do `mcp_audit_log`
- [ADR 0086](../../memory/decisions/0086-fase-5-mvp-governance-actiongate-warn.md) — Governance Fase 5 MVP (ActionGate em aviso)
- [ADR 0155](../../memory/decisions/0155-module-grade-v3.md) — rubrica das notas de módulo
- [ADR 0264](../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) — trio de tela, caso↔teste
- [ADR 0286](../../memory/decisions/0286-contrato-de-tela.md) — Contrato de Tela
- [ADR 0366](../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md) — fronteira Jana/Forja/Governance/KB
- Charters vivos: `resources/js/Pages/governance/{Dashboard,Policies,Audit,DriftAlerts}.charter.md` + `ModuleGrades/Index.charter.md`
