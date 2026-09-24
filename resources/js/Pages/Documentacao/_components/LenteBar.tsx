import { Link } from '@inertiajs/react';
import type { Navegacao } from './tipos';

/**
 * Lentes da documentação — faixa própria abaixo do PageHeader (protótipo
 * `documentacao-page.jsx`, `.doc-lentebar`).
 *
 * A lente é decidida no SERVIDOR (query manda, cookie lembra — `lenteAtiva()`), então cada
 * aba é navegação GET, não estado do cliente: a URL continua compartilhável (AR-DOC-013).
 *
 * "Tudo" manda `?lente=` VAZIO, não omite o parâmetro. Omitido, o servidor cairia no cookie e
 * devolveria a lente lembrada — "Tudo" não desfaria a preferência. Vazio é "escolha explícita
 * e inválida", que o controller converte em null e usa pra apagar o cookie.
 */
export default function LenteBar({ nav, path }: { nav: Navegacao; path: string }) {
  const abas: Array<[string, string]> = [['', 'Tudo'], ...Object.entries(nav.lentes)];

  return (
    <nav className="doc-lentebar fallback" aria-label="Lente de leitura">
      {abas.map(([chave, rotulo]) => {
        const ativa = (nav.lente ?? '') === chave;
        return (
          <Link
            key={chave || 'tudo'}
            as="button"
            href={`${path}?lente=${chave}`}
            className={ativa ? 'on' : undefined}
            aria-current={ativa ? 'true' : undefined}
          >
            {rotulo}
          </Link>
        );
      })}
    </nav>
  );
}
