// FinCommentsThread — Cowork KB-9.75 Financeiro Onda 5 R1 Curadoria
// (thread de comentários por lançamento — Eliana ↔ Wagner ↔ Bruna).
//
// Refs:
//  - prototipo-ui/financeiro-curation.jsx — useFinComments + FinCommentsThread
//  - SaleItemComments.tsx pattern (canon localStorage Sells Onda 3)
//
// Storage: localStorage[oimpresso.financeiro.comments] = { rowId: [{author, text, when}] }
// Onda futura plugará em backend `fin_comments` table com sync MCP.

import { useCallback, useEffect, useState, type KeyboardEvent } from 'react';

const LS_KEY = 'oimpresso.financeiro.comments';

export interface FinComment {
  author: string;
  text: string;
  when: string;
}

type CommentMap = Record<string, FinComment[]>;

function loadComments(): CommentMap {
  if (typeof window === 'undefined') return {};
  try {
    return JSON.parse(window.localStorage.getItem(LS_KEY) || '{}');
  } catch (_) {
    return {};
  }
}

function saveComments(m: CommentMap): void {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.setItem(LS_KEY, JSON.stringify(m));
  } catch (_) {
    /* ls indisponível */
  }
}

function ptBrStamp(d: Date = new Date()): string {
  return d.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export interface UseFinCommentsApi {
  get: (rowId: string | number) => FinComment[];
  add: (rowId: string | number, text: string, author?: string) => void;
  remove: (rowId: string | number, idx: number) => void;
  countFor: (rowId: string | number) => number;
  hasAny: () => boolean;
}

export function useFinComments(): UseFinCommentsApi {
  const [m, setM] = useState<CommentMap>(loadComments);

  useEffect(() => {
    saveComments(m);
  }, [m]);

  const get = useCallback((rowId: string | number) => m[String(rowId)] || [], [m]);

  const add = useCallback((rowId: string | number, text: string, author = 'Eliana') => {
    const t = text.trim();
    if (!t) return;
    setM((prev) => ({
      ...prev,
      [String(rowId)]: [...(prev[String(rowId)] || []), { author, text: t, when: ptBrStamp() }],
    }));
  }, []);

  const remove = useCallback((rowId: string | number, idx: number) => {
    setM((prev) => {
      const key = String(rowId);
      const next = { ...prev };
      next[key] = (next[key] || []).filter((_, i) => i !== idx);
      if (next[key].length === 0) delete next[key];
      return next;
    });
  }, []);

  const countFor = useCallback((rowId: string | number) => (m[String(rowId)] || []).length, [m]);
  const hasAny = useCallback(() => Object.keys(m).length > 0, [m]);

  return { get, add, remove, countFor, hasAny };
}

interface FinCommentsThreadProps {
  rowId: string | number;
  comments: UseFinCommentsApi;
  author?: string;
}

export function FinCommentsThread({ rowId, comments, author = 'Eliana' }: FinCommentsThreadProps) {
  const [text, setText] = useState('');
  const list = comments.get(rowId);

  const submit = () => {
    if (!text.trim()) return;
    comments.add(rowId, text, author);
    setText('');
  };

  const onKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
      e.preventDefault();
      submit();
    }
  };

  return (
    <div className="fin-comments">
      <div className="fin-comments-h">
        <h4>Comentários</h4>
        <small>
          {list.length} · visíveis pra Eliana · Wagner · Bruna
        </small>
      </div>
      {list.length > 0 && (
        <ul className="fin-comments-list">
          {list.map((c, i) => (
            <li key={i} className="fin-comment">
              <span className="fin-comment-av">{(c.author || '?').charAt(0).toUpperCase()}</span>
              <div className="fin-comment-body">
                <header>
                  <b>{c.author}</b>
                  <time>{c.when}</time>
                  <button
                    type="button"
                    className="fin-comment-x"
                    onClick={() => comments.remove(rowId, i)}
                    title="Remover"
                    aria-label="Remover comentário"
                  >
                    ×
                  </button>
                </header>
                <p>{c.text}</p>
              </div>
            </li>
          ))}
        </ul>
      )}
      <div className="fin-comment-new">
        <textarea
          value={text}
          rows={2}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={onKeyDown}
          placeholder='Ex: "Conferi com Bruna, valor correto" · "Anexar comprovante" · ⌘↵ envia'
        />
        <button type="button" onClick={submit} disabled={!text.trim()}>
          Comentar
        </button>
      </div>
    </div>
  );
}

interface FinCommentsBadgeProps {
  rowId: string | number;
  comments: UseFinCommentsApi;
}

/** Indicador silent pra header da linha — mostra "💬 N" só quando há comentário. */
export function FinCommentsBadge({ rowId, comments }: FinCommentsBadgeProps) {
  const n = comments.countFor(rowId);
  if (n === 0) return null;
  return (
    <span className="fin-comments-badge" title={`${n} comentário${n > 1 ? 's' : ''}`}>
      💬 {n}
    </span>
  );
}

export default FinCommentsThread;
