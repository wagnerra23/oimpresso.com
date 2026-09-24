// Drawer de edição do conteúdo do site (PT-02) — fase 2 da thread Cms/01.
// RUNBOOK: memory/requisitos/Cms/RUNBOOK-admin-content.md
//
// Um formulário serve os três tipos (R1); o que muda são os RÓTULOS (R2/R5). O corpo é HTML
// cru — o TinyMCE saiu e só volta com decisão [W] (F1 §6); a sanitização acontece no render
// público (R8). `meta_description` vazia é preenchida pelo SERVIDOR (R7), não aqui.

import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';

export type Tipo = 'page' | 'blog' | 'testimonial';

interface ItemDestaque {
  icon: string;
  title: string;
  description: string;
}

/** Registro `feature` da home — o que o FeatureGrid de `/` mostra (fase 2b). */
export interface Destaques {
  id: number | null;
  title: string;
  description: string;
  content: ItemDestaque[];
}

export interface Editando {
  id: number;
  titulo: string;
  conteudo: string;
  meta_description: string;
  tags: string;
  prioridade: number | null;
  publicada: boolean;
  layout: string | null;
  imagem_url: string | null;
  destaques: Destaques | null;
}

const BASE = '/cms/cms-page';
const LIMITE_SEO = 160;
const MAX_DESTAQUES = 12;

function rotulos(tipo: Tipo, layout: string | null) {
  if (tipo === 'testimonial') return { titulo: 'Nome de quem depõe', corpo: 'Depoimento', imagem: 'Foto' };
  const corpo = layout === 'home' || layout === 'contact' ? 'Descrição' : 'Conteúdo';
  return { titulo: 'Título', corpo, imagem: 'Imagem de destaque' };
}

interface Props {
  tipo: Tipo;
  /** `null` = criando; objeto = editando essa linha. */
  item: Editando | null;
  aberto: boolean;
  onFechar: () => void;
}

export default function Editor({ tipo, item, aberto, onFechar }: Props) {
  const r = rotulos(tipo, item?.layout ?? null);
  const form = useForm({
    title: item?.titulo ?? '',
    content: item?.conteudo ?? '',
    meta_description: item?.meta_description ?? '',
    tags: item?.tags ?? '',
    priority: item?.prioridade == null ? '' : String(item.prioridade),
    is_enabled: item?.publicada ?? true,
    type: tipo,
    feature_image: null as File | null,
    destaques: item?.destaques ?? null,
  });

  function mudarItem(i: number, campo: keyof ItemDestaque, valor: string) {
    const d = form.data.destaques;
    if (!d) return;
    form.setData('destaques', { ...d, content: d.content.map((c, k) => (k === i ? { ...c, [campo]: valor } : c)) });
  }

  function salvar(e: FormEvent) {
    e.preventDefault();
    const opcoes = { forceFormData: true, preserveScroll: true, onSuccess: onFechar };
    if (item) {
      // PUT com arquivo precisa de method spoofing: multipart não viaja em PUT real.
      // O servidor já grava `meta[feature]` por CmsPageMeta::updateOrCreateMetaForPage.
      form.transform(({ destaques, ...d }) => ({
        ...d,
        is_enabled: d.is_enabled ? 1 : '',
        _method: 'put',
        ...(destaques ? { meta: { feature: destaques } } : {}),
      }));
      form.post(`${BASE}/${item.id}`, opcoes);
    } else {
      form.transform(({ destaques: _d, ...d }) => ({ ...d, is_enabled: d.is_enabled ? 1 : '' }));
      form.post(BASE, opcoes);
    }
  }

  const meta = form.data.meta_description;

  return (
    <Sheet open={aberto} onOpenChange={(v) => !v && onFechar()}>
      <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-2xl">
        <SheetHeader>
          <SheetTitle>{item ? `Editar — ${item.titulo}` : 'Novo item'}</SheetTitle>
          <SheetDescription>
            {item?.layout ? 'Página de sistema: o endereço e o layout são fixos.' : 'O endereço público sai do título.'}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={salvar} className="flex flex-col gap-4 px-4 pb-6" data-contract="cms.content.editor">
          <Campo id="title" rotulo={r.titulo} erro={form.errors.title}>
            <Input id="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
          </Campo>
          {item && form.data.title !== item.titulo && item.layout === null && tipo !== 'testimonial' && (
            <p role="status" className="-mt-2 text-xs text-muted-foreground">
              Ao salvar, o endereço público muda e o link antigo passa a dar página não encontrada.
            </p>
          )}

          <Campo id="content" rotulo={`${r.corpo} (HTML)`} erro={form.errors.content}>
            <Textarea id="content" rows={12} className="font-mono text-xs" value={form.data.content}
              onChange={(e) => form.setData('content', e.target.value)} />
          </Campo>

          <Campo id="meta_description" rotulo="Descrição para buscadores" erro={form.errors.meta_description}
            ajuda={meta.trim() === ''
              ? 'Vazia: ao salvar, recebe os 160 primeiros caracteres do conteúdo.'
              : `${meta.length} de ${LIMITE_SEO} caracteres recomendados${meta.length > LIMITE_SEO ? ' — o buscador corta o excesso' : ''}.`}>
            <Textarea id="meta_description" rows={3} value={meta}
              onChange={(e) => form.setData('meta_description', e.target.value)} />
          </Campo>

          <div className="grid gap-4 sm:grid-cols-2">
            <Campo id="tags" rotulo="Palavras-chave" erro={form.errors.tags}>
              <Input id="tags" value={form.data.tags} onChange={(e) => form.setData('tags', e.target.value)} />
            </Campo>
            <Campo id="priority" rotulo="Ordem no site" erro={form.errors.priority} ajuda="Menor aparece primeiro; vazio vai para o fim.">
              <Input id="priority" type="number" min={0} value={form.data.priority}
                onChange={(e) => form.setData('priority', e.target.value)} />
            </Campo>
          </div>

          <Campo id="feature_image" rotulo={r.imagem} erro={form.errors.feature_image}
            ajuda={item?.imagem_url ? 'Enviar outra substitui e apaga a atual.' : 'Imagem até 5 MB.'}>
            <Input id="feature_image" type="file" accept="image/*"
              onChange={(e) => form.setData('feature_image', e.target.files?.[0] ?? null)} />
          </Campo>

          <div className="flex items-center gap-2">
            <Switch id="is_enabled" checked={form.data.is_enabled} onCheckedChange={(v) => form.setData('is_enabled', v)} />
            <Label htmlFor="is_enabled">{form.data.is_enabled ? 'Publicada' : 'Rascunho'}</Label>
          </div>

          {form.data.destaques && (
            <fieldset className="flex flex-col gap-3 rounded-md border p-3" data-contract="cms.content.destaques">
              <legend className="px-1 text-sm font-medium">Destaques da página inicial</legend>
              <p className="text-xs text-muted-foreground">
                É a grade de recursos do site. Item sem título não aparece; ícone é um emoji.
              </p>
              <Input aria-label="Título da seção" value={form.data.destaques.title}
                onChange={(e) => form.setData('destaques', { ...form.data.destaques!, title: e.target.value })} />
              <Textarea aria-label="Texto da seção" rows={2} value={form.data.destaques.description}
                onChange={(e) => form.setData('destaques', { ...form.data.destaques!, description: e.target.value })} />
              {form.data.destaques.content.map((c, i) => (
                <div key={i} className="grid grid-cols-[3.5rem_1fr_auto] items-start gap-2">
                  <Input aria-label={`Ícone do destaque ${i + 1}`} value={c.icon} onChange={(e) => mudarItem(i, 'icon', e.target.value)} />
                  <div className="flex flex-col gap-1">
                    <Input aria-label={`Título do destaque ${i + 1}`} value={c.title} onChange={(e) => mudarItem(i, 'title', e.target.value)} />
                    <Textarea aria-label={`Descrição do destaque ${i + 1}`} rows={2} value={c.description}
                      onChange={(e) => mudarItem(i, 'description', e.target.value)} />
                  </div>
                  <Button type="button" variant="ghost" size="sm" aria-label={`Remover destaque ${i + 1}`}
                    onClick={() => form.setData('destaques', { ...form.data.destaques!, content: form.data.destaques!.content.filter((_, k) => k !== i) })}>
                    Remover
                  </Button>
                </div>
              ))}
              {form.data.destaques.content.length < MAX_DESTAQUES && (
                <Button type="button" variant="outline" size="sm" className="self-start"
                  onClick={() => form.setData('destaques', { ...form.data.destaques!, content: [...form.data.destaques!.content, { icon: '✨', title: '', description: '' }] })}>
                  Adicionar destaque
                </Button>
              )}
            </fieldset>
          )}

          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="ghost" onClick={onFechar}>Cancelar</Button>
            <Button type="submit" disabled={form.processing}>{form.processing ? 'Salvando…' : 'Salvar'}</Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}

function Campo({ id, rotulo, erro, ajuda, children }: {
  id: string; rotulo: string; erro?: string; ajuda?: string; children: ReactNode;
}) {
  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={id}>{rotulo}</Label>
      {children}
      {erro ? <p className="text-xs text-destructive">{erro}</p> : ajuda && <p className="text-xs text-muted-foreground">{ajuda}</p>}
    </div>
  );
}
