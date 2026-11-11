---
id: resources-js-pages-financeiro-assinatura-atualizar-casos
casos: Atualizar cobrança da assinatura · /financeiro/assinatura/atualizar
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/assinatura/atualizar

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Charter em **draft** (FIN-004 / US-FIN-063). Tela que mexe em **cobrança de cliente real** — HITL exigido.

## UC-ASS-01 — Ver assinaturas ativas antes de mexer
Status: 🧪 (`AssinaturaAtualizarGuardTest`)
Quando o operador abre a tela · Então lista as assinaturas ativas do business (status, valor atual, ciclo, forma, próximo vencimento) e o formulário só aparece após selecionar uma.

## [BACKLOG] Preview do impacto antes de salvar
Status: ⬜ sem prova — AssinaturaAtualizarGuardTest tem só 2 testes (A1 render, A2 guest) — não há prova do preview de diff. Vira `UC-ASS-02` quando existir teste citando o id (G-2).
Quando altera valor/ciclo/forma · Então vê o diff campo a campo antes de confirmar; nada é enviado por mudança de campo.

## UC-ASS-03 — Sem mudança real, nada é gravado
Status: 🧪 (`AssinaturaPermissaoPatchContratoTest`)
`PATCH` sem nenhum campo devolve 422. Com os mesmos valores responde `skipped: no_changes` e a linha não muda.

## UC-ASS-04 — Validação do contrato de campos
Status: 🧪 (`AssinaturaPermissaoPatchContratoTest`)
`valor` ≥ 0,01 · `ciclo` mensal|trimestral|semestral|anual · `forma_pagamento` boleto|pix|cartao, todos opcionais. Fora disso, 422 no campo e nada é gravado.

## UC-ASS-05 — Salvar não cobra, e o log não carrega o valor
Status: 🧪 (`AssinaturaPermissaoPatchContratoTest`)
Salvar altera o contrato da assinatura, não cria fatura, e o log `rb.subscription.atualizada` traz só ids e flags.

## UC-ASS-06 — A tela exige a permissão de editar assinatura
Status: 🧪 (`AssinaturaPermissaoPatchContratoTest`)
Sem `recurringbilling.subscriptions.manage`, 403. Com ela, lista só as assinaturas da empresa da sessão.

## UC-ASS-07 — O PATCH respeita permissão e empresa
Status: 🧪 (`AssinaturaPermissaoPatchContratoTest`)
Sem a permissão, 403 e nada muda. Com ela, grava valor e forma. Assinatura de outra empresa, 404 e nada muda.

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork. Tela Tier-dinheiro com 1 arquivo de teste pequeno (2 KB) — UC-ASS-02 e 05 são o débito visível.
- 2026-11-09 · lote 3.1 da auditoria: UC-ASS-03..07 com teste pelas rotas; o teto de 999.999,99 saiu do contrato porque o Request nunca o aplicou. UC-ASS-02 (preview) segue sem prova.

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
