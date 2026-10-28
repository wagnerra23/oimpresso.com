// @memcofre
//   tela: /producao/painel
//   module: Producao
//   stories: playbook Produção thread 06 (UC-PCV-01, UC-PCV-02, UC-PCV-03, UC-PCV-04, UC-PCV-29) · US-PCP-017 · UC-PCART-07
//   permissao: producao.access
//
// Painel do gestor: o que trava a produção hoje e onde agir. Âncora visual: `Painel` de
// prototipo-ui/cowork/Wagner/producao-cv-page.jsx (classes `pcv-*` do bundle). Os indicadores abrem o
// Quadro já filtrado; "atrasadas ou vencendo" e "arte travando" vêm dos mesmos cartões do Quadro, então
// o número é igual nas duas telas (UC-PCV-03). A única escrita é na fila de arte: "Reenviar link" e
// "Enviar nova prova" geram link novo (o token só guarda o hash; liberado por [W] 2026-10-27, UC-PCART-07).
// Fora deste corte: refugo em R$ e o link para o Custo (thread 08), produtividade por setor (UC-PCV-27)
// e aproveitamento de mídia (UC-PCV-26).
import '../../../../../../../resources/css/cowork-producao-bundle.css';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import ProducaoAbas from '../_components/ProducaoAbas';
import AcaoDaProva from './_components/AcaoDaProva';

interface Indicadores {
  em_producao: number; vencem_hoje: number; atrasadas_ou_vencendo: number; atrasadas_numeros: string[];
  atrasadas_so_atraso: number; arte_travando: number; refugo_semana: { ocorrencias: number; quantidade: string };
}
interface Alerta { tipo: string; tom: 'danger' | 'warn'; titulo: string; texto: string; acao: { rotulo: string; href: string } }
interface ItemArte { chave: string; os_id: number; numero: string; cliente: string | null; estado: 'aguardando' | 'ajuste'; desde: string; comentario: string | null; expira_em: string }
interface Carga { posto_id: number; nome: string; setor: string | null; horas: number; capacidade: number; pct: number }
interface Evento { quando: string; texto: string; etapa: string | null; quem: string | null; os: string | null }
interface Dados { indicadores: Indicadores; alertas: Alerta[]; fila_arte: ItemArte[]; carga: Carga[]; eventos: Evento[] }
interface Props { hoje: string; painel?: Dados }

const QUADRO = '/producao/quadro';
const h1 = (n: number) => n.toLocaleString('pt-BR', { maximumFractionDigits: 1 });
const quando = (iso: string) => iso.slice(8, 10) + '/' + iso.slice(5, 7) + ' ' + iso.slice(11, 16);

export default function PainelIndex(props: Props) {
  return (
    <div className="pcv-root" data-screen-label="Produção · Painel">
      <div className="pcv-ph" data-contract="cabecalho"><PageHeader title="Produção" suffix=" · Painel" below={<ProducaoAbas ativa="/producao/painel" />} /></div>
      <Deferred data="painel" fallback={<div className="pcv-body"><Skeleton className="h-24 w-full" /><Skeleton className="h-64 w-full" /></div>}>
        {props.painel ? <Conteudo d={props.painel} /> : <div />}
      </Deferred>
    </div>
  );
}

function Kpi({ href, rotulo, children }: { href?: string; rotulo: string; children: ReactNode }) {
  return href ? <a className="pcv-kpi-btn" href={href} aria-label={rotulo}>{children}</a> : <div>{children}</div>;
}

function Conteudo({ d }: { d: Dados }) {
  const i = d.indicadores;
  return (
    <div className="pcv-body">
      <div className="pcv-kpis" data-contract="kpis">
        <Kpi href={QUADRO} rotulo="Ver todas as OS em produção no quadro">
          <KpiCard label="OS em produção" value={i.em_producao} description={i.vencem_hoje === 1 ? '1 vence hoje' : `${i.vencem_hoje} vencem hoje`} tone="info" />
        </Kpi>
        <Kpi href={`${QUADRO}?so=atrasadas`} rotulo="Ver as atrasadas ou vencendo no quadro">
          <KpiCard label="Atrasadas ou vencendo" value={i.atrasadas_ou_vencendo} description={i.atrasadas_numeros.slice(0, 4).join(', ') || 'nenhuma'}
            tone={i.atrasadas_so_atraso ? 'danger' : i.atrasadas_ou_vencendo ? 'warning' : 'default'} />
        </Kpi>
        <Kpi href={`${QUADRO}?so=arte`} rotulo="Ver as OS travadas por arte no quadro">
          <KpiCard label="Arte travando" value={i.arte_travando} description="aguardando aprovação ou ajuste" tone={i.arte_travando ? 'warning' : 'default'} />
        </Kpi>
        <Kpi rotulo="Refugo na semana">
          <KpiCard label="Refugo na semana" value={i.refugo_semana.ocorrencias}
            description={i.refugo_semana.ocorrencias ? `ocorrência(s) · ${i.refugo_semana.quantidade.replace('.', ',')} un. perdidas` : 'nenhuma ocorrência'}
            tone={i.refugo_semana.ocorrencias ? 'danger' : 'default'} />
        </Kpi>
      </div>

      {d.alertas.length > 0 && (
        <div className="pcv-alertas" role="region" aria-label="Alertas de produção" data-contract="producao-painel-alertas">
          {d.alertas.map((a) => (
            <Alert key={a.tipo + a.titulo} variant={a.tom === 'danger' ? 'destructive' : 'default'}>
              <AlertTitle>{a.titulo}</AlertTitle>
              <AlertDescription>
                {a.texto}
                <div className="pcv-al-a"><Button size="sm" variant="outline" asChild><a href={a.acao.href}>{a.acao.rotulo}</a></Button></div>
              </AlertDescription>
            </Alert>
          ))}
        </div>
      )}

      <Card data-contract="producao-painel-fila-arte">
        <CardHeader>
          <CardTitle>Fila de aprovação de arte</CardTitle>
          <CardDescription>O cliente aprova pelo link da prova, sem login.</CardDescription>
        </CardHeader>
        <CardContent className="p-0">
          {d.fila_arte.length ? (
            <ul className="pcv-fila-arte">
              {d.fila_arte.map((f) => (
                <li key={f.chave}>
                  <div>
                    <span><b className="mono">{f.numero}</b>{f.cliente ? ' · ' + f.cliente : ''}</span>
                    <small>{f.estado === 'ajuste'
                      ? `pediu ajuste em ${quando(f.desde)}${f.comentario ? ` — "${f.comentario}"` : ''}`
                      : `link enviado em ${quando(f.desde)} · vale até ${quando(f.expira_em)}`}</small>
                  </div>
                  <Badge variant={f.estado === 'ajuste' ? 'danger' : 'warning'}>{f.estado === 'ajuste' ? 'Ajuste pedido' : 'Aguardando cliente'}</Badge>
                  <AcaoDaProva osId={f.os_id} ajuste={f.estado === 'ajuste'} />
                  <Button size="sm" variant="ghost" asChild><a href={`${QUADRO}?so=arte`}>Ver no quadro</a></Button>
                </li>
              ))}
            </ul>
          ) : <p className="pcv-help px-4 pb-4">Nenhuma arte esperando o cliente.</p>}
        </CardContent>
      </Card>

      <div className="pcv-grid2">
        <Card data-contract="producao-painel-eventos">
          <CardHeader>
            <CardTitle>Acontecendo agora</CardTitle>
            <CardDescription>O que a produção fez por último: apontamento, cliente e PCP.</CardDescription>
          </CardHeader>
          <CardContent className="p-0">
            {d.eventos.length ? (
              <ol className="pcv-feed">
                {d.eventos.map((e, k) => (
                  <li key={k + e.quando}>
                    <span className="pcv-feed-dot" aria-hidden="true" />
                    <div className="pcv-feed-m">
                      <span className="pcv-feed-t">{e.texto}{e.etapa ? ' · ' + e.etapa : ''}</span>
                      <small><time className="mono">{quando(e.quando)}</time>{e.quem ? ' · ' + e.quem : ''}</small>
                    </div>
                    {e.os && <span className="mono text-xs">{e.os}</span>}
                  </li>
                ))}
              </ol>
            ) : <p className="pcv-help px-4 pb-4">Nada registrado ainda.</p>}
          </CardContent>
        </Card>

        <Card data-contract="producao-painel-carga">
          <CardHeader>
            <CardTitle>Carga das máquinas hoje</CardTitle>
            <CardDescription>Horas das etapas previstas para hoje ÷ horas do dia do posto.</CardDescription>
          </CardHeader>
          <CardContent>
            {d.carga.length ? (
              <ul className="pcv-carga">
                {d.carga.map((m) => (
                  <li key={m.posto_id}>
                    <span>{m.nome}</span>
                    <div className="h-1.5 rounded bg-muted" role="progressbar" aria-label={`${m.nome} ${m.pct}%`} aria-valuenow={Math.min(m.pct, 100)} aria-valuemin={0} aria-valuemax={100}>
                      <div className={'h-1.5 rounded ' + (m.pct > 100 ? 'bg-destructive' : m.pct > 85 ? 'bg-warning' : 'bg-primary')} style={{ width: `${Math.min(m.pct, 100)}%` }} />
                    </div>
                    <b className={m.pct > 100 ? 'pcv-late' : ''}>{h1(m.horas)} h</b>
                  </li>
                ))}
              </ul>
            ) : <p className="pcv-help">Nenhuma máquina cadastrada em Postos e cadastros.</p>}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

PainelIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
