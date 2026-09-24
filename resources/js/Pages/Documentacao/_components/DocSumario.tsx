import { useEffect, useState, type ReactNode } from 'react';
import type { ItemSumario } from './tipos';

/**
 * "Nesta página" — sumário DERIVADO dos títulos pelo servidor (`comSumario()`), recalculado a
 * cada acesso (AR-DOC-004). O realce da seção lida é conforto: sem JS continua um sumário de
 * links que funciona.
 *
 * `children` são os cartões de metadados abaixo do sumário (fonte, data), como no protótipo.
 */
export default function DocSumario({ sumario, children }: { sumario: ItemSumario[]; children?: ReactNode }) {
  const [ativo, setAtivo] = useState<string | null>(sumario[0]?.id ?? null);

  useEffect(() => {
    if (typeof IntersectionObserver === 'undefined') return;
    const alvos = sumario
      .map((s) => document.getElementById(s.id))
      .filter((el): el is HTMLElement => el !== null);
    if (alvos.length === 0) return;

    const ob = new IntersectionObserver(
      (entradas) => entradas.forEach((e) => e.isIntersecting && setAtivo(e.target.id)),
      { rootMargin: '-10% 0px -75% 0px' },
    );
    alvos.forEach((el) => ob.observe(el));
    return () => ob.disconnect();
  }, [sumario]);

  return (
    <aside className="doc-aside">
      {sumario.length > 0 && (
        <>
          <div className="doc-aside-h">Nesta página</div>
          <nav className="doc-toc" aria-label="Sumário do documento">
            {sumario.map((s) => (
              <a
                key={s.id}
                href={`#${s.id}`}
                className={ativo === s.id ? 'on' : undefined}
                style={s.nivel === 4 ? { paddingLeft: 18 } : undefined}
              >
                {s.codigo ? <b>{s.codigo} </b> : null}
                {s.rotulo}
              </a>
            ))}
          </nav>
        </>
      )}
      {children}
    </aside>
  );
}
