import { useEffect, useState } from 'react';
import { X, FileText, Music, Video as VideoIcon, File as FileIcon, Image as ImageIcon } from 'lucide-react';
import { formatBytes } from './helpers';

/**
 * US-WA-042 — Card de pré-visualização de arquivo selecionado no composer,
 * ANTES de disparar o POST `send-media`. Atendente confere antes de enviar
 * (evita "ops, mandei o PDF errado"). Mostra:
 *
 *  - Thumbnail para imagens (objectURL local — revogado no unmount)
 *  - Ícone semântico para audio/video/document
 *  - Nome do arquivo (truncado se grande)
 *  - Tamanho em KB/MB
 *  - Botão X para remover da fila
 *
 * Pattern: ler ANTES de POST → preview-then-send (igual WhatsApp Web /
 * Telegram / Chatwoot). Sem isso a UX era "click → upload imediato" que
 * causa erros operacionais frequentes.
 *
 * Limites legais Tier 0:
 *   - Caption max 1024 chars (validação `InboxController::sendMedia`)
 *   - File max 16MB (Message::MEDIA_MAX_SIZE_BYTES)
 *   - MIME whitelist enforced no backend (Message::MEDIA_MIME_WHITELIST)
 */
interface Props {
  file: File;
  onRemove: () => void;
}

export default function MediaPreviewCard({ file, onRemove }: Props) {
  const [thumbUrl, setThumbUrl] = useState<string | null>(null);
  const isImage = file.type.startsWith('image/');

  // ObjectURL local pra preview de imagem (sem upload) — revogado no unmount
  // pra não vazar memória de blob (caso atendente troque arquivo várias vezes).
  useEffect(() => {
    if (!isImage) return;
    const url = URL.createObjectURL(file);
    setThumbUrl(url);
    return () => {
      URL.revokeObjectURL(url);
      setThumbUrl(null);
    };
  }, [file, isImage]);

  // Ícone semântico pra não-imagem (audio/video/document/outro)
  let Icon = FileIcon;
  let typeLabel = 'Arquivo';
  if (file.type.startsWith('audio/')) {
    Icon = Music;
    typeLabel = 'Áudio';
  } else if (file.type.startsWith('video/')) {
    Icon = VideoIcon;
    typeLabel = 'Vídeo';
  } else if (file.type === 'application/pdf') {
    Icon = FileText;
    typeLabel = 'PDF';
  } else if (file.type.startsWith('image/')) {
    Icon = ImageIcon;
    typeLabel = 'Imagem';
  } else if (file.type) {
    Icon = FileIcon;
    typeLabel = file.type.split('/')[1]?.toUpperCase() ?? 'Arquivo';
  }

  return (
    <div
      className="flex items-center gap-2 px-2 py-1.5 rounded-md border border-border bg-muted/30 text-sm"
      data-testid={`media-preview-${file.name}`}
    >
      {isImage && thumbUrl ? (
        <img
          src={thumbUrl}
          alt={file.name}
          className="w-10 h-10 object-cover rounded shrink-0"
        />
      ) : (
        <div className="w-10 h-10 rounded bg-muted flex items-center justify-center shrink-0">
          <Icon size={18} className="opacity-70" aria-hidden />
        </div>
      )}
      <div className="min-w-0 flex-1">
        <div className="text-xs font-medium truncate" title={file.name}>
          {file.name}
        </div>
        <div className="text-[10px] text-muted-foreground">
          {typeLabel} · {formatBytes(file.size)}
        </div>
      </div>
      <button
        type="button"
        onClick={onRemove}
        className="shrink-0 h-6 w-6 rounded hover:bg-muted flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors"
        title="Remover anexo"
        aria-label={`Remover ${file.name}`}
        data-testid={`media-preview-remove-${file.name}`}
      >
        <X size={14} aria-hidden />
      </button>
    </div>
  );
}
