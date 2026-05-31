// @memcofre
//   tela: /superadmin/usuarios
//   module: Superadmin
//   stories: USUARIO-360 (Wagner — não pular galho em galho pra rastrear acesso)
//   permissao: superadmin
//
// Lista de busca pra entrar no Usuário 360°.
// Charter: ./Index.charter.md (draft)

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { ChevronLeft, ChevronRight, Search, X } from 'lucide-react';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';

interface UserRow {
  id: number;
  username: string;
  email: string;
  nome: string;
  business_id: number | null;
  status: string;
  user_type: string;
}

interface Props {
  users: UserRow[];
  filters: { q: string };
}

const PAGE_SIZE = 10;

function Usuario360Index({ users, filters }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const isFirstRun = useRef(true);

  // Busca com debounce 300ms + partial reload (only: ['users','filters']) — sem submit.
  useEffect(() => {
    if (isFirstRun.current) {
      isFirstRun.current = false;
      return;
    }
    const handle = setTimeout(() => {
      if (q.trim() === (filters.q ?? '').trim()) return;
      router.get(
        '/superadmin/usuarios',
        { q: q.trim() },
        {
          only: ['users', 'filters'],
          preserveState: true,
          preserveScroll: true,
          replace: true,
          onStart: () => setLoading(true),
          onFinish: () => setLoading(false),
        },
      );
    }, 300);
    return () => clearTimeout(handle);
  }, [q, filters.q]);

  // Resultados novos => volta pra primeira página.
  useEffect(() => {
    setPage(1);
  }, [users]);

  const hasSearched = (filters.q ?? '').trim() !== '';
  const totalPages = Math.max(1, Math.ceil(users.length / PAGE_SIZE));
  const pageItems = useMemo(
    () => users.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE),
    [users, page],
  );
  const rangeStart = users.length === 0 ? 0 : (page - 1) * PAGE_SIZE + 1;
  const rangeEnd = Math.min(page * PAGE_SIZE, users.length);

  return (
    <>
      <PageHeader
        icon="users"
        title="Usuário 360°"
        description="Vista única de tudo sobre um usuário — roles, permissions, tokens, sessions, auditoria."
      />

      <div className="mt-4 space-y-4">
        <div className="relative max-w-md">
          <Search
            className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden
          />
          <Input
            type="search"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Buscar por nome, email ou username…"
            className="pl-9 pr-9"
            aria-label="Buscar usuário"
          />
          {q && (
            <button
              type="button"
              onClick={() => setQ('')}
              aria-label="Limpar busca"
              className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground hover:text-foreground"
            >
              <X className="h-4 w-4" />
            </button>
          )}
        </div>

        {loading ? (
          <div className="space-y-2" aria-busy="true">
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className="h-12 w-full" />
            ))}
          </div>
        ) : !hasSearched && users.length === 0 ? (
          <EmptyState
            icon="search"
            title="Comece uma busca"
            description="Digite um nome, email ou username para localizar o usuário."
          />
        ) : users.length === 0 ? (
          <EmptyState
            icon="search-x"
            variant="search"
            title="Nenhum usuário encontrado"
            description={`Nada corresponde a "${filters.q}". Tente outro termo.`}
          />
        ) : (
          <>
            <Card className="py-0">
              <CardContent className="overflow-x-auto px-0">
                <table className="w-full text-sm">
                  <thead className="border-b text-left text-xs uppercase text-muted-foreground">
                    <tr>
                      <th className="px-4 py-2">ID</th>
                      <th className="px-4 py-2">Nome</th>
                      <th className="px-4 py-2">Email</th>
                      <th className="px-4 py-2">Username</th>
                      <th className="px-4 py-2">Business</th>
                      <th className="px-4 py-2">Status</th>
                      <th className="px-4 py-2">Tipo</th>
                      <th className="px-4 py-2" />
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {pageItems.map((u) => (
                      <tr key={u.id} className="hover:bg-muted/40">
                        <td className="px-4 py-2 text-muted-foreground">{u.id}</td>
                        <td className="px-4 py-2 font-medium text-foreground">{u.nome}</td>
                        <td className="px-4 py-2 text-muted-foreground">{u.email}</td>
                        <td className="px-4 py-2 text-muted-foreground">{u.username}</td>
                        <td className="px-4 py-2 text-muted-foreground">{u.business_id ?? '—'}</td>
                        <td className="px-4 py-2">
                          <Badge variant={u.status === 'active' ? 'default' : 'destructive'}>
                            {u.status}
                          </Badge>
                        </td>
                        <td className="px-4 py-2 text-muted-foreground">{u.user_type}</td>
                        <td className="px-4 py-2 text-right">
                          <Button asChild variant="link" size="sm">
                            <Link href={`/superadmin/usuarios/${u.id}/360`}>Ver 360° →</Link>
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </CardContent>
            </Card>

            <div className="flex items-center justify-between">
              <p className="text-sm text-muted-foreground tabular-nums">
                Mostrando {rangeStart}–{rangeEnd} de {users.length}
              </p>
              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  <ChevronLeft className="h-4 w-4" />
                  Anterior
                </Button>
                <span className="text-sm text-muted-foreground tabular-nums">
                  {page} / {totalPages}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page >= totalPages}
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                >
                  Próxima
                  <ChevronRight className="h-4 w-4" />
                </Button>
              </div>
            </div>
          </>
        )}
      </div>
    </>
  );
}

Usuario360Index.layout = (page: ReactNode) => (
  <AppShellV2 title="Usuário 360°">{page}</AppShellV2>
);

export default Usuario360Index;
