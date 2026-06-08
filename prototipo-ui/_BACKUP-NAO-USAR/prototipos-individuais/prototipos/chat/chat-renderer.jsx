// chat-renderer.jsx — 4 componentes de bubble tipado + JanaAvatar canônico
// Aplicação do amendment COWORK_NOTES.amendment-jana-chat-block-renderer.md (2026-05-14)
// Charter: resources/js/Pages/Jana/Chat.charter.md

// ============================================================================
// JanaAvatar — quadrado rounded-md monocromático letra "J" (charter + amendment 2026-05-09)
// ============================================================================
function JanaAvatar({ size = 32 }) {
  const fontSize = Math.max(11, Math.floor(size * 0.45));
  return (
    <div
      className="jana-avatar"
      style={{
        width: size,
        height: size,
        borderRadius: 6,
        background: 'var(--primary, #ea7c2c)',
        color: 'var(--primary-foreground, #fff)',
        display: 'grid',
        placeItems: 'center',
        fontWeight: 600,
        fontSize,
        fontFamily: 'var(--font-sans)',
        flexShrink: 0,
      }}
    >
      J
    </div>
  );
}

// ============================================================================
// MarkdownBubble — texto livre com citations inline [1][2] clicáveis
// ============================================================================
function MarkdownBubble({ markdown, sources = [], time }) {
  // Render simples: troca [N] por chip clicável que abre source card
  const rendered = useMemo(() => {
    const parts = [];
    const re = /\[(\d+)\]/g;
    let last = 0;
    let m;
    while ((m = re.exec(markdown)) !== null) {
      if (m.index > last) parts.push({ kind: 'text', t: markdown.slice(last, m.index) });
      const n = parseInt(m[1], 10);
      const src = sources.find(s => s.n === n);
      parts.push({ kind: 'cite', n, label: src?.label || `Fonte ${n}`, href: src?.href });
      last = m.index + m[0].length;
    }
    if (last < markdown.length) parts.push({ kind: 'text', t: markdown.slice(last) });
    return parts;
  }, [markdown, sources]);

  return (
    <div className="bubble bubble-md">
      <div className="bubble-content">
        {rendered.map((p, i) =>
          p.kind === 'text' ? (
            <span key={i}>{p.t}</span>
          ) : (
            <a
              key={i}
              className="cite-chip"
              href={p.href}
              title={p.label}
              onClick={e => { e.preventDefault(); if (p.href) window.open(p.href, '_blank'); }}
            >
              [{p.n}]
            </a>
          )
        )}
      </div>
      {sources.length > 0 && (
        <div className="bubble-sources">
          {sources.map(s => (
            <a key={s.n} className="source-card" href={s.href} onClick={e => { e.preventDefault(); if (s.href) window.open(s.href, '_blank'); }}>
              <span className="source-n">[{s.n}]</span>
              <span className="source-label">{s.label}</span>
            </a>
          ))}
        </div>
      )}
      <span className="meta">{time}</span>
    </div>
  );
}

// ============================================================================
// ToolUseChip — chip sky com nome da ferramenta + status
// ============================================================================
function ToolUseChip({ tool, params = {}, status = 'done', time }) {
  const statusIcon = status === 'running' ? '⏳' : status === 'error' ? '✕' : '✓';
  const statusClass = `tool-status-${status}`;
  const paramsStr = Object.entries(params)
    .slice(0, 3)
    .map(([k, v]) => `${k}=${typeof v === 'string' ? `"${v}"` : v}`)
    .join(' ');

  return (
    <div className="bubble bubble-tool">
      <div className={`tool-chip ${statusClass}`}>
        <span className="tool-icon">{statusIcon}</span>
        <span className="tool-name">Consultou <code>{tool}</code></span>
        {paramsStr && <span className="tool-params">{paramsStr}</span>}
      </div>
      <span className="meta">{time}</span>
    </div>
  );
}

// ============================================================================
// DataTableBubble — tabela inline read-only dentro do bubble (max 5 rows + "ver mais")
// ============================================================================
function DataTableBubble({ columns = [], rows = [], caption, time }) {
  const [expanded, setExpanded] = useState2(false);
  const visible = expanded ? rows : rows.slice(0, 5);
  const hasMore = rows.length > 5;

  return (
    <div className="bubble bubble-table">
      {caption && <div className="table-caption">{caption}</div>}
      <div className="table-wrap">
        <table className="data-table">
          <thead>
            <tr>{columns.map(c => <th key={c.key}>{c.label}</th>)}</tr>
          </thead>
          <tbody>
            {visible.map((r, i) => (
              <tr key={i}>
                {columns.map(c => <td key={c.key}>{r[c.key]}</td>)}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {hasMore && (
        <button className="table-more" onClick={() => setExpanded(!expanded)}>
          {expanded ? `Ocultar (mostrando ${rows.length})` : `Ver mais ${rows.length - 5} linhas`}
        </button>
      )}
      <span className="meta">{time}</span>
    </div>
  );
}

// ============================================================================
// ActionCardBubble — confirm_required: emerald (done), amber (pending), rose (error)
// ============================================================================
function ActionCardBubble({ action, summary, confirm_required = false, result = null, onConfirm, onCancel, time }) {
  const [state, setState] = useState2(result === null ? (confirm_required ? 'pending' : 'done') : (result.ok ? 'done' : 'error'));

  const tone = state === 'done' ? 'emerald' : state === 'error' ? 'rose' : 'amber';
  const icon = state === 'done' ? '✓' : state === 'error' ? '✕' : '⚠';
  const heading =
    state === 'done' ? 'Ação executada' :
    state === 'error' ? 'Falha na ação' :
    'Confirmar ação';

  const handleConfirm = () => {
    setState('done');
    onConfirm?.();
  };

  const handleCancel = () => {
    setState('error');
    onCancel?.();
  };

  return (
    <div className={`bubble bubble-action action-${tone}`}>
      <div className="action-head">
        <span className="action-icon">{icon}</span>
        <b className="action-heading">{heading}</b>
        <code className="action-key">{action}</code>
      </div>
      <div className="action-summary">{summary}</div>
      {state === 'pending' && (
        <div className="action-buttons">
          <button className="action-btn action-confirm" onClick={handleConfirm}>Confirmar</button>
          <button className="action-btn action-cancel" onClick={handleCancel}>Cancelar</button>
        </div>
      )}
      <span className="meta">{time}</span>
    </div>
  );
}

// ============================================================================
// ThinkingIndicator — 1 chip animate-pulse "Jana está pensando" (NÃO 3-dots loop)
// ============================================================================
function ThinkingIndicator() {
  return (
    <div className="thinking">
      <span className="thinking-dot" />
      <span className="thinking-text">Jana está pensando</span>
    </div>
  );
}

// ============================================================================
// BlockRouter — switch por kind, retorna o componente certo
// ============================================================================
function BlockRouter({ msg }) {
  switch (msg.kind) {
    case 'markdown':    return <MarkdownBubble markdown={msg.markdown || msg.text} sources={msg.sources} time={msg.time} />;
    case 'tool_use':    return <ToolUseChip tool={msg.tool} params={msg.params} status={msg.status} time={msg.time} />;
    case 'data_table':  return <DataTableBubble columns={msg.columns} rows={msg.rows} caption={msg.caption} time={msg.time} />;
    case 'action_card': return <ActionCardBubble action={msg.action} summary={msg.summary} confirm_required={msg.confirm_required} result={msg.result} time={msg.time} />;
    default:            return <MarkdownBubble markdown={msg.text || msg.markdown || ''} sources={msg.sources || []} time={msg.time} />;
  }
}

// ============================================================================
// PiiDetector — regex CPF/CNPJ/cartão. Não bloqueia; mostra chip amber.
// ============================================================================
function detectPii(text) {
  if (!text) return null;
  // CPF: 000.000.000-00 ou 00000000000
  const cpf = /\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/;
  // CNPJ: 00.000.000/0000-00 ou 00000000000000
  const cnpj = /\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/;
  // Cartão: 4 grupos de 4 dígitos (espaço ou sem)
  const card = /\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/;
  if (cpf.test(text)) return 'CPF';
  if (cnpj.test(text)) return 'CNPJ';
  if (card.test(text)) return 'cartão';
  return null;
}

function PiiWarning({ kind }) {
  if (!kind) return null;
  return (
    <div className="pii-warning">
      <span className="pii-icon">⚠</span>
      <span>Conteúdo sensível detectado ({kind}) — Jana redige sem PII no audit log</span>
    </div>
  );
}

// Export ao window pra outros .jsx carregarem
window.JanaAvatar = JanaAvatar;
window.MarkdownBubble = MarkdownBubble;
window.ToolUseChip = ToolUseChip;
window.DataTableBubble = DataTableBubble;
window.ActionCardBubble = ActionCardBubble;
window.ThinkingIndicator = ThinkingIndicator;
window.BlockRouter = BlockRouter;
window.detectPii = detectPii;
window.PiiWarning = PiiWarning;
