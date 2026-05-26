

# oimpresso · Modules/OficinaAuto

> **Charter executivo 1-pager** · pra Martinho Caçambas · 13/maio/2026
>
> ⚠️ **REGISTRO HISTÓRICO 2026-05-13 — vocabulário desatualizado pós-[ADR 0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) (2026-05-26).** Este documento foi escrito **antes** da descoberta de que Martinho é sub-vertical 4 mecânica pesada caminhão basculante (CNAE 4520), NÃO locação caçamba container CNAE 4581. Vocabulário canon atualizado em [BRIEFING.md vigente](../BRIEFING.md). Próxima apresentação pra Martinho (ou novo cliente sub-vertical 4) deve usar 1-pager NOVO refletindo: peça hidráulica · PTO · kit hidráulico · hora-trabalho · NÃO m³/diária/locação. Preserva-se este histórico pro registro da reunião 2026-05-13 que ancorou ativação ADR 0171.

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

### Núcleo geral (todo cliente usa)

| Feature | Status |
|---|---|
| **Estoque de produtos** (peças, lonas, materiais) — multi-unidade (kg/un/m), alerta estoque baixo, controle por variação | ✅ LIVE — UltimatePOS legacy |
| **Venda balcão / POS** (cliente compra material no ato) — gera NFC-e | ✅ LIVE — `/pos/create` |
| **Vendas faturadas** (cliente compra a prazo) — Grade Avançada com KPIs, filtros, agrupamento | ✅ LIVE — `/sells` |
| **Boletos automáticos** Inter PJ + PIX Asaas — webhook confirma pagamento | ✅ canary 7d |
| **Cobrança recorrente** (mensalidade locação caçamba) | ✅ LIVE — Modules/RecurringBilling |
| **NFe / NFC-e** (manifestação destinatário, inutilização) | ✅ LIVE — Modules/NfeBrasil |
| **Financeiro consolidado** (AR + AP + saldo Inter PJ + extrato) | ✅ LIVE — Modules/Financeiro |
| **WhatsApp Inbox** multi-atendente + macros + SLA + auto-link CRM | ✅ LIVE — Modules/Whatsapp |
| **CRM Contatos** (clientes, fornecedores, histórico) | ✅ LIVE |
| **IA Jana** (memória persistente + brief diário + cobrança automática inadimplente) | ✅ LIVE — OpenAI gpt-4o-mini |
| **Multi-tenant Tier 0** (seus dados isolados por business) | ✅ canon — ADR 0093 |

### Modules/OficinaAuto (vertical específica pra você — NOVO desde 11/maio)

| Feature | Status |
|---|---|
| Cadastro Caçambas (placa, capacidade m³, status) | ✅ LIVE |
| Dashboard 4 KPIs (Disponíveis / Locadas / Manutenção / Atrasada) | ✅ LIVE |
| Ordens de Serviço (locação + manutenção) | ✅ LIVE |
| FSM Pipeline canônico (Disponível → Locada → Recolhida + ramo Manutenção) | ✅ LIVE prod (ADR 0143) |
| Drawer com botões dinâmicos (Iniciar locação / Recolher / Concluir manutenção) | ✅ LIVE Wave 7 |
| Auditoria LGPD (quem mudou o quê e quando) | ✅ LIVE |
| Link Venda → OS (1 clique cria OS pra cobrar a locação) | ✅ LIVE |

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
