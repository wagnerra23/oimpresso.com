// @memcofre
//   tela: /superadmin/usuarios
//   module: Superadmin
//   stories: USUARIO-360 (Wagner — não pular galho em galho pra rastrear acesso)
//   permissao: superadmin
//
// Lista de busca pra entrar no Usuário 360°.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import PageHeader from '@/Components/shared/PageHeader';

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

function Usuario360Index({ users, filters }: Props) {
  const [q, setQ] = useState(filters.q ?? '');

  function onSearch(e: React.FormEvent) {
    e.preventDefault();
    router.get('/superadmin/usuarios', { q }, { preserveState: true, preserveScroll: true });
  }

  return (
    <>
      <PageHeader
        title="Usuário 360°"
        description="Vista única de tudo sobre um usuário — roles, permissions, tokens, sessions, auditoria."
      />

      <Card className="mb-4">
        <CardContent className="pt-6">
          <form onSubmit={onSearch} className="flex gap-2">
            <Input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar por nome, email ou username…"
              className="max-w-md"
            />
            <button
              type="submit"
              className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >
              Buscar
            </button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{users.length} usuário(s)</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="border-b text-left text-xs uppercase text-muted-foreground">
                <tr>
                  <th className="px-2 py-2">ID</th>
                  <th className="px-2 py-2">Nome</th>
                  <th className="px-2 py-2">Email</th>
                  <th className="px-2 py-2">Username</th>
                  <th className="px-2 py-2">Business</th>
                  <th className="px-2 py-2">Status</th>
                  <th className="px-2 py-2">Tipo</th>
                  <th className="px-2 py-2"></th>
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.id} className="border-b hover:bg-muted/40">
                    <td className="px-2 py-2 text-muted-foreground">{u.id}</td>
                    <td className="px-2 py-2 font-medium">{u.nome}</td>
                    <td className="px-2 py-2">{u.email}</td>
                    <td className="px-2 py-2">{u.username}</td>
                    <td className="px-2 py-2">{u.business_id ?? '—'}</td>
                    <td className="px-2 py-2">
                      <Badge variant={u.status === 'active' ? 'default' : 'destructive'}>
                        {u.status}
                      </Badge>
                    </td>
                    <td className="px-2 py-2 text-muted-foreground">{u.user_type}</td>
                    <td className="px-2 py-2">
                      <Link
                        href={`/superadmin/usuarios/${u.id}/360`}
                        className="text-sm font-medium text-primary hover:underline"
                      >
                        Ver 360° →
                      </Link>
                    </td>
                  </tr>
                ))}
                {users.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-2 py-8 text-center text-muted-foreground">
                      Nenhum usuário encontrado.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </>
  );
}

Usuario360Index.layout = (page: ReactNode) => (
  <AppShellV2 title="Usuário 360°">{page}</AppShellV2>
);

export default Usuario360Index;
