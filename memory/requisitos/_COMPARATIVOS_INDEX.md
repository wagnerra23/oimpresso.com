# 🏆 Dashboard de Comparativos com Concorrência

> Estilo **Capterra**: cards visuais, scores 0-100, matriz features, pros/cons, pricing.
> Cobertura: **10 módulos** | Cadência: trimestral | Última auditoria geral: **2026-04-25**

---

## 📊 Ranking visual — Score nosso vs líder

```
Módulo              Nosso        Líder           Gap     Status
═══════════════════════════════════════════════════════════════════
Financeiro          🟢 75/100    Conta Azul 85   -10     em produção
PontoWr2            🟡 52/100    Pontomais 84    -32     em desenvolvimento
MemCofre            🟡 62/100    GDrive 83       -21     em uso
Grow                🟡 50/100    HubSpot 87      -37     legado/incerto
LaravelAI (técnico) 🔴 14/100    Prism 76        -62     adotar lib FOSS
Chat                ⚫ 0/100     Crisp 82        -82     perdido — integrar
BI                  ⚫ 0/100     Looker 84       -84     descartar→Copiloto
NfeBrasil           ⚫ 0/100     FocusNFE 83     -83     spec-ready
RecurringBilling    ⚫ 0/100     Pagar.me 85     -85     spec-ready
Copiloto            ⚫ 0/100     ChatGPT 50      aposta  spec-ready (diferencial)

Legenda: 🟢 ≥70  🟡 30-69  🔴 1-29  ⚫ 0 (n/a)
```

---

## 🎯 Tabela executiva

| # | Módulo | Score nosso | Líder | Gap | Estágio | Estratégia | Doc |
|---|---|---|---|---|---|---|---|
| 1 | **Financeiro** | 🟢 75 | Conta Azul 85 | -10 | produção (5 telas) | imitar OCR + dif. tela única | [📄](Financeiro/COMPARATIVO_CONCORRENCIA.md) |
| 2 | **PontoWr2** | 🟡 52 | Pontomais 84 | -32 | dev avançado | imitar mobile + dif. dashboard live | [📄](PontoWr2/COMPARATIVO_CONCORRENCIA.md) |
| 3 | **MemCofre** | 🟡 62 | GDrive 83 | -21 | em uso (basic) | bundled + integração IA Copiloto | [📄](MemCofre/COMPARATIVO_CONCORRENCIA.md) |
| 4 | **Grow** | 🟡 50 | HubSpot 87 | -37 | legado, status incerto | revisar — talvez integrar HubSpot | [📄](Grow/COMPARATIVO_CONCORRENCIA.md) |
| 5 | **LaravelAI** | 🔴 14 | Prism PHP 76 | -62 | spec | **NÃO BUILD** — adotar Prism + wrapper | [📄](LaravelAI/COMPARATIVO_CONCORRENCIA.md) |
| 6 | **Chat** | ⚫ 0 | Crisp 82 | -82 | perdido | **NÃO BUILD** — integrar Crisp/Jivo | [📄](Chat/COMPARATIVO_CONCORRENCIA.md) |
| 7 | **BI** | ⚫ 0 | Looker 84 | -84 | perdido | **DESCARTAR** — investir em Copiloto | [📄](BI/COMPARATIVO_CONCORRENCIA.md) |
| 8 | **NfeBrasil** | ⚫ 0 | FocusNFE 83 | -83 | spec-ready | imitar core + dif. emissão na venda | [📄](NfeBrasil/COMPARATIVO_CONCORRENCIA.md) |
| 9 | **RecurringBilling** | ⚫ 0 | Pagar.me 85 | -85 | spec-ready | imitar cobrança + dif. integrado UPos | [📄](RecurringBilling/COMPARATIVO_CONCORRENCIA.md) |
| 10 | **Copiloto** | ⚫ 0 | ChatGPT 50 (s/ contexto BR) | **aposta** | spec-ready | **DIFERENCIAR** — chat IA com contexto ERP | [📄](Copiloto/COMPARATIVO_CONCORRENCIA.md) |

---

## 🚨 Decisões estratégicas reveladas pelos comparativos

### ✅ Build próprio (vale ROI)
- **Financeiro** — diferenciação clara (tela única + integração POS)
- **PontoWr2** — diferenciação clara (dashboard live + bundle UPos)
- **NfeBrasil** — diferenciação clara (emissão na tela de venda)
- **RecurringBilling** — diferenciação clara (integrado Financeiro)
- **Copiloto** — diferenciação ÚNICA (contexto ERP + LGPD masking BR)
- **MemCofre** — diferenciação por bundle + alimenta Copiloto

### ⚠ Não-build, integrar ou adotar
- **LaravelAI** — adotar Prism PHP ou Spatie ecosystem (não reinventar)
- **Chat** — integrar Crisp ou JivoChat via webhook (1 sprint)

### ❌ Descartar
- **BI** standalone — Copiloto conversacional cobre o caso de uso PME

### 🔍 Revisar prioridade
- **Grow** — pode ser melhor integrar HubSpot Free + capturar lead no UPos. Investigar.

---

## 🏁 Track de fechamento de gap por módulo

### Módulos em produção (perto do líder)
- **Financeiro:** sprints 1-3 documentadas em [DOC_TELAS_E_SCORE.md](Financeiro/DOC_TELAS_E_SCORE.md) → meta **85/100**
- **PontoWr2:** roadmap M9-M11 (mobile + eSocial + biometria) → meta **80/100**
- **MemCofre:** OCR + versionamento + IA reuso → meta **75/100**

### Módulos a entregar MVP
- **NfeBrasil:** emissão NF-e/NFC-e + cancelar (3-4 sprints) → meta **70/100**
- **RecurringBilling:** plano + cobrança + dunning (4-6 sprints) → meta **75/100**
- **Copiloto:** chat + contexto + masking (3 sprints) → meta **65/100** (aposta)

### Módulos a integrar (não build)
- **LaravelAI:** POC Prism + wrapper LGPD (1 sprint)
- **Chat:** webhook Crisp → UPos contact (1 sprint)

---

## 💡 Vantagens competitivas estruturais (todos os módulos)

Todos os comparativos revelam o mesmo **moat estratégico do oimpresso**:

1. **Integração POS UPos nativa** — concorrentes BR não têm
2. **Dados unificados** entre módulos (Financeiro lê venda, Ponto lê funcionário, Copiloto lê tudo)
3. **Sem double-entry** — diferenciador estrutural
4. **Bundle de preço** — economia 30-50% vs comprar SaaS separados
5. **Architecture limpa** — Strategy Pattern + ADRs documentadas (sustentável)

---

## 🎬 Como usar este dashboard

### Ao iniciar trabalho em módulo
1. Abre [📄] do módulo → seção **"Onde PERDEMOS"** (P0)
2. Prioriza features P0 que fecham mais gap
3. Atualiza matriz após cada release

### Ao planejar onda nova
1. Compara score atual vs líder
2. Escolhe 1-2 features P0 + 1 diferencial
3. Cobertura funcional = métrica única ouro

### Ao escrever marketing
1. Pega "Pros nossos" como bullets
2. Posicionamento já está pronto (frase de venda)
3. Pricing comparativo já calculado

### Ao decidir build vs buy
1. Olha score do líder
2. Se gap > 70 e mercado maduro → integrar/descartar
3. Se temos diferencial estrutural → build com aposta

---

## 📐 Estrutura padrão de cada doc (template)

[Template universal](_TEMPLATE_COMPARATIVO_CONCORRENCIA.md) com 10 seções:

1. **Sobre o módulo** (best for, persona, JTBD)
2. **Cards de produtos** (1 por concorrente)
3. **Matriz de features** (✅/⚠/❌ + P0/P1/P2)
4. **Score 8 critérios** (ease/service/features/value/perf/mobile/integrations/onboarding)
5. **Pros & Cons**
6. **Pricing tiers**
7. **Integrações**
8. **Reviews públicas** (oportunidades)
9. **Estratégia 1-pager**
10. **Refs**

---

## 🔄 Cadência de revisão

| Tipo | Frequência | Ação |
|---|---|---|
| Atualização score nosso | pós-release significativo | atualizar scores + matriz |
| Auditoria geral | trimestral | revisar todos os 10 + adicionar novos concorrentes |
| Adicionar concorrente novo | quando surgir | adicionar card + atualizar matriz |
| Decisão estratégica | quando gap muda muito | revisar build vs buy |

**Próxima auditoria geral:** **2026-07-25**

---

## 📚 Refs gerais

- Capterra BR: https://www.capterra.com.br
- G2: https://www.g2.com
- B2BStack: https://b2bstack.com.br
- Memória relacionada:
  - [Revenue thesis](../reference_revenue_thesis_modulos.md)
  - [Roadmap Faturamento](../_Roadmap_Faturamento.md)
  - [Meta R$ 5mi/ano](../decisions/0022-meta-5mi-ano-financeira.md)
