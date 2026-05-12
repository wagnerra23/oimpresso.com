import { useEffect, useState } from 'react';
import { X, Download, ZoomIn, ZoomOut, ChevronLeft, ChevronRight } from 'lucide-react';

/**
 * US-WA-043 (PR-8 CYCLE-07) — Lightbox modal reusável pra mídia inbound/outbound.
 *
 * Usado pelo `MediaContent` em ConversationThread quando atendente clica numa
 * imagem da thread. Substitui o modal inline anterior (linhas 1075-1097) por
 * componente isolado, testável e com navegação Anterior/Próxima entre TODAS as
 * imagens da conversa.
 *
 * Features:
 *  - Click fora ou ESC fecha
 *  - Botão zoom in/out (escala 1× ↔ 2.5× via CSS transform)
 *  - Botão download (target=_blank com download attribute)
 *  - Setas Anterior/Próxima quando >1 imagem no conjunto
 *  - Keyboard: ESC=fechar, ←/→ navega, +/- zoom
 *
 * Props:
 *  - urls: array de URLs das imagens (mesma ordem da thread cronológica)
 *  - filenames: array paralelo de filenames (mesmo length que urls; usado em download attr)
 *  - currentIndex: idx inicial (clique inicial)
 *  - onClose: callback fechar
 */
interface Props {
  urls: string[];
  filenames: (string | null)[];
  currentIndex: number;
  onClose: () => void;
}

export default function MediaFullscreenModal({ urls, filenames, currentIndex, onClose }: Props) {
  const [index, setIndex] = useState(currentIndex);
  const [zoomed, setZoomed] = useState(false);

  // Reset zoom ao trocar imagem
  useEffect(() => setZoomed(false), [index]);

  // Keyboard handlers
  useEffect(() => {
    function handler(e: KeyboardEvent) {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
      } else if (e.key === 'ArrowLeft' && index > 0) {
        e.preventDefault();
        setIndex((i) => Math.max(0, i - 1));
      } else if (e.key === 'ArrowRight' && index < urls.length - 1) {
        e.preventDefault();
        setIndex((i) => Math.min(urls.length - 1, i + 1));
      } else if (e.key === '+' || e.key === '=') {
        e.preventDefault();
        setZoomed(true);
      } else if (e.key === '-') {
        e.preventDefault();
        setZoomed(false);
      }
    }
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [index, urls.length, onClose]);

  const currentUrl = urls[index];
  const currentName = filenames[index] ?? 'imagem';
  const hasPrev = index > 0;
  const hasNext = index < urls.length - 1;

  if (!currentUrl) return null;

  return (
    <div
      className="fixed inset-0 bg-black/85 z-50 flex items-center justify-center p-4 cursor-zoom-out"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-label="Visualizador de mídia"
      data-testid="media-fullscreen-modal"
    >
      <img
        src={currentUrl}
        alt={currentName}
        className={`max-w-full max-h-full object-contain transition-transform ${
          zoomed ? 'scale-[2.5] cursor-zoom-out' : 'cursor-zoom-in'
        }`}
        onClick={(e) => {
          e.stopPropagation();
          setZoomed((z) => !z);
        }}
      />

      {/* Toolbar superior direita: zoom + download + fechar */}
      <div className="absolute top-3 right-3 flex items-center gap-1">
        <button
          type="button"
          onClick={(e) => { e.stopPropagation(); setZoomed((z) => !z); }}
          className="w-9 h-9 rounded bg-black/40 hover:bg-black/60 text-white flex items-center justify-center transition-colors"
          aria-label={zoomed ? 'Diminuir zoom' : 'Aumentar zoom'}
          title={zoomed ? 'Diminuir (-)' : 'Aumentar (+)'}
        >
          {zoomed ? <ZoomOut size={18} /> : <ZoomIn size={18} />}
        </button>
        <a
          href={currentUrl}
          download={currentName}
          target="_blank"
          rel="noopener noreferrer"
          onClick={(e) => e.stopPropagation()}
          className="w-9 h-9 rounded bg-black/40 hover:bg-black/60 text-white flex items-center justify-center transition-colors"
          aria-label="Baixar imagem"
          title="Baixar"
        >
          <Download size={18} />
        </a>
        <button
          type="button"
          onClick={(e) => { e.stopPropagation(); onClose(); }}
          className="w-9 h-9 rounded bg-black/40 hover:bg-black/60 text-white flex items-center justify-center transition-colors"
          aria-label="Fechar"
          title="Fechar (ESC)"
        >
          <X size={20} />
        </button>
      </div>

      {/* Navegação anterior/próxima — só renderiza se houver mais imagens */}
      {hasPrev && (
        <button
          type="button"
          onClick={(e) => { e.stopPropagation(); setIndex((i) => i - 1); }}
          className="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center transition-colors"
          aria-label="Anterior"
          title="Anterior (←)"
        >
          <ChevronLeft size={22} />
        </button>
      )}
      {hasNext && (
        <button
          type="button"
          onClick={(e) => { e.stopPropagation(); setIndex((i) => i + 1); }}
          className="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center transition-colors"
          aria-label="Próxima"
          title="Próxima (→)"
        >
          <ChevronRight size={22} />
        </button>
      )}

      {/* Contador (só quando >1) */}
      {urls.length > 1 && (
        <div className="absolute bottom-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-black/40 text-white text-xs tabular-nums">
          {index + 1} / {urls.length}
        </div>
      )}
    </div>
  );
}
