// KbComposer — criar/editar SOP (ONDA 2 autoria, ADR 0150).
//   Drawer lateral com form + corpo em markdown → converte pra body_blocks.
//   Backend: POST /kb/nodes (novo) · PUT /kb/nodes/{slug} (editar) — KbNodeController.
//   MVP funcional: markdown simples (## título, - lista, > tone: callout, parágrafos).
//   Block-editor visual WYSIWYG fica pra evolução; isto já permite criar SOPs de verdade.

import * as React from 'react';
import { Loader2, Save } from 'lucide-react';
import {
  Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription,
} from '@/Components/ui/sheet';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import { toast } from 'sonner';
import type { KbBlock, KbCategory, KbNode, KbSubcategory } from '../_lib/types';

interface Props {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  /** node a editar; null = criar novo */
  node: KbNode | null;
  categories: KbCategory[];
  subcategories: KbSubcategory[];
  /** chamado após salvar com sucesso (pai recarrega a lista) */
  onSaved: () => void;
}

function csrf(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

// ── markdown ↔ body_blocks (parser simples, cobre para/h2/list/callout) ──

export function blocksToMarkdown(blocks: KbBlock[] | null | undefined): string {
  return (blocks ?? [])
    .map((b) => {
      switch (b.kind) {
        case 'h2': return `## ${b.t}`;
        case 'list': return b.items.map((i) => `- ${i}`).join('\n');
        case 'callout': return `> ${b.tone}: ${b.t}`;
        case 'image': return `![${b.alt ?? ''}](${b.src})`;
        case 'para': default: return (b as { t?: string }).t ?? '';
      }
    })
    .filter((s) => s !== '')
    .join('\n\n');
}

const CALLOUT_RE = /^(info|ok|warn|bad):\s*(.*)$/;

export function markdownToBlocks(md: string): KbBlock[] {
  const blocks: KbBlock[] = [];
  for (const chunk of md.split(/\n\s*\n/)) {
    const lines = chunk.split('\n').map((l) => l.trim()).filter(Boolean);
    if (lines.length === 0) continue;

    if (lines.every((l) => l.startsWith('- '))) {
      blocks.push({ kind: 'list', items: lines.map((l) => l.slice(2)) });
      continue;
    }
    for (const l of lines) {
      if (l.startsWith('## ')) {
        blocks.push({ kind: 'h2', t: l.slice(3) });
      } else if (l.startsWith('> ')) {
        const m = l.slice(2).match(CALLOUT_RE);
        if (m) blocks.push({ kind: 'callout', tone: m[1] as 'info' | 'ok' | 'warn' | 'bad', t: m[2] });
        else blocks.push({ kind: 'para', t: l.slice(2) });
      } else {
        blocks.push({ kind: 'para', t: l });
      }
    }
  }
  return blocks;
}

const NIVEIS = [
  { v: 'iniciante', l: 'Iniciante' },
  { v: 'intermediario', l: 'Intermediário' },
  { v: 'avancado', l: 'Avançado' },
];

export default function KbComposer({ open, onOpenChange, node, categories, subcategories, onSaved }: Props) {
  const isEdit = !!node;
  const [title, setTitle] = React.useState('');
  const [excerpt, setExcerpt] = React.useState('');
  const [categoryId, setCategoryId] = React.useState<string>('');
  const [subcategoryId, setSubcategoryId] = React.useState<string>('');
  const [nivel, setNivel] = React.useState<string>('');
  const [equip, setEquip] = React.useState('');
  const [tags, setTags] = React.useState('');
  const [markdown, setMarkdown] = React.useState('');
  const [saving, setSaving] = React.useState(false);

  // (re)inicializa quando abre / muda o node-alvo
  React.useEffect(() => {
    if (!open) return;
    setTitle(node?.title ?? '');
    setExcerpt(node?.excerpt ?? '');
    setCategoryId(node?.category_id ? String(node.category_id) : '');
    setSubcategoryId(node?.subcategory_id ? String(node.subcategory_id) : '');
    setNivel(node?.nivel ?? '');
    setEquip(node?.equip ?? '');
    setTags((node?.tags ?? []).join(', '));
    setMarkdown(blocksToMarkdown(node?.body_blocks));
    setSaving(false);
  }, [open, node]);

  const subsOfCat = React.useMemo(
    () => subcategories.filter((s) => String(s.category_id) === categoryId),
    [subcategories, categoryId],
  );

  async function save() {
    if (title.trim().length < 3) {
      toast.error('Dê um título (mín. 3 caracteres).');
      return;
    }
    setSaving(true);
    const payload = {
      title: title.trim(),
      excerpt: excerpt.trim() || null,
      category_id: categoryId ? Number(categoryId) : null,
      subcategory_id: subcategoryId ? Number(subcategoryId) : null,
      nivel: nivel || null,
      equip: equip.trim() || null,
      tags: tags.split(',').map((t) => t.trim()).filter(Boolean),
      body_blocks: markdownToBlocks(markdown),
    };
    try {
      const res = await fetch(isEdit ? `/kb/nodes/${node!.slug}` : '/kb/nodes', {
        method: isEdit ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json', Accept: 'application/json',
          'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      });
      if (!res.ok) throw res;
      toast.success(isEdit ? 'SOP atualizado.' : 'SOP criado.');
      onOpenChange(false);
      onSaved();
    } catch {
      toast.error('Falha ao salvar o SOP.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:max-w-2xl overflow-y-auto">
        <SheetHeader>
          <SheetTitle>{isEdit ? 'Editar SOP' : 'Novo SOP'}</SheetTitle>
          <SheetDescription>
            Procedimento Operacional Padrão. O corpo aceita markdown simples: <code>## título</code>, <code>- item</code> de lista, <code>&gt; warn: alerta</code>, parágrafos.
          </SheetDescription>
        </SheetHeader>

        <div className="mt-4 space-y-4">
          <div>
            <Label htmlFor="kc-title">Título *</Label>
            <Input id="kc-title" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Ex.: Como trocar a bobina da HP Latex 365" autoFocus />
          </div>

          <div>
            <Label htmlFor="kc-excerpt">Resumo</Label>
            <Input id="kc-excerpt" value={excerpt} onChange={(e) => setExcerpt(e.target.value)} placeholder="Uma linha explicando o procedimento" />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label>Categoria</Label>
              <Select value={categoryId} onValueChange={(v) => { setCategoryId(v); setSubcategoryId(''); }}>
                <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                <SelectContent>
                  {categories.map((c) => (<SelectItem key={c.id} value={String(c.id)}>{c.label}</SelectItem>))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>Subcategoria</Label>
              <Select value={subcategoryId} onValueChange={setSubcategoryId} disabled={!categoryId || subsOfCat.length === 0}>
                <SelectTrigger><SelectValue placeholder={subsOfCat.length ? 'Selecione' : '—'} /></SelectTrigger>
                <SelectContent>
                  {subsOfCat.map((s) => (<SelectItem key={s.id} value={String(s.id)}>{s.label}</SelectItem>))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label>Nível</Label>
              <Select value={nivel} onValueChange={setNivel}>
                <SelectTrigger><SelectValue placeholder="Selecione" /></SelectTrigger>
                <SelectContent>
                  {NIVEIS.map((n) => (<SelectItem key={n.v} value={n.v}>{n.l}</SelectItem>))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label htmlFor="kc-equip">Equipamento</Label>
              <Input id="kc-equip" value={equip} onChange={(e) => setEquip(e.target.value)} placeholder="Ex.: HP Latex 365" />
            </div>
          </div>

          <div>
            <Label htmlFor="kc-tags">Etiquetas (separadas por vírgula)</Label>
            <Input id="kc-tags" value={tags} onChange={(e) => setTags(e.target.value)} placeholder="bobina, manutencao, latex" />
          </div>

          <div>
            <Label htmlFor="kc-body">Corpo (markdown)</Label>
            <Textarea id="kc-body" value={markdown} onChange={(e) => setMarkdown(e.target.value)} rows={14}
              placeholder={'## Pré-requisitos\n- Bobina nova homologada\n- Impressora parada\n\n## Passos\n- Abra a tampa traseira\n- Solte o eixo\n\n> warn: nunca force o eixo com a máquina ligada'}
              className="font-mono text-[13px]" />
          </div>

          <div className="flex gap-2 pb-6">
            <Button onClick={save} disabled={saving}>
              {saving ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />} {isEdit ? 'Salvar alterações' : 'Criar SOP'}
            </Button>
            <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={saving}>Cancelar</Button>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
