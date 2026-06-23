// FinCrossLinkify — Cowork KB-9.75 Financeiro Onda 7 R3 Cross-link
// (regex parser pra detectar referências cruzadas e linkar pra outros módulos).
//
// Refs:
//  - prototipo-ui/financeiro-output.jsx — cross-link Vendas ↔ Financeiro
//  - resources/js/Pages/Sells/_components/SaleLinkifier.tsx (canon Vendas Onda 3)
//
// Detecta no texto:
//   #V-NNNN   → /sells/NNNN              (Venda)
//   #BL-NNNN  → /financeiro/boletos/NNNN (Boleto · placeholder)
//   #PC-NNNN  → /compras/NNNN            (Pedido de Compra · placeholder)
//   #OS-NNNN  → /repair/job/NNNN         (Ordem de Serviço)
//   #R-NNNN   → /financeiro/contas-receber/NNNN (Recebível legacy)
//   #P-NNNN   → /financeiro/contas-pagar/NNNN   (Pagável legacy)
//
// Renderiza pills clicáveis usando Inertia router.visit (preserva SPA).

import { Fragment, type ReactNode } from 'react';
import { router } from '@inertiajs/react';

type CrossKind = 'venda' | 'boleto' | 'compra' | 'os' | 'receber' | 'pagar';

interface RefMatch {
  kind: CrossKind;
  raw: string;
  num: string;
  href: string;
}

const PATTERNS: Array<{ kind: CrossKind; re: RegExp; href: (n: string) => string }> = [
  { kind: 'venda', re: /#V-(\d{1,8})/g, href: (n) => `/sells/${n}` },
  { kind: 'boleto', re: /#BL-(\d{1,8})/g, href: (n) => `/financeiro/boletos/${n}` },
  { kind: 'compra', re: /#PC-(\d{1,8})/g, href: (n) => `/compras/${n}` },
  { kind: 'os', re: /#OS-(\d{1,8})/g, href: (n) => `/repair/job/${n}` },
  { kind: 'receber', re: /#R-(\d{1,8})/g, href: (n) => `/financeiro/contas-receber/${n}` },
  { kind: 'pagar', re: /#P-(\d{1,8})/g, href: (n) => `/financeiro/contas-pagar/${n}` },
];

const LABEL: Record<CrossKind, string> = {
  venda: 'Venda',
  boleto: 'Boleto',
  compra: 'Pedido',
  os: 'OS',
  receber: 'Receber',
  pagar: 'Pagar',
};

interface FinCrossLinkifyProps {
  text: string;
  className?: string;
}

function findAllRefs(text: string): RefMatch[] {
  const refs: RefMatch[] = [];
  for (const p of PATTERNS) {
    p.re.lastIndex = 0;
    let m: RegExpExecArray | null;
    while ((m = p.re.exec(text)) != null) {
      refs.push({
        kind: p.kind,
        raw: m[0],
        num: m[1],
        href: p.href(m[1]),
      });
    }
  }
  // sort por posição no texto (preserva ordem visual)
  return refs.sort((a, b) => text.indexOf(a.raw) - text.indexOf(b.raw));
}

export function FinCrossLinkify({ text, className = '' }: FinCrossLinkifyProps) {
  if (!text) return null;
  const refs = findAllRefs(text);
  if (refs.length === 0) return <span className={className}>{text}</span>;

  // Particiona texto em [text-before, pill, text-after, pill, ...]
  const parts: ReactNode[] = [];
  let cursor = 0;
  refs.forEach((ref, i) => {
    const at = text.indexOf(ref.raw, cursor);
    if (at < 0) return;
    if (at > cursor) {
      parts.push(<Fragment key={`t${i}`}>{text.slice(cursor, at)}</Fragment>);
    }
    parts.push(
      <button
        key={`p${i}`}
        type="button"
        className={`fin-xlink fin-xlink-${ref.kind}`}
        onClick={(e) => {
          e.preventDefault();
          e.stopPropagation();
          router.visit(ref.href);
        }}
        title={`Ir para ${LABEL[ref.kind]} ${ref.raw}`}
      >
        {ref.raw}
      </button>,
    );
    cursor = at + ref.raw.length;
  });

  if (cursor < text.length) {
    parts.push(<Fragment key="t-end">{text.slice(cursor)}</Fragment>);
  }

  return <span className={className}>{parts}</span>;
}

export default FinCrossLinkify;
