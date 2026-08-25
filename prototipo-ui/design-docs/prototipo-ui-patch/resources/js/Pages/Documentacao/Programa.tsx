// @memcofre tela=/documentacao/programa module=Governanca status=proposta
//
// Programa de documentação — Trilha D. Leitura humana do plano dono
// (memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md § Trilha D).
//
// INVARIANTES (Programa.casos.md — não remover na revisão):
//  UC-PROGDOC-01 · estado de onda vem das tasks MCP via props. Proibido literal de status aqui.
//  UC-PROGDOC-02 · texto do plano vem do servidor (parse do dono). Proibido parágrafo do plano como literal.
//  UC-PROGDOC-03 · read-only: nenhum controle grava.
//  UC-PROGDOC-04 · vista na URL (?vista=) e tabs underline-active, nunca pill.
//  UC-PROGDOC-05 · sem segredo, sem business_id: conteúdo é global de governança.

import AppShellV2 from '@/Layouts/AppShellV2';
import { type ReactNode } from 'react';
import { router, usePage } from '@inertiajs/react';
import { ExternalLink, ArrowLeft, RotateCcw } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';

type Vista = 'ciclo' | 'ondas' | 'caminhos' | 'pronto';
type StatusOnda = 'todo' | 'doing' | 'done';

interface Estacao {
  n: string;              // "01".."11"
  fase: 'medir' | 'traduzir' | 'publicar' | 'operar';
  titulo: string;
  resumo: string;
  entrada: string;
  maquina: string;        // máquina que JÁ existe
  regra: string;
}

interface Onda {
  id: string;             // "D0".."D10"
  nome: string;
  escopo: string;
  saida: string;
  gate: string;
  status: StatusOnda;     // projeção das tasks MCP — nunca literal desta tela
  tasks_abertas: number;
}

interface Caminho {
  tipo: string;
  resumo: string;
  fluxo: string[];
  campos: string[];
}

interface ItemDod {
  texto: string;
  onda: string;
  fechado: boolean;
  parcial: boolean;
}

interface Props {
  plano: { git_url: string; git_path: string; secao: string; atualizado_em: string };
  task: { codigo: string; status: string; parent_plan: string };
  estacoes: Estacao[];
  ondas: Onda[];
  caminhos: Caminho[];
  camadas: { camada: string; componentes: string; dono: string }[];
  dod: ItemDod[];
  batimento: { momento: string; maquina: string; efeito: string }[];
  estacao_de_retorno: { de: string; para: string };
}

const VISTAS: { key: Vista; label: string }[] = [
  { key: 'ciclo', label: 'Ciclo' },
  { key: 'ondas', label: 'Ondas' },
  { key: 'caminhos', label: 'Caminhos' },
  { key: 'pronto', label: 'Pronto & batimento' },
];

// Fase colore o marcador. Hue nomeado no token quando a tela sair de proposta.
const FASE_HUE: Record<Estacao['fase'], number> = { medir: 220, traduzir: 295, publicar: 155, operar: 75 };

// Rótulo humano do status — TRADUÇÃO na borda, a fonte é a task (UC-PROGDOC-01).
const ROTULO: Record<StatusOnda, string> = { todo: 'na fila', doing: 'em execução', done: 'fechada' };

function useVista(): [Vista, (v: Vista) => void] {
  const { url } = usePage();
  const atual = (new URL(url, 'http://x').searchParams.get('vista') ?? 'ciclo') as Vista;
  const vista = VISTAS.some((v) => v.key === atual) ? atual : 'ciclo';
  const set = (v: Vista) =>
    router.get('/documentacao/programa', { vista: v }, { preserveState: true, preserveScroll: true, replace: true });
  return [vista, set];
}

function TabsVistas({ vista, onChange }: { vista: Vista; onChange: (v: Vista) => void }) {
  return (
    <div data-contract="tabbar-vistas" className="border-b border-stone-200 bg-stone-50/40 px-6" role="tablist">
      <div className="flex gap-0.5">
        {VISTAS.map((v) => (
          <button
            key={v.key}
            type="button"
            role="tab"
            aria-selected={vista === v.key}
            onClick={() => onChange(v.key)}
            className={
              'px-3 py-2 text-[12.5px] border-b-2 -mb-px transition-colors ' +
              (vista === v.key
                ? 'border-primary text-stone-900 font-semibold'
                : 'border-transparent text-stone-500 hover:text-stone-900')
            }
          >
            {v.label}
          </button>
        ))}
      </div>
    </div>
  );
}

function Kpis({ ondas, estacoes, task }: Pick<Props, 'ondas' | 'estacoes' | 'task'>) {
  const atual = ondas.find((o) => o.status === 'doing');
  const fechadas = ondas.filter((o) => o.status === 'done').length;
  return (
    <div data-contract="kpi-strip" className="grid grid-cols-2 lg:grid-cols-4 gap-2.5">
      {[
        { k: 'onda atual', v: atual?.id ?? '—', s: atual?.nome ?? 'nenhuma onda em execução' },
        { k: 'ondas fechadas', v: `${fechadas}/${ondas.length}`, s: 'projeção das tasks do programa' },
        { k: 'estações do ciclo', v: String(estacoes.length), s: 'fecha em aprender → medir de novo' },
        { k: 'task MCP', v: task.codigo, s: `${task.parent_plan} · merge ratifica` },
      ].map((c) => (
        <div key={c.k} className="rounded-lg border border-stone-200 bg-white px-3.5 py-3">
          <div className="font-mono text-[10.5px] uppercase tracking-wider text-stone-500">{c.k}</div>
          <div className="mt-1.5 text-2xl font-semibold tracking-tight tabular-nums text-stone-900">{c.v}</div>
          <div className="mt-1 text-[12px] leading-snug text-stone-600">{c.s}</div>
        </div>
      ))}
    </div>
  );
}

function Ciclo({ estacoes, retorno }: { estacoes: Estacao[]; retorno: Props['estacao_de_retorno'] }) {
  return (
    <section data-contract="ciclo-estacoes">
      <div className="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-2">
        {estacoes.map((e) => (
          <article key={e.n} className="relative rounded-lg border border-stone-200 bg-white p-3">
            <span
              className="absolute right-2.5 top-2.5 h-1.5 w-1.5 rounded-full"
              style={{ background: `oklch(0.62 0.13 ${FASE_HUE[e.fase]})` }}
              aria-hidden="true"
            />
            <div className="font-mono text-[10.5px] text-stone-500">{e.n}</div>
            <h3 className="mt-1 text-[12.5px] font-semibold leading-tight text-stone-900">{e.titulo}</h3>
            <p className="mt-1 text-[11.5px] leading-snug text-stone-500">{e.resumo}</p>
            <dl className="mt-2.5 space-y-1.5 border-t border-stone-100 pt-2 text-[11.5px] leading-snug">
              <div>
                <dt className="font-mono text-[10px] uppercase tracking-wider text-stone-400">entrada</dt>
                <dd className="text-stone-600">{e.entrada}</dd>
              </div>
              <div>
                <dt className="font-mono text-[10px] uppercase tracking-wider text-stone-400">máquina</dt>
                <dd className="text-stone-600">{e.maquina}</dd>
              </div>
              <div>
                <dt className="font-mono text-[10px] uppercase tracking-wider text-stone-400">regra</dt>
                <dd className="text-stone-600">{e.regra}</dd>
              </div>
            </dl>
          </article>
        ))}
      </div>
      <p
        data-contract="ciclo-volta"
        className="mt-2.5 flex items-center gap-2 rounded-lg border border-dashed border-primary/40 bg-primary/5 px-3 py-2 font-mono text-[11px] text-stone-600"
      >
        <RotateCcw className="h-3.5 w-3.5" aria-hidden="true" />
        estação {retorno.de} → estação {retorno.para} · o aprendizado reentra na medição
      </p>
    </section>
  );
}

function Ondas({ ondas }: { ondas: Onda[] }) {
  return (
    <section data-contract="ondas-tabela" className="overflow-hidden rounded-lg border border-stone-200">
      <table className="w-full text-[12.5px]">
        <thead>
          <tr className="border-b border-stone-200 bg-stone-50/40 text-left font-mono text-[10.5px] uppercase tracking-wider text-stone-500">
            <th className="px-3 py-2 font-medium">onda</th>
            <th className="px-3 py-2 font-medium">escopo</th>
            <th className="px-3 py-2 font-medium">saída no dono existente</th>
            <th className="px-3 py-2 font-medium">gate de saída</th>
            <th className="w-[130px] px-3 py-2 font-medium">estado</th>
          </tr>
        </thead>
        <tbody>
          {ondas.map((o) => (
            <tr key={o.id} className="border-b border-stone-100 align-top last:border-0">
              <td className="px-3 py-2.5">
                <b className="text-stone-900">{o.id}</b>
                <div className="font-mono text-[11px] text-stone-500">{o.nome}</div>
              </td>
              <td className="px-3 py-2.5 text-stone-600">{o.escopo}</td>
              <td className="px-3 py-2.5 text-stone-600">{o.saida}</td>
              <td className="px-3 py-2.5 text-stone-600">{o.gate}</td>
              <td className="px-3 py-2.5">
                <span
                  className={
                    'inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium ' +
                    (o.status === 'doing'
                      ? 'bg-primary/10 text-primary'
                      : o.status === 'done'
                        ? 'text-success-fg bg-success-soft'
                        : 'bg-stone-100 text-stone-500')
                  }
                >
                  {ROTULO[o.status]}
                </span>
                {o.tasks_abertas > 0 && (
                  <div className="mt-1 font-mono text-[10.5px] text-stone-500">{o.tasks_abertas} task(s) aberta(s)</div>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}

function Caminhos({ caminhos, camadas }: Pick<Props, 'caminhos' | 'camadas'>) {
  return (
    <>
      <section data-contract="caminhos-grid" className="grid gap-3 lg:grid-cols-2">
        {caminhos.map((c) => (
          <article key={c.tipo} className="rounded-lg border border-stone-200 bg-white p-3.5">
            <h3 className="text-[13.5px] font-semibold text-stone-900">{c.tipo}</h3>
            <p className="mt-0.5 text-[12px] leading-snug text-stone-500">{c.resumo}</p>
            <div className="mt-3 flex flex-wrap gap-1.5">
              {c.fluxo.map((f, i) => (
                <span key={f} className="flex items-center gap-1.5">
                  {i > 0 && <span className="text-stone-400">→</span>}
                  <span className="rounded border border-stone-200 bg-stone-50 px-1.5 py-0.5 font-mono text-[10.5px] text-stone-600">
                    {f}
                  </span>
                </span>
              ))}
            </div>
            <ul className="mt-3 list-disc pl-4 text-[12.5px] leading-relaxed text-stone-600">
              {c.campos.map((i) => (
                <li key={i}>{i}</li>
              ))}
            </ul>
          </article>
        ))}
      </section>

      <section data-contract="camadas-tabela" className="mt-6 overflow-hidden rounded-lg border border-stone-200">
        <table className="w-full text-[12.5px]">
          <thead>
            <tr className="border-b border-stone-200 bg-stone-50/40 text-left font-mono text-[10.5px] uppercase tracking-wider text-stone-500">
              <th className="px-3 py-2 font-medium">camada</th>
              <th className="px-3 py-2 font-medium">componentes</th>
              <th className="px-3 py-2 font-medium">dono principal</th>
            </tr>
          </thead>
          <tbody>
            {camadas.map((c) => (
              <tr key={c.camada} className="border-b border-stone-100 align-top last:border-0">
                <td className="px-3 py-2.5"><b className="text-stone-900">{c.camada}</b></td>
                <td className="px-3 py-2.5 text-stone-600">{c.componentes}</td>
                <td className="px-3 py-2.5 text-stone-600">{c.dono}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </>
  );
}

function Pronto({ dod, batimento }: Pick<Props, 'dod' | 'batimento'>) {
  const fechados = dod.filter((d) => d.fechado).length;
  const parciais = dod.filter((d) => !d.fechado && d.parcial).length;
  return (
    <>
      <section data-contract="dod-lista">
        <h2 className="text-[15px] font-semibold text-stone-900">Definição de pronto</h2>
        <p className="mt-0.5 text-[12.5px] text-stone-500">
          {fechados} fechados · {parciais} parciais · {dod.length - fechados - parciais} abertos
        </p>
        <div className="mt-3 grid gap-2 lg:grid-cols-2">
          {dod.map((d) => (
            <div key={d.texto} className="flex items-start gap-2.5 rounded-lg border border-stone-200 bg-white px-3 py-2.5">
              <span
                aria-hidden="true"
                className={
                  'mt-0.5 h-3.5 w-3.5 flex-none rounded border ' +
                  (d.fechado
                    ? 'border-success bg-success'
                    : d.parcial
                      ? 'border-amber-500 bg-amber-500/25'
                      : 'border-stone-300 bg-stone-50')
                }
              />
              <div>
                <p className="text-[12.5px] leading-snug text-stone-600">{d.texto}</p>
                <span className="mt-1 block font-mono text-[10.5px] uppercase tracking-wider text-stone-400">
                  {d.onda} · {d.fechado ? 'fechado' : d.parcial ? 'parcial' : 'aberto'}
                </span>
              </div>
            </div>
          ))}
        </div>
      </section>

      <section data-contract="batimento-tabela" className="mt-6">
        <h2 className="text-[15px] font-semibold text-stone-900">Batimento que mantém a trilha ativa</h2>
        <p className="mt-0.5 text-[12.5px] text-stone-500">
          advisory por decisão: detecta e oferece trabalho, não decide conteúdo nem merge
        </p>
        <div className="mt-3 overflow-hidden rounded-lg border border-stone-200">
          <table className="w-full text-[12.5px]">
            <thead>
              <tr className="border-b border-stone-200 bg-stone-50/40 text-left font-mono text-[10.5px] uppercase tracking-wider text-stone-500">
                <th className="px-3 py-2 font-medium">momento</th>
                <th className="px-3 py-2 font-medium">máquina existente</th>
                <th className="px-3 py-2 font-medium">efeito</th>
              </tr>
            </thead>
            <tbody>
              {batimento.map((b) => (
                <tr key={b.momento} className="border-b border-stone-100 align-top last:border-0">
                  <td className="px-3 py-2.5"><b className="text-stone-900">{b.momento}</b></td>
                  <td className="px-3 py-2.5 font-mono text-[11.5px] text-stone-600">{b.maquina}</td>
                  <td className="px-3 py-2.5 text-stone-600">{b.efeito}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </>
  );
}

function DocumentacaoPrograma(props: Props) {
  const { plano, task, estacoes, ondas, caminhos, camadas, dod, batimento, estacao_de_retorno } = props;
  const [vista, setVista] = useVista();

  return (
    <div>
      <div data-contract="page-header">
        <PageHeader
          title="Programa de documentação"
          suffix=" · Trilha D"
          subtitle={
            <>
              Não é escrever documentação — é manter um sistema que mede, traduz, publica, opera, detecta drift e
              aprende. Plano atualizado em {plano.atualizado_em}.
            </>
          }
        >
          <div className="ml-auto flex flex-shrink-0 items-center gap-1.5">
            <button
              type="button"
              onClick={() => router.visit('/documentacao')}
              className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[12px] text-stone-600 hover:bg-stone-100"
            >
              <ArrowLeft className="h-3.5 w-3.5" /> Documentação
            </button>
            <a
              href={plano.git_url}
              target="_blank"
              rel="noopener"
              className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[12px] text-stone-600 hover:bg-stone-100"
            >
              Ver plano no git <ExternalLink className="h-3.5 w-3.5" />
            </a>
          </div>
        </PageHeader>
      </div>

      <TabsVistas vista={vista} onChange={setVista} />

      <div className="px-6 pb-16 pt-5">
        <Kpis ondas={ondas} estacoes={estacoes} task={task} />

        <div className="mt-6">
          {vista === 'ciclo' && <Ciclo estacoes={estacoes} retorno={estacao_de_retorno} />}
          {vista === 'ondas' && <Ondas ondas={ondas} />}
          {vista === 'caminhos' && <Caminhos caminhos={caminhos} camadas={camadas} />}
          {vista === 'pronto' && <Pronto dod={dod} batimento={batimento} />}
        </div>

        <p data-contract="fonte-dona" className="mt-8 border-t border-stone-200 pt-3.5 font-mono text-[11px] text-stone-500">
          dono deste texto:{' '}
          <a href={plano.git_url} target="_blank" rel="noopener" className="text-primary hover:underline">
            {plano.git_path}
          </a>{' '}
          {plano.secao} · a tela renderiza o plano; não guarda cópia
        </p>
      </div>
    </div>
  );
}

DocumentacaoPrograma.layout = (page: ReactNode) => (
  <AppShellV2
    title="Programa de documentação — Trilha D"
    breadcrumbItems={[{ label: 'Documentação', href: '/documentacao' }, { label: 'Programa' }]}
  >
    {page}
  </AppShellV2>
);

export default DocumentacaoPrograma;
