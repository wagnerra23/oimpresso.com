---
last_validated: "2026-11-09"
slug: runbook-auditoria-detail
title: "RUNBOOK — /auditoria/{id} (detalhe de uma alteração)"
type: runbook
module: Auditoria
tela: Auditoria/Detail
page: /auditoria/{activityId}
component: resources/js/Pages/Auditoria/Detail.tsx
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

# RUNBOOK — `/auditoria/{activityId}` (Inertia)

> Detalhe read-only de uma entrada de `activity_log`: quem fez, quando, em qual entidade e o diff
> Campo · Antes · Depois. A lista tem RUNBOOK próprio: [RUNBOOK-auditoria-index.md](RUNBOOK-auditoria-index.md).
> Contrato de comportamento: [Detail.casos.md](../../../resources/js/Pages/Auditoria/Detail.casos.md).
> Em caso de divergência, vale a precedência de `memory/proibicoes.md`: teste verde > casos > charter > SPEC.

## 1. Quando esta tela quebra (sintomas)

| Sintoma | Onde olhar primeiro |
|---|---|
| Responde 500 | `storage/logs/laravel.log`; `properties` da entrada com JSON inesperado |
| Responde 403 | middlewares da rota em `Modules/Auditoria/Routes/web.php` |
| "Atividade não encontrada" aparece por um instante antes do conteúdo | a tela está lendo a prop deferida sem `<Deferred>`: no primeiro render `activity` é `undefined` |
| "Atividade não encontrada" numa entrada que existe | `activity_log.business_id` da entrada difere do `user.business_id` da sessão |
| Esqueleto que nunca termina | o pedido parcial de `activity` falhou: na aba Rede, ler o status da resposta |
| Tabela de diff vazia com dado no banco | `properties` sem o par `old`/`attributes`: a tela cai no formato chave/valor |

## 2. Estrutura de arquivos

```
Modules/Auditoria/
├── Http/Controllers/AuditoriaController.php   # show: `activity` em Inertia::defer
├── Routes/web.php                             # /auditoria/{activityId} só numérico
└── Services/AuditEntryService.php             # find + baseQuery (business_id)

resources/js/Pages/Auditoria/
├── Detail.tsx
├── Detail.charter.md
└── Detail.casos.md

tests/Feature/Auditoria/AuditoriaTelasContratoTest.php   # UC-AUDD-*, lane auditoria-pest
```

## 3. Contrato de dados

- `activity` chega por `Inertia::defer`, então **não vem no primeiro render**. Na resposta inicial ela
  aparece só em `deferredProps`.
- São estados distintos, e a tela não pode confundi-los: `undefined` é "ainda carregando"; registro
  inexistente ou de outra empresa é "não encontrada".
- O escopo de empresa mora em `AuditEntryService::baseQuery`. A tela nunca o recebe como parâmetro.

Um teste HTTP desta tela precisa mandar os headers que o cliente Inertia manda de fato no partial
reload: `X-Requested-With`, `X-Inertia`, `X-Inertia-Version`, `X-Inertia-Partial-Component` e
`X-Inertia-Partial-Data`. A versão boa vem do `page` do primeiro GET.

## 4. Comandos úteis

Teste e análise estática rodam **só no CT 100 ou no CI**, nunca na máquina local (ADR 0062).

```bash
tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test tests/Feature/Auditoria/AuditoriaTelasContratoTest.php"
```

O checkout do container pode estar atrás do `main`. Para validar um branch, use a lane
`auditoria-pest` do PR. Na leitura do resultado, olhe as *assertions*: skip sai com exit 0.

## 5. Smoke em produção

Sempre numa empresa interna. **Nunca biz=4**: `properties` carrega dados reais, então print e
evidência não podem mostrar nome de cliente, documento ou valor. Prefira capturar uma entrada cujo
diff não tenha dado pessoal.

Medir o DOM (Regra 0 do `PROTOCOLO-COMPARACAO-RUNTIME`) com navegação real a partir da lista, com um
`MutationObserver` ligado antes do clique em "Ver →", para registrar todo texto que apareceu:

```js
const vistos = new Set();
new MutationObserver(() => {
  if (document.body.innerText.includes('Atividade não encontrada')) vistos.add('nao-encontrada');
  if (document.querySelector('[aria-busy="true"]')) vistos.add('esqueleto');
}).observe(document.body, { childList: true, subtree: true });
```

Esperado numa entrada que existe: `esqueleto` sim, `nao-encontrada` não. Repetir com um id que não
existe: ao terminar a carga, o estado de não encontrada aparece e o esqueleto some.

## 6. Rollback

Reverter o PR e aguardar o deploy. A tela é só leitura e não há migration associada a este RUNBOOK.

## 7. Invariantes (Tier 0)

- Entrada de outra empresa não abre e não devolve dado nenhum (UC-AUDD-02, [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- A tela não edita, não apaga e não reverte a entrada (Non-Goals do charter).
- Abrir o detalhe não grava nada.

## 8. Pré-requisitos antes de editar a tela

1. Ler [Detail.charter.md](../../../resources/js/Pages/Auditoria/Detail.charter.md), a
   [ADR UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) e o
   [PT-03 Detalhe](../_DesignSystem/padroes-tela/PT-03-Detalhe.md).
2. Rodar o [PRE-MERGE-UI](../_DesignSystem/PRE-MERGE-UI.md).
3. Mudou comportamento: atualizar `Detail.casos.md` no mesmo PR (o gate G-6 mede o frescor).
4. Não há protótipo desta tela (`related_prototype: n/a`), então não se afirma que ela é igual a um.

## 9. Referências

- [SPEC.md](SPEC.md) · [SDD-auditoria-v1.0.md](SDD-auditoria-v1.0.md) · [SCOPE.md](SCOPE.md)
- [RUNBOOK-inertia-defer-pattern.md](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)
