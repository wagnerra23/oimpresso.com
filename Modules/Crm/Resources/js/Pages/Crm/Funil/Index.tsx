// @memcofre
//   tela: /crm/funil
//   module: Crm
//   stories: US-CRM-092 (FunilController · ADR 0441)
//   permissao: crm.funil.ver (ver) · crm.funil.editar (criar e mover)
//
// Funil de vendas em kanban (PT-05). Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Felipe/erp-shell-v2/crm-page.jsx (kanban com drag, drawer).
// Mover para Ganho ou Perdido pede confirmação: depois disso o negócio não muda mais de etapa.

import { Deferred, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import KanbanDndProvider from '@/Components/shared/KanbanDndProvider';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import NumericInputPtBR from '@/Components/ui/numeric-input-ptbr';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import CustomerSearchAutocomplete from '@/Pages/Sells/_components/CustomerSearchAutocomplete';
import { BRL, FunilColuna, NegocioCard, type Etapa, type Negocio } from './_components/FunilQuadro';

const ROTA = '/crm/funil';

interface ResumoEtapa { count: number; valor_total: number; valor_ponderado: number }
interface Resumo { por_etapa: Record<string, ResumoEtapa>; previsao_mes: number }
interface Props {
  etapas: Etapa[];
  pode_editar: boolean;
  negocios?: Negocio[];
  resumo?: Resumo;
}

interface Movimento { negocio: Negocio; para: Etapa }

export default function FunilIndex({ etapas, pode_editar, negocios, resumo }: Props) {
  const [aberto, setAberto] = useState<Negocio | null>(null);
  const [novoAberto, setNovoAberto] = useState(false);
  const [confirmar, setConfirmar] = useState<Movimento | null>(null);

  const porEtapa = useMemo(() => {
    const m: Record<string, Negocio[]> = {};
    etapas.forEach((e) => { m[e.id] = []; });
    (negocios ?? []).forEach((n) => { (m[n.etapa] ??= []).push(n); });
    return m;
  }, [etapas, negocios]);

  const mover = useCallback((negocio: Negocio, para: Etapa) => {
    router.patch(`${ROTA}/${negocio.id}/etapa`, { etapa: para.id }, {
      preserveScroll: true, preserveState: true, only: ['negocios', 'resumo'],
      onSuccess: () => setAberto(null),
    });
  }, []);

  const pedirMovimento = useCallback((negocio: Negocio, paraId: string) => {
    const para = etapas.find((e) => e.id === paraId);
    if (!para || negocio.etapa === paraId) return;
    // Ganho e Perdido são finais: confirma antes (PT-05 R4).
    if (para.terminal) setConfirmar({ negocio, para });
    else mover(negocio, para);
  }, [etapas, mover]);

  const onMove = useCallback((id: number, _de: string, para: string, negocio: Negocio) => {
    void id;
    pedirMovimento(negocio, para);
  }, [pedirMovimento]);

  const r = resumo?.por_etapa;
  const ganhos = r?.ganho?.count ?? 0;
  const fechados = ganhos + (r?.perdido?.count ?? 0);
  const abertos = (r ? Object.entries(r).filter(([k]) => k !== 'totais' && k !== 'ganho' && k !== 'perdido') : [])
    .reduce((acc, [, v]) => ({ n: acc.n + v.count, valor: acc.valor + v.valor_total }), { n: 0, valor: 0 });

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="Funil de vendas"
        subtitle="Negócios por etapa, do primeiro contato ao fechamento."
        actions={pode_editar ? <PageHeaderPrimary label="Novo negócio" onClick={() => setNovoAberto(true)} /> : undefined}
      />

      <Deferred data="resumo" fallback={<Skeleton className="h-24 w-full" />}>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-contract="funil-indicadores">
          <KpiCard label="Em aberto" value={BRL.format(abertos.valor)} description={`${abertos.n} negócios`} />
          <KpiCard label="Ganhos" value={BRL.format(r?.ganho?.valor_total ?? 0)} description={`${ganhos} negócios`} tone="success" />
          <KpiCard label="Taxa de conversão" value={fechados > 0 ? `${Math.round((ganhos / fechados) * 100)}%` : '—'} description="ganhos entre os fechados" />
          <KpiCard label="Previsão do mês" value={BRL.format(resumo?.previsao_mes ?? 0)} description="valor × chance da etapa" />
        </div>
      </Deferred>

      <Deferred data="negocios" fallback={<Skeleton className="h-96 w-full" />}>
        <KanbanDndProvider<Negocio> onMove={onMove} renderPreview={(n) => <NegocioCard negocio={n} podeArrastar={false} onAbrir={() => {}} />}>
          <div className="overflow-x-auto pb-2" data-contract="funil-quadro">
            <div className="grid gap-3" style={{ gridTemplateColumns: `repeat(${etapas.length}, minmax(220px, 1fr))` }}>
              {etapas.map((e) => (
                <FunilColuna key={e.id} etapa={e} negocios={porEtapa[e.id] ?? []} podeEditar={pode_editar} onAbrir={setAberto} />
              ))}
            </div>
          </div>
        </KanbanDndProvider>
      </Deferred>

      <Sheet open={aberto !== null} onOpenChange={(o) => !o && setAberto(null)}>
        <SheetContent side="right" className="w-full sm:max-w-md">
          {aberto && (
            <>
              <SheetHeader>
                <SheetTitle>{aberto.titulo}</SheetTitle>
                <SheetDescription>{aberto.contato ?? 'Sem cliente'}{aberto.responsavel ? ` · ${aberto.responsavel}` : ''}</SheetDescription>
              </SheetHeader>
              <div className="mt-4 flex flex-col gap-4">
                <div>
                  <small className="text-xs text-muted-foreground">Valor estimado</small>
                  <p className="font-mono text-2xl tabular-nums">{BRL.format(aberto.valor)}</p>
                </div>
                <div className="flex flex-col gap-2">
                  <small className="text-xs text-muted-foreground">Etapa</small>
                  <div className="flex flex-wrap gap-2">
                    {etapas.map((e) => (
                      <Button
                        key={e.id}
                        size="sm"
                        variant={e.id === aberto.etapa ? 'default' : 'outline'}
                        disabled={!pode_editar || e.id === aberto.etapa || etapas.find((x) => x.id === aberto.etapa)?.terminal}
                        onClick={() => pedirMovimento(aberto, e.id)}
                      >
                        {e.rotulo}
                      </Button>
                    ))}
                  </div>
                </div>
                {aberto.contato_id !== null && (
                  <a className="text-sm text-primary underline-offset-4 hover:underline" href={`/contacts/${aberto.contato_id}`}>Abrir o cadastro do cliente</a>
                )}
              </div>
            </>
          )}
        </SheetContent>
      </Sheet>

      {pode_editar && <NovoNegocio aberto={novoAberto} onFechar={() => setNovoAberto(false)} />}

      <AlertDialog open={confirmar !== null} onOpenChange={(o) => !o && setConfirmar(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Marcar como {confirmar?.para.rotulo.toLowerCase()}?</AlertDialogTitle>
            <AlertDialogDescription>
              {confirmar?.negocio.titulo} sai do funil em aberto. Depois disso o negócio não muda mais de etapa.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={() => { if (confirmar) mover(confirmar.negocio, confirmar.para); setConfirmar(null); }}>
              Confirmar
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

function NovoNegocio({ aberto, onFechar }: { aberto: boolean; onFechar: () => void }) {
  const [titulo, setTitulo] = useState('');
  const [contato, setContato] = useState<number | null>(null);
  const [valor, setValor] = useState(0);
  const [fechamento, setFechamento] = useState('');
  const [erros, setErros] = useState<Record<string, string>>({});
  const [salvando, setSalvando] = useState(false);

  const salvar = () => {
    setSalvando(true);
    // Valor vai como número com 2 casas, nunca texto pt-BR (regra de valor do projeto).
    router.post(ROTA, {
      titulo, contact_id: contato, valor_estimado: Math.round(valor * 100) / 100, data_fechamento_prevista: fechamento || null,
    }, {
      preserveScroll: true, only: ['negocios', 'resumo'],
      onError: (e) => setErros(e),
      onSuccess: () => { setTitulo(''); setContato(null); setValor(0); setFechamento(''); setErros({}); onFechar(); },
      onFinish: () => setSalvando(false),
    });
  };

  return (
    <Sheet open={aberto} onOpenChange={(o) => !o && onFechar()}>
      <SheetContent side="right" className="w-full sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Novo negócio</SheetTitle>
          <SheetDescription>Entra no funil na etapa Lead.</SheetDescription>
        </SheetHeader>
        <div className="mt-4 flex flex-col gap-4">
          <div className="flex flex-col gap-1">
            <Label htmlFor="funil-titulo">Título</Label>
            <Input id="funil-titulo" value={titulo} onChange={(e) => setTitulo(e.target.value)} placeholder="Ex.: fachada em ACM da loja" />
            {erros.titulo && <small className="text-xs text-destructive">{erros.titulo}</small>}
          </div>
          <div className="flex flex-col gap-1">
            <Label>Cliente</Label>
            <CustomerSearchAutocomplete defaultName="" onSelect={(c) => setContato(c.id)} onClear={() => setContato(null)} />
            {erros.contact_id && <small className="text-xs text-destructive">{erros.contact_id}</small>}
          </div>
          <div className="flex flex-col gap-1">
            <Label htmlFor="funil-valor">Valor estimado (R$)</Label>
            <NumericInputPtBR id="funil-valor" value={valor} onChange={setValor} />
            {erros.valor_estimado && <small className="text-xs text-destructive">{erros.valor_estimado}</small>}
          </div>
          <div className="flex flex-col gap-1">
            <Label htmlFor="funil-fecha">Fechamento previsto</Label>
            <Input id="funil-fecha" type="date" value={fechamento} onChange={(e) => setFechamento(e.target.value)} />
          </div>
        </div>
        <SheetFooter className="mt-6">
          <Button onClick={salvar} disabled={salvando || titulo.trim() === '' || contato === null}>
            {salvando ? 'Salvando…' : 'Criar negócio'}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
