# ADR UI-0002 (RecurringBilling) · Timeline visual da assinatura

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: ui
- **Relacionado**: UI-0001, US-RB-005

## Contexto

Larissa-financeiro abre `/recurring-billing/contracts/{id}` e precisa entender em 5 segundos:
- Em que estado está hoje (active? past_due? trial?)
- Quando começou
- O que aconteceu (upgrades, downgrades, cobranças falhadas, dunning)
- Quando vence próxima cobrança

Tabela cronológica de eventos é densa demais. Cliente final (portal B2C) precisa do mesmo de forma ainda mais simples.

Concorrentes:
- **Stripe Dashboard** — timeline gráfica, excelente
- **Lago** — timeline de events com filtros
- **Conta Azul** — só tabela
- **Tiny** — só tabela

Concorrentes BR perdem aqui. Oportunidade de diferenciar.

## Decisão

**Timeline gráfica usando Recharts + ícones contextuais.**

Layout:

```
┌──────────────────────────────────────────────────────────────┐
│ Plano Pro · ATIVO                              R$ 350/mês    │
├──────────────────────────────────────────────────────────────┤
│   ●━━━━━━●━━━━━━●━━━━━━●━━━━━━●━━━━━━●━━━━━━●━━━━━━○━━━━━━○│
│   │      │      │      │      │      │      │      │      │ │
│ ASSINOU TRIAL CHARGE UPGRADE FAIL DUNNING PAID NEXT FUTURE  │
│ 01/01  15/01  01/02   03/02  01/03 02/03  09/03 01/04 01/05│
│                                                              │
│ MRR:  R$ 200  →  R$ 350                                     │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

Cada ponto:
- Cor: verde (pago/ok), amarelo (atenção), vermelho (falha), azul (mudança)
- Ícone: dollar, refresh, X, trend-up
- Tooltip: detalhe + link pra ação ("ver charge attempt", "ver invoice")
- Click: scrolla pra timeline detalhada abaixo

Abaixo da timeline gráfica: tabela detalhada (mesma info, scroll vertical).

Filtros rápidos (tabs):
- Todos
- Cobranças (charge attempts)
- Mudanças de plano (upgrades/downgrades)
- Comunicação (dunning, e-mails enviados)
- Cancelamentos (failed/canceled)

## Schema events para timeline

```sql
CREATE TABLE rb_contract_events (
    id BIGINT UNSIGNED PRIMARY KEY,
    business_id INT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('created', 'trial_started', 'trial_ended', 'invoice_generated',
              'charge_succeeded', 'charge_failed', 'plan_changed',
              'canceled', 'reactivated', 'dunning_step', 'reajuste') NOT NULL,
    payload JSON NOT NULL,
    occurred_at TIMESTAMP NOT NULL,
    INDEX idx_contract (contract_id, occurred_at)
);
```

Cada evento listener relevante grava aqui. Timeline lê tudo, ordena, renderiza.

## Componente React

```tsx
function ContractTimeline({ contract, events }: Props) {
  return (
    <Card>
      <CardHeader>
        <Typography variant="h2">{contract.plan.name}</Typography>
        <Badge variant={statusColor(contract.status)}>{statusLabel(contract.status)}</Badge>
        <Typography>{currency(contract.monthly_value)}/mês</Typography>
      </CardHeader>

      <CardContent>
        {/* Gráfico Recharts */}
        <ResponsiveContainer width="100%" height={120}>
          <ScatterChart>
            <XAxis dataKey="occurred_at" type="time" />
            <Scatter data={events} fill={(e) => eventColor(e.tipo)} />
            <Tooltip content={<EventTooltip />} />
          </ScatterChart>
        </ResponsiveContainer>

        {/* Tabela detalhada */}
        <ContractEventsTable events={events} onClick={(e) => /* drill-down */} />
      </CardContent>
    </Card>
  );
}
```

## Consequências

**Positivas:**
- Larissa entende contrato em 5s — diferencial vs concorrentes
- Cliente B2C: portal mostra mesmo timeline simplificado (transparência aumenta retenção)
- Audit: timeline conta a história sem precisar SQL forense
- Diagnóstico rápido: "por que esse cliente está past_due?" → óbvio na timeline

**Negativas:**
- Mais 1 tabela (`rb_contract_events`) com listeners populando — overhead disco ~1KB por evento
- Timeline em mobile fica apertada — usar accordion vertical ou cards pequenos
- Eventos histórias longas (> 2 anos) — paginar/filtro por período

## Tests obrigatórios

- Listener — cada evento dispara grava `rb_contract_events`
- Component test — renderiza eventos com cor/ícone correto por tipo
- Filtro por tipo funciona
- E2E (Playwright) — abrir contrato → ver timeline → clicar evento → modal detalhe

## Métricas a observar (post-launch)

- Tempo médio entre abrir contrato e tomar ação (cancelar, atualizar, etc.)
- Quais eventos têm mais clicks (otimizar drill-down)
- Mobile vs desktop usage da tela contrato

## Decisões em aberto

- [ ] Limitar timeline aos últimos 12 meses por padrão? (com expand)
- [ ] Adicionar preditivo "próximo evento esperado" (ex: "Tentativa #2 em 3 dias")?
- [ ] Compartilhar timeline com cliente final via link (transparência)?

## Alternativas consideradas

- **Tabela só** (sem gráfica) — rejeitado: perde diferencial visual
- **Gantt** — overkill pra 1 contrato
- **Timeline vertical** — pior pra ver histórico longo
- **Heatmap calendário** — bonito mas não conta a história de mudanças

## Referências

- UI-0001 (portal B2C usa mesma timeline em versão simplificada)
- Stripe Dashboard timeline (referência)
- `auto-memória: PontoWr2/adr/ui/0001-espelho-show-com-totalizadores-e-grafico-dia-a-dia.md` — pattern Recharts
