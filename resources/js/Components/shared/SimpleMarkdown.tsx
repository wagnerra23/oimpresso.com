import { ReactNode } from 'react';

/**
 * Renderer markdown minimalista — sem deps externas.
 *
 * Suporta: #/##/###/#### headings, **bold**, *italic*, `code inline`,
 * ```blocos de código```, - listas, > citações, [link](url), linhas --- separador.
 *
 * Não-meta: Tailwind 4 tokens semânticos. Preserva quebras de linha com <br/>.
 * Para HTML cru ou features avançadas (tabelas, mermaid), ainda cai em <pre>.
 */
export default function SimpleMarkdown({ source }: { source: string }) {
  if (!source) return null;

  const blocks = parseBlocks(source);

  return (
    <div className="prose prose-sm max-w-none text-sm leading-relaxed">
      {blocks.map((block, i) => renderBlock(block, i))}
    </div>
  );
}

type Block =
  | { kind: 'heading'; level: number; text: string }
  | { kind: 'code'; lang: string; content: string }
  | { kind: 'list'; items: string[] }
  | { kind: 'quote'; text: string }
  | { kind: 'hr' }
  | { kind: 'paragraph'; text: string };

function parseBlocks(source: string): Block[] {
  const lines = source.split(/\r?\n/);
  const blocks: Block[] = [];
  let i = 0;

  while (i < lines.length) {
    const line = lines[i];

    // Fenced code
    if (/^```/.test(line)) {
      const lang = line.replace(/^```/, '').trim();
      const chunk: string[] = [];
      i++;
      while (i < lines.length && !/^```/.test(lines[i])) {
        chunk.push(lines[i]);
        i++;
      }
      blocks.push({ kind: 'code', lang, content: chunk.join('\n') });
      i++;
      continue;
    }

    // Heading
    const headingMatch = line.match(/^(#{1,4})\s+(.+)$/);
    if (headingMatch) {
      blocks.push({ kind: 'heading', level: headingMatch[1].length, text: headingMatch[2] });
      i++;
      continue;
    }

    // HR
    if (/^---+$/.test(line.trim())) {
      blocks.push({ kind: 'hr' });
      i++;
      continue;
    }

    // Quote
    if (/^>\s?/.test(line)) {
      const chunk: string[] = [];
      while (i < lines.length && /^>\s?/.test(lines[i])) {
        chunk.push(lines[i].replace(/^>\s?/, ''));
        i++;
      }
      blocks.push({ kind: 'quote', text: chunk.join(' ') });
      continue;
    }

    // List
    if (/^[-*]\s+/.test(line)) {
      const items: string[] = [];
      while (i < lines.length && /^[-*]\s+/.test(lines[i])) {
        items.push(lines[i].replace(/^[-*]\s+/, ''));
        i++;
      }
      blocks.push({ kind: 'list', items });
      continue;
    }

    // Paragraph (consuma linhas até blank line)
    if (line.trim() === '') {
      i++;
      continue;
    }
    const paraChunk: string[] = [];
    while (i < lines.length && lines[i].trim() !== '' && !isBlockBoundary(lines[i])) {
      paraChunk.push(lines[i]);
      i++;
    }
    blocks.push({ kind: 'paragraph', text: paraChunk.join(' ') });
  }

  return blocks;
}

function isBlockBoundary(line: string): boolean {
  return /^(#{1,4}\s|```|---+$|>\s|[-*]\s)/.test(line);
}

function renderInline(text: string): ReactNode[] {
  // Pipeline simples: code > bold > italic > link
  const parts: ReactNode[] = [];
  let remaining = text;
  let key = 0;

  while (remaining.length > 0) {
    // `code inline`
    const codeMatch = remaining.match(/^`([^`]+)`/);
    if (codeMatch) {
      parts.push(<code key={key++} className="px-1 py-0.5 bg-muted rounded text-[0.9em] font-mono">{codeMatch[1]}</code>);
      remaining = remaining.slice(codeMatch[0].length);
      continue;
    }
    // **bold**
    const boldMatch = remaining.match(/^\*\*([^*]+)\*\*/);
    if (boldMatch) {
      parts.push(<strong key={key++}>{renderInline(boldMatch[1])}</strong>);
      remaining = remaining.slice(boldMatch[0].length);
      continue;
    }
    // *italic*
    const italicMatch = remaining.match(/^\*([^*]+)\*/);
    if (italicMatch) {
      parts.push(<em key={key++}>{renderInline(italicMatch[1])}</em>);
      remaining = remaining.slice(italicMatch[0].length);
      continue;
    }
    // [text](url)
    const linkMatch = remaining.match(/^\[([^\]]+)\]\(([^)]+)\)/);
    if (linkMatch) {
      parts.push(
        <a key={key++} href={linkMatch[2]} target="_blank" rel="noreferrer" className="text-primary hover:underline">
          {linkMatch[1]}
        </a>
      );
      remaining = remaining.slice(linkMatch[0].length);
      continue;
    }
    // Regular char: junta até próximo marker
    const nextMarker = remaining.search(/[`*\[]/);
    if (nextMarker === -1) {
      parts.push(<span key={key++}>{remaining}</span>);
      break;
    }
    parts.push(<span key={key++}>{remaining.slice(0, nextMarker)}</span>);
    remaining = remaining.slice(nextMarker);
    if (remaining.length > 0 && !remaining.match(/^(`|\*\*|\*|\[)/)) {
      // Marker isolado, não é início de pattern
      parts.push(<span key={key++}>{remaining[0]}</span>);
      remaining = remaining.slice(1);
    }
  }

  return parts;
}

function renderBlock(block: Block, key: number): ReactNode {
  switch (block.kind) {
    case 'heading': {
      const sizes = ['text-2xl font-bold mt-4 mb-2', 'text-xl font-bold mt-3 mb-2', 'text-base font-semibold mt-3 mb-1', 'text-sm font-semibold mt-2 mb-1'];
      const cls = sizes[block.level - 1] || sizes[3];
      const Tag = `h${block.level}` as 'h1' | 'h2' | 'h3' | 'h4';
      return <Tag key={key} className={cls}>{renderInline(block.text)}</Tag>;
    }
    case 'code':
      return (
        <pre key={key} className="my-3 p-3 bg-muted/50 rounded overflow-x-auto text-xs font-mono border border-border">
          {block.lang && <div className="text-[10px] text-muted-foreground mb-1 uppercase">{block.lang}</div>}
          <code>{block.content}</code>
        </pre>
      );
    case 'list':
      return (
        <ul key={key} className="my-2 ml-5 list-disc space-y-1">
          {block.items.map((item, i) => <li key={i}>{renderInline(item)}</li>)}
        </ul>
      );
    case 'quote':
      return <blockquote key={key} className="my-2 pl-3 border-l-2 border-border text-muted-foreground italic">{renderInline(block.text)}</blockquote>;
    case 'hr':
      return <hr key={key} className="my-4 border-border" />;
    case 'paragraph':
      return <p key={key} className="my-2">{renderInline(block.text)}</p>;
  }
}
