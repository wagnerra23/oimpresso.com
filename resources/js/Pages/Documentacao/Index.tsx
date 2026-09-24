// Documentacao/Index — a capa de /documentacao: o Guia do Sistema renderizado, com rail e sumário.
//
// Migração Blade→Inertia (US-DOC-001). O contrato é a lista de paridade
// memory/requisitos/Documentacao/ANTI-REGRESSAO-documentacao-blade.md (AR-DOC-001..014); a forma
// vem do protótipo prototipo-ui/cowork/Wagner/documentacao-page.jsx.
//
// Read-only de propósito (Non-Goal do charter): nada aqui grava, e por isso não há FsmActionPanel.
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import DocRail from './_components/DocRail';
import LenteBar from './_components/LenteBar';
import DocSumario from './_components/DocSumario';
import type { ItemSumario, Navegacao } from './_components/tipos';
import '../../../css/cowork-documentacao-bundle.css';

interface Props {
  /** HTML já convertido e sanitizado NO SERVIDOR — o cliente não roda parser de markdown (AR-DOC-003). */
  html: string;
  sumario: ItemSumario[];
  fonte: string;
  blob: string;
  atualizadoEm: string | null;
  buscaDisponivel: boolean;
  nav: Navegacao;
  atual: string | null;
  escopo: { tipos: string[]; prosa: string };
}

export default function Index({ html, sumario, fonte, blob, atualizadoEm, buscaDisponivel, nav, atual, escopo }: Props) {
  return (
    <AppShellV2 title="Documentação" breadcrumbItems={[{ label: 'Documentação' }]}>
      <div className="doc-page">
        <PageHeader
          title="Documentação do sistema"
          subtitle="A página é o documento renderizado a cada acesso — não uma cópia dele."
          actions={
            <Button asChild variant="outline" size="sm">
              <a href={blob} target="_blank" rel="noopener noreferrer">Ver fonte no git</a>
            </Button>
          }
          below={<LenteBar nav={nav} path="/documentacao" />}
        />

        <div className="doc-wrap">
          <DocRail nav={nav} atual={atual} escopoProsa={escopo.prosa} buscaDisponivel={buscaDisponivel} />

          <main className="doc-main">
            <div className="doc-meta">
              <span className="m">{fonte}</span>
              {atualizadoEm ? <span className="m">atualizado {atualizadoEm}</span> : null}
              <span className="m">renderizado a cada acesso</span>
              {buscaDisponivel ? null : <span className="m">busca: índice indisponível neste ambiente</span>}
            </div>

            {/* Tabela larga rola sozinha; a coluna de leitura nunca rola de lado. */}
            <div
              className="doc-body prose prose-sm dark:prose-invert max-w-none [&_table]:block [&_table]:overflow-x-auto"
              dangerouslySetInnerHTML={{ __html: html }}
            />

            <div className="doc-src">
              Fonte dona deste texto:{' '}
              <a href={blob} target="_blank" rel="noopener noreferrer">{fonte}</a>
              <span> · </span>
              {buscaDisponivel
                ? `para o resto do acervo, use a busca — ela cobre ${escopo.prosa}`
                : 'alterou a fonte por PR? a página muda no próximo acesso'}
            </div>
          </main>

          <DocSumario sumario={sumario}>
            <div className="doc-aside-card">
              <div className="k">fonte</div>
              <div className="v">{fonte}</div>
            </div>
          </DocSumario>
        </div>
      </div>
    </AppShellV2>
  );
}
