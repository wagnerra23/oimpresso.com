---
id: requisitos-superadmin-briefing
module: Superadmin
status: producao
status_nota: "backoffice interno do oimpresso + telas de assinatura do cliente (/subscription/*)"
updated_at: "2026-11-16"
owner: W
related_adrs:
  - "0093-multi-tenant-isolation-tier-0"
  - "0170-onda5-simplificada"
  - "0399-aposentar-rubrica-module-grade-gate-e-baseline"
---

# BRIEFING — Modules/Superadmin

> **Atualizado:** 2026-11-16 (lote L6 da auditoria de 2026-11-05; a versão anterior era de 2026-08-01) | **Owner:** [W]
> Canon: [SPEC.md](SPEC.md) · [SCOPE.md](SCOPE.md) · [CHANGELOG](CHANGELOG.md) · [SUPERFICIE.md](SUPERFICIE.md) (inventário gerado de arquivos)

## O que é

Duas coisas no mesmo módulo:

1. **Backoffice da plataforma** (`/superadmin/*`, middleware `superadmin`): o operador do oimpresso cria negócios, gerencia pacotes (planos), aprova/vence/cancela assinaturas, envia comunicados cross-tenant, edita páginas do site público, configura settings globais e abre o raio-X de um usuário (Usuário 360).
2. **Telas de assinatura do cliente** (`/subscription/*`, permissão `superadmin.access_package_subscriptions`): Minha assinatura e Pagar. Essas são do tenant, não do operador.

O backoffice é **cross-tenant intencional** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) §exceções); cada bypass de escopo leva a marca `// SUPERADMIN: <razão>`.

## Telas (medido em 2026-11-16: 12 `Inertia::render` em `Modules/Superadmin/Http`)

| Tela | Rota | Desde |
|---|---|---|
| `superadmin/Dashboard/Index` | `/superadmin` | SA-O1 |
| `superadmin/Negocios/Index` + drawers Novo negócio e Adicionar assinatura | `/superadmin/business` | SA-O2; drawers #8714, #8733 (2026-10-05) |
| `superadmin/Pacotes/Index` + drawer criar/editar | `/superadmin/packages` | SA-O4c; drawer #8725 (2026-10-05) |
| `superadmin/Assinaturas/Index` (aprovar · vencer · cancelar · vigência) | `/superadmin/superadmin-subscription` | SA-O4a/b |
| `superadmin/Comunicador/Index` | `/superadmin/communicator` | #8342 |
| `superadmin/Configuracoes/Index` (segredos mascarados) | `/superadmin/settings` | #8361 |
| `superadmin/Paginas/Index` | `/superadmin/frontend-pages` | cutover #10028 (2026-10-28) |
| `superadmin/Usuario360/Index` e `Show` (grava log de acesso, #8364) | `/superadmin/usuarios` | — |
| `superadmin/MinhaAssinatura/Index` (cliente) | `/subscription` | — |
| `superadmin/Pagar/Index` (cliente, atrás da flag `useV2SuperadminPagar`) | `/subscription/{id}/pay` | #10267 |
| `Site/Pricing` (público) | `/pricing` | — |

Seguem em Blade: o pagamento sem a flag (ou com `?classico=1`), `/pricing/old`, a página pública `/page/{slug}` e os partials de `subscription/`. As 18 Blades sem renderizador foram apagadas no #10967 (2026-11-16).

## Cobrança e acesso — como o código faz hoje

Empresa nova → `BusinessAutoSubscriptionObserver` cria assinatura `waiting` com `trial_end_date` → cron `paymentgateway:emit-trial-expired` (08:00, só `live`) emite PIX Automático no trial vencido → `CobrancaPaga` → `OnCobrancaPagaUpdateSubscription` aprova e calcula a vigência → `ModuleUtil::hasThePermissionInSubscription` libera os módulos do `package_details`. Inadimplência: `CobrancaVencida` → `declined` + `officeimpresso_bloqueado`. Detalhe e buracos no SDD do fluxo (lote L6, PR 3).

**Pendências de decisão [W] (abertas em 2026-11-16, comportamento atual descrito, futuro não decidido):**
- **B1 — trial sem módulos:** `active_subscription()` só conta `approved` (`Subscription.php:111-125`); a assinatura `waiting` do trial não libera módulo por pacote. Inferido do código, não medido em runtime.
- **B2 — renovação:** `OnCobrancaPagaUpdateSubscription.php:65` ignora `CobrancaPaga` de assinatura já `approved`; nenhum cron emite a cobrança seguinte.
- **B3 — expiração:** nenhum processo automático grava `expired`; só a ação "vencer" da tela (`SubscriptionLifecycleService.php:97`). `findOverdueApproved()` não tem chamador fora dos testes.

## Gateways

A tela Pagar nova oferece **só PIX Automático e offline** (UC-SAPG-03). A Blade de pagamento ainda tem partials de PayPal, Stripe, Razorpay, PesaPal, Paystack, Flutterwave, offline e PIX Automático. A emissão de PIX é do `Modules/PaymentGateway` ([ADR 0170](../../decisions/0170-onda5-simplificada.md)). Segundo o docblock do ServiceProvider, em 2026-09-09 havia 0 cobranças `subscription_license` em prod; não remedido.

## Operação

- `superadmin:health` (`--detail`/`--notify`) alimenta o `jana:health-check`; o agendamento às 06:20 está no PR aberto #11022.
- `pos:sendSubscriptionExpiryAlert` diário.
- As 7 notificações vão para a fila `superadmin-mail`, criptografadas (lote L4, 2026-11-16).

## Testes

`node scripts/governance/test-lane-coverage.mjs --json` (2026-11-16): **28** arquivos no módulo, **15 fora de qualquer lane de PR** — entre eles todo o fluxo de cobrança (Observer, os dois listeners, `AssinaturaAtivaDeterministaTest`, `PackageManagerServiceTest`). O lote L1 trata disso. Os `*ContratoTest` das telas declaram `@covers-us` e estão citados no SPEC. Rodar Pest só no CT 100.

## Gaps reconhecidos

- ✅ (2026-11-16) Fechado: desativar e excluir empresa ou pacote saía por GET (um link bastava) — agora POST/DELETE com CSRF, `is_active` só 0/1 e as travas de `canDestroy` na exclusão (`SuperadminRotasDestrutivasContratoTest`). O que a exclusão apaga continua como antes (B5).
- ✅ (2026-11-16) Fechado: `confirm` aprovava pacote pago com gateway inventado e por GET sem CSRF — agora só gateway configurado, POST, travas de pacote privado/uso único (`SubscriptionConfirmContratoTest`, #10974)
- US-SUPER-006 (respeitar `requires` do `module.json`) e US-SUPER-007 (versão PHP/MySQL, disco, jobs com falha): parciais
- US-SUPER-011: o MRR entrou na SA-O1b (#5955, delegado ao RecurringBilling no #5981; `SuperadminDashboardService::calcularMrr`); funil trial→pago e receita por pacote seguem sem query. O comentário em `SuperadminController.php:41-43` ainda diz que o MRR não tem query (é anterior ao #5955)
- US-SUPER-012 (Primeiros passos): atrás de flag desligada
- 11 dos 12 charters em `status: draft`
- B1/B2/B3 acima; pendências humanas da ADR 0170 (credencial BCB, homologação, smoke) sem registro de conclusão

## Decisões relacionadas

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 (exceções superadmin)
- [ADR 0170](../../decisions/0170-onda5-simplificada.md) — cobrança da licença por PIX Automático
- [ADR 0399](../../decisions/0399-aposentar-rubrica-module-grade-gate-e-baseline.md) — aposentou a rubrica module-grade; o score "~85-90" que ficava aqui saiu por isso

## Próximo passo sugerido

Ondas da auditoria de 2026-11-05: L0 mergeado em 2026-11-16 (#10974 — ver Gaps); em curso L1 (testes em lane), L2 (cobrança), L3 (validação), L7 (medição das telas). B1/B2/B3 dependem de decisão [W].

---
**Atualizado:** 2026-11-16 pelo lote L6 PR 2 (auditoria Superadmin) · **Mantenedor:** Claude (skill `brief-update`) + [W]
