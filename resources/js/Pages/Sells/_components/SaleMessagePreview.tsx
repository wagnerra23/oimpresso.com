// SaleMessagePreview — Cowork KB-9.75 Sells Onda 4 R4 Distribuição (WhatsApp message preview).
// Refs:
//  - prototipo-ui/prototipos/sells-index/vendas-output.jsx (canonical VdMessagePreview)
//
// 3 templates (Confirmação · Retirada · Cobrança) com substituição de
// variáveis {{cliente}} {{id}} {{total}} {{forma}} {{seller}} {{prazo}}
// {{vencimento}} {{status}} {{data}}. Tira de variáveis visível + bolha
// WhatsApp verde + botões Copiar / Abrir WhatsApp (encodeURI deep-link).

import { useMemo, useState, type ReactNode } from 'react';
import { Copy, MessageCircle, Check } from 'lucide-react';

interface MessageVenda {
  invoice_no: string;
  transaction_date: string;
  final_total: number;
  payment_status: string;
  payment_method?: string | null;
  customer_name: string | null;
  customer_mobile?: string | null;
  seller_name?: string | null;
  pay_term_days?: number | null;
  due_date?: string | null;
}

interface Template {
  id: 'confirm' | 'pickup' | 'overdue';
  label: string;
  body: string;
}

const TEMPLATES: Template[] = [
  {
    id: 'confirm',
    label: 'Confirmação',
    body:
      `Oi {{cliente}}! Confirmando sua venda #{{id}} feita em {{data}} com {{seller}}. ` +
      `Total: {{total}} via {{forma}}. {{status}}. Qualquer dúvida me chama!`,
  },
  {
    id: 'pickup',
    label: 'Retirada / Entrega',
    body:
      `Oi {{cliente}}, seu pedido #{{id}} já está disponível pra retirada/entrega 🎉. ` +
      `Total {{total}}. {{seller}} te avisou? Qualquer ajuste fala comigo.`,
  },
  {
    id: 'overdue',
    label: 'Cobrança amigável',
    body:
      `Oi {{cliente}}, passando pra lembrar do seu pedido #{{id}} ({{total}}). ` +
      `Pagamento {{status}} — vencimento {{vencimento}}. ` +
      `Se precisar parcelar ou já pagou, me avisa pra eu atualizar aqui!`,
  },
];

const fmtBRL = (n: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);

function formatDateBR(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleDateString('pt-BR');
}

function substitute(body: string, venda: MessageVenda): string {
  const statusLabel: Record<string, string> = {
    paid: 'pagamento confirmado',
    partial: 'pagamento parcial',
    due: 'pagamento pendente',
  };
  const vars: Record<string, string> = {
    cliente: venda.customer_name ?? 'cliente',
    id: venda.invoice_no,
    total: fmtBRL(venda.final_total),
    forma: venda.payment_method ?? 'método combinado',
    seller: venda.seller_name ?? 'nossa equipe',
    prazo: venda.pay_term_days ? `${venda.pay_term_days} dias` : '—',
    vencimento: formatDateBR(venda.due_date),
    status: statusLabel[venda.payment_status] ?? venda.payment_status,
    data: formatDateBR(venda.transaction_date),
  };
  return body.replace(/\{\{(\w+)\}\}/g, (_m, key: string) => vars[key] ?? `{{${key}}}`);
}

function buildWhatsappUrl(phone: string | null | undefined, text: string): string {
  const digits = (phone ?? '').replace(/\D/g, '');
  const base = digits ? `https://wa.me/55${digits}` : 'https://wa.me/';
  return `${base}?text=${encodeURIComponent(text)}`;
}

interface Props {
  venda: MessageVenda;
}

export default function SaleMessagePreview({ venda }: Props): ReactNode {
  const [active, setActive] = useState<Template['id']>('confirm');
  const [copied, setCopied] = useState(false);

  const tpl = useMemo(() => TEMPLATES.find((t) => t.id === active) ?? TEMPLATES[0]!, [active]);
  const body = useMemo(() => substitute(tpl.body, venda), [tpl, venda]);
  const waUrl = useMemo(() => buildWhatsappUrl(venda.customer_mobile, body), [venda.customer_mobile, body]);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(body);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1500);
    } catch (_) {
      /* fallback noop */
    }
  };

  return (
    <div className="vd-msg">
      <div className="vd-msg-tabs" role="tablist">
        {TEMPLATES.map((t) => (
          <button
            key={t.id}
            type="button"
            role="tab"
            aria-selected={t.id === active}
            className={`vd-msg-tab ${t.id === active ? 'on' : ''}`}
            onClick={() => setActive(t.id)}
          >
            {t.label}
          </button>
        ))}
      </div>

      <div className="vd-msg-vars">
        <small>
          variáveis: <kbd>cliente</kbd> <kbd>id</kbd> <kbd>total</kbd> <kbd>forma</kbd>{' '}
          <kbd>seller</kbd> <kbd>prazo</kbd> <kbd>vencimento</kbd> <kbd>status</kbd> <kbd>data</kbd>
        </small>
      </div>

      <div className="vd-msg-bubble">
        <p>{body}</p>
        <small className="vd-msg-time">agora</small>
      </div>

      <div className="vd-msg-actions">
        <button type="button" className="vd-msg-copy" onClick={copy}>
          {copied ? <Check size={12} /> : <Copy size={12} />}
          {copied ? 'Copiado!' : 'Copiar texto'}
        </button>
        <a
          className="vd-msg-wa"
          href={waUrl}
          target="_blank"
          rel="noopener noreferrer"
          title={
            venda.customer_mobile
              ? `Abrir WhatsApp pra ${venda.customer_mobile}`
              : 'Abrir WhatsApp (sem telefone cadastrado)'
          }
        >
          <MessageCircle size={12} />
          Abrir no WhatsApp
        </a>
      </div>
    </div>
  );
}

export type { MessageVenda };
