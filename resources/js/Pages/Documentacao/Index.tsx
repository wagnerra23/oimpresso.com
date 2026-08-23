// Documentacao/Index — carimbado do PT-03 Detalhe por criar-tela.mjs (UI-0013).
// F3 PENDENTE: rail, TOC e cabeçalho canon ainda não estão aqui. O que já está: o payload
// real do controller e o contrato de que o HTML vem pronto do servidor.
//
// SEM FsmActionPanel de propósito — mesma razão do Doc.tsx: tela de leitura não tem estado a
// transicionar, "read-only" é Non-Goal declarado por [W] em 2026-08-06 (Index.charter.md), e o
// import do arquétipo apontava pra um componente inexistente, quebrando o build do app inteiro.
import AppShellV2 from '@/Layouts/AppShellV2';

interface ItemSumario {
  id: string;
  titulo: string;
  nivel: number;
}

interface Props {
  html: string;
  sumario: ItemSumario[];
  fonte: string;
  atualizadoEm: string | null;
  buscaDisponivel: boolean;
  nav: Record<string, unknown>;
  atual: string | null;
  escopo: { tipos: string[]; prosa: string };
}

export default function Index({ html, fonte, atualizadoEm }: Props) {
  return (
    <AppShellV2>
      {/* TODO F3: rail derivado (nav) + lentes + TOC (sumario) + oferta de busca
          condicionada a `buscaDisponivel`, conforme PT-03. */}
      <article>
        {/* O HTML vem convertido e sanitizado do SERVIDOR — o cliente não roda parser de
            markdown (AR-DOC-003, Non-Goal do charter). */}
        <div dangerouslySetInnerHTML={{ __html: html }} />
        <footer>
          <span>{fonte}</span>
          {atualizadoEm ? <span> · atualizado em {atualizadoEm}</span> : null}
        </footer>
      </article>
    </AppShellV2>
  );
}
