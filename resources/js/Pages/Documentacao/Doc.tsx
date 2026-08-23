// Documentacao/Doc — carimbado do PT-03 Detalhe por criar-tela.mjs (UI-0013).
// F3 PENDENTE: rail, TOC e cabeçalho canon ainda não estão aqui. O que já está: o payload
// real do controller e o contrato de que o HTML vem pronto do servidor.
//
// SEM FsmActionPanel de propósito. O arquétipo PT-03 traz o painel de ação FSM — é o que
// distingue Detalhe de Dashboard num registro com máquina de estados. Esta é tela de LEITURA:
// não há estado a transicionar, e "read-only" é Non-Goal declarado por [W] em 2026-08-06
// (Doc.charter.md). O import foi REMOVIDO, não comentado: apontava pra um componente
// inexistente e quebrava o build de todo o app, não só desta página.
import AppShellV2 from '@/Layouts/AppShellV2';

interface DocMeta {
  slug: string;
  title: string;
  type: string;
  module: string | null;
  git_path: string;
  git_sha: string | null;
  indexed_at: string | null;
  pii_redactions_count: number | null;
}

interface Props {
  doc: DocMeta;
  html: string;
  nav: Record<string, unknown>;
  atual: string | null;
  escopo: { tipos: string[]; prosa: string };
}

export default function Doc({ doc, html }: Props) {
  return (
    <AppShellV2>
      {/* TODO F3: rail derivado (nav) à esquerda + coluna de leitura + TOC, conforme PT-03. */}
      <article>
        <h1>{doc.title}</h1>
        {/* O HTML vem convertido e sanitizado do SERVIDOR — o cliente não roda parser de
            markdown (AR-DOC-003, Non-Goal do charter). */}
        <div dangerouslySetInnerHTML={{ __html: html }} />
      </article>
    </AppShellV2>
  );
}
