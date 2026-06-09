// LaudoPhotoSection — Fotos & Laudo OS-level com 3 estados + lightbox.
//
// F3 OS-V2-1 (fila TELAS_REVIEW_QUEUE · 2026-06-09). Porta pro drawer real
// (ServiceOrderRichSheet) o protótipo Cowork APROVADO [W] 2026-06-09 (seção
// "Fotos & Laudo" do Drawer · oficina-page.jsx · `.ofc-photos*`/`.ofc-lightbox`).
//
// 3 estados:
//   VAZIO     — zona tracejada role=button (drag&drop + clique → picker image/*)
//   ENVIANDO  — thumb esmaecida + barra de progresso REAL (XHR upload.onprogress)
//   PREENCHIDO— grid 3 col, thumb com chip de legenda, tile "+" pra adicionar
//
// Lightbox: ampliar, legenda editável (PATCH original_name), Remover, Esc/clique-fora
// fecha SÓ o lightbox (captura keydown — não fecha o Sheet drawer).
//
// Backend (F3 OS-V2-1): fotos anexadas à própria ServiceOrder (HasArquivos morphTo)
// via ServiceOrderPhotoController. Entram no print A4 ("Fotos da vistoria").
//   GET    /oficina-auto/ordens-servico/{order}/fotos
//   POST   /oficina-auto/ordens-servico/{order}/fotos            (multipart `photo`)
//   PATCH  /oficina-auto/ordens-servico/{order}/fotos/{arquivo}  ({label})
//   DELETE /oficina-auto/ordens-servico/{order}/fotos/{arquivo}
//
// Touch ≥44px nos alvos (persona Técnico Repair · tablet).
// CRÍTICO React 19 — useCallback nos handlers (lição PR #717).
// CRÍTICO F3 LICOES_F3_FINANCEIRO_REJEITADO.md — sem emoji (lucide-react only),
// sem auto-upload on-mount, sem window.print, sem nova aba.
// CRÍTICO multi-tenant Tier 0 [ADR 0093] — backend escopa business_id (frontend consome).

import { useCallback, useRef, useState } from 'react';
import { Camera, ImagePlus, Loader2, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { Dialog, DialogContent, DialogTitle } from '@/Components/ui/dialog';
import { Grid, Inline } from '@/Components/layout';

export interface LaudoPhoto {
  id: number;
  label: string;
  mime_type: string;
  size_bytes: number;
  display_url: string;
  created_at: string | null;
}

interface LocalUpload {
  tempId: string;
  previewUrl: string;
  name: string;
  progress: number;
}

interface Props {
  serviceOrderId: number;
  initialPhotos: LaudoPhoto[];
}

function csrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

// Upload com progresso REAL (fetch não expõe upload.onprogress → XHR).
function uploadWithProgress(
  url: string,
  file: File,
  onProgress: (pct: number) => void,
): Promise<{ foto?: LaudoPhoto }> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.withCredentials = true;
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) onProgress(Math.min(96, Math.round((e.loaded / e.total) * 100)));
    };
    xhr.onload = () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          resolve(JSON.parse(xhr.responseText));
        } catch {
          resolve({});
        }
      } else {
        let msg = `HTTP ${xhr.status}`;
        try {
          const j = JSON.parse(xhr.responseText);
          msg = j?.message ?? j?.errors?.photo?.[0] ?? msg;
        } catch {
          /* sem JSON */
        }
        reject(new Error(msg));
      }
    };
    xhr.onerror = () => reject(new Error('Erro de rede no upload.'));
    const fd = new FormData();
    fd.append('photo', file);
    xhr.send(fd);
  });
}

export default function LaudoPhotoSection({ serviceOrderId, initialPhotos }: Props) {
  const [photos, setPhotos] = useState<LaudoPhoto[]>(initialPhotos);
  const [uploads, setUploads] = useState<LocalUpload[]>([]);
  const [dragOver, setDragOver] = useState(false);
  const [lightboxId, setLightboxId] = useState<number | null>(null);
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const base = `/oficina-auto/ordens-servico/${serviceOrderId}/fotos`;

  // Esc / clique-fora fecham SÓ o lightbox: o Radix Dialog é a camada topo da
  // pilha DismissableLayer, então consome o Escape antes do Sheet drawer.

  const addFiles = useCallback(
    (files: FileList | File[]) => {
      const imgs = Array.from(files).filter((f) => f.type && f.type.startsWith('image/'));
      if (imgs.length === 0) {
        toast.error('Apenas imagens são aceitas no laudo.');
        return;
      }
      imgs.forEach((file, k) => {
        const tempId = `up_${Date.now()}_${k}`;
        const previewUrl = URL.createObjectURL(file);
        setUploads((prev) => [...prev, { tempId, previewUrl, name: file.name, progress: 4 }]);

        void uploadWithProgress(base, file, (pct) =>
          setUploads((prev) => prev.map((u) => (u.tempId === tempId ? { ...u, progress: pct } : u))),
        )
          .then((res) => {
            if (res.foto) {
              setPhotos((prev) => [...prev, res.foto as LaudoPhoto]);
            }
            URL.revokeObjectURL(previewUrl);
            setUploads((prev) => prev.filter((u) => u.tempId !== tempId));
          })
          .catch((err: Error) => {
            URL.revokeObjectURL(previewUrl);
            setUploads((prev) => prev.filter((u) => u.tempId !== tempId));
            toast.error(`${file.name}: ${err.message}`);
          });
      });
    },
    [base],
  );

  const openPicker = useCallback(() => fileInputRef.current?.click(), []);

  const onPicked = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      if (e.target.files && e.target.files.length) addFiles(e.target.files);
      e.target.value = '';
    },
    [addFiles],
  );

  const dragProps = {
    onDragOver: (e: React.DragEvent) => {
      e.preventDefault();
      setDragOver(true);
    },
    onDragLeave: () => setDragOver(false),
    onDrop: (e: React.DragEvent) => {
      e.preventDefault();
      setDragOver(false);
      if (e.dataTransfer.files) addFiles(e.dataTransfer.files);
    },
  };

  const saveLabel = useCallback(
    async (photo: LaudoPhoto, label: string) => {
      const next = label.trim().slice(0, 180);
      if (!next || next === photo.label) return;
      // Otimista
      setPhotos((prev) => prev.map((p) => (p.id === photo.id ? { ...p, label: next } : p)));
      try {
        const resp = await fetch(`${base}/${photo.id}`, {
          method: 'PATCH',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
          },
          body: JSON.stringify({ label: next }),
        });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      } catch {
        setPhotos((prev) => prev.map((p) => (p.id === photo.id ? { ...p, label: photo.label } : p)));
        toast.error('Falha ao salvar a legenda.');
      }
    },
    [base],
  );

  const removePhoto = useCallback(
    async (photo: LaudoPhoto) => {
      const snapshot = photos;
      setPhotos((prev) => prev.filter((p) => p.id !== photo.id));
      setLightboxId(null);
      try {
        const resp = await fetch(`${base}/${photo.id}`, {
          method: 'DELETE',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
          },
        });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        toast.success('Foto removida do laudo.');
      } catch {
        setPhotos(snapshot);
        toast.error('Falha ao remover a foto.');
      }
    },
    [base, photos],
  );

  const isEmpty = photos.length === 0 && uploads.length === 0;
  const doneCount = photos.length;
  const lightbox = photos.find((p) => p.id === lightboxId) ?? null;

  return (
    <div className="space-y-2">
      {isEmpty ? (
        // ─── Estado VAZIO ───
        <div
          role="button"
          tabIndex={0}
          aria-label="Adicionar fotos ao laudo"
          onClick={openPicker}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              openPicker();
            }
          }}
          {...dragProps}
          className={
            'flex min-h-[88px] cursor-pointer flex-col items-center justify-center gap-1.5 ' +
            'rounded-lg border border-dashed p-4 text-center text-xs transition-colors ' +
            (dragOver
              ? 'border-primary bg-primary/5 text-primary'
              : 'border-border text-muted-foreground hover:border-primary hover:bg-primary/5 hover:text-primary')
          }
        >
          <Camera size={18} aria-hidden />
          <span className="font-medium text-foreground">Sem fotos na OS</span>
          <span>Toque pra fotografar ou arraste imagens — entram no laudo e na impressão</span>
        </div>
      ) : (
        // ─── Estado PREENCHIDO / ENVIANDO ───
        <Grid cols={3} className="gap-1.5">
          {photos.map((photo) => (
            <button
              key={photo.id}
              type="button"
              onClick={() => setLightboxId(photo.id)}
              title={`${photo.label} · clique pra ampliar`}
              aria-label={`Ampliar foto ${photo.label}`}
              className="group relative aspect-square overflow-hidden rounded-md border border-border bg-muted/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              <img
                src={photo.display_url}
                alt={photo.label}
                loading="lazy"
                className="h-full w-full object-cover"
              />
              {photo.label && (
                <span className="absolute inset-x-1 bottom-1 truncate rounded bg-black/70 px-1.5 py-0.5 text-[9px] font-medium text-white">
                  {photo.label}
                </span>
              )}
            </button>
          ))}

          {uploads.map((u) => (
            <div
              key={u.tempId}
              className="relative aspect-square overflow-hidden rounded-md border border-border bg-muted/30"
              aria-label={`Enviando ${u.name}`}
            >
              <img src={u.previewUrl} alt="" className="h-full w-full object-cover opacity-45 saturate-50" />
              <span className="absolute inset-x-1.5 bottom-1.5 grid place-items-center">
                <span className="h-1 w-full overflow-hidden rounded-full bg-border">
                  <span
                    className="block h-full rounded-full bg-primary transition-all"
                    style={{ width: `${u.progress}%` }}
                  />
                </span>
              </span>
              <Loader2
                size={16}
                className="absolute inset-0 m-auto animate-spin text-muted-foreground"
                aria-hidden
              />
            </div>
          ))}

          {/* Tile "+" pra adicionar mais (≥44px de alvo) */}
          <div
            role="button"
            tabIndex={0}
            aria-label="Adicionar mais fotos"
            onClick={openPicker}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openPicker();
              }
            }}
            {...dragProps}
            className={
              'grid aspect-square min-h-[44px] cursor-pointer place-items-center rounded-md ' +
              'border border-dashed transition-colors ' +
              (dragOver
                ? 'border-primary bg-primary/5 text-primary'
                : 'border-border text-muted-foreground hover:border-primary hover:bg-primary/5 hover:text-primary')
            }
          >
            <ImagePlus size={16} aria-hidden />
          </div>
        </Grid>
      )}

      {doneCount > 0 && (
        <p className="text-[10.5px] text-muted-foreground">
          {doneCount} foto{doneCount === 1 ? '' : 's'} no laudo — {doneCount === 1 ? 'entra' : 'entram'} na impressão A4.
        </p>
      )}

      <input
        ref={fileInputRef}
        type="file"
        accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
        capture="environment"
        multiple
        onChange={onPicked}
        className="sr-only"
        aria-hidden
      />

      {/* ─── Lightbox (Radix Dialog — Esc/clique-fora fecham só esta camada) ─── */}
      <Dialog open={lightbox !== null} onOpenChange={(o) => !o && setLightboxId(null)}>
        <DialogContent className="max-w-3xl gap-0 overflow-hidden p-0">
          <DialogTitle className="sr-only">Foto do laudo da OS</DialogTitle>
          {lightbox && (
            <>
              <img
                src={lightbox.display_url}
                alt={lightbox.label}
                className="max-h-[70vh] w-full bg-black object-contain"
              />
              <Inline gap={2} className="border-t border-border px-3 py-2">
                <input
                  defaultValue={lightbox.label}
                  aria-label="Legenda da foto"
                  placeholder="legenda da foto"
                  className="min-w-0 flex-1 rounded border border-transparent bg-transparent px-1.5 py-1 text-[12.5px] font-medium text-foreground outline-none hover:border-border focus:border-ring"
                  onBlur={(e) => void saveLabel(lightbox, e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') (e.target as HTMLInputElement).blur();
                  }}
                />
                <button
                  type="button"
                  onClick={() => void removePhoto(lightbox)}
                  className="mr-6 inline-flex h-9 items-center gap-1 rounded-md px-2.5 text-xs text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                  aria-label="Remover foto do laudo"
                >
                  <Trash2 size={14} aria-hidden />
                  Remover
                </button>
              </Inline>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
