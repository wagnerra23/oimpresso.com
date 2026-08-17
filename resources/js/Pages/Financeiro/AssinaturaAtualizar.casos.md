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

## [BACKLOG] Sem diff real, sem PATCH
Status: ⬜ sem prova — nenhum teste exercita PATCH com payload sem mudança. Vira `UC-ASS-03` quando existir teste citando o id (G-2).
O botão salvar só habilita com mudança real e o `PATCH` recusa payload vazio (sem patch cego).

## [BACKLOG] Validação do contrato de campos
Status: ⬜ sem prova — nenhum teste exercita UpdateAssinaturaRequest (valor/ciclo/forma_pagamento). Vira `UC-ASS-04` quando existir teste citando o id (G-2).
`valor` 0,01..999.999,99 · `ciclo` mensal|trimestral|semestral|anual · `forma_pagamento` boleto|pix|cartao; todos `sometimes`, ao menos um obrigatório.

## [BACKLOG] Nunca cobra agora, nunca loga valor/PII
Status: ⬜ sem prova — sem prova de não-cobrança nem de ausência de PII em log. Vira `UC-ASS-05` quando existir teste citando o id (G-2).
Salvar altera o contrato da assinatura; não emite fatura nem cobra no gateway, e nem Controller nem log imprimem valor real ou PII (biz=4 prod).

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork. Tela Tier-dinheiro com 1 arquivo de teste pequeno (2 KB) — UC-ASS-02 e 05 são o débito visível.

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
