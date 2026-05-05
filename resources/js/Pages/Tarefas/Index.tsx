// @memcofre
//   tela: /tarefas
//   stories: a definir (Fase 4 ADR 0039 — TaskProvider/TaskRegistry)
//   adrs: 0039 (Cockpit), UI-0008 (cockpit layout), UI-0011 (sidebar single-pane)
//   status: stub (placeholder até backend agregar providers)
//   module: app principal (cross-módulo)

import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Inbox, MessageSquare } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';

export default function TarefasIndex() {
  return (
    <>
      <Head title="Tarefas — Oimpresso" />
      <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 p-12 text-center">
        <Inbox className="h-16 w-16" style={{ color: 'var(--text-mute)' }} />
        <div>
          <h1 className="text-xl font-semibold" style={{ color: 'var(--text)' }}>
            Sua caixa de tarefas
          </h1>
          <p className="mt-2 text-sm" style={{ color: 'var(--text-mute)' }}>
            Aqui virão as pendências cross-módulo: aprovações de OS, ligações CRM,
            justificativas de ponto, boletos a aprovar.
          </p>
          <p className="mt-1 text-xs" style={{ color: 'var(--text-mute)' }}>
            Em desenvolvimento — Fase 4 do plano de migração (TaskProvider).
          </p>
        </div>
        <Link
          href="/copiloto"
          className="mt-2 inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium"
          style={{
            background: 'var(--accent)',
            color: 'var(--accent-fg)',
          }}
        >
          <MessageSquare className="h-4 w-4" />
          Conversar com o Copiloto
        </Link>
      </div>
    </>
  );
}

TarefasIndex.layout = (page: React.ReactNode) => (
  <AppShellV2
    title="Tarefas"
    breadcrumbItems={[{ label: 'Tarefas' }]}
  >
    {page}
  </AppShellV2>
);
