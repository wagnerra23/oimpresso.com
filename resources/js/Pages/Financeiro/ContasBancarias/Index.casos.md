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

## Backlog de casos (sem id)
- **[BACKLOG] Sem side-effect ao abrir** — não testa conectividade de gateway, não emite boleto, não chama driver Inter/Asaas/C6.
- **[BACKLOG] Pill "Conta indefinida" (US-FIN-038 PR2/3)** — herdar da Unificada quando a baixa não tem conta vinculada.

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork; achada a âncora podre do charter.

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
