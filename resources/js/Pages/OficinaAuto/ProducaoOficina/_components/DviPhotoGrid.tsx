// DviPhotoGrid — grade de fotos polimórfica pra item DVI (Vistoria Digital).
//
// Gap 1 (2026-05-26) US-OFICINA-035 — substitui placeholder V2 FOTOS no drawer
// ServiceOrderRichSheet por upload real via `Modules/Arquivos` (ADR 0123).
//
// Pattern AutoVitals/Tekmetric 2026: foto inline em CADA item DVI (não anexo
// solto da OS). Motorista caminhão basculante Martinho biz=164 leva foto
// antes/depois pra ressarcir transportadora 3a / seguradora
// (sub-vertical 4 mecânica pesada ADR 0194).
//
// Props:
//   items            — array de fotos já anexadas (shape Arquivo do shapeArquivo backend)
//   uploadUrl        — endpoint POST upload (ex `/oficina-auto/ordens-servico/{order}/dvi/{item}/photo`)
//   deleteUrlPattern — template DELETE (substitui `{arquivo}` pelo id)
//   onUploaded       — callback após upload (parent refresh)
//   onDeleted        — callback após delete (parent refresh)
//   disabled         — desabilita upload/delete (durante request)
//
// CRÍTICO React 19 — useCallback nos handlers (lição PR #717).
// CRÍTICO F3 LICOES_F3_FINANCEIRO_REJEITADO.md — NÃO emoji (lucide-react only),
// NÃO auto-upload on-mount, NÃO window.print, NÃO nova aba.
// CRÍTICO multi-tenant Tier 0 [ADR 0093] — backend ArquivosService::attach lê
// business_id da sessão (frontend só consome).

import { useCallback, useRef, useState } from 'react';
import { Camera, Image as ImageIcon, Loader2, Trash2, X } from 'lucide-react';
import { toast } from 'sonner';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';

export interface DviPhoto {
  id: number;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  display_url: string;
  created_at: string | null;
}

interface Props {
  items: DviPhoto[];
  uploadUrl: string;
  /**
   * Template URL pra DELETE — substitui `{arquivo}` pelo id na chamada.
   * Ex: `/oficina-auto/ordens-servico/123/dvi/45/photo/{arquivo}`
   */
  deleteUrlPattern: string;
  onUploaded?: (arquivo: DviPhoto) => void;
  onDeleted?: (id: number) => void;
  disabled?: boolean;
  /** Label do botão "+ Adicionar foto" (default: "Adicionar foto"). */
  uploadLabel?: string;
}

// Pega CSRF token do meta tag (UltimatePOS canon — Laravel app injeta).
function csrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta?.getAttribute('content') ?? '';
}

export default function DviPhotoGrid({
  items,
  uploadUrl,
  deleteUrlPattern,
  onUploaded,
  onDeleted,
  disabled = false,
  uploadLabel = 'Adicionar foto',
}: Props) {
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const [uploading, setUploading] = useState(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [preview, setPreview] = useState<DviPhoto | null>(null);

  // Abre file picker (click programático no input oculto).
  const handleClickAdd = useCallback(() => {
    if (disabled || uploading) return;
    fileInputRef.current?.click();
  }, [disabled, uploading]);

  // Upload single ou multi (FormData append `photo` — backend processa 1 por request).
  const handleFilesSelected = useCallback(
    async (e: React.ChangeEvent<HTMLInputElement>) => {
      const files = e.target.files;
      if (!files || files.length === 0) return;

      // Reset input pra permitir re-selecionar mesmo arquivo (UX em retry).
      e.target.value = '';

      setUploading(true);
      let successCount = 0;
      const errors: string[] = [];

      for (const file of Array.from(files)) {
        try {
          const fd = new FormData();
          fd.append('photo', file);

          const resp = await fetch(uploadUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken(),
            },
            body: fd,
          });

          if (!resp.ok) {
            // Tenta extrair mensagem do backend (FormRequest::messages())
            let msg = `HTTP ${resp.status}`;
            try {
              const json = await resp.json();
              if (json?.message) msg = json.message;
              if (json?.errors?.photo?.[0]) msg = json.errors.photo[0];
            } catch {
              /* sem JSON body */
            }
            errors.push(`${file.name}: ${msg}`);
            continue;
          }

          const json = await resp.json();
          if (json?.arquivo) {
            onUploaded?.(json.arquivo as DviPhoto);
            successCount += 1;
          }
        } catch (err) {
          errors.push(`${file.name}: ${(err as Error)?.message ?? 'erro de rede'}`);
        }
      }

      setUploading(false);

      if (successCount > 0) {
        toast.success(
          successCount === 1
            ? 'Foto enviada com sucesso.'
            : `${successCount} fotos enviadas.`,
        );
      }
      if (errors.length > 0) {
        toast.error(errors[0], {
          description: errors.length > 1 ? `+${errors.length - 1} outras falhas` : undefined,
        });
      }
    },
    [uploadUrl, onUploaded],
  );

  // Soft-delete uma foto (DELETE → 204).
  const handleDelete = useCallback(
    async (photo: DviPhoto) => {
      if (deletingId !== null) return;
      const ok = window.confirm(
        `Remover foto "${photo.original_name}"? Soft-delete (recuperável 30d).`,
      );
      if (!ok) return;

      setDeletingId(photo.id);
      try {
        const url = deleteUrlPattern.replace('{arquivo}', String(photo.id));
        const resp = await fetch(url, {
          method: 'DELETE',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
          },
        });

        if (!resp.ok) {
          let msg = `HTTP ${resp.status}`;
          try {
            const json = await resp.json();
            if (json?.message) msg = json.message;
          } catch {
            /* sem JSON body */
          }
          throw new Error(msg);
        }

        onDeleted?.(photo.id);
        toast.success('Foto removida.');
      } catch (err) {
        toast.error((err as Error)?.message ?? 'Falha ao remover foto.');
      } finally {
        setDeletingId(null);
      }
    },
    [deleteUrlPattern, onDeleted, deletingId],
  );

  return (
    <div className="space-y-2">
      {/* Grid responsivo — auto-fill minmax 80px, gap 6px (espelha grid-cols-3 antigo
          em mobile mas escala fluidamente em telas largas — drawer 575px+). */}
      <div
        className="grid gap-1.5"
        style={{
          gridTemplateColumns: 'repeat(auto-fill, minmax(80px, 1fr))',
        }}
      >
        {items.length === 0 && (
          <div
            className="col-span-full aspect-[3/1] rounded border border-dashed border-slate-200 grid place-items-center text-center text-[10.5px] text-slate-400 leading-tight p-2"
            role="img"
            aria-label="Sem fotos anexadas"
          >
            <div className="flex flex-col items-center gap-1">
              <ImageIcon size={16} aria-hidden />
              <span>Sem fotos — anexe pelo menos 1 antes do laudo</span>
            </div>
          </div>
        )}

        {items.map((photo) => (
          <div
            key={photo.id}
            className="relative group aspect-square rounded border border-slate-200 overflow-hidden bg-slate-50"
          >
            <button
              type="button"
              onClick={() => setPreview(photo)}
              className="w-full h-full block focus:outline-none focus:ring-2 focus:ring-emerald-500"
              title={`${photo.original_name} · clique pra ampliar`}
              aria-label={`Ampliar foto ${photo.original_name}`}
            >
              <img
                src={photo.display_url}
                alt={photo.original_name}
                className="w-full h-full object-cover"
                loading="lazy"
              />
            </button>

            {/* Botão delete (overlay top-right, visível em hover/focus) */}
            <button
              type="button"
              onClick={() => handleDelete(photo)}
              disabled={disabled || deletingId === photo.id}
              className="absolute top-1 right-1 grid place-items-center w-6 h-6 rounded-full bg-rose-600/90 text-white opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed"
              title="Remover foto (soft-delete)"
              aria-label={`Remover foto ${photo.original_name}`}
            >
              {deletingId === photo.id ? (
                <Loader2 size={12} className="animate-spin" aria-hidden />
              ) : (
                <Trash2 size={12} aria-hidden />
              )}
            </button>
          </div>
        ))}
      </div>

      {/* Botão upload — accept image/* + capture environment (camera traseira mobile) */}
      <input
        ref={fileInputRef}
        type="file"
        accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
        capture="environment"
        multiple
        onChange={handleFilesSelected}
        className="sr-only"
        aria-hidden
      />
      <Button
        size="sm"
        variant="ghost"
        className="text-xs h-7"
        disabled={disabled || uploading}
        onClick={handleClickAdd}
        title="Câmera traseira em mobile, file picker em desktop"
      >
        {uploading ? (
          <Loader2 size={11} className="mr-1.5 animate-spin" aria-hidden />
        ) : (
          <Camera size={11} className="mr-1.5" aria-hidden />
        )}
        + {uploadLabel}
      </Button>

      {/* Lightbox modal pra preview full-size */}
      <Dialog open={preview !== null} onOpenChange={(open) => !open && setPreview(null)}>
        <DialogContent className="max-w-3xl p-0 overflow-hidden">
          <DialogHeader className="px-4 py-3 border-b border-border">
            <DialogTitle className="text-sm font-medium truncate">
              {preview?.original_name ?? 'Foto'}
            </DialogTitle>
            <DialogDescription className="text-xs text-muted-foreground">
              {preview ? (
                <>
                  {preview.mime_type} · {(preview.size_bytes / 1024).toFixed(0)} KB
                  {preview.created_at && (
                    <> · {new Date(preview.created_at).toLocaleString('pt-BR')}</>
                  )}
                </>
              ) : null}
            </DialogDescription>
          </DialogHeader>
          {preview && (
            <div className="bg-slate-50 grid place-items-center p-4">
              <img
                src={preview.display_url}
                alt={preview.original_name}
                className="max-w-full max-h-[70vh] object-contain rounded"
              />
            </div>
          )}
          <button
            type="button"
            onClick={() => setPreview(null)}
            className="absolute top-2 right-2 grid place-items-center w-8 h-8 rounded-full bg-background/90 text-foreground border border-border hover:bg-muted"
            aria-label="Fechar preview"
          >
            <X size={14} aria-hidden />
          </button>
        </DialogContent>
      </Dialog>
    </div>
  );
}
