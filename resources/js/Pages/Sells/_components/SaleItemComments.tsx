// SaleItemComments — Cowork KB-9.75 Sells Onda 3 R3 Curadoria
// (comentários inline por item da venda).
//
// Refs:
//  - prototipo-ui/prototipos/sells-index/vendas-curation.jsx (canonical source)
//  - resources/css/sells-cowork-curadoria.css (.vd-item-cur tokens)
//  - SaleSheet.tsx (callsite — tab Itens dentro do drawer)
//
// Storage: localStorage[oimpresso.sells.itemComments] = { saleId: { itemIdx: [{author, text, when}] } }
// Onda 3.5 plugará em backend `sell_item_comments` table com sync MCP.

import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { MessageSquare, X } from 'lucide-react';

interface Comment {
  author: string;
  text: string;
  when: string;
}

type CommentMap = Record<string, Record<string, Comment[]>>;

const LS_KEY = 'oimpresso.sells.itemComments';

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

/**
 * Hook compartilhado — uso típico no SaleSheet drawer parent.
 *
 * @example
 *   const comments = useSaleItemComments();
 *   <SaleItemComments venda_id={sale.id} item_idx={0} comments={comments} />
 */
export function useSaleItemComments() {
  const [m, setM] = useState<CommentMap>(loadComments);

  useEffect(() => {
    saveComments(m);
  }, [m]);

  const add = useCallback(
    (vendaId: number | string, itemIdx: number | string, text: string, author = 'você') => {
      const t = text.trim();
      if (!t) return;
      setM((prev) => {
        const vKey = String(vendaId);
        const iKey = String(itemIdx);
        const v = prev[vKey] ?? {};
        const it = v[iKey] ?? [];
        const when = new Date().toLocaleString('pt-BR', {
          day: '2-digit',
          month: '2-digit',
          hour: '2-digit',
          minute: '2-digit',
        });
        return {
          ...prev,
          [vKey]: { ...v, [iKey]: [...it, { author, text: t, when }] },
        };
      });
    },
    []
  );

  const remove = useCallback(
    (vendaId: number | string, itemIdx: number | string, ci: number) => {
      setM((prev) => {
        const vKey = String(vendaId);
        const iKey = String(itemIdx);
        const v = prev[vKey] ?? {};
        const it = v[iKey] ?? [];
        const nextIt = it.filter((_c, i) => i !== ci);
        const nextV = { ...v };
        if (nextIt.length) nextV[iKey] = nextIt;
        else delete nextV[iKey];
        return { ...prev, [vKey]: nextV };
      });
    },
    []
  );

  const get = useCallback(
    (vendaId: number | string, itemIdx: number | string): Comment[] => {
      return m[String(vendaId)]?.[String(itemIdx)] ?? [];
    },
    [m]
  );

  const countFor = useCallback(
    (vendaId: number | string): number => {
      const v = m[String(vendaId)] ?? {};
      return Object.values(v).reduce((s, l) => s + l.length, 0);
    },
    [m]
  );

  return useMemo(() => ({ add, remove, get, countFor }), [add, remove, get, countFor]);
}

interface SaleItemCommentsProps {
  venda_id: number | string;
  item_idx: number | string;
  /** retorno do `useSaleItemComments()` hook. */
  controller: ReturnType<typeof useSaleItemComments>;
}

export default function SaleItemComments({
  venda_id,
  item_idx,
  controller,
}: SaleItemCommentsProps): ReactNode {
  const [open, setOpen] = useState(false);
  const [text, setText] = useState('');
  const list = controller.get(venda_id, item_idx);
  const has = list.length > 0;

  const submit = () => {
    const t = text.trim();
    if (!t) return;
    controller.add(venda_id, item_idx, t);
    setText('');
    setOpen(false);
  };

  return (
    <div className={`vd-item-cur ${has ? 'has-comments' : ''}`}>
      <button
        type="button"
        className={`vd-item-c-comm ${open ? 'on' : ''} ${has ? 'has' : ''}`}
        onClick={() => setOpen((o) => !o)}
        title={has ? `${list.length} comentário${list.length > 1 ? 's' : ''}` : 'Comentar este item'}
        aria-expanded={open}
      >
        {open ? <X size={12} /> : <MessageSquare size={12} />}
        {has && !open && <span className="ct">{list.length}</span>}
      </button>

      {(has || open) && (
        <div className="vd-item-thread">
          {list.map((c, ci) => (
            <div key={ci} className="vd-item-comment">
              <div className="vd-item-comment-h">
                <span className="vd-item-comment-av">{c.author.charAt(0).toUpperCase()}</span>
                <b>{c.author}</b>
                <span className="vd-item-comment-when">{c.when}</span>
                <button
                  type="button"
                  className="vd-item-comment-x"
                  onClick={() => controller.remove(venda_id, item_idx, ci)}
                  title="Remover"
                  aria-label="Remover comentário"
                >
                  ×
                </button>
              </div>
              <p>{c.text}</p>
            </div>
          ))}
          {open && (
            <div className="vd-item-comment-new">
              <textarea
                autoFocus
                value={text}
                rows={2}
                onChange={(e) => setText(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
                    e.preventDefault();
                    submit();
                  }
                }}
                placeholder='Ex: "Cliente pediu material premium" · "Confirmar arte antes de imprimir" · ⌘↵ envia'
              />
              <div className="vd-item-comment-row">
                <small>📌 visível pra Produção · Financeiro · Vendedor</small>
                <button
                  type="button"
                  className="vd-item-ghost"
                  onClick={() => {
                    setOpen(false);
                    setText('');
                  }}
                >
                  Cancelar
                </button>
                <button
                  type="button"
                  className="vd-item-primary"
                  disabled={!text.trim()}
                  onClick={submit}
                >
                  Comentar
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
