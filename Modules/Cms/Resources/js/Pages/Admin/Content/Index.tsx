// @memcofre
//   tela: /cms/cms-page
//   module: Cms
//   stories: US-CMS-004 (Blade/AdminLTE → Inertia) — thread Cms/01, fase 1 (lista)
//   permissao: superadmin
//
// Todo o conteúdo do site num lugar só: páginas, blog e depoimentos.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md
// RUNBOOK: memory/requisitos/Cms/RUNBOOK-admin-content.md
// Âncora de design: prototipo-ui/cowork/Wagner/cowork-inbox/cms/CMS-F1-2026-08-19.md (PT-01)
//
// Fase 1 = a LISTA; fase 2 = criar/editar num drawer PT-02 (./_components/Editor.tsx) sem
// sair da tela. Excluir chama o `destroy` que já existe (responde JSON e só aceita ajax).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import Editor, { type Editando } from './_components/Editor';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';

import type { Tipo } from './_components/Editor';

interface Linha {
  id: number;
  titulo: string;
  prioridade: number | null;
  publicada: boolean;
  sistema: boolean;
  sem_descricao: boolean;
  criada_em: string | null;
  endereco: string | null;
}

interface Props {
  tipo: Tipo;
  contagens: Record<Tipo, number>;
  paginas?: Linha[];
  /** Só chega por partial reload (?editar=id) — ver `abrirEdicao`. */
  editando?: Editando | null;
}

// A1 do F1: nada de enum cru na interface.
const ROTULO: Record<Tipo, string> = { page: 'Páginas', blog: 'Blog', testimonial: 'Depoimentos' };
const NOVO: Record<Tipo, string> = { page: 'Nova página', blog: 'Nova publicação', testimonial: 'Novo depoimento' };
const BASE = '/cms/cms-page';

function ConteudoIndex({ tipo, contagens, paginas, editando }: Props) {
  // `false` = fechado · `null` = criando · número = editando essa linha.
  const [drawer, setDrawer] = useState<false | null | number>(false);

  function abrirEdicao(id: number) {
    router.reload({ data: { editar: id }, only: ['editando'], onSuccess: () => setDrawer(id) });
  }

  const item = typeof drawer === 'number' && editando?.id === drawer ? editando : null;

  return (
    <div className="pb-8">
      {/* PageHeader canon v3 (ADR 0189/0190): abas na Zona C, primary na Zona R. */}
      <PageHeader
        title="Conteúdo do site"
        subtitle="O que está no ar em oimpresso.com — páginas, blog e depoimentos"
        subnav={<Abas tipo={tipo} contagens={contagens} />}
        actions={<PageHeaderPrimary label={NOVO[tipo]} onClick={() => setDrawer(null)} />}
      />

      <div className="px-6 pt-3" data-contract="cms.content.lista">
        <Deferred data="paginas" fallback={<Skeleton className="h-64 w-full" />}>
          <Lista tipo={tipo} linhas={paginas} onEditar={abrirEdicao} />
        </Deferred>
      </div>

      {/* `key` remonta o form a cada item: useForm só lê os valores iniciais uma vez. */}
      <Editor key={`${tipo}-${item?.id ?? 'novo'}`} tipo={tipo} item={item}
        aberto={drawer === null || item !== null} onFechar={() => setDrawer(false)} />
    </div>
  );
}

function Abas({ tipo, contagens }: { tipo: Tipo; contagens: Record<Tipo, number> }) {
  return (
    <nav className="flex gap-1" aria-label="Tipo de conteúdo" data-contract="cms.content.abas">
      {(Object.keys(ROTULO) as Tipo[]).map((t) => (
        <Link
          key={t}
          href={`${BASE}?type=${t}`}
          aria-current={t === tipo ? 'page' : undefined}
          className={
            'rounded-md px-3 py-1.5 text-sm ' +
            (t === tipo ? 'bg-muted font-medium' : 'text-muted-foreground hover:bg-muted/60')
          }
        >
          {ROTULO[t]} <span className="tabular-nums text-muted-foreground">{contagens[t] ?? 0}</span>
        </Link>
      ))}
    </nav>
  );
}

function Lista({ tipo, linhas, onEditar }: { tipo: Tipo; linhas?: Linha[]; onEditar: (id: number) => void }) {

  const lista = linhas ?? [];
  const [erro, setErro] = useState<string | null>(null);

  if (lista.length === 0) {
    return (
      <Card>
        <CardContent className="p-0">
          <EmptyState
            title={`Nenhum item em ${ROTULO[tipo]}`}
            description="O que for cadastrado aqui aparece no site público assim que estiver publicado."
          />
        </CardContent>
      </Card>
    );
  }

  async function excluir(l: Linha) {
    if (!window.confirm(`Excluir "${l.titulo}"? A imagem de destaque também é apagada.`)) return;
    setErro(null);
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const resp = await fetch(`${BASE}/${l.id}?type=${tipo}`, {
      method: 'DELETE',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
    });
    const corpo = resp.ok ? await resp.json().catch(() => null) : null;
    if (corpo?.success) {
      router.reload({ only: ['paginas', 'contagens'] });
    } else {
      setErro('Não foi possível excluir. Nada foi alterado.');
    }
  }

  return (
    <Card>
      <CardContent className="p-0">
        {erro && <p role="alert" className="border-b px-4 py-2 text-sm text-destructive">{erro}</p>}
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-muted-foreground">
            <tr>
              <th className="px-4 py-2 font-medium">Título</th>
              <th className="px-4 py-2 font-medium">Situação</th>
              <th className="px-4 py-2 text-right font-medium">Ordem</th>
              <th className="px-4 py-2 font-medium">Criada em</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody>
            {lista.map((l) => (
              <tr key={l.id} className="border-b last:border-0">
                <td className="px-4 py-2">
                  <div className="font-medium">{l.titulo}</div>
                  {l.endereco && <div className="text-xs text-muted-foreground">{l.endereco}</div>}
                </td>
                <td className="px-4 py-2">
                  <div className="flex flex-wrap gap-1">
                    <Badge variant={l.publicada ? 'default' : 'secondary'}>{l.publicada ? 'Publicada' : 'Rascunho'}</Badge>
                    {l.sistema && <Badge variant="outline">Página de sistema</Badge>}
                    {l.sem_descricao && <Badge variant="outline">Sem descrição de busca</Badge>}
                  </div>
                </td>
                <td className="px-4 py-2 text-right tabular-nums">{l.prioridade ?? '—'}</td>
                <td className="px-4 py-2 tabular-nums text-muted-foreground">
                  {l.criada_em ? new Date(l.criada_em).toLocaleDateString('pt-BR') : '—'}
                </td>
                <td className="px-4 py-2 text-right whitespace-nowrap">
                  <Button variant="ghost" size="sm" onClick={() => onEditar(l.id)}>
                    Editar
                  </Button>
                  {l.sistema ? (
                    <span className="px-2 text-xs text-muted-foreground" title="Página de sistema não pode ser excluída">
                      Fixa
                    </span>
                  ) : (
                    <Button variant="ghost" size="sm" onClick={() => excluir(l)}>
                      Excluir
                    </Button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </CardContent>
    </Card>
  );
}

ConteudoIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ConteudoIndex;
