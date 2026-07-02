---
slug: 0121-oimpresso-modular-especializado-por-vertical
number: 121
title: "oimpresso é ERP modular especializado por vertical — núcleo comum + Modules/<Vertical> profundo"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-10"
module: null
quarter: 2026-Q2
tags: [arquitetura, posicionamento, modular, multi-vertical, produto, business-strategy]
supersedes: []
supersedes_partially: []
amends: [0011]
superseded_by: []
related: [0011-alinhamento-padrao-jana, 0024-instalacao-1-clique-modulos, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0105-cliente-como-sinal-guiar-sem-mandar, 0106-recalibracao-velocidade-fator-10x-ia-pair, 0119-migration-factory-capacidade-institucional]
pii: false
review_triggers:
  - "terceiro módulo vertical em produção com 3+ clientes pagando (atual: 0; previsto: ComunicacaoVisual, Vestuario, OficinaAuto)"
  - "cliente pagar mais pelo módulo vertical do que pelo núcleo (sinal de over-pricing núcleo OU under-pricing vertical)"
  - "revenue de módulos verticais ultrapassar 30% do MRR (vira foco — exige roadmap dedicado)"
  - "módulo vertical com <2 clientes pagantes após 12m no ar (candidato a aposentar via lifecycle: historical)"
---

# ADR 0121 — oimpresso é ERP modular especializado por vertical

## Contexto

Durante execução autônoma 2026-05-09 (~50 sub-agents Opus 4.7, ~5h wallclock, PR #372 mergeado), foi proposta a tese de produto **"oimpresso Insights"** com 5 pilares e posicionamento ambíguo entre vertical (comunicação visual) e horizontal (SaaS B2B PME).

Ao final da sessão, Wagner apontou **erro factual recorrente** em 14+ documentos: eu venha tratando ROTA LIVRE (cliente piloto biz=4, 99% volume) como **gráfica de SP**. Realidade descoberta:

- **ROTA LIVRE** = `LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME`, CNPJ 73.306.573/0001-11
- **Localização**: Termas do Gravatal/SC (não SP)
- **Setor**: vestuário (CNAE 4781-4/00) — não comunicação visual

Isso revelou tensão estratégica não-resolvida no projeto:

- **CLAUDE.md** posicionava: *"ERP gráfico brasileiro pra setor de comunicação visual"*
- **Cliente piloto único validado em produção**: vestuário em SC
- **Base WR Sistemas legacy** (37 bancos OfficeImpresso): ~70% gráficas/com.visual, ~10% oficinas, ~20% diversos (vestuário, móveis, alimentação, etc)

Wagner formulou Hipótese D durante conversa pós-PR #372:

> *"acho que vou fazer módulos especializados"*

Esta ADR formaliza essa hipótese como **princípio arquitetural canon**.

## Decisão

**oimpresso é um ERP brasileiro com arquitetura modular especializada por vertical**:

```
oimpresso (núcleo comum)
├── Multi-tenant Tier 0 (business_id global scope — ADR 0093)
├── Modules/Copiloto (Jana IA + memória persistente — ADR 0035-0053)
├── Modules/Financeiro (visão unificada AR/AP)
├── Modules/NfeBrasil (NFe/NFC-e/NFSe ponta-a-ponta)
├── Modules/RecurringBilling (assinaturas + boletos)
├── Modules/MemCofre (cofre senhas)
└── Modules/<Vertical>  ← ESPECIALIZAÇÕES PROFUNDAS POR SETOR
    ├── ComunicacaoVisual  (cálculo m², plotter, PCP gráfico, NFe-de-boleto)
    ├── Vestuario          (SKU+tamanho+cor, devolução, etiqueta) ← ROTA LIVRE
    ├── OficinaAuto        (placa, mecânico, tabela tempária Sindirepa)
    ├── Farmacia           (controlados, SNGPC, receita médica)
    ├── Salao              (agendamento, comissão por serviço, fidelidade)
    ├── Contabilidade      (multi-cliente, integrações Receita Federal)
    └── ... (extensível conforme sinal qualificado ADR 0105)
```

**Cada módulo vertical é um produto separado, vendável como add-on ao núcleo.**

## Princípios derivados

### P1 — Núcleo é SaaS B2B PME genérico, não vertical
Multi-tenant + Jana + Financeiro + NFe servem a **qualquer pequena empresa brasileira**. Não há vertical hardcoded no núcleo. CLAUDE.md anteriormente sugeria foco com.visual — corrigido por esta ADR.

### P2 — Módulos verticais são profundos, não rasos
Diferencial competitivo vs concorrentes horizontais (Bling/Tiny/Conta Azul) está na **profundidade vertical**. Modules/ComunicacaoVisual implementa cálculo de m², spool de plotter, PCP gráfico — coisas que Bling jamais terá. Modules/Vestuario implementa estoque por SKU+tamanho+cor — coisas que Mubisys (vertical com.visual) jamais terá.

### P3 — Cada módulo precisa sinal qualificado pra existir (ADR 0105)
Não criar módulo vertical sem cliente real pagando piloto OU 3+ outreach inbound em 90d. Hipóteses sem sinal viram **ADR feature-wish**, não US ativa.

### P4 — Schema multi-vertical é a base técnica (proposto F18)
- Tabela `verticals` lista módulos disponíveis
- `business.vertical_id` indica qual módulo o cliente assinou
- `business.vertical_attributes` JSON guarda configurações custom do módulo daquele cliente
- `verticals.attributes_schema` JSON define spec do módulo (campos obrigatórios)

### P5 — Pricing alinhado: núcleo + módulo
Cliente paga **base pelo núcleo** (ex: Pro R$ [redacted Tier 0]/m) + **add-on por módulo vertical** (ex: +R$ [redacted Tier 0]-199/m). Cliente paga proporcional ao que usa. ROTA LIVRE valida hoje: paga R$ [redacted Tier 0]/m que cobre núcleo + Modules/Vestuario completo.

### P6 — Concorrentes mapeados por módulo, não por produto
- **Núcleo oimpresso** vs Bling, Tiny, Conta Azul, Omie (horizontais raso)
- **Modules/ComunicacaoVisual** vs Mubisys, Zênite, Calcgraf, Calcme (vertical legacy)
- **Modules/Vestuario** vs Linx Microvix, ProMoz, Vendizap (vertical moda)
- **Modules/OficinaAuto** vs Mecânico, Auto Manager, Lokoz, OficinaMaster (vertical auto)
- **Modules/Farmacia** vs Inovafarma, FarmaSoft (vertical farmácia)

### P7 — Cliente piloto validador por módulo
- **Modules/Vestuario** ✅ ROTA LIVRE em prod (biz=4, 99% volume sistema novo)
- **Modules/ComunicacaoVisual** ⏸️ candidatos: 6 saudáveis OfficeImpresso (Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart) — migração legacy gera piloto
- **Modules/OficinaAuto** ⏸️ candidato: Martinho Caçambas (sinal qualificado pendente confirmação)
- **Outros módulos**: backlog ADR-feature-wish até 1+ piloto pagante por módulo

### P8 — Modules/Repair atual fica como infraestrutura compartilhada (não Modules/OficinaAuto)
[Modules/Repair](../../Modules/Repair) implementa Kanban OS drag-drop genérico (PR #363). É reutilizável por **vários** módulos verticais (ComunicacaoVisual usa pra OS de impressão; OficinaAuto usaria pra OS de mecânico; Modules/Vestuario poderia usar pra OS de ajuste/reparo). Permanece como **shared infrastructure**, não vertical específico. (Decisão Wagner pendente — caso queira renomear, virá em ADR amendment futuro.)

## Implicações práticas

### Posicionamento atualizado

**Antes** (CLAUDE.md):
> *"ERP gráfico brasileiro pra setor de comunicação visual"*

**Depois** (esta ADR):
> *"ERP brasileiro com arquitetura modular especializada por vertical. Núcleo comum (multi-tenant + Jana IA + Financeiro + NFe) + módulos profundos por setor. Hoje em produção: vestuário (ROTA LIVRE). Em desenvolvimento: comunicação visual, oficina auto."*

### TAM (Total Addressable Market) revisado

| Vertical | Universo BR estimado | Estado oimpresso |
|----------|----------------------:|-------------------|
| Vestuário | ~180k empresas | ✅ ROTA LIVRE em prod |
| Comunicação Visual | ~5-25k | ⏸️ Modules/ComunicacaoVisual em construção |
| Oficina Auto | ~120k | ⏸️ sinal qualificado pendente (Martinho) |
| Farmácia | ~80k | 🔒 backlog ADR feature-wish |
| Contabilidade | ~80k | 🔒 backlog (multiplicador potencial) |
| Salão Beleza | ~200k | 🔒 backlog |
| ... | ... | ... |

**TAM agregado**: ~1M+ empresas BR (vs ~25k vertical com.visual sozinho).

### Roadmap de módulos

| Fase | Módulo | Dependência | Cliente piloto | Prazo |
|------|--------|-------------|----------------|-------|
| ✅ **Hoje** | Vestuário | núcleo | ROTA LIVRE | em prod |
| 🟡 **Q3/26** | ComunicacaoVisual | schema multi-vertical (F15+F18) | 1 dos 6 saudáveis OfficeImpresso (migração legacy) | jul-set/26 |
| 🟡 **Q4/26 ou Q1/27** | OficinaAuto | sinal qualificado real | Martinho Caçambas (a confirmar) ou outro | conforme sinal |
| 🔒 **Futuro** | Farmacia, Salao, Contabilidade, etc | 3+ pilotos pagantes por módulo | a identificar | aberto |

### Lifecycle de módulo vertical

Cada módulo segue lifecycle:
1. **proposto** — ADR feature-wish (sem código)
2. **piloto** — 1 cliente real pagando (R$ [redacted Tier 0]-499/m base)
3. **ativo** — 3+ clientes pagantes, módulo estável
4. **maduro** — 10+ clientes, network effect benchmark setorial habilitado
5. **historical** — abaixo de 2 clientes ativos por 12m → revisar pra aposentar

Aposentar módulo vertical = manter código pros clientes legacy mas marcar `lifecycle: historical` na tabela `verticals` + parar de aceitar novos clientes nele.

## Consequências

### Positivas
- **Crescimento sem repensar arquitetura** — adicionar módulo é nWidart pattern já dominado
- **Diferencial competitivo claro** — núcleo competitivo + profundidade vertical onde precisa
- **TAM 40x maior** que vertical único (~1M+ empresas vs 25k)
- **Pricing modular** — cliente paga proporcional ao que usa, simples de comunicar
- **Resolve tensão posicionamento** — ROTA LIVRE = caso piloto Modules/Vestuario, válido
- **Reaproveita 26 anos expertise gráfica** — vira **Modules/ComunicacaoVisual** com profundidade que concorrente novo não consegue

### Negativas
- **Suporte multi-vertical com 5 pessoas** — exige disciplina ADR 0105 pra não dispersar
- **Marketing precisa ser multi-vertical** — não dá pra dizer "somos a Mubisys" nem "somos o Bling"
- **Onboarding por vertical** — cada módulo tem fluxo de setup específico
- **Dívida técnica de Modules/Repair** — vocabulário automotivo já vazou pra produção (placa, vehicle, brand, km, box) mesmo sendo módulo genérico — decidir formalmente se vira Modules/OficinaAuto ou se reverter pra termos genéricos

### Mitigações
- **ADR 0105 rigoroso** — só ativar módulo novo com sinal qualificado
- **Marketing horizontal pra topo de funil + vertical pra fundo** — site oimpresso.com mostra todos módulos; cold email é específico por vertical
- **Onboarding wizard por vertical** — Jana proativa pede atributos custom no momento contextual

## Alternativas consideradas

### A — Vertical com.visual exclusivo
- Mantém posicionamento atual CLAUDE.md
- ROTA LIVRE = exceção tolerada
- TAM: ~25k empresas
- ❌ rejeitado: ROTA LIVRE é 99% volume — não dá pra chamar de exceção

### B — Horizontal SaaS B2B PME genérico
- ERP raso multi-vertical estilo Bling/Tiny
- TAM: ~1M+ empresas, mas competição total
- ❌ rejeitado: perde diferencial vertical, briga preço com Bling (R$ [redacted Tier 0]/m) impossível com qualidade que oimpresso entrega

### C — Híbrido (oimpresso = horizontal com expertise vertical com.visual)
- Posicionamento ambíguo
- ❌ rejeitado: confunde mensagem comercial

### D — Modular especializado ✅ ESCOLHIDO
- Núcleo horizontal forte + módulos verticais profundos
- TAM: ~1M+ com profundidade onde precisa
- Cabe na arquitetura existente (nWidart laravel-modules)
- Pricing modular natural

## Arquivos afetados (alteração de CANON)

Esta ADR exige atualização nos seguintes documentos canônicos:

- [CLAUDE.md](../../CLAUDE.md) — frase de abertura "Por que existe" precisa refletir multi-vertical
- [memory/why-oimpresso.md](../why-oimpresso.md) — origem + posicionamento
- [memory/what-oimpresso.md](../what-oimpresso.md) — adicionar arquitetura modular
- [memory/decisions/proposals/PRODUTO-OIMPRESSO-INSIGHTS-MASTER-SPEC.md](proposals/PRODUTO-OIMPRESSO-INSIGHTS-MASTER-SPEC.md) — Hipótese D adotada
- [memory/decisions/proposals/OIMPRESSO-INSIGHTS-1PAGER.md](proposals/OIMPRESSO-INSIGHTS-1PAGER.md) — idem
- [memory/decisions/proposals/schema-multi-vertical-cnae-taxonomia.md](proposals/schema-multi-vertical-cnae-taxonomia.md) — agora é Pilar 0 (foundation), não Pilar 1
- 14+ arquivos com referência factual errada a ROTA LIVRE como gráfica/SP — fix-em-batch

## ADR amends

Esta ADR **emenda** [ADR 0011 (Alinhamento padrão Jana)](0011-alinhamento-padrao-jana.md) — clarificando que padrão modular nWidart é a base canônica de todo crescimento por vertical, não apenas ferramenta organizacional.

## Métricas de sucesso (review_triggers ativos)

- **12 meses**: 3 módulos verticais em produção, ≥2 clientes pagando cada
- **24 meses**: 5 módulos, network effect benchmark ativo em 2+ verticais
- **36 meses**: 10 módulos, ARR R$ [redacted Tier 0]M atingido principalmente via diversificação modular

Reverter pra Hipótese A (vertical com.visual exclusivo) se em 18m apenas 1 módulo (com.visual) tiver tração — sinal de que multi-vertical foi distração.

## Decisão pendente subordinada

- **Modules/Repair**: fica como infraestrutura compartilhada OU vira Modules/OficinaAuto?
  → Decisão Wagner pendente, vira ADR amendment se mudar.
  → Default: shared infrastructure até decisão explícita.
