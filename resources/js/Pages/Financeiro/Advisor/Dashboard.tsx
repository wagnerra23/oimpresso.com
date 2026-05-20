// @memcofre tela=/advisor module=Financeiro
// Onda 31 (2026-05-20) #57 US-FIN-037 — Dashboard do contador parceiro.
// Mostra cards de cada cliente acessível via grant ATIVO + links read-only
// pra /financeiro/unificado e /financeiro/relatorios (middleware AdvisorViewScope
// força readonly + valida grant).

import { router } from '@inertiajs/react';

interface ClienteCard {
  access_id: number;
  business_id: number;
  business_name: string;
  granted_at_label: string | null;
  can_view_unificado: boolean;
  can_view_reports: boolean;
  has_consent: boolean;
  url_unificado: string;
  url_relatorios: string;
}

interface Props {
  advisor: {
    id: number;
    nome: string;
    email: string;
    referral_code: string;
  };
  clientes: ClienteCard[];
  total_clientes: number;
}

function Dashboard({ advisor, clientes, total_clientes }: Props) {
  const logout = () => {
    router.post('/advisor/logout');
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="bg-white border-b">
        <div className="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
          <div>
            <h1 className="text-xl font-semibold">Portal do Contador</h1>
            <p className="text-xs text-muted-foreground">
              {advisor.nome} · {advisor.email}
            </p>
          </div>
          <div className="flex items-center gap-3 text-xs">
            <span className="text-muted-foreground">
              Seu código de indicação: <code className="font-mono bg-slate-100 px-2 py-0.5 rounded">{advisor.referral_code}</code>
            </span>
            <button type="button" className="os-btn ghost" onClick={logout}>
              Sair
            </button>
          </div>
        </div>
      </header>

      <main className="max-w-6xl mx-auto px-6 py-8 space-y-6">
        <section>
          <h2 className="text-base font-semibold">Meus clientes ({total_clientes})</h2>
          <p className="text-sm text-muted-foreground">
            Visualização somente leitura. Qualquer alteração precisa ser feita pelo próprio cliente.
          </p>
        </section>

        {clientes.length === 0 ? (
          <div className="rounded-md border bg-white p-8 text-center">
            <p className="text-sm text-muted-foreground">
              Você ainda não tem clientes ativos. Peça ao cliente para te adicionar em<br />
              <code className="text-xs">Financeiro → Configurações → Contador</code>
            </p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {clientes.map((c) => (
              <div key={c.access_id} className="rounded-md border bg-white p-4 space-y-3">
                <div>
                  <h3 className="font-semibold">{c.business_name}</h3>
                  <p className="text-xs text-muted-foreground">
                    Desde {c.granted_at_label ?? '—'}
                  </p>
                </div>

                {!c.has_consent && (
                  <div className="rounded border border-amber-300 bg-amber-50 px-2 py-1 text-xs text-amber-900">
                    Sem consentimento LGPD registrado. Acesso pode ser bloqueado.
                  </div>
                )}

                <div className="flex flex-wrap gap-2">
                  {c.can_view_unificado && (
                    <a
                      href={c.url_unificado}
                      className="os-btn primary text-xs"
                    >
                      Visão Unificada
                    </a>
                  )}
                  {c.can_view_reports && (
                    <a
                      href={c.url_relatorios}
                      className="os-btn ghost text-xs"
                    >
                      Relatórios
                    </a>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </main>
    </div>
  );
}

export default Dashboard;
