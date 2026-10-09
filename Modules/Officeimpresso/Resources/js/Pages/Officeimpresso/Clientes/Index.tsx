// Clientes OAuth — Officeimpresso (credenciais password-grant de cada Delphi / WR Comercial).
//
//   rota:     /officeimpresso/client (ClientController::index, atrás da flag useV2OfficeimpressoClientes;
//             flag OFF = Blade clients/index, rota de fuga até o cutover — RUNBOOK-clientes §F5)
//   padrão:   PT-01 Lista
//   charter:  ./Index.charter.md · casos: ./Index.casos.md
//   âncora:   prototipo-ui/cowork/Wagner/officeimpresso-page.jsx → ViewClientes() (rota oi-clientes)
//
// O secret nunca vem na lista (thread 05): só o bloco da criação o traz, uma vez.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import EmptyState from '@/Components/shared/EmptyState';

interface Cliente { id: number; name: string; tipo: 'password' | 'personal' | 'authz_code' }
interface Props {
  is_demo: boolean;
  credencial: { name: string; secret: string } | null;
  permissions: { pode_excluir: boolean; pode_regenerar: boolean };
  clientes?: Cliente[];
}

function ClientesIndex({ is_demo, credencial, permissions, clientes }: Props) {
  const [novo, setNovo] = useState(false);
  const form = useForm({ name: '' });
  const criar = (e: FormEvent) => {
    e.preventDefault();
    form.post('/officeimpresso/client', { onSuccess: () => { form.reset(); setNovo(false); } });
  };

  return (
    <div className="pb-8">
      <div data-contract="header">
        <PageHeader title="Clientes OAuth"
          subtitle="Credenciais password-grant · uma por instalação Delphi"
          actions={is_demo ? undefined : (
            <div className="flex gap-2">
              {permissions.pode_regenerar && (
                <Button asChild variant="ghost" size="sm">
                  <a href="/officeimpresso/regenerate"
                    onClick={(e) => { if (!confirm('Regenerar as chaves derruba todos os Delphi conectados. Continuar?')) e.preventDefault(); }}>
                    Regenerar chaves
                  </a>
                </Button>
              )}
              <Button size="sm" onClick={() => setNovo(true)}>Nova credencial</Button>
            </div>
          )} />
      </div>

      <div className="flex flex-col gap-4 px-6 pt-4">
        {is_demo ? (
          <EmptyState title="Recurso desabilitado em modo demo." description="Credenciais OAuth não são criadas no ambiente de demonstração." />
        ) : (
          <>
            {credencial && <Credencial credencial={credencial} />}
            {novo && (
              <form onSubmit={criar} className="flex flex-wrap items-end gap-2 rounded-md border p-3" data-contract="nova">
                <label className="flex min-w-64 flex-1 flex-col gap-1 text-sm">
                  Nome do cliente
                  <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required
                    placeholder="Ex: Desktop Loja 1" aria-label="Nome do cliente" />
                </label>
                <Button type="submit" size="sm" disabled={form.processing}>Criar</Button>
                <Button type="button" variant="ghost" size="sm" onClick={() => setNovo(false)}>Cancelar</Button>
              </form>
            )}
            <Deferred data="clientes" fallback={<Skeleton className="h-48 w-full" />}>
              <Lista clientes={clientes ?? []} podeExcluir={permissions.pode_excluir} />
            </Deferred>
          </>
        )}
      </div>
    </div>
  );
}

function Credencial({ credencial }: { credencial: { name: string; secret: string } }) {
  const [aberta, setAberta] = useState(true);
  const [copiado, setCopiado] = useState(false);
  if (!aberta) return null;
  return (
    <div className="rounded-md border border-warning p-3 text-sm" data-contract="credencial">
      <div className="flex items-center justify-between">
        <strong>Credencial criada: {credencial.name}</strong>
        <Button variant="ghost" size="sm" onClick={() => setAberta(false)}>Fechar</Button>
      </div>
      <p className="text-warning">Copie agora: o secret não será exibido de novo. Perdeu? Crie outro cliente e exclua este.</p>
      <p>Client ID: o número da linha "{credencial.name}" na lista abaixo.</p>
      <p className="flex items-center gap-2">
        Secret: <span className="font-mono">{credencial.secret}</span>
        <Button variant="ghost" size="sm" onClick={() => { void navigator.clipboard?.writeText(credencial.secret); setCopiado(true); }}>
          {copiado ? 'Copiado' : 'Copiar'}
        </Button>
      </p>
    </div>
  );
}

function Lista({ clientes, podeExcluir }: { clientes: Cliente[]; podeExcluir: boolean }) {
  if (clientes.length === 0) {
    return <EmptyState title="Nenhum cliente OAuth cadastrado." description='Clique em "Nova credencial" para gerar o acesso de um Delphi.' />;
  }
  const excluir = (c: Cliente) => {
    if (confirm(`Remover o cliente OAuth "${c.name}"? O Delphi que usa esta credencial para de autenticar.`)) {
      router.delete(`/officeimpresso/client/${c.id}`, { preserveScroll: true });
    }
  };
  return (
    <div data-contract="grade" className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead><tr className="border-b text-left text-xs text-muted-foreground">
          <th className="py-2">Credencial</th><th>Client ID</th><th>Tipo</th>{podeExcluir && <th />}
        </tr></thead>
        <tbody>
          {clientes.map((c) => (
            <tr key={c.id} className="border-b">
              <td className="py-2 font-semibold">{c.name}</td>
              <td className="font-mono text-xs">{c.id}</td>
              <td className="text-xs">{c.tipo}</td>
              {podeExcluir && (
                <td className="text-right">
                  <Button variant="ghost" size="sm" onClick={() => excluir(c)}>Excluir</Button>
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

ClientesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ClientesIndex;
