// Documentacao/Programa — o programa de documentação (Trilha D): ciclo, ondas, caminhos e pronto.
//
// Migração Blade→Inertia (US-DOC-002 · thread 02 do playbook `programa-doc`). Contrato de paridade:
// memory/requisitos/Documentacao/ANTI-REGRESSAO-documentacao-blade.md (AR-DOC-060..069); forma de
// referência: prototipo-ui/cowork/Wagner/programa-doc-page.jsx; casos: Programa.casos.md.
//
// TUDO aqui vem do servidor. Estações, ondas, caminhos e DoD saem da § Trilha D do plano, lida a
// cada acesso; o estado sai das tasks MCP. NÃO escreva aqui nome de estação, de onda nem status —
// seria um segundo dono do mesmo fato (UC-PROGRA-01/02). Rótulo humano dos baldes é o único
// vocabulário próprio desta tela, e ele traduz o balde que o servidor já calculou.
//
// Read-only (Non-Goal do charter · UC-PROGRA-04): só navegação — nada aqui grava.
import { useState } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import SubNav from '@/Components/shared/SubNav';
import DocRail from './_components/DocRail';
import type { Navegacao } from './_components/tipos';
import '../../../css/cowork-documentacao-bundle.css';

interface LinhaTabela {
  rotulo: string;
  codigo: string | null;
  nome: string;
  colunas: string[];
}

interface Estacao {
  n: string;
  titulo: string;
  corpo: string;
}

type Balde = 'fila' | 'andamento' | 'concluida' | 'cancelada' | 'outro';
type EstadoOnda = Balde | 'sem_task';

interface EstadoPrograma {
  /** false = o MCP não respondeu: NÃO MEDIDO, diferente de "zero tasks" (UC-PROGRA-03). */
  disponivel: boolean;
  total: number;
  porBalde: Partial<Record<Balde, number>>;
  ondas: Record<string, { estado: EstadoOnda; tasks: { id: string; balde: Balde }[] }>;
  semOnda: { id: string; balde: Balde }[];
}

interface Props {
  fonte: string;
  blob: string;
  atualizadoEm: string | null;
  ondas: LinhaTabela[];
  estacoes: Estacao[];
  caminhos: LinhaTabela[];
  batimento: LinhaTabela[];
  dod: string[];
  estado: EstadoPrograma;
  buscaDisponivel: boolean;
  nav: Navegacao;
  atual: string | null;
  escopo: { tipos: string[]; prosa: string };
}

const VISTAS = [
  { value: 'ciclo', label: 'Ciclo' },
  { value: 'ondas', label: 'Ondas' },
  { value: 'caminhos', label: 'Caminhos' },
  { value: 'pronto', label: 'Pronto & batimento' },
] as const;

type Vista = (typeof VISTAS)[number]['value'];

/** Tradução na borda do balde calculado no servidor (RUNBOOK-programa §Vocabulário). */
const ROTULO_ESTADO: Record<EstadoOnda, { texto: string; variant: 'neutral' | 'info' | 'success' | 'outline' }> = {
  sem_task: { texto: 'sem task', variant: 'outline' },
  fila: { texto: 'na fila', variant: 'neutral' },
  andamento: { texto: 'em andamento', variant: 'info' },
  concluida: { texto: 'concluída', variant: 'success' },
  cancelada: { texto: 'cancelada', variant: 'outline' },
  outro: { texto: 'status fora do vocabulário', variant: 'outline' },
};

function vistaInicial(): Vista {
  if (typeof window === 'undefined') return 'ciclo';
  const pedida = new URLSearchParams(window.location.search).get('vista');
  return VISTAS.some((v) => v.value === pedida) ? (pedida as Vista) : 'ciclo';
}

function Tabela({ cabecalho, linhas, comEstado }: {
  cabecalho: string[];
  linhas: LinhaTabela[];
  comEstado?: EstadoPrograma;
}) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full border-collapse text-sm">
        <thead>
          <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
            {cabecalho.map((c) => (
              <th key={c} scope="col" className="px-3 py-2 font-medium whitespace-nowrap">{c}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {linhas.map((l) => {
            const estadoOnda = comEstado?.disponivel && l.codigo ? comEstado.ondas[l.codigo] : undefined;
            return (
              <tr key={l.rotulo} className="border-b border-border align-top">
                <th scope="row" className="px-3 py-2 text-left font-medium whitespace-nowrap">
                  {l.codigo ? <span className="mr-1.5 font-mono text-primary">{l.codigo}</span> : null}
                  <span className="font-normal">{l.codigo ? l.nome : l.rotulo}</span>
                </th>
                {comEstado ? (
                  <td className="px-3 py-2 whitespace-nowrap">
                    {estadoOnda ? (
                      <Badge variant={ROTULO_ESTADO[estadoOnda.estado].variant}>
                        {ROTULO_ESTADO[estadoOnda.estado].texto}
                      </Badge>
                    ) : (
                      <span className="text-muted-foreground">—</span>
                    )}
                  </td>
                ) : null}
                {l.colunas.map((c, i) => (
                  <td key={i} className="px-3 py-2 leading-relaxed">{c}</td>
                ))}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

export default function Programa({
  fonte, blob, atualizadoEm, ondas, estacoes, caminhos, batimento, dod, estado, buscaDisponivel, nav, atual, escopo,
}: Props) {
  const [vista, setVista] = useState<Vista>(vistaInicial);

  // Vista na URL: o link colado num handoff abre exatamente ali. `replaceState` e não visita
  // Inertia — trocar de aba não refaz o request nem relê o plano.
  const trocarVista = (nova: string) => {
    setVista(nova as Vista);
    const url = new URL(window.location.href);
    url.searchParams.set('vista', nova);
    window.history.replaceState(window.history.state, '', url);
  };

  const emAndamento = Object.entries(estado.ondas)
    .filter(([, o]) => o.estado === 'andamento')
    .map(([codigo]) => codigo);

  return (
    <AppShellV2
      title="Programa de documentação"
      breadcrumbItems={[{ label: 'Documentação', href: '/documentacao' }, { label: 'Programa' }]}
    >
      <div className="doc-page">
        <div data-contract="cabecalho">
          <PageHeader
            title="Programa de documentação · Trilha D"
            subtitle="Não é escrever documentação — é manter um sistema que mede, traduz, publica, opera, detecta drift e aprende."
            actions={
              <div className="flex gap-2">
                <Button asChild variant="ghost" size="sm">
                  <a href="/documentacao">← Documentação</a>
                </Button>
                <Button asChild variant="outline" size="sm">
                  <a href={blob} target="_blank" rel="noopener noreferrer">Ver plano no git</a>
                </Button>
              </div>
            }
            below={<SubNav items={[...VISTAS]} value={vista} onChange={trocarVista} />}
          />
        </div>

        <div className="doc-wrap lg:!grid-cols-[252px_minmax(0,1fr)]">
          <DocRail nav={nav} atual={atual} escopoProsa={escopo.prosa} buscaDisponivel={buscaDisponivel} />

          <main className="doc-main !max-w-none space-y-6">
            <div className="doc-meta">
              <span className="m">{fonte}</span>
              {atualizadoEm ? <span className="m">plano atualizado {atualizadoEm}</span> : null}
              <span className="m">renderizado a cada acesso</span>
            </div>

            <div data-contract="kpis">
              <KpiGrid cols={4}>
                <KpiCard label="Ondas" value={ondas.length} description="da tabela § D.3 do plano" />
                <KpiCard label="Estações do ciclo" value={estacoes.length} description="fecha em aprender → medir de novo" />
                <KpiCard
                  label="Tasks do programa"
                  value={estado.disponivel ? estado.total : '—'}
                  description={estado.disponivel ? 'parent_plan=programa-ondas no MCP' : 'MCP indisponível — estado não medido'}
                />
                <KpiCard
                  label="Ondas em andamento"
                  value={estado.disponivel ? (emAndamento.length ? emAndamento.join(' · ') : 'nenhuma') : '—'}
                  description={estado.disponivel ? 'derivado das tasks, não do plano' : 'sem MCP a tela não afirma estado'}
                />
              </KpiGrid>
            </div>

            {!estado.disponivel ? (
              <p role="status" className="rounded-md border border-dashed border-border px-3 py-2 text-sm text-muted-foreground">
                Estado de execução indisponível: o MCP não respondeu. As ondas aparecem sem estado — a tela não inventa um.
              </p>
            ) : null}

            {vista === 'ciclo' && (
              <section aria-labelledby="h-ciclo">
                <h2 id="h-ciclo" className="mb-3 text-base font-semibold">O ciclo completo</h2>
                <ol className="grid list-none gap-2.5 p-0 sm:grid-cols-2 xl:grid-cols-3">
                  {estacoes.map((e) => (
                    <li key={e.n} className="flex flex-col gap-1 rounded-md border border-border px-3.5 py-3">
                      <span className="font-mono text-xs text-muted-foreground">{e.n}</span>
                      <strong className="text-sm">{e.titulo}</strong>
                      <span className="text-xs leading-relaxed text-muted-foreground">{e.corpo}</span>
                    </li>
                  ))}
                </ol>
                <p className="mt-4 rounded-md border border-dashed border-border px-3.5 py-2.5 font-mono text-xs text-muted-foreground">
                  estação {estacoes.length} → estação 02 · o aprendizado reentra na medição; publicar não encerra
                </p>
              </section>
            )}

            {vista === 'ondas' && (
              <section aria-labelledby="h-ondas">
                <h2 id="h-ondas" className="mb-3 text-base font-semibold">As ondas</h2>
                <Tabela
                  cabecalho={['Onda', 'Estado', 'Escopo', 'Saída no dono existente', 'Gate de saída']}
                  linhas={ondas}
                  comEstado={estado}
                />
                {estado.disponivel && estado.semOnda.length > 0 ? (
                  <p className="mt-3 text-xs text-muted-foreground">
                    {estado.semOnda.length} task(s) do programa sem <code>onda:</code> declarada: {estado.semOnda.map((t) => t.id).join(', ')}
                  </p>
                ) : null}
              </section>
            )}

            {vista === 'caminhos' && (
              <section aria-labelledby="h-caminhos">
                <h2 id="h-caminhos" className="mb-3 text-base font-semibold">Caminho canônico por tipo</h2>
                <Tabela cabecalho={['Tipo', 'Caminho', 'O que a documentação precisa responder']} linhas={caminhos} />
              </section>
            )}

            {vista === 'pronto' && (
              <section aria-labelledby="h-pronto" className="space-y-6">
                <div>
                  <h2 id="h-pronto" className="mb-3 text-base font-semibold">Quando a trilha termina</h2>
                  <ul className="max-w-[78ch] list-disc space-y-1.5 pl-5 text-sm leading-relaxed">
                    {dod.map((c) => <li key={c}>{c}</li>)}
                  </ul>
                </div>
                {batimento.length > 0 ? (
                  <div>
                    <h2 className="mb-3 text-base font-semibold">O batimento que a mantém ativa</h2>
                    <Tabela cabecalho={['Momento', 'Máquina existente', 'Efeito']} linhas={batimento} />
                  </div>
                ) : null}
              </section>
            )}

            <footer className="doc-src">
              Dono deste texto: <a href={blob} target="_blank" rel="noopener noreferrer">{fonte}</a> — a tela
              renderiza o plano, não é cópia commitada. O estado de execução vem das tasks MCP
              (<code>parent_plan=programa-ondas</code>, agrupadas por <code>onda:</code>), nunca desta página.
            </footer>
          </main>
        </div>
      </div>
    </AppShellV2>
  );
}
