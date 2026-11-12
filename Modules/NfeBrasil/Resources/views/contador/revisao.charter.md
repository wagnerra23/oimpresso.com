---
page: /nfe-brasil/contador/revisao/{business}/{link}
component: Modules/NfeBrasil/Resources/views/contador/revisao.blade.php
related_prototype: n/a (página pública em Blade, sem shell do app; não segue Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-11-11"
parent_module: NfeBrasil
related_adrs: [93]
related_us: [US-NFE-009]
tier: A
charter_version: 1
---

# Page Charter — página pública de revisão do contador

> **Status:** draft, criado em 2026-11-11 pela auditoria NfeBrasil (lote 5.3, achado N2-07). A página já existia
> (playbook Fiscal thread 15b, #9015) sem charter: o contrato dela vivia só em dois casos da tela de
> Tributação. Este charter **descreve o que o código faz hoje**; não é um pedido de mudança.
> Fica fora do glob dos gates de charter (`resources/js/Pages/**`), porque a tela é Blade.

## Mission

Deixar o contador da empresa, que não tem conta no oimpresso, revisar as regras fiscais pendentes e aceitar ou
pedir ajuste em cada uma, por um link que a empresa manda por e-mail.

## O contrato hoje (lido no código de `origin/main`, 2026-11-11)

- **Quem manda o link:** só usuário com `nfe.tributacao.manage`
  (`POST /nfe-brasil/tributacao/revisoes/link`). O link é assinado, vale **14 dias** e fica preso a **um**
  business: trocar o business ou o link na URL invalida a assinatura.
- **Sem login, com código:** abrir o link manda um código de **6 dígitos** para o e-mail cadastrado, válido por
  **15 minutos** e guardado só como hash. Depois de **5** erros o link é bloqueado e a empresa precisa mandar
  outro. Acertar o código regenera a sessão e libera o link só nela.
- **As ações exigem a sessão liberada**, não a assinatura: baixar o CSV, aceitar uma revisão, pedir ajuste.
  Link expirado ou bloqueado responde 403 com a mensagem "Peça um novo à empresa".
- **O que aparece:** só revisões `pendente` ou `ajuste_pedido` daquele business, com o de → para da regra.
  Pedir ajuste exige comentário (até 2.000 caracteres).
- **Throttle:** 30 requisições por minuto no grupo `/nfe-brasil/contador/*`.
- **Fora do pacote:** esta página e o DANFE por link assinado são as duas rotas de `/nfe-brasil/*` que ficaram
  sem o pacote `fiscal_module` de propósito (lote 3.3, #10732).

## Onde o contrato é testado

- `UC-NFTR-22` e `UC-NFTR-23` em
  [`Tributacao/Index.casos.md`](../../../../../resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md),
  pelo `Modules/NfeBrasil/Tests/Feature/RevisaoContadorLinkTest.php`.

## Non-Goals

_A preencher pelo [W]._ Non-Goals e Anti-hooks viram teste; não são inferidos do código.

## Automation Anti-hooks

_A preencher pelo [W]._

## Refs

- Controller: `Modules/NfeBrasil/Http/Controllers/RevisaoContadorLinkController.php`
- Rotas: `Modules/NfeBrasil/Routes/web.php` (grupo `nfe-brasil/contador`)
- [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
