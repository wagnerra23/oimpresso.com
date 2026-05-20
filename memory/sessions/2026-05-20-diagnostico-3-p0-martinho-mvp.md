# Diagnóstico — 3 P0 OficinaAuto em review (MVP Martinho)

> Investigação 2026-05-20 · pra fechar MVP cycle CYCLE-06 (Martinho pagando)
> Status MCP review · sem PR aberto · risco de drift status vs main

## Sumário executivo

| US | Estado real | Ação recomendada |
|---|---|---|
| US-OFICINA-005 | **Mergeado em prod** (PR #960, 2026-05-16) — porém SPEC pede 3 sub-features UI + Comandos artisan entregues cobrem só parcial | Reavaliar `done` × `partial`: marcar `done` se MVP-Martinho for "Commands CLI" OU criar US-OFICINA-022 (já existe linha 468 SPEC) pra sub-features (a)(b)(c) UI completas |
| US-OFICINA-006 | **Mergeado em prod** (PRs #723 #728, 2026-05-13) — MVP Martinho 2 processos caçamba (locacao/manutencao); seeder amplo 15×19 do SPEC §14 NÃO implementado (intencional, é Vargas/futuro) | Marcar `done` (subset Martinho entregue); criar US-OFICINA-006B "FSM seeder amplo 15 stages × 19 actions (Vargas + oficina pesada)" se Wagner quiser cobrir SPEC §14 antes de Vargas |
| US-OFICINA-014 | **Mergeado em prod** (PR #960, 2026-05-16) — Controller público + Service + Pages Inertia + 2 testes Pest entregues. Detalhe: usa rota `/aprovar-os/{token}` (SPEC pede `/oficina/aprovar/{token}` — não-blocker, só path) e atualiza `status` direto (NÃO via FSM action `cliente_aprovou`) | Marcar `done`; criar TASK pequena "wire-up `cliente_aprovou` no FSM" se Wagner quiser integração full (atual funciona pra Martinho, FSM seeder caçamba NÃO tem stage orçamento → action não aplica nesse fluxo) |

---

## US-OFICINA-006 — FSM wire-up ServiceOrder

- **Status MCP:** review · unowned · estimate 6h · blocked_by US-OFICINA-001 (done)
- **Status real:** **MERGEADO** em main desde 2026-05-13 (commits `5a4513a29` PR #723 + `fa1d85935` PR #728)

### Evidências em main (D:/oimpresso.com)

| Critério SPEC §US-OFICINA-006 | Entregue? | Arquivo |
|---|---|---|
| Adicionar `current_stage_id` em `service_orders` migration | SIM | `Modules/OficinaAuto/Database/Migrations/2026_05_13_010001_add_current_stage_id_to_service_orders.php` (PR #728) |
| Seeder FSM oficina (15 stages × 19 actions × roles) | **PARCIAL** — entregue 2 processos curtos (`cacamba_locacao` 4 stages + `cacamba_manutencao` 4 stages) suficientes pro MVP Martinho | `Modules/OficinaAuto/Database/Seeders/OficinaAutoFsmSeeder.php` (PR #723) |
| `ServiceOrder` Model adota trait `GuardsFsmTransitions` | **NÃO** — model não importa o trait. Controller usa `ExecuteStageActionService` diretamente; defensivo p/ `current_stage_id` null | `Modules/OficinaAuto/Entities/ServiceOrder.php` — sem `use GuardsFsmTransitions` |
| `OficinaAutoFsmActionController` espelhando RepairFsmActionController | SIM (nome final: `ServiceOrderFsmActionController` em `app/Http/Controllers/`) | `app/Http/Controllers/ServiceOrderFsmActionController.php` (3 endpoints: actions/execute/start-pipeline) |
| UI drawer `FsmActionPanel` reuso de Repair | SIM (Wave 7-B via PR #729 `claude/oficinaauto-drawer-fsm-ui`) | `resources/js/Pages/OficinaAuto/...` (drawer + FsmActionPanel — PR #729 MERGED 2026-05-13) |
| Pest: 15 transition tests biz=1 + cross-tenant guard | SIM (9 specs no Controller test + `FsmTransitionTest.php` + multi-tenant `VehicleMultiTenantTest.php`) | `tests/Feature/Modules/OficinaAuto/ServiceOrderFsmActionControllerTest.php` (435 linhas, 9+ specs) + `Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php` |
| Rotas wiradas | SIM | `Modules/OficinaAuto/Routes/web.php` linhas 87-101 (3 endpoints `service-orders/{order}/fsm/*`) |
| Side-effects caçamba (7 classes) | SIM | `app/Domain/Fsm/SideEffects/{IniciarLocacaoCacamba,RecolherCacamba,EnviarCacambaManutencao,ConcluirServicoCacamba,VoltarCacambaDisponivel,IniciarServicoCacamba,CancelarServicoCacamba}.php` (PR #726) |

### Gaps reconhecidos (não bloqueiam Martinho)

1. **Trait `GuardsFsmTransitions` ausente no model `ServiceOrder`** — Sells/Repair canon do ADR 0143 usa o trait pra prevenir UPDATE direto em `current_stage_id`. OficinaAuto omite. Mitigação atual: `ServiceOrderFsmActionController::execute` é o único caminho que muda stage (não há UI Edit que altere). Risco: pequeno — qualquer dev futuro pode fazer `$so->update(['current_stage_id' => X])` e bypass auditoria. **Recomendação:** criar TASK follow-up de 1h "adicionar trait + Observer guard ServiceOrder".

2. **Seeder amplo SPEC §14 (15 stages × 19 actions) NÃO implementado** — o que está em main cobre só caçambas (Martinho); Vargas/multi-defeito/teste-estrada/garantia ficam pra US-OFICINA-007 + 010 (já como P1 todo no SPEC). **Decisão correta pro MVP** — não-blocker.

3. **Comentário enganoso em `AprovacaoOsService.php`** linha 38 `@see US-OFICINA-006` deveria ser `US-OFICINA-014` (US-006 é o FSM, US-014 é a aprovação). Cosmético.

### Ação recomendada

**Marcar US-OFICINA-006 = done.** Critério "FSM ServiceOrder espelhando Sells/Repair" foi entregue na essência (controller + endpoints + seeder + 7 side-effects + Pest + UI drawer). Criar opcionalmente:
- TASK pequena "ServiceOrder usa trait GuardsFsmTransitions" (1h) — fechar gap.
- TASK média "Seeder FSM oficina amplo 15×19 (Vargas)" (4h) — só se Vargas virar piloto qualificado.

---

## US-OFICINA-014 — Aprovação OS via WhatsApp PIN

- **Status MCP:** review · unowned · estimate 7h · blocked_by US-OFICINA-006 (que vimos é done)
- **Status real:** **MERGEADO** em main em 2026-05-16 via PR #960 commit `820a10123`

### Evidências em main

| Critério SPEC §US-OFICINA-014 | Entregue? | Arquivo |
|---|---|---|
| Endpoint público `/oficina/aprovar/{token}` | **PARCIAL** — entregue `/aprovar-os/{token}` (path diferente do SPEC, mas funcionalmente equivalente) | `Modules/OficinaAuto/Routes/web.php` linhas 116-121 |
| Page Inertia mobile-first | SIM | `resources/js/Pages/OficinaAuto/AprovacaoPublica.tsx` (225 linhas) + charter |
| PIN 4 dígitos via SMS/WhatsApp | SIM (geração no Service; envio fica pro Job WhatsApp, ainda não wirado) | `AprovacaoOsService::gerarTokenAprovacao` — retorna `['token','pin','expires_at']` |
| Token HMAC + business_id assinado (multi-tenant Tier 0) | SIM | `AprovacaoOsService::sign/validarToken` — payload `{os_id, business_id, exp_ts}` |
| Rate-limit anti-bruteforce | SIM | route throttle:30,1/IP + service lockout 5 tentativas → 30min |
| Webhook dispara FSM action `cliente_aprovou`/`cliente_rejeitou` com role `public.token` | **NÃO** — Controller V0 atualiza `$os->status = 'aprovada'`/'rejeitada' direto (sem passar pelo ExecuteStageActionService). Comentário linha 134: `"V0: update direto no status (não usa FsmExecuteStageActionService porque..."` | `Modules/OficinaAuto/Http/Controllers/Public/AprovacaoOsController.php` linha 134 |
| LGPD consentimento | SIM no charter `AprovacaoPublica.charter.md` + `LgpdComplianceTest.php` cobre fluxo | `Modules/OficinaAuto/Tests/Feature/LgpdComplianceTest.php` |
| Pest tests | SIM (2 testes dedicados) | `Modules/OficinaAuto/Tests/Feature/AprovacaoOsTokenTest.php` + `WhatsAppAprovacaoPinTest.php` |

### Gaps reconhecidos

1. **Path da rota `/aprovar-os/{token}`** vs SPEC `/oficina/aprovar/{token}` — não-blocker, só inconsistência texto. Atualizar SPEC ou rota; rota atual já está em prod, mais simples atualizar SPEC.

2. **Update direto `status` em vez de FSM `cliente_aprovou` action** — V0 pragmático. Pra caçambas Martinho não faz diferença porque o FSM seeder caçamba **não tem stage `aguardando_aprovacao_cliente` nem action `cliente_aprovou`** (fluxo Martinho é disponivel→locada→recolhida; sem orçamento intermediário). Pro fluxo Vargas (recapagem multi-item orçamento → cliente_aprovou) seria necessário, mas Vargas é outra US (US-OFICINA-007). **Não bloqueia Martinho.**

3. **Job WhatsApp que dispara link+PIN** não consta no PR. O Service `gerarTokenAprovacao` retorna `pin/token`, mas o envio efetivo (template WhatsApp `aprovacao_orcamento_v1` no WhatsAppOficial driver) NÃO foi wirado. Pro MVP Martinho não importa (caçamba não precisa aprovação); pra Vargas seria gap.

### Ação recomendada

**Marcar US-OFICINA-014 = done** (subset MVP). Está completo nos artefatos públicos (Controller + Service + Page + Tests + LGPD). Os 3 gaps são **devidos** ao fato de Martinho não precisar do fluxo orçamento; pertencem ao roadmap Vargas. Criar TASK opcional:
- "Wire FSM action `cliente_aprovou` no AprovacaoOsController (substituir update direto)" — 2h — só se Vargas pegar.
- "Job WhatsApp dispara link+PIN no template `aprovacao_orcamento_v1`" — 3h — pré-req Vargas.

---

## US-OFICINA-005 — Cleanup tools cliente legacy migrado

- **Status MCP:** review · unowned · estimate 12h · blocked_by US-OFICINA-002 (importer Martinho)
- **Status real:** **MERGEADO em prod** (PR #960 2026-05-16) — porém entrega é **CLI Commands**, NÃO as 3 sub-features UI do SPEC

### Evidências em main

| Critério SPEC §US-OFICINA-005 | Entregue? | Arquivo |
|---|---|---|
| **(a) Tela "Revisão de pendências legadas"** — batch UI 200/dia × 23 dias (Baixar/Cancelar/Renegociar/Write-off) | **NÃO** — não há Page `LegacyPendenciasReview.tsx` nem Controller equivalente | — |
| **(b) Conciliação VENDA↔FINANCEIRO** — detector 374 vendas 12m sem lançamento (R$ 1,64M drift) | **PARCIAL** — `OficinaAutoSanityCheckCommand.php` (239 linhas) faz sanity check CLI; mas é diagnóstico, NÃO conciliação interativa | `Modules/OficinaAuto/Console/Commands/OficinaAutoSanityCheckCommand.php` |
| **(c) PESSOAS deduplicador** fuzzy match ~920 razões sociais órfãs | **NÃO** — não há `ContactDedupeCommand` nem UI similar | — |
| **Bônus entregue além do SPEC:** `OficinaAutoCleanupMigratedClientCommand` (vendas teste, OS órfãs, fixtures) | SIM (159 linhas + 156 linhas Pest) | `Modules/OficinaAuto/Console/Commands/OficinaAutoCleanupMigratedClientCommand.php` |
| **Bônus entregue:** `OficinaAutoMigrationReportCommand` (relatório pós-import) | SIM (227 linhas) | `Modules/OficinaAuto/Console/Commands/OficinaAutoMigrationReportCommand.php` |

### Discrepância grande

O PR #960 entrega **3 Commands CLI utilitários pós-migração** (cleanup teste/OS órfãs + sanity check + migration report) — fundamental pra Wagner rodar manualmente, mas **não é o que a US-005 descreve no SPEC** (3 sub-features ROI cliente: tela Revisão batch UI + Conciliação VENDA↔FINANCEIRO + PESSOAS deduplicador).

Observação: a linha 468 do SPEC tem **US-OFICINA-022** ("Cleanup tools cliente legacy migrado (continua US-OFICINA-005) — P0 já existe"). Indica que Wagner JÁ sabia que tinha drift e duplicou pra manter o backlog. Vou conferir definição precisa de 022.

### Ação recomendada

Wagner deve escolher entre 2 caminhos:

**Caminho A — pragmático MVP:** marcar `US-OFICINA-005 = done` (entrega das 3 Commands CLI cobre operação pós-import Martinho que **realmente precisa hoje**) + atualizar texto SPEC pra refletir realidade (renomear US pra "CLI tools pós-migração" e jogar as 3 sub-features UI pra US-OFICINA-022 ou nova US-OFICINA-023).

**Caminho B — purista SPEC:** voltar US-OFICINA-005 pra `todo`, manter estimate 12h, criar PR separado pra entregar (a)/(b)/(c) UI Inertia + Controllers. Quando entregar marcar done.

Recomendo **A** — alinha com prática do projeto (US-OFICINA-022 já documenta a continuação como item separado) e desbloqueia fechamento CYCLE-06.

---

## Próximo passo Wagner

1. Rodar `php artisan oficina:sanity-check 1 --detail` e `oficina:migration-report 1` no Hostinger pra validar Commands na real (Martinho biz=1).
2. Decidir Caminho A vs B em US-OFICINA-005 (sugiro A).
3. Aprovar 3 transições MCP `tasks-update`:
   - `US-OFICINA-006`: review → done (PRs #723+#728+#726+#729 entregaram)
   - `US-OFICINA-014`: review → done (PR #960 entregou MVP)
   - `US-OFICINA-005`: review → done **se** Caminho A (atualizar texto SPEC junto), ou → todo **se** B.
4. Criar 2 TASKs follow-up opcionais (pré-Vargas, NÃO bloqueiam Martinho):
   - "ServiceOrder + trait GuardsFsmTransitions" (1h)
   - "AprovacaoOsController usa FSM action `cliente_aprovou` em vez de update direto" (2h)
   - Job WhatsApp dispara template `aprovacao_orcamento_v1` com link+PIN (3h)
