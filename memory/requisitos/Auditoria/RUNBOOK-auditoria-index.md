---
last_validated: "2026-11-09"
slug: runbook-auditoria-index
title: "RUNBOOK — /auditoria (lista da trilha de alterações)"
type: runbook
module: Auditoria
tela: Auditoria/Index
page: /auditoria
component: resources/js/Pages/Auditoria/Index.tsx
status: rascunho
version: "0.1"
owner: W
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0127-modules-auditoria-undo-activity-log
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
---

# RUNBOOK — `/auditoria` (Inertia)

> Tela de leitura da trilha `activity_log`, um registro por alteração. O detalhe de uma entrada
> tem RUNBOOK próprio: [RUNBOOK-auditoria-detail.md](RUNBOOK-auditoria-detail.md).
> Contrato de comportamento: [Index.casos.md](../../../resources/js/Pages/Auditoria/Index.casos.md).
> Em caso de divergência, vale a precedência de `memory/proibicoes.md`: teste verde > casos > charter > SPEC.

## 1. Quando esta tela quebra (sintomas)

| Sintoma | Onde olhar primeiro |
|---|---|
| `/auditoria` responde 500 | `storage/logs/laravel.log`; `Modules/Auditoria` ativo em `modules_statuses.json` |
| Responde 403 | middlewares do grupo em `Modules/Auditoria/Routes/web.php` e o que eles exigem do usuário |
| Item some do menu | `Modules/Auditoria/Http/Controllers/DataController.php` (`modifyAdminMenu`) e o pacote da empresa |
| Tabela presa no esqueleto | a resposta do pedido parcial de `activities`: na aba Rede, filtrar por `/auditoria` e ler o status |
| Lista vazia com dados no banco | `activity_log.business_id` nulo ou de outra empresa: `AuditEntryService::baseQuery` filtra só por `activity_log.business_id` |
| Filtro não muda a lista | a chave está fora de `AuditEntryService::ALLOWED_FILTERS` e `normalizeFilters` a descartou |
| `/reports/activity-log` não redireciona | rota 301 em `Modules/Auditoria/Routes/web.php`, coberta por UC-AUDI-03 |

## 2. Estrutura de arquivos

```
Modules/Auditoria/
├── Http/Controllers/AuditoriaController.php   # index: `activities` em Inertia::defer, `filters` eager
├── Http/Requests/FilterAuditEntriesRequest.php
├── Routes/web.php                             # /auditoria e o 301 de /reports/activity-log
└── Services/AuditEntryService.php             # list + normalizeFilters + baseQuery (business_id)

resources/js/Pages/Auditoria/
├── Index.tsx
├── Index.charter.md
└── Index.casos.md

tests/Feature/Auditoria/AuditoriaTelasContratoTest.php   # UC-AUDI-*, lane auditoria-pest
```

A tela não tem Blade. O módulo só lê: quem grava a trilha é o trait `LogsActivity` no Model de cada
módulo (ver [SCOPE.md](SCOPE.md)).

## 3. Contrato de dados

- `activities` chega por `Inertia::defer`, então **não vem no primeiro render**. A tela embrulha a
  tabela em `<Deferred data="activities">`. Ler a prop no primeiro render mede o defer, não a tabela.
- Filtros aceitos: `causer_kind`, `event`, `subject_type`. Qualquer outra chave é descartada antes da
  consulta, e é assim que o filtro nunca troca a empresa.
- Paginação e filtros usam partial reload (`only: ['activities', 'filters']`).

## 4. Comandos úteis

Teste e análise estática rodam **só no CT 100 ou no CI**, nunca na máquina local (ADR 0062).

```bash
tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test tests/Feature/Auditoria/AuditoriaTelasContratoTest.php"
```

O checkout do container pode estar atrás do `main`. Para validar um branch, use a lane
`auditoria-pest` do PR. Na leitura do resultado, olhe as *assertions*: skip sai com exit 0.

## 5. Smoke em produção

Sempre numa empresa interna. **Nunca biz=4**: a tela mostra dados reais de alteração, então print e
evidência não podem carregar nome de cliente, documento ou valor.

```bash
curl -sv https://oimpresso.com/auditoria 2>&1 | grep '^< HTTP'
curl -sv "https://oimpresso.com/reports/activity-log?event=updated" 2>&1 | grep -E '^< (HTTP|location)'
```

Sem sessão, o primeiro responde o redirect de login. O segundo responde o 301 sem precisar de login.

Com a sessão no navegador (Regra 0 do `PROTOCOLO-COMPARACAO-RUNTIME`), medir o DOM depois que a carga
deferida terminar, relendo até o número de linhas parar de mudar:

```js
[...document.querySelectorAll('table tbody tr')].length
```

## 6. Rollback

Reverter o PR e aguardar o deploy. A tela é só leitura e não há migration associada a este RUNBOOK.

## 7. Invariantes (Tier 0)

- Nenhuma linha de outra empresa na lista (UC-AUDI-01, [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- `activity_log` é append-only: esta tela não edita nem apaga entrada.
- O filtro nunca aceita `business_id` (UC-AUDI-02).

## 8. Pré-requisitos antes de editar a tela

1. Ler [Index.charter.md](../../../resources/js/Pages/Auditoria/Index.charter.md), a
   [ADR UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) e o
   [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md).
2. Rodar o [PRE-MERGE-UI](../_DesignSystem/PRE-MERGE-UI.md).
3. Mudou comportamento: atualizar `Index.casos.md` no mesmo PR (o gate G-6 mede o frescor).
4. `related_prototype` é `n/a` no charter. Não existe medição desta tela contra protótipo, então
   não se afirma que ela é igual a um.

## 9. Referências

- [SPEC.md](SPEC.md) · [SDD-auditoria-v1.0.md](SDD-auditoria-v1.0.md) · [SCOPE.md](SCOPE.md)
- [RUNBOOK-inertia-defer-pattern.md](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)
