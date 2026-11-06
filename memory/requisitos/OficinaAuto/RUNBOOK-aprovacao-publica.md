---
id: requisitos-oficina-auto-runbook-aprovacao-publica
title: "RUNBOOK — Aprovação pública da OS por link + PIN (OficinaAuto/AprovacaoPublica)"
module: OficinaAuto
tela: OficinaAuto/AprovacaoPublica
owner: W
status: ativo
last_validated: "2026-11-05"
related_adrs:
  - "0093-multi-tenant-isolation-tier-0"
  - "0171-oficinaauto-ativacao-piloto-martinho-faseada"
  - "0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada"
preconditions:
  - "OS em status orcamento com link gerado (AprovacaoOsService)"
steps:
  - "Ler o charter e o casos.md da tela antes de editar AprovacaoPublica.tsx"
  - "Rodar os testes de aprovação no CT 100 ou na lane oficinaauto-pest"
---

# RUNBOOK — Aprovação pública da OS (`/aprovar-os/{token}`)

> Escrito sobre `origin/main` de 2026-11-05 (`7d997591d7`) e atualizado em 2026-11-06 pelo
> PR 2.1 do lote 2 da auditoria (orçamento só depois do PIN, passo `conferir`).

## O que a tela faz

O cliente final, sem conta no sistema, abre o link recebido no WhatsApp e vê só o número da OS,
o veículo e as datas. Digita o PIN de 4 dígitos para abrir o orçamento (itens e total) e então
aprova ou rejeita (CDC art. 40: orçamento discriminado antes da aprovação). Charter: `AprovacaoPublica.charter.md`; casos:
`AprovacaoPublica.casos.md` (ao lado do `.tsx`).

## Peças

| Peça | Path |
|---|---|
| Tela (fora do AppShellV2) | `resources/js/Pages/OficinaAuto/AprovacaoPublica.tsx` |
| Controller público | `Modules/OficinaAuto/Http/Controllers/Public/AprovacaoOsController.php` |
| Token HMAC + PIN + lockout | `Modules/OficinaAuto/Services/AprovacaoOsService.php` |
| Envio do link | `Modules/OficinaAuto/Jobs/EnviarLinkAprovacaoWhatsappJob.php` |
| Rotas | `Modules/OficinaAuto/Routes/web.php` (grupo `web` + `throttle:30,1`, fora do prefixo `/oficina-auto`) |

## Contrato

| Verbo | Path | Comportamento |
|---|---|---|
| GET | `/aprovar-os/{token}` | token inválido ou expirado → renderiza a tela com `erro: link_invalido` e `os: null`; válido → `os` (sem `notes` e sem valor) + `orcamento` + `tentativasRestantes`. `orcamento` é `null` até o PIN ser conferido nesta sessão |
| POST | `/aprovar-os/{token}` | body `{pin?: "\d{4}", decisao: conferir\|aprovar\|rejeitar}`. `conferir` + PIN certo abre o orçamento na sessão por 30 min; com ele aberto, `aprovar`/`rejeitar` dispensam o PIN; sem ele, exigem o PIN no mesmo POST. PIN errado decrementa as tentativas; após `MAX_PIN_ATTEMPTS` (5) bloqueia por `PIN_LOCKOUT_SECONDS` (30 min) |

- **Orçamento:** itens da OS (tipo, descrição, quantidade, unitário, valor, sem `notes`) e
  `total` de `ServiceOrderItemService::recalcularTotal`, a mesma soma que a tela interna mostra em
  `total_items` e que o faturamento usa. Não é o total recomendado da DVI (decisão [W] 2026-11-05).
- **Sessão do orçamento:** chave `oficina_aprovacao_liberada.{business_id}_{os_id}`; conferir a OS
  de uma empresa não abre a de outra. Decidir apaga a chave.

- **Aprovar:** se a OS está em `status = orcamento`, grava `status = aprovada`,
  `approval_decision = approved` e `approval_decided_at`. Escreve a **coluna `status`**, não a etapa
  FSM — ver os dois donos do estado em [`RUNBOOK-fsm-pipeline.md`](RUNBOOK-fsm-pipeline.md).
  OS em outro status: sai sem mudar nada (idempotente).
- **Rejeitar:** não muda `status`; carimba `approval_decision = declined`, que o gate do drawer
  mostra como "Revisar e reenviar".
- **Tenant:** a rota é pública e não tem sessão. A re-busca da OS usa `withoutGlobalScopes()`
  filtrando por `id` **e** `business_id` da OS resolvida pelo token (comentário `// SUPERADMIN:`
  no controller).

## Testes

`Modules/OficinaAuto/Tests/Feature/`: `AprovacaoOsTokenTest`, `WhatsAppAprovacaoPinTest`,
`ServiceOrderEnviarAprovacaoTest`, `EnviarLinkAprovacaoWhatsappJobTest`,
`AprovacaoPublicaOrcamentoContratoTest` (UC-OAP-08..10: antes/depois do PIN e isolamento). Rodam no CT 100 ou na
lane `oficinaauto-pest`, nunca local.

## Pegadinhas

- A tela não herda o layout autenticado: nada de `AppShellV2`, `PageHeader` ou dado de sessão.
- O link depende do envio por WhatsApp; a fronteira com o módulo Whatsapp está na auditoria do
  WhatsApp (caminho B), não aqui.
- Texto de erro e de sucesso vem do controller via flash; mudar a cópia é mudar o controller.
