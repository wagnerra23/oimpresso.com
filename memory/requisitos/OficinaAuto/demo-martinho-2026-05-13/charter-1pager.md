# oimpresso · Modules/OficinaAuto

> **Charter executivo 1-pager** · pra Martinho Caçambas · 13/maio/2026

---

## Quem somos

**oimpresso** — ERP brasileiro modular, Laravel + React, multi-tenant, 26 anos no mercado (sucessor do Office Impresso desktop). Construído com IA pareada — entregamos **10× mais rápido** que ERP tradicional.

---

## Por que esse módulo existe

Amostramos os 4 maiores clientes saudáveis migrando do Office Impresso desktop. **2 dos 4 são oficina** (você + um cliente de recapagem). 50% do nosso sample = **sinal qualificado** pra construir vertical dedicado:

> **Modules/OficinaAuto** — vertical mecânica/recapagem/locação de veículos

CNAEs cobertos: `4520-0/01` (mecânica) · `2212-9/00` (recapagem) · **`4581-4/00` (locação caçambas — você)**

---

## O que está PRONTO hoje (em produção)

| Feature | Status | Evidência |
|---|---|---|
| Modules/OficinaAuto V0 — Veículos + Ordens de Serviço (8 telas) | ✅ LIVE | PR #556, 11/maio |
| FSM Pipeline canônico (estado de venda + auditoria LGPD) | ✅ LIVE prod biz=1 | ADR 0143, 12/maio · 50 PRs em ~10h |
| WhatsApp Inbox (multi-atendente + macros + SLA) | ✅ LIVE | 11+ PRs cycle WhatsApp |
| NFe Brasil (NFC-e + NF-e + manifestação destinatário) | ✅ LIVE | Modules/NfeBrasil |
| Inter PJ Banking (boletos + PIX + saldo automático) | ✅ canary 7d | RUNBOOK pronto |
| Financeiro consolidado (AR + AP + boletos) | ✅ LIVE | Modules/Financeiro |
| IA Jana (memória persistente + brief diário) | ✅ LIVE | OpenAI gpt-4o-mini |
| Multi-tenant Tier 0 (isolamento por business) | ✅ canon | ADR 0093 |

---

## Roadmap específico Martinho Caçambas (~2 meses)

```
✅ V0 fundação (PR #556)             ── 11/maio    feito
◐ V1 importer Firebird → MySQL      ── +2 semanas  91 caçambas + 44k vendas migradas
○ V2 NFSe locação automática        ── +3 semanas  CNAE 4581-4/00, município SP
○ V3 cobrança Inter PJ              ── +1 semana   boleto/PIX automático na locação
○ V4 IA Jana cobrança WhatsApp      ── +4 semanas  inadimplente + classificação prioridade
                                       ─────────
                          Total: 10 semanas pra ter tudo
```

---

## Por que migrar (5 motivos concretos)

1. **Não perde caçamba.** Cada locação tem timeline auditada (quem alugou, quando saiu, prazo, status). Sistema avisa antes de atraso.
2. **Cobrança automática.** Boleto Inter PJ emitido no início da locação · baixa quando cliente paga · IA cobra inadimplente educadamente via WhatsApp.
3. **Atendimento profissional.** WhatsApp integrado, vários atendentes, histórico unificado, macros pra resposta rápida.
4. **Visibilidade total.** Dashboard mostra: caçambas disponíveis, locadas, em manutenção, atrasadas — em 1 segundo.
5. **Sem amarras.** Cloud, multi-dispositivo, backup automático. Office Impresso desktop fica como fallback durante migração faseada (V0 → V4).

---

## Garantias

- **Importação reversível.** Tudo é dry-run primeiro. Você valida antes de migrar de verdade.
- **Multi-tenant Tier 0.** Seus dados são isolados por business — outras empresas no mesmo servidor não conseguem ver nada seu (ADR 0093 — princípio constitucional do projeto).
- **LGPD.** Consent columns + auditoria append-only + opt-in WhatsApp e email.
- **Office Impresso continua rodando** durante migração até feature equivalente estar pronta.
- **Beta 30 dias gratuito** — zero risco financeiro pra você nos primeiros 30 dias.

---

## Próximo passo (você escolhe)

| Opção | O que ganha | Risco financeiro |
|---|---|---|
| **A — Beta 30d** | Importamos 91 caçambas em ambiente teste · usa em paralelo · decide depois | R$ 0 |
| **B — Migração faseada** | V1→V4 em 2 meses · cobrança só começa em V2 LIVE | Baixo (paga só quando funciona) |
| **C — Pacote completo** | Tudo de uma vez + treinamento + suporte premium | Médio (pricing baseado em sizing) |

---

**Contato:** Wagner Rocha · oimpresso · [contato pessoal]

> *"Construído pela WR2 Sistemas — mesma equipe do Office Impresso desktop há 26 anos."*
