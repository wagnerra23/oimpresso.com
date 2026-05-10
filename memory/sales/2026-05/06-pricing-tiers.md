# Pricing tiers — oimpresso `[draft — Wagner valida]`

> Versão tentativa pra discussão. Todos os números abaixo precisam Wagner aprovar antes de uso comercial.
> Premissa: meta R$ [redacted Tier 0]M/ano (ADR 0022). Take rate alvo + assinatura recorrente.

---

## Resumo dos 3 tiers

| | **Starter** | **Pro** | **Enterprise** |
|---|---|---|---|
| **Mensal** | R$ [redacted Tier 0] `[draft]` | R$ [redacted Tier 0] `[draft]` | R$ [redacted Tier 0] `[draft]` |
| **Setup fee** | R$ [redacted Tier 0] | R$ [redacted Tier 0] `[draft]` | R$ [redacted Tier 0] `[draft]` |
| **Compromisso** | mensal | 12 meses | 24 meses |
| **Multi-business** | 1 | 1 | até 5 |
| **Usuários** | até 3 | até 10 | ilimitado |
| **Vendas/mês** | até 200 | até 1.000 | ilimitado |
| **NFe/mês** | até 100 (avulsa manual) | até 500 (auto-emissão) | ilimitado |
| **Suporte** | e-mail 48h | e-mail 24h + chat | dedicado + WhatsApp 4h |
| **Treinamento** | vídeo gravado | 2h ao vivo | 8h ao vivo + on-site `[se SP]` |
| **Migração dados** | self-service | guiada (incluso setup) | full-service (incluso setup) |
| **Rollback 30d** | sim | sim | sim |

---

## Módulos por tier

| Módulo | Starter | Pro | Enterprise |
|---|---|---|---|
| Vendas / Compras (UltimatePOS core) | ✅ | ✅ | ✅ |
| Financeiro Visão Unificada | ✅ (limite 200 faturas AR ativas) | ✅ + Asaas/Inter/C6 nativo | ✅ + multi-business consolidação |
| NfeBrasil | manual | auto-emissão por boleto pago | + contingência SVC |
| Repair (produção drag-drop) | colunas fixas | colunas customizáveis | + auditoria de tempo + SLA |
| Jana IA | ❌ (add-on R$ [redacted Tier 0]/mês) | ✅ (500 perguntas/mês) | ilimitado + memória cross-business |
| RecurringBilling | manual | automatizado | + dunning customizado |
| MemCofre (cofre senhas equipe) | ❌ | ✅ | ✅ |
| API pública | ❌ | read-only | full + webhooks |

---

## Add-ons (qualquer tier)

| Add-on | R$/mês `[draft]` |
|---|---|
| Jana IA (Starter) | 199 |
| +500 perguntas Jana | 99 |
| +1 business adicional (Pro) | 199 |
| Treinamento extra (1h ao vivo) | 250 one-shot |
| Customização tela (1 page Inertia) | 1.500 one-shot |
| Migração assistida (Pro) | 1.500 one-shot |
| Integração Bling/Tiny (one-way leitura) | 99 |

---

## Comparativo concorrentes `[validar números]`

| | **oimpresso Pro** | **Bling** | **Conta Azul + Asaas** | **Zênite (vertical)** | **Mubisys** |
|---|---|---|---|---|---|
| Mensal | R$ [redacted Tier 0] | R$ [redacted Tier 0]-450 | R$ [redacted Tier 0] + 1% boleto | `[validar]` | `[validar]` |
| NFe automática por boleto pago | ✅ nativo | ❌ | ❌ | ❌ | ❌ |
| IA chat sobre o negócio | ✅ Jana | ❌ | ❌ | ❌ | ❌ |
| Drag-drop produção | ✅ | parcial | ❌ | ✅ | ✅ |
| Vertical com. visual | ✅ nativo | ❌ genérico | ❌ genérico | ✅ nativo | ✅ nativo |
| Cálculo m² adesivo | ✅ | ❌ | ❌ | ✅ | ✅ |
| Multi-tenant Tier 0 governado | ✅ ADR 0093 | n/a | n/a | `[validar]` | `[validar]` |
| Stack moderna (React 19) | ✅ | parcial | parcial | legacy | legacy |

---

## Política de desconto (Wagner aprova caso a caso)

- **Cliente piloto SP referência:** -50% Pro mensal por 12m em troca de case escrito + autorização menção. ROTA LIVRE já tem (legacy).
- **Anual à vista:** -10% sobre 12 meses
- **Bianual à vista:** -15% sobre 24 meses
- **Indicação cliente atual:** -1 mês grátis pro indicador, -1 mês pro indicado (cap: 3/ano por cliente)
- **MEI/microempresa (faturamento <R$ [redacted Tier 0]k/mês):** Starter R$ [redacted Tier 0] `[draft]` (vs R$ [redacted Tier 0]) — entrada qualificada

---

## Notas estratégicas

- **Setup fee é guard-rail anti-tire-kicker.** Cliente que não paga setup raramente vinga.
- **Compromisso 12m no Pro** porque migração custa caro (suporte interno) — recuperar em <12m fica difícil.
- **Jana como add-on no Starter, incluso no Pro** — incentiva upgrade. Quem prova Jana raramente volta.
- **Enterprise é caro de propósito** — só faz sentido pra gráfica >R$ [redacted Tier 0]k/mês ou rede multi-loja.
- **Não competir por preço com Bling.** Bling é genérico R$ [redacted Tier 0] oimpresso é vertical R$ [redacted Tier 0] **Não é mesmo produto.**
- **Não dar preço sem demo.** Tabela acima vai pra interno — pra prospect, sempre pacote escrito após demo.

---

**Refs internas:** ADR 0022 (meta R$ [redacted Tier 0]M/ano), `reference_revenue_thesis_modulos.md` (auto-mem), `reference_concorrentes_com_visual.md` (auto-mem).
