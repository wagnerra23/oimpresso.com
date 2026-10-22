// @memcofre
//   tela: /producao/roteiros
//   module: Producao
//   stories: playbook Produção thread 03 (UC-PCV-13) · US-PCP-026 (UC-FLX-07, produtos do roteiro)
//   permissao: producao.access
//
// Roteiros por família de produto. Âncora de design: alvo producao--roteiros--index (A1) ·
// prototipo-ui/cowork/Wagner/producao-cv-os.jsx → Roteiros(). Classes do bundle
// `cowork-producao-bundle.css` (cópia inteira do producao-cv.css).
//
// Regra (UC-PCV-13, validada no servidor por SalvarRoteiroRequest): até 2 trilhas em paralelo,
// que sempre se juntam numa etapa nomeada; etapa opcional liga pela opção do orçamento.
import '../../../../../../../resources/css/cowork-producao-bundle.css';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { Fragment, useState, type CSSProperties, type ReactNode } from 'react';
import { Info, Pencil, Plus } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import ProducaoAbas from '../_components/ProducaoAbas';
import EditorRoteiro, { type Etapa, type Item, type Posto, type Roteiro } from './_components/EditorRoteiro';
import ProdutosDoRoteiro from './_components/ProdutosDoRoteiro';

interface Props { roteiros?: Roteiro[]; postos?: Posto[] }

const ehParalelo = (i: Item): i is Extract<Item, { paralelo: Etapa[][] }> => 'paralelo' in i;

function Passo({ p, n, postos }: { p: Etapa; n: number; postos: Posto[] }) {
  const posto = postos.find((x) => x.id === p.posto_id);
  return (
    <li className={'is-fila' + (p.opcional_por ? ' is-opc' : '')} style={{ '--c': 'var(--accent)' } as CSSProperties}>
      <span className="pcv-et-n" aria-hidden="true">{n}</span>
      <div className="pcv-et-m"><b>{p.nome}</b>
        <small>{[posto?.setor, posto?.nome, p.opcional_por ? 'só ' + p.opcional_por : null].filter(Boolean).join(' · ') || '—'}</small></div>
      <div className="pcv-et-t mono">{p.horas ? `${Number(p.horas).toLocaleString('pt-BR')} h` : ''}</div>
    </li>
  );
}

function Fluxo({ r, postos }: { r: Roteiro; postos: Posto[] }) {
  let n = 0;
  return (
    <ol className="pcv-etapas pcv-rot-fluxo">
      {r.etapas.map((item, k) => ehParalelo(item)
        ? <li key={k} className="pcv-par" aria-label={`Em paralelo, junta em ${item.junta}`}>
            <span className="pcv-par-h">Em paralelo — as duas trilhas andam ao mesmo tempo e se juntam em <b>{item.junta}</b></span>
            <div className="pcv-par-g">{item.paralelo.map((t, ti) => (
              <ol key={ti} className="pcv-etapas" aria-label={`Trilha ${ti + 1}`}>{t.map((q, qi) => <Passo key={qi} p={q} n={++n} postos={postos} />)}</ol>
            ))}</div>
          </li>
        : <Passo key={k} p={item} n={++n} postos={postos} />)}
    </ol>
  );
}

function Conteudo({ roteiros, postos }: { roteiros: Roteiro[]; postos: Posto[] }) {
  const [sel, setSel] = useState<number | null>(roteiros[0]?.id ?? null);
  const [editando, setEditando] = useState<Roteiro | 'novo' | null>(null);
  const r = roteiros.find((x) => x.id === sel) ?? roteiros[0];
  const familias = [...new Set(roteiros.map((x) => x.familia))];

  return (
    <div className="pcv-body pcv-rot">
      <nav className="pcv-rot-l" aria-label="Roteiros por família de produto">
        {familias.map((f) => (
          <Fragment key={f}><span className="pcv-eyebrow pcv-rot-fam">{f}</span>
            {roteiros.filter((x) => x.familia === f).map((x) => (
              <button key={x.id} type="button" aria-current={r?.id === x.id ? 'true' : undefined} onClick={() => setSel(x.id)}>
                <b>{x.nome}</b><small>{x.etapas.some(ehParalelo) ? 'com etapas em paralelo' : 'em sequência'}</small>
              </button>
            ))}
          </Fragment>
        ))}
        <Button variant="outline" size="sm" onClick={() => setEditando('novo')}><Plus className="size-3.5" aria-hidden="true" /> Novo roteiro</Button>
      </nav>
      <div className="pcv-rot-r">
        {r ? (
          <Card data-contract="producao-roteiro">
            <CardContent className="flex flex-col gap-3 p-4">
              <div className="flex items-start gap-3">
                <div className="min-w-0 flex-1"><h2 className="pcv-h2">{r.nome}</h2>
                  <p className="pcv-help">{r.familia} · vira as etapas da OS quando o orçamento é aprovado</p></div>
                <Button size="sm" variant="outline" onClick={() => setEditando(r)}><Pencil className="size-3.5" aria-hidden="true" /> Ajustar roteiro</Button>
              </div>
              <Fluxo r={r} postos={postos} />
              <p className="pcv-help">Tempo de máquina = quantidade ÷ capacidade do posto. Etapa marcada “só com…” entra na OS quando a opção vem marcada no orçamento.</p>
              <ProdutosDoRoteiro roteiroId={r.id} produtos={r.produtos ?? []} />
            </CardContent>
          </Card>
        ) : (
          <Card><CardContent className="p-6 text-sm text-muted-foreground">Nenhum roteiro ainda. Comece pela família que você mais produz.</CardContent></Card>
        )}
        <Alert>
          <Info aria-hidden="true" />
          <AlertTitle>Por que no máximo duas trilhas</AlertTitle>
          <AlertDescription>Linha reta demais faz a impressão esperar a serralheria sem motivo. Fluxograma livre demais ninguém treina. O meio-termo: famílias prontas, até 2 trilhas que sempre se juntam, e opcionais ligados pelo orçamento. O operador nunca vê o roteiro inteiro — só “minhas etapas de hoje”.</AlertDescription>
        </Alert>
      </div>
      {editando && <EditorRoteiro roteiro={editando === 'novo' ? null : editando} postos={postos} onClose={() => setEditando(null)} />}
    </div>
  );
}

export default function RoteirosIndex({ roteiros, postos }: Props) {
  return (
    <div className="pcv-root" data-screen-label="Produção · Roteiros">
      <div className="pcv-ph"><PageHeader title="Produção" suffix=" · Roteiros" below={<ProducaoAbas ativa="/producao/roteiros" />} /></div>
      <Deferred data={['roteiros', 'postos']} fallback={<div className="pcv-body"><Skeleton className="h-64 w-full" /></div>}>
        <Conteudo roteiros={roteiros ?? []} postos={postos ?? []} />
      </Deferred>
    </div>
  );
}

RoteirosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
