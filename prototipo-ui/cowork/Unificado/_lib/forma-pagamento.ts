// Forma de pagamento — fonte única (rótulos PT-BR + ícones) compartilhada
// entre Index (drawer + coluna), FinEditPanel (edição) e TituloCreateSheet
// (criação). Espelha o enum backend fin_titulos.forma_pagamento /
// fin_titulo_baixas.meio_pagamento (Titulo::FORMAS_PAGAMENTO).
import {
  Banknote, QrCode, Barcode, CreditCard, ArrowLeftRight,
  ReceiptText, Scale, Wallet,
  type LucideIcon,
} from 'lucide-react';

export type MeioPagamento =
  | 'dinheiro' | 'pix' | 'boleto' | 'cartao_credito' | 'cartao_debito'
  | 'transferencia' | 'cheque' | 'compensacao' | 'outro';

export const FORMA_PAGAMENTO_OPCOES: { value: MeioPagamento; label: string }[] = [
  { value: 'dinheiro', label: 'Dinheiro' },
  { value: 'pix', label: 'Pix' },
  { value: 'boleto', label: 'Boleto' },
  { value: 'cartao_credito', label: 'Cartão de crédito' },
  { value: 'cartao_debito', label: 'Cartão de débito' },
  { value: 'transferencia', label: 'Transferência' },
  { value: 'cheque', label: 'Cheque' },
  { value: 'compensacao', label: 'Compensação' },
  { value: 'outro', label: 'Outro' },
];

const LABELS: Record<MeioPagamento, string> = Object.fromEntries(
  FORMA_PAGAMENTO_OPCOES.map((o) => [o.value, o.label]),
) as Record<MeioPagamento, string>;

const ICONS: Record<MeioPagamento, LucideIcon> = {
  dinheiro: Banknote,
  pix: QrCode,
  boleto: Barcode,
  cartao_credito: CreditCard,
  cartao_debito: CreditCard,
  transferencia: ArrowLeftRight,
  cheque: ReceiptText,
  compensacao: Scale,
  outro: Wallet,
};

export function formaPagamentoLabel(v: string | null | undefined): string {
  if (!v) return '—';
  return LABELS[v as MeioPagamento] ?? v;
}

export function formaPagamentoIcon(v: string | null | undefined): LucideIcon | null {
  if (!v) return null;
  return ICONS[v as MeioPagamento] ?? null;
}
