import { useEffect, useRef, type ReactNode } from 'react';

/**
 * Réplica local do `DataGrid` do DS (`prototipo-ui/design-system/components/DataGrid/`),
 * que é a grade de todas as telas do protótipo da Fabricação. O app não tem par React dele
 * (o `shared/DataTable` pagina pelo servidor), então a forma vem copiada aqui, como manda a
 * ADR 0388 (réplica primeiro). Medido contra o protótipo em 2026-10-01: `<table>` de verdade,
 * checkbox nativo tingido (13px), cabeçalho 10px caixa-alta, linhas listradas, rodapé fixo à
 * direita com "a–b de N <rótulo>".
 *
 * Diferença deliberada do original: o cabeçalho ordenável é `<button>` (o DS usa `<span>` com
 * `onClick`), pra a ordenação continuar acessível pelo teclado. O visual é o mesmo.
 */
export type ColunaGrade<T> = {
  key: string;
  label: string;
  align?: 'right';
  mono?: boolean;
  sortable?: boolean;
  render: (linha: T) => ReactNode;
};

type Props<T> = {
  caption: string;
  colunas: ColunaGrade<T>[];
  linhas: T[];
  idDe: (linha: T) => number | string;
  rotuloDe?: (linha: T) => string;
  ordem?: { k: string; dir: 'asc' | 'desc' };
  onOrdenar?: (k: string) => void;
  selecao?: {
    ids: (number | string)[];
    onAlternar: (id: number | string) => void;
    onAlternarTodas: (marcar: boolean) => void;
    todas: boolean;
    algumas: boolean;
  };
  onLinha?: (linha: T) => void;
  clicavel?: (linha: T) => boolean;
  paginacao?: {
    pagina: number;
    porPagina: number;
    total: number;
    rotulo: string;
    onPagina: (p: number) => void;
  };
};

function paginas(atual: number, total: number): (number | '…')[] {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  const out: (number | '…')[] = [1];
  const lo = Math.max(2, atual - 1);
  const hi = Math.min(total - 1, atual + 1);
  if (lo > 2) out.push('…');
  for (let i = lo; i <= hi; i++) out.push(i);
  if (hi < total - 1) out.push('…');
  out.push(total);
  return out;
}

const Seta = ({ dir }: { dir: 'esq' | 'dir' }) => (
  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
    <polyline points={dir === 'esq' ? '15 18 9 12 15 6' : '9 18 15 12 9 6'} />
  </svg>
);

export default function GradeFabricacao<T>({
  caption,
  colunas,
  linhas,
  idDe,
  rotuloDe,
  ordem,
  onOrdenar,
  selecao,
  onLinha,
  clicavel,
  paginacao,
}: Props<T>) {
  const cabecalhoChk = useRef<HTMLInputElement>(null);
  useEffect(() => {
    if (cabecalhoChk.current) cabecalhoChk.current.indeterminate = !!selecao && selecao.algumas && !selecao.todas;
  }, [selecao]);

  const nPaginas = paginacao ? Math.max(1, Math.ceil(paginacao.total / paginacao.porPagina)) : 1;
  const atual = paginacao ? Math.min(paginacao.pagina, nPaginas) : 1;

  return (
    <div className="mfg-dg">
      <div className="mfg-dg-scroll">
        <table>
          <caption className="sr-only">{caption}</caption>
          <thead>
            <tr>
              {selecao && (
                <th scope="col" className="chk">
                  <input
                    ref={cabecalhoChk}
                    type="checkbox"
                    checked={selecao.todas}
                    onChange={(e) => selecao.onAlternarTodas(e.target.checked)}
                    aria-label="Selecionar todas"
                  />
                </th>
              )}
              {colunas.map((c) => {
                const ativa = ordem?.k === c.key;
                return (
                  <th
                    key={c.key}
                    scope="col"
                    className={c.align === 'right' ? 'r' : undefined}
                    aria-sort={ativa ? (ordem?.dir === 'desc' ? 'descending' : 'ascending') : undefined}
                  >
                    {c.sortable && onOrdenar ? (
                      <button type="button" className={ativa ? 'act' : undefined} onClick={() => onOrdenar(c.key)}>
                        {c.label}
                        <span className="ind" aria-hidden>
                          {ativa ? (ordem?.dir === 'desc' ? '↓' : '↑') : '↕'}
                        </span>
                      </button>
                    ) : (
                      c.label
                    )}
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody>
            {linhas.map((l) => {
              const id = idDe(l);
              const sel = !!selecao && selecao.ids.includes(id);
              const clic = !!onLinha && (clicavel ? clicavel(l) : true);
              return (
                <tr
                  key={id}
                  className={[clic ? 'clic' : '', sel ? 'sel' : ''].filter(Boolean).join(' ') || undefined}
                  role={clic ? 'button' : undefined}
                  tabIndex={clic ? 0 : undefined}
                  onClick={clic ? () => onLinha!(l) : undefined}
                  onKeyDown={
                    clic
                      ? (e) => {
                          if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            onLinha!(l);
                          }
                        }
                      : undefined
                  }
                >
                  {selecao && (
                    // Clicar no checkbox não abre a gaveta (§4.2).
                    <td className="chk" onClick={(e) => e.stopPropagation()}>
                      <input
                        type="checkbox"
                        checked={sel}
                        onChange={() => selecao.onAlternar(id)}
                        aria-label={`Selecionar ${rotuloDe ? rotuloDe(l) : id}`}
                      />
                    </td>
                  )}
                  {colunas.map((c) => (
                    <td key={c.key} className={[c.align === 'right' ? 'r' : '', c.mono ? 'mono' : ''].filter(Boolean).join(' ') || undefined}>
                      {c.render(l)}
                    </td>
                  ))}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      {paginacao && (
        <div className="mfg-dg-foot">
          <nav aria-label="Paginação" className="mfg-dg-pag">
            <div className="bts">
              <button type="button" className="edge" disabled={atual <= 1} aria-label="Anterior" onClick={() => paginacao.onPagina(atual - 1)}>
                <Seta dir="esq" />
              </button>
              {paginas(atual, nPaginas).map((p, i) =>
                p === '…' ? (
                  <span key={`e${i}`} className="ell">
                    …
                  </span>
                ) : (
                  <button
                    type="button"
                    key={p}
                    aria-current={p === atual ? 'page' : undefined}
                    onClick={() => paginacao.onPagina(p)}
                  >
                    {p}
                  </button>
                ),
              )}
              <button type="button" className="edge" disabled={atual >= nPaginas} aria-label="Próxima" onClick={() => paginacao.onPagina(atual + 1)}>
                <Seta dir="dir" />
              </button>
            </div>
            <span className="meta">
              <b>
                {paginacao.total === 0 ? 0 : (atual - 1) * paginacao.porPagina + 1}–
                {Math.min(atual * paginacao.porPagina, paginacao.total)}
              </b>{' '}
              de {paginacao.total} {paginacao.rotulo}
            </span>
          </nav>
        </div>
      )}
    </div>
  );
}
