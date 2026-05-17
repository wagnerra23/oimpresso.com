// SalePresentationMode — Cowork KB-9.75 Sells Onda 4 R4 Distribuição (apresentar fullscreen).
// Refs:
//  - prototipo-ui/prototipos/sells-index/vendas-output.jsx (canonical VdPresentationMode)
//  - resources/css/sells-cowork-distribuicao.css (.vd-presentation)
//
// Modo apresentação fullscreen escuro 4 slides:
//   intro (cliente + título) · itens (lista grande) · valor (R$ gigante) · próximos passos
// Sem IDs internos visíveis pro cliente. Setas + dots + Esc.

import { useEffect, useState, type ReactNode } from 'react';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';

interface PresentationLine {
  product_name: string | null;
  quantity: number;
  subtotal: number;
}

interface PresentationVenda {
  customer_name: string | null;
  final_total: number;
  payment_status: string;
  payment_method?: string | null;
  lines: PresentationLine[];
  business_name?: string | null;
}

interface Props {
  venda: PresentationVenda;
  open: boolean;
  onClose: () => void;
}

const fmt = (n: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);

export default function SalePresentationMode({ venda, open, onClose }: Props): ReactNode {
  const [slide, setSlide] = useState(0);
  const total = 4;

  useEffect(() => {
    if (!open) {
      setSlide(0);
      return;
    }
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
      if (e.key === 'ArrowRight' || e.key === ' ') {
        e.preventDefault();
        setSlide((s) => Math.min(total - 1, s + 1));
      }
      if (e.key === 'ArrowLeft') {
        e.preventDefault();
        setSlide((s) => Math.max(0, s - 1));
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  const itemsCount = venda.lines.length;
  const paymentLabel =
    venda.payment_status === 'paid'
      ? 'Recebida'
      : venda.payment_status === 'partial'
        ? 'Parcialmente recebida'
        : 'A receber';

  return (
    <div className="vd-presentation-bd">
      <button
        type="button"
        className="vd-presentation-close"
        onClick={onClose}
        aria-label="Fechar apresentação"
        title="Esc"
      >
        <X size={18} />
      </button>

      <div className="vd-presentation-stage">
        {slide === 0 && (
          <div className="vd-slide vd-slide-intro">
            <small>{venda.business_name ?? 'Oimpresso'}</small>
            <h1>Resumo da venda</h1>
            <p>{venda.customer_name ?? 'Consumidor Final'}</p>
          </div>
        )}
        {slide === 1 && (
          <div className="vd-slide vd-slide-items">
            <h2>Itens ({itemsCount})</h2>
            <ul>
              {venda.lines.map((l, i) => (
                <li key={i}>
                  <span className="vd-slide-item-q">{l.quantity}×</span>
                  <span className="vd-slide-item-n">{l.product_name ?? '—'}</span>
                  <span className="vd-slide-item-v">{fmt(l.subtotal)}</span>
                </li>
              ))}
            </ul>
          </div>
        )}
        {slide === 2 && (
          <div className="vd-slide vd-slide-value">
            <small>Total</small>
            <h1 className="vd-slide-total">{fmt(venda.final_total)}</h1>
            <p>{paymentLabel}</p>
            {venda.payment_method && <span>{venda.payment_method}</span>}
          </div>
        )}
        {slide === 3 && (
          <div className="vd-slide vd-slide-next">
            <h2>Próximos passos</h2>
            <ol>
              <li>Confirmar dados pra emissão fiscal</li>
              <li>Aprovação de arte / detalhes técnicos quando aplicável</li>
              <li>Pagamento conforme combinado</li>
              <li>Produção / entrega / retirada</li>
            </ol>
          </div>
        )}
      </div>

      <div className="vd-presentation-nav">
        <button
          type="button"
          className="vd-presentation-arr"
          onClick={() => setSlide((s) => Math.max(0, s - 1))}
          disabled={slide === 0}
          aria-label="Anterior"
        >
          <ChevronLeft size={20} />
        </button>
        <div className="vd-presentation-dots">
          {Array.from({ length: total }, (_, i) => (
            <span key={i} className={`vd-presentation-dot ${i === slide ? 'on' : ''}`} />
          ))}
        </div>
        <button
          type="button"
          className="vd-presentation-arr"
          onClick={() => setSlide((s) => Math.min(total - 1, s + 1))}
          disabled={slide === total - 1}
          aria-label="Próximo"
        >
          <ChevronRight size={20} />
        </button>
      </div>
    </div>
  );
}

export type { PresentationVenda };
