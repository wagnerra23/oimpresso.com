// @docvault
//   tela: /essentials/knowledge-base/create
//   module: Essentials
//   status: implementada
//   rules: R-ESSE-001
//   tests: Modules/Essentials/Tests/Feature/KnowledgeCreateTest

import AppShell from '@/Layouts/AppShell';
import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { toast } from 'sonner';
import { ArrowLeft, BookOpen, FileText, FolderOpen, Save } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
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

interface UserOption { id: number; label: string; }

interface Parent {
  id: number;
  title: string;
  kb_type: string;
}

interface Props {
  parent: Parent | null;
  users: UserOption[];
}

type FormData = {
  title: string;
  content: string;
  kb_type: 'knowledge_base' | 'section' | 'article';
  parent_id: number | null;
  share_with: string;
  user_ids: number[];
};

const nextTypeFor = (parentType: string | null): FormData['kb_type'] => {
  if (!parentType) return 'knowledge_base';
  if (parentType === 'knowledge_base') return 'section';
  return 'article';
};

const labelFor = (kbType: FormData['kb_type']): string =>
  kbType === 'knowledge_base' ? 'Livro' : kbType === 'section' ? 'Seção' : 'Artigo';

const iconFor = (kbType: FormData['kb_type']) => {
  if (kbType === 'knowledge_base') return <BookOpen size={16} />;
  if (kbType === 'section') return <FolderOpen size={16} />;
  return <FileText size={16} />;
};

export default function KnowledgeCreate({ parent, users }: Props) {
  const kbType = nextTypeFor(parent?.kb_type ?? null);

  const form = useForm<FormData>({
    title: '',
    content: '',
    kb_type: kbType,
    parent_id: parent?.id ?? null,
    share_with: 'public',
    user_ids: [],
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post('/essentials/knowledge-base', {
      onSuccess: () => toast.success(`${labelFor(kbType)} criado.`),
      onError: () => toast.error('Verifique os campos destacados.'),
    });
  };

  const toggleUser = (id: number) => {
    const has = form.data.user_ids.includes(id);
    form.setData('user_ids', has ? form.data.user_ids.filter((u) => u !== id) : [...form.data.user_ids, id]);
  };

  const backHref = parent
    ? `/essentials/knowledge-base/${parent.id}`
    : '/essentials/knowledge-base';

  return (
    <AppShell
      title={`Novo ${labelFor(kbType).toLowerCase()}`}
      breadcrumb={[
        { label: 'Essentials' },
        { label: 'Base de conhecimento', href: '/essentials/knowledge-base' },
        ...(parent ? [{ label: parent.title, href: `/essentials/knowledge-base/${parent.id}` }] : []),
        { label: `Novo ${labelFor(kbType).toLowerCase()}` },
      ]}
    >
      <div className="mx-auto max-w-3xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              {iconFor(kbType)} Novo {labelFor(kbType).toLowerCase()}
            </h1>
            {parent && (
              <p className="text-sm text-muted-foreground mt-1">
                Dentro de <strong>{parent.title}</strong>
              </p>
            )}
          </div>
          <Button variant="outline" size="sm" asChild>
            <Link href={backHref}><ArrowLeft size={14} className="mr-1.5" /> Voltar</Link>
          </Button>
        </header>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Conteúdo</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-4">
              <div className="space-y-1">
                <Label htmlFor="title">Título *</Label>
                <Input
                  id="title"
                  value={form.data.title}
                  onChange={(e) => form.setData('title', e.target.value)}
                  required
                  autoFocus
                />
                {form.errors.title && <p className="text-xs text-destructive">{form.errors.title}</p>}
              </div>

              <div className="space-y-1">
                <Label htmlFor="content">Conteúdo (HTML permitido)</Label>
                <Textarea
                  id="content"
                  rows={10}
                  value={form.data.content}
                  onChange={(e) => form.setData('content', e.target.value)}
                  placeholder="Texto do livro/seção/artigo…"
                />
                {form.errors.content && <p className="text-xs text-destructive">{form.errors.content}</p>}
              </div>

              {kbType === 'knowledge_base' && (
                <>
                  <div className="space-y-1">
                    <Label htmlFor="share_with">Compartilhar com</Label>
                    <Select
                      value={form.data.share_with}
                      onValueChange={(v) => form.setData('share_with', v)}
                    >
                      <SelectTrigger id="share_with">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="public">Público (todos do business)</SelectItem>
                        <SelectItem value="only_with">Apenas usuários selecionados</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  {form.data.share_with === 'only_with' && users.length > 0 && (
                    <div className="space-y-1">
                      <Label>Usuários com acesso</Label>
                      <div className="flex flex-wrap gap-2 p-2 border border-border rounded-md max-h-48 overflow-y-auto">
                        {users.map((u) => {
                          const selected = form.data.user_ids.includes(u.id);
                          return (
                            <button
                              key={u.id}
                              type="button"
                              onClick={() => toggleUser(u.id)}
                              className={`px-2.5 py-1 rounded text-xs border transition ${
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
                      <p className="text-xs text-muted-foreground">
                        {form.data.user_ids.length} usuário(s) selecionado(s).
                      </p>
                    </div>
                  )}
                </>
              )}

              <div className="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" asChild>
                  <Link href={backHref}>Cancelar</Link>
                </Button>
                <Button type="submit" disabled={form.processing} className="gap-1.5">
                  <Save size={14} /> Salvar
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
