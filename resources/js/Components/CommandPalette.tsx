// @memcofre componente=CommandPalette modulo=ProjectMgmt
// PMG-002 (ADR 0100) — Cmd+K Search Global
//
// Wrapper de cmdk (via shadcn `command.tsx`) que faz fetch debounced em
// /project-mgmt/search?q= e renderiza grupos (Tasks/Epics/Cycles/Projects).
// Navegação keyboard nativa do cmdk: ↑↓ navegar, Enter abre URL, Esc fecha.
//
// Props:
//   open / onOpenChange — controlled state (AppShellV2 é dono do trigger Cmd+K)
//
// Endpoint backend: Modules/ProjectMgmt/Http/Controllers/SearchController.php

import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Folder, KanbanSquare, Loader2, Target, Calendar } from 'lucide-react';
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
  CommandShortcut,
} from '@/Components/ui/command';

interface TaskResult {
  task_id: string;
  identifier: string | null;
  display_id: string;
  title: string;
  status: string;
  priority: string;
  owner: string | null;
  module: string | null;
  project_key: string | null;
  url: string;
}

interface EpicResult {
  id: number;
  key: string;
  title: string;
  status: string;
  project_key: string | null;
  url: string;
}

interface CycleResult {
  id: number;
  key: string;
  name: string | null;
  status: string;
  project_key: string | null;
  url: string;
}

interface ProjectResult {
  id: number;
  key: string;
  name: string;
  status: string;
  url: string;
}

interface SearchResponse {
  query: string;
  results: {
    tasks: TaskResult[];
    epics: EpicResult[];
    cycles: CycleResult[];
    projects: ProjectResult[];
  };
  total: number;
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const EMPTY: SearchResponse = {
  query: '',
  results: { tasks: [], epics: [], cycles: [], projects: [] },
  total: 0,
};

const PRIORITY_DOT: Record<string, string> = {
  p0: 'bg-red-500',
  p1: 'bg-orange-500',
  p2: 'bg-yellow-500',
  p3: 'bg-blue-500',
};

export default function CommandPalette({ open, onOpenChange }: Props) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchResponse>(EMPTY);
  const [loading, setLoading] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  // Debounced fetch
  useEffect(() => {
    if (!open) return;
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (abortRef.current) abortRef.current.abort();

    if (query.trim().length < 2) {
      setResults(EMPTY);
      setLoading(false);
      return;
    }

    setLoading(true);
    debounceRef.current = setTimeout(() => {
      const ctrl = new AbortController();
      abortRef.current = ctrl;
      fetch(`/project-mgmt/search?q=${encodeURIComponent(query.trim())}`, {
        headers: { Accept: 'application/json' },
        signal: ctrl.signal,
      })
        .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
        .then((data: SearchResponse) => {
          setResults(data);
          setLoading(false);
        })
        .catch((err) => {
          if (err?.name === 'AbortError') return;
          setLoading(false);
          setResults(EMPTY);
        });
    }, 220);

    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [query, open]);

  // Reset on close
  useEffect(() => {
    if (!open) {
      setQuery('');
      setResults(EMPTY);
      setLoading(false);
    }
  }, [open]);

  const navigate = (url: string) => {
    onOpenChange(false);
    router.visit(url, { preserveScroll: true });
  };

  const { tasks, epics, cycles, projects } = results.results;
  const hasResults = results.total > 0;

  return (
    <CommandDialog
      open={open}
      onOpenChange={onOpenChange}
      title="Buscar"
      description="Cmd+K — busque tasks, epics, cycles ou projects"
    >
      <CommandInput
        placeholder="Buscar tasks, epics, cycles, projects… (Cmd+K)"
        value={query}
        onValueChange={setQuery}
      />
      <CommandList>
        {loading && (
          <div className="flex items-center justify-center py-6 text-sm text-muted-foreground">
            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
            buscando…
          </div>
        )}

        {!loading && query.trim().length < 2 && (
          <CommandEmpty>
            Digite ao menos 2 caracteres para buscar.
          </CommandEmpty>
        )}

        {!loading && query.trim().length >= 2 && !hasResults && (
          <CommandEmpty>Nenhum resultado para "{query}".</CommandEmpty>
        )}

        {!loading && tasks.length > 0 && (
          <CommandGroup heading={`Tasks (${tasks.length})`}>
            {tasks.map((t) => (
              <CommandItem
                key={`task-${t.task_id}`}
                value={`task ${t.task_id} ${t.display_id} ${t.title} ${t.owner ?? ''} ${t.module ?? ''}`}
                onSelect={() => navigate(t.url)}
              >
                <span className={`mr-1 inline-block h-2 w-2 rounded-full ${PRIORITY_DOT[t.priority] ?? 'bg-gray-300'}`} />
                <KanbanSquare className="mr-1" />
                <span className="font-mono text-xs text-muted-foreground">{t.display_id}</span>
                <span className="truncate">{t.title}</span>
                <CommandShortcut>{t.status}</CommandShortcut>
              </CommandItem>
            ))}
          </CommandGroup>
        )}

        {!loading && epics.length > 0 && (
          <>
            <CommandSeparator />
            <CommandGroup heading={`Epics (${epics.length})`}>
              {epics.map((e) => (
                <CommandItem
                  key={`epic-${e.id}`}
                  value={`epic ${e.key} ${e.title}`}
                  onSelect={() => navigate(e.url)}
                >
                  <Target />
                  <span className="font-mono text-xs text-muted-foreground">{e.key}</span>
                  <span className="truncate">{e.title}</span>
                  <CommandShortcut>{e.status}</CommandShortcut>
                </CommandItem>
              ))}
            </CommandGroup>
          </>
        )}

        {!loading && cycles.length > 0 && (
          <>
            <CommandSeparator />
            <CommandGroup heading={`Cycles (${cycles.length})`}>
              {cycles.map((c) => (
                <CommandItem
                  key={`cycle-${c.id}`}
                  value={`cycle ${c.key} ${c.name ?? ''}`}
                  onSelect={() => navigate(c.url)}
                >
                  <Calendar />
                  <span className="font-mono text-xs text-muted-foreground">{c.key}</span>
                  <span className="truncate">{c.name ?? '—'}</span>
                  <CommandShortcut>{c.status}</CommandShortcut>
                </CommandItem>
              ))}
            </CommandGroup>
          </>
        )}

        {!loading && projects.length > 0 && (
          <>
            <CommandSeparator />
            <CommandGroup heading={`Projects (${projects.length})`}>
              {projects.map((p) => (
                <CommandItem
                  key={`project-${p.id}`}
                  value={`project ${p.key} ${p.name}`}
                  onSelect={() => navigate(p.url)}
                >
                  <Folder />
                  <span className="font-mono text-xs text-muted-foreground">{p.key}</span>
                  <span className="truncate">{p.name}</span>
                  <CommandShortcut>{p.status}</CommandShortcut>
                </CommandItem>
              ))}
            </CommandGroup>
          </>
        )}
      </CommandList>
    </CommandDialog>
  );
}
