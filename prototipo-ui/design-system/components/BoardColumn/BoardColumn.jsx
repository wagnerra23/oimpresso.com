/**
 * BoardColumn — a Kanban column (DS, ProjectMgmt canon ADR 0070).
 * Top border colored by status · header (label + count) · card slot · empty state.
 * Pure, dependency-free (global React + inline token styles).
 *
 * status: 'backlog'|'todo'|'doing'|'review'|'done'|'blocked'|'cancelled'
 * children = TaskCard nodes · count overrides the auto count · onDrop?(status)
 */
const COL = {
  backlog:   { border: 'var(--text-mute)',          label: 'Backlog' },
  todo:      { border: 'var(--text-dim)',           label: 'A fazer' },
  doing:     { border: 'var(--color-info)',         label: 'Fazendo' },
  review:    { border: 'var(--color-warning)',      label: 'Revisão' },
  done:      { border: 'var(--color-success)',      label: 'Concluído' },
  blocked:   { border: 'var(--color-destructive)',  label: 'Bloqueado' },
  cancelled: { border: 'var(--border)',             label: 'Cancelado' },
};

export function BoardColumn({ status = 'todo', label, count, children, onDrop, header }) {
  const c = COL[status] || COL.todo;
  const [over, setOver] = React.useState(false);
  const n = count != null ? count : React.Children.count(children);
  return (
    <div
      onDragOver={onDrop ? (e) => { e.preventDefault(); setOver(true); } : undefined}
      onDragLeave={onDrop ? () => setOver(false) : undefined}
      onDrop={onDrop ? () => { setOver(false); onDrop(status); } : undefined}
      style={{
        borderRadius: 'var(--radius-xl, 12px)', borderTop: '4px solid ' + c.border,
        border: '1px solid var(--border)', borderTopColor: c.border, borderTopWidth: 4,
        background: over ? 'color-mix(in oklch, var(--accent) 8%, var(--bg-2))' : 'var(--bg-2)',
        boxShadow: over ? '0 0 0 2px var(--accent)' : 'none',
        padding: 12, minHeight: 280, transition: 'background .15s, box-shadow .15s',
      }}
    >
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
        <span style={{ fontSize: 11, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '.05em', color: 'var(--text)' }}>{label || c.label}</span>
        <span style={{ fontSize: 11, fontFamily: 'var(--font-mono)', color: 'var(--text-mute)' }}>{n}</span>
      </div>
      {header}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        {children}
        {n === 0 && (
          <div style={{
            fontSize: 11, color: 'var(--text-mute)', textAlign: 'center', padding: '24px 0',
            border: '2px dashed var(--border)', borderRadius: 'var(--radius-lg, 8px)',
          }}>vazio</div>
        )}
      </div>
    </div>
  );
}
