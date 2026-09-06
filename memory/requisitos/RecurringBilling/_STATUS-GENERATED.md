---
authority: generated
---

<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — RecurringBilling · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 29 |
| CU no SDD | 15 |
| Telas (.tsx) | 6 |
| Telas com `casos.md` | 6 |
| UC declarados | 36 |
| UC com teste que os cita | 36 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| `CU-RB-09` sem UC | caso de uso que o exercite — Emitir NFe automaticamente ao receber o pagamento |
| `CU-RB-13` sem UC | caso de uso que o exercite — Ler saldo e extrato do Inter PJ |
| `CU-RB-15` sem UC | caso de uso que o exercite — Ativar gateway em assinaturas de cobrança dormente |
| `US-RB-041` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Test de retry idempotente do ProcessAsaasWebhookJob |
| `US-RB-043` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — [Epic] Models Subscription/Plan/Invoice/ChargeAttempt + migr |
| `US-RB-044` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Listener InvoicePaid em NfeBrasil — emissão automática NFe55 |
| `US-RB-045` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Inter PJ — saldo via Banking API v2 (Fase 1 OF direto) |
| `US-RB-046` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Inter PJ — extrato sync diário + tela /financeiro/extrato (F |
| `US-RB-047` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Inter PJ — PIX cob imediata + webhook receiver (Fase 3) |
| `US-RB-048` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — RUNBOOK operacional antes do Inter PJ Banking API ir pra pro |
| `US-RB-051` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Inter PJ — webhook PIX receiver (CYCLE-06 G1 wiring Martinho |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-RB-001 | `desconhecido` | Cadastrar plano de assinatura |
| US-RB-002 | `desconhecido` | Criar contrato (subscription) |
| US-RB-003 | `desconhecido` | Gerar faturas em ciclo (job) |
| US-RB-004 | `desconhecido` | Cobrar fatura (charge attempt) |
| US-RB-005 | `desconhecido` | Cancelar contrato |
| US-RB-006 | `desconhecido` | Proração em upgrade/downgrade mid-cycle |
| US-RB-010 | `desconhecido` | Cadastrar credencial de gateway |
| US-RB-011 | `desconhecido` | Salvar cartão tokenizado de cliente |
| US-RB-012 | `desconhecido` | Receber webhook de gateway |
| US-RB-013 | `desconhecido` | Smart retry em soft decline |
| US-RB-020 | `desconhecido` | Solicitar autorização Pix Automático |
| US-RB-021 | `desconhecido` | Cobrar via Pix Automático autorizado |
| US-RB-030 | `desconhecido` | Configurar régua de inadimplência |
| US-RB-031 | `desconhecido` | Disparar régua quando cobrança falha |
| US-RB-040 | `desconhecido` | Cobertura Pest dos 3 drivers de boleto (Inter/C6/Asaas) |
| US-RB-042 | `desconhecido` | Completar cancelar() C6/Asaas + UI Cancelar título + audit log |
| US-RB-049 | `desconhecido` | Permissions UI: plans.manage, contracts.manage, webhooks.view (US-RB-001..005) |
| US-RB-050 | `desconhecido` | Inter PJ — PIX cobrança imediata (CYCLE-06 G1 wiring Martinho) |
| US-RB-052 | `todo` | Ativar gateway nas 109 assinaturas com gateway=NULL (cobranças dormentes) |
| US-RB-055 | `todo` | Aplicar recalibração de pricing (setup · trial · anual) — 3 ajustes |
| US-RB-056 | `todo` | Unificar as 3 implementações de "próximo vencimento" (NoOverflow/EN vs Overflow/ |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-RBCFG-01 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-RBCFG-02 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-RBCFG-03 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-RBCFG-04 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-RBCFG-05 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-RBCFG-06 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-RBCFG-07 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-RBCFG-08 | Configuracoes/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-01 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-02 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-03 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-04 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-05 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-06 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-07 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-08 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-09 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-10 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-11 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-12 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBFAT-13 | Faturas/Index | 🧪 aguarda veredito da lane |
| UC-RBPLN-01 | Planos/Index | 🧪 aguarda veredito da lane |
| UC-RBPLN-02 | Planos/Index | 🧪 aguarda veredito da lane |
| UC-RBPLN-03 | Planos/Index | 🧪 aguarda veredito da lane |
| UC-RBPNC-01 | Planos/Create | 🧪 aguarda veredito da lane |
| UC-RBPNC-02 | Planos/Create | 🧪 aguarda veredito da lane |
| UC-RBPNE-01 | Planos/Edit | 🧪 aguarda veredito da lane |
| UC-RBPNE-02 | Planos/Edit | 🧪 aguarda veredito da lane |
| UC-RBSUB-01 | Index | 🧪 aguarda veredito da lane |
| UC-RBSUB-02 | Index | 🧪 aguarda veredito da lane |
| UC-RBSUB-03 | Index | 🧪 aguarda veredito da lane |
| UC-RBSUB-04 | Index | 🧪 aguarda veredito da lane |
| UC-RBSUB-05 | Index | 🧪 aguarda veredito da lane |
| UC-RBSUB-06 | Index | 🧪 aguarda veredito da lane |
| UC-RBSUB-07 | Index | 🧪 aguarda veredito da lane |
| UC-RBSUB-08 | Index | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
