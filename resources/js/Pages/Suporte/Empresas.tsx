// Modo Suporte (ADR 0305) — lista read-only das empresas-cliente acessíveis (exceto a
// operadora). PT-01 Lista, variante read-only lean (Header + Tabela + EmptyState; sem
// BulkBar/Drawer/sub-tabs). Cópia do mockup aprovado (memory/requisitos/Suporte/mockup).
// Dados vêm da resolução central (SupportAccessService) — a operadora nunca chega aqui.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Inline } from '@/Components/layout';

interface Empresa {
  id: number;
  name: string;
}

interface Props {
  empresas: Empresa[];
}

export default function Empresas({ empresas }: Props) {
  const [q, setQ] = useState('');

  const termo = q.trim().toLowerCase();
  const filtradas = termo
    ? empresas.filter((e) => e.name.toLowerCase().includes(termo) || String(e.id).includes(termo))
    : empresas;

  return (
    <AppShellV2 title="Suporte">
      <Head title="Suporte · empresas" />

      <div className="p-6 max-w-5xl mx-auto space-y-5">
        <Inline justify="between" align="start" gap={4} wrap>
          <div>
            <h1 className="text-lg font-semibold text-[color:var(--text)]">Suporte · empresas</h1>
            <p className="mt-0.5 text-sm text-[color:var(--text-mute)]">
              Empresas-cliente que você pode atender — a empresa operadora não aparece.
            </p>
          </div>
          <Input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Buscar empresa…"
            aria-label="Buscar empresa"
            className="w-full sm:w-64"
          />
        </Inline>

        <div className="overflow-hidden rounded-lg border border-[color:var(--border)]">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[color:var(--border)] text-left text-xs uppercase tracking-wide text-[color:var(--text-mute)]">
                <th className="px-4 py-3 font-semibold">Empresa</th>
                <th className="px-4 py-3 font-semibold">ID</th>
                <th className="px-4 py-3 text-right font-semibold">Ação</th>
              </tr>
            </thead>
            <tbody>
              {filtradas.map((e) => (
                <tr
                  key={e.id}
                  className="border-b border-[color:var(--border)] last:border-0 hover:bg-[color:var(--panel-2)]"
                >
                  <td className="px-4 py-3 text-[color:var(--text)]">{e.name}</td>
                  <td className="px-4 py-3 tabular-nums text-[color:var(--text-mute)]">#{e.id}</td>
                  <td className="px-4 py-3 text-right">
                    <Button size="sm" onClick={() => router.visit(`/suporte/empresas/${e.id}`)}>
                      Entrar (suporte)
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {filtradas.length === 0 && (
            <div className="py-12 text-center text-sm text-[color:var(--text-mute)]">
              {empresas.length === 0
                ? 'Nenhuma empresa-cliente acessível.'
                : `Nada para “${q}”.`}
            </div>
          )}
        </div>
      </div>
    </AppShellV2>
  );
}
