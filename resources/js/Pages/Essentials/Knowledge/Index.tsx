// @docvault
//   tela: /essentials/knowledge-base
//   module: Essentials
//   status: implementada
//   rules: R-ESSE-001
//   tests: Modules/Essentials/Tests/Feature/KnowledgeIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import {
  BookOpen,
  ChevronDown,
  ChevronRight,
  Edit,
  Eye,
  FileText,
  FolderOpen,
  Plus,
  Trash2,
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
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

interface Article {
  id: number;
  title: string;
  kb_type: string;
}

interface Section {
  id: number;
  title: string;
  content: string | null;
  kb_type: string;
  children: Article[];
}

interface Book {
  id: number;
  title: string;
  content: string | null;
  kb_type: string;
  share_with: string | null;
  children: Section[];
}

interface Props {
  books: Book[];
}

export default function KnowledgeIndex({ books }: Props) {
  const [openSections, setOpenSections] = useState<Record<number, boolean>>({});
  const [deleteTarget, setDeleteTarget] = useState<{ id: number; title: string } | null>(null);

  const toggleSection = (id: number) =>
    setOpenSections((prev) => ({ ...prev, [id]: !prev[id] }));

  const confirmDelete = () => {
    if (!deleteTarget) return;
    router.delete(`/essentials/knowledge-base/${deleteTarget.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Item removido.');
        setDeleteTarget(null);
      },
      onError: () => toast.error('Falha ao remover.'),
    });
  };

  return (
    <>
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <BookOpen size={22} /> Base de conhecimento
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Organize manuais, procedimentos e artigos em livros → seções → artigos.
            </p>
          </div>
          <Button asChild>
            <Link href="/essentials/knowledge-base/create">
              <Plus size={14} className="mr-1.5" /> Novo livro
            </Link>
          </Button>
        </header>

        {books.length === 0 ? (
          <Card>
            <CardContent className="py-12 text-center">
              <BookOpen size={32} className="mx-auto mb-2 opacity-50 text-muted-foreground" />
              <p className="text-sm text-muted-foreground mb-4">
                Nenhum livro cadastrado ainda.
              </p>
              <Button asChild>
                <Link href="/essentials/knowledge-base/create">
                  <Plus size={14} className="mr-1.5" /> Criar primeiro livro
                </Link>
              </Button>
            </CardContent>
          </Card>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {books.map((book) => (
              <Card key={book.id} className="flex flex-col">
                <CardHeader className="pb-3">
                  <CardTitle className="text-base flex items-center justify-between gap-2">
                    <span className="truncate flex items-center gap-1.5">
                      <BookOpen size={16} /> {book.title}
                    </span>
                    <div className="flex-shrink-0 flex gap-1">
                      <Button size="sm" variant="ghost" className="h-7 w-7 p-0" asChild>
                        <Link href={`/essentials/knowledge-base/${book.id}`} title="Ver">
                          <Eye size={14} />
                        </Link>
                      </Button>
                      <Button size="sm" variant="ghost" className="h-7 w-7 p-0" asChild>
                        <Link href={`/essentials/knowledge-base/${book.id}/edit`} title="Editar">
                          <Edit size={14} />
                        </Link>
                      </Button>
                      <Button size="sm" variant="ghost" className="h-7 w-7 p-0 text-destructive" onClick={() => setDeleteTarget({ id: book.id, title: book.title })} title="Remover">
                        <Trash2 size={14} />
                      </Button>
                      <Button size="sm" variant="ghost" className="h-7 w-7 p-0" asChild>
                        <Link href={`/essentials/knowledge-base/create?parent=${book.id}`} title="Adicionar seção">
                          <Plus size={14} />
                        </Link>
                      </Button>
                    </div>
                  </CardTitle>
                </CardHeader>
                <CardContent className="text-sm flex-1">
                  {book.content && (
                    <div
                      className="text-muted-foreground text-xs mb-3 line-clamp-3"
                      dangerouslySetInnerHTML={{ __html: book.content }}
                    />
                  )}
                  {book.children.length === 0 ? (
                    <p className="text-xs text-muted-foreground italic">Nenhuma seção.</p>
                  ) : (
                    <ul className="space-y-1">
                      {book.children.map((section) => {
                        const isOpen = openSections[section.id] ?? false;
                        return (
                          <li key={section.id} className="border border-border rounded">
                            <div className="flex items-center justify-between gap-1 p-2">
                              <button
                                type="button"
                                onClick={() => toggleSection(section.id)}
                                className="flex-1 flex items-center gap-1 text-left hover:text-primary transition"
                              >
                                {isOpen ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
                                <FolderOpen size={14} className="text-muted-foreground" />
                                <span className="text-sm truncate">{section.title}</span>
                              </button>
                              <div className="flex-shrink-0 flex gap-0.5">
                                <Link href={`/essentials/knowledge-base/${section.id}`} className="text-muted-foreground hover:text-primary p-1" title="Ver">
                                  <Eye size={11} />
                                </Link>
                                <Link href={`/essentials/knowledge-base/${section.id}/edit`} className="text-muted-foreground hover:text-primary p-1" title="Editar">
                                  <Edit size={11} />
                                </Link>
                                <button type="button" onClick={() => setDeleteTarget({ id: section.id, title: section.title })} className="text-muted-foreground hover:text-destructive p-1" title="Remover">
                                  <Trash2 size={11} />
                                </button>
                                <Link href={`/essentials/knowledge-base/create?parent=${section.id}`} className="text-muted-foreground hover:text-primary p-1" title="Adicionar artigo">
                                  <Plus size={11} />
                                </Link>
                              </div>
                            </div>
                            {isOpen && section.children.length > 0 && (
                              <ul className="ml-4 mb-2 space-y-0.5">
                                {section.children.map((article) => (
                                  <li key={article.id} className="flex items-center gap-1 px-2 py-1 hover:bg-accent/30 rounded">
                                    <FileText size={11} className="text-muted-foreground" />
                                    <Link href={`/essentials/knowledge-base/${article.id}`} className="text-xs flex-1 hover:text-primary truncate">
                                      {article.title}
                                    </Link>
                                    <Link href={`/essentials/knowledge-base/${article.id}/edit`} className="text-muted-foreground hover:text-primary p-0.5">
                                      <Edit size={10} />
                                    </Link>
                                    <button type="button" onClick={() => setDeleteTarget({ id: article.id, title: article.title })} className="text-muted-foreground hover:text-destructive p-0.5">
                                      <Trash2 size={10} />
                                    </button>
                                  </li>
                                ))}
                              </ul>
                            )}
                          </li>
                        );
                      })}
                    </ul>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover item?</AlertDialogTitle>
            <AlertDialogDescription>
              "{deleteTarget?.title}" será removido junto com seus filhos (seções e artigos). Ação não pode ser desfeita.
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

KnowledgeIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Base de conhecimento" breadcrumbItems={[{ label: 'Essentials' }, { label: 'Base de conhecimento' }]}>
    {page}
  </AppShellV2>
);
