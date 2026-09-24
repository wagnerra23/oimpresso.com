import { useState, type FormEvent } from 'react';
import { Link, router } from '@inertiajs/react';
import type { Navegacao } from './tipos';

/**
 * Rail da documentação — itens DERIVADOS do frontmatter (`navegacao()` no controller). Não há
 * lista escrita aqui: doc sem `nav_group` não aparece (AR-DOC-010/011).
 *
 * O número à esquerda é o `ordinal` que vem do servidor — a ordem visível na lente ativa, sem
 * buraco (AR-DOC-012). Nunca recalcular aqui a partir de `nav_order`.
 *
 * `atual` null = a capa (ou o Programa): nenhum item vem ativo (AR-DOC-007 · AR-DOC-066).
 */
export default function DocRail({
  nav,
  atual,
  termo = '',
  escopoProsa,
}: {
  nav: Navegacao;
  atual: string | null;
  termo?: string;
  escopoProsa: string;
}) {
  const [q, setQ] = useState(termo);

  const buscar = (e: FormEvent) => {
    e.preventDefault();
    router.get('/documentacao/buscar', { q });
  };

  return (
    <aside className="doc-rail" aria-label="Documentos">
      <form role="search" onSubmit={buscar}>
        <input
          type="search"
          name="q"
          className="doc-rail-search"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Buscar na documentação…"
          aria-label={`Buscar em ${escopoProsa}`}
        />
      </form>

      <div className="doc-nav">
        <Link as="button" href="/documentacao" className={atual === null ? 'on' : undefined}
          aria-current={atual === null ? 'page' : undefined}>
          Comece aqui
        </Link>
      </div>

      {nav.grupos.map((grupo) => (
        <div className="doc-grp" key={grupo.id}>
          <div className="doc-grp-h">
            {grupo.titulo}
            <span>{grupo.itens.length}</span>
          </div>
          <div className="doc-nav">
            {grupo.itens.map((item) => {
              const ativo = atual === item.id;
              return (
                <Link
                  key={item.id}
                  as="button"
                  href={`/documentacao/${item.id}`}
                  className={ativo ? 'on' : undefined}
                  aria-current={ativo ? 'page' : undefined}
                  title={item.descricao ?? undefined}
                >
                  <i>{String(item.ordinal).padStart(2, '0')}</i>
                  {item.rotulo}
                </Link>
              );
            })}
          </div>
        </div>
      ))}
    </aside>
  );
}
