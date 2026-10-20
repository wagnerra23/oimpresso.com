// Documentacao/CasosDeUso — os casos de uso do catálogo de fluxos (UC-FLX-NN) na documentação.
//
// DERIVADO, não escrito: a lista chega do servidor, lida de CasosDoPrototipo::CASOS (a mesma
// constante que a aba "Casos de uso" de /fluxos usa). Nada aqui repete id, título, regra, estado
// ou US — só layout e filtro. Charter: CasosDeUso.charter.md · casos: CasosDeUso.casos.md.
// Os links para SPEC e `.casos.md` chegam em `artefatos` (deferred), derivados da árvore do repo
// por RastroDosCasos — a tela só troca o id da US pelo link que veio do servidor.
import { useMemo, useState, type ReactNode } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { Inline } from '@/Components/layout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import DocRail from './_components/DocRail';
import LenteBar from './_components/LenteBar';
import type { Navegacao } from './_components/tipos';
import '../../../css/cowork-documentacao-bundle.css';

interface Caso {
  id: string;
  titulo: string;
  processo: string;
  etapa: string;
  regra: string;
  fontes: string[];
  porte: string;
  estado: string;
  estadoRotulo: string;
  estadoTom: string;
  us: string;
}

interface Artefato {
  rotulo: string;
  path: string;
  href: string;
  interno: boolean;
}

interface Rastro {
  us: { id: string; docs: Artefato[] }[];
  documentado: Artefato[];
}

interface Props {
  casos: Caso[];
  /** Deferred (Inertia::defer): `undefined` = ainda carregando; a US aparece como texto até lá. */
  artefatos?: Record<string, Rastro>;
  processos: string[];
  estados: Record<string, { rotulo: string; tom: string }>;
  fonte: string;
  blob: string;
  buscaDisponivel: boolean;
  nav: Navegacao;
  atual: string | null;
  escopo: { tipos: string[]; prosa: string };
}

const VARIANTE: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = {
  ok: 'success',
  accent: 'info',
  warn: 'warning',
  danger: 'danger',
  mute: 'neutral',
};
const TODOS = '__todos__';
// Mesmo padrão de id que o servidor usa (RastroDosCasos::RE_US) — só para recortar o texto.
const RE_US = /(US-[A-Z]+(?:-NEW)?-\d+)/;

function LinkArtefato({ a, children }: { a: Artefato; children?: ReactNode }) {
  return (
    <a
      href={a.href}
      title={a.path}
      className="underline underline-offset-2"
      {...(a.interno ? {} : { target: '_blank', rel: 'noopener noreferrer' })}
    >
      {children ?? a.rotulo}
    </a>
  );
}

/** O texto da US, com cada id que o servidor resolveu virando link para o SPEC dele. */
function TextoUs({ texto, rastro }: { texto: string; rastro?: Rastro }) {
  if (!texto) return <>—</>;
  const porId = new Map((rastro?.us ?? []).map((u) => [u.id, u.docs]));
  return (
    <>
      {texto.split(RE_US).map((parte, i) => {
        const docs = porId.get(parte);
        if (!docs || docs.length === 0) return <span key={i}>{parte}</span>;
        return (
          <span key={i}>
            {docs.map((d, j) => (
              <span key={d.path}>
                {j > 0 ? ' · ' : null}
                <LinkArtefato a={d}>{j === 0 ? parte : d.rotulo}</LinkArtefato>
              </span>
            ))}
          </span>
        );
      })}
    </>
  );
}

export default function CasosDeUso({ casos, artefatos, processos, estados, fonte, blob, buscaDisponivel, nav, atual, escopo }: Props) {
  const [processo, setProcesso] = useState<string>(TODOS);
  const [estado, setEstado] = useState<string>(TODOS);

  const visiveis = useMemo(
    () => casos.filter((c) => (processo === TODOS || c.processo === processo) && (estado === TODOS || c.estado === estado)),
    [casos, processo, estado],
  );

  return (
    <AppShellV2
      title="Casos de uso"
      breadcrumbItems={[{ label: 'Documentação', href: '/documentacao' }, { label: 'Casos de uso' }]}
    >
      <div className="doc-page">
        <div data-contract="doc-cabecalho">
          <PageHeader
            title="Casos de uso do catálogo de fluxos"
            subtitle="Lidos do catálogo a cada acesso — o estado de cada caso nunca é copiado para texto."
            actions={
              <Button asChild variant="outline" size="sm">
                <a href={blob} target="_blank" rel="noopener noreferrer">Ver fonte no git</a>
              </Button>
            }
            below={<div data-contract="doc-lentebar"><LenteBar nav={nav} path="/documentacao/casos-de-uso" /></div>}
          />
        </div>

        <div className="doc-wrap" data-contract="doc-corpo">
          <DocRail nav={nav} atual={atual} escopoProsa={escopo.prosa} buscaDisponivel={buscaDisponivel} />

          <main className="doc-main">
            <div className="doc-meta">
              <span className="m">{fonte}</span>
              <span className="m" data-contract="casos-contagem">{visiveis.length} de {casos.length} casos</span>
              <span className="m">renderizado a cada acesso</span>
            </div>

            <div className="mb-3" data-contract="casos-filtros">
              <Inline gap={2} wrap>
                <Select value={processo} onValueChange={setProcesso}>
                  <SelectTrigger size="sm" aria-label="Filtrar por processo"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value={TODOS}>Todos os processos</SelectItem>
                    {processos.map((p) => <SelectItem key={p} value={p}>{p}</SelectItem>)}
                  </SelectContent>
                </Select>
                <Select value={estado} onValueChange={setEstado}>
                  <SelectTrigger size="sm" aria-label="Filtrar por estado"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value={TODOS}>Todos os estados</SelectItem>
                    {Object.entries(estados).map(([k, e]) => <SelectItem key={k} value={k}>{e.rotulo}</SelectItem>)}
                  </SelectContent>
                </Select>
              </Inline>
            </div>

            <div className="overflow-x-auto" data-contract="casos-tabela">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-xs text-muted-foreground">
                    <th className="py-2 pr-3">Caso</th>
                    <th className="py-2 pr-3">Processo · etapa</th>
                    <th className="py-2 pr-3">Regra</th>
                    <th className="py-2 pr-3">Estado</th>
                    <th className="py-2 pr-3">US</th>
                    <th className="py-2">Documentado em</th>
                  </tr>
                </thead>
                <tbody>
                  {visiveis.map((c) => (
                    <tr key={c.id} className="border-b align-top" data-caso={c.id}>
                      <td className="py-2 pr-3">
                        <div className="font-mono text-xs text-muted-foreground">{c.id}</div>
                        <div className="font-medium">{c.titulo}</div>
                      </td>
                      <td className="py-2 pr-3 whitespace-nowrap">{c.processo} · {c.etapa}</td>
                      <td className="py-2 pr-3">{c.regra}</td>
                      <td className="py-2 pr-3 whitespace-nowrap">
                        <Badge variant={VARIANTE[c.estadoTom] ?? 'neutral'}>{c.estadoRotulo}</Badge>
                      </td>
                      <td className="py-2 pr-3 text-xs" data-contract="caso-us"><TextoUs texto={c.us} rastro={artefatos?.[c.id]} /></td>
                      <td className="py-2 text-xs" data-contract="caso-documentado">
                        {artefatos === undefined ? (
                          '…'
                        ) : (artefatos[c.id]?.documentado ?? []).length === 0 ? (
                          '—'
                        ) : (
                          (artefatos[c.id]?.documentado ?? []).map((d) => (
                            <div key={d.path}><LinkArtefato a={d} /></div>
                          ))
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {visiveis.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">Nenhum caso com esses filtros.</p>
              ) : null}
            </div>

            <div className="doc-src">
              Fonte dona destes casos:{' '}
              <a href={blob} target="_blank" rel="noopener noreferrer">{fonte}</a>
              <span> · exportada de fluxos-data.jsx; alterou o catálogo, a página muda no próximo acesso</span>
            </div>
          </main>
        </div>
      </div>
    </AppShellV2>
  );
}
