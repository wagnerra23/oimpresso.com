// Wave D — US-CRM-066 Tab Documents & Note (MWART F3 paridade /contacts/{id} tab documents_and_notes)
// Restrições Tier 0 (ADR 0093): backend filtra business_id global scope em todas as queries.
//
// Anexos (Wagner 2026-06-01) — endpoints dedicados ContactController, business_id scope:
//   GET    /cliente/{id}/anexos           (listar — mesma fonte do contador "N anexos" do header)
//   POST   /cliente/{id}/anexos           (enviar — cria document-note + anexa media ao contato)
//   DELETE /cliente/{id}/anexos/{mediaId} (excluir — valida posse no contato antes)
// Notas (texto) seguem no DocumentAndNoteController:
//   POST /note-documents (store) · PUT /note-documents/{id} (update)
//
// Modelo polimórfico: notable_id={contact.id}, notable_type='App\Contact'
//
// Pattern: textarea autosave debounced 1.5s + lista de anexos recarregada do backend.

import { useEffect, useRef, useState } from 'react';
import { Download, FileText, Loader2, Paperclip, StickyNote, Trash2, Upload } from 'lucide-react';
import { Button } from '@/Components/ui/button';

export interface DocumentItem {
  id: number;
  file_name: string;
  display_name: string | null;
  description: string | null;
  file_size: number | null; // bytes
  mime_type: string | null;
  uploaded_by_name: string | null;
  created_at: string | null;
  download_url: string;
}

export interface NoteItem {
  id: number;
  heading: string | null;
  description: string;
  created_by_name: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface DocumentsTabProps {
  contactId: number;
  documents?: DocumentItem[];
  notes?: NoteItem[];
  /** Notable type fixo App\Contact, mantido em prop pra reuso futuro */
  notableType?: string;
  permissions?: {
    upload: boolean;
    delete_document: boolean;
    edit_note: boolean;
  };
  /** Wagner 2026-06-01 — reporta a contagem VIVA de anexos (após load/upload/delete)
   * pro header (chip "📎 N anexos"), que senão fica no valor estático do payload. */
  onCountChange?: (count: number) => void;
}

const formatBytes = (bytes: number | null) => {
  if (!bytes || bytes === 0) return '—';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const formatDate = (iso: string | null) => {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d);
};

function getCsrf(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export default function DocumentsTab({
  contactId,
  documents: docsProp,
  notes: notesProp,
  notableType = 'App\\Contact',
  permissions = { upload: true, delete_document: true, edit_note: true },
  onCountChange,
}: DocumentsTabProps) {
  const [documents, setDocuments] = useState<DocumentItem[]>(docsProp ?? []);
  // notes list não é renderizada inteira (UI mostra só primary note); mantido pra futura grid.
  const [notes] = useState<NoteItem[]>(notesProp ?? []);
  const [primaryNote, setPrimaryNote] = useState<string>(notesProp?.[0]?.description ?? '');
  const [noteHeading, setNoteHeading] = useState<string>(notesProp?.[0]?.heading ?? '');
  const [noteId, setNoteId] = useState<number | null>(notesProp?.[0]?.id ?? null);
  const [uploading, setUploading] = useState(false);
  const [autosaveStatus, setAutosaveStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const fileRef = useRef<HTMLInputElement | null>(null);
  const noteTimeoutRef = useRef<number | null>(null);

  // Wagner 2026-06-01 — carrega anexos do backend. Reutilizado no mount E após
  // upload/exclusão (backend é a fonte da verdade). Só quando `documents` NÃO
  // veio por prop (preserva usos prop-driven/SSR). GET /cliente/{id}/anexos.
  const loadDocuments = async () => {
    if (docsProp !== undefined) return;
    try {
      const res = await fetch(`/cliente/${contactId}/anexos`, {
        credentials: 'same-origin',
        cache: 'no-store', // pós-upload precisa do estado fresco, nunca cache do GET
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (Array.isArray(data?.documents)) {
        setDocuments(data.documents as DocumentItem[]);
        onCountChange?.(data.documents.length); // sincroniza o chip do header
      }
    } catch {
      // silencioso: painel permanece com o estado atual (mesmo fallback de antes).
    }
  };

  useEffect(() => {
    void loadDocuments();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [contactId]);

  // Autosave debounced 1500ms na nota primária (primeira nota; senão cria)
  useEffect(() => {
    if (primaryNote === (notesProp?.[0]?.description ?? '') && noteHeading === (notesProp?.[0]?.heading ?? '')) {
      return;
    }
    if (noteTimeoutRef.current) window.clearTimeout(noteTimeoutRef.current);
    setAutosaveStatus('saving');
    noteTimeoutRef.current = window.setTimeout(async () => {
      try {
        const body = new FormData();
        body.append('notable_id', String(contactId));
        body.append('notable_type', notableType);
        body.append('description', primaryNote);
        if (noteHeading) body.append('heading', noteHeading);

        let url = '/note-documents';
        if (noteId) {
          url = `/note-documents/${noteId}`;
          body.append('_method', 'PUT');
        }

        const res = await fetch(url, {
          method: 'POST', // Laravel honors _method
          body,
          credentials: 'same-origin',
          headers: {
            'X-CSRF-TOKEN': getCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
          },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json().catch(() => ({}));
        if (data?.note?.id && !noteId) setNoteId(data.note.id);
        setAutosaveStatus('saved');
        setTimeout(() => setAutosaveStatus('idle'), 2000);
      } catch (e) {
        setAutosaveStatus('error');
      }
    }, 1500);

    return () => {
      if (noteTimeoutRef.current) window.clearTimeout(noteTimeoutRef.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [primaryNote, noteHeading]);

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (!files || files.length === 0) return;
    setUploading(true);
    try {
      for (const file of Array.from(files)) {
        const fd = new FormData();
        fd.append('file', file);

        // Wagner 2026-06-01 — endpoint dedicado que PERSISTE (cria document-note
        // + anexa media ao contato), business_id scope. Substitui o endpoint
        // legado (postMedia) que só subia o arquivo sem persistir o vínculo.
        const res = await fetch(`/cliente/${contactId}/anexos`, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
          headers: {
            'X-CSRF-TOKEN': getCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
          },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
      }
      // Recarrega do backend (fonte da verdade) — reflete o que persistiu.
      await loadDocuments();
    } catch (err) {
       
      alert('Erro ao enviar arquivo. Tente novamente.');
    } finally {
      setUploading(false);
      if (fileRef.current) fileRef.current.value = '';
    }
  };

  const handleDelete = async (docId: number) => {
    if (!confirm('Excluir este anexo?')) return;
    try {
      const fd = new FormData();
      fd.append('_method', 'DELETE'); // Laravel honra _method via POST

      // docId = id do media (DocumentItem.id). Endpoint valida que pertence a um
      // document-note DESTE contato (business_id scope) antes de excluir.
      const res = await fetch(`/cliente/${contactId}/anexos/${docId}`, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': getCsrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const next = documents.filter((d) => d.id !== docId);
      setDocuments(next);
      onCountChange?.(next.length); // decrementa o chip do header
    } catch {
       
      alert('Erro ao excluir anexo.');
    }
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4" data-testid="documents-tab-root">
      {/* Notas */}
      <section className="rounded-lg border border-border bg-background p-4 flex flex-col">
        <div className="flex items-center justify-between mb-3">
          <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
            <StickyNote size={14} className="text-muted-foreground" aria-hidden />
            Notas
          </h3>
          <AutosaveBadge status={autosaveStatus} />
        </div>
        <input
          type="text"
          value={noteHeading}
          onChange={(e) => setNoteHeading(e.target.value)}
          placeholder="Título (opcional)"
          className="mb-2 h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
          disabled={!permissions.edit_note}
          data-testid="notes-heading-input"
        />
        <textarea
          value={primaryNote}
          onChange={(e) => setPrimaryNote(e.target.value)}
          placeholder="Anotações internas sobre o cliente (autosave a cada 1.5s)…"
          className="flex-1 min-h-[180px] w-full resize-y rounded-md border border-border bg-background px-3 py-2 text-sm placeholder:text-muted-foreground/60 focus:outline-none focus:ring-2 focus:ring-ring"
          disabled={!permissions.edit_note}
          data-testid="notes-textarea"
        />
        {notes.length > 1 && (
          <details className="mt-3 text-xs">
            <summary className="cursor-pointer text-muted-foreground">Histórico de notas ({notes.length - 1})</summary>
            <ul className="mt-2 space-y-2">
              {notes.slice(1).map((n) => (
                <li key={n.id} className="border-l-2 border-muted pl-3 py-1" data-testid={`note-history-${n.id}`}>
                  {n.heading && <div className="font-medium text-foreground">{n.heading}</div>}
                  <div className="text-muted-foreground whitespace-pre-wrap">{n.description}</div>
                  <div className="text-[10px] text-muted-foreground/70 mt-1">
                    {n.created_by_name ?? '—'} · {formatDate(n.created_at)}
                  </div>
                </li>
              ))}
            </ul>
          </details>
        )}
      </section>

      {/* Documentos */}
      <section className="rounded-lg border border-border bg-background p-4">
        <div className="flex items-center justify-between mb-3">
          <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
            <Paperclip size={14} className="text-muted-foreground" aria-hidden />
            Anexos ({documents.length})
          </h3>
          {permissions.upload && (
            <>
              <input
                ref={fileRef}
                type="file"
                multiple
                onChange={handleUpload}
                className="hidden"
                aria-label="Selecionar arquivos"
                data-testid="documents-file-input"
              />
              <Button
                size="sm"
                variant="outline"
                onClick={() => fileRef.current?.click()}
                disabled={uploading}
                data-testid="documents-upload-btn"
              >
                {uploading ? (
                  <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                ) : (
                  <Upload className="mr-1.5 h-3.5 w-3.5" />
                )}
                {uploading ? 'Enviando…' : 'Enviar'}
              </Button>
            </>
          )}
        </div>

        {documents.length === 0 ? (
          <div className="py-12 text-center text-xs text-muted-foreground" data-testid="documents-empty">
            <Paperclip className="mx-auto h-8 w-8 text-muted-foreground/40 mb-2" strokeWidth={1.5} aria-hidden />
            Nenhum anexo. Envie comprovantes, contratos, fotos.
          </div>
        ) : (
          <ul className="space-y-1.5">
            {documents.map((d) => (
              <li
                key={d.id}
                className="flex items-center gap-3 rounded-md border border-border bg-background p-2.5 hover:bg-muted/30"
                data-testid={`document-row-${d.id}`}
              >
                <FileText size={16} className="text-muted-foreground flex-shrink-0" aria-hidden />
                <div className="flex-1 min-w-0">
                  <div className="text-sm font-medium text-foreground truncate">
                    {d.display_name ?? d.file_name}
                  </div>
                  <div className="text-[10px] text-muted-foreground">
                    {formatBytes(d.file_size)} · {d.uploaded_by_name ?? '—'} · {formatDate(d.created_at)}
                  </div>
                </div>
                <Button variant="ghost" size="sm" asChild>
                  <a href={d.download_url} target="_blank" rel="noopener noreferrer" aria-label={`Baixar ${d.file_name}`}>
                    <Download size={14} />
                  </a>
                </Button>
                {permissions.delete_document && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleDelete(d.id)}
                    aria-label={`Excluir ${d.file_name}`}
                    data-testid={`document-delete-${d.id}`}
                  >
                    <Trash2 size={14} className="text-destructive" />
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}

function AutosaveBadge({ status }: { status: 'idle' | 'saving' | 'saved' | 'error' }) {
  if (status === 'idle') return null;
  // status já estreitado pra 'saving' | 'saved' | 'error' após o early return.
  const styles: Record<'saving' | 'saved' | 'error', string> = {
    saving: 'text-muted-foreground',
    saved: 'text-success-fg',
    error: 'text-destructive-fg',
  };
  const labels: Record<'saving' | 'saved' | 'error', string> = {
    saving: 'Salvando…',
    saved: 'Salvo',
    error: 'Erro ao salvar',
  };
  return (
    <span className={'text-[10px] flex items-center gap-1 ' + styles[status]} data-testid="notes-autosave-status">
      {status === 'saving' && <Loader2 size={10} className="animate-spin" aria-hidden />}
      {labels[status]}
    </span>
  );
}
