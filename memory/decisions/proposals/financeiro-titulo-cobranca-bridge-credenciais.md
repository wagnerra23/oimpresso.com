---
status: proposal
title: Ponte título↔cobrança de mão dupla + unificação das credenciais de gateway
proposed_by: Wagner + Claude Code
proposed_at: 2026-06-08
relates_to:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0144-paymentgateway-extracao-camada-cobranca
  - 0170-paymentgateway-module-extraction
---

# PROPOSAL — Ponte título↔cobrança de mão dupla + unificação das credenciais de gateway

> **Status:** `proposal` — Wagner promove pra ADR aceita (próximo número canônico) após decidir as questões abertas (§7). Tier 0 (multi-tenant + dado financeiro) → decisão é dele.
>
> **Origem:** sessão 2026-06-08 (Wagner: "como criar boleto pelo financeiro" → "ainda não integra no financeiro?"). Onda A (deep-link "Cobrar", [US-FIN-054](../../requisitos/Financeiro/SPEC.md)) entregue em [PR #2449](https://github.com/wagnerra23/oimpresso.com/pull/2449). Este doc cobre as **Ondas B e C** deixadas explicitamente fora daquele PR.

## 1. Contexto

Hoje a emissão de cobrança (`Modules/PaymentGateway`, tabela `cobrancas`) é uma **ilha** em relação ao Financeiro (`Modules/Financeiro`, tabela `fin_titulos`). Três fragmentações concretas:

**(B) A integração título↔cobrança é de mão única, parcial e gambiarrada.**
- O único elo é o listener [`OnCobrancaPagaCreateFinanceiroTitulo`](../../../Modules/Financeiro/Listeners/OnCobrancaPagaCreateFinanceiroTitulo.php), que:
  - só roda pra **biz=1** (`config('app.saas_owner_business_id')`) — `return` na linha 45 pra qualquer tenant;
  - só dispara **no pagamento** (evento `CobrancaPaga`), nunca na emissão;
  - cria o `fin_titulo` **já `quitado`**, pulando a fase "em aberto" — quem usa o Financeiro **nunca vê o que tem a receber**, só o que já recebeu;
  - usa `origem='manual'` porque o enum de `fin_titulos.origem` **não tem** `'paymentgateway'` (workaround pra não migrar o enum em prod).
- Não existe o caminho reverso: emitir cobrança **não** cria título; e não há "gerar boleto a partir do título a receber" (a Onda A só faz deep-link visual).

**(C) Existem 3 tabelas de credencial de gateway concorrentes.**
- `rb_boleto_credentials` — onde o **Inter vivo de biz=1** realmente está (instalado via `scripts/inter-credentials/install-biz.py`, cert mTLS em base64).
- `payment_gateway_credentials` — a tabela canônica do módulo PaymentGateway (`conta_bancaria_id` FK, wizard step 3).
- `fin_contas_bancarias.payment_gateway_credential_id` — FK legado de transição.
- **Consequência visível:** o "Conta destino" do wizard `/financeiro/cobranca` (que lê `fin_contas_bancarias` com credencial resolvível) aparece **vazio em biz=1**, apesar do Inter estar funcionando — porque a credencial mora na tabela errada. Sem conta destino, `PaymentGatewayService::for()` lança `CredentialMisconfiguredException` → não emite.

## 2. Problema

1. Larissa/Eliana (e o próprio Wagner) não enxergam **contas a receber** geradas por boletos emitidos — só o pago.
2. A emissão real de boleto via wizard depende de uma conta destino que, hoje, só existe pra quem cadastrou pelo caminho `payment_gateway_credentials` — não pra quem tem Inter no `rb_boleto_credentials`.
3. A regra `só biz=1` impede que qualquer cliente pagante use a integração.
4. `origem='manual'` polui o livro financeiro e impede rastrear a real procedência (paymentgateway) sem ler `metadata`.

## 3. Onda B — Ponte título↔cobrança de mão dupla

### Decisão proposta
1. **Emitir cobrança cria/atualiza um `fin_titulo` "em aberto" na hora** (não no pagamento), pra **todos os tenants** — via listener no evento `CobrancaEmitida`.
2. **Quando a cobrança nasce de um título** (deep-link Onda A → `cobranca.origem_type='titulo'`, `origem_id=titulo.id`): **não cria título novo** — vincula à cobrança ao título existente e, no pagamento, dá baixa **naquele** título.
3. **Quando a cobrança é avulsa** (sem título de origem): cria o `fin_titulo` "a receber" na emissão; baixa no pagamento.
4. **Adicionar `'paymentgateway'` ao enum `fin_titulos.origem`** (migration idempotente, PR separado) e aposentar o `origem='manual'` desse fluxo.
5. **Remover o gate `biz=1`** do listener — substituir por resolução de conta correta por tenant + (se necessário) feature-flag de rollout gradual.

### Opções consideradas
| Opção | Prós | Contras |
|---|---|---|
| **B1 — Listener cria título na emissão (recomendado)** | AR fica correto; reaproveita eventos já existentes (`CobrancaEmitida`/`CobrancaPaga`); append-only respeitado | Precisa migration de enum + backfill dos órfãos |
| B2 — `fin_titulos` vira view/projeção de `cobrancas` | Fonte única | Reescrita grande; quebra idempotência e baixas manuais já existentes |
| B3 — Manter ilhas + relatório que une as duas | Zero migration | Não resolve o "não vejo a receber"; perpetua a gambiarra |

### Consequências
- ✅ Contas a Receber passa a refletir boletos emitidos (em aberto → quitado).
- ✅ Tier 0: cada `fin_titulo`/`cobranca` no seu `business_id` (ADR 0093) — resolução de conta por tenant é o ponto sensível a blindar com Pest cross-tenant.
- ⚠️ Backfill: cobranças `emitida` históricas sem título correspondente precisam de comando idempotente (`fin:backfill-cobrancas-em-aberto`).
- ⚠️ Append-only ([tech/0002](../../requisitos/Financeiro/adr/tech/0002-soft-delete-com-trava-historico.md)): cancelar cobrança ⇒ título vira `cancelado`, nunca delete.

## 4. Onda C — Unificação das credenciais de gateway

### Decisão proposta
1. **`payment_gateway_credentials` é a fonte canônica única.** 
2. **Migrar o Inter de `rb_boleto_credentials` → `payment_gateway_credentials`** (gateway_key='inter', `config_json` com cert b64 + client_id/secret), vinculando a uma `fin_contas_bancarias` (`conta_bancaria_id`).
3. Manter `fin_contas_bancarias.payment_gateway_credential_id` e `rb_boleto_credentials` como **fallback read-only durante a transição** (deprecação anunciada, não deleção imediata).
4. Resultado: `listarContasDestino` passa a devolver a conta Inter de biz=1 → "Conta destino" deixa de ficar vazio → wizard emite ponta-a-ponta.

### Opções consideradas
| Opção | Prós | Contras |
|---|---|---|
| **C1 — Migrar tudo pra `payment_gateway_credentials` (recomendado)** | 1 fonte; wizard funciona; `install-biz.py` passa a gravar lá | Migration sensível (cert/secret cifrados — cuidado LGPD/segredos) |
| C2 — Wizard passa a ler as 3 tabelas | Sem migration | Perpetua fragmentação; 3 caminhos de bug |
| C3 — Deletar `rb_boleto_credentials` já | Limpo | Quebra Inter LIVE de biz=1 — **inaceitável** |

### Consequências
- ✅ "Conta destino" preenchido → wizard emite de verdade (Inter biz=1 e futuros tenants).
- ⚠️ **Segredos:** mover cert mTLS + client_secret entre tabelas é manuseio de credencial — seguir [feedback-nunca-publicar-credenciais](../../reference/feedback-nunca-publicar-credenciais.md); cifrar por-campo; **não logar**.
- ⚠️ `install-biz.py` precisa ser atualizado pra gravar na tabela canônica (senão recria a fragmentação).

## 5. Sequência sugerida
1. **C primeiro** (destrava o "Conta destino" → wizard da Onda A passa a emitir de fato).
2. **B em seguida** (AR correto + baixa automática vinculada ao título, todos tenants).
3. Cada onda = 1 ADR aceita + migrations em PRs separados (regra M-AP-4 das [Lições F3](../../../memory/reference/prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md): schema novo → ADR → migration própria → só então código).

## 6. Não-objetivos
- Reescrever `cobrancas` ou `fin_titulos` do zero.
- Mexer no enum em prod sem migration idempotente + backfill.
- Deletar `rb_boleto_credentials` antes da transição validada.

## 7. Questões abertas (Wagner decide antes de virar ADR)
1. **Criar `fin_titulo` na emissão de TODA cobrança**, ou só de `boleto`/`pix_cobv` (cobranças com vencimento)? (PIX imediato/cartão talvez não devam virar "a receber".)
2. **Rollout da remoção do gate biz=1**: liga pra todos os tenants de uma vez, ou feature-flag por business (ex. começa Larissa biz=4)?
3. **Migração de credenciais**: faço a migration C1 movendo o Inter agora, ou prefere que eu só escreva o plano e você roda o `install-biz.py` apontando pra tabela nova?
4. Backfill dos boletos `emitida` históricos vira "a receber" retroativo, ou aplica só dali pra frente?

---

**Próximo passo:** Wagner responde §7 → eu promovo a duas ADRs canônicas (`0NNN-titulo-cobranca-bridge.md` + `0NNN-unificacao-credenciais-gateway.md`) e abro os PRs de migration na ordem da §5.
