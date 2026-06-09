# E2E (Playwright) — Gate G-3 da Governança executável (ADR 0264)

Testes de **comportamento em navegador real** dos UCs críticos. Locators **resilientes**
(`getByRole`/`getByLabel`/`getByText` — nunca classe CSS, lição L-24). Fonte do contrato:
os `*.casos.md` ao lado de cada tela.

## Estado

- ✅ Harness pronto: `playwright.config.ts` + `e2e/global-setup.ts` (login → storageState) +
  `e2e/oficina-uc06-gate-etapa.spec.ts` (UC-01/02/03/06 da Oficina).
- ⏳ **Primeiro run verde PENDENTE** de validação no stack do app — o agente desktop não tem
  PHP/serve. Por isso o workflow `e2e-gate.yml` é **`workflow_dispatch` (manual) + NÃO-required**
  até ficar verde-estável (lição dura ADR 0261: gate flaky required trava todo merge).
- 🔜 Próximos specs: Vendas **UC-V05** (split fiscal NF-e/NFS-e) · Financeiro **UC-F02** (saldo).

## Rodar local

```bash
# 1. App servindo (Laravel + Inertia build) em http://127.0.0.1:8000, com biz=1 seedado
php artisan serve &
# 2. Instalar o browser (1ª vez)
npm run e2e:install
# 3. Rodar
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8000 E2E_USER=<user> E2E_PASS=<pass> npm run e2e:check
```

## Por que não-required ainda

O E2E precisa do app real (DB seedado + login). Promover a `required` antes de verde-estável
**travaria todos os merges** (ADR 0261). Fluxo: validar manualmente (`workflow_dispatch`) →
estabilizar → só então entrar no trilho de required, junto com os guards `casos`/`dominio`.

## Convenção

- 1 spec por tela crítica, nome cita o `UC-id` (fecha a rastreabilidade G-2 do `casos:check`).
- `test.skip(...)` explícito quando falta seed (NUNCA falso-verde).
- Viewport 1280 = monitor da Larissa (casos.md UC-09).
