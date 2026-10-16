// Aba Casos de uso — UC-FLX com o que faz, processo, quem anuncia, porte, modelos e estado no
// oimpresso; filtros, gaveta do caso e lista das fontes.
// Fonte visual: aba "casos" e `CasoDrawer` em prototipo-ui/cowork/Wagner/fluxos-page.jsx.
// Dados: CatalogoModelos::casos(), fontes(), estadosCaso() (#9501).

import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import DataTable from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import type { CasoCatalogo, MapaEstadosCaso, MapaFontes, Modelo, Opcao } from './tipos';

interface Props {
  casos: CasoCatalogo[];
  fontes: MapaFontes;
  estados: MapaEstadosCaso;
  dominios: Opcao[];
  portes: Opcao[];
  modelos: Modelo[];
  emUsoId: string | null;
}

const TODOS = '__todos';
/** Domínio do caso → processo da aba Processos (`DOM_PROC` do protótipo). */
const DOM_PROC: Record<string, string> = { venda: 'venda', producao: 'producao', compra: 'compra', entrega: 'entrega', financeiro: 'cobranca' };

function Selo({ estado, estados }: { estado: string; estados: MapaEstadosCaso }) {
  const e = estados[estado];
  return <span className={`flx-pill ${e?.tom ?? 'mute'}`}>{e?.rotulo ?? estado}</span>;
}

function Filtro({ rotulo, valor, onChange, opcoes }: { rotulo: string; valor: string; onChange: (v: string) => void; opcoes: Opcao[] }) {
  const id = `flx-f-${rotulo}`;
  return (
    <label className="flx-busca" htmlFor={id}>
      <span className="flx-lbl">{rotulo}</span>
      <Select value={valor} onValueChange={onChange}>
        <SelectTrigger id={id}><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value={TODOS}>Todos</SelectItem>
          {opcoes.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
        </SelectContent>
      </Select>
    </label>
  );
}

export default function AbaCasos({ casos, fontes, estados, dominios, portes, modelos, emUsoId }: Props) {
  const [q, setQ] = useState('');
  const [fProc, setFProc] = useState(TODOS);
  const [fFonte, setFFonte] = useState(TODOS);
  const [fEst, setFEst] = useState(TODOS);
  const [aberto, setAberto] = useState<string | null>(null);

  const nomeProc = (d: string) => dominios.find((o) => o.value === d)?.label ?? d;
  const nomePorte = (p: string) => portes.find((o) => o.value === p)?.label ?? p;
  const nomeFontes = (ids: string[]) => ids.map((f) => fontes[f]?.nome ?? f).join(', ');
  const ativos = Number(fProc !== TODOS) + Number(fFonte !== TODOS) + Number(fEst !== TODOS) + Number(q.trim() !== '');
  const limpar = () => { setQ(''); setFProc(TODOS); setFFonte(TODOS); setFEst(TODOS); };

  const filtrados = useMemo(() => {
    const t = q.trim().toLowerCase();
    return casos.filter((c) =>
      (!t || `${c.id} ${c.titulo} ${c.regra} ${c.etapa} ${nomeFontes(c.fontes)}`.toLowerCase().includes(t))
      && (fProc === TODOS || c.processo === fProc)
      && (fFonte === TODOS || c.fontes.includes(fFonte))
      && (fEst === TODOS || c.estado === fEst));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [casos, q, fProc, fFonte, fEst]);

  const colunas: ColumnDef<CasoCatalogo>[] = [
    { id: 'id', header: 'Caso', meta: { width: 96, mono: true }, cell: ({ row }) => row.original.id },
    {
      id: 't', header: 'O que faz',
      cell: ({ row }) => (
        <div>
          <b>{row.original.titulo}</b>
          <div className="flx-dim">Ao entrar em {row.original.etapa} — {row.original.regra}</div>
        </div>
      ),
    },
    { id: 'proc', header: 'Processo', cell: ({ row }) => nomeProc(row.original.processo) },
    { id: 'fontes', header: 'Quem anuncia', cell: ({ row }) => nomeFontes(row.original.fontes) },
    { id: 'porte', header: 'A partir de', cell: ({ row }) => nomePorte(row.original.porte) },
    { id: 'mod', header: 'Modelos', meta: { align: 'right', mono: true }, cell: ({ row }) => row.original.modelos.length },
    {
      id: 'estado', header: 'No oimpresso',
      cell: ({ row }) => (
        <span className="flx-est">
          <Selo estado={row.original.estado} estados={estados} />
          {row.original.us && <small>{row.original.us}</small>}
        </span>
      ),
    },
  ];

  const c = casos.find((x) => x.id === aberto) ?? null;
  const comUrl = Object.entries(fontes).filter(([, f]) => f.url);

  return (
    <>
      <p className="flx-intro">
        Clique num caso para ver as fontes e os modelos que o usam. Cada caso é uma transição com efeito — <i>ao entrar
        na etapa, o sistema faz</i>. Levantado no que os concorrentes <b>anunciam</b> em páginas públicas; nada foi testado
        em demonstração. Ausência de fonte quer dizer “não anunciado”, não “não tem”.
      </p>

      <div className="flx-filtros">
        <label className="flx-busca" htmlFor="flx-busca">
          <span className="flx-lbl">Buscar</span>
          <Input id="flx-busca" type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="caso, etapa, efeito ou concorrente" />
        </label>
        <Filtro rotulo="Processo" valor={fProc} onChange={setFProc} opcoes={dominios} />
        <Filtro rotulo="Quem anuncia" valor={fFonte} onChange={setFFonte} opcoes={Object.entries(fontes).map(([v, f]) => ({ value: v, label: f.nome }))} />
        <Filtro rotulo="No oimpresso" valor={fEst} onChange={setFEst} opcoes={Object.entries(estados).map(([v, e]) => ({ value: v, label: e.rotulo }))} />
        <div className="flx-filtros-r" aria-live="polite">
          <span className="flx-dim">{filtrados.length} de {casos.length} casos</span>
          {ativos > 0 && <Button size="sm" variant="ghost" onClick={limpar}>Limpar filtros ({ativos})</Button>}
        </div>
      </div>

      {filtrados.length === 0 ? (
        <EmptyState title="Nenhum caso com esses filtros" description="Troque o processo, a fonte ou o estado." action={<Button size="sm" onClick={limpar}>Limpar filtros</Button>} />
      ) : (
        <DataTable<CasoCatalogo>
          caption="Casos de uso"
          columns={colunas}
          data={filtrados}
          rowKey={(r) => r.id}
          showSearch={false}
          onRowClick={(r) => setAberto(r.id)}
          totalLabel="casos"
        />
      )}

      <div className="flx-sec"><span>Fontes</span><i /></div>
      <ul className="flx-fontes">
        {comUrl.map(([id, f]) => (
          <li key={id}>
            <a href={f.url} target="_blank" rel="noopener noreferrer">{f.nome}</a>
            <span>{casos.filter((x) => x.fontes.includes(id)).length} casos</span>
          </li>
        ))}
      </ul>
      <p className="flx-nota">
        Pesquisa base: memory/research/2026-05-prospeccao/02 e 33 (main, lidos em 06/10/2026) + páginas públicas da
        Holdprint. Documento: fluxos-modelos.casos.md.
      </p>

      <Sheet open={c !== null} onOpenChange={(a) => !a && setAberto(null)}>
        <SheetContent className="w-full sm:max-w-[520px] overflow-y-auto">
          {c && (
            <>
              <SheetHeader>
                <SheetTitle>{c.titulo}</SheetTitle>
                <SheetDescription>{c.id} · {nomeProc(c.processo)}</SheetDescription>
              </SheetHeader>
              <div className="flx-det">
                <section className="flx-proc">
                  <h4>Regra</h4>
                  <ul className="flx-regras"><li><span className="em">Ao entrar em <b>{c.etapa}</b></span><span>{c.regra}</span><span /></li></ul>
                </section>
                <section className="flx-proc">
                  <h4>No oimpresso</h4>
                  <div className="flx-est-l">
                    <Selo estado={c.estado} estados={estados} />
                    {c.us && <span className="flx-dim">{c.us}</span>}
                    <span className="flx-dim">· a partir do porte {nomePorte(c.porte).toLowerCase()}</span>
                  </div>
                </section>
                <section className="flx-proc">
                  <h4>Quem anuncia <span className="flx-dim">{c.fontes.length}</span></h4>
                  <ul className="flx-fontes">
                    {c.fontes.map((f) => (
                      <li key={f}>
                        {fontes[f]?.url
                          ? <a href={fontes[f].url} target="_blank" rel="noopener noreferrer">{fontes[f].nome}</a>
                          : <span>{fontes[f]?.nome ?? f} (proposta própria)</span>}
                      </li>
                    ))}
                  </ul>
                </section>
                <section className="flx-proc">
                  <h4>Modelos que usam <span className="flx-dim">{c.modelos.length}</span></h4>
                  {c.modelos.length > 0 ? (
                    <div className="flp-liga">
                      {c.modelos.map((id) => (
                        <span key={id} className="flp-chip">
                          {modelos.find((m) => m.id === id)?.nome ?? id}{emUsoId === id ? ' · em uso' : ''}
                        </span>
                      ))}
                    </div>
                  ) : <p className="flx-nota">Nenhum modelo usa este caso ainda.</p>}
                </section>
              </div>
              <SheetFooter className="flex-row justify-end gap-2">
                <Button variant="ghost" onClick={() => setAberto(null)}>Fechar</Button>
                <Button asChild><Link href={`/fluxos?aba=processos&processo=${DOM_PROC[c.processo] ?? 'venda'}`}>Ver o processo</Link></Button>
              </SheetFooter>
            </>
          )}
        </SheetContent>
      </Sheet>
    </>
  );
}
