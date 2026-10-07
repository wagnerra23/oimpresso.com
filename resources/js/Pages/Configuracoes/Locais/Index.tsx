// @memcofre
//   tela: /business-location
//   module: Configuracoes
//   stories: thread sistema/playbook/04 (tela 3 de 3)
//   permissao: business_settings.access (a mesma da Configuração da empresa — o legado não separa)
//
// Locais comerciais (filiais) do negócio. Atrás da flag `useV2ConfiguracoesLocais` (BusinessLocationController::FLAG_V2);
// desligada, a rota segue na Blade `business_location/index`.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-locais.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx → Locais()
//
// Não existe excluir local (o destroy() é vazio): só ativar e desativar. "Configurações de recibo" do protótipo é outra
// tela (location_settings), fora desta thread.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { MoreHorizontal, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Checkbox } from '@/Components/ui/checkbox';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';
import { Grid, Inline, Stack } from '@/Components/layout';
import ConfiguracoesSubNav from '@/Pages/Configuracoes/_shared/ConfiguracoesSubNav';

export interface Local {
  id: number; nome: string; referencia: string; cidade: string; cnpj: string; ativo: boolean;
  tabela: string | null; esquema: string | null; layout_pdv: string | null; layout_venda: string | null; dados: Dados;
}
type Pagamentos = Record<string, { is_enabled?: string | number; account?: string | number | null }>;
/** Os campos do formulário da Blade, crus; `default_payment_accounts`/`featured_products` voltam intactos no update(). */
interface Dados { [campo: string]: string | Pagamentos | string[]; default_payment_accounts: Pagamentos; featured_products: string[] }
interface Opcoes {
  esquemas: Record<string, string>; layouts: Record<string, string>; tabelas: Record<string, string>;
  formas: Record<string, string>; contas: Record<string, string>; rotulos: Record<string, string>;
}
interface Props { locais?: Local[]; opcoes?: Opcoes }

type Campo = { k: string; rotulo: string; obrigatorio?: boolean; opcoes?: keyof Opcoes; ajuda?: string };
/** Seções e campos na ordem do `business_location/edit.blade.php`. */
const SECOES: { titulo: string; campos: Campo[] }[] = [
  { titulo: 'Identificação', campos: [{ k: 'name', rotulo: 'Nome', obrigatorio: true }, { k: 'location_id', rotulo: 'Referência', ajuda: 'Vazio: o sistema gera (BL0001…).' }] },
  { titulo: 'Fiscal', campos: [{ k: 'cnpj', rotulo: 'CNPJ' }, { k: 'razao_social', rotulo: 'Razão social' }, { k: 'nome_fantasia', rotulo: 'Nome fantasia' },
    { k: 'inscricao_estadual', rotulo: 'Inscrição estadual' }, { k: 'inscricao_municipal', rotulo: 'Inscrição municipal' }] },
  { titulo: 'Endereço', campos: [{ k: 'landmark', rotulo: 'Ponto de referência' }, { k: 'city', rotulo: 'Cidade', obrigatorio: true },
    { k: 'zip_code', rotulo: 'CEP', obrigatorio: true, ajuda: 'Até 7 caracteres (o campo do banco é char(7)).' }, { k: 'state', rotulo: 'Estado', obrigatorio: true },
    { k: 'country', rotulo: 'País', obrigatorio: true }] },
  { titulo: 'Contato', campos: [{ k: 'mobile', rotulo: 'Celular' }, { k: 'alternate_number', rotulo: 'Outro telefone' }, { k: 'email', rotulo: 'E-mail' }, { k: 'website', rotulo: 'Site' }] },
  { titulo: 'Fatura e preço', campos: [{ k: 'invoice_scheme_id', rotulo: 'Esquema de fatura (PDV)', obrigatorio: true, opcoes: 'esquemas' },
    { k: 'sale_invoice_scheme_id', rotulo: 'Esquema de fatura (venda)', obrigatorio: true, opcoes: 'esquemas' },
    { k: 'invoice_layout_id', rotulo: 'Layout de fatura (PDV)', obrigatorio: true, opcoes: 'layouts' },
    { k: 'sale_invoice_layout_id', rotulo: 'Layout de fatura (venda)', obrigatorio: true, opcoes: 'layouts' },
    { k: 'selling_price_group_id', rotulo: 'Tabela de preço', opcoes: 'tabelas' }] },
];
const PERSONALIZADOS = ['custom_field1', 'custom_field2', 'custom_field3', 'custom_field4'];
/** Sentinela do Radix Select para "nenhum" (o valor vazio não é permitido em SelectItem). */
const NENHUM = '__nenhum';

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** store()/update() devolvem `{success, msg}` (ajax), como o modal da Blade; quota/assinatura devolvem outra coisa. */
async function gravar(id: number | undefined, d: Dados, formas: string[]): Promise<{ success: boolean; msg?: string }> {
  const corpo = new URLSearchParams();
  for (const [k, v] of Object.entries(d)) if (typeof v === 'string') corpo.append(k, v);
  for (const f of formas) {
    const p = d.default_payment_accounts[f] ?? {};
    if (p.is_enabled) corpo.append(`default_payment_accounts[${f}][is_enabled]`, '1');
    corpo.append(`default_payment_accounts[${f}][account]`, p.account == null ? '' : String(p.account));
  }
  for (const v of d.featured_products) corpo.append('featured_products[]', v);
  if (id) corpo.append('_method', 'PUT');
  const r = await fetch(id ? `/business-location/${id}` : '/business-location', {
    method: 'POST', credentials: 'same-origin', body: corpo,
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  const json = await r.json().catch(() => null);
  if (json && typeof json.success === 'boolean') return json;
  return { success: false, msg: `Não foi possível salvar (HTTP ${r.status}). A assinatura ou a cota de locais do pacote pode ter barrado o cadastro.` };
}

/** activateDeactivateLocation() é GET e devolve `{success, msg}` — igual à Blade. */
async function alternar(id: number): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(`/business-location/activate-deactivate/${id}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  });
  const corpo = await r.json().catch(() => null);
  if (corpo && typeof corpo.success === 'boolean') return corpo;
  return { success: false, msg: `Não foi possível concluir (HTTP ${r.status}).` };
}

function LocaisIndex({ locais: locaisProp, opcoes }: Props) {
  const locais = useMemo(() => locaisProp ?? [], [locaisProp]);
  const [q, setQ] = useState('');
  const [aviso, setAviso] = useState<string | null>(null);
  const [edicao, setEdicao] = useState<{ id?: number; dados: Dados } | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [salvando, setSalvando] = useState(false);
  const busca = useRef<HTMLInputElement>(null);
  const formas = useMemo(() => Object.keys(opcoes?.formas ?? {}).filter(Boolean), [opcoes]);

  /** Cadastro novo: todas as formas de pagamento ligadas e sem conta, como o create.blade.php marca. */
  const novo = useCallback(() => {
    const dados: Dados = { default_payment_accounts: {}, featured_products: [], country: 'Brasil' };
    for (const f of formas) dados.default_payment_accounts[f] = { is_enabled: '1', account: '' };
    setErro(null);
    setEdicao({ dados });
  }, [formas]);
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
      if (e.key === 'n' && opcoes) { e.preventDefault(); novo(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, [novo, opcoes]);

  const valor = (k: string) => (typeof edicao?.dados[k] === 'string' ? (edicao.dados[k] as string) : '');
  const muda = (k: string, v: string) => setEdicao((e) => (e ? { ...e, dados: { ...e.dados, [k]: v } } : e));
  const mudaPagamento = (f: string, campo: 'is_enabled' | 'account', v: string) => setEdicao((e) => {
    if (!e) return e;
    const atual = e.dados.default_payment_accounts[f] ?? {};
    return { ...e, dados: { ...e.dados, default_payment_accounts: { ...e.dados.default_payment_accounts, [f]: { ...atual, [campo]: v } } } };
  });
  const obrigatorios = SECOES.flatMap((s) => s.campos).filter((c) => c.obrigatorio);
  const podeSalvar = !!edicao && obrigatorios.every((c) => valor(c.k).trim() !== '');
  const salvar = async () => {
    if (!edicao || !podeSalvar) return;
    setSalvando(true); setErro(null);
    const r = await gravar(edicao.id, edicao.dados, formas);
    setSalvando(false);
    if (!r.success) { setErro(r.msg || 'Não foi possível salvar o local.'); return; }
    setEdicao(null);
    setAviso(r.msg ?? null);
    router.reload({ only: ['locais'] });
  };

  const termo = q.trim().toLowerCase();
  const lista = useMemo(
    () => locais.filter((l) => !termo || [l.nome, l.referencia, l.cidade, l.cnpj].some((v) => v.toLowerCase().includes(termo))),
    [locais, termo],
  );
  const ativos = locais.filter((l) => l.ativo).length;

  const ativarOuDesativar = async (l: Local) => {
    const r = await alternar(l.id);
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['locais'] });
  };

  const colunas: ColumnDef<Local, unknown>[] = [
    {
      id: 'nome', header: 'Local',
      cell: ({ row: { original: l } }) => (
        <Stack gap={0}>
          <Inline gap={2}><span className="font-medium">{l.nome}</span>{!l.ativo && <Badge variant="outline">inativo</Badge>}</Inline>
          <span className="text-xs text-muted-foreground">{l.referencia || '—'}{l.cidade ? ` · ${l.cidade}` : ''}</span>
        </Stack>
      ),
    },
    { id: 'cnpj', header: 'CNPJ', meta: { mono: true }, cell: ({ row: { original: l } }) => l.cnpj || '—' },
    { id: 'tabela', header: 'Tabela de preço', cell: ({ row: { original: l } }) => l.tabela ?? 'Padrão' },
    { id: 'esquema', header: 'Esquema de fatura', cell: ({ row: { original: l } }) => l.esquema ?? '—' },
    {
      id: 'layouts', header: 'Layouts (PDV · venda)',
      cell: ({ row: { original: l } }) => <span className="text-muted-foreground">{l.layout_pdv ?? '—'} · {l.layout_venda ?? l.layout_pdv ?? '—'}</span>,
    },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: l } }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${l.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem disabled={!opcoes} onSelect={() => { setErro(null); setEdicao({ id: l.id, dados: { ...l.dados } }); }}>Editar local</DropdownMenuItem>
            <DropdownMenuItem className={l.ativo ? 'text-destructive' : undefined} onSelect={() => ativarOuDesativar(l)}>
              {l.ativo ? 'Desativar local' : 'Ativar local'}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Locais comerciais"
          subtitle={locaisProp ? <><strong>{ativos}</strong> {ativos === 1 ? 'local ativo' : 'locais ativos'} de {locais.length}</> : 'filiais do negócio'}
          subnav={<ConfiguracoesSubNav />}
          actions={<Button disabled={!opcoes} onClick={novo}><Plus className="size-4" /> Novo local</Button>} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar local, referência, cidade ou CNPJ…" aria-label="Buscar local" />
          </div>
        </Inline>
        {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}

        <Deferred data="locais" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando locais…</p>}>
          {locais.length > 0 ? (
            <div data-contract="locais-table">
              <DataTable columns={colunas} data={lista} caption="Locais comerciais do negócio" rowKey={(l) => l.id}
                emptyMessage="Nenhum local com esse termo. Limpe a busca para ver todos." />
            </div>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhum local que você possa ver" description="Sem acesso a todos os locais, a lista mostra só os liberados para você." />
            </div>
          )}
        </Deferred>
      </Stack>

      <Sheet open={!!edicao} onOpenChange={(v) => !v && setEdicao(null)}>
        <SheetContent className="w-full overflow-y-auto p-0 sm:max-w-xl">
          <header className="os-drawer-head">
            <div className="os-drawer-head-l">
              <SheetTitle asChild><h2 className="m-0">{edicao?.id ? `Editar ${valor('name')}` : 'Novo local'}</h2></SheetTitle>
              <SheetDescription asChild><p>{edicao?.id ? 'Filial do negócio.' : 'Cadastrar cria também a permissão do local nas Funções.'}</p></SheetDescription>
            </div>
          </header>
          {edicao && opcoes && (
            <Stack data-contract="local-form" gap={4} className="p-5 text-sm">
              {SECOES.map((sec) => (
                <Stack key={sec.titulo} gap={2}>
                  <h3 className="m-0 text-xs font-semibold uppercase text-muted-foreground">{sec.titulo}</h3>
                  <Grid cols={2} gap={3}>
                    {sec.campos.map((c) => (
                      <Stack key={c.k} gap={1}>
                        <label htmlFor={`loc-${c.k}`}>{c.rotulo}{c.obrigatorio ? ' *' : ''}</label>
                        {c.opcoes ? (
                          <Select value={valor(c.k) || NENHUM} onValueChange={(v) => muda(c.k, v === NENHUM ? '' : v)}>
                            <SelectTrigger id={`loc-${c.k}`}><SelectValue /></SelectTrigger>
                            <SelectContent>
                              <SelectItem value={NENHUM}>{c.obrigatorio ? 'Escolha…' : 'Padrão'}</SelectItem>
                              {Object.entries(opcoes[c.opcoes]).filter(([k]) => k).map(([k, l]) => <SelectItem key={k} value={k}>{l}</SelectItem>)}
                            </SelectContent>
                          </Select>
                        ) : (
                          <Input id={`loc-${c.k}`} value={valor(c.k)} maxLength={c.k === 'zip_code' ? 7 : undefined} onChange={(e) => muda(c.k, e.target.value)} />
                        )}
                        {c.ajuda && <span className="text-xs text-muted-foreground">{c.ajuda}</span>}
                      </Stack>
                    ))}
                  </Grid>
                </Stack>
              ))}
              <Stack gap={2}>
                <h3 className="m-0 text-xs font-semibold uppercase text-muted-foreground">Campos personalizados</h3>
                <Grid cols={2} gap={3}>
                  {PERSONALIZADOS.map((k) => (
                    <Stack key={k} gap={1}>
                      <label htmlFor={`loc-${k}`}>{opcoes.rotulos[k] ?? k}</label>
                      <Input id={`loc-${k}`} value={valor(k)} onChange={(e) => muda(k, e.target.value)} />
                    </Stack>
                  ))}
                </Grid>
              </Stack>
              <Stack gap={2}>
                <h3 className="m-0 text-xs font-semibold uppercase text-muted-foreground">Formas de pagamento no PDV</h3>
                {formas.map((f) => {
                  const p = edicao.dados.default_payment_accounts[f] ?? {};
                  const contas = Object.entries(opcoes.contas).filter(([k]) => k);
                  return (
                    <Inline key={f} gap={3}>
                      <Checkbox id={`loc-pag-${f}`} checked={!!Number(p.is_enabled ?? 0)} onCheckedChange={(v) => mudaPagamento(f, 'is_enabled', v === true ? '1' : '')} />
                      <label htmlFor={`loc-pag-${f}`} className="flex-1">{opcoes.formas[f]}</label>
                      {contas.length > 0 && (
                        <Select value={p.account ? String(p.account) : NENHUM} onValueChange={(v) => mudaPagamento(f, 'account', v === NENHUM ? '' : v)}>
                          <SelectTrigger aria-label={`Conta padrão de ${opcoes.formas[f]}`} className="w-48"><SelectValue /></SelectTrigger>
                          <SelectContent>
                            <SelectItem value={NENHUM}>Sem conta</SelectItem>
                            {contas.map(([k, l]) => <SelectItem key={k} value={k}>{l}</SelectItem>)}
                          </SelectContent>
                        </Select>
                      )}
                    </Inline>
                  );
                })}
              </Stack>
              {edicao.dados.featured_products.length > 0 && (
                <p className="text-xs text-muted-foreground">
                  {edicao.dados.featured_products.length} produto(s) em destaque no PDV continuam como estão. A escolha deles ainda é feita na tela antiga.
                </p>
              )}
              {erro && <p role="alert" className="text-destructive">{erro}</p>}
              <Inline gap={2} justify="end" className="border-t pt-4">
                <Button variant="ghost" onClick={() => setEdicao(null)}>Cancelar</Button>
                <Button disabled={!podeSalvar || salvando} onClick={salvar}>{edicao.id ? 'Salvar' : 'Cadastrar'}</Button>
              </Inline>
            </Stack>
          )}
        </SheetContent>
      </Sheet>
    </div>
  );
}

LocaisIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default LocaisIndex;
