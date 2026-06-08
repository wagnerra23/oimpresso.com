# ADR ARQ-0003 (RecurringBilling) · Pix Automático com Jornada 3 (PAYMENTONAPPROVAL)

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: ARQ-0001, US-RB-020, US-RB-021

## Contexto

Pix Automático (BCB 2025+) é **autorização recorrente nativa** que substitui débito automático tradicional. Cliente autoriza débito futuro de uma instituição (PSP) com limite e vencimento. Cobrança recorrente fica:

- ~14× mais barata que cartão (R$ 0,01 a R$ 0,10 por transação vs R$ 0,30 + 2,9% cartão)
- Sem expirar (cartão expira a cada 3-5 anos)
- Sem fraude por chargeback
- Aprovação ~99% (vs ~85% cartão pra recorrência)

BCB define **3 jornadas** de autorização (JRC — Jornada de Recorrência de Consentimento):

| Jornada | Como autoriza | Como cobra | Quando usar |
|---|---|---|---|
| **1** (CREATE_RECEIVER_ID) | Cadastro recipiente, payer autoriza ID | Cobrança usa receiver_id ativo | Setup pesado, flexibilidade máxima |
| **2** (CREATE_RECURRENCY) | Recorrência específica (valor + ciclo) autorizada | Mesma autorização cobra ciclo a ciclo | Autorização mais granular |
| **3** (PAYMENTONAPPROVAL) | Autoriza E paga 1ª cobrança no mesmo QR | Próximas cobranças usam mesma autorização | Onboarding mais simples |

Tenant que vende SaaS típico:
- Cliente autoriza ao assinar
- Próxima cobrança é mensal
- Quer "1 QR pra autorizar + pagar primeira"

## Decisão

**Jornada 3 (PAYMENTONAPPROVAL) como default, com Jornada 2 como fallback enterprise.**

Razões pra Jornada 3:
- Onboarding mais rápido (1 ação do cliente faz autorização + 1ª cobrança)
- Menos abandono (autorizar QR sem pagar é leve, mas algumas pessoas hesitam)
- Já está pronta na maioria dos PSPs principais (Banco do Brasil, Itaú, Bradesco, Sicoob, Woovi)
- Lib `Woovi PHP SDK` ou similar suporta nativo

Quando usar Jornada 2:
- Tenant Enterprise quer autorização sem pagamento (típico em B2B onde o serviço só começa após approval)
- Cliente final precisa autorizar antes de receber o serviço (academia, plano de saúde)

Jornada 1 — não fazer. Complexidade alta sem retorno claro pra MVP.

## Consequências

**Positivas:**
- Onboarding cliente final: 1 click "Pagar com Pix Automático" → QR → app banco → pronto
- 1ª cobrança imediata + autorização viva pras próximas
- Adoção mais rápida (PSPs tem essa jornada bem testada em 2025-2026)
- Mais barato que cartão → tenant pode passar economia ao cliente

**Negativas:**
- Limita escolha em casos extremos (B2B autorização-sem-pagamento) — Jornada 2 cobre
- PSP que não suporta Jornada 3: filtra fora (~10% PSPs em 2026)
- Refunding: cancelar autorização precisa fluxo claro (cliente autorizou no app banco; cancelar via API)

## Pattern obrigatório

```php
class PixAutomaticoJornada3Service {
    public function gerarAutorizacaoComCobranca(Contract $c, Invoice $primeiraInvoice): Authorization {
        $auth = Authorization::create([
            'business_id' => $c->business_id,
            'contract_id' => $c->id,
            'jornada' => 3,
            'limite_max' => $c->plan->valor_max_autorizacao,
            'status' => 'created',
        ]);

        // Chama PSP — gera txid + QR
        $psp = $this->resolvePsp($c->business);
        $resp = $psp->createJrc3([
            'auth' => $auth,
            'invoice' => $primeiraInvoice,
        ]);

        $auth->update(['psp_txid' => $resp->txid, 'qr_string' => $resp->qrString]);
        return $auth;
    }
}
```

## Tests obrigatórios

- `Jornada3CriacaoTest` — gera autorização + 1ª cobrança no mesmo QR
- `Jornada3WebhookAtivacaoTest` — webhook ativa autorização após pagamento
- `Jornada3CobrancaSubsequenteTest` — usa autorização ativa pra próximo ciclo
- `Jornada3CancelamentoTest` — cancelar autorização não permite mais cobranças

## Decisões em aberto

- [ ] PSP MVP: Woovi (cobre maioria) ou direto banco (Sicoob — banco da ROTA LIVRE)?
- [ ] Jornada 2 como fallback automático ou só pra Enterprise?
- [ ] Cliente final pode mudar limite_max via app? Ou só cancelar e re-autorizar?
- [ ] Cobrança noturna 03:00 ou horário comercial pra evitar surpresa cliente?

## Alternativas consideradas

- **Jornada 1** — rejeitado: complexidade sem retorno
- **Default Jornada 2** — rejeitado: onboarding mais lento, abandono maior
- **Não implementar Pix Automático** — rejeitado: cartão recorrente é caro e taxa aprovação ruim; Pix Automático é a vantagem competitiva

## Referências

- BCB — manual Pix Automático JRC
- Woovi (https://woovi.com) — referência implementação JRC 3
- US-RB-020, US-RB-021 (SPEC)
- ARQ-0001 (módulo separado)
