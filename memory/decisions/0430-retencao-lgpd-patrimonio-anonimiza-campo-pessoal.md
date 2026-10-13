---
slug: 0430-retencao-lgpd-patrimonio-anonimiza-campo-pessoal
number: 430
title: "Retenção LGPD do Patrimônio anonimiza campo pessoal, nunca apaga linha nem trilha"
type: adr
status: recusado
rejected_at: "2026-10-13"
rejected_via: "Wagner 2026-10-13 no chat: 'não pode ter o LGPD no patrimonio isso é errado' e 'descarta o branch e reverte a ADR 0430'"
rejected_reason: "No Patrimônio não há retenção LGPD: vale a lápide de 2026-07-27 (num ERP o dado tem dever de guarda; o controle é por permissão de acesso). A thread 05 volta a ser não executável."
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-07"
module: null
quarter: 2026-Q4
tags: [lgpd, retencao, patrimonio, asset-management, append-only]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
pii: false
review_triggers:
  - "ligar ASSETMANAGEMENT_RETENTION_ENABLED em qualquer empresa (D-CANARY-LGPD)"
  - "cumprir uma janela exigir DELETE em asset_transactions"
---

# ADR 0430 — Retenção LGPD do Patrimônio anonimiza campo pessoal, nunca apaga linha nem trilha

## Contexto

Em 2026-07-27 uma lápide do §5 (`memory/licoes-rejeitadas.md`, *"Ligar o `jana:retention-purge`
… DESCARTADO por [W]: num ERP não se apaga PII"*) proibiu re-propor varredura automática por TTL
que *"apague/anonimize conteúdo de negócio ou de conversa … em qualquer entidade do ERP"*. Ela
nasceu do item #6 do loop IA-OS, que tratava a varredura da Jana. O limite dela foi escrito largo
o bastante para alcançar o Patrimônio, e o playbook do Patrimônio registrou a thread 05 (job de
retenção) como **barrada** por essa lápide (`_saida-04.md`).

Em 2026-10-07, no Cowork, o [W] decidiu (D-LGPD-REABRE, índice do playbook `patrimonio`):
*"o descarte da 05 está errado — reabrir. Retenção ANONIMIZA campo pessoal, nunca apaga linha nem
trilha de auditoria; nasce desligada (enabled=false). Ligar segue D-CANARY-LGPD."* A ficha
`05a-adr-e-chaves.md` pede esta ADR e diz o motivo: a lápide misturou duas coisas diferentes, o
**apagar** dado e o **anonimizar** campo pessoal mantendo o registro.

Medido no mesmo playbook (`_saida-04.md` §2b): as 4 chaves de `Modules/AssetManagement/Config/retention.php`
apontavam para tabelas que nenhuma migration cria (`am_*`). As reais são `assets`,
`asset_transactions`, `asset_maintenances` e `asset_warranties`.

## Decisão

1. **No Patrimônio (`Modules/AssetManagement`), retenção é anonimização de campo pessoal.** A linha
   fica; o que some depois da janela é o vínculo com a pessoa (ex.: `asset_transactions.receiver`,
   `asset_maintenances.assigned_to`, notas livres). Métricas e histórico do bem continuam.
2. **Nunca perde linha:** `activity_log` e `asset_transactions` não sofrem `DELETE`. Se cumprir uma
   janela exigir `DELETE` em `asset_transactions`, a execução para e volta ao [W].
3. **Nasce desligada:** `ASSETMANAGEMENT_RETENTION_ENABLED=false`. Ligar em qualquer empresa é a
   decisão D-CANARY-LGPD, do [W].
4. **Por empresa:** o executor (thread 05) roda com `business_id` explícito, nunca em varredura global
   (ADR 0093).
5. **Janela só onde há campo pessoal.** `asset_warranties` não tem coluna que aponte para pessoa
   (só `asset_id`, datas, custo e nota), então sai do `retention.php`.

### O que esta ADR NÃO decide

- **A Jana.** A lápide de 2026-07-27 continua valendo para `jana:retention-purge` e para o item #6
  do loop IA-OS: o texto da D-LGPD-REABRE fala do descarte **da 05 do Patrimônio**. Estender a
  reabertura à Jana pede decisão [W] própria.
- **Quais campos exatos são anonimizados e como.** Isso é a thread 05, com teste de que a trilha
  fica intacta.

## Justificativa

A lápide de 27/07 tinha um motivo de domínio correto: num ERP o dado tem dever de guarda, e o que
se controla é quem enxerga. Anonimizar o vínculo pessoal sem apagar a linha preserva esse dever:
o bem, a movimentação e a manutenção continuam registrados, e a trilha de auditoria não é tocada.
O que a lápide proibia de fato (perder o registro) segue proibido pelo item 2.

## Consequências

**Positivas:** a thread 05 deixa de estar barrada; o `retention.php` passa a apontar tabelas que
existem, e o teste e o health check que o leem passam a conferir os nomes certos.

**Negativas / Trade-offs:** a lápide de 27/07 passa a ter exceção no Patrimônio; quem a ler sem a
emenda pode achar que ainda bloqueia. Por isso a emenda em `memory/licoes-rejeitadas.md` aponta
para esta ADR.

**Riscos mitigados:** nada liga sozinho (item 3); linha nunca some (item 2); nenhuma varredura
cruza empresas (item 4).

## Referências

- `prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/05a-adr-e-chaves.md` — a ficha
- `prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/05-retencao-lgpd.md` — o executor
- `prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/_saida-04.md` §2b — as 4 chaves
- `memory/licoes-rejeitadas.md` — lápide de 2026-07-27 e a emenda de 2026-10-13
- ADR 0093 — multi-tenant Tier 0
