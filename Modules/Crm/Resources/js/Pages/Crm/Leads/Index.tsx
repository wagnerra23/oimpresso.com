// @memcofre
//   tela: /crm/leads
//   module: Crm
//   stories: thread Crm/02 (LeadController@index · Blade → Inertia, detalhe em drawer)
//   permissao: crm.access_all_leads | crm.access_own_leads
//
// Lista de leads do CRM. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/crm-blade.jsx → TelaLeads()
// Contrato: governance/design/contracts/crm-leads.contract.json
//
// "Adicionar" abre o formulário de lead (Cliente/Create parametrizado, thread Crm/06). A
// conversão para cliente, o kanban e o "local" do contato seguem na Blade (`?classico=1`).
//
// Forma (pendências CRM-20/21/22/26, medido em produção em 2026-11-25): filtros recolhíveis,
// cartão "Todos os leads" com contador, Confortável/Compacto e Lista/Kanban em seletores,
// coluna Ação, estágio e último acompanhamento em selo colorido, os dois campos personalizados
// com o rótulo do negócio, e a gaveta do protótipo (`CrmGaveta`, 480px) com o Tipo PJ/PF.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState, type ReactNode } from 'react';
import { ChevronDown, Plus } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Segmented } from '@/Components/ui/segmented';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import CrmCabecalho from '../_components/CrmCabecalho';
import { CrmCartao, CrmFiltros, CrmGaveta, CrmMini, CrmPilula, CrmSecao } from '../_components/CrmBlocos';
import { frescor, tomDoEstagio } from '../_components/lead';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import { useCockpit } from '@/Hooks/useCockpit';

const ROTA = '/crm/leads';
const CLASSICO = `${ROTA}?classico=1`;
const KANBAN = `${ROTA}?lead_view=kanban`;
// Formulário de lead = Cliente/Create parametrizado (thread Crm/06, D2).
const NOVO = `${ROTA}/create`;
const TODOS = '__todos';

interface Opcao { value: string; label: string }
interface Opcoes { fontes: Opcao[]; estagios: Opcao[]; usuarios: Opcao[] }
interface Lead {
  id: number; codigo: string | null; nome: string; celular: string | null; email: string | null;
  documento: string | null; fonte: string | null; estagio: string | null; atribuidos: string[];
  endereco: string; ultimo: string | null; proximo: string | null; adicionado_em: string | null;
  tipo?: 'PJ' | 'PF' | null; campo1?: string | null; campo2?: string | null;
}
interface Rotulos { campo1: string; campo2: string }
type Filtros = Record<string, string | undefined>;
interface Props {
  filtros: Filtros;
  rotulos?: Rotulos;
  opcoes?: Opcoes;
  leads?: PaginatorShape<Lead>;
  lead?: Lead | null;
}

const ROTULOS_PADRAO: Rotulos = { campo1: 'Campo personalizado 1', campo2: 'Campo personalizado 2' };

function filtrar(filtros: Filtros, mudanca: Filtros) {
  router.get(ROTA, { ...filtros, ...mudanca }, { preserveState: true, preserveScroll: true, replace: true });
}

function verLead(filtros: Filtros, id: number | null) {
  router.get(ROTA, id ? { ...filtros, lead: String(id) } : filtros, {
    only: ['lead'], preserveState: true, preserveScroll: true, replace: true,
  });
}

export default function LeadsIndex({ filtros, rotulos = ROTULOS_PADRAO, opcoes, leads, lead }: Props) {
  const cockpit = useCockpit();
  const [compacta, setCompacta] = useState(false);

  return (
    <div className="flex flex-col gap-4">
      <CrmCabecalho aba="Leads" />

      <CrmFiltros data-contract="crm-filtros">
        <Deferred data="opcoes" fallback={<Skeleton className="h-16 w-full" />}>
          <div className="grid gap-3 sm:grid-cols-3">
            <Filtro rotulo="Fonte" campo="source" lista={opcoes?.fontes} filtros={filtros} />
            <Filtro rotulo="Estágio de vida" campo="life_stage" lista={opcoes?.estagios} filtros={filtros} />
            <Filtro rotulo="Atribuído a" campo="user_id" lista={opcoes?.usuarios} filtros={filtros} />
          </div>
        </Deferred>
      </CrmFiltros>

      <CrmCartao
        data-contract="crm-leads"
        titulo="Todos os leads"
        nota={leads ? `${leads.data.length} de ${leads.total}` : null}
        semPadding
      >
        <div className="flex flex-col gap-3 p-3">
          <div className="flex flex-wrap items-center gap-2" data-contract="crm-toolbar">
            <div className="ml-auto flex flex-wrap items-center gap-2">
              {/* O contrato `crm-leads` lê "Confortável/Compacto" no arquivo da tela. */}
              <Segmented
                aria-label="Densidade da tabela"
                value={compacta ? 'comp' : 'conf'}
                onValueChange={(v) => v && setCompacta(v === 'comp')}
                options={[{ value: 'conf', label: 'Confortável' }, { value: 'comp', label: 'Compacto' }]}
              />
              {/* O kanban segue na Blade (UC-CRMLD-07): escolher "Kanban" leva até lá. */}
              <Segmented
                aria-label="Modo de exibição"
                value="lista"
                onValueChange={(v) => { if (v === 'kanban') window.location.assign(KANBAN); }}
                options={[{ value: 'lista', label: 'Exibição de lista' }, { value: 'kanban', label: 'Kanban' }]}
              />
              <Button asChild size="sm"><a href={NOVO}><Plus className="size-3" /> Adicionar</a></Button>
            </div>
          </div>

          <Deferred data="leads" fallback={<Skeleton className="h-64 w-full" />}>
            {leads ? (
              <>
                <DataTable<Lead>
                  caption="Leads"
                  columns={colunas(rotulos, filtros, cockpit)}
                  data={leads.data}
                  pagination={leads}
                  endpoint={ROTA}
                  filters={filtros}
                  initialSearch={filtros.q ?? ''}
                  searchPlaceholder="Buscar por nome, ID ou celular"
                  emptyMessage="Nada com esses filtros"
                  rowKey={(r) => r.id}
                  onRowClick={(r) => verLead(filtros, r.id)}
                  density={compacta ? 'grid' : 'grid-relaxed'}
                  zebra={false}
                  tableWrapperClassName="overflow-hidden rounded-md border border-border"
                />
                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground" data-contract="crm-rodape">
                  <span>Total: {leads.total}</span>
                  <div className="ml-auto flex gap-2">
                    <Button asChild variant="outline" size="sm"><a href={CLASSICO}>Adicionar ao local</a></Button>
                    <Button asChild variant="outline" size="sm"><a href={CLASSICO}>Remover do local</a></Button>
                  </div>
                </div>
              </>
            ) : null}
          </Deferred>
        </div>
      </CrmCartao>

      {lead ? (
        <CrmGaveta
          onFechar={() => verLead(filtros, null)}
          selo={lead.estagio ? <CrmPilula tom={tomDoEstagio(lead.estagio)}>{lead.estagio}</CrmPilula> : null}
          titulo={lead.nome}
          subtitulo={[lead.codigo, lead.fonte, lead.atribuidos.join(', ')].filter(Boolean).join(' · ') || '—'}
          rodape={<>
            <Button asChild variant="outline"><a href={`${ROTA}/${lead.id}`}>Abrir ficha completa</a></Button>
            <Button asChild className="text-white"><a href={CLASSICO}>Converter para cliente</a></Button>
          </>}
        >
          <CrmSecao titulo="Informação do lead">
            <CrmMini linhas={[
              ['Celular', lead.celular || '—'], ['E-mail', lead.email || '—'], ['CNPJ / CPF', lead.documento || '—'],
              ['Tipo', lead.tipo || '—'], ['Endereço', lead.endereco || '—'], ['Adicionado em', lead.adicionado_em || '—'],
            ]} />
          </CrmSecao>
          <CrmSecao titulo="Acompanhamento">
            <CrmMini linhas={[
              ['Último', lead.ultimo || '—'], ['Próximo', lead.proximo || '—'],
              [rotulos.campo1, lead.campo1 || '—'], [rotulos.campo2, lead.campo2 || '—'],
            ]} />
          </CrmSecao>
        </CrmGaveta>
      ) : null}
    </div>
  );
}

function Filtro({ rotulo: nome, campo, lista, filtros }: { rotulo: string; campo: string; lista?: Opcao[]; filtros: Filtros }) {
  const cockpit = useCockpit();
  return (
    <div className="flex flex-col gap-1">
      <Label>{nome}</Label>
      <Select value={filtros[campo] || TODOS} onValueChange={(v) => filtrar(filtros, { [campo]: v === TODOS ? undefined : v })}>
        <SelectTrigger aria-label={nome}><SelectValue /></SelectTrigger>
        <SelectContent container={cockpit}>
          <SelectItem value={TODOS}>Todos</SelectItem>
          {(lista ?? []).map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}
        </SelectContent>
      </Select>
    </div>
  );
}

function colunas(rotulos: Rotulos, filtros: Filtros, cockpit: HTMLElement | null): ColumnDef<Lead>[] {
  const texto = (header: string, acc: (r: Lead) => ReactNode, width = 150, mono = false): ColumnDef<Lead> => ({
    id: header, header, cell: ({ row }) => acc(row.original) || '—', meta: { width, mono },
  });

  const acao: ColumnDef<Lead> = {
    id: 'Ação', header: 'Ação', meta: { width: 92 },
    cell: ({ row }) => (
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          {/* A linha abre a gaveta; o menu não pode abrir os dois. */}
          <Button variant="ghost" size="sm" aria-label={`Ações de ${row.original.nome}`}
            onClick={(e) => e.stopPropagation()} onKeyDown={(e) => e.stopPropagation()}>Ações <ChevronDown className="size-3" /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent container={cockpit} align="start" onClick={(e) => e.stopPropagation()}>
          <DropdownMenuItem onSelect={() => verLead(filtros, row.original.id)}>Exibir lead</DropdownMenuItem>
          {/* A conversão ainda mora na Blade (CRM-25). */}
          <DropdownMenuItem asChild><a href={CLASSICO}>Converter para cliente</a></DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    ),
  };

  return [
    acao,
    texto('ID do contato', (r) => r.codigo, 118, true),
    texto('Nome', (r) => r.nome, 210),
    texto('Celular', (r) => r.celular, 138, true),
    texto('E-mail', (r) => r.email, 226),
    texto('Fonte', (r) => r.fonte, 126),
    texto('Último acompanhamento', (r) => {
      const f = frescor(r.ultimo);
      return f ? <span title={r.ultimo ?? undefined}><CrmPilula tom={f.tom}>{f.texto}</CrmPilula></span> : null;
    }, 178),
    texto('Próximo acompanhamento', (r) => r.proximo, 178, true),
    texto('Estágio de vida', (r) => (r.estagio ? <CrmPilula tom={tomDoEstagio(r.estagio)}>{r.estagio}</CrmPilula> : null), 150),
    texto('Atribuído a', (r) => r.atribuidos.join(', '), 150),
    texto('Endereço', (r) => r.endereco, 250),
    texto('CNPJ / CPF', (r) => r.documento, 158, true),
    texto('Adicionado em', (r) => r.adicionado_em, 128, true),
    texto(rotulos.campo1, (r) => r.campo1, 160),
    texto(rotulos.campo2, (r) => r.campo2, 190),
  ];
}

LeadsIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
