// SaleLinkifier — Cowork KB-9.75 Sells Onda 3 R3 Curadoria (linkify).
// Refs:
//  - prototipo-ui/prototipos/sells-index/vendas-curation.jsx (canonical source)
//  - memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md F4
//
// Parsea ocorrências de `#V-NNNN`, `#OS-NNNN`, `#CLI-Nome`, `#orc-NNNN`
// em pills coloridas clicáveis. Default: cada match abre rota canônica.
// Custom handler via prop `onPick` opcional (ex: drawer-internal nav).

import { type ReactNode } from 'react';

type LinkifyKind = 'venda' | 'os' | 'cliente' | 'orcamento' | 'referencia';

const PATTERNS: Array<{ re: RegExp; kind: LinkifyKind }> = [
  { re: /#V-(\d+)/gi, kind: 'venda' },
  { re: /#OS-(\d+)/gi, kind: 'os' },
  { re: /#CLI-([A-Za-zÀ-ÿ0-9_-]+)/g, kind: 'cliente' },
  { re: /#orc-(\d+)/gi, kind: 'orcamento' },
];

const KIND_HREF: Record<LinkifyKind, (id: string) => string> = {
  venda: (id) => `/sells/${id}`,
  os: (id) => `/repair/${id}`,
  cliente: (id) => `/contacts/customers?search=${encodeURIComponent(id)}`,
  orcamento: (id) => `/sells/quotations?search=${id}`,
  referencia: (id) => `#${id}`,
};

const KIND_CLASS: Record<LinkifyKind, string> = {
  venda: 'vd-link-venda',
  os: 'vd-link-os',
  cliente: 'vd-link-cli',
  orcamento: 'vd-link-orc',
  referencia: 'vd-link-ref',
};

interface LinkifyMatch {
  start: number;
  end: number;
  kind: LinkifyKind;
  id: string;
  raw: string;
}

function findMatches(text: string): LinkifyMatch[] {
  const out: LinkifyMatch[] = [];
  for (const { re, kind } of PATTERNS) {
    re.lastIndex = 0;
    let m: RegExpExecArray | null;
    while ((m = re.exec(text)) !== null) {
      out.push({
        start: m.index,
        end: m.index + m[0].length,
        kind,
        id: m[1] ?? '',
        raw: m[0],
      });
    }
  }
  out.sort((a, b) => a.start - b.start);
  // Remove overlapping (preserva o primeiro).
  const filtered: LinkifyMatch[] = [];
  let lastEnd = -1;
  for (const m of out) {
    if (m.start >= lastEnd) {
      filtered.push(m);
      lastEnd = m.end;
    }
  }
  return filtered;
}

interface SaleLinkifierProps {
  text: string;
  onPick?: (id: string, kind: LinkifyKind) => void;
}

export default function SaleLinkifier({ text, onPick }: SaleLinkifierProps): ReactNode {
  if (!text) return null;
  const matches = findMatches(text);
  if (matches.length === 0) {
    return <>{text}</>;
  }

  const parts: ReactNode[] = [];
  let cursor = 0;
  matches.forEach((m, idx) => {
    if (m.start > cursor) {
      parts.push(<span key={`t-${idx}`}>{text.slice(cursor, m.start)}</span>);
    }
    parts.push(
      <a
        key={`l-${idx}`}
        className={`vd-link ${KIND_CLASS[m.kind]}`}
        href={KIND_HREF[m.kind](m.id)}
        onClick={(e) => {
          if (onPick) {
            e.preventDefault();
            onPick(m.id, m.kind);
          }
        }}
        title={`${m.kind}: ${m.id}`}
      >
        {m.raw}
      </a>
    );
    cursor = m.end;
  });
  if (cursor < text.length) {
    parts.push(<span key="t-end">{text.slice(cursor)}</span>);
  }
  return <>{parts}</>;
}

export type { LinkifyKind, SaleLinkifierProps };
