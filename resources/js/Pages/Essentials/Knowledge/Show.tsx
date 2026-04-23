// @docvault
//   tela: /essentials/knowledge-base/show
//   module: Essentials
//   status: implementada
//   rules: R-ESSE-001
//   tests: Modules/Essentials/Tests/Feature/KnowledgeShowTest

import AppShell from '@/Layouts/AppShell';
import { Link } from '@inertiajs/react';
import {
  ArrowLeft,
  BookOpen,
  Edit,
  FileText,
  FolderOpen,
  Plus,
  Users as UsersIcon,
} from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

interface Article { id: number; title: string; kb_type: string; }
interface Section { id: number; title: string; content: string | null; kb_type: string; children: Article[]; }

interface Book {
  id: number;
  title: string;
  content: string | null;
  kb_type: string;
  share_with: string | null;
  children: Section[];
}

interface Item {
  id: number;
  title: string;
  content: string | null;
  kb_type: string;
  share_with: string | null;
  shared_users: string[];
  created_by: number | null;
  parent_id: number | null;
}

interface Props {
  item: Item;
  book: Book;
  sectionId: number | null;
  articleId: number | null;
}

const typeLabel = (kbType: string) =>
  kbType === 'knowledge_base' ? 'Livro' : kbType === 'section' ? 'Seção' : 'Artigo';

const typeIcon = (kbType: string) => {
  if (kbType === 'knowledge_base') return <BookOpen size={14} />;
  if (kbType === 'section') return <FolderOpen size={14} />;
  return <FileText size={14} />;
};

export default function KnowledgeShow({ item, book, sectionId, articleId }: Props) {
  return (
    <AppShell
      title={item.title}
      breadcrumb={[
        { label: 'Essentials' },
        { label: 'Base de conhecimento', href: '/essentials/knowledge-base' },
        { label: item.title },
      ]}
    >
      <div className="mx-auto max-w-7xl p-6 grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-4">
        {/* Sidebar: navegação dentro do livro */}
        <aside className="space-y-2">
          <Link
            href={`/essentials/knowledge-base/${book.id}`}
            className={`block rounded px-3 py-2 text-sm font-medium flex items-center gap-1.5 ${
              item.id === book.id ? 'bg-primary/15 text-primary' : 'hover:bg-accent'
            }`}
          >
            <BookOpen size={14} /> {book.title}
          </Link>

          {book.children.length > 0 && (
            <ul className="ml-2 space-y-0.5 border-l border-border pl-3">
              {book.children.map((section) => (
                <li key={section.id}>
                  <Link
                    href={`/essentials/knowledge-base/${section.id}`}
                    className={`block rounded px-2 py-1 text-sm flex items-center gap-1.5 ${
                      sectionId === section.id ? 'bg-primary/15 text-primary font-medium' : 'hover:bg-accent'
                    }`}
                  >
                    <FolderOpen size={12} /> <span className="truncate">{section.title}</span>
                  </Link>
                  {section.children.length > 0 && (
                    <ul className="ml-2 space-y-0.5 mt-0.5 border-l border-border pl-3">
                      {section.children.map((article) => (
                        <li key={article.id}>
                          <Link
                            href={`/essentials/knowledge-base/${article.id}`}
                            className={`block rounded px-2 py-0.5 text-xs flex items-center gap-1.5 ${
                              articleId === article.id
                                ? 'bg-primary/15 text-primary font-medium'
                                : 'text-muted-foreground hover:text-foreground'
                            }`}
                          >
                            <FileText size={10} /> <span className="truncate">{article.title}</span>
                          </Link>
                        </li>
                      ))}
                    </ul>
                  )}
                </li>
              ))}
            </ul>
          )}
        </aside>

        {/* Conteúdo */}
        <div className="space-y-4 min-w-0">
          <header className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
              <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                {typeIcon(item.kb_type)}
                <span className="truncate">{item.title}</span>
                <Badge variant="secondary" className="text-[10px]">{typeLabel(item.kb_type)}</Badge>
              </h1>
              {item.kb_type === 'knowledge_base' && item.share_with && (
                <p className="text-xs text-muted-foreground mt-1 flex items-center gap-1">
                  <UsersIcon size={10} />
                  Compartilhado: <strong>{item.share_with === 'public' ? 'Público' : 'Apenas selecionados'}</strong>
                  {item.share_with === 'only_with' && item.shared_users.length > 0 && (
                    <span>({item.shared_users.join(', ')})</span>
                  )}
                </p>
              )}
            </div>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" asChild>
                <Link href={`/essentials/knowledge-base/${item.id}/edit`}>
                  <Edit size={14} className="mr-1.5" /> Editar
                </Link>
              </Button>
              {item.kb_type !== 'article' && (
                <Button size="sm" asChild>
                  <Link href={`/essentials/knowledge-base/create?parent=${item.id}`}>
                    <Plus size={14} className="mr-1.5" /> Adicionar {item.kb_type === 'knowledge_base' ? 'seção' : 'artigo'}
                  </Link>
                </Button>
              )}
              <Button variant="outline" size="sm" asChild>
                <Link href="/essentials/knowledge-base">
                  <ArrowLeft size={14} className="mr-1.5" /> Voltar
                </Link>
              </Button>
            </div>
          </header>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Conteúdo</CardTitle>
            </CardHeader>
            <CardContent>
              {item.content ? (
                <div
                  className="prose prose-sm dark:prose-invert max-w-none"
                  dangerouslySetInnerHTML={{ __html: item.content }}
                />
              ) : (
                <p className="text-sm text-muted-foreground italic">
                  (Sem conteúdo — clique em Editar para adicionar.)
                </p>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}
