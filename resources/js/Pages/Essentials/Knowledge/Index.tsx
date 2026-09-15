// @docvault
//   tela: /essentials/knowledge-base
//   module: Essentials
//   status: implementada
//   rules: R-ESSE-001
//   tests: Modules/Essentials/Tests/Feature/KnowledgeIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Skeleton } from '@/Components/ui/skeleton';
import {
  BookOpen,
  ChevronDown,
  ChevronRight,
  Edit,
  Eye,
  FileText,
  FolderOpen,
  Plus,
  Search,
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
import { Inline } from '@/Components/layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';

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
  // books vem via Inertia::defer — undefined no first render
  books?: Book[];
}

export default function KnowledgeIndex({ books }: Props) {
  const bookList = useMemo(() => books ?? [], [books]);
  const [openSections, setOpenSections] = useState<Record<number, boolean>>({});
  const [deleteTarget, setDeleteTarget] = useState<{ id: number; title: string } | null>(null);
  const [busca, setBusca] = useState('');
  const campoBusca = useRef<HTMLInputElement>(null);

  const toggleSection = (id: number) =>
    setOpenSections((prev) => ({ ...prev, [id]: !prev[id] }));

  // Busca do protótipo (`Buscar na base · /`, essenciais-extras.jsx:144). Client-side
  // porque a árvore inteira já chega no payload — nada de ida ao banco.
  // O `casa()` de lá compara título + conteúdo; aqui o conteúdo é HTML, então tiramos
  // as tags antes, senão buscar "li" casaria com todo <li> do texto.
  const termo = busca.trim().toLowerCase();

  // Filtra seção e artigo, como o protótipo. Diferença deliberada: lá as categorias
  // ficam TODAS visíveis (a árvore é um aside estreito); aqui cada livro é um CARD numa
  // grade — deixar 20 cards vazios na tela seria pior que não ter busca. Some o livro
  // que não casa e não tem filho casando.
  const livrosFiltrados = useMemo(() => {
    if (!termo) return bookList;
    const casa = (titulo: string, conteudo?: string | null) =>
      (titulo + ' ' + (conteudo ?? '').replace(/<[^>]*>/g, ' ')).toLowerCase().includes(termo);
    return bookList
      .map((book) => {
        const secoes = book.children
          .map((sec) => ({
            ...sec,
            children: sec.children.filter((a) => casa(a.title)),
          }))
          .filter((sec) => casa(sec.title, sec.content) || sec.children.length > 0);
        const livroCasa = casa(book.title, book.content);
        return livroCasa && secoes.length === 0 ? book : { ...book, children: secoes };
      })
      .filter((book) => casa(book.title, book.content) || book.children.length > 0);
  }, [bookList, termo]);

  // A busca só serve se o resultado estiver aberto: com termo, abre as seções que casaram.
  const secoesAbertas = (id: number) => (termo ? true : openSections[id] ?? false);

  // `/` foca a busca (atalho do protótipo). Não dispara com foco num campo.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const el = e.target as HTMLElement | null;
      if (!el || el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable) return;
      if (e.metaKey || e.ctrlKey || e.altKey) return;
      if (e.key === '/') { e.preventDefault(); campoBusca.current?.focus(); }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

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
            <h1 className="text-2xl font-semibold tracking-tight flex items-center gap-2">
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

        {/* ── busca (charter do intake: é a 1ª seção da tela) ── */}
        <Card>
          <CardContent className="py-3" data-contract="busca">
            <Inline gap={2} align="center" wrap>
              <div className="relative min-w-56 flex-1">
                <Search size={14} aria-hidden="true" className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
                <Input
                  ref={campoBusca}
                  value={busca}
                  onChange={(e) => setBusca(e.target.value)}
                  placeholder="Buscar na base"
                  aria-label="Buscar na base de conhecimento"
                  className="pl-8"
                />
              </div>
              <span className="ml-auto hidden items-center gap-2 text-xs text-muted-foreground lg:inline-flex">
                <kbd className="rounded border border-border px-1.5 py-0.5">/</kbd> buscar
              </span>
            </Inline>
          </CardContent>
        </Card>

        <Deferred data="books" fallback={<Skeleton className="h-64 w-full" />}>
        {termo && livrosFiltrados.length === 0 ? (
          <Card>
            <CardContent className="py-12 text-center" data-contract="vazio">
              <Search size={32} aria-hidden="true" className="mx-auto mb-2 opacity-50 text-muted-foreground" />
              <p className="text-sm font-medium">Nada encontrado na base</p>
              <p className="mt-1 text-sm text-muted-foreground">
                Nenhum livro, seção ou artigo com “{busca.trim()}”. Tente outra palavra.
              </p>
              <Button variant="outline" size="sm" className="mt-4" onClick={() => setBusca('')}>
                Limpar busca
              </Button>
            </CardContent>
          </Card>
        ) : bookList.length === 0 ? (
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
            {livrosFiltrados.map((book) => (
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
                        const isOpen = secoesAbertas(section.id);
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
        </Deferred>
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
