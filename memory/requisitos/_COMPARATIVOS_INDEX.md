# Index — Comparativos Concorrência por módulo

> Estilo Capterra (cards + matriz + scores 0-100). Atualização a cada 3 meses ou release relevante.
> Template em [_TEMPLATE_COMPARATIVO_CONCORRENCIA.md](_TEMPLATE_COMPARATIVO_CONCORRENCIA.md).

## 📋 Comparativos prontos

| Módulo | Score nosso | Score líder | Gap | Doc |
|---|---|---|---|---|
| **Financeiro** | **75/100** | Conta Azul 85 | -10 | [📄](Financeiro/COMPARATIVO_CONCORRENCIA.md) |
| **PontoWr2** | **52/100** | Pontomais 84 | -32 | [📄](PontoWr2/COMPARATIVO_CONCORRENCIA.md) |
| **NfeBrasil** | **0/100** (n/a) | TecnoSpeed/FocusNFE 82 | -82 | [📄](NfeBrasil/COMPARATIVO_CONCORRENCIA.md) |
| **RecurringBilling** | **0/100** (n/a) | Pagar.me 85 | -85 | [📄](RecurringBilling/COMPARATIVO_CONCORRENCIA.md) |
| **Copiloto** | **0/100** (n/a) | ChatGPT 50 (s/ contexto BR) | aposta | [📄](Copiloto/COMPARATIVO_CONCORRENCIA.md) |

## 🟡 Pendentes (criar quando módulo for trabalhado)

| Módulo | Concorrentes a investigar | Prioridade |
|---|---|---|
| **Grow** | RD Station, HubSpot, ActiveCampaign | alta |
| **Officeimpresso** | (N/A — superadmin-only interno) | baixa |
| **MemCofre** | Notion, Obsidian, Confluence | média |
| **LaravelAI** | Vizra ADK, Spatie Laravel-data, Prism | técnico — pesquisar lib |
| **Chat** (módulo perdido na migração) | Intercom, Crisp, Tawk | revisitar |
| **BI** | Metabase, Looker Studio, Power BI | média |

## 🎯 Como usar

### Ao iniciar trabalho em módulo
1. Abrir o `COMPARATIVO_CONCORRENCIA.md` do módulo
2. Ler **"Onde PERDEMOS"** seção P0
3. Priorizar features P0 sobre features novas
4. Atualizar matriz após release

### Ao planejar onda nova
1. Comparar score atual vs concorrente líder
2. Escolher 1-2 features P0 que fecham mais gap
3. Adicionar diferencial (track diferenciar) por onda
4. Métricas: cobertura funcional + score normalizado

### Ao escrever marketing
- Pegar seção **"Onde GANHAMOS"** (3-5 bullets)
- Posicionamento (frase de venda) já está pronto
- Pricing comparativo já calculado

## 📐 Estrutura padrão (template)

Cada `COMPARATIVO_CONCORRENCIA.md` segue esse esqueleto:

1. **Sobre o módulo** (best for, persona, JTBD)
2. **Cards de produtos** (1 por concorrente: ⭐ score Capterra, 💰 preço, 🎯 best for, ✨ diferencial)
3. **Matriz de features** (✅/⚠/❌ + importância P0/P1/P2)
4. **Score detalhado** (8 critérios Capterra: ease/service/features/value/perf/mobile/integrations/onboarding)
5. **Pros & Cons** (nossos + do líder, 3-5 bullets cada)
6. **Pricing tiers** comparativo
7. **Integrações** comparativas
8. **Reviews / sentiment público** (oportunidades)
9. **Estratégia** (posicionamento + track imitar/diferenciar/preço + métricas)
10. **Refs** (links Capterra + ADRs + sites)

## 🔄 Cadence de revisão

- **Trimestral** (cada 3 meses) — atualizar matriz de features
- **Pós-release significativo** — atualizar score nosso
- **Pós-novo concorrente surgir** — adicionar card

## Última auditoria geral

**2026-04-25** — criados 5 comparativos (Financeiro, PontoWr2, NfeBrasil, RecurringBilling, Copiloto) + template + index. Cobertura 5/11 módulos do roadmap.

**Próxima auditoria:** 2026-07-25
