// Naturezas de operação — lista (RUNBOOK-tributacao §Naturezas · onda 3b). Só aparece com a flag
// useV2NfeNaturezas ligada para a empresa; o Index decide pelo prop `naturezasLiberado`.
//
// Forma: prototipo-ui/cowork/Felipe/Configuracao de Tributos.html, `renderLista()` — cabeçalho da
// seção, faixa de resumo (`.dados`), abas com contagem (`.areas`), dica + busca (`.toolbar`) e a
// tabela com as colunas Natureza · CFOP · ICMS · Tributos · Situação (`.lista-tb`). A seção mora
// dentro de /nfe-brasil/tributacao (RUNBOOK "um lugar só"), por isso o título é h2, não h1.
import { useCallback, useEffect, useMemo, useState, type CSSProperties, type ReactNode } from 'react';
import { Plus, ChevronRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import SubNav from '@/Components/shared/SubNav';
import { Grid, Inline, Stack } from '@/Components/layout';
import NaturezaEditor from './NaturezaEditor';
import {
  CST_COM_ST, FINS, REGIME_NOME, completar, listar, rotulo, vazia,
  type EmpresaResumo, type LinhaNatureza, type Natureza, type Regime,
} from './natureza';

type Filtro = 'todas' | 'saidas' | 'devolucoes' | 'entradas' | 'inativas';

const FILTROS: { value: Filtro; label: string }[] = [
  { value: 'todas', label: 'Todas' },
  { value: 'saidas', label: 'Saídas' },
  { value: 'devolucoes', label: 'Devoluções' },
  { value: 'entradas', label: 'Entradas' },
  { value: 'inativas', label: 'Inativas' },
];

// Cores dos tributos — as mesmas do protótipo (--tx-icms… são apelidos destes tokens do cockpit).
const COR = {
  icms: 'var(--origin-CRM-fg)',
  ipi: 'var(--origin-MFG-fg)',
  pis: 'var(--origin-FIN-fg)',
  ibs: 'var(--stage-violet)',
} as const;

function noFiltro(l: LinhaNatureza, f: Filtro): boolean {
  const n = l.natureza;
  if (f === 'inativas' ? n.ativo : !n.ativo) return false;
  if (f === 'saidas' && (n.tipo !== 'S' || n.fin === '4')) return false;
  if (f === 'devolucoes' && n.fin !== '4') return false;
  if (f === 'entradas' && n.tipo !== 'E') return false;
  return true;
}

function naBusca(l: LinhaNatureza, busca: string): boolean {
  const b = busca.trim().toLowerCase();
  if (!b) return true;
  const n = l.natureza;
  return n.nome.toLowerCase().includes(b) || [n.cfop.in, n.cfop.out, n.cfop.ext].some((c) => (c ?? '').includes(b));
}

function pct(fracao: number): string {
  return `${(fracao * 100).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;
}

function Tag({ cor, children }: { cor: string; children: ReactNode }) {
  return (
    <span
      className="inline-flex h-5 items-center whitespace-nowrap rounded-[5px] border px-[7px] font-mono text-[10.5px] font-semibold"
      style={{
        background: `color-mix(in oklch, ${cor} 12%, var(--surface))`,
        color: `color-mix(in oklch, ${cor} 62%, var(--text))`,
        borderColor: `color-mix(in oklch, ${cor} 26%, transparent)`,
      }}
    >
      {children}
    </span>
  );
}

function Pill({ cor, children }: { cor: string; children: ReactNode }) {
  return (
    <span
      className="inline-flex h-5 items-center gap-1.5 whitespace-nowrap text-[10.5px] font-semibold uppercase tracking-[.04em]"
      style={{ color: `color-mix(in oklch, ${cor} 62%, var(--text))` }}
    >
      <span aria-hidden className="h-1.5 w-1.5 flex-none rounded-full" style={{ background: cor }} />
      {children}
    </span>
  );
}

function Dado({ rotulo: r, valor, sub, mono }: { rotulo: string; valor: ReactNode; sub?: ReactNode; mono?: boolean }) {
  return (
    <div className="min-w-0 bg-[var(--surface)] px-3 py-2.5">
      <span className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-[var(--text-dim)]">{r}</span>
      <b className="block break-words text-[13px] font-semibold leading-snug text-[var(--text)]">{valor}</b>
      {sub && (
        <span className={`mt-0.5 block text-[11.5px] leading-snug text-[var(--text-dim)] ${mono ? 'font-mono' : ''}`}>{sub}</span>
      )}
    </div>
  );
}

function situacao(l: LinhaNatureza): { texto: string; cor: string } {
  if (!l.natureza.ativo) return { texto: 'Inativa', cor: 'var(--text-mute)' };
  if (!l.editavel) return { texto: 'Usada no cálculo', cor: 'var(--stage-blue)' };
  if (l.pendencias) return { texto: `${l.pendencias} ${l.pendencias === 1 ? 'pendência' : 'pendências'}`, cor: 'var(--neg)' };
  return { texto: 'Em uso', cor: 'var(--pos)' };
}

const TH = 'whitespace-nowrap border-b border-[var(--border)] bg-[var(--bg-2)] px-2.5 py-2 text-left text-[10.5px] font-semibold uppercase tracking-[.04em] text-[var(--text-dim)]';
const TD = 'border-b border-[var(--border-2)] px-2.5 py-[9px] align-top text-[12.5px] text-[var(--text)]';

export default function NaturezasSecao() {
  const [linhas, setLinhas] = useState<LinhaNatureza[] | null>(null);
  const [regime, setRegime] = useState<Regime>('LP');
  const [empresa, setEmpresa] = useState<EmpresaResumo | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [filtro, setFiltro] = useState<Filtro>('todas');
  const [busca, setBusca] = useState('');
  const [aberta, setAberta] = useState<{ natureza: Natureza; editavel: boolean } | null>(null);

  const carregar = useCallback(() => {
    setErro(null);
    listar()
      .then((r) => { setLinhas(r.naturezas); setRegime(r.regime); setEmpresa(r.empresa ?? null); })
      .catch(() => setErro('Não foi possível carregar as naturezas agora.'));
  }, []);

  useEffect(carregar, [carregar]);

  const visiveis = useMemo(
    () => (linhas ?? []).filter((l) => noFiltro(l, filtro) && naBusca(l, busca)),
    [linhas, filtro, busca],
  );
  const abas = useMemo(
    () => FILTROS.map((f) => ({ ...f, badge: linhas ? linhas.filter((l) => noFiltro(l, f.value)).length : undefined })),
    [linhas],
  );
  const sn = regime === 'SN';

  if (aberta) {
    return (
      <NaturezaEditor
        inicial={aberta.natureza}
        editavel={aberta.editavel}
        regime={regime}
        onFechar={(salvou) => { setAberta(null); if (salvou) carregar(); }}
      />
    );
  }

  const abrir = (l: LinhaNatureza) => setAberta({ natureza: { ...completar(l.natureza), id: l.id }, editavel: l.editavel });

  return (
    <Stack asChild gap={4}>
    <section id="naturezas" data-contract="naturezas" aria-labelledby="naturezas-titulo">
      <Inline asChild wrap align="end" gap={3} className="gap-x-6">
      <header>
        <div className="min-w-0">
          <Inline gap={1} className="mb-1.5 gap-1.5 text-xs text-[var(--text-dim)]">
            <span>Fiscal</span>
            <span aria-hidden className="text-[var(--text-mute)]">/</span>
            <span>Naturezas de operação</span>
          </Inline>
          <h2 id="naturezas-titulo" className="m-0 text-[22px] font-semibold leading-tight tracking-[-.02em] text-[var(--text)]">
            Tributação padrão
          </h2>
          <p className="mt-1 max-w-[70ch] text-[12.5px] leading-[1.45] text-[var(--text-dim)]">
            Cada natureza de operação guarda o CFOP, o {sn ? 'CSOSN' : 'CST'} e de onde vem cada alíquota. No lançamento
            da nota, ao escolher a natureza, esses padrões preenchem os itens; os valores são calculados ali, com os
            produtos e preços da nota.
          </p>
        </div>
        <Button className="ml-auto" onClick={() => setAberta({ natureza: vazia(), editavel: true })}>
          <Plus className="mr-1.5 h-4 w-4" />
          Nova natureza
        </Button>
      </header>
      </Inline>

      {empresa && (
        <Grid
          gap={0}
          className="gap-px overflow-hidden rounded-lg border border-[var(--border-2)] bg-[var(--border-2)]"
          style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 150px), 1fr))' } as CSSProperties}
        >
          <Dado rotulo="Empresa emitente" valor={empresa.nome ?? '—'} sub={empresa.cnpj ?? undefined} mono />
          <Dado rotulo="Regime tributário" valor={REGIME_NOME[regime]} sub={sn ? 'CRT 1 · usa CSOSN' : 'CRT 3 · usa CST'} />
          <Dado
            rotulo="Origem das notas"
            valor={empresa.municipio ? `${empresa.municipio} · ${empresa.uf}` : empresa.uf}
            sub="define dentro e fora do estado"
          />
          <Dado
            rotulo="Reforma tributária"
            valor={empresa.ano === 2026 ? 'IBS e CBS em teste' : `IBS e CBS em ${empresa.ano}`}
            sub={
              empresa.ibs !== null && empresa.cbs !== null
                ? `${pct(empresa.ibs)} IBS · ${pct(empresa.cbs)} CBS em ${empresa.ano}`
                : `CBS de ${empresa.ano} ainda sem alíquota`
            }
          />
        </Grid>
      )}

      <div className="min-w-0 overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--surface)] shadow-[var(--shadow-soft)]">
        <SubNav
          ariaLabel="Filtrar naturezas"
          className="px-4"
          items={abas}
          value={filtro}
          onChange={(v) => setFiltro(v as Filtro)}
        />
        <Inline wrap justify="between" gap={3} className="px-4 py-2.5">
          <span className="text-[11.5px] text-[var(--text-dim)]">Clique numa natureza para revisar os passos.</span>
          <Input
            className="h-8 max-w-xs"
            placeholder="Buscar por nome ou CFOP"
            aria-label="Buscar natureza por nome ou CFOP"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
          />
        </Inline>

        {erro && <p className="px-4 py-4 text-sm text-destructive">{erro}</p>}
        {!linhas && !erro && <div className="px-4 pb-4"><Skeleton className="h-32 w-full" /></div>}
        {linhas && (
          <div className="overflow-x-auto">
            <table className="w-full border-collapse">
              <thead>
                <tr>
                  <th className={`${TH} pl-4`}>Natureza</th>
                  <th className={TH}>CFOP</th>
                  <th className={TH}>ICMS</th>
                  <th className={TH}>Tributos</th>
                  <th className={TH}>Situação</th>
                  <th className={`${TH} pr-4`}><span className="sr-only">Abrir</span></th>
                </tr>
              </thead>
              <tbody>
                {visiveis.length === 0 && (
                  <tr>
                    <td colSpan={6} className={`${TD} py-6 text-center text-[var(--text-dim)]`}>
                      Nenhuma natureza nesta visão{busca.trim() ? ` para “${busca.trim()}”` : ''}.
                    </td>
                  </tr>
                )}
                {visiveis.map((l) => {
                  const n = l.natureza;
                  const s = situacao(l);
                  // Venda padrão e CSV não têm os tributos da tela: mostra só o que a natureza tem.
                  const cst = sn ? n.icms?.csosn : n.icms?.cst;
                  const pc = sn ? n.pis?.cstSN : n.pis?.cst;
                  const inativa = !n.ativo;
                  return (
                    <tr
                      key={l.id}
                      className="cursor-pointer hover:[&>td]:bg-[color-mix(in_oklch,var(--accent)_4%,var(--surface))]"
                      onClick={() => abrir(l)}
                    >
                      <td className={`${TD} min-w-[200px] pl-4`}>
                        <b className={`block font-semibold ${inativa ? 'text-[var(--text-dim)]' : ''}`}>{n.nome}</b>
                        <span className="block text-[11.5px] leading-snug text-[var(--text-dim)]">
                          {n.tipo === 'E' ? 'Entrada' : 'Saída'} · finalidade {rotulo(FINS, n.fin).replace(/^\d · /, '').toLowerCase()}
                          {n.ref ? ' · com nota referenciada' : ''}
                        </span>
                      </td>
                      <td className={`${TD} whitespace-nowrap font-mono text-xs leading-normal ${inativa ? 'text-[var(--text-mute)]' : ''}`}>
                        <div><span className="mr-1 font-sans text-[11px] text-[var(--text-mute)]">dentro</span>{n.cfop.in || '—'}</div>
                        <div><span className="mr-1 font-sans text-[11px] text-[var(--text-mute)]">fora</span>{n.cfop.out || '—'}</div>
                      </td>
                      <td className={`${TD} whitespace-nowrap font-mono tabular-nums`}>
                        {cst ? `${sn ? 'CSOSN' : 'CST'} ${cst}` : <span className="text-[var(--text-mute)]">—</span>}
                        {n.icms?.origem && (
                          <small className="block font-sans text-[11px] text-[var(--text-dim)]">
                            {n.icms.origem === 'origem' ? 'da nota de origem' : n.icms.origem === 'fixa' ? 'alíquota fixa' : 'alíquota pela UF'}
                          </small>
                        )}
                      </td>
                      <td className={TD}>
                        {n.icms ? (
                          <Inline wrap gap={1}>
                            <Tag cor={COR.icms}>ICMS</Tag>
                            {cst && CST_COM_ST.includes(cst) && <Tag cor={COR.icms}>ST</Tag>}
                            {n.ipi?.on && <Tag cor={COR.ipi}>IPI {n.ipi.cst}</Tag>}
                            {pc && <Tag cor={COR.pis}>PIS/COFINS {pc}</Tag>}
                            {n.ibs?.on && <Tag cor={COR.ibs}>IBS/CBS {n.ibs.cst}</Tag>}
                          </Inline>
                        ) : (
                          <span className="text-[var(--text-mute)]">—</span>
                        )}
                      </td>
                      <td className={TD}><Pill cor={s.cor}>{s.texto}</Pill></td>
                      <td className={`${TD} pr-4 text-right`}>
                        <Button
                          size="icon"
                          variant="outline"
                          className="h-7 w-7"
                          aria-label={`${l.editavel ? 'Editar' : 'Ver'} ${n.nome}`}
                          onClick={(e) => { e.stopPropagation(); abrir(l); }}
                        >
                          <ChevronRight className="h-4 w-4" />
                        </Button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </section>
    </Stack>
  );
}
