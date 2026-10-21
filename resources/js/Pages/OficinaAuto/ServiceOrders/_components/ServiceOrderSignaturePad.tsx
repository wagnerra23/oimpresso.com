// ServiceOrderSignaturePad — quadro de assinatura do cliente na entrega da OS
// (US-OFICINA-048 · UC-FLX-22).
//
// Aparece dentro do checklist da entrega (ServiceOrderStageGate) quando a empresa exige a
// assinatura e ela ainda não foi colhida. O cliente assina com o dedo/mouse; "Salvar assinatura"
// envia o PNG (data URL, ≤ 200 KB — o mesmo contrato da entrega do pedido no app) para
// POST /oficina-auto/ordens-servico/{id}/assinatura. Salvar NÃO entrega a OS: só cumpre o requisito;
// a entrega continua sendo o botão do checklist.
//
// Multi-tenant Tier 0 [ADR 0093]: o backend escopa pela OS.

import { useCallback, useEffect, useRef, useState } from 'react';
import { Eraser, Loader2, PenLine } from 'lucide-react';
import { toast } from 'sonner';
import { Inline } from '@/Components/layout';

interface Props {
  serviceOrderId: number;
  /** Chamado depois de gravar — o checklist recarrega o gate. */
  onSaved: () => void;
}

function csrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export default function ServiceOrderSignaturePad({ serviceOrderId, onSaved }: Props) {
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const drawing = useRef(false);
  const [hasInk, setHasInk] = useState(false);
  const [nome, setNome] = useState('');
  const [saving, setSaving] = useState(false);

  // Ajusta o bitmap ao tamanho exibido (nitidez em tela densa) e pinta fundo branco — o PNG sai
  // legível no laudo e no arquivo, sem fundo transparente.
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    const { width, height } = canvas.getBoundingClientRect();
    canvas.width = Math.max(1, Math.round(width * ratio));
    canvas.height = Math.max(1, Math.round(height * ratio));
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.scale(ratio, ratio);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    // Tinta preta sobre papel branco: cor fixa de propósito — o PNG vai para o arquivo, não segue o tema.
    ctx.strokeStyle = '#000000';
  }, []);

  const point = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const rect = e.currentTarget.getBoundingClientRect();
    return { x: e.clientX - rect.left, y: e.clientY - rect.top };
  };

  const onDown = useCallback((e: React.PointerEvent<HTMLCanvasElement>) => {
    const ctx = e.currentTarget.getContext('2d');
    if (!ctx) return;
    e.currentTarget.setPointerCapture(e.pointerId);
    drawing.current = true;
    const { x, y } = point(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
  }, []);

  const onMove = useCallback((e: React.PointerEvent<HTMLCanvasElement>) => {
    if (!drawing.current) return;
    const ctx = e.currentTarget.getContext('2d');
    if (!ctx) return;
    const { x, y } = point(e);
    ctx.lineTo(x, y);
    ctx.stroke();
    setHasInk(true);
  }, []);

  const onUp = useCallback(() => {
    drawing.current = false;
  }, []);

  const clear = useCallback(() => {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext('2d');
    if (!canvas || !ctx) return;
    const { width, height } = canvas.getBoundingClientRect();
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);
    setHasInk(false);
  }, []);

  const save = useCallback(async () => {
    const canvas = canvasRef.current;
    if (!canvas || !hasInk) return;
    setSaving(true);
    try {
      const res = await fetch(`/oficina-auto/ordens-servico/${serviceOrderId}/assinatura`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ assinatura_png: canvas.toDataURL('image/png'), recebido_por: nome.trim() || null }),
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) {
        toast.error(json?.errors?.assinatura_png?.[0] ?? json?.message ?? `HTTP ${res.status}`);
        return;
      }
      toast.success('Assinatura do cliente salva.');
      onSaved();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Falha ao salvar a assinatura.');
    } finally {
      setSaving(false);
    }
  }, [hasInk, nome, onSaved, serviceOrderId]);

  return (
    <div className="mt-2.5 rounded-md border border-border bg-background p-2.5" data-testid="os-assinatura-quadro">
      <Inline className="mb-1.5 gap-1.5 text-[11.5px] font-medium text-foreground">
        <PenLine size={13} aria-hidden />
        Assinatura do cliente
      </Inline>
      <canvas
        ref={canvasRef}
        aria-label="Quadro de assinatura do cliente"
        className="block h-32 w-full touch-none rounded border border-dashed border-border bg-white"
        onPointerDown={onDown}
        onPointerMove={onMove}
        onPointerUp={onUp}
        onPointerLeave={onUp}
      />
      <input
        type="text"
        value={nome}
        maxLength={120}
        onChange={(e) => setNome(e.target.value)}
        placeholder="Nome de quem recebe (opcional)"
        aria-label="Nome de quem recebe"
        className="mt-2 h-9 w-full rounded-md border border-border bg-background px-2 text-[12px]"
      />
      <Inline className="mt-2 gap-2">
        <button
          type="button"
          onClick={clear}
          disabled={!hasInk || saving}
          className="inline-flex h-9 items-center gap-1 rounded-md border border-border px-3 text-[12px] text-muted-foreground hover:text-foreground disabled:opacity-50"
        >
          <Eraser size={13} aria-hidden />
          Limpar
        </button>
        <button
          type="button"
          onClick={save}
          disabled={!hasInk || saving}
          className="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-md bg-primary px-3 text-[12px] font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
        >
          {saving ? <Loader2 size={13} className="animate-spin" /> : null}
          Salvar assinatura
        </button>
      </Inline>
    </div>
  );
}
