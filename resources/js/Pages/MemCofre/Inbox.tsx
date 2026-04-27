// @memcofre
//   tela: /docs/inbox
//   module: Cofre de Memórias
//   status: implementada
//   stories: US-DOCVAULT-003
//   rules: R-DOCVAULT-001, R-DOCVAULT-003
//   adrs: 0003, 0004
//   tests: Modules/MemCofre/Tests/Feature/InboxTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import {
  Check,
  ExternalLink,
  Inbox as InboxIcon,
  Save,
  Search,
  Sparkles,
  Trash2,
  X,
} from 'lucide-react';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';

interface Source {
  id: number | null;
  type: string | null;
  title: string | null;
  storage_url: string | null;
  source_url: string | null;
}

interface Evidence {
  id: number;
  kind: string;
  status: string;
  module_target: string | null;
  content: string;
  ai_confidence: number | null;
  extracted_by_ai: boolean;
  suggested_story_id: string | null;
  suggested_rule_id: string | null;
  notes: string | null;
  created_at_human: string | null;
  source: Source;
}

interface Paginated {
  data: Evidence[];
  total: number;
  current_page: number;
  last_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  evidences: Paginated;
  filtros: { status: string; module: string | null; q?: string };
  counts: Record<string, number>;
}

const statusTabs = [
  { key: 'pending', label: 'Pendentes', tone: 'amber' },
  { key: 'triaged', label: 'Triadas', tone: 'sky' },
  { key: 'applied', label: 'Aplicadas', tone: 'emerald' },
  { key: 'rejected', label: 'Rejeitadas', tone: 'muted' },
];

const kindLabel: Record<string, string> = {
  bug: 'Bug',
  rule: 'Regra',
  flow: 'Fluxo',
  quote: 'Citação',
  screenshot: 'Print',
  decision: 'Decisão',
};

export default function MemCofreInbox({ evidences, filtros, counts }: Props) {
  const [editTarget, setEditTarget] = useState<Evidence | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Evidence | null>(null);
  const [searchTerm, setSearchTerm] = useState(filtros.q ?? '');

  // Debounce 300ms — envia busca pro backend via Scout (ADR arq/0006)
  useEffect(() => {
    if (searchTerm === (filtros.q ?? '')) return;
    const handle = setTimeout(() => {
      router.get('/memcofre/inbox',
        { ...filtros, q: searchTerm || undefined },
        { preserveScroll: true, preserveState: true, replace: true }
      );
    }, 300);
    return () => clearTimeout(handle);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchTerm]);

  const editForm = useForm({
    status: 'pending',
    kind: 'quote',
    module_target: '',
    suggested_story_id: '',
    suggested_rule_id: '',
    notes: '',
  });

  const setStatus = (s: string) => {
    router.get('/memcofre/inbox', { ...filtros, status: s }, { preserveScroll: true, preserveState: true });
  };

  const openEdit = (e: Evidence) => {
    editForm.setData({
      status: e.status,
      kind: e.kind,
      module_target: e.module_target ?? '',
      suggested_story_id: e.suggested_story_id ?? '',
      suggested_rule_id: e.suggested_rule_id ?? '',
      notes: e.notes ?? '',
    });
    setEditTarget(e);
  };

  const submitEdit = (ev: FormEvent) => {
    ev.preventDefault();
    if (!editTarget) return;
    editForm.post(`/docs/inbox/${editTarget.id}/triage`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Evidência atualizada.');
        setEditTarget(null);
      },
      onError: () => toast.error('Falha ao salvar.'),
    });
  };

  const apply = (e: Evidence) => {
    router.post(`/docs/inbox/${e.id}/apply`, {}, {
      preserveScroll: true,
      onSuccess: () => toast.success('Marcada como aplicada.'),
      onError: () => toast.error('Falha.'),
    });
  };

  const confirmDelete = () => {
    if (!deleteTarget) return;
    router.delete(`/docs/inbox/${deleteTarget.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Removida.');
        setDeleteTarget(null);
      },
    });
  };

  return (
    <>
      <Head title="Cofre de Memórias — Inbox" />
      <div className="mx-auto max-w-6xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <InboxIcon size={22} /> Inbox
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Evidências aguardando classificação. Triagem humana valida antes de virar requisito.
            </p>
          </div>
        </header>

        {/* Search bar (Scout via backend — driver `database` agora, `meilisearch` quando ativar) */}
        <div className="relative">
          <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder="Buscar no conteúdo, notes, IDs sugeridos..."
            className="pl-9 pr-9"
          />
          {searchTerm && (
            <button
              type="button"
              onClick={() => setSearchTerm('')}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
              aria-label="Limpar busca"
            >
              <X size={14} />
            </button>
          )}
          {filtros.q && (
            <div className="text-[10px] text-muted-foreground mt-1 ml-1">
              Buscando via Scout · {evidences.total} resultado(s)
            </div>
          )}
        </div>

        {/* Status tabs */}
        <div className="border-b border-border flex gap-1 flex-wrap">
          {statusTabs.map((t) => (
            <button
              key={t.key}
              type="button"
              onClick={() => setStatus(t.key)}
              className={`px-3 py-2 text-sm flex items-center gap-1.5 border-b-2 -mb-px transition ${
                filtros.status === t.key
                  ? 'border-primary text-foreground font-medium'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              }`}
            >
              {t.label}
              <Badge variant="secondary" className="text-[10px]">
                {counts[t.key] ?? 0}
              </Badge>
            </button>
          ))}
        </div>

        <Card>
          <CardContent className="p-0">
            {evidences.data.length === 0 ? (
              <div className="p-12 text-center text-sm text-muted-foreground">
                <InboxIcon size={32} className="mx-auto mb-2 opacity-50" />
                Nenhuma evidência em "{filtros.status}".
                {filtros.status === 'pending' && (
                  <div className="mt-3">
                    <Button size="sm" asChild>
                      <Link href="/memcofre/ingest">Registrar evidência</Link>
                    </Button>
                  </div>
                )}
              </div>
            ) : (
              <ul className="divide-y divide-border">
                {evidences.data.map((e) => (
                  <li key={e.id} className="p-4 space-y-2 hover:bg-accent/30">
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 text-xs flex-wrap">
                          <Badge variant="secondary" className="text-[10px]">
                            {kindLabel[e.kind] ?? e.kind}
                          </Badge>
                          {e.module_target && (
                            <Badge variant="outline" className="text-[10px]">
                              {e.module_target}
                            </Badge>
                          )}
                          {e.extracted_by_ai && (
                            <Badge className="text-[10px] gap-1">
                              <Sparkles size={10} /> IA ({Math.round((e.ai_confidence ?? 0) * 100)}%)
                            </Badge>
                          )}
                          {e.source.type && (
                            <span className="text-muted-foreground">
                              via {e.source.type}: {e.source.title ?? '(sem título)'}
                            </span>
                          )}
                          <span className="ml-auto text-muted-foreground">{e.created_at_human}</span>
                        </div>
                        <p className="text-sm mt-2 whitespace-pre-wrap">{e.content}</p>
                        {e.notes && (
                          <p className="text-xs text-muted-foreground mt-1 italic">
                            Nota: {e.notes}
                          </p>
                        )}
                        {(e.suggested_story_id || e.suggested_rule_id) && (
                          <div className="text-xs text-muted-foreground mt-1 flex gap-2">
                            {e.suggested_story_id && <code>→ {e.suggested_story_id}</code>}
                            {e.suggested_rule_id && <code>→ {e.suggested_rule_id}</code>}
                          </div>
                        )}
                        {(e.source.storage_url || e.source.source_url) && (
                          <div className="mt-2 flex gap-2">
                            {e.source.storage_url && (
                              <a
                                href={e.source.storage_url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-xs text-primary hover:underline flex items-center gap-1"
                              >
                                Abrir arquivo <ExternalLink size={10} />
                              </a>
                            )}
                            {e.source.source_url && (
                              <a
                                href={e.source.source_url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-xs text-primary hover:underline flex items-center gap-1"
                              >
                                Abrir URL <ExternalLink size={10} />
                              </a>
                            )}
                          </div>
                        )}
                      </div>
                      <div className="flex-shrink-0 flex gap-1 flex-col sm:flex-row">
                        <Button size="sm" variant="outline" onClick={() => openEdit(e)}>
                          Editar
                        </Button>
                        {e.status !== 'applied' && (
                          <Button size="sm" variant="default" onClick={() => apply(e)} className="gap-1">
                            <Check size={12} /> Aplicar
                          </Button>
                        )}
                        <Button
                          size="sm"
                          variant="ghost"
                          className="text-destructive"
                          onClick={() => setDeleteTarget(e)}
                        >
                          <Trash2 size={12} />
                        </Button>
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
            {evidences.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border p-3 text-xs">
                <span className="text-muted-foreground">
                  Página {evidences.current_page} de {evidences.last_page} · {evidences.total} item(s)
                </span>
                <div className="flex gap-1">
                  {evidences.links.map((link, i) => (
                    <Button
                      key={i}
                      variant={link.active ? 'default' : 'outline'}
                      size="sm"
                      className="h-7 min-w-8 px-2 text-xs"
                      disabled={!link.url}
                      onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                    >
                      <span dangerouslySetInnerHTML={{ __html: link.label }} />
                    </Button>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Edit dialog */}
      <Dialog open={editTarget !== null} onOpenChange={(o) => !o && setEditTarget(null)}>
        <DialogContent className="max-w-lg">
          <form onSubmit={submitEdit}>
            <DialogHeader>
              <DialogTitle>Triar evidência</DialogTitle>
            </DialogHeader>
            <div className="py-4 space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label htmlFor="e-status">Status</Label>
                  <Select value={editForm.data.status} onValueChange={(v) => editForm.setData('status', v)}>
                    <SelectTrigger id="e-status"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="pending">Pendente</SelectItem>
                      <SelectItem value="triaged">Triada</SelectItem>
                      <SelectItem value="applied">Aplicada</SelectItem>
                      <SelectItem value="rejected">Rejeitada</SelectItem>
                      <SelectItem value="duplicate">Duplicada</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1">
                  <Label htmlFor="e-kind">Tipo</Label>
                  <Select value={editForm.data.kind} onValueChange={(v) => editForm.setData('kind', v)}>
                    <SelectTrigger id="e-kind"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="bug">Bug</SelectItem>
                      <SelectItem value="rule">Regra</SelectItem>
                      <SelectItem value="flow">Fluxo</SelectItem>
                      <SelectItem value="quote">Citação</SelectItem>
                      <SelectItem value="screenshot">Print</SelectItem>
                      <SelectItem value="decision">Decisão</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div className="space-y-1">
                <Label htmlFor="e-module">Módulo-alvo</Label>
                <Input
                  id="e-module"
                  value={editForm.data.module_target}
                  onChange={(ev) => editForm.setData('module_target', ev.target.value)}
                  placeholder="Ex: Essentials, PontoWr2"
                />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label htmlFor="e-story">Sugestão user story</Label>
                  <Input
                    id="e-story"
                    value={editForm.data.suggested_story_id}
                    onChange={(ev) => editForm.setData('suggested_story_id', ev.target.value)}
                    placeholder="US-ESSE-003"
                  />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="e-rule">Sugestão regra</Label>
                  <Input
                    id="e-rule"
                    value={editForm.data.suggested_rule_id}
                    onChange={(ev) => editForm.setData('suggested_rule_id', ev.target.value)}
                    placeholder="R-ESSE-007"
                  />
                </div>
              </div>
              <div className="space-y-1">
                <Label htmlFor="e-notes">Notas</Label>
                <Textarea
                  id="e-notes"
                  rows={3}
                  value={editForm.data.notes}
                  onChange={(ev) => editForm.setData('notes', ev.target.value)}
                />
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setEditTarget(null)}>
                <X size={14} className="mr-1.5" /> Cancelar
              </Button>
              <Button type="submit" disabled={editForm.processing}>
                <Save size={14} className="mr-1.5" /> Salvar
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteTarget !== null} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover evidência?</AlertDialogTitle>
            <AlertDialogDescription>
              A fonte original (screenshot/chat/arquivo) fica preservada. Só a linha de evidência é apagada.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Remover
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

MemCofreInbox.layout = (page: ReactNode) => (
  <AppShellV2 title="Inbox · Cofre" breadcrumbItems={[{ label: 'Cofre de Memórias', href: '/memcofre' }, { label: 'Inbox' }]}>
    {page}
  </AppShellV2>
);
