// @memcofre
//   tela: /superadmin/frontend-pages?tela=nova
//   module: Superadmin
//   stories: thread Superadmin/08 (Blade/AdminLTE → Inertia) · decisão [W] D-PAG 2026-10-07
//   permissao: superadmin
//
// Páginas institucionais do site (Sobre, Termos, Privacidade…), servidas em /page/{slug}.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: n/a — herda PT-01 Lista + drawer PT-02 (não há sa-paginas no protótipo).
// RUNBOOK: memory/requisitos/Superadmin/RUNBOOK-paginas.md
//
// Entra atrás da chave `?tela=nova`; sem ela o backend serve a Blade. A lista NÃO imprime o HTML
// da página (a Blade imprimia) — mostra resumo sem tag. O HTML só aparece na página pública.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Badge } from '@/Components/ui/badge';
import { Skeleton } from '@/Components/ui/skeleton';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { plural } from '../_components/assinatura';

interface Pagina { id: number; titulo: string; slug: string; ordem: number; visivel: boolean; resumo: string; conteudo: string }
interface Props { paginas?: Pagina[] }

const ROTA = '/superadmin/frontend-pages';
type Alvo = Pagina | 'nova' | null;

function PaginasIndex({ paginas }: Props) {
  const [alvo, setAlvo] = useState<Alvo>(null);

  return (
    <div className="pb-8">
      <PageHeader
        title="Páginas do site"
        subtitle="Conteúdo institucional publicado em /page/{slug}"
        actions={
          <Button size="sm" className="h-8 text-xs" onClick={() => setAlvo('nova')} data-contract="superadmin.paginas.nova-botao">
            Nova página
          </Button>
        }
      />
      <div className="px-6 pt-4" data-contract="superadmin.paginas.lista">
        <Deferred data="paginas" fallback={<Skeleton className="h-64 w-full" />}>
          <Lista paginas={paginas ?? []} onEditar={setAlvo} />
        </Deferred>
      </div>
      {alvo !== null && <Gaveta key={alvo === 'nova' ? 'nova' : alvo.id} alvo={alvo} onFechar={() => setAlvo(null)} />}
    </div>
  );
}

function Lista({ paginas, onEditar }: { paginas: Pagina[]; onEditar: (p: Pagina) => void }) {
  if (paginas.length === 0) {
    return (
      <Card>
        <CardContent className="p-0">
          <EmptyState title="Nenhuma página cadastrada" description="Termos de uso e política de privacidade costumam ser as primeiras." />
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardContent className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-muted-foreground">
            <tr>
              <th className="px-4 py-2 font-medium">Página</th>
              <th className="px-4 py-2 font-medium">Endereço</th>
              <th className="px-4 py-2 text-right font-medium">Ordem no menu</th>
              <th className="px-4 py-2 font-medium">Situação</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {paginas.map((p) => (
              <tr key={p.id} className="cursor-pointer hover:bg-muted/40" onClick={() => onEditar(p)}>
                <td className="px-4 py-2">
                  <b className="block">{p.titulo || 'Sem título'}</b>
                  <span className="line-clamp-1 text-xs text-muted-foreground">{p.resumo}</span>
                </td>
                <td className="px-4 py-2 text-xs text-muted-foreground">/page/{p.slug}</td>
                <td className="px-4 py-2 text-right tabular-nums">{p.ordem}</td>
                <td className="px-4 py-2">
                  <Badge variant={p.visivel ? 'success' : 'neutral'}>{p.visivel ? 'Visível' : 'Oculta'}</Badge>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        <p className="border-t px-4 py-2 text-xs text-muted-foreground">{plural(paginas.length, 'página', 'páginas')}</p>
      </CardContent>
    </Card>
  );
}

function Gaveta({ alvo, onFechar }: { alvo: Pagina | 'nova'; onFechar: () => void }) {
  const p = alvo === 'nova' ? null : alvo;
  const form = useForm({
    title: p?.titulo ?? '',
    slug: p?.slug ?? '',
    menu_order: String(p?.ordem ?? 0),
    is_shown: p ? p.visivel : true,
    content: p?.conteudo ?? '',
  });
  const [apagar, setApagar] = useState(false);
  const ok = { preserveScroll: true, onSuccess: onFechar };
  const salvar = () => {
    form.transform((d) => ({ ...d, is_shown: d.is_shown ? 1 : 0 }));
    if (p) form.put(`${ROTA}/${p.id}`, ok);
    else form.post(ROTA, ok);
  };
  const excluir = () => p && router.delete(`${ROTA}/${p.id}`, ok);

  return (
    <Sheet open onOpenChange={(aberto) => !aberto && onFechar()}>
      <SheetContent className="flex w-[min(640px,92vw)] flex-col sm:max-w-none" data-contract="superadmin.paginas.form">
        <SheetHeader>
          <SheetTitle>{p ? 'Editar página' : 'Nova página'}</SheetTitle>
          <SheetDescription>O endereço público é /page/ seguido do slug.</SheetDescription>
        </SheetHeader>
        <div className="flex flex-1 flex-col gap-4 overflow-y-auto px-4">
          <Campo id="pg-titulo" rotulo="Título">
            <Input id="pg-titulo" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
          </Campo>
          <Campo id="pg-slug" rotulo="Slug" erro={form.errors.slug}>
            <Input id="pg-slug" value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} />
          </Campo>
          <Campo id="pg-ordem" rotulo="Ordem no menu">
            <Input id="pg-ordem" inputMode="numeric" className="tabular-nums" value={form.data.menu_order} onChange={(e) => form.setData('menu_order', e.target.value)} />
          </Campo>
          <div className="flex items-center gap-2">
            <Switch id="pg-visivel" checked={form.data.is_shown} onCheckedChange={(v) => form.setData('is_shown', v)} />
            <Label htmlFor="pg-visivel" className="text-sm font-normal">Visível no site</Label>
          </div>
          <Campo id="pg-conteudo" rotulo="Conteúdo (HTML)">
            <Textarea id="pg-conteudo" rows={12} value={form.data.content} onChange={(e) => form.setData('content', e.target.value)} />
          </Campo>
        </div>
        <SheetFooter className="flex-row justify-between">
          {p ? (
            apagar ? (
              <Button variant="destructive" onClick={excluir}>Confirmar exclusão</Button>
            ) : (
              <Button variant="ghost" onClick={() => setApagar(true)}>Excluir</Button>
            )
          ) : <span />}
          <Button onClick={salvar} disabled={form.processing || form.data.slug.trim() === ''}>
            {form.processing ? 'Salvando…' : 'Salvar'}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}

function Campo({ id, rotulo, erro, children }: { id: string; rotulo: string; erro?: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-1">
      <Label htmlFor={id}>{rotulo}</Label>
      {children}
      {erro && <p className="text-xs text-destructive">{erro}</p>}
    </div>
  );
}

PaginasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default PaginasIndex;
