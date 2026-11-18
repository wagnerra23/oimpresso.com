/**
 * QuadroAssinatura — quadro para o cliente assinar com o dedo (ou o mouse) e devolver o desenho em PNG.
 *
 * Só desenha: não envia nada. Cada traço terminado chama `onChange` com o data URL `data:image/png;base64,…`;
 * "Limpar" chama com `null`. Quem usa decide para onde o PNG vai (o backend valida com App\Support\AssinaturaPng:
 * PNG de até 200 KB).
 *
 * Fundo branco e tinta preta fixos de propósito: o PNG vai para o arquivo e para o protocolo, não segue o tema.
 *
 * Consumidores: Pages/ComunicacaoVisual/Instalacao (comprovante da instalação). A entrega da OS na Oficina
 * (Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderSignaturePad) ainda tem o quadro dela; passa a usar
 * este num PR próprio.
 */
import { useCallback, useEffect, useRef, useState, type PointerEvent } from 'react';
import { Eraser } from 'lucide-react';

interface Props {
  /** PNG em data URL depois de cada traço; null quando o quadro é limpo. */
  onChange: (png: string | null) => void;
  disabled?: boolean;
  /** Nome acessível do quadro. */
  rotulo?: string;
}

export function QuadroAssinatura({ onChange, disabled = false, rotulo = 'Quadro de assinatura' }: Props) {
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const desenhando = useRef(false);
  // Ref, não só estado: o pointerup do mesmo traço lê o valor antes do re-render.
  const tinta = useRef(false);
  const [temTinta, setTemTinta] = useState(false);

  const pintarFundo = useCallback(() => {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext('2d');
    if (!canvas || !ctx) return;
    const { width, height } = canvas.getBoundingClientRect();
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);
  }, []);

  // Bitmap no tamanho exibido (nítido em tela densa) e fundo branco, para o PNG não sair transparente.
  useEffect(() => {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext('2d');
    if (!canvas || !ctx) return;
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    const { width, height } = canvas.getBoundingClientRect();
    canvas.width = Math.max(1, Math.round(width * ratio));
    canvas.height = Math.max(1, Math.round(height * ratio));
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#000000';
    pintarFundo();
  }, [pintarFundo]);

  const ponto = (e: PointerEvent<HTMLCanvasElement>) => {
    const r = e.currentTarget.getBoundingClientRect();
    return { x: e.clientX - r.left, y: e.clientY - r.top };
  };

  const comecar = (e: PointerEvent<HTMLCanvasElement>) => {
    const ctx = e.currentTarget.getContext('2d');
    if (disabled || !ctx) return;
    e.currentTarget.setPointerCapture?.(e.pointerId);
    desenhando.current = true;
    const { x, y } = ponto(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
  };

  const mover = (e: PointerEvent<HTMLCanvasElement>) => {
    const ctx = e.currentTarget.getContext('2d');
    if (!desenhando.current || !ctx) return;
    const { x, y } = ponto(e);
    ctx.lineTo(x, y);
    ctx.stroke();
    tinta.current = true;
    setTemTinta(true);
  };

  const terminar = () => {
    if (!desenhando.current) return;
    desenhando.current = false;
    const canvas = canvasRef.current;
    if (canvas && tinta.current) onChange(canvas.toDataURL('image/png'));
  };

  const limpar = () => {
    pintarFundo();
    tinta.current = false;
    setTemTinta(false);
    onChange(null);
  };

  return (
    <div>
      <canvas
        ref={canvasRef}
        aria-label={rotulo}
        className="block h-36 w-full touch-none rounded-md border border-dashed border-border bg-white"
        onPointerDown={comecar}
        onPointerMove={mover}
        onPointerUp={terminar}
        onPointerLeave={terminar}
      />
      <button
        type="button"
        onClick={limpar}
        disabled={disabled || !temTinta}
        className="mt-2 inline-flex h-9 items-center gap-1 rounded-md border border-border px-3 text-sm text-muted-foreground hover:text-foreground disabled:opacity-50"
      >
        <Eraser size={14} aria-hidden /> Limpar
      </button>
    </div>
  );
}
