// @docvault
//   tela: /essentials/todo/show
//   module: Essentials
//   status: implementada
//   stories: US-ESSE-001
//   rules: R-ESSE-001
//   adrs: ui/0001
//   tests: Modules/Essentials/Tests/Feature/TodoShowTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import {
  Activity,
  ArrowLeft,
  ClipboardList,
  Clock,
  Download,
  ExternalLink,
  File as FileIcon,
  FileSpreadsheet,
  Flag,
  MessageCircle,
  Pencil,
  Send,
  Trash2,
  UploadCloud,
  Users as UsersIcon,
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
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

interface Option { value: string; label: string; }

interface TodoDetail {
  id: number;
  task_id: string | null;
  task: string;
  description: string | null;
  status: string | null;
  priority: string | null;
  date: string | null;
  end_date: string | null;
  estimated_hours: string | null;
  created_by: number | null;
  created_at: string | null;
  updated_at: string | null;
  assigned_by: { id: number | null; name: string | null };
  users: Array<{ id: number; name: string }>;
}

interface Comment {
  id: number;
  comment: string;
  author_id: number;
  author_name: string;
  created_at: string | null;
  created_at_human: string | null;
  can_delete: boolean;
}

interface Doc {
  id: number;
  name: string;
  description: string | null;
  url: string;
  uploaded_by: string;
  uploaded_at: string | null;
  can_delete: boolean;
}

interface ActivityEntry {
  id: number;
  description: string;
  causer_name: string;
  created_at: string | null;
}

interface SharedSheet {
  id: number | null;
  name: string;
  url: string | null;
  created_at: string | null;
}

interface Props {
  todo: TodoDetail;
  comments: Comment[];
  documents: Doc[];
  activities: ActivityEntry[];
  statuses: Option[];
  priorities: Option[];
  can: { add: boolean; edit: boolean; delete: boolean; assign: boolean };
}

type Tab = 'comments' | 'documents' | 'activities';

const statusTone: Record<string, string> = {
  new: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
  in_progress: 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/30',
  on_hold: 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30',
  completed: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
};

const priorityTone: Record<string, string> = {
  low: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
  medium: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
  high: 'bg-orange-500/15 text-orange-700 dark:text-orange-300',
  urgent: 'bg-red-500/15 text-red-700 dark:text-red-300',
};

const labelFor = (opts: Option[], value: string | null) =>
  value ? opts.find((o) => o.value === value)?.label ?? value : '—';

export default function TodoShow({
  todo,
  comments: initialComments,
  documents: initialDocs,
  activities,
  statuses,
  priorities,
  can,
}: Props) {
  const [tab, setTab] = useState<Tab>('comments');
  const [deleteCommentId, setDeleteCommentId] = useState<number | null>(null);
  const [deleteDocId, setDeleteDocId] = useState<number | null>(null);

  const [sharedOpen, setSharedOpen] = useState(false);
  const [sharedLoading, setSharedLoading] = useState(false);
  const [sharedSheets, setSharedSheets] = useState<SharedSheet[]>([]);

  // TODO inertia-v3: revisar timing reset (agora so no onFinish)
  const commentForm = useForm<{ task_id: number; comment: string }>({
    task_id: todo.id,
    comment: '',
  });

  // TODO inertia-v3: revisar timing reset (agora so no onFinish)
  const uploadForm = useForm<{
    task_id: number;
    description: string;
    documents: File[];
  }>({
    task_id: todo.id,
    description: '',
    documents: [],
  });

  const submitComment = (e: FormEvent) => {
    e.preventDefault();
    commentForm.post('/essentials/todo/add-comment', {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Comentário adicionado.');
        commentForm.setData('comment', '');
      },
      onError: () => toast.error('Falha ao comentar.'),
    });
  };

  const submitUpload = (e: FormEvent) => {
    e.preventDefault();
    if (uploadForm.data.documents.length === 0) {
      toast.error('Selecione pelo menos um arquivo.');
      return;
    }
    uploadForm.post('/essentials/todo/upload-document', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Anexo(s) enviado(s).');
        uploadForm.reset('documents', 'description');
        const input = document.getElementById('documents-input') as HTMLInputElement | null;
        if (input) input.value = '';
      },
      onError: () => toast.error('Falha no upload.'),
    });
  };

  const confirmDeleteComment = () => {
    if (deleteCommentId === null) return;
    router.get(`/essentials/todo/delete-comment/${deleteCommentId}`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Comentário removido.');
        setDeleteCommentId(null);
      },
      onError: () => toast.error('Falha ao remover.'),
    });
  };

  const confirmDeleteDoc = () => {
    if (deleteDocId === null) return;
    router.get(`/essentials/todo/delete-document/${deleteDocId}`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Anexo removido.');
        setDeleteDocId(null);
      },
      onError: () => toast.error('Falha ao remover anexo.'),
    });
  };

  const openSharedDocs = async () => {
    setSharedOpen(true);
    setSharedLoading(true);
    try {
      const res = await fetch(`/essentials/view-todo-${todo.id}-share-docs`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      setSharedSheets(Array.isArray(data?.sheets) ? data.sheets : []);
    } catch (err) {
      toast.error('Não foi possível carregar documentos compartilhados.');
      setSharedSheets([]);
    } finally {
      setSharedLoading(false);
    }
  };

  return (
    <>
      <Head title={`Tarefa ${todo.task_id ?? '#' + todo.id}`} />
      <div className="mx-auto max-w-5xl p-6 space-y-4">
        <header className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
          <div className="min-w-0">
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2 flex-wrap">
              <ClipboardList size={22} />
              <span className="font-mono text-base text-muted-foreground">{todo.task_id}</span>
              <span className="truncate">{todo.task}</span>
            </h1>
            <div className="text-sm text-muted-foreground mt-1 flex items-center gap-2 flex-wrap">
              {todo.status && (
                <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded border text-[10px] font-medium ${statusTone[todo.status] ?? 'bg-muted'}`}>
                  {labelFor(statuses, todo.status)}
                </span>
              )}
              {todo.priority && (
                <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium ${priorityTone[todo.priority] ?? 'bg-muted'}`}>
                  <Flag size={10} /> {labelFor(priorities, todo.priority)}
                </span>
              )}
              <span className="text-xs">Criada em {todo.created_at}</span>
            </div>
          </div>
          <div className="flex gap-2 flex-wrap">
            <Button variant="outline" size="sm" onClick={openSharedDocs} className="gap-1.5">
              <FileSpreadsheet size={14} /> Docs compartilhados
            </Button>
            {can.edit && (
              <Button variant="outline" size="sm" asChild>
                <Link href={`/essentials/todo/${todo.id}/edit`}>
                  <Pencil size={14} className="mr-1.5" /> Editar
                </Link>
              </Button>
            )}
            <Button variant="outline" size="sm" asChild>
              <Link href="/essentials/todo">
                <ArrowLeft size={14} className="mr-1.5" /> Voltar
              </Link>
            </Button>
          </div>
        </header>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Dados</CardTitle>
          </CardHeader>
          <CardContent className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div>
              <div className="text-xs text-muted-foreground mb-0.5">Início</div>
              <div className="font-medium">{todo.date ?? '—'}</div>
            </div>
            <div>
              <div className="text-xs text-muted-foreground mb-0.5">Previsão de término</div>
              <div className="font-medium">{todo.end_date ?? '—'}</div>
            </div>
            <div>
              <div className="text-xs text-muted-foreground mb-0.5 flex items-center gap-1">
                <Clock size={12} /> Horas estimadas
              </div>
              <div className="font-medium">{todo.estimated_hours ?? '—'}</div>
            </div>
            <div>
              <div className="text-xs text-muted-foreground mb-0.5">Criada por</div>
              <div className="font-medium">{todo.assigned_by.name ?? '—'}</div>
            </div>
            <div className="md:col-span-2">
              <div className="text-xs text-muted-foreground mb-0.5 flex items-center gap-1">
                <UsersIcon size={12} /> Atribuída a
              </div>
              <div className="flex flex-wrap gap-1">
                {todo.users.length === 0 ? (
                  <span className="text-muted-foreground text-xs">—</span>
                ) : (
                  todo.users.map((u) => (
                    <Badge key={u.id} variant="secondary" className="text-[10px]">{u.name}</Badge>
                  ))
                )}
              </div>
            </div>
            {todo.description && (
              <div className="md:col-span-3">
                <div className="text-xs text-muted-foreground mb-0.5">Descrição</div>
                <div
                  className="text-sm whitespace-pre-wrap rounded bg-muted/30 p-3"
                  dangerouslySetInnerHTML={{ __html: todo.description }}
                />
              </div>
            )}
          </CardContent>
        </Card>

        {/* Tabs */}
        <div className="border-b border-border flex gap-1">
          <TabButton active={tab === 'comments'} onClick={() => setTab('comments')} icon={<MessageCircle size={14} />}>
            Comentários <span className="ml-1 text-xs text-muted-foreground">({initialComments.length})</span>
          </TabButton>
          <TabButton active={tab === 'documents'} onClick={() => setTab('documents')} icon={<FileIcon size={14} />}>
            Anexos <span className="ml-1 text-xs text-muted-foreground">({initialDocs.length})</span>
          </TabButton>
          <TabButton active={tab === 'activities'} onClick={() => setTab('activities')} icon={<Activity size={14} />}>
            Atividades <span className="ml-1 text-xs text-muted-foreground">({activities.length})</span>
          </TabButton>
        </div>

        {tab === 'comments' && (
          <Card>
            <CardContent className="pt-6 space-y-4">
              <form onSubmit={submitComment} className="space-y-2">
                <Label htmlFor="new_comment">Novo comentário</Label>
                <Textarea
                  id="new_comment"
                  rows={3}
                  value={commentForm.data.comment}
                  onChange={(e) => commentForm.setData('comment', e.target.value)}
                  placeholder="Escreva um comentário sobre essa tarefa…"
                  required
                />
                {commentForm.errors.comment && (
                  <p className="text-xs text-destructive">{commentForm.errors.comment}</p>
                )}
                <div className="flex justify-end">
                  <Button type="submit" size="sm" disabled={commentForm.processing} className="gap-1.5">
                    <Send size={14} /> Enviar
                  </Button>
                </div>
              </form>

              {initialComments.length === 0 ? (
                <div className="py-8 text-center text-sm text-muted-foreground">
                  Ainda não há comentários nesta tarefa.
                </div>
              ) : (
                <ul className="space-y-3 border-t border-border pt-3">
                  {initialComments.map((c) => (
                    <li key={c.id} className="flex gap-3 items-start">
                      <div className="flex-shrink-0 size-8 rounded-full bg-primary/15 text-primary flex items-center justify-center text-xs font-semibold">
                        {initialsOf(c.author_name)}
                      </div>
                      <div className="flex-1 min-w-0 rounded border border-border bg-card p-3">
                        <div className="flex items-center justify-between gap-2 mb-1">
                          <div className="text-xs">
                            <strong>{c.author_name}</strong>
                            <span className="text-muted-foreground ml-2">
                              {c.created_at_human ?? c.created_at}
                            </span>
                          </div>
                          {c.can_delete && (
                            <Button
                              size="sm"
                              variant="ghost"
                              className="h-6 w-6 p-0 text-muted-foreground hover:text-destructive"
                              onClick={() => setDeleteCommentId(c.id)}
                              aria-label="Remover comentário"
                            >
                              <Trash2 size={12} />
                            </Button>
                          )}
                        </div>
                        <p className="text-sm whitespace-pre-wrap">{c.comment}</p>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        )}

        {tab === 'documents' && (
          <Card>
            <CardContent className="pt-6 space-y-4">
              <form onSubmit={submitUpload} className="space-y-3">
                <div className="space-y-1">
                  <Label htmlFor="documents-input">Anexar arquivos</Label>
                  <Input
                    id="documents-input"
                    type="file"
                    multiple
                    onChange={(e) => uploadForm.setData('documents', Array.from(e.target.files ?? []))}
                    accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip"
                  />
                  <p className="text-xs text-muted-foreground">
                    Até 10 MB por arquivo. PDF, imagens, planilhas, documentos e arquivos compactados.
                  </p>
                  {uploadForm.errors.documents && (
                    <p className="text-xs text-destructive">{uploadForm.errors.documents}</p>
                  )}
                </div>
                <div className="space-y-1">
                  <Label htmlFor="upload_description">Descrição (opcional)</Label>
                  <Input
                    id="upload_description"
                    value={uploadForm.data.description}
                    onChange={(e) => uploadForm.setData('description', e.target.value)}
                    placeholder="Ex: Relatório final do mês"
                  />
                </div>
                {uploadForm.progress && (
                  <div className="h-1.5 bg-muted rounded overflow-hidden">
                    <div
                      className="h-full bg-primary transition-all"
                      style={{ width: `${uploadForm.progress.percentage ?? 0}%` }}
                    />
                  </div>
                )}
                <div className="flex justify-end">
                  <Button
                    type="submit"
                    size="sm"
                    disabled={uploadForm.processing || uploadForm.data.documents.length === 0}
                    className="gap-1.5"
                  >
                    <UploadCloud size={14} /> Enviar anexos
                  </Button>
                </div>
              </form>

              {initialDocs.length === 0 ? (
                <div className="py-8 text-center text-sm text-muted-foreground border-t border-border">
                  Nenhum anexo enviado ainda.
                </div>
              ) : (
                <div className="overflow-x-auto border-t border-border pt-3">
                  <table className="w-full text-sm">
                    <thead className="text-xs text-muted-foreground">
                      <tr>
                        <th className="text-left p-2 font-medium">Arquivo</th>
                        <th className="text-left p-2 font-medium">Descrição</th>
                        <th className="text-left p-2 font-medium">Enviado por</th>
                        <th className="text-left p-2 font-medium">Em</th>
                        <th className="text-right p-2 font-medium"></th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {initialDocs.map((d) => (
                        <tr key={d.id}>
                          <td className="p-2 font-medium truncate max-w-xs">{d.name}</td>
                          <td className="p-2 text-xs text-muted-foreground">{d.description ?? '—'}</td>
                          <td className="p-2 text-xs">{d.uploaded_by}</td>
                          <td className="p-2 text-xs">{d.uploaded_at ?? '—'}</td>
                          <td className="p-2 text-right">
                            <div className="flex justify-end gap-1">
                              <Button size="sm" variant="outline" asChild>
                                <a href={d.url} download className="text-xs gap-1">
                                  <Download size={12} /> Baixar
                                </a>
                              </Button>
                              {d.can_delete && (
                                <Button
                                  size="sm"
                                  variant="ghost"
                                  className="text-destructive hover:text-destructive"
                                  onClick={() => setDeleteDocId(d.id)}
                                >
                                  <Trash2 size={12} />
                                </Button>
                              )}
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {tab === 'activities' && (
          <Card>
            <CardContent className="pt-6">
              {activities.length === 0 ? (
                <div className="py-8 text-center text-sm text-muted-foreground">
                  Nenhuma atividade registrada para essa tarefa.
                </div>
              ) : (
                <ul className="space-y-2">
                  {activities.map((a) => (
                    <li key={a.id} className="flex items-start gap-3 text-sm">
                      <div className="size-7 rounded-full bg-muted flex items-center justify-center mt-0.5">
                        <Activity size={12} />
                      </div>
                      <div>
                        <div>
                          <strong>{a.causer_name}</strong>{' '}
                          <span className="text-muted-foreground">{a.description}</span>
                        </div>
                        <div className="text-xs text-muted-foreground">{a.created_at}</div>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        )}
      </div>

      <AlertDialog open={deleteCommentId !== null} onOpenChange={(open) => !open && setDeleteCommentId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover comentário?</AlertDialogTitle>
            <AlertDialogDescription>
              Essa ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDeleteComment}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Remover
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={deleteDocId !== null} onOpenChange={(open) => !open && setDeleteDocId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover anexo?</AlertDialogTitle>
            <AlertDialogDescription>
              O arquivo será apagado do servidor e não poderá ser recuperado.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDeleteDoc}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Remover
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <Dialog open={sharedOpen} onOpenChange={setSharedOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <FileSpreadsheet size={16} /> Documentos compartilhados
            </DialogTitle>
          </DialogHeader>
          <div className="pt-2">
            {sharedLoading ? (
              <div className="py-6 text-center text-sm text-muted-foreground">Carregando…</div>
            ) : sharedSheets.length === 0 ? (
              <div className="py-6 text-center text-sm text-muted-foreground">
                Nenhum documento compartilhado com essa tarefa.
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead className="text-xs text-muted-foreground">
                  <tr>
                    <th className="text-left p-2 font-medium">Nome</th>
                    <th className="text-left p-2 font-medium">Criado em</th>
                    <th className="text-right p-2"></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {sharedSheets.map((s, idx) => (
                    <tr key={s.id ?? idx}>
                      <td className="p-2">{s.name}</td>
                      <td className="p-2 text-xs text-muted-foreground">{s.created_at ?? '—'}</td>
                      <td className="p-2 text-right">
                        {s.url && (
                          <Button size="sm" variant="outline" asChild>
                            <a href={s.url} target="_blank" rel="noreferrer" className="text-xs gap-1">
                              Abrir <ExternalLink size={12} />
                            </a>
                          </Button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}

TodoShow.layout = (page: ReactNode) => (
  <AppShellV2 breadcrumbItems={[
    { label: 'Essentials' },
    { label: 'Tarefas', href: '/essentials/todo' },
  ]}>
    {page}
  </AppShellV2>
);

function TabButton({
  active,
  onClick,
  icon,
  children,
}: {
  active: boolean;
  onClick: () => void;
  icon: React.ReactNode;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`px-3 py-2 text-sm flex items-center gap-1.5 border-b-2 -mb-px transition ${
        active
          ? 'border-primary text-foreground font-medium'
          : 'border-transparent text-muted-foreground hover:text-foreground'
      }`}
    >
      {icon}
      {children}
    </button>
  );
}

function initialsOf(name: string): string {
  if (!name || name === '—') return '?';
  const parts = name.trim().split(/\s+/);
  const a = parts[0]?.[0] ?? '';
  const b = parts.length > 1 ? parts[parts.length - 1][0] : '';
  return (a + b).toUpperCase();
}
