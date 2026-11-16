# CHANGELOG — Modules/Superadmin

Append-only. Cada PR mergeado que toca `Modules/Superadmin/` deve adicionar 1 linha na entrada do Wave/data.

## 2026-11-16 — Lote 0 da auditoria (pagamento)

- `SubscriptionController::confirm` só cria assinatura com gateway configurado em `_payment_gateways()` que tenha `pay_<gateway>`; recusa pacote privado, de uso único já assinado e inexistente; rota passa a POST (PayPal/Pesapal voltam por `confirm/retorno`, GET que só aceita os dois); exceção vai ao log redigido, não à tela. Teste: `SubscriptionConfirmContratoTest` (UC-SACF-01..07).

## 2026-11-16 — Lote L4 da auditoria (fila e agendamento)

- As 7 notificações de `Notifications/` passam a ir para a fila própria `superadmin-mail`, com payload criptografado (`ShouldQueue` + `ShouldBeEncrypted`, trait `NaFilaDoSuperadmin`); o worker da fila é agendado no `registerScheduleCommands()` (só `live`). O Comunicador deixa de mandar um e-mail por dono dentro do request. Teste: `Tests/Feature/SuperadminNotificacoesNaFilaTest.php`.

## 2026-05-18 → 2026-11-16 — reconstituído do `git log` (registrado em 2026-11-16, lote L6)

O CHANGELOG ficou parado em 2026-05-17 apesar da regra "1 linha por PR". Linhas abaixo saem de `git log --since=2026-05-18 origin/main -- Modules/Superadmin` (70 commits; repo não-raso), só os `feat`/`fix`/`refactor`/`chore(superadmin)`. Data = commit date.

- 2026-06-13 · #2647 registra `superadmin:health` no ServiceProvider
- 2026-08-19 · #5945 enum de `subscriptions.status` aceita `expired`/`cancelled` · #5948 SA-O1: home vira Inertia (Dashboard) · #5955 SA-O1b: KPI de MRR + tendência · #5957 motivo do cancelamento persistido · #5965 SA-O2: Negócios sai do DataTables · #5982 SA-O2b: drawer de detalhe do negócio
- 2026-08-20 · #5981 MRR passa a vir do RecurringBilling · #6013 para de consultar tabela do RecurringBilling por query crua · #6027 contrato de tela do F1 vira teste · #6048 remove 2 rotas de negócio quebradas
- 2026-08-21 · #6064 SA-O4a: Assinaturas em Inertia · #6086 SA-O4b: ações por linha (aprovar/vencer/cancelar) · #6087 SA-O4c: Pacotes em grade Inertia · #6096 Assinaturas no menu
- 2026-08-26 · #6290 assinatura antiga deixava de apagar módulo habilitado depois dela · #6304 salvar pacote preserva chave fora do catálogo
- 2026-09-09 · #7074 comando agendado inexistente + schedule duplicado
- 2026-10-01 · #8342 Comunicador em Inertia · #8361 Configurações em Inertia com segredos mascarados · #8364 log de acesso do raio-X do Usuário 360 (LGPD Art. 7º) · #8398 teste do Usuário 360 sem flaky de ordem
- 2026-10-02 · #8504 contratos de tela do Comunicador e das Configurações
- 2026-10-05 · #8669 Minha assinatura em Inertia · #8700 Minha assinatura dava 500 · #8706 página do negócio vira drawer · #8714 Novo negócio vira drawer · #8725 pacote vira drawer, preço igual ao criar e editar · #8726 forms Blade de assinatura levam à lista · #8733 Adicionar assinatura no drawer do negócio
- 2026-10-09 · #9082 Páginas em Inertia atrás de `?tela=nova`
- 2026-10-27 · #10028 cutover de Páginas (MWART F5)
- 2026-11-03 · #10267 tela Pagar em React atrás da flag `useV2SuperadminPagar`
- 2026-11-16 · #10967 apaga 18 Blades sem renderizador · #10973 notificações na fila (ver entrada acima) · #10979 remove `PackagePolicy` morta e 2 `withoutGlobalScopes` sem efeito

## Wave 27 polish final — 2026-05-17 (target cross_cutting ≥92)

- `Tests/Feature/Wave27CrossTenantSaturationTest.php` (NOVO) — **50 cenários
  cross-tenant SATURADOS** organizados em 5 blocos:
  - Bloco A SuperadminDashboardService (12 cenários: smoke / cross-session /
    invariants / SUPERADMIN convention / D9 spans)
  - Bloco B BusinessAuditService (10 cenários: canDestroy Wagner-protect /
    self-destroy / aging summary / convention / spans)
  - Bloco C PackageManagerService (9 cenários: smoke / find defesa / catálogo
    cross-tenant / consistência listActive vs countActive)
  - Bloco D SubscriptionLifecycleService (10 cenários: lifecycle approve/expire/
    cancel rejeições + idempotência cross-state)
  - Bloco E Regression guards (9 cenários: signatures estáveis / no withoutGlobalScopes
    sem comment / PII-free attributes / PT-BR markers / meta-test count)
- `Http/Controllers/SuperadminController.php` — **D9 spans** legacy blade
  (`superadmin.legacy.index` + `superadmin.legacy.stats`) — paridade com
  SuperadminDashboardService extraído W18+W23. Cross-tenant intencional
  marcado com `// SUPERADMIN:` em stats() method (ADR 0093).
- Acumulado Pest cross-tenant Superadmin: W23 (14) + W25 (25) + W27 (**50**)
  = **89+ cenários** (target 50+ no W27 alcançado).
- Acumulado OTel spans Superadmin: 4 Services × 3-4 métodos
  (Dashboard 4 + BusinessAudit 3 + PackageManager 2 + SubscriptionLifecycle 3)
  = 12 spans + 2 W27 Controllers legacy = **14 spans canônicos**.

## Wave 18 RETRY — 2026-05-16 (governança meta-97)

- `module.json`: declarado `fsm_n_a: true` + razão — Superadmin é backoffice Wagner-only com Subscription status linear (vs Sells/Repair ADR 0143 multi-stage).
- `CHANGELOG.md` (este arquivo) + `README.md` criados — preencher D3 docs internas.
- `Services/PackageManagerService.php` extraído — encapsula listagem/cálculo de packages (D4 boost: extrair lógica `Package::listPackages` + `scopeActive` testável isolado).
- `Services/SubscriptionLifecycleService.php` extraído — encapsula status transitions (waiting_approval → approved → expired) com Spatie audit trail.
- `Tests/Feature/PackageManagerServiceTest.php` criado — cobre listagem + private/public filter.

## Wave 11 — 2026-05-15 (D7 LGPD audit + D5 na_justified)

- `Entities/Package.php`, `Subscription.php`, `SuperadminCommunicatorLog.php`: `LogsActivity` Spatie audit trail (D7.b LGPD — events fiscais append-only).
- SPEC.md: declarado `na_justified` D5/D4.c/D8.b — Superadmin é Wagner-only cross-tenant intencional, blade legacy preservado, sem CSRF except.

## Pré-Wave 11 (UltimatePOS herdado)

- 14 controllers + 6 form requests + entities Package/Subscription/Communicator herdados de UltimatePOS v6.

---

**Como atualizar:** entrada nova no TOPO da seção Wave ativa, NUNCA editar antigas. ⚠️ NUNCA editar `superadmin_communicator_logs` direto — append-only LGPD.
