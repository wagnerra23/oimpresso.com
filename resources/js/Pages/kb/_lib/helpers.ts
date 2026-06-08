/**
 * KB Unificado — helpers puros
 *
 * Port direto das funções utilitárias do `kb-page.jsx`, `kb-trouble-lib.jsx`
 * (Cowork [CC]) com TypeScript estrito.
 */

import type {
  FreshnessInfo,
  KbBlock,
  KbCategory,
  KbNode,
  KbNodeType,
} from './types';

// ──────────────────────────────────────────────────────────────────
// freshnessLevel — classifica frescor a partir de updated_at (ISO ou relative)
// Port do `kb-page.jsx::freshnessLevel`
// ──────────────────────────────────────────────────────────────────

export function freshnessLevel(updatedAt: string | null): FreshnessInfo {
  if (!updatedAt) return { level: 'fresh', label: 'novo' };

  const date = new Date(updatedAt);
  if (Number.isNaN(date.getTime())) {
    // Fallback: tratar como string relativa (compat seed Cowork tipo "há 3 dias")
    const s = updatedAt.toLowerCase();
    if (/agora|hoje|min/.test(s)) return { level: 'fresh', label: 'novo' };
    if (/dia/.test(s)) {
      const n = parseInt(s.match(/\d+/)?.[0] ?? '1', 10);
      return n <= 7
        ? { level: 'fresh', label: 'fresco' }
        : { level: 'aging', label: 'recente' };
    }
    if (/semana/.test(s)) {
      const n = parseInt(s.match(/\d+/)?.[0] ?? '1', 10);
      return n <= 3
        ? { level: 'aging', label: 'recente' }
        : { level: 'stale', label: 'parado' };
    }
    if (/mês|mes/.test(s)) {
      const n = parseInt(s.match(/\d+/)?.[0] ?? '1', 10);
      return n <= 2
        ? { level: 'stale', label: 'parado' }
        : { level: 'expired', label: 'expirado' };
    }
    return { level: 'expired', label: 'expirado' };
  }

  const days = (Date.now() - date.getTime()) / 86_400_000;
  if (days < 1) return { level: 'fresh', label: 'novo' };
  if (days <= 7) return { level: 'fresh', label: 'fresco' };
  if (days <= 21) return { level: 'aging', label: 'recente' };
  if (days <= 60) return { level: 'stale', label: 'parado' };
  return { level: 'expired', label: 'expirado' };
}

// ──────────────────────────────────────────────────────────────────
// fmtRelative — "agora", "5min atrás", "3h atrás", "2d atrás", data
// ──────────────────────────────────────────────────────────────────

export function fmtRelative(iso: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso).getTime();
  if (Number.isNaN(d)) return iso; // já é relativo (compat seed)
  const diffSec = (Date.now() - d) / 1000;
  if (diffSec < 60) return 'agora';
  if (diffSec < 3600) return `${Math.floor(diffSec / 60)}min atrás`;
  if (diffSec < 86_400) return `${Math.floor(diffSec / 3600)}h atrás`;
  if (diffSec < 86_400 * 7) return `${Math.floor(diffSec / 86_400)}d atrás`;
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export const numBR = (v: number): string =>
  new Intl.NumberFormat('pt-BR').format(v ?? 0);

// ──────────────────────────────────────────────────────────────────
// fuzzyMatch — busca local (token AND nos hayfields)
// Port do `kb-page.jsx::fuzzyMatch` — aplicado em title + excerpt + tags + author
// ──────────────────────────────────────────────────────────────────

export function fuzzyMatch(node: KbNode, q: string): boolean {
  if (!q) return true;
  const tags = (node.tags ?? []).join(' ');
  const author = node.author_name ?? '';
  const hay = `${node.title} ${node.excerpt ?? ''} ${tags} ${author}`.toLowerCase();
  return q
    .toLowerCase()
    .split(/\s+/)
    .filter(Boolean)
    .every((part) => hay.includes(part));
}

// ──────────────────────────────────────────────────────────────────
// kbLinkifyText — transforma "#kb-N", "#aN", "#tN" em links clicáveis
// Port do `kb-trouble-lib.jsx::kbLinkifyText` — adaptado pro novo padrão
// ──────────────────────────────────────────────────────────────────

import * as React from 'react';

export function kbLinkifyText(
  text: string | null | undefined,
  onPickRef: (ref: string) => void,
): React.ReactNode[] {
  if (!text || typeof text !== 'string') return [text ?? ''];

  const parts: React.ReactNode[] = [];
  // Aceita #kb-NNN (slugs novos), #aNNN ou #tNNN ou #tr-XXX (compat Cowork)
  const re = /#(kb-[\w-]+|a\d+|t\d+|tr-[a-z0-9-]+)/g;
  let last = 0;
  let m: RegExpExecArray | null;
  let i = 0;
  while ((m = re.exec(text)) !== null) {
    if (m.index > last) parts.push(text.slice(last, m.index));
    const id = m[1];
    parts.push(
      React.createElement(
        'button',
        {
          key: `lk-${i++}`,
          className: 'kb-link',
          onClick: (e: React.MouseEvent) => {
            e.stopPropagation();
            onPickRef(id);
          },
        },
        `#${id}`,
      ),
    );
    last = m.index + m[0].length;
  }
  if (last < text.length) parts.push(text.slice(last));
  return parts;
}

// ──────────────────────────────────────────────────────────────────
// kbBuildArticleText — concatena body_blocks pra prompt de IA
// Port do `kb-page.jsx::window.kbBuildArticleText`
// ──────────────────────────────────────────────────────────────────

export function kbBuildArticleText(node: KbNode): string {
  if (!node.body_blocks) return node.excerpt ?? '';
  return node.body_blocks
    .map((b: KbBlock) => {
      switch (b.kind) {
        case 'para':
          return b.t;
        case 'h2':
          return `## ${b.t}`;
        case 'list':
          return b.items.map((i) => `- ${i}`).join('\n');
        case 'callout':
          return `> ${(b.tone ?? 'info').toUpperCase()}: ${b.t}`;
        case 'image':
          return b.caption ? `[imagem: ${b.caption}]` : '';
        default:
          return '';
      }
    })
    .join('\n\n');
}

// ──────────────────────────────────────────────────────────────────
// relatedNodes — top-N relacionados por overlap de tags + categoria + equip
// Port do `kb-extras.jsx::relatedArticles` — adaptado pra KbNode
// ──────────────────────────────────────────────────────────────────

export function relatedNodes(
  node: KbNode | null,
  all: KbNode[],
  n: number = 3,
): KbNode[] {
  if (!node) return [];
  const tagsA = new Set(node.tags ?? []);
  return all
    .filter((b) => b.id !== node.id && b.status !== 'deleted')
    .map((b) => {
      const tagsB = new Set(b.tags ?? []);
      let overlap = 0;
      tagsA.forEach((t) => {
        if (tagsB.has(t)) overlap++;
      });
      const catBonus = b.category_id && b.category_id === node.category_id ? 1.5 : 0;
      const equipBonus =
        node.equip && b.equip === node.equip && b.equip !== '—' ? 1 : 0;
      return { node: b, score: overlap * 2 + catBonus + equipBonus };
    })
    .filter((x) => x.score > 0)
    .sort((x, y) => y.score - x.score)
    .slice(0, n)
    .map((x) => x.node);
}

// ──────────────────────────────────────────────────────────────────
// Helpers de catálogo
// ──────────────────────────────────────────────────────────────────

export function findCategory(
  categories: KbCategory[],
  id: number | null | undefined,
): KbCategory | undefined {
  if (id == null) return undefined;
  return categories.find((c) => c.id === id);
}

export function findCategoryBySlug(
  categories: KbCategory[],
  slug: string | undefined,
): KbCategory | undefined {
  if (!slug || slug === 'all') return undefined;
  return categories.find((c) => c.slug === slug);
}

export function typeLabelPtBR(type: KbNodeType): string {
  const map: Record<KbNodeType, string> = {
    article: 'artigo',
    adr: 'ADR',
    session: 'session',
    charter: 'charter',
    runbook: 'runbook',
    briefing: 'briefing',
    spec: 'spec',
    comparativo: 'comparativo',
    reference: 'referência',
    os: 'OS',
    customer: 'cliente',
    product: 'produto',
    nfe: 'NF-e',
    equipment: 'equipamento',
    external_file: 'arquivo',
  };
  return map[type] ?? type;
}

// ──────────────────────────────────────────────────────────────────
// Heading anchors do TOC (mesmo que ArticleReader do Cowork)
// ──────────────────────────────────────────────────────────────────

export function extractHeadings(blocks: KbBlock[] | null): string[] {
  if (!blocks) return [];
  return blocks.filter((b) => b.kind === 'h2').map((b) => (b as { t: string }).t);
}

// ──────────────────────────────────────────────────────────────────
// isOutdated — sinônimo central pra evitar duplicar lógica
// ──────────────────────────────────────────────────────────────────

export function isNodeOutdated(node: KbNode): boolean {
  return node.status === 'outdated' || node.outdated_votes >= 2;
}
