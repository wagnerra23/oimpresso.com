// @memcofre
//   tela: /connector/client
//   module: Connector
//   stories: US-CONN-001 · US-CONN-013 · thread Connector/04 (CONN-O3 · Blade → Inertia)
//   permissao: superadmin
//
// Credenciais OAuth (password grant) dos apps externos do negócio — WR Comercial, app do técnico.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · Contrato: governance/design/contracts/connector-api.contract.json
// Âncora de design: prototipo-ui/cowork/Wagner/connector-page.jsx → ClientsView()
//
// PR-a entregou a aba de clients (lista, criar, excluir); o PR-b, as abas Documentação, Saúde e
// Módulo (`_components/ConnectorAbas.tsx`). A aba vem de `?aba=` — é o que o menu usa para abrir
// a Documentação direto. O segredo nunca vem na lista ([W] D6): só no flash da criação,
// mostrado uma vez num bloco copiável que fica até o usuário fechar.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Copy, MoreHorizontal, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import KpiCard from '@/Components/shared/KpiCard';
import KpiGrid from '@/Components/shared/KpiGrid';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import { DocsAba, ModuloAba, SaudeAba, type Endpoint, type Modulo } from './_components/ConnectorAbas';

interface Client { id: number; name: string; user_name: string; created_at: string | null; active_tokens_24h: number }
interface Credencial { id: number; name: string; secret: string }
interface Props {
  clients: Client[]; is_demo: boolean; endpoints_count: number; credencial: Credencial | null;
  endpoints: Endpoint[]; modulo: Modulo;
}

const ABAS = [
  { key: 'clients', label: 'API clients', titulo: 'Conector — API clients' },
  { key: 'docs', label: 'Documentação', titulo: 'Conector — documentação da API' },
  { key: 'saude', label: 'Saúde', titulo: 'Conector — saúde' },
  { key: 'modulo', label: 'Módulo', titulo: 'Conector — módulo' },
] as const;
type Aba = (typeof ABAS)[number]['key'];
const abaDaUrl = (): Aba => {
  const a = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('aba');
  return ABAS.some((x) => x.key === a) ? (a as Aba) : 'clients';
};

const MSG_OBRIGATORIO = 'O nome do client OAuth é obrigatório.';
const MSG_TETO = 'O nome do client não pode ultrapassar 191 caracteres.';
const dataCurta = (iso: string | null) => (iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('pt-BR') : '—');
const copiar = (txt: string) => { try { void navigator.clipboard?.writeText(txt); } catch { /* sem clipboard: o valor segue na tela */ } };

function ApiIndex({ clients, is_demo, endpoints_count, credencial, endpoints, modulo }: Props) {
  const [aba, setAba] = useState<Aba>(abaDaUrl);
  const trocarAba = (k: string) => {
    setAba(k as Aba);
    try { window.history.replaceState(window.history.state, '', k === 'clients' ? window.location.pathname : `?aba=${k}`); } catch { /* sem history: a aba troca igual */ }
  };
  const [q, setQ] = useState('');
  const [novo, setNovo] = useState(false);
  const [excluir, setExcluir] = useState<Client | null>(null);
  const [criada, setCriada] = useState<Credencial | null>(credencial);
  const busca = useRef<HTMLInputElement>(null);
  useEffect(() => setCriada(credencial), [credencial]);

  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
      if (e.key === 'n' && !is_demo && aba === 'clients') { e.preventDefault(); setNovo(true); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, [is_demo, aba]);

  const termo = q.trim().toLowerCase();
  const lista = useMemo(() => clients.filter((c) => !termo || `${c.name} ${c.id}`.toLowerCase().includes(termo)), [clients, termo]);
  const tokens = clients.reduce((n, c) => n + c.active_tokens_24h, 0);

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title={ABAS.find((x) => x.key === aba)?.titulo ?? 'Conector — API clients'} subtitle={<>{endpoints_count} endpoints · OAuth do Passport · 120 req/min por token</>}
          actions={<span className="text-xs text-muted-foreground">superadmin · cross-tenant</span>} />
      </div>
      <div data-contract="tabs" className="px-6 pt-2">
        <PageHeaderTabs group="sistema" activeGhostKey={aba} onGhostChange={trocarAba}
          ghosts={ABAS.map((x) => ({ key: x.key, label: x.label, href: x.key === 'clients' ? '/connector/client' : `/connector/client?aba=${x.key}` }))} />
      </div>
      {aba === 'docs' && <div className="px-6 pt-4"><DocsAba endpoints={endpoints} /></div>}
      {aba === 'saude' && <div className="px-6 pt-4"><SaudeAba tokens24h={clients.reduce((n, c) => n + c.active_tokens_24h, 0)} rotas={endpoints_count} /></div>}
      {aba === 'modulo' && <div className="px-6 pt-4"><ModuloAba modulo={modulo} rotas={endpoints_count} /></div>}
      {aba === 'clients' && <div className="flex flex-col gap-4 px-6 pt-4">
        {is_demo ? (
          <div data-contract="aviso-demo" className="rounded-md border border-warning/30 bg-warning/5 p-3 text-sm">
            <b>Desligado na demonstração</b> — nenhuma credencial é listada e nada pode ser emitido nesta base.
          </div>
        ) : (
          <div data-contract="aviso-permissao" className="rounded-md border border-info/30 bg-info/5 p-3 text-sm">
            <b>Só superadmin emite credencial de API</b> — emitir e revogar acesso de app externo não se delega.
          </div>
        )}

        <KpiGrid cols={4} data-contract="kpis">
          <KpiCard label="Clients ativos" value={clients.length} description="somente deste negócio" />
          <KpiCard label="Tokens ativos em 24 h" value={tokens} description="emitidos pelo Passport" />
          <KpiCard label="Endpoints publicados" value={endpoints_count} description="prefixo connector/api" />
          <KpiCard label="Limite por token" value="120 req/min" description="throttle da API externa" />
        </KpiGrid>

        <div data-contract="toolbar" className="flex flex-wrap items-center gap-2">
          <div className="relative min-w-64 flex-1">
            <Search className="absolute left-2 top-2.5 size-4 text-muted-foreground" />
            <Input ref={busca} className="pl-8" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar client por nome ou id…" aria-label="Buscar client" />
          </div>
          <span className="text-xs text-muted-foreground"><kbd>n</kbd> novo client · <kbd>/</kbd> buscar</span>
          <Button disabled={is_demo} onClick={() => setNovo(true)}>Criar API client</Button>
        </div>

        {criada && (
          <div data-contract="credencial-criada" className="rounded-md border border-warning/40 bg-warning/5 p-4 text-sm">
            <div className="flex items-center justify-between"><b>Client “{criada.name}” criado</b>
              <Button variant="ghost" size="sm" onClick={() => setCriada(null)}>Fechar</Button></div>
            {([['client_id', String(criada.id)], ['client_secret', criada.secret]] as const).map(([rotulo, valor]) => (
              <div key={rotulo} className="mt-2 flex items-center gap-2">
                <span className="w-28 text-xs text-muted-foreground">{rotulo}</span>
                <code className="select-all break-all font-mono">{valor}</code>
                <Button variant="ghost" size="sm" aria-label={`Copiar ${rotulo}`} onClick={() => copiar(valor)}><Copy className="size-3.5" /></Button>
              </div>
            ))}
            <p className="mt-2 text-warning">Esta é a única vez que o segredo aparece. Copie e guarde no app: nenhuma tela mostra esse valor de novo — nem para o administrador. Perdeu, emite outra e exclui esta.</p>
          </div>
        )}

        {lista.length > 0 ? (
          <table data-contract="clients-table" className="w-full text-sm">
            <thead><tr className="border-b text-left text-xs text-muted-foreground">
              <th className="py-2">ID</th><th>Nome</th><th>Segredo</th><th className="text-right">Tokens 24 h</th><th>Criado</th><th />
            </tr></thead>
            <tbody>
              {lista.map((c) => (
                <tr key={c.id} className="border-b">
                  <td className="py-2 font-mono">{c.id}</td>
                  <td><div className="font-medium">{c.name}</div><div className="text-xs text-muted-foreground">por {c.user_name} · redirecionamento http://localhost</div></td>
                  <td><span className="font-mono text-xs">•••• guardado</span> <span className="text-xs text-muted-foreground">não é exibível</span></td>
                  <td className="text-right tabular-nums">{c.active_tokens_24h > 0 ? c.active_tokens_24h : '—'}</td>
                  <td className="tabular-nums">{dataCurta(c.created_at)}</td>
                  <td className="text-right">
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label="Ações do client"><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onSelect={() => copiar(String(c.id))}>Copiar o ID do client</DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => setNovo(true)}>Emitir credencial nova</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem className="text-destructive" onSelect={() => setExcluir(c)}>Excluir client</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <div data-contract="vazio">
            {is_demo ? (
              <EmptyState title="Credenciais desligadas na demonstração" description="Nesta base pública nenhuma credencial de API é listada nem emitida." />
            ) : clients.length === 0 ? (
              <EmptyState title="Nenhuma credencial emitida ainda"
                description="Um API client é a credencial que um programa de fora usa pra entrar aqui — o WR Comercial no balcão, o aplicativo do técnico. Cada um recebe o seu, pra você poder cortar um sem derrubar os outros."
                action={<Button onClick={() => setNovo(true)}>Criar o primeiro API client</Button>} />
            ) : (
              <EmptyState variant="search" title={`Nenhum client casa com “${q}”`} description="A busca cobre nome e id."
                action={<Button variant="outline" onClick={() => setQ('')}>Limpar busca</Button>} />
            )}
          </div>
        )}
      </div>}

      <NovoClient open={novo} onClose={() => setNovo(false)} nomes={clients.map((c) => c.name)} />

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader><AlertDialogTitle>Excluir este API client?</AlertDialogTitle></AlertDialogHeader>
          <p className="text-sm">O client <b>{excluir?.name}</b> sai da lista e ninguém mais consegue pedir token novo com ele.</p>
          <p className="text-sm text-warning">{excluir && excluir.active_tokens_24h > 0
            ? `Os acessos abertos com esta credencial caem na hora (${excluir.active_tokens_24h} usados nas últimas 24 h) — a exclusão revoga os tokens junto.`
            : 'Nenhum acesso usado nas últimas 24 h — os tokens que existirem são revogados junto.'}</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90"
              onClick={() => excluir && router.delete(`/connector/client/${excluir.id}`, { preserveScroll: true })}>Excluir client</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

// StoreOauthClientRequest: name obrigatório, até 191. As mensagens são as do servidor.
function NovoClient({ open, onClose, nomes }: { open: boolean; onClose: () => void; nomes: string[] }) {
  const form = useForm({ name: '' });
  const [tentou, setTentou] = useState(false);
  useEffect(() => { if (open) { form.reset(); form.clearErrors(); setTentou(false); } }, [open]); // eslint-disable-line react-hooks/exhaustive-deps
  const limpo = form.data.name.trim();
  const erro = limpo.length === 0 ? MSG_OBRIGATORIO : limpo.length > 191 ? MSG_TETO : null;
  const dup = !erro && nomes.some((n) => n.toLowerCase() === limpo.toLowerCase());
  const salvar = () => {
    if (erro) { setTentou(true); return; }
    form.post('/connector/client', { preserveScroll: true, onSuccess: onClose });
  };

  return (
    <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
      <DialogContent>
        <DialogHeader><DialogTitle>Criar API client</DialogTitle></DialogHeader>
        <div data-contract="novo-client-form" className="flex flex-col gap-2 text-sm">
          <label htmlFor="cnx-nome" className="font-medium">Nome</label>
          <Input id="cnx-nome" autoFocus value={form.data.name} onChange={(e) => form.setData('name', e.target.value)}
            placeholder="Onde essa credencial vai rodar" aria-invalid={tentou && !!erro ? true : undefined} />
          {((tentou && erro) || form.errors.name) && <p className="text-xs text-destructive">{form.errors.name ?? erro}</p>}
          {dup && <p className="text-xs text-warning">Já existe um client com esse nome. O sistema aceita duplicado — depois ninguém sabe qual revogar.</p>}
          <dl className="grid grid-cols-[8rem_1fr] gap-1 text-xs">
            <dt className="text-muted-foreground">Redirecionamento</dt><dd className="font-mono">http://localhost</dd>
            <dt className="text-muted-foreground">Tipo</dt><dd>client de senha (o app troca usuário e senha por token)</dd>
          </dl>
          <p className="text-xs text-muted-foreground">Ao salvar, o segredo aparece <b>uma única vez</b>: depois disso nenhuma tela mostra esse valor — nem para você. A credencial continua valendo no app.</p>
        </div>
        <DialogFooter>
          <Button variant="ghost" onClick={onClose}>Cancelar</Button>
          <Button onClick={salvar} disabled={form.processing}>Salvar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

ApiIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ApiIndex;
