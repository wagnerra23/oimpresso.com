// Documentacao/CasosDeUso — os casos de uso do catálogo de fluxos (UC-FLX-NN) na documentação.
//
// DERIVADO, não escrito: a lista chega do servidor, lida de CasosDoPrototipo::CASOS (a mesma
// constante que a aba "Casos de uso" de /fluxos usa). Nada aqui repete id, título, regra, estado
// ou US — só layout e filtro. Charter: CasosDeUso.charter.md · casos: CasosDeUso.casos.md.
import { useMemo, useState } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import DocRail from './_components/DocRail';
import LenteBar from './_components/LenteBar';
import type { Navegacao } from './_components/tipos';
import '../../../css/cowork-documentacao-bundle.css';

interface Caso {
  id: string;
  titulo: string;
  processo: string;
  etapa: string;
  regra: string;
  fontes: string[];
  porte: string;
  estado: string;
  estadoRotulo: string;
  estadoTom: string;
  us: string;
}

interface Props {
  casos: Caso[];
  processos: string[];
  estados: Record<string, { rotulo: string; tom: string }>;
  fonte: string;
  blob: string;
  buscaDisponivel: boolean;
  nav: Navegacao;
  atual: string | null;
  escopo: { tipos: string[]; prosa: string };
}

const TOM: Record<string, string> = {
  ok: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
  accent: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
  warn: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
  danger: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
  mute: 'bg-muted text-muted-foreground',
};

export default function CasosDeUso({ casos, processos, estados, fonte, blob, buscaDisponivel, nav, atual, escopo }: Props) {
  const [processo, setProcesso] = useState<string>('');
  const [estado, setEstado] = useState<string>('');

  const visiveis = useMemo(
    () => casos.filter((c) => (!processo || c.processo === processo) && (!estado || c.estado === estado)),
    [casos, processo, estado],
  );

  return (
    <AppShellV2
      title="Casos de uso"
      breadcrumbItems={[{ label: 'Documentação', href: '/documentacao' }, { label: 'Casos de uso' }]}
    >
      <div className="doc-page">
        <div data-contract="doc-cabecalho">
          <PageHeader
            title="Casos de uso do catálogo de fluxos"
            subtitle="Lidos do catálogo a cada acesso — o estado de cada caso nunca é copiado para texto."
            actions={
              <Button asChild variant="outline" size="sm">
                <a href={blob} target="_blank" rel="noopener noreferrer">Ver fonte no git</a>
              </Button>
            }
            below={<div data-contract="doc-lentebar"><LenteBar nav={nav} path="/documentacao/casos-de-uso" /></div>}
          />
        </div>

        <div className="doc-wrap" data-contract="doc-corpo">
          <DocRail nav={nav} atual={atual} escopoProsa={escopo.prosa} buscaDisponivel={buscaDisponivel} />

          <main className="doc-main">
            <div className="doc-meta">
              <span className="m">{fonte}</span>
              <span className="m" data-contract="casos-contagem">{visiveis.length} de {casos.length} casos</span>
              <span className="m">renderizado a cada acesso</span>
            </div>

            <div className="mb-3 flex flex-wrap gap-2" data-contract="casos-filtros">
              <select
                aria-label="Filtrar por processo"
                className="h-8 rounded-md border bg-background px-2 text-sm"
                value={processo}
                onChange={(e) => setProcesso(e.target.value)}
              >
                <option value="">Todos os processos</option>
                {processos.map((p) => <option key={p} value={p}>{p}</option>)}
              </select>
              <select
                aria-label="Filtrar por estado"
                className="h-8 rounded-md border bg-background px-2 text-sm"
                value={estado}
                onChange={(e) => setEstado(e.target.value)}
              >
                <option value="">Todos os estados</option>
                {Object.entries(estados).map(([k, e]) => <option key={k} value={k}>{e.rotulo}</option>)}
              </select>
            </div>

            <div className="overflow-x-auto" data-contract="casos-tabela">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-xs text-muted-foreground">
                    <th className="py-2 pr-3">Caso</th>
                    <th className="py-2 pr-3">Processo · etapa</th>
                    <th className="py-2 pr-3">Regra</th>
                    <th className="py-2 pr-3">Estado</th>
                    <th className="py-2">US</th>
                  </tr>
                </thead>
                <tbody>
                  {visiveis.map((c) => (
                    <tr key={c.id} className="border-b align-top" data-caso={c.id}>
                      <td className="py-2 pr-3">
                        <div className="font-mono text-xs text-muted-foreground">{c.id}</div>
                        <div className="font-medium">{c.titulo}</div>
                      </td>
                      <td className="py-2 pr-3 whitespace-nowrap">{c.processo} · {c.etapa}</td>
                      <td className="py-2 pr-3">{c.regra}</td>
                      <td className="py-2 pr-3 whitespace-nowrap">
                        <span className={`rounded px-1.5 py-0.5 text-xs ${TOM[c.estadoTom] ?? TOM.mute}`}>{c.estadoRotulo}</span>
                      </td>
                      <td className="py-2 text-xs">{c.us || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {visiveis.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">Nenhum caso com esses filtros.</p>
              ) : null}
            </div>

            <div className="doc-src">
              Fonte dona destes casos:{' '}
              <a href={blob} target="_blank" rel="noopener noreferrer">{fonte}</a>
              <span> · exportada de fluxos-data.jsx; alterou o catálogo, a página muda no próximo acesso</span>
            </div>
          </main>
        </div>
      </div>
    </AppShellV2>
  );
}
