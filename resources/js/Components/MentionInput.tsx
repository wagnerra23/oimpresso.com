// @memcofre
//   componente: MentionInput
//   stories: PMG-005 (ADR 0100) — @mentions em comments
//   permissao: copiloto.mcp.usage.all (endpoint suggest)
//
// Textarea com autocomplete de @username quando user digita "@". Após
// trigger, mostra dropdown com sugestões fetched de
// GET /project-mgmt/board/users/suggest?q=. Setas ↑↓ navegam, Enter
// completa, Esc fecha dropdown.
//
// Backend: parser de @mentions em TaskCrudService::comment() (já existe);
// dispara mcp_inbox_notifications pra cada user mencionado.

import { useEffect, useRef, useState, useCallback, type ChangeEvent, type KeyboardEvent } from 'react';
import { Loader2 } from 'lucide-react';

interface UserSuggestion {
  id: number;
  username: string;
  name: string;
}

interface Props {
  value: string;
  onChange: (value: string) => void;
  onSubmit?: () => void; // Cmd+Enter envia
  placeholder?: string;
  disabled?: boolean;
  rows?: number;
}

interface MentionState {
  active: boolean;
  query: string;
  /** Posição inicial do '@' no value */
  startIdx: number;
  /** Posição do cursor após o termo (pra reposicionar) */
  cursorIdx: number;
  suggestions: UserSuggestion[];
  loading: boolean;
  selectedIdx: number;
}

const INITIAL: MentionState = {
  active: false,
  query: '',
  startIdx: -1,
  cursorIdx: -1,
  suggestions: [],
  loading: false,
  selectedIdx: 0,
};

export default function MentionInput({
  value,
  onChange,
  onSubmit,
  placeholder = 'Comentar (use @ pra mencionar)…',
  disabled = false,
  rows = 3,
}: Props) {
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const [mention, setMention] = useState<MentionState>(INITIAL);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  // Detecta se cursor está dentro de um @mention sendo digitada
  const detectMention = useCallback((text: string, cursor: number) => {
    const before = text.slice(0, cursor);
    // Match último '@' seguido de letras/dígitos sem espaço
    const m = before.match(/(?:^|\s)@([a-zA-Z0-9_-]*)$/);
    if (!m) {
      return null;
    }
    const startIdx = before.lastIndexOf('@');
    return { startIdx, query: m[1] ?? '' };
  }, []);

  function handleChange(e: ChangeEvent<HTMLTextAreaElement>) {
    const newValue = e.target.value;
    const cursor = e.target.selectionStart ?? newValue.length;
    onChange(newValue);

    const det = detectMention(newValue, cursor);
    if (det) {
      setMention((prev) => ({
        ...prev,
        active: true,
        query: det.query,
        startIdx: det.startIdx,
        cursorIdx: cursor,
        selectedIdx: 0,
      }));
    } else {
      setMention(INITIAL);
    }
  }

  // Fetch debounced quando query muda
  useEffect(() => {
    if (!mention.active) return;
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (abortRef.current) abortRef.current.abort();

    if (mention.query.length === 0) {
      setMention((prev) => ({ ...prev, suggestions: [], loading: false }));
      return;
    }

    setMention((prev) => ({ ...prev, loading: true }));
    debounceRef.current = setTimeout(() => {
      const ctrl = new AbortController();
      abortRef.current = ctrl;
      fetch(`/project-mgmt/board/users/suggest?q=${encodeURIComponent(mention.query)}`, {
        headers: { Accept: 'application/json' },
        signal: ctrl.signal,
      })
        .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
        .then((data: { users: UserSuggestion[] }) => {
          setMention((prev) => ({
            ...prev,
            suggestions: data.users,
            loading: false,
            selectedIdx: 0,
          }));
        })
        .catch((err) => {
          if (err?.name === 'AbortError') return;
          setMention((prev) => ({ ...prev, suggestions: [], loading: false }));
        });
    }, 180);
     
  }, [mention.query, mention.active]);

  function applyMention(user: UserSuggestion) {
    const before = value.slice(0, mention.startIdx);
    const after = value.slice(mention.cursorIdx);
    const newText = `${before}@${user.username} ${after}`;
    onChange(newText);
    setMention(INITIAL);

    // Reposiciona cursor após o mention completo
    setTimeout(() => {
      if (textareaRef.current) {
        const newCursor = before.length + user.username.length + 2; // @user + space
        textareaRef.current.focus();
        textareaRef.current.setSelectionRange(newCursor, newCursor);
      }
    }, 0);
  }

  function handleKeyDown(e: KeyboardEvent<HTMLTextAreaElement>) {
    if (mention.active && mention.suggestions.length > 0) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        setMention((prev) => ({
          ...prev,
          selectedIdx: Math.min(prev.suggestions.length - 1, prev.selectedIdx + 1),
        }));
        return;
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        setMention((prev) => ({
          ...prev,
          selectedIdx: Math.max(0, prev.selectedIdx - 1),
        }));
        return;
      }
      if (e.key === 'Enter' || e.key === 'Tab') {
        const u = mention.suggestions[mention.selectedIdx];
        if (u) {
          e.preventDefault();
          applyMention(u);
          return;
        }
      }
      if (e.key === 'Escape') {
        e.preventDefault();
        setMention(INITIAL);
        return;
      }
    }

    // Cmd+Enter / Ctrl+Enter envia (quando NÃO há mention ativo)
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter' && onSubmit) {
      e.preventDefault();
      onSubmit();
    }
  }

  return (
    <div className="relative">
      <textarea
        ref={textareaRef}
        value={value}
        onChange={handleChange}
        onKeyDown={handleKeyDown}
        placeholder={placeholder}
        disabled={disabled}
        rows={rows}
        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 resize-y"
      />
      {onSubmit && (
        <p className="mt-1 text-[10px] text-muted-foreground">
          <kbd className="px-1 py-0.5 rounded bg-muted">Cmd+Enter</kbd> envia ·{' '}
          <kbd className="px-1 py-0.5 rounded bg-muted">@</kbd> menciona alguém
        </p>
      )}

      {mention.active && (mention.loading || mention.suggestions.length > 0) && (
        <div className="absolute left-0 top-full z-50 mt-1 max-h-64 w-72 overflow-y-auto rounded-md border bg-popover shadow-lg">
          {mention.loading ? (
            <div className="flex items-center justify-center py-3 text-xs text-muted-foreground">
              <Loader2 className="mr-2 h-3 w-3 animate-spin" />
              buscando…
            </div>
          ) : mention.suggestions.length === 0 ? (
            <div className="py-3 text-center text-xs text-muted-foreground">
              Nenhum usuário encontrado pra "{mention.query}".
            </div>
          ) : (
            <ul className="py-1">
              {mention.suggestions.map((u, idx) => (
                <li
                  key={u.id}
                  onMouseDown={(e) => {
                    e.preventDefault();
                    applyMention(u);
                  }}
                  className={`cursor-pointer px-3 py-1.5 text-xs ${
                    idx === mention.selectedIdx ? 'bg-accent text-accent-foreground' : 'hover:bg-muted'
                  }`}
                >
                  <span className="font-mono">@{u.username}</span>
                  {u.name && u.name !== u.username && (
                    <span className="ml-2 text-muted-foreground">{u.name}</span>
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
