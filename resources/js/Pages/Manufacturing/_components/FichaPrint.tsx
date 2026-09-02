// FichaPrint — folha de prova PT-07 do handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §8.
// Porte de prototipo-ui/cowork/manufacturing-print.jsx (espelho == ZIP, conferido byte a byte).
//
// Duas variantes da MESMA folha, cada uma uma decisão de negócio:
//   · "Ficha com custo"  → orçamento/conferência: custo unitário, subtotais, quadro de total
//   · "Via de produção"  → bancada: ZERO valor de compra; a coluna de custo vira caixa de
//     conferência (`Separado ☐`). R-22 do handoff: nenhuma ocorrência de `R$` na folha.
//
// Mecanismo: portal no <body> + `@media print` do bundle esconde tudo que não for
// `.mfg-print-host`. `window.print()` 120ms após montar; `afterprint` fecha.
// O CSS de impressão vive em resources/css/cowork-manufacturing-bundle.css (bloco @media print).

import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import type { Receita } from '../_lib/tipos';
import { fmt, num } from '../_lib/formato';

interface Props {
  /** Uma folha A4 por receita — a BulkBar imprime N de uma vez (R-23). */
  itens: Receita[];
  /** `true` = via de produção (sem nenhum valor de compra). */
  semCusto: boolean;
  onDone: () => void;
}

export default function FichaPrint({ itens, semCusto, onDone }: Props) {
  useEffect(() => {
    const fim = () => onDone();
    window.addEventListener('afterprint', fim);
    const t = window.setTimeout(() => window.print(), 120);
    return () => {
      window.removeEventListener('afterprint', fim);
      window.clearTimeout(t);
    };
  }, [onDone]);

  const emitida = new Date().toLocaleString('pt-BR');

  return createPortal(
    <div className="mfg-print-host">
      {itens.map((r) => (
        <section className="mfg-sheet" key={r.id}>
          <i className="cm tl" aria-hidden />
          <i className="cm tr" aria-hidden />
          <i className="cm bl" aria-hidden />
          <i className="cm br" aria-hidden />

          <header className="mfg-sheet-h">
            <svg className="mfg-reg" viewBox="0 0 48 48" aria-hidden>
              <circle cx="24" cy="24" r="15" fill="none" stroke="currentColor" strokeWidth="0.6" />
              <circle cx="24" cy="24" r="7" fill="none" stroke="currentColor" strokeWidth="0.6" />
              <path d="M24 2v14M24 32v14M2 24h14M32 24h14" stroke="currentColor" strokeWidth="0.6" />
            </svg>
            <div className="id">
              <span className="eyebrow">
                Office Impresso · Manufacturing{semCusto ? ' · via de produção' : ''}
              </span>
              <h1>{r.name}</h1>
              <p>
                {r.cat} / {r.sub} · lote de {num(r.qtd, 2)} {r.un}
              </p>
            </div>
            <dl className="stamp">
              <dt>Receita</dt>
              <dd>{r.sku}</dd>
              <dt>Produto</dt>
              <dd>{r.sku}</dd>
              <dt>Emitida em</dt>
              <dd>{emitida}</dd>
            </dl>
          </header>

          <div className="mfg-sheet-cotas">
            <div className="cota">
              <span className="l">Lote</span>
              <b>{num(r.qtd, 2)}</b>
              <i className="ln" aria-hidden />
              <small>{r.un}</small>
            </div>
            <div className="cota">
              <span className="l">Rendimento líquido</span>
              <b>{num(r.custos.qtd_liq, 2)}</b>
              <i className="ln" aria-hidden />
              <small>
                {r.un} · desperdício {num(r.waste, 0)}%
              </small>
            </div>
            {r.sub_un && r.sub_fator ? (
              <div className="cota">
                <span className="l">Sub-unidade de saída</span>
                <b>{num(r.custos.qtd_liq * r.sub_fator, 2)}</b>
                <i className="ln" aria-hidden />
                <small>{r.sub_un}</small>
              </div>
            ) : null}
            {semCusto ? (
              <div className="cota hi">
                <span className="l">Conferência</span>
                <b>{r.n_ingredientes}</b>
                <i className="ln" aria-hidden />
                <small>itens · separar tudo na bancada</small>
              </div>
            ) : (
              <div className="cota hi">
                <span className="l">Custo por {r.un}</span>
                <b>{fmt(r.custos.unit)}</b>
                <i className="ln" aria-hidden />
                <small>preço do ingrediente de hoje</small>
              </div>
            )}
          </div>

          <table className="mfg-sheet-t">
            <thead>
              <tr>
                <th>Ingrediente</th>
                <th className="r">Quantidade</th>
                <th>Unidade</th>
                <th className={semCusto ? 'sep' : 'r'}>{semCusto ? 'Separado' : 'Custo unit.'}</th>
                {!semCusto && <th className="r">Subtotal</th>}
              </tr>
            </thead>
            {r.grupos.map((g) => (
              <tbody key={g.g}>
                <tr className="grp">
                  <th colSpan={semCusto ? 4 : 5}>{g.g}</th>
                </tr>
                {g.itens.map((i) => (
                  <tr key={i.id}>
                    <td>
                      {i.nome}
                      <span className="eq"> {i.sku}</span>
                    </td>
                    <td className="r mono">{num(i.quantidade, i.quantidade < 1 ? 3 : 2)}</td>
                    <td className="dim">
                      {i.unidade}
                      {i.multiplicador > 1 ? (
                        <span className="eq">
                          {' '}
                          · equivale a {num(i.quantidade * i.multiplicador, 3)} {i.unidade_base}
                        </span>
                      ) : null}
                    </td>
                    <td className={semCusto ? '' : 'r mono'}>
                      {semCusto ? <i className="box" aria-hidden /> : fmt(i.custo_unitario)}
                    </td>
                    {!semCusto && <td className="r mono">{fmt(i.subtotal)}</td>}
                  </tr>
                ))}
              </tbody>
            ))}
          </table>

          <div className="mfg-sheet-close">
            <div className="assina">
              <div className="ass">
                <i aria-hidden />
                <span>Produção</span>
              </div>
              <div className="ass">
                <i aria-hidden />
                <span>Conferido por</span>
              </div>
              <p className="obs">Ficha de uso interno — não é documento fiscal.</p>
            </div>
            {!semCusto && (
              <table className="mfg-sheet-t tot">
                <tbody>
                  <tr>
                    <td>Ingredientes</td>
                    <td className="r mono">{fmt(r.custos.ingredientes)}</td>
                  </tr>
                  <tr>
                    <td>Custo extra</td>
                    <td className="r mono">{fmt(r.custos.extra)}</td>
                  </tr>
                  <tr className="big">
                    <td>Custo por {r.un}</td>
                    <td className="r mono">{fmt(r.custos.unit)}</td>
                  </tr>
                </tbody>
              </table>
            )}
          </div>

          <footer className="mfg-sheet-f">
            {/* Tira CMYK + escada de cinza (§8). As cores vivem no bundle
                (`.tinta-*` / `.cinza-*`), não em style inline — ver Adaptação 4. */}
            <span className="strip">
              {['tinta-c', 'tinta-m', 'tinta-y', 'tinta-k'].map((c) => (
                <i key={c} className={c} aria-hidden />
              ))}
            </span>
            <span className="strip d">
              {[0, 1, 2, 3, 4, 5, 6, 7].map((n) => (
                <i key={n} className={`cinza-${n}`} aria-hidden />
              ))}
            </span>
            <span>{r.sku}</span>
          </footer>
        </section>
      ))}
    </div>,
    document.body,
  );
}
