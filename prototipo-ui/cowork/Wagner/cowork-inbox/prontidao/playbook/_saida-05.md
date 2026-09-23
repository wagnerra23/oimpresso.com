---
sessao: "05"
titulo: casos.md com UC · PaymentGateways (promover backlog)
executor: "[CL]"
base: 317e1b4ec33
---
# _saida 05

## Checklist
1. ✅ `Index.casos.md` tem 7 UC reconhecidos pela lib (`## UC-PGSET-01..07`, heading que casa `ucHeadRe()` de `scripts/lib/uc-regex.mjs`)
2. ✅ cada UC-id citado por teste: `tests/Feature/PaymentGateway/PaymentGatewaysSettingsContratoTest.php`
3. ✅ só o prefixo tocado (`Index.casos.md` + `tests/`); `.tsx` e `.charter.md` intocados
4. ✅ PARAR SE não acionado (ver abaixo)

## De onde vieram os UC

Charter (§Goals + §Automation Anti-hooks) cruzado com o controller real
`Modules/PaymentGateway/Http/Controllers/Settings/PaymentGatewaysController.php` —
`index` (`Inertia::render('Settings/PaymentGateways/Index')`, `defer` de `gateways`/`kpis`) e `toggle`.
Critério: só vira UC o que tem contrato nas duas fontes.

| UC | o quê | fonte no charter |
|---|---|---|
| UC-PGSET-01 | caminho feliz: abrir a tela e ver a credencial própria com estado | Goals "Tabela de credenciais" |
| UC-PGSET-02 `[T0]` | credencial do business 99 não aparece na lista do 98 | Anti-hook "não acessa outro business_id" |
| UC-PGSET-03 | toggle inverte `ativo` e persiste (ida e volta) | Goals "ConfirmToggleModal" + Hook `toggle` |
| UC-PGSET-04 `[T0]` | toggle cross-tenant = 404, registro alheio intocado | Anti-hook "não acessa outro business_id" |
| UC-PGSET-05 | payload da lista nunca carrega `config_json` | Anti-hook "não exibe config_json" |
| UC-PGSET-06 | KPIs contam só o business; `fail` = ativa e não-ok | Goals "3 KPIs" |
| UC-PGSET-07 | GET (render + reload deferido) não cria credencial nem cobrança | Anti-hooks "não dispara cobrança" / "não cria credencial no GET" |

**Prefixo `UC-PGSET-`, não `UC-PG-`:** o `CnabRetorno.casos.md` irmão também anuncia `UC-PG-NN`.
Mesmo id em dois donos faz a prova de um creditar o outro (§5 2026-09-04).

**Ficou no backlog (sem fonte dupla ou sem teste seguro):** health check on-demand (chama o
serviço que fala com o banco externo — teste não pode chamar API), ações de linha sem `onClick`,
wizard `store` (teste em `Modules/.../Tests`, fora do prefixo), warn PesaPal (só copy do controller),
`cobs_hoje` por valor.

## O teste

- Tenant **98** (ADR 0358), adversário **99**; skip declarado se a lane não semeou os tenants. Nunca biz=4.
- Cada caso negativo tem controle positivo ao lado (lista/payload não pode ficar verde por vácuo).
- KPI por **delta** discriminante (+2 ativos, +3 total, +1 fail), com ruído no 99.
- Segredo: `config_json` de fixture é um marcador aleatório inerte; nenhuma credencial real.
- Matcher variádico sem mensagem: mensagens só em `assertContains`/`assertStringNotContainsString` (PHPUnit).
- `health_status` usa valores do enum (`ok`/`down`) — a migration não aceita `fail`.
- `php -l`: **não rodado** — `php` não está no PATH deste ambiente. Pest/PHPStan: não rodados (CT 100).

## Lane de CI (medido)

**Nenhuma lane de PR executa o arquivo.** `node scripts/governance/test-lane-coverage.mjs --json`
dá o módulo PaymentGateway com 45 de 48 arquivos órfãos, e `.github/ci-sqlite-pest.list` não lista
`tests/Feature/PaymentGateway/`. O arquivo roda no **nightly CT 100** (`phpunit.xml` inclui
`./tests/Feature` recursivo; `scripts/tests/shards-plan.mjs` varre `tests`). Não mexi em `.github`.
Por isso os 7 UC estão `🧪 sem veredito`.

## Gates

`node scripts/casos-coverage-guard.mjs`:
```
casos:check · 74 violações (telas: 220, casos.md: 158)
✅ Sem violações novas DESTE PR (débito caiu −9 vs baseline).
```
(nenhuma violação cita `PaymentGateways/Index` nem `UC-PGSET`)

`node scripts/qa/prototipo-readiness.mjs`:
```
✅ PRONTAS pra aplicar HOJE: 60   (inclui [PaymentGateway] Settings/PaymentGateways/Index)
🟡 PRECISAM DE 1 CICLO: 34
     casos.md-com-UC ainda faltando: Essentials/Documents · Essentials/Knowledge ·
       Essentials/Messages · Essentials/Reminders · Essentials/Todo · Sells/Caixa
     scorecard faltando: Arquivos · Backup · Essentials/Metas · Essentials/Tipos ·
       Jana/Acoes · Jana/Alertas · Jana/Plataforma · kb/Index.v2 · Manufacturing (4) ·
       Patrimonio (5) · Sells/CreateV3 · User/Perfil · Forja (3) · Officeimpresso (2) ·
       superadmin (4)
Total de telas com protótipo real: 94
```

## PARAR SE
- Charter × `.tsx`: **não acionado.** O que o charter promete e o `.tsx` não entrega (botões de linha
  `RefreshCw`/`MoreHorizontal` sem `onClick`) já estava declarado no backlog do casos.md; os comportamentos
  promovidos existem no `.tsx` (toggle com `ConfirmToggleModal`, "Testar todos", lista, KPIs).
- 300 linhas: não acionado (1 tela).
