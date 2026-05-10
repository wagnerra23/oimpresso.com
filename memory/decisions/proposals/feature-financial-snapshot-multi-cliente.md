---
title: Feature proposta — Financial Snapshot multi-cliente como produto pago
status: proposed (Wagner valida)
date: 2026-05-09
author: Claude Opus 4.7 (sub-agent autônomo)
type: feature wish ADR-eligible
relates: officeimpresso-financial-snapshot (skill), RUNBOOK-financial-snapshot-cliente.md
---

# Feature proposta — Financial Snapshot multi-cliente como produto pago

## Sinal qualificado origem

- 2026-05-09: análise piloto ServidorWR2 revelou achados que Wagner não tinha visibilidade clara (déficit -R$ [redacted Tier 0]k 12m, R$ [redacted Tier 0]k em atrasos)
- Wagner pediu: *"essa ideia merece uma skill, runbook e feature que pode ser cobrada"*
- Confirmou: *"esse tipo de análise deve ser feito com todos os clientes. anote isso vai ter rotinas para isso"*

**Hipótese**: clientes legacy OfficeImpresso (38 bases ativas) **não sabem o estado financeiro real** porque sistema Delphi não gera dashboards modernos. Pagariam por um relatório mensal automatizado.

## O produto em 1 frase

> *"Conecte seu OfficeImpresso ao oimpresso. Receba todo dia 5 um dashboard financeiro completo (caixa, AR, AP, alertas) — sem trocar de sistema. R$ [redacted Tier 0]/m."*

## Por que isso é wedge brutal

1. **Conversa começa com valor** — prospect recebe relatório antes de qualquer pitch. Trial = 1 mês grátis sem ser pedido.
2. **Lead magnet pra migração** — depois de 3-6 meses recebendo relatório bom, prospect aceita migrar pro oimpresso.com novo (já confia no produto).
3. **Diferencial competitivo** — Mubisys/Zênite/Calcgraf não têm "leitor de banco legacy". oimpresso é único.
4. **Validation cruzada** — sabemos receita real do prospect antes de propor pricing (= cold email mais preciso).
5. **Auto-gera content** — 38 análises = 38 case studies (anonimizados) pra blog/LinkedIn = SEO orgânico.

## Modelo comercial

### Tier 1 — Snapshot (R$ [redacted Tier 0]/m)
- 1 banco OfficeImpresso conectado
- Relatório semanal automático (PDF + email)
- Dashboard web read-only (oimpresso.com/snapshot)
- Alertas push: déficit detectado, churn iminente, inadimplência alta
- Suporte email

### Tier 2 — Financial Pro (R$ [redacted Tier 0]/m)
- Tudo do Tier 1 +
- Dashboard interativo com filtros + drill-down
- Histórico 5 anos completo
- Plano de cobrança automático (sugere quem cobrar primeiro, calcula ROI)
- API pra integrar com Bling/Conta Azul/Excel
- Suporte WhatsApp

### Tier 3 — Migration Ready (R$ [redacted Tier 0]/m)
- Tudo do Tier 2 +
- Comparativo lado-a-lado OfficeImpresso vs oimpresso.com
- Plano de migração assistido (download de cadastros, vendas, financeiro)
- 1 sessão Wagner mensal pra revisar achados
- Pricing-lock: se migrar nos 12m de assinatura, setup R$ [redacted Tier 0]

## Pricing strategy

- **Trial 30d grátis sem cartão** (Wagner conecta no banco, gera 1 relatório, manda pro cliente)
- **Anual paga 10** (Tier 1 anual: R$ [redacted Tier 0]/ano)
- **Setup**: zero default, R$ [redacted Tier 0] só pra integração customizada (banco em local incomum, charset diferente, etc)

## Implementação técnica

### MVP (8 semanas IA-pair = ~12 dias wallclock Felipe + Wagner pareados)

**Etapa 1 — Backend (40h IA-pair)**
- `Modules/OfficeImpressoSnapshot/` (módulo Laravel modular nWidart)
- Models: `LegacyConnection`, `LegacySnapshot`, `LegacyAlert`
- Service: `FirebirdConnector` (Python via shell-out OU PHP php-fdb extension)
- Job: `RunSnapshotJob` (queued, runs mensal/semanal)
- Hook DataController + sidebar entry
- Multi-tenant Tier 0 (cada subscriber tem seu próprio banco conectado)

**Etapa 2 — Frontend (30h IA-pair)**
- Pages Inertia: `Snapshot/Connections`, `Snapshot/Dashboard/{id}`, `Snapshot/Alerts`
- Components: `MonthlyReceitasChart`, `AlertBadge`, `TopClientesTable`
- Charter: charter.md ao lado de cada Page (MWART process)
- Visual-comparison.md obrigatória

**Etapa 3 — Subscription + billing (16h)**
- Hook em Modules/RecurringBilling pra cobrar tier escolhido via Asaas
- Cancellation flow + downgrade
- Relatório de uso do cliente (transparência)

**Etapa 4 — Onboarding (16h)**
- Wizard: cliente preenche IP+alias do banco legacy
- Auto-test conexão + schema-fingerprint
- 1ª análise gerada em 5min
- Email com relatório como teaser

**Etapa 5 — Marketing site (8h)**
- Página `oimpresso.com/snapshot`
- Demo pública com dados sintéticos
- Calculator ROI: "quanto você pode estar perdendo em inadimplência?"

**Total MVP**: 110h IA-pair = ~16 dias úteis Felipe full-time

### Riscos técnicos
- Conexão LAN cliente (cada cliente roda Firebird local) — exige VPN ou tunnel SSH (gateway ou ngrok-like)
- Charset legacy ISO-8859-1 → UTF-8 conversion
- Versões Delphi diferentes podem ter schema ligeiramente diferente (validar fingerprint sempre)
- Cliente pode "ter vergonha" do estado financeiro → marketing precisa ser cuidadoso ("descubra antes que o concorrente descubra")

### Fora-de-MVP (release 2)
- Multi-banco simultâneo (consolidar 5 unidades de cliente em 1 dashboard)
- Comparação setorial benchmark anônimo ("você está acima/abaixo da média de gráficas SP")
- Forecast 90d (ML simples — receita esperada baseado em histórico)
- Integration Asaas/Iugu pra automatizar cobrança (não só sugerir)

## Modelo de receita projetado

### Cenário conservador (12m)
- 5 clientes Tier 1 × R$ [redacted Tier 0]/m × 12 = R$ [redacted Tier 0]
- 2 clientes Tier 2 × R$ [redacted Tier 0]/m × 12 = R$ [redacted Tier 0]
- 1 cliente Tier 3 × R$ [redacted Tier 0]/m × 12 = R$ [redacted Tier 0]
- **Total ARR adicional**: R$ [redacted Tier 0] (4% da meta R$ [redacted Tier 0]M)

### Cenário realista (12m)
- 15 Tier 1 × R$ [redacted Tier 0]/m × 12 = R$ [redacted Tier 0]
- 8 Tier 2 × R$ [redacted Tier 0]/m × 12 = R$ [redacted Tier 0]
- 3 Tier 3 × R$ [redacted Tier 0]/m × 12 = R$ [redacted Tier 0]
- **Total ARR adicional**: R$ [redacted Tier 0] (14% da meta R$ [redacted Tier 0]M)

### Cenário otimista (12m)
- 30 Tier 1 × R$ [redacted Tier 0]/m × 12 = R$ [redacted Tier 0]
- 15 Tier 2 × R$ [redacted Tier 0]/m × 12 = R$ [redacted Tier 0]
- 5 Tier 3 × R$ [redacted Tier 0]/m × 12 = R$ [redacted Tier 0]
- **Total ARR adicional**: R$ [redacted Tier 0] (25% da meta R$ [redacted Tier 0]M)

### Bonus implícito — conversão pra oimpresso.com
- Cada cliente Tier 3 que migrar = + R$ [redacted Tier 0]-1.499/m receita oimpresso (vs R$ [redacted Tier 0] do Snapshot)
- 30% conversion rate em 12m = ~5 migrações × R$ [redacted Tier 0] médio × 12m = R$ [redacted Tier 0] adicionais
- **Total combinado realista**: ~R$ [redacted Tier 0]k ARR (24% da meta)

## ROI vs custo

- **Custo MVP**: 110h × R$ X/h custo interno = baixo (IA-pair, time existente)
- **Marketing**: ~R$ [redacted Tier 0]k (página, ads iniciais)
- **Custo recorrente**: VPS pequena pra rodar jobs (R$ [redacted Tier 0]/m), Asaas billing fee (~3%)
- **Payback**: cenário conservador R$ [redacted Tier 0]k/ano vs MVP ~R$ [redacted Tier 0]k custo interno = 18 meses
- **Cenário realista R$ [redacted Tier 0]k/ano vs custo = 5-6 meses payback**

## Alinhamento ADR 0105 (cliente como sinal qualificado)

Wagner CONFIRMOU sinal qualificado:
1. Análise piloto revelou achados que ele mesmo não tinha visibilidade (= demanda interna validada)
2. Wagner pediu skill+runbook+feature explícito (= sinal direto do dono)
3. 38 bases legacy = mercado interno qualificado já mapeado

→ **Não viola ADR 0105**. Vira US ativa quando Wagner formalizar.

## Próximo passo

1. **Wagner valida** este documento (aprovar/cortar/editar)
2. Se aprovado, virar **ADR canon** (próximo número disponível, ex 0121) com `status: accepted`
3. Adicionar ao roadmap M2-M3 (jul-set/2026) — paralelo a 2º+3º cliente
4. Spawn Felipe + Wagner pra MVP de 8 semanas
5. Lançar 1º trial com cliente Estilo (ou outro escolhido pelo Wagner)
6. Aprender, iterar, escalar

## Riscos da decisão

- **Risco 1: clientes legacy desconfiam** ("vou deixar você ver meu financeiro?")
  Mitigação: termo de confidencialidade explícito + caso público anonimizado primeiro
- **Risco 2: dispersão de foco** (oimpresso vs Snapshot vs migração)
  Mitigação: Snapshot é caminho pra migração, não competidor — mesmo time
- **Risco 3: dependência da rede LAN cliente**
  Mitigação: oferecer "tunnel SSH em sua máquina" como opção (VPN-light) OU rodar via secagent-like

## Critério de "pronto pra GO"

- 5 clientes legacy aceitam piloto pago no Tier 1 = sinal forte = construir MVP completo
- Se 5 não aparecem em 60d = re-questionar hipótese, talvez modelo errado
- Sem nenhum sinal em 90d = arquivar como ADR feature-wish, foco em outras prioridades

## Decisão final pendente de Wagner

- [ ] Aprovar conceito + tier pricing? (R$ [redacted Tier 0]/299/599)
- [ ] Apontar 5 clientes legacy candidatos a piloto?
- [ ] Aprovar MVP scope (110h)?
- [ ] Formalizar como ADR 0121 ou manter ADR feature-wish?
