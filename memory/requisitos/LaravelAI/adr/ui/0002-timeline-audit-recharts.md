# ADR UI-0002 (LaravelAI) · Timeline de auditoria via Recharts

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: ui

## Contexto

`/laravel-ai/audit/{subject}` mostra timeline de uma entidade (ex: `transactions/123`):
- Cronológica
- Eventos coloridos por causer (user, system, agente)
- Filtros (período, action, causer)
- Drill-down (click em evento mostra diff JSON)

Bibliotecas:

| Lib | Para que | Pró | Contra |
|---|---|---|---|
| **Recharts** | Charts gerais (line, bar, scatter) | Usado em outros módulos oimpresso, dark mode pronto | Não tem "timeline" nativo |
| **Visx Timeline** | Timeline específico | Mais flexível | Setup maior |
| **react-vertical-timeline-component** | Timeline vertical pronta | Plug-and-play | Menos customizável, design dated |
| **Custom (D3 ou shadcn raw)** | Total controle | Identidade própria | Tempo de implementação maior |

PontoWr2 e RecurringBilling já usam Recharts (`auto-memória: PontoWr2/adr/ui/0001`). Manter coerência ajuda manutenção e learning curve da equipe.

## Decisão

**Recharts em modo `<ScatterChart>` com X = tempo, Y = event-type, ponto colorido por causer.**

Layout:

```
    audit log: transactions/123
    ────────────────────────────────────────────────────
    Sistema  ●─────────●●──────────●
             |         |           |
    Larissa     ●──────────────●●─────●
             |
    Wagner                              ●
             |
             0:00  6:00  12:00  18:00  24:00  ←  hora do dia
    ────────────────────────────────────────────────────
    Click em ponto → modal com diff JSON
```

Vantagens visuais:
- Cronologia clara (X = tempo)
- Causer destacado (Y categorical)
- Cluster de eventos próximos = visualmente óbvio
- Cor por type de action (created=verde, updated=âmbar, deleted=vermelho)

Para muitos eventos (>200): paginar por dia ou agrupar visualmente.

## Componente React

```tsx
import { ScatterChart, Scatter, XAxis, YAxis, Tooltip, CartesianGrid } from 'recharts';

function AuditTimeline({ events }: Props) {
  // Normalizar: { ts: timestamp, causerY: 'Larissa', actionType: 'updated', payload: {...} }

  return (
    <ScatterChart width={800} height={300}>
      <CartesianGrid />
      <XAxis dataKey="ts" type="number" domain={['dataMin', 'dataMax']}
             tickFormatter={(ts) => format(ts, 'HH:mm')} />
      <YAxis dataKey="causerY" type="category" />
      <Tooltip content={<AuditTooltip />} />
      <Scatter
        data={events}
        fill={(e) => actionColor(e.actionType)}
        onClick={(e) => openDiffModal(e.payload)}
      />
    </ScatterChart>
  );
}

function AuditTooltip({ active, payload }: TooltipProps) {
  if (!active || !payload?.length) return null;
  const e = payload[0].payload;
  return (
    <div className="bg-popover border rounded p-2">
      <div className="font-semibold">{e.causer}</div>
      <div className="text-sm">{e.actionType}</div>
      <div className="text-xs text-muted-foreground">{format(e.ts, 'HH:mm:ss')}</div>
    </div>
  );
}
```

## Filtros

- **Período**: `<DateRangePicker>` shadcn — default últimas 24h
- **Causer**: `<MultiSelect>` com auto-complete users
- **Action**: tabs (Todos / Criados / Atualizados / Deletados)
- **Subject**: input com auto-complete (digite "transaction" → sugere transaction/123)

## Drill-down (modal de evento)

```tsx
function AuditEventModal({ event }: Props) {
  return (
    <Dialog>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{event.causer} {event.actionType}</DialogTitle>
        </DialogHeader>
        <DiffViewer
          oldData={event.properties.old}
          newData={event.properties.new}
          format="json"
        />
        <DialogFooter>
          <Badge>{event.subject_type}/{event.subject_id}</Badge>
          <Button variant="outline" onClick={openInLaravelAi}>Pergunte à IA</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

CTA "Pergunte à IA" gera prompt automático: "Explique por que [causer] alterou [field] em [subject] em [ts]".

## Performance

- **Pagination**: backend retorna max 500 eventos por request
- **Virtualização**: Recharts memoiza pontos viewport-visible
- **Cache**: `audit:{subject_type}:{subject_id}:{period}` → 1 min
- **Server-side filtering**: filtros aplicados no SQL (não no front)

## Tests obrigatórios

- Component (Vitest): timeline renderiza com cores por action
- E2E (Playwright): abrir audit → filtrar período → click ponto → modal aparece
- Snapshot test: timeline com 5 eventos conhecidos

## Decisões em aberto

- [ ] Timeline interativa em chat (resposta do agente abre timeline contextual)?
- [ ] Export CSV/PDF da timeline (auditoria oficial)?
- [ ] Comparação side-by-side de 2 subjects?

## Alternativas consideradas

- **react-vertical-timeline-component** — rejeitado: design dated, menos flexível
- **D3 customizado** — rejeitado: tempo de impl alto, ganho marginal
- **Tabela simples** — rejeitado: perde visualização de cluster

## Referências

- ARQ-0003 (Inertia + React)
- US-AI-004 (SPEC)
- `auto-memória: PontoWr2/adr/ui/0001-espelho-show-com-totalizadores-e-grafico-dia-a-dia.md` — Recharts pattern
- `RecurringBilling/adr/ui/0002-timeline-assinatura-visual.md` — timeline pattern análogo
