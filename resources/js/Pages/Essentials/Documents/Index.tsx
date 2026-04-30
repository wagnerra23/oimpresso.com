// @docvault
//   tela: /essentials/document
//   module: Essentials
//   status: implementada
//   rules: R-ESSE-001
//   tests: Modules/Essentials/Tests/Feature/DocumentsIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
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
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
  Download,
  Eye,
  FileText,
  FolderUp,
  Mail,
  Plus,
  Share2,
  Trash2,
  Users as UsersIcon,
} from 'lucide-react';

interface Doc {
  id: number;
  name: string;
  display_name: string;
  description: string | null;
  type: 'document' | 'memos';
  user_id: number;
  is_mine: boolean;
  shared_by: string;
  created_at: string | null;
}

interface Props {
  documents: Doc[];
  memos: Doc[];
  initialTab: 'documents' | 'memos';
  me: number;
}

interface Option { id: number; label: string; }
interface ShareState {
  users: Option[];
  roles: Option[];
  shared_user_ids: number[];
  shared_role_ids: number[];
  document_id: number;
}

export default function DocumentsIndex({ documents, memos, initialTab, me }: Props) {
  const [tab, setTab] = useState<'documents' | 'memos'>(initialTab);

  const [uploadOpen, setUploadOpen] = useState(false);
  const [memoOpen, setMemoOpen] = useState(false);
  const [memoView, setMemoView] = useState<Doc | null>(null);

  const [deleteTarget, setDeleteTarget] = useState<Doc | null>(null);

  const [shareState, setShareState] = useState<ShareState | null>(null);
  const [shareLoading, setShareLoading] = useState(false);

  // Form: Upload (tipo document)
  // TODO inertia-v3: revisar timing reset (agora so no onFinish)
  const uploadForm = useForm<{ name: File | null; description: string }>({
    name: null,
    description: '',
  });

  // Form: Memo
  // TODO inertia-v3: revisar timing reset (agora so no onFinish)
  const memoForm = useForm<{ name: string; body: string }>({
    name: '',
    body: '',
  });

  useEffect(() => {
    // Sincroniza URL quando troca tab (mantém ?type=memos)
    const expected = tab === 'memos' ? '/essentials/document?type=memos' : '/essentials/document';
    if (window.location.pathname + window.location.search !== expected) {
      window.history.replaceState(null, '', expected);
    }
  }, [tab]);

  const submitUpload = (e: FormEvent) => {
    e.preventDefault();
    if (!uploadForm.data.name) {
      toast.error('Selecione um arquivo.');
      return;
    }
    uploadForm.post('/essentials/document', {
      forceFormData: true,
      onSuccess: () => {
        toast.success('Arquivo enviado.');
        uploadForm.reset();
        const input = document.getElementById('document-file') as HTMLInputElement | null;
        if (input) input.value = '';
        setUploadOpen(false);
      },
      onError: () => toast.error('Falha no upload.'),
    });
  };

  const submitMemo = (e: FormEvent) => {
    e.preventDefault();
    memoForm.post('/essentials/document', {
      onSuccess: () => {
        toast.success('Memo criado.');
        memoForm.reset();
        setMemoOpen(false);
        setTab('memos');
      },
      onError: () => toast.error('Verifique os campos.'),
    });
  };

  const confirmDelete = () => {
    if (!deleteTarget) return;
    router.delete(`/essentials/document/${deleteTarget.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Removido.');
        setDeleteTarget(null);
      },
      onError: () => toast.error('Falha ao remover.'),
    });
  };

  const openShare = async (doc: Doc) => {
    setShareLoading(true);
    setShareState({ users: [], roles: [], shared_user_ids: [], shared_role_ids: [], document_id: doc.id });
    try {
      const res = await fetch(`/essentials/document-share/${doc.id}/edit`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      setShareState({
        users: data.users ?? [],
        roles: data.roles ?? [],
        shared_user_ids: data.shared_user_ids ?? [],
        shared_role_ids: data.shared_role_ids ?? [],
        document_id: data.document_id ?? doc.id,
      });
    } catch {
      toast.error('Falha ao carregar compartilhamentos.');
      setShareState(null);
    } finally {
      setShareLoading(false);
    }
  };

  const toggleShareUser = (id: number) => {
    setShareState((prev) =>
      prev
        ? {
            ...prev,
            shared_user_ids: prev.shared_user_ids.includes(id)
              ? prev.shared_user_ids.filter((u) => u !== id)
              : [...prev.shared_user_ids, id],
          }
        : prev
    );
  };

  const toggleShareRole = (id: number) => {
    setShareState((prev) =>
      prev
        ? {
            ...prev,
            shared_role_ids: prev.shared_role_ids.includes(id)
              ? prev.shared_role_ids.filter((r) => r !== id)
              : [...prev.shared_role_ids, id],
          }
        : prev
    );
  };

  const submitShare = async () => {
    if (!shareState) return;
    try {
      const res = await fetch('/essentials/document-share', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN':
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          document_id: shareState.document_id,
          user: shareState.shared_user_ids,
          role: shareState.shared_role_ids,
        }),
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      toast.success('Compartilhamentos atualizados.');
      setShareState(null);
    } catch {
      toast.error('Falha ao salvar compartilhamentos.');
    }
  };

  const openMemoView = async (memo: Doc) => {
    // Enriquece via endpoint show() (memo description já vem no listing, mas
    // padronizamos — também serve se mudar shape futuro).
    setMemoView(memo);
  };

  const rows = tab === 'documents' ? documents : memos;

  return (
    <>
      <div className="mx-auto max-w-6xl p-6 space-y-4">
        <header className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              {tab === 'memos' ? <Mail size={22} /> : <FileText size={22} />}
              {tab === 'memos' ? 'Memos' : 'Documentos'}
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              {tab === 'memos'
                ? 'Avisos e memorandos internos em texto.'
                : 'Arquivos compartilhados com a equipe.'}
            </p>
          </div>
          <div className="flex gap-2">
            {tab === 'documents' ? (
              <Button onClick={() => setUploadOpen(true)}>
                <FolderUp size={14} className="mr-1.5" /> Enviar arquivo
              </Button>
            ) : (
              <Button onClick={() => setMemoOpen(true)}>
                <Plus size={14} className="mr-1.5" /> Novo memo
              </Button>
            )}
          </div>
        </header>

        {/* Tabs */}
        <div className="border-b border-border flex gap-1">
          <TabButton active={tab === 'documents'} onClick={() => setTab('documents')} icon={<FileText size={14} />}>
            Arquivos <span className="ml-1 text-xs text-muted-foreground">({documents.length})</span>
          </TabButton>
          <TabButton active={tab === 'memos'} onClick={() => setTab('memos')} icon={<Mail size={14} />}>
            Memos <span className="ml-1 text-xs text-muted-foreground">({memos.length})</span>
          </TabButton>
        </div>

        <Card>
          <CardContent className="p-0">
            {rows.length === 0 ? (
              <div className="p-12 text-center text-sm text-muted-foreground">
                {tab === 'memos'
                  ? 'Nenhum memo criado ainda.'
                  : 'Nenhum arquivo enviado ainda.'}
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                    <tr>
                      <th className="text-left p-3 font-medium">{tab === 'memos' ? 'Título' : 'Arquivo'}</th>
                      <th className="text-left p-3 font-medium">Descrição</th>
                      <th className="text-left p-3 font-medium">Enviado em</th>
                      <th className="text-right p-3 font-medium">Ações</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {rows.map((d) => (
                      <tr key={d.id} className="hover:bg-accent/30">
                        <td className="p-3">
                          <div className="font-medium">{d.display_name}</div>
                          {!d.is_mine && d.shared_by && (
                            <div className="text-[10px] text-muted-foreground flex items-center gap-1">
                              <UsersIcon size={10} /> Compartilhado por {d.shared_by}
                            </div>
                          )}
                        </td>
                        <td className="p-3 text-xs text-muted-foreground max-w-md truncate">
                          {d.description ?? '—'}
                        </td>
                        <td className="p-3 text-xs">{d.created_at ?? '—'}</td>
                        <td className="p-3 text-right">
                          <div className="flex justify-end gap-1">
                            {tab === 'documents' ? (
                              <Button size="sm" variant="outline" asChild>
                                <a href={`/essentials/document/download/${d.id}`} download className="text-xs gap-1">
                                  <Download size={12} /> Baixar
                                </a>
                              </Button>
                            ) : (
                              <Button size="sm" variant="outline" onClick={() => openMemoView(d)} className="text-xs gap-1">
                                <Eye size={12} /> Ver
                              </Button>
                            )}
                            {d.is_mine && (
                              <>
                                <Button size="sm" variant="outline" onClick={() => openShare(d)} className="text-xs gap-1">
                                  <Share2 size={12} />
                                </Button>
                                <Button
                                  size="sm"
                                  variant="ghost"
                                  className="text-destructive hover:text-destructive"
                                  onClick={() => setDeleteTarget(d)}
                                >
                                  <Trash2 size={12} />
                                </Button>
                              </>
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
      </div>

      {/* Upload dialog */}
      <Dialog open={uploadOpen} onOpenChange={setUploadOpen}>
        <DialogContent>
          <form onSubmit={submitUpload}>
            <DialogHeader>
              <DialogTitle>Enviar arquivo</DialogTitle>
              <DialogDescription>
                Arquivos ficam disponíveis para você. Compartilhe via ícone <Share2 size={12} className="inline" /> após o upload.
              </DialogDescription>
            </DialogHeader>
            <div className="py-4 space-y-3">
              <div className="space-y-1">
                <Label htmlFor="document-file">Arquivo *</Label>
                <Input
                  id="document-file"
                  type="file"
                  onChange={(e) => uploadForm.setData('name', e.target.files?.[0] ?? null)}
                  required
                />
                {uploadForm.progress && (
                  <div className="h-1.5 bg-muted rounded overflow-hidden mt-1">
                    <div
                      className="h-full bg-primary transition-all"
                      style={{ width: `${uploadForm.progress.percentage ?? 0}%` }}
                    />
                  </div>
                )}
                {uploadForm.errors.name && <p className="text-xs text-destructive">{uploadForm.errors.name}</p>}
              </div>
              <div className="space-y-1">
                <Label htmlFor="upload-description">Descrição (opcional)</Label>
                <Textarea
                  id="upload-description"
                  rows={3}
                  value={uploadForm.data.description}
                  onChange={(e) => uploadForm.setData('description', e.target.value)}
                />
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setUploadOpen(false)}>Cancelar</Button>
              <Button type="submit" disabled={uploadForm.processing}>Enviar</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Memo dialog */}
      <Dialog open={memoOpen} onOpenChange={setMemoOpen}>
        <DialogContent>
          <form onSubmit={submitMemo}>
            <DialogHeader>
              <DialogTitle>Novo memo</DialogTitle>
              <DialogDescription>Aviso em texto compartilhado com a equipe.</DialogDescription>
            </DialogHeader>
            <div className="py-4 space-y-3">
              <div className="space-y-1">
                <Label htmlFor="memo-name">Título *</Label>
                <Input
                  id="memo-name"
                  value={memoForm.data.name}
                  onChange={(e) => memoForm.setData('name', e.target.value)}
                  required
                />
                {memoForm.errors.name && <p className="text-xs text-destructive">{memoForm.errors.name}</p>}
              </div>
              <div className="space-y-1">
                <Label htmlFor="memo-body">Corpo *</Label>
                <Textarea
                  id="memo-body"
                  rows={6}
                  value={memoForm.data.body}
                  onChange={(e) => memoForm.setData('body', e.target.value)}
                  required
                />
                {memoForm.errors.body && <p className="text-xs text-destructive">{memoForm.errors.body}</p>}
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setMemoOpen(false)}>Cancelar</Button>
              <Button type="submit" disabled={memoForm.processing}>Criar</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Memo view */}
      <Dialog open={memoView !== null} onOpenChange={(open) => !open && setMemoView(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{memoView?.display_name}</DialogTitle>
            <DialogDescription>
              {memoView?.created_at} {memoView?.shared_by ? `· por ${memoView.shared_by}` : ''}
            </DialogDescription>
          </DialogHeader>
          <div className="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap">
            {memoView?.description}
          </div>
        </DialogContent>
      </Dialog>

      {/* Share dialog */}
      <Dialog open={shareState !== null} onOpenChange={(open) => !open && setShareState(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Share2 size={16} /> Compartilhar
            </DialogTitle>
            <DialogDescription>
              Escolha usuários ou papéis que terão acesso ao arquivo/memo.
            </DialogDescription>
          </DialogHeader>
          {shareLoading ? (
            <div className="py-6 text-center text-sm text-muted-foreground">Carregando…</div>
          ) : shareState ? (
            <div className="py-3 space-y-4">
              <div>
                <Label className="mb-2 block">Usuários</Label>
                <div className="flex flex-wrap gap-1.5 max-h-40 overflow-y-auto p-2 border border-border rounded">
                  {shareState.users.map((u) => {
                    const selected = shareState.shared_user_ids.includes(u.id);
                    return (
                      <button
                        key={u.id}
                        type="button"
                        onClick={() => toggleShareUser(u.id)}
                        className={`px-2 py-0.5 rounded text-xs border transition ${
                          selected
                            ? 'bg-primary text-primary-foreground border-primary'
                            : 'bg-background hover:bg-accent border-border'
                        }`}
                      >
                        {u.label}
                      </button>
                    );
                  })}
                </div>
              </div>
              <div>
                <Label className="mb-2 block">Papéis (roles)</Label>
                <div className="flex flex-wrap gap-1.5 p-2 border border-border rounded">
                  {shareState.roles.length === 0 ? (
                    <span className="text-xs text-muted-foreground">Nenhum papel disponível.</span>
                  ) : (
                    shareState.roles.map((r) => {
                      const selected = shareState.shared_role_ids.includes(r.id);
                      return (
                        <button
                          key={r.id}
                          type="button"
                          onClick={() => toggleShareRole(r.id)}
                          className={`px-2 py-0.5 rounded text-xs border transition ${
                            selected
                              ? 'bg-primary text-primary-foreground border-primary'
                              : 'bg-background hover:bg-accent border-border'
                          }`}
                        >
                          {r.label}
                        </button>
                      );
                    })
                  )}
                </div>
              </div>
            </div>
          ) : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setShareState(null)}>
              Cancelar
            </Button>
            <Button type="button" onClick={submitShare} disabled={shareLoading}>
              Salvar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover {deleteTarget?.type === 'memos' ? 'memo' : 'arquivo'}?</AlertDialogTitle>
            <AlertDialogDescription>
              "{deleteTarget?.display_name}" será apagado junto com seus compartilhamentos.
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

DocumentsIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Documentos" breadcrumbItems={[{ label: 'Essentials' }, { label: 'Documentos' }]}>
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
