// @memcofre
//   tela: /crm/reports
//   module: Crm
//   stories: thread Crm/13 (ReportController@index · Blade → Inertia)
//   permissao: admin do negócio (is_admin)
//
// Relatórios do CRM, atrás da flag useV2CrmRelatorios (OFF). Charter: ./Index.charter.md
// Casos: ./Index.casos.md · Âncora de design: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx → Relatorios()
// Contrato: governance/design/contracts/crm-relatorios.contract.json
//
// Só leitura. As três matrizes saem das mesmas consultas que alimentavam os DataTables da Blade.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { PageHeader } from '@/Components/PageHeader';

const ROTA = '/crm/reports';
const TODOS = '__todos';

interface Opcao { value: string; label: string }
type Contagens = Record<string, number>;
interface LinhaUsuario extends Contagens { id: number }
interface Conversao { id: number; nome: string; total: number }
interface Convertido { nome: string; convertido_em: string | null }
type Filtros = Record<string, string | undefined>;
interface Props {
  filtros: Filtros;
  status: Opcao[];
  categorias?: Opcao[];
  por_usuario?: (LinhaUsuario & { nome: string })[];
  por_contato?: (Contagens & { nome: string })[];
  conversao?: Conversao[];
  convertidos?: Convertido[] | null;
}

function filtrar(filtros: Filtros, mudanca: Filtros) {
  router.get(ROTA, { ...filtros, ...mudanca }, { preserveState: true, preserveScroll: true, replace: true });
}

function verConvertidos(filtros: Filtros, id: number | null) {
  router.get(ROTA, id ? { ...filtros, convertido_por: String(id) } : filtros, {
    only: ['convertidos'], preserveState: true, preserveScroll: true, replace: true,
  });
}

export default function RelatoriosIndex({ filtros, status, categorias, por_usuario, por_contato, conversao, convertidos }: Props) {
  const convertidoPor = new URLSearchParams(usePage().url.split('?')[1] ?? '').get('convertido_por');
  const quem = conversao?.find((c) => String(c.id) === convertidoPor);

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Relatórios" subtitle="Acompanhamentos por usuário e por contato, e leads convertidos em cliente." />

      <Card data-contract="crm-filtros">
        <CardContent className="flex flex-col gap-3 p-4">
          <h3 className="text-sm font-medium">Filtros</h3>
          <div className="grid gap-3 sm:grid-cols-4">
            <div className="flex flex-col gap-1">
              <Label htmlFor="rel-de">Intervalo de datas — de</Label>
              <Input id="rel-de" type="date" value={filtros.start_date ?? ''} onChange={(e) => filtrar(filtros, { start_date: e.target.value || undefined })} />
            </div>
            <div className="flex flex-col gap-1">
              <Label htmlFor="rel-ate">até</Label>
              <Input id="rel-ate" type="date" value={filtros.end_date ?? ''} onChange={(e) => filtrar(filtros, { end_date: e.target.value || undefined })} />
            </div>
            <div className="flex flex-col gap-1">
              <Label>Categoria de acompanhamento</Label>
              <Select value={filtros.followup_category_id || TODOS} onValueChange={(v) => filtrar(filtros, { followup_category_id: v === TODOS ? undefined : v })}>
                <SelectTrigger aria-label="Categoria de acompanhamento"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value={TODOS}>Todas</SelectItem>
                  {(categorias ?? []).map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card data-contract="crm-rel-usuario">
        <CardContent className="flex flex-col gap-3 p-4">
          <h3 className="text-sm font-medium">Acompanhamentos por usuário</h3>
          <Deferred data="por_usuario" fallback={<Skeleton className="h-40 w-full" />}>
            <Matriz primeira="Usuário" status={status} linhas={por_usuario ?? []} />
          </Deferred>
        </CardContent>
      </Card>

      <Card data-contract="crm-rel-contato">
        <CardContent className="flex flex-col gap-3 p-4">
          <h3 className="text-sm font-medium">Acompanhamentos por contatos</h3>
          <Deferred data="por_contato" fallback={<Skeleton className="h-40 w-full" />}>
            <Matriz primeira="Contato" status={status} linhas={por_contato ?? []} />
          </Deferred>
        </CardContent>
      </Card>

      <Card data-contract="crm-rel-conversao">
        <CardContent className="flex flex-col gap-3 p-4">
          <h3 className="text-sm font-medium">Leads convertidos em cliente</h3>
          <Deferred data="conversao" fallback={<Skeleton className="h-24 w-full" />}>
            <table className="w-full text-sm">
              <thead><tr className="text-left text-muted-foreground"><th scope="col">Convertido por</th><th scope="col" className="text-right">Total</th></tr></thead>
              <tbody>
                {(conversao ?? []).length === 0 ? (
                  <tr><td colSpan={2} className="py-2 text-muted-foreground">Nenhuma conversão</td></tr>
                ) : (conversao ?? []).map((c) => (
                  <tr key={c.id} className="border-t border-border">
                    <td className="py-1"><Button variant="link" className="h-auto p-0" onClick={() => verConvertidos(filtros, c.id)}>{c.nome}</Button></td>
                    <td className="py-1 text-right tabular-nums">{c.total}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Deferred>
        </CardContent>
      </Card>

      <Sheet open={!!convertidos} onOpenChange={(aberto) => { if (!aberto) verConvertidos(filtros, null); }}>
        {convertidos ? (
          <SheetContent side="right" className="w-full sm:max-w-[760px]">
            <SheetHeader>
              <SheetTitle>{quem?.nome ?? 'Conversões'}</SheetTitle>
              <SheetDescription>{convertidos.length} lead(s) convertidos em cliente</SheetDescription>
            </SheetHeader>
            <ul className="flex flex-col gap-1 overflow-y-auto px-4 text-sm">
              {convertidos.length === 0 ? <li className="text-muted-foreground">Nenhuma conversão</li> : convertidos.map((c, i) => (
                <li key={i} className="flex justify-between gap-3 border-b border-border py-1">
                  <span>{c.nome}</span><span className="tabular-nums text-muted-foreground">{c.convertido_em ?? '—'}</span>
                </li>
              ))}
            </ul>
          </SheetContent>
        ) : null}
      </Sheet>
    </div>
  );
}

function Matriz({ primeira, status, linhas }: { primeira: string; status: Opcao[]; linhas: (Contagens & { nome: string })[] }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr className="text-left text-muted-foreground">
            <th scope="col">{primeira}</th>
            {status.map((s) => <th key={s.value} scope="col" className="text-right">{s.label}</th>)}
            <th scope="col" className="text-right">Outros</th>
            <th scope="col" className="text-right">Acompanhamentos totais</th>
          </tr>
        </thead>
        <tbody>
          {linhas.length === 0 ? (
            <tr><td colSpan={status.length + 3} className="py-2 text-muted-foreground">Nenhum acompanhamento no período</td></tr>
          ) : linhas.map((l, i) => (
            <tr key={i} className="border-t border-border">
              <td className="py-1">{l.nome}</td>
              {status.map((s) => <td key={s.value} className="py-1 text-right tabular-nums">{l[s.value] ?? 0}</td>)}
              <td className="py-1 text-right tabular-nums">{l.outros}</td>
              <td className="py-1 text-right tabular-nums">{l.total}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

RelatoriosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
