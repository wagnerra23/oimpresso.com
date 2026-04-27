// @memcofre
//   tela: /docs/memoria
//   module: Cofre de Memórias
//   status: implementada
//   adrs: 0007
//   tests: Modules/MemCofre/Tests/Feature/MemoriaTest

import AppShellV2 from '@/Layouts/AppShellV2';
import SimpleMarkdown from '@/Components/shared/SimpleMarkdown';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import {
  ArrowLeft,
  Bot,
  ChevronDown,
  ChevronRight,
  FileText,
  Folder,
  FolderOpen,
  Home,
  Search,
  Sparkles,
  User,
} from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';

interface TreeFile {
  type: 'file';
  name: string;
  key: string;
  size: number;
  mtime: string;
  preview: string;
  meta?: Record<string, string>;
}

interface TreeDir {
  type: 'dir';
  name: string;
  path: string;
  exists: boolean;
  children: Array<TreeFile | TreeDir>;
}

interface Selected {
  root: string;
  relative: string;
  absolute: string;
  content: string;
  size: number;
  mtime: string;
  meta: Record<string, string>;
}

interface Props {
  roots: {
    primer: TreeDir;
    project: TreeDir;
    claude: TreeDir;
  };
  stats: {
    primer: number;
    project: number;
    claude: number;
  };
  selected: Selected | null;
  paths: {
    project_dir: string;
    claude_dir: string;
  };
}

function stripFrontmatter(content: string): string {
  return content.replace(/^---\s*\n[\s\S]*?\n---\s*\n/, '');
}

export default function Memoria({ roots, stats, selected: initialSelected, paths }: Props) {
  const [activeRoot, setActiveRoot] = useState<'primer' | 'project' | 'claude'>(initialSelected?.root as any || 'project');
  const [selected, setSelected] = useState<Selected | null>(initialSelected);
  const [search, setSearch] = useState('');
  const [expanded, setExpanded] = useState<Set<string>>(new Set(['root']));
  const [loading, setLoading] = useState(false);

  const openFile = async (key: string) => {
    setLoading(true);
    try {
      const res = await fetch(`/docs/memoria/file?key=${encodeURIComponent(key)}`);
      if (res.ok) {
        const data: Selected = await res.json();
        setSelected(data);
        const url = new URL(window.location.href);
        url.searchParams.set('key', key);
        window.history.replaceState(null, '', url.toString());
      }
    } finally {
      setLoading(false);
    }
  };

  const toggleExpand = (path: string) => {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(path)) next.delete(path);
      else next.add(path);
      return next;
    });
  };

  const rootIcon = {
    primer: <Home size={14} className="text-amber-500" />,
    project: <Folder size={14} className="text-sky-500" />,
    claude: <Bot size={14} className="text-emerald-500" />,
  };

  const rootLabel = {
    primer: 'Primer',
    project: 'Projeto',
    claude: 'Claude',
  };

  const currentTree = roots[activeRoot];
  const filterTerm = search.trim().toLowerCase();

  return (
    <>
      <Head title="Cofre de Memórias — Memória" />
      <div className="mx-auto max-w-7xl p-6">
        <header className="flex items-start justify-between gap-3 mb-4">
          <div className="min-w-0">
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <Sparkles size={22} /> Memória unificada
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Primer do projeto + memória versionada + memória persistente do Claude — tudo num lugar, read-only.
            </p>
          </div>
          <Button variant="outline" size="sm" asChild>
            <Link href="/memcofre">
              <ArrowLeft size={14} className="mr-1.5" /> Voltar
            </Link>
          </Button>
        </header>

        {/* Stats / seletor de root */}
        <div className="grid grid-cols-3 gap-3 mb-4">
          {(['primer', 'project', 'claude'] as const).map((r) => {
            const active = activeRoot === r;
            const exists = roots[r]?.exists !== false;
            return (
              <button
                key={r}
                onClick={() => {
                  setActiveRoot(r);
                  setExpanded(new Set(['root']));
                }}
                disabled={!exists}
                className={`p-3 rounded border-2 text-left transition ${
                  active ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'
                } ${!exists ? 'opacity-50 cursor-not-allowed' : ''}`}
              >
                <div className="flex items-center gap-2 text-xs text-muted-foreground uppercase tracking-wide">
                  {rootIcon[r]} {rootLabel[r]}
                </div>
                <div className="text-2xl font-bold mt-1">
                  {stats[r]}
                  <span className="text-xs font-normal text-muted-foreground ml-1">arquivos</span>
                </div>
                {!exists && <div className="text-[10px] text-destructive mt-1">pasta não encontrada</div>}
              </button>
            );
          })}
        </div>

        <div className="grid md:grid-cols-[320px_1fr] gap-3">
          {/* Árvore */}
          <Card>
            <CardContent className="p-0">
              <div className="p-2 border-b border-border relative">
                <Search size={13} className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" />
                <input
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Filtrar..."
                  className="w-full pl-7 pr-2 py-1.5 text-sm border border-border rounded bg-background"
                />
              </div>
              <div className="text-[10px] text-muted-foreground px-3 py-1.5 border-b border-border font-mono truncate">
                {activeRoot === 'project' ? paths.project_dir
                  : activeRoot === 'claude' ? paths.claude_dir
                  : '—'}
              </div>
              <div className="max-h-[70vh] overflow-y-auto">
                <TreeNode
                  node={currentTree}
                  path="root"
                  expanded={expanded}
                  onToggle={toggleExpand}
                  onOpen={openFile}
                  selectedKey={selected?.root === activeRoot ? `${selected.root}::${selected.relative}` : null}
                  filter={filterTerm}
                  depth={0}
                />
              </div>
            </CardContent>
          </Card>

          {/* Preview pane */}
          <Card>
            <CardContent>
              {loading && (
                <div className="text-sm text-muted-foreground flex items-center gap-2 py-12 justify-center">
                  <span className="animate-pulse">●</span>
                  <span>Carregando...</span>
                </div>
              )}
              {!loading && !selected && (
                <div className="text-center py-12 text-sm text-muted-foreground">
                  <FileText size={40} className="mx-auto mb-2 text-muted-foreground/50" />
                  <p>Selecione um arquivo na árvore à esquerda.</p>
                </div>
              )}
              {!loading && selected && (
                <>
                  <div className="flex items-center justify-between gap-2 pb-3 mb-3 border-b border-border flex-wrap">
                    <div className="min-w-0 flex-1">
                      <code className="text-xs bg-muted px-2 py-0.5 rounded">{selected.relative}</code>
                      <div className="text-[10px] text-muted-foreground font-mono mt-1 truncate">
                        {selected.absolute}
                      </div>
                    </div>
                    <div className="flex gap-2 items-center">
                      <Badge variant="outline" className="text-[10px]">
                        {(selected.size / 1024).toFixed(1)} KB
                      </Badge>
                      <Badge variant="outline" className="text-[10px]">
                        {selected.mtime}
                      </Badge>
                    </div>
                  </div>
                  {Object.keys(selected.meta || {}).length > 0 && (
                    <div className="bg-muted/30 rounded p-3 mb-3 grid grid-cols-2 gap-2">
                      {Object.entries(selected.meta).map(([k, v]) => (
                        <div key={k} className="text-xs">
                          <span className="text-muted-foreground">{k}:</span>{' '}
                          <span className="font-mono">{v}</span>
                        </div>
                      ))}
                    </div>
                  )}
                  <div className="max-h-[65vh] overflow-y-auto">
                    {selected.relative.endsWith('.md') ? (
                      <SimpleMarkdown source={stripFrontmatter(selected.content)} />
                    ) : (
                      <pre className="text-xs overflow-x-auto whitespace-pre-wrap font-mono">
                        {selected.content}
                      </pre>
                    )}
                  </div>
                </>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}

Memoria.layout = (page: ReactNode) => (
  <AppShellV2 title="Memória · Cofre" breadcrumbItems={[
    { label: 'Cofre de Memórias', href: '/memcofre' },
    { label: 'Memória' },
  ]}>
    {page}
  </AppShellV2>
);

interface TreeNodeProps {
  node: TreeFile | TreeDir;
  path: string;
  expanded: Set<string>;
  onToggle: (path: string) => void;
  onOpen: (key: string) => void;
  selectedKey: string | null;
  filter: string;
  depth: number;
}

function TreeNode({ node, path, expanded, onToggle, onOpen, selectedKey, filter, depth }: TreeNodeProps) {
  if (node.type === 'file') {
    const matches = !filter || node.name.toLowerCase().includes(filter) || node.preview.toLowerCase().includes(filter);
    if (!matches) return null;
    const isSelected = selectedKey === node.key;
    return (
      <button
        onClick={() => onOpen(node.key)}
        className={`w-full text-left flex items-start gap-2 px-2 py-1.5 hover:bg-accent/30 ${isSelected ? 'bg-accent/50' : ''}`}
        style={{ paddingLeft: `${depth * 12 + 8}px` }}
      >
        <FileText size={12} className="mt-0.5 flex-shrink-0 text-muted-foreground" />
        <span className="text-xs truncate">{node.name}</span>
      </button>
    );
  }

  // dir
  const isOpen = expanded.has(path);
  const hasFilterMatch = (n: TreeFile | TreeDir): boolean => {
    if (!filter) return true;
    if (n.type === 'file') return n.name.toLowerCase().includes(filter) || n.preview.toLowerCase().includes(filter);
    return n.children.some(hasFilterMatch);
  };
  if (!hasFilterMatch(node)) return null;

  // Auto-abre quando tem filtro ativo
  const effectiveOpen = filter ? true : isOpen;

  return (
    <div>
      <button
        onClick={() => onToggle(path)}
        className="w-full text-left flex items-center gap-1 px-2 py-1.5 hover:bg-accent/30"
        style={{ paddingLeft: `${depth * 12 + 4}px` }}
      >
        {effectiveOpen ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
        {effectiveOpen ? <FolderOpen size={12} className="text-amber-500" /> : <Folder size={12} className="text-amber-500" />}
        <span className="text-xs font-medium">{node.name}</span>
        <span className="text-[10px] text-muted-foreground ml-auto pr-1">
          {node.children.length}
        </span>
      </button>
      {effectiveOpen && (
        <div>
          {node.children.map((c, i) => (
            <TreeNode
              key={`${path}/${i}`}
              node={c}
              path={`${path}/${c.type === 'file' ? (c as TreeFile).key : c.name}`}
              expanded={expanded}
              onToggle={onToggle}
              onOpen={onOpen}
              selectedKey={selectedKey}
              filter={filter}
              depth={depth + 1}
            />
          ))}
        </div>
      )}
    </div>
  );
}
