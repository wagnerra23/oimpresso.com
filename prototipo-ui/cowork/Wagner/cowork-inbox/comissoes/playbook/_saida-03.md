---
sessao: "03"
titulo: "Split de comissão auditado (activity log) — saída da thread"
autor: "[CL]"
criado: 2026-10-06
base: 1ab4ab51b1
thread: 03-split-auditado.md
veredito: "entregue no código, SEM RUN ainda — commission_split e commission_agent entram no logOnly do Transaction; docblock do controller corrigido; teste de comportamento UC-COM-01/02 nasce na lane Sells · Pest (MySQL). Prefixo ampliado em 1 arquivo (.github/workflows/sells-pest.yml) para o teste rodar."
---

# _saída 03 · Split de comissão auditado

## O que mudou

| arquivo | mudança |
|---|---|
| `app/Transaction.php` | `+ 'commission_agent', 'commission_split'` no `logOnly` (log `sales.transaction`, ADR 0127). `logOnlyDirty` + `dontSubmitEmptyLogs` seguem. |
| `app/Http/Controllers/SellCommissionSplitController.php` | docblock: deixa de prometer auditoria que não existia e diz o que existe agora (com a data do conserto). Nenhuma linha de código. |
| `tests/Feature/Sells/CommissionSplitAuditTest.php` | novo — 6 testes, UC-COM-01 (4) e UC-COM-02 (2). |
| `.github/workflows/sells-pest.yml` | **fora do prefixo, declarado**: o teste entra na allowlist da lane, e `app/Transaction.php` + o controller entram nos dois filtros de caminho. Sem isso o teste existiria e não rodaria em CI nenhum (a lane lista arquivo por arquivo; `ci.yml` é SQLite e pula). |

## Cálculo de comissão

Não muda. Só passa a **registrar** a troca de quem recebe e em que proporção. A REGRA MESTRE de valor não se aplica: nenhum total, percentual ou base de cálculo foi tocado.

## Casos de uso

- **UC-COM-01** `[T0]` — PATCH 70/30 → 100/0 gera 1 registro com `causer_id`, `business_id` da venda, `old` e `attributes` do split; PATCH `null` grava o split apagado no antes; trocar `commission_agent` grava antes→depois; controle positivo: o mesmo split salvo de novo não gera registro.
- **UC-COM-02** `[T0]` — venda de outro business → 404 e a coluna fica `null`; mecânico de outro business → 422 em `commission_split.mecanico_id`; balconista de outro business → 422 em `commission_split.balcao_id`; controle positivo: mesmo payload no próprio business → 200.
  - O playbook dizia "já coberto por `CommissionSplitEditorTest`? conferir". **Conferido: não estava.** Aquele arquivo só lê o fonte (`file_get_contents` + `toContain`) e testa o cast em memória; nenhum caso faz o PATCH. Por isso os dois casos foram escritos aqui, sem duplicar nada de lá.

## Efeito colateral medido

`Modules/Auditoria/Services/RevertService` reaplica `properties.old` no model. Com os dois campos logados, desfazer uma troca de split pela tela de Auditoria passa a restaurar o split anterior — que já tinha passado pela validação quando foi gravado. A trava UNREVERTIBLE do Transaction (pagamento posterior) continua valendo.

## O que NÃO foi provado

- **Nenhum run ainda.** Pest não roda local; o veredito vem da lane `Sells · Pest (MySQL)` no PR. Vermelho ali é deste arquivo.
- `commission_agent` é gravado também pelo fluxo POS (`SellPosController`); o teste exercita a troca pelo model, não pela tela do POS.
- Casos UC-COM-01/02 não têm `.casos.md` de tela (o endpoint não é tela). O contrato mora neste playbook.

## Fronteira respeitada

Nada em `resources/js/` nem em `Modules/`.
