/**
 * TaskCard — single card on the Board/Kanban (DS, from ProjectMgmt canon, ADR 0070).
 * Pure, dependency-free (global React + inline token styles).
 *
 * task: { displayId, title, priority: 'p0'|'p1'|'p2'|'p3', module?, owner?,
 *         estimateH?, storyPoints?, due?, isBlocked?, isOverdue?, blockedBy?: [] }
 * selected → blue ring · draggable + onDragStart · onClick(task)
 */
const PRIORITY = {
  p0: { bg: 'var(--color-destructive-soft)', fg: 'var(--color-destructive-fg)' },
  p1: { bg: 'color-mix(in oklch, var(--color-warning) 18%, var(--surface))', fg: 'var(--color-warning-fg)' },
  p2: { bg: 'var(--color-secondary)', fg: 'var(--color-muted-foreground)' },
  p3: { bg: 'var(--bg-2)', fg: 'var(--text-mute)' },
};

export function TaskCard({ task = {}, selected = false, onDragStart, onClick }) {
  const p = PRIORITY[task.priority] || PRIORITY.p2;
  const chip = {
    display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 10,
    padding: '1px 6px', borderRadius: 4, background: 'var(--color-secondary)', color: 'var(--text-dim)',
  };
  return (
    <div
      draggable={!!onDragStart}
      onDragStart={onDragStart ? () => onDragStart(task) : undefined}
      onClick={onClick ? () => onClick(task) : undefined}
      style={{
        background: task.isBlocked ? 'color-mix(in oklch, var(--color-destructive) 6%, var(--surface))' : 'var(--surface)',
        border: '1px solid ' + (task.isBlocked ? 'color-mix(in oklch, var(--color-destructive) 35%, var(--border))' : 'var(--border)'),
        borderRadius: 'var(--radius-lg, 8px)', padding: 12, userSelect: 'none',
        boxShadow: selected ? '0 0 0 2px var(--accent), 0 1px 2px rgba(0,0,0,.06)' : '0 1px 2px rgba(0,0,0,.06)',
        cursor: onClick ? 'pointer' : (onDragStart ? 'grab' : 'default'),
        transition: 'box-shadow .15s, border-color .15s',
      }}
    >
      <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 8, marginBottom: 6 }}>
        <span style={{ fontSize: 10, fontFamily: 'var(--font-mono)', color: 'var(--text-mute)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{task.displayId}</span>
        <span style={{ fontSize: 10, fontWeight: 700, padding: '1px 6px', borderRadius: 4, background: p.bg, color: p.fg, flexShrink: 0 }}>{(task.priority || 'p2').toUpperCase()}</span>
      </div>
      <p style={{
        margin: '0 0 8px', fontSize: 12, fontWeight: 500, lineHeight: 1.35, color: 'var(--text)',
        display: '-webkit-box', WebkitLineClamp: 3, WebkitBoxOrient: 'vertical', overflow: 'hidden',
      }}>{task.title}</p>
      <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 5 }}>
        {task.isBlocked && (
          <span style={{ ...chip, background: 'var(--color-destructive-soft)', color: 'var(--color-destructive-fg)', fontWeight: 600 }}>🔒 blocked</span>
        )}
        {task.module && <span style={chip}>{task.module}</span>}
        {task.owner && <span style={chip}>@{task.owner}</span>}
        {task.estimateH > 0 && <span style={{ fontSize: 10, color: 'var(--text-mute)' }}>{task.estimateH}h</span>}
        {task.storyPoints > 0 && <span style={{ fontSize: 10, color: 'var(--text-mute)' }}>{task.storyPoints}sp</span>}
        {task.due && (
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3, fontSize: 10, fontWeight: task.isOverdue ? 600 : 400, color: task.isOverdue ? 'var(--color-destructive-fg)' : 'var(--text-mute)' }}>
            {task.isOverdue ? '⚠' : '🗓'} {task.due}
          </span>
        )}
        {task.blockedBy && task.blockedBy.length > 0 && !task.isBlocked && (
          <span style={{ fontSize: 10, color: 'var(--color-warning-fg)' }}>↶ {task.blockedBy.join(', ')}</span>
        )}
      </div>
    </div>
  );
}
