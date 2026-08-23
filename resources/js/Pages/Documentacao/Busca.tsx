// Documentacao/Busca — carimbado do PT-01 Lista por criar-tela.mjs (UI-0013).
// F3 PENDENTE: rail, filtros e a lista canon ainda não estão aqui. O que já está: o payload
// real do controller e os dois estados que o contrato de paridade distingue.
//
// SEM DataTable por enquanto. O arquétipo PT-01 traz `<DataTable>`, que exige `endpoint` —
// ele pagina do servidor. Esta busca NÃO pagina por endpoint: o controller já devolve até
// `POR_PAGINA` resultados ordenados por relevância, num payload só. Plugar o DataTable aqui
// exigiria inventar um endpoint que não existe, então a ligação certa fica pra F3, junto com
// a decisão de paginar ou não (AR-DOC-024).
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';

interface Resultado {
  slug: string;
  type: string;
  module: string | null;
  title: string;
  git_path: string;
  trecho: string;
}

interface Props {
  termo: string;
  resultados: Resultado[];
  /** Corpus inacessível. NÃO é "nada encontrado" — os dois estados são distintos de
   *  propósito (AR-DOC-021): colapsar faz alguém concluir que o documento não existe. */
  indisponivel: boolean;
  nav: Record<string, unknown>;
  atual: string | null;
  escopo: { tipos: string[]; prosa: string };
}

export default function Busca({ termo, resultados, indisponivel, escopo }: Props) {
  return (
    <AppShellV2>
      <PageHeader title="Busca na documentação" description={`Cobre ${escopo.prosa}.`} />

      {/* TODO F3: rail derivado (nav) + faceta de módulo + destaque do termo no trecho. */}
      {indisponivel ? (
        <p>Índice indisponível no momento — não foi possível procurar.</p>
      ) : (
        <ul>
          {resultados.map((r) => (
            <li key={r.slug}>
              <a href={`/documentacao/${r.slug}`}>{r.title}</a>
              <span> · {r.type}</span>
              {r.module ? <span> · {r.module}</span> : null}
              <p>{r.trecho}</p>
            </li>
          ))}
          {resultados.length === 0 && termo !== '' ? <li>Nada encontrado para “{termo}”.</li> : null}
        </ul>
      )}
    </AppShellV2>
  );
}
