---
id: resources-js-pages-financeiro-contas-bancarias-index-casos
casos: Contas bancárias · /financeiro/contas-bancarias
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/contas-bancarias

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Tela `live` (CNAB + beneficiário). Credenciais de gateway **não** moram aqui (ADR 0170).

> ⚠️ **Âncora podre encontrada:** o charter cita `FinanceiroContasBancariasCharterTest` em "Métricas vivas", mas **não existe** arquivo com esse nome em `Modules/Financeiro/Tests/` (medido 2026-08-17). As provas reais são `ContaBancariaIndexTest` e `UpsertContaBancariaRequestTest`. Corrigir o charter no mesmo PR.

## UC-CTB-01 — Listar contas com status honesto
Status: 🧪 (`tests/Feature/Modules/Financeiro/ContaBancariaIndexTest.php`)
Quando abre a tela · Então cada conta mostra beneficiário, carteira e um StatusBadge que diz a verdade: "Faltam dados" · "Inativo" · "Ativo · Cart. X".

## UC-CTB-02 — Configurar boleto valida os dados do beneficiário
Status: 🧪 (`UpsertContaBancariaRequestTest`)
Quando salva o `ConfigurarBoletoSheet` · Então banco/agência/carteira/beneficiário são validados (CEP, UF) e o upsert grava sem emitir nada.

## [BACKLOG] Credencial de gateway nunca é persistida aqui
Status: ⬜ sem prova — nenhum teste posta campo gateway_* pra provar o descarte. Vira `UC-CTB-03` quando existir teste citando o id (G-2).
Um POST com campos de credencial ainda salva os dados bancários e **descarta** a credencial (o cofre é `payment_gateway_credentials`).

## UC-CTB-04 — Tier 0
Status: 🧪 (`MultiTenantIsolationTest`)
Conta de outro business não aparece nem é editável ([ADR 0093]).

## Cadastro da conta (tela única de contas — decisão [W] 2026-10-27)

Desde 2026-10-27 esta tela também cadastra a conta do núcleo (`accounts`), antes feita em `/account/account`. O saldo
e o saldo de abertura saem do mesmo serviço do núcleo (`App\Services\Contas\ContasDoNucleo`). Prova:
`tests/Feature/Configuracoes/ContasNoFinanceiroContratoTest.php`.

## UC-CTB-05 — Lista só da empresa, com o saldo do núcleo
Status: 🧪 · Dado contas em duas empresas e lançamentos (um apagado) · Quando abre a tela · Então só aparecem as da
empresa da sessão e o saldo é crédito − débito sem o apagado, igual à conta à mão (dois caminhos).

## UC-CTB-06 — Nova conta com saldo de abertura
Status: 🧪 · Quando salva "Nova conta" com saldo de abertura "1.234,56" · Então a conta nasce na empresa da sessão
(o `business_id` do formulário é ignorado) e um crédito `opening_balance` de 1234,56 é gravado — o mesmo valor que o
`num_uf` do núcleo dá para o texto (dois caminhos).

## UC-CTB-07 — Sem saldo de abertura, sem lançamento
Status: 🧪 · Quando salva sem saldo de abertura · Então a conta não ganha lançamento.

## UC-CTB-08 — Tipo de conta de outra empresa é recusado
Status: 🧪 · Quando o formulário traz um tipo de conta de outra empresa · Então dá erro no campo e nada é gravado.

## UC-CTB-09 — Editar não toca no saldo
Status: 🧪 · Quando edita nome e observação · Então o cadastro muda e o saldo continua o mesmo; conta de outra
empresa responde 404 e fica intacta.

## UC-CTB-10 — Encerrar e reativar
Status: 🧪 · Quando encerra · Então a conta sai das ativas e aparece em "encerrada"; reativar devolve. Conta de outra
empresa responde 404.

## UC-CTB-11 — Sem permissão, não cria
Status: 🧪 · Dado usuário sem `financeiro.contas_bancarias.manage` · Quando tenta criar · Então 403 e nada gravado.

## UC-CTB-14 — Tipos de conta na empresa da sessão
Status: 🧪 (`tests/Feature/Configuracoes/TiposContaContratoTest.php`) · Quando cria tipo ou subtipo no painel "Tipos de
conta" · Então ele nasce na empresa da sessão; um tipo pai de outra empresa é recusado e nada é gravado.

## UC-CTB-15 — Tipo de outra empresa é intocável
Status: 🧪 · Quando tenta editar (404) ou remover um tipo de outra empresa · Então ele continua igual.

## UC-CTB-16 — Remover tipo solta os subtipos
Status: 🧪 · Quando remove um tipo · Então os subtipos dele ficam sem pai; um tipo não pode ser pai de si mesmo.

## UC-CTB-17 — Modais Blade de tipo saíram
Status: 🧪 · `/account-types/create` e `/{id}/edit` não abrem mais tela; o cadastro é o painel desta tela.

## Backlog de casos (sem id)
- **[BACKLOG] Sem side-effect ao abrir** — não testa conectividade de gateway, não emite boleto, não chama driver Inter/Asaas/C6.
- **[BACKLOG] Pill "Conta indefinida" (US-FIN-038 PR2/3)** — herdar da Unificada quando a baixa não tem conta vinculada.

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork; achada a âncora podre do charter.

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
