---
id: requisitos-financeiro-features-recebimento-parcial-parcela-plan
feature: recebimento-parcial-parcela
module: Financeiro
---

# Plan — arquitetura do recebimento parcial de parcela

## Status vivo

- **status:** proposto · **owner:** W
- **criado/revisado:** 2026-08-03 · **próxima revisão:** 2026-09-02
- **cycle:** off-cycle · `parent_plan=financeiro-recebimento-parcial-parcela`
- **gate de saída:** AC-1..07 provados na lane MySQL, smoke real e US-FIN-003 ancorada por SHA.
- **kill-condition:** replanejar se ADR posterior substituir o SPLIT ou impedir idempotência atômica.
- **verdade-viva:** este documento

## Gerado agora pela máquina

```text
memory/requisitos/Financeiro/features/recebimento-parcial-parcela/
├── requirements.md  # O QUÊ: exemplo, clarificações e AC-1..07
├── plan.md          # COMO: arquitetura, reuso, dados e riscos
└── tasks.md         # ORDEM: T-01..T-06 e prova por etapa
```

O trio detalha a US-FIN-003; não duplica o `SPEC.md`/`casos.md` nem afirma runtime já entregue.

## Arquitetura de implementação desejada

| Arquivo | Ação e responsabilidade |
|---|---|
| `Http/Requests/StoreBaixaRequest.php` | reutilizar; validar entrada e `idempotency_key`, nunca `business_id` |
| `Http/Controllers/UnificadoController.php` | editar; adaptar HTTP e chamar o serviço, sem regra contábil |
| `Services/BaixaService.php` | **novo**; único dono de lock, split/quitação, baixa e caixa transacionais |
| `Services/TituloAutoService.php` | editar após paridade; adaptar `TransactionPayment` ao mesmo núcleo |
| `Events/TituloBaixado.php` | **novo**; evento after-commit mínimo, sem PII |
| `Repositories/BaixaRepository.php` | reutilizar; consulta tenant-scoped por idempotência/read-side |
| `Tests/Feature/BaixaConservacaoValorContratoTest.php` | estender split, clamp e isolamento |
| `Tests/Feature/BaixaManualLedgerContratoTest.php` | **novo**; baixa+caixa e retry idempotente |
| `Unificado/_components/FinBaixaSheet.tsx` | editar; UUID estável por tentativa e feedback |
| `Unificado/Index.casos.md` | estender casos existentes, nunca criar cópia |
| `memory/requisitos/Financeiro/SPEC.md` | manter somente o ponteiro para este trio |

`BaixaService` recebe `businessId`, `tituloId`, dados validados e `actorId`; não lê `session()` nem conhece
HTTP/Inertia. O controller só traduz o resultado em flash. O repository permanece read-side.

## Fluxo desejado

```text
FinBaixaSheet → StoreBaixaRequest → UnificadoController → BaixaService
  1. deduplica idempotency_key no business
  2. lockForUpdate e valida título/conta/tenant
  3. parcial: filho quitado + pai reduzido; total: pai quitado
  4. cria TituloBaixa + CaixaMovimento na mesma transação
  5. commit → TituloBaixado → flash recebido/restante
```

## Decisões e âncoras

| # | Decisão | Âncora |
|---|---|---|
| D1 | Manter SPLIT, sem `status=parcial` | CU-FIN-02 · UC-FUNI-01 |
| D2 | Materializar `BaixaService`, já citado por docblocks/DoD | US-FIN-003 |
| D3 | Título, baixa e caixa compartilham uma transação | CU-FIN-08 |
| D4 | UUID da tentativa, único por business; constraint existente é a última defesa | migration de `fin_titulo_baixas` |
| D5 | `businessId`/`actorId` explícitos; nenhum tenant vem do payload | ADR 0093 |
| D6 | Reusar request, repository, sheet e casos existentes | reuse-check do trio |
| D7 | Evento somente após commit | append-only contábil |

## Dados e contratos

- **Escritas:** `fin_titulos`, `fin_titulo_baixas`, `fin_caixa_movimentos`.
- **Leituras:** `fin_contas_bancarias`, `fin_planos_contas` e idempotência da baixa.
- **Rota preservada:** `POST /financeiro/unificado/{id}/baixar`.
- **Entrada:** valor, data, conta, meio e chave; `business_id`/`created_by` são contexto confiável.
- **Saída do serviço:** baixa, título, valor efetivo e restante; sem resposta HTTP.

## Riscos Tier-0

- [x] **Multi-tenant:** escopo explícito e teste biz=1 versus biz=2 (ADR 0093).
- [ ] **Valor:** antes da implementação, aprovação [W] do antes→depois e dois caminhos do AC-3; aberto
  porque agora só houve documentação.
- [x] **LGPD:** evento/log sem nome, CPF/CNPJ ou observação livre.
- [x] **Tela:** se o `.tsx` mudar, ler charter, estender casos, PRE-MERGE-UI e prova visual.
- [x] **Runtime:** Pest/PHPStan apenas no CT 100 (ADR 0062); localmente, só lint Node.

## Alternativas descartadas

- Regra no controller — mantém caminhos divergentes.
- Novo FormRequest ou migration — request e constraint já existem.
- Somente `TituloBaixa` — quebra CU-FIN-08; UUID novo por retry não deduplica.
- Voltar a `status=parcial` — contradiz SPLIT e UC-FUNI-01..04.
