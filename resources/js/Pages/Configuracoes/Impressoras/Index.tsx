// @memcofre
//   tela: /printers
//   module: Configuracoes
//   stories: thread sistema/playbook/04 (tela 1 de 3)
//   permissao: access_printers (o controller exige em todas as ações)
//
// Impressoras de cupom do negócio. Atrás da flag `useV2ConfiguracoesImpressoras` (PrinterController::FLAG_V2);
// com ela desligada a rota segue na Blade `printer/index`.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-impressoras.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx → Impressoras()
//
// "Testar" do protótipo fica fora: não existe endpoint de cupom de teste no legado (RUNBOOK §10).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { MoreHorizontal, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';
import { Inline, Stack } from '@/Components/layout';
import ConfiguracoesSubNav from '@/Pages/Configuracoes/_shared/ConfiguracoesSubNav';

export interface Impressora {
  id: number; nome: string; conexao: 'network' | 'windows' | 'linux'; perfil: string;
  caracteres_linha: string; ip: string; porta: string; caminho: string;
}
interface Props {
  impressoras?: Impressora[];
  opcoes: { conexao: Record<string, string>; perfil: Record<string, string> };
}

/** Rótulo em PT do tipo de conexão; o enum do banco é `network|windows|linux`. */
const CONEXAO: Record<Impressora['conexao'], string> = { network: 'Rede', windows: 'Windows', linux: 'Linux' };

interface Edicao { id?: number; nome: string; conexao: Impressora['conexao']; perfil: string; caracteres_linha: string; ip: string; porta: string; caminho: string }
/** Defaults do `printer/create.blade.php`: 42 caracteres por linha, porta 9100. */
const NOVA: Edicao = { nome: '', conexao: 'network', perfil: 'default', caracteres_linha: '42', ip: '', porta: '9100', caminho: '' };

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** `destroy()` só responde a ajax() e devolve `{success, msg}` — igual à Blade. */
async function excluirNoServidor(id: number): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(`/printers/${id}`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  const corpo = await r.json().catch(() => null);
  if (corpo && typeof corpo.success === 'boolean') return corpo;
  return { success: false, msg: `Não foi possível excluir (HTTP ${r.status}).` };
}

function ImpressorasIndex({ impressoras: impressorasProp, opcoes }: Props) {
  const impressoras = useMemo(() => impressorasProp ?? [], [impressorasProp]);
  const [q, setQ] = useState('');
  const [excluir, setExcluir] = useState<Impressora | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);
  const [edicao, setEdicao] = useState<Edicao | null>(null);
  const [salvando, setSalvando] = useState(false);
  const busca = useRef<HTMLInputElement>(null);
  // store()/update() redirecionam com ->with('status'): a mensagem chega como flash (sucesso ou erro).
  const { flash } = usePage<{ flash?: { success?: unknown; error?: unknown } }>().props;
  const flashMsg = [flash?.error, flash?.success].find((m): m is string => typeof m === 'string' && m !== '');

  const nova = useCallback(() => setEdicao({ ...NOVA }), []);
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
      if (e.key === 'n') { e.preventDefault(); nova(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, [nova]);

  const rede = edicao?.conexao === 'network';
  const podeSalvar = !!edicao && edicao.nome.trim() !== '' && (rede ? edicao.ip.trim() !== '' : edicao.caminho.trim() !== '');
  /** Mesmo corpo do formulário da Blade; o store()/update() redirecionam para /printers com o aviso. */
  const salvar = () => {
    if (!edicao || !podeSalvar) return;
    const corpo = {
      name: edicao.nome.trim(), connection_type: edicao.conexao, capability_profile: edicao.perfil,
      char_per_line: edicao.caracteres_linha, ip_address: edicao.ip.trim(), port: edicao.porta.trim(), path: edicao.caminho.trim(),
    };
    const fim = { onStart: () => setSalvando(true), onFinish: () => { setSalvando(false); setEdicao(null); } };
    if (edicao.id) router.put(`/printers/${edicao.id}`, corpo, fim);
    else router.post('/printers', corpo, fim);
  };
  const muda = (k: keyof Edicao, v: string) => setEdicao((e) => (e ? { ...e, [k]: v } : e));

  const termo = q.trim().toLowerCase();
  const lista = useMemo(
    () => impressoras.filter((p) => !termo || [p.nome, p.ip, p.caminho].some((v) => v.toLowerCase().includes(termo))),
    [impressoras, termo],
  );

  const confirmarExclusao = async () => {
    if (!excluir) return;
    const r = await excluirNoServidor(excluir.id);
    setExcluir(null);
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['impressoras'] });
  };

  const colunas: ColumnDef<Impressora, unknown>[] = [
    { id: 'nome', header: 'Nome', cell: ({ row: { original: p } }) => <span className="font-medium">{p.nome}</span> },
    { id: 'conexao', header: 'Conexão', cell: ({ row: { original: p } }) => CONEXAO[p.conexao] ?? p.conexao },
    { id: 'perfil', header: 'Perfil', cell: ({ row: { original: p } }) => opcoes.perfil[p.perfil] ?? p.perfil },
    { id: 'cpl', header: 'Caracteres/linha', meta: { align: 'right', mono: true }, cell: ({ row: { original: p } }) => p.caracteres_linha || '—' },
    {
      id: 'destino', header: 'Endereço',
      cell: ({ row: { original: p } }) => (
        <span className="font-mono text-xs">{p.conexao === 'network' ? `${p.ip || '—'}:${p.porta || '—'}` : p.caminho || '—'}</span>
      ),
    },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: p } }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${p.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onSelect={() => setEdicao({ ...p })}>Editar impressora</DropdownMenuItem>
            <DropdownMenuItem className="text-destructive" onSelect={() => setExcluir(p)}>Excluir impressora</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Impressoras"
          subtitle={impressorasProp ? <><strong>{impressoras.length}</strong> {impressoras.length === 1 ? 'impressora de cupom' : 'impressoras de cupom'}</> : 'impressoras de cupom'}
          subnav={<ConfiguracoesSubNav />}
          actions={<Button onClick={nova}><Plus className="size-4" /> Adicionar impressora</Button>} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar impressora, IP ou caminho…" aria-label="Buscar impressora" />
          </div>
        </Inline>
        {(aviso ?? flashMsg) && <p role="status" className={flash?.error && !aviso ? 'text-sm text-destructive' : 'text-sm text-muted-foreground'}>{aviso ?? flashMsg}</p>}

        <Deferred data="impressoras" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando impressoras…</p>}>
          {impressoras.length > 0 ? (
            <div data-contract="impressoras-table">
              <DataTable columns={colunas} data={lista} caption="Impressoras do negócio" rowKey={(p) => p.id}
                emptyMessage="Nenhuma impressora com esse termo. Limpe a busca para ver todas." />
            </div>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhuma impressora cadastrada" description="Sem impressora o cupom sai pelo diálogo do navegador."
                action={<Button onClick={nova}>Adicionar a primeira</Button>} />
            </div>
          )}
        </Deferred>
      </Stack>

      <Sheet open={!!edicao} onOpenChange={(v) => !v && setEdicao(null)}>
        <SheetContent className="w-full overflow-y-auto p-0 sm:max-w-md">
          <header className="os-drawer-head">
            <div className="os-drawer-head-l">
              <SheetTitle asChild><h2 className="m-0">{edicao?.id ? `Editar ${edicao.nome}` : 'Adicionar impressora'}</h2></SheetTitle>
              <SheetDescription asChild><p>Como o caixa chega até a impressora de cupom.</p></SheetDescription>
            </div>
          </header>
          {edicao && (
            <Stack data-contract="impressora-form" gap={3} className="p-5 text-sm">
              <Stack gap={1}>
                <label htmlFor="imp-nome">Nome *</label>
                <Input id="imp-nome" value={edicao.nome} placeholder="Ex.: Caixa 1 (balcão)" onChange={(e) => muda('nome', e.target.value)} />
              </Stack>
              <Stack gap={1}>
                <label htmlFor="imp-conexao">Tipo de conexão *</label>
                <Select value={edicao.conexao} onValueChange={(v) => muda('conexao', v)}>
                  <SelectTrigger id="imp-conexao"><SelectValue /></SelectTrigger>
                  <SelectContent>{Object.keys(opcoes.conexao).filter(Boolean).map((k) => <SelectItem key={k} value={k}>{CONEXAO[k as Impressora['conexao']] ?? opcoes.conexao[k]}</SelectItem>)}</SelectContent>
                </Select>
              </Stack>
              <Stack gap={1}>
                <label htmlFor="imp-perfil">Perfil de capacidade *</label>
                <Select value={edicao.perfil} onValueChange={(v) => muda('perfil', v)}>
                  <SelectTrigger id="imp-perfil"><SelectValue /></SelectTrigger>
                  <SelectContent>{Object.entries(opcoes.perfil).filter(([k]) => k).map(([k, l]) => <SelectItem key={k} value={k}>{l}</SelectItem>)}</SelectContent>
                </Select>
              </Stack>
              <Stack gap={1}>
                <label htmlFor="imp-cpl">Caracteres por linha *</label>
                <Input id="imp-cpl" inputMode="numeric" value={edicao.caracteres_linha} onChange={(e) => muda('caracteres_linha', e.target.value.replace(/\D/g, ''))} />
                <span className="text-xs text-muted-foreground">48 na bobina de 80 mm, 32 na de 58 mm.</span>
              </Stack>
              {rede ? (
                <Inline gap={3}>
                  <Stack gap={1} className="flex-1"><label htmlFor="imp-ip">Endereço IP *</label><Input id="imp-ip" value={edicao.ip} placeholder="192.168.0.31" onChange={(e) => muda('ip', e.target.value)} /></Stack>
                  <Stack gap={1} className="w-28"><label htmlFor="imp-porta">Porta *</label><Input id="imp-porta" inputMode="numeric" value={edicao.porta} onChange={(e) => muda('porta', e.target.value)} /></Stack>
                </Inline>
              ) : (
                <Stack gap={1}>
                  <label htmlFor="imp-caminho">Caminho *</label>
                  <Input id="imp-caminho" value={edicao.caminho} placeholder={edicao.conexao === 'windows' ? 'LPT1' : '/dev/usb/lp1'} onChange={(e) => muda('caminho', e.target.value)} />
                  <span className="text-xs text-muted-foreground">Windows: <code>LPT1</code> (paralela) ou <code>COM1</code> (serial). Linux: <code>/dev/lp0</code>, <code>/dev/usb/lp1</code> (USB), <code>/dev/ttyUSB0</code> (USB-serial).</span>
                </Stack>
              )}
              <Inline gap={2} justify="end" className="border-t pt-4">
                <Button variant="ghost" onClick={() => setEdicao(null)}>Cancelar</Button>
                <Button disabled={!podeSalvar || salvando} onClick={salvar}>{edicao.id ? 'Salvar' : 'Adicionar'}</Button>
              </Inline>
            </Stack>
          )}
        </SheetContent>
      </Sheet>

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader><AlertDialogTitle>Excluir {excluir?.nome}?</AlertDialogTitle></AlertDialogHeader>
          <p className="text-sm">Os caixas que usam esta impressora voltam a imprimir pelo navegador.</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

ImpressorasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ImpressorasIndex;
