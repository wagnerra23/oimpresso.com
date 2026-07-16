---
feature: gateway-ativacao
module: RecurringBilling
---

# Tasks — Ativar gateway nas assinaturas dormentes

> **Estado de workflow vive no MCP** (`tasks-create ... parent_plan:"recurring-billing-gateway-ativacao"`,
> ADR 0070) — este arquivo é o plano versionado (ordem + dependências + DoD), sem `status:`
> (ADR 0302). Executar em ordem topológica de `blocked_by:`.

### T-01 · Criar `GatewayBackfillCommand` skeleton com dry-run padrão (tabela antes→depois)
> blocked_by: — · covers: AC-1 · us: US-RB-052 · estimate: 2h

Novo comando em `Modules/RecurringBilling/Console/Commands/` imitando `BackfillCachedFieldsCommand`
(chunk + relatório + `--detail`, nunca `--verbose`). Sem `--apply`, apenas lista: assinatura,
business, contato `[REDACTED]`, provider proposto (vazio nesta task), conta alvo. Zero escrita.

**DoD:** `php artisan rb:gateway-backfill` roda no CT 100 staging e imprime a tabela das
assinaturas `conta_bancaria_id IS NULL` + `status IN (active, trialing)`; `SELECT COUNT(*)`
de `conta_bancaria_id NOT NULL` idêntico antes/depois (prova de não-escrita).

### T-02 · Resolução determinística de provider por business (Inter 077 · C6 336 · Cora 403)
> blocked_by: T-01 · covers: AC-4 · us: US-RB-052 · estimate: 3h

Resolver conta bancária ativa do business no banco preferencial (plan.md D4). Ambiguidade
(0 ou 2+ contas candidatas) = não-resolvível: assinatura pulada e listada na seção
"não-resolvíveis" do relatório. Nunca palpite.

**DoD:** dry-run em staging classifica 100% das assinaturas dormentes em `resolvível(provider)`
ou `não-resolvível(motivo)`; nenhuma linha sem classificação.

### T-03 · `--apply` idempotente + audit `gateway_atribuido` por escrita
> blocked_by: T-02 · covers: AC-2, AC-3 · us: US-RB-052 · estimate: 2h

Escrita via Model (global scope Tier 0), 1 `SubscriptionEvent` `gateway_atribuido` por
assinatura (de→pra, ator, origem=backfill). Só toca quem está `conta_bancaria_id IS NULL`
(idempotência estrutural).

**DoD:** em staging, `--apply` 1ª rodada grava N + N eventos; 2ª rodada reporta `0 alteradas`;
diff de dados da 2ª rodada vazio.

### T-04 · Pest: idempotência · não-resolvível pulado · cross-tenant biz=1 vs biz=99
> blocked_by: T-03 · covers: AC-3, AC-4, AC-5 · us: US-RB-052 · estimate: 3h

Feature tests em `Modules/RecurringBilling/Tests/` (registrados no phpunit.xml como a suite
atual — pegadinha "Tests/ sem registro = falsa cobertura"). Cenários: re-run no-op; business
sem conta → intocada + relatório; assinatura biz=99 jamais recebe conta de biz=1. Testes biz=1
(ADR 0101), rodando no CT 100.

**DoD:** suite verde no CT 100 (`tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=GatewayBackfill"`) + CI verde no PR.

### T-05 · GATE HUMANO — Wagner aprova o dry-run real (antes→depois das 109) e autoriza `--apply` em prod
> blocked_by: T-04 · covers: AC-2 · us: US-RB-052 · estimate: 30min

REGRA MESTRE valor/estoque + R10: colar a tabela dry-run de PROD (109 linhas: 36 C6 + 51 Inter
+ 22 Cora + eventuais não-resolvíveis) e aguardar aprovação explícita. Sem aprovação, nada
de `--apply`.

**DoD:** aprovação Wagner registrada (mensagem/PR comment) + `--apply` executado em prod com
output colado; contagem pós-apply bate com o dry-run aprovado.

### T-06 · Fechar o loop — smoke canário 1 ciclo + âncora da US-RB-052 no SPEC
> blocked_by: T-05 · covers: AC-6 · us: US-RB-052 · estimate: 1h

Escolher 1 assinatura canário destravada, acompanhar a próxima geração de fatura: `gateway`/
`conta_bancaria_id` preenchidos + emissão disparada (evidência literal — R1). Então atualizar
`**Implementado em:**` da US-RB-052 no SPEC pra `` `path do comando` · verificado@<sha7> (<data>) ``
(ADR 0273) e reativar a régua do lote inteiro conforme decisão do gate T-05.

**DoD:** fatura canário com `gateway` preenchido (query colada) + emissão confirmada no
provider + `node scripts/governance/anchor-lint.mjs memory/requisitos/RecurringBilling/SPEC.md`
mostra US-RB-052 `anchored_ok`.
