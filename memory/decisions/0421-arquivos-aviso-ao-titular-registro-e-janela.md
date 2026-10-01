---
slug: 0421-arquivos-aviso-ao-titular-registro-e-janela
number: 421
title: "Arquivos — aviso ao titular (LGPD Art. 18 VI): registro titular_avisado_at + ação notice, janela de 30 dias, sem canal de envio"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-01"
module: null
tags: [arquivos, lgpd, retencao, aviso-titular, playbook]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0123-modules-arquivos-backbone
  - 0093-multi-tenant-isolation-tier-0
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
pii: false
---

# ADR 0421 — Arquivos: aviso ao titular é registro + janela; o canal fica para depois

## Contexto

**Fonte da decisão:** [W] 2026-10-01 — D5 aprovada, textual *"Aprove todos"*, registrado em
`prototipo-ui/cowork/Wagner/cowork-inbox/arquivos/playbook/_DECISOES-W-2026-10-01.md`:
*"sim — cunhar a ADR do aviso ao titular (LGPD Art. 18 VI) e criar `titular_avisado_at`, no
desenho da ficha 05 (janela `notice` 30d, só `bucket=sensitive` com titular identificado)"*.

O desenho vem da proposta `memory/decisions/proposals/arquivos-retencao-ui-aviso-titular.md`
(2026-08-24), PR-9. Medido no `main` antes desta ADR:

- `Config/retention.php` declara `notice_period_days = 30` sem nenhum consumidor — e o arquivo
  não é registrado em `config()` (o próprio docblock dele diz isso).
- O enum `arquivos_audit_log.action` já foi alargado duas vezes, pela mesma convenção
  (MySQL-only, só amplia, `down()` recusa perder trilha): `2026_07_02_000001` e `2026_08_10_000001`.
- O vencimento já tem regra viva: `ArquivosAdminController::linha()` usa `created_at` + prazo
  (`retention_days` da linha → policy do `sub_destination` → `retention_days_default`).
- O schema não tem coluna de titular. O único vínculo com pessoa é o dono polimórfico
  (`arquivable_type`/`arquivable_id`, FQCN — sem morphMap no projeto).
- Nem a ficha 05 nem a proposta dizem **por qual canal** o titular é avisado (a proposta diz
  só "canal de Notification").

Introspecção (skill `pre-adr-introspect`): nenhuma ADR anterior trata aviso ao titular;
número 0421 livre segundo `next-id.mjs`. Reusa as convenções acima — nada inventado de schema.

## Decisão

1. **Schema:** `arquivos.titular_avisado_at` (timestamp nullable) e `notice` no enum de
   `arquivos_audit_log.action`. Migration idempotente; o `down()` recusa reverter se houver
   aviso gravado.
2. **Janela:** um arquivo é elegível quando faltam **1 a 30 dias** para o vencimento, está fora
   da lixeira, ainda não foi avisado, tem `bucket = sensitive` e tem **titular identificado**.
   Vencido fica fora: o aviso é prévio.
3. **Titular identificado** = dono do arquivo é `App\Contact` (o cadastro de pessoa do ERP).
   É o critério conservador: ticket, OS e venda têm pessoa por trás, mas o vínculo não é direto.
4. **Registro:** `AvisoTitularService::registrarAviso(biz, arquivo, canal)` grava a coluna e a
   linha `notice` na mesma transação, idempotente, com payload sem PII
   (`canal`, `vence_em`, `dias_restantes`). Recusa canal vazio.
5. **Sem envio nesta ADR.** O service não envia nada e nada o chama: registrar "avisado" sem ter
   avisado seria registro falso. Quem implementar o canal chama `registrarAviso()` depois de enviar.
6. **Avisar nunca apaga.** Não toca `deleted_at`, não chama o `RetentionCleanupCommand` nem o
   purge do `ArquivosRetentionService`.
7. **Multi-tenant (ADR 0093):** `business_id` é argumento explícito em toda query; teste
   cross-tenant 98 × 99 (ADR 0358).

## Justificativa

Cumprir o prazo de aviso exige duas peças separáveis: saber **quem** está na janela e **provar**
que avisou. As duas cabem sem decidir o canal, e o canal é decisão de produto (e-mail? WhatsApp?
tela?) que [W] não tomou. Entregar só o registro evita o pior desfecho — uma trilha que afirma
avisos que não aconteceram.

Reabrir se: o canal for decidido (aí nasce o envio, que chama o registro); o critério de titular
precisar incluir outros donos; ou a janela precisar sair de `config()` por business.

## Consequências

**Positivas:** a janela é consultável; a prova do aviso fica na trilha append-only; nenhum dado
é apagado por este caminho.

**Negativas / Trade-offs:** até o canal existir, nenhum titular é de fato avisado — a tela segue
dizendo que o aviso não existe, e isso continua verdade. A janela de 30 dias é constante no
service (o `retention.php` não é registrado em config).

**Riscos mitigados:** registro falso de aviso; purge disparado pelo aviso; vazamento cross-tenant;
PII na trilha.

## Pendências [W]

- **Canal do aviso** (e-mail, WhatsApp, Notification de tela) e quem dispara (job agendado ou ação manual).
- **Critério de titular** além de `App\Contact`.
- **Arquivo já vencido sem aviso:** hoje fica fora da janela.

## Referências

- ADR 0123 — Modules/Arquivos backbone (§8 trilha append-only)
- ADR 0093 — Multi-tenant Tier 0
- ADR 0358 — tenant fictício 98 em teste
- Proposta `arquivos-retencao-ui-aviso-titular` (2026-08-24), PR-9
- `Modules/Arquivos/Database/Migrations/2026_10_01_000001_add_titular_avisado_at_and_notice_to_arquivos.php`
- `Modules/Arquivos/Services/AvisoTitularService.php`
