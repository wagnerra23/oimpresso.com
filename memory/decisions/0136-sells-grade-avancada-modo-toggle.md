---
slug: 0136-sells-grade-avancada-modo-toggle
number: 136
title: "Sells: split Lista (default) vs Grade Avançada (toggle) — migração legacy OfficeImpresso sem chocar power-user"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-11"
module: sells
tags: [migration, ux, legacy, officeimpresso, comunicacao-visual, grid, power-user, ADR-0105-cliente-sinal, ADR-0121-modular-vertical]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related: ["0094-constituicao-v2-7-camadas-8-principios", "0104-processo-mwart-canonico-unico-caminho", "0105-cliente-como-sinal-guiar-sem-mandar", "0107-emendation-0104-visual-comparison-gate-f3", "0121-oimpresso-modular-especializado-por-vertical"]
pii: false
review_triggers:
  - "≥3 clientes OfficeImpresso migrados reclamarem da Lista padrão → acelerar US Grade Avançada"
  - "Snapshot Firebird mostrar que <10% das interações no Delphi usam agrupamento/multiseleção/total → reduzir escopo Grade Avançada (focar só nas 3 P0)"
  - "ADR 0105 (cliente como sinal) reinterpretada → revisar quais US do bloco 'feature-wish' viram ativas"
---

# ADR 0136 — Sells: split Lista (default) vs Grade Avançada (toggle)

## Contexto

A tela `Modules/.../Sells/Index.tsx` hoje é **Lista enxuta** (5 colunas: Data, Nº fatura, Cliente, Total, Pago, Status, Fiscal) com 3 KPIs (Abertas / Atrasadas / Total) e 5 pills (Todas/Pago/A receber/Parcial/Atrasadas). Funciona bem pra **ROTA LIVRE biz=4** (vestuário, ~113 vendas, 99% do volume), validada em produção (US-SELL-008 mergeada).

O legacy **OfficeImpresso Delphi** (WR Sistemas — gráficas) usa grade **DevExpress densa** com 30+ colunas, agrupamento drag-to-group, multiseleção, totalizador rodapé (R$ 16.763.317,54 visível ao pé), sub-linha de produtos por venda (MEDIDAS · Quant · R$ Valor · R$ Total · Situação), filtros multi-data (Emissão / NF / Faturamento / Competência / Prometido) com presets Dia/Semana/Mês/Ano, e impressão batch de vendas selecionadas.

Os 6 candidatos saudáveis pra migração (Vargas, Extreme, Gold, Zoom, Fixar, Produart — `reference_clientes_ativos.md`) são **power-users** com 10-26 anos de uso desse grid. Migrar pra Lista enxuta sem opção avançada = **risco alto de churn** ("não consigo mais ver tudo de uma vez", "perdi os totais", "perdi os agrupamentos").

Por outro lado, **clonar a tela Delphi 1:1** no Inertia/React quebra:
- a metáfora Cockpit (ADR 0039 — list+detail, foco em 1 venda por vez)
- a regra "Charter > Spec" (ADR 0094 §3) — tela densa exige charter próprio
- "cliente como sinal qualificado" (ADR 0105) — assumir que TODOS os 12 comportamentos do legacy são necessários é feature-wish; só sinal real (frequência de uso medida no Firebird) qualifica scope

## Decisão

**Modules/.../Sells/Index.tsx ganha um toggle `"Lista" / "Grade Avançada"` no header**, persistido em `localStorage` (`oimpresso.sells.viewMode`):

- **Modo "Lista" (default todos clientes novos + ROTA LIVRE)** — tela atual sem mudança. Manda no cliente que nasceu no oimpresso.
- **Modo "Grade Avançada" (default automático pra `business.legacy_origin = 'officeimpresso'`)** — grade densa com as 12 capacidades do Delphi, implementadas incrementalmente conforme **sinal qualificado**:

| # | Capacidade | Priority | Sinal pra ativar |
|---|------------|----------|------------------|
| 1 | Toggle "Lista / Grade Avançada" + viewMode persist | **P0** | Pré-requisito arquitetural — todas demais dependem |
| 2 | Multiseleção (checkbox por linha + ações em lote) | **P0** | Padrão moderno em qualquer grid empresarial — sinal trivial |
| 3 | Totalizador rodapé (Qtd vendas + Σ R$ filtrado) | **P0** | Power-user OfficeImpresso menciona em **toda** demo |
| 4 | Filtros multi-data com presets Dia/Semana/Mês/Ano + custom | P1 | Snapshot Firebird mostrar uso ≥30% das sessões |
| 5 | Agrupamento drag-to-group por campo do grid | P1 | Snapshot Firebird mostrar uso ≥20% das sessões |
| 6 | Especificação campo "Status" (financeiro / produção / fiscal — desambiguar com badge) | P1 | Reclamação cliente migrado ("não sei qual Status") |
| 7 | Especificação campo "Data" (emissão / NF / faturamento / competência / prometido) | P1 | Reclamação cliente migrado ("qual data é essa?") |
| 8 | Sub-linha de produtos por venda (expandir linha → MEDIDAS · Qtd · R$ Valor · R$ Total · Situação) | P2 | Snapshot Firebird mostrar uso de "Expandir produto" ≥15% |
| 9 | Status produção visível na lista (badge separado de Status financeiro) | P2 | Cliente migrado pedir explicitamente OU comparativo CAPTERRA-FICHA flag P0 |
| 10 | Campo "venda agrupada" explícito (não inferir de `ativo criado` string como Delphi faz) | P2 | Reclamação cliente migrado ("confuso saber o que está agrupado") |
| 11 | Botões agrupamento rápido (1-click pra agrupamentos comuns: Por Cliente, Por Status, Por Mês) | P3 | Telemetria: >5 usuários agrupam manualmente pelos mesmos 3 campos ≥10x/semana |
| 12 | Impressão batch de vendas selecionadas (PDF consolidado) | P3 | Cliente migrado pedir explicitamente |

**P0 entra independente de sinal** porque:
- (1) é pré-requisito arquitetural (sem o toggle, nada das outras 11 cabe)
- (2) e (3) são **expectativa de qualquer grid 2026** (multiseleção e total no rodapé) — não é feature-wish, é higiene UX. CAPTERRA-FICHA gráfica (Mubisys, Zênite, Calcgraf) tem ambas.

**P1+ entra só com sinal qualificado** (snapshot Firebird via skill `officeimpresso-financial-snapshot` OU reclamação documentada de cliente migrado) — alinhamento estrito com [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md).

## Critério de auto-toggle por business

```php
// HandleInertiaRequests::share('sells.viewMode.default')
$default = match (true) {
    Auth::user()->settings['sells_view_mode'] ?? null !== null
        => Auth::user()->settings['sells_view_mode'],          // user override em qq momento
    session('business.legacy_origin') === 'officeimpresso'
        => 'grade-avancada',                                    // power-user migrado
    default
        => 'lista',                                             // ROTA LIVRE + novos
};
```

Campo `business.legacy_origin` é novo (`nullable VARCHAR(32)`) — preenchido em (a) migration retroativa pros 6 candidatos OfficeImpresso saudáveis, (b) wizard de onboarding "De qual sistema você vem?" pra novos clientes que importam do legacy.

## Por que NÃO 2 telas separadas

Avaliada alternativa: `Modules/Sells/Pages/Index.tsx` (lista) + `Modules/Sells/Pages/IndexAvancado.tsx` (grade). Rejeitada porque:
- Duplica `useEffect` de fetch JSON, paginação, filtros, busca, sort, drawer
- Dois URLs (`/sells` e `/sells-avancado`) confundem clientes que migram de empresa A pra B
- Toggle interno preserva URL único (`/sells`) com `?view=grade` opcional pra deep-link

## Implementação progressiva

US-SELL-015..026 no [Sells/SPEC.md](../requisitos/Sells/SPEC.md):
- **P0 ativos agora:** US-SELL-015 (toggle + Grade base), US-SELL-016 (multiseleção), US-SELL-017 (totalizador rodapé)
- **P1+ feature-wish:** US-SELL-018..026 — status `todo` no SPEC mas com label `aguarda-sinal-firebird` no campo `origin`; só viram task ativa no MCP via `tasks-create` quando o snapshot Firebird (skill `officeimpresso-financial-snapshot`) ou reclamação documentada qualificar

## Consequências

✅ Cliente novo (vestuário, oficina, prestador serviço) cai na Lista padrão — UX limpa, Cockpit canon.
✅ Cliente migrado OfficeImpresso cai automaticamente em Grade Avançada — power-user mantém produtividade.
✅ Escopo trabalho controlado por sinal — não construímos 9 features que ninguém usa.
✅ Reversibilidade: toggle no header sempre disponível; se cliente migrado preferir Lista, basta clicar.
✅ ADR 0094 §1 (Context as a product) honrado — o "produto" do power-user gráfica é o grid denso; pra cliente novo o "produto" é o Cockpit list+detail.

⚠️ Risco: GradeAvancada vira gambiarra de manter se time não tratar como **viewMode** (1 componente, 2 layouts) e sim como 2 componentes paralelos. Mitigação: PR US-SELL-015 estabelece o pattern (props-driven, `viewMode === 'grade-avancada' && <GradeAvancadaLayout />` dentro do mesmo arquivo), e charter `Modules/Sells/Index.charter.md` (S4) documenta "Anti-hooks: não duplicar fetch/state — só layout".

⚠️ Risco: Charter "Grade Avançada" pode atrair pedido "põe gráfico, põe IA, põe dashboard" — escopo creep clássico. Mitigação: ADR 0105 enforce — sem sinal, sem US; sem US, sem código.

## Refs

- [ADR 0094 §1, §3](0094-constituicao-v2-7-camadas-8-principios.md) — Context as product + Charter > Spec
- [ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) — processo migração Blade → Inertia/React
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado (governance de feature-wish)
- [ADR 0107](0107-emendation-0104-visual-comparison-gate-f3.md) — gate visual F3
- [ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md) — modular especializado por vertical (ComunicacaoVisual vs Vestuario)
- Skill [officeimpresso-financial-snapshot](../../.claude/skills/officeimpresso-financial-snapshot/SKILL.md) — Firebird query pra qualificar P1+
- [reference_clientes_ativos.md](../auto-mem-pending/reference_clientes_ativos.md) — 6 candidatos saudáveis
- Resources legacy [reference_legacy_delphi_firebird.md](../auto-mem-pending/reference_legacy_delphi_firebird.md)
