// @memcofre
//   tela: /team-mcp/team
//   module: TeamMcp (split do Copiloto, ADR pendente)
//   stories: MEM-TEAM-1 (ADR 0055) — self-host equivalent Anthropic Team plan admin
//   adrs: 0053, 0055
//   tests: TODO
//   permissao: copiloto.mcp.usage.all
//
// Admin console pra Wagner gerenciar:
//   - Lista de devs com tokens MCP ativos
//   - Custo hoje/mês + % das quotas
//   - Top tools/skills usadas
//   - Último uso MCP
//   - Actions: gerar/revogar token, editar quota, export CSV

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, Deferred } from '@inertiajs/react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Badge } from '@/Components/ui/badge';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { toast } from 'sonner';

interface QuotaInfo {
  id: number;
  limit: number;
  block: boolean;
  pct_atingido: number;
}

interface TeamMember {
  id: number;
  nome: string;
  email: string;
  tokens_ativos: number;
  custo_hoje_brl: number;
  custo_mes_brl: number;
  calls_hoje: number;
  calls_mes: number;
  quota_diaria: QuotaInfo | null;
  quota_mensal: QuotaInfo | null;
  top_tools: Array<{ tool: string; count: number }>;
  ultimo_uso_mcp: string | null;
}

interface StatsGlobais {
  custo_hoje_brl: number;
  custo_mes_brl: number;
  usuarios_ativos_hoje: number;
  calls_hoje: number;
}

interface Props {
  // W27 D6: backend devolve via Inertia::defer (TeamController:54) — frontend
  // recebe undefined no primeiro paint e dado real após resolve. UI usa
  // <Deferred> wrapper pra evitar crash de map/length em undefined.
  team?: TeamMember[];
  stats_globais?: StatsGlobais;
  pricing_config: { modelo_default: string; cambio_brl_usd: number };
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v ?? 0);

function fmtDate(iso: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

// G-DESIGN-04 — relativo PT-BR pra last_used_at no TokensListDialog
function fmtRelative(iso: string | null): string {
  if (!iso) return 'Nunca usado';
  const d = new Date(iso).getTime();
  const diff = (Date.now() - d) / 1000;
  if (diff < 60) return 'agora há pouco';
  if (diff < 3600) return `há ${Math.floor(diff / 60)} min`;
  if (diff < 86400) return `há ${Math.floor(diff / 3600)} h`;
  if (diff < 86400 * 30) return `há ${Math.floor(diff / 86400)} dias`;
  if (diff < 86400 * 365) return `há ${Math.floor(diff / (86400 * 30))} meses`;
  return `há ${Math.floor(diff / (86400 * 365))} anos`;
}

// G-DESIGN-05 — status pill semântico por token (FICHA CAPTERRA 2026-05-25)
interface TokenRow {
  id: number;
  name: string;
  created_at: string | null;
  expires_at: string | null;
  revoked_at: string | null;
  last_used_at: string | null;
  last_used_ip: string | null;
}

function tokenStatus(t: TokenRow): { label: string; className: string } {
  if (t.revoked_at) {
    return { label: 'Revogado', className: 'bg-muted text-muted-foreground' };
  }
  const now = Date.now();
  if (t.expires_at) {
    const expMs = new Date(t.expires_at).getTime();
    if (expMs < now) {
      return { label: 'Expirado', className: 'bg-muted text-muted-foreground' };
    }
    const days = Math.ceil((expMs - now) / 86400000);
    if (days <= 7) {
      return {
        label: `Expira em ${days}d`,
        className: 'bg-yellow-100 text-yellow-800 border-yellow-200',
      };
    }
  }
  return { label: 'Ativo', className: 'bg-green-100 text-green-800' };
}

function quotaBadge(pct: number, block: boolean): { className: string; label: string } {
  if (pct >= 100) return { className: 'bg-red-100 text-red-800', label: block ? '🚫 BLOQUEADO' : '⚠️ excedido' };
  if (pct >= 80)  return { className: 'bg-orange-100 text-orange-800', label: '⚠️ ' + pct + '%' };
  if (pct >= 50)  return { className: 'bg-yellow-100 text-yellow-800', label: pct + '%' };
  return { className: 'bg-green-100 text-green-800', label: pct + '%' };
}

function TeamIndex(props: Props) {
  // W27 D6: defaults sentinela enquanto props deferred resolvem
  const team = props.team ?? [];
  const stats_globais = props.stats_globais ?? {
    custo_hoje_brl: 0,
    custo_mes_brl: 0,
    usuarios_ativos_hoje: 0,
    calls_hoje: 0,
  };
  const { pricing_config } = props;
  const [tokenGerado, setTokenGerado] = useState<{ user: string; raw: string } | null>(null);
  const [editQuotaUser, setEditQuotaUser] = useState<TeamMember | null>(null);
  // G-DESIGN-01/02/03 — drill-down lista tokens + AlertDialog destrutivo unificado
  const [tokensListUser, setTokensListUser] = useState<TeamMember | null>(null);
  const [confirmAction, setConfirmAction] = useState<{
    title: string;
    description: string;
    confirmLabel: string;
    onConfirm: () => void;
  } | null>(null);

  function gerarToken(member: TeamMember) {
    setConfirmAction({
      title: `Gerar token MCP novo pra ${member.nome}?`,
      description:
        'O token novo terá acesso a 107 docs de memória + 56 ADRs + chat Copiloto. ' +
        'Entregue ao dev via Vaultwarden imediatamente — o raw NÃO será mostrado de novo. ' +
        'Esta ação não pode ser desfeita.',
      confirmLabel: 'Gerar token',
      onConfirm: () => doGerarToken(member),
    });
  }

  function doGerarToken(member: TeamMember) {
    fetch(`/team-mcp/team/${member.id}/token`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ note: `Gerado em ${new Date().toLocaleDateString('pt-BR')}` }),
    })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          setTokenGerado({ user: member.nome, raw: data.token_raw });
          router.reload({ only: ['team'] });
        } else {
          toast.error(data.message ?? 'Erro ao gerar token');
        }
      })
      .catch(() => toast.error('Erro de rede'));
  }

  function gerarDxt(member: TeamMember) {
    setConfirmAction({
      title: `Gerar arquivo .dxt pra ${member.nome}?`,
      description:
        'Cria token MCP novo embutido no .dxt (Claude Desktop). Entregue o arquivo via ' +
        'Vaultwarden ou canal seguro — quem tiver o .dxt tem acesso completo ao MCP server. ' +
        'Esta ação não pode ser desfeita.',
      confirmLabel: 'Gerar .dxt',
      onConfirm: () => { void doGerarDxt(member); },
    });
  }

  async function doGerarDxt(member: TeamMember) {
    try {
      const res = await fetch(`/team-mcp/team/${member.id}/dxt`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      if (!res.ok) { toast.error('Erro ao gerar .dxt'); return; }
      const blob = await res.blob();
      const cd = res.headers.get('Content-Disposition') ?? '';
      const m = /filename="([^"]+)"/.exec(cd);
      const filename = m?.[1] ?? `oimpresso-mcp-${member.id}.dxt`;
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      router.reload({ only: ['team'] });
      toast.success(`.dxt baixado pra ${member.nome} — entrega via Vaultwarden`);
    } catch {
      toast.error('Erro de rede ao gerar .dxt');
    }
  }

  function exportarCsv() {
    const periodo = prompt('Período (de,ate em YYYY-MM-DD,YYYY-MM-DD ou ENTER pra mês corrente):', '');
    let url = '/team-mcp/team/export.csv';
    if (periodo) {
      const [de, ate] = periodo.split(',').map(s => s.trim());
      if (de && ate) url += `?de=${de}&ate=${ate}`;
    }
    window.location.href = url;
  }

  return (
    <>

      <PageHeader
        icon="users"
        title="Team Admin"
        description={`Equivalente self-host Anthropic Team plan — modelo ${pricing_config.modelo_default}, câmbio R$ ${pricing_config.cambio_brl_usd.toFixed(2)}`}
        action={
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={exportarCsv}>
              📊 Export CSV
            </Button>
          </div>
        }
      />

      {/* W27 D6 — KPIs globais via Inertia::defer (backend TeamController:55) */}
      <Deferred
        data="stats_globais"
        fallback={
          <KpiGrid cols={4} className="mt-6">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="h-24 rounded-lg bg-muted/40 animate-pulse" />
            ))}
          </KpiGrid>
        }
      >
        <KpiGrid cols={4} className="mt-6">
          <KpiCard
            icon="users"
            tone="info"
            label="Devs ativos hoje"
            value={num(stats_globais.usuarios_ativos_hoje)}
            description={`de ${team.length} no time`}
          />
          <KpiCard
            icon="activity"
            tone="default"
            label="Calls MCP hoje"
            value={num(stats_globais.calls_hoje)}
          />
          <KpiCard
            icon="dollar-sign"
            tone="warning"
            label="Custo hoje"
            value={brl(stats_globais.custo_hoje_brl)}
          />
          <KpiCard
            icon="dollar-sign"
            tone="success"
            label="Custo mês"
            value={brl(stats_globais.custo_mes_brl)}
            description={`média ${brl(stats_globais.custo_mes_brl / Math.max(1, new Date().getDate()))} /dia`}
          />
        </KpiGrid>
      </Deferred>

      {/* W27 D6 — Tabela team via Inertia::defer (N×6 queries cada row) */}
      <Card className="mt-6">
        <CardHeader>
          <CardTitle>Time ({team.length} devs)</CardTitle>
          <CardDescription>
            Tokens MCP ativos, custo hoje/mês, quotas, top tools, último uso
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Deferred
            data="team"
            fallback={
              <div className="space-y-2 py-4">
                <div className="text-xs text-muted-foreground">Carregando time…</div>
                {[1, 2, 3, 4, 5].map((i) => (
                  <div key={i} className="h-10 rounded bg-muted/40 animate-pulse" />
                ))}
              </div>
            }
          >
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b">
                  <th className="text-left py-2 px-2 font-medium">Dev</th>
                  <th className="text-center py-2 px-2 font-medium">Tokens</th>
                  <th className="text-right py-2 px-2 font-medium">Hoje (R$)</th>
                  <th className="text-right py-2 px-2 font-medium">Mês (R$)</th>
                  <th className="text-center py-2 px-2 font-medium">Quota dia</th>
                  <th className="text-center py-2 px-2 font-medium">Quota mês</th>
                  <th className="text-left py-2 px-2 font-medium">Top tools</th>
                  <th className="text-left py-2 px-2 font-medium">Último uso</th>
                  <th className="text-right py-2 px-2 font-medium">Ações</th>
                </tr>
              </thead>
              <tbody>
                {team.map((m) => (
                  <tr key={m.id} className="border-b hover:bg-muted/40">
                    <td className="py-2 px-2">
                      <div className="font-medium">{m.nome}</div>
                      <div className="text-xs text-muted-foreground">{m.email}</div>
                    </td>
                    <td className="text-center py-2 px-2">
                      {/* G-DESIGN-01: contador clicável abre TokensListDialog (drill-down) */}
                      <button
                        type="button"
                        onClick={() => setTokensListUser(m)}
                        className="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800 hover:bg-green-200 hover:underline disabled:opacity-60"
                        aria-label={`Ver ${m.tokens_ativos} tokens de ${m.nome}`}
                        title={m.tokens_ativos > 0
                          ? `Ver ${m.tokens_ativos} token${m.tokens_ativos > 1 ? 's' : ''} de ${m.nome}`
                          : `Ver histórico de tokens de ${m.nome}`}
                      >
                        {m.tokens_ativos > 0
                          ? `${m.tokens_ativos} ativo${m.tokens_ativos > 1 ? 's' : ''}`
                          : 'ver'}
                      </button>
                    </td>
                    <td className="text-right py-2 px-2 font-mono">{brl(m.custo_hoje_brl)}</td>
                    <td className="text-right py-2 px-2 font-mono">{brl(m.custo_mes_brl)}</td>
                    <td className="text-center py-2 px-2">
                      {m.quota_diaria ? (
                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${quotaBadge(m.quota_diaria.pct_atingido, m.quota_diaria.block).className}`}>
                          {brl(m.quota_diaria.limit)} · {quotaBadge(m.quota_diaria.pct_atingido, m.quota_diaria.block).label}
                        </span>
                      ) : (
                        <button
                          className="text-xs text-blue-600 hover:underline"
                          onClick={() => setEditQuotaUser(m)}
                        >
                          definir
                        </button>
                      )}
                    </td>
                    <td className="text-center py-2 px-2">
                      {m.quota_mensal ? (
                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${quotaBadge(m.quota_mensal.pct_atingido, m.quota_mensal.block).className}`}>
                          {brl(m.quota_mensal.limit)} · {quotaBadge(m.quota_mensal.pct_atingido, m.quota_mensal.block).label}
                        </span>
                      ) : (
                        <button
                          className="text-xs text-blue-600 hover:underline"
                          onClick={() => setEditQuotaUser(m)}
                        >
                          definir
                        </button>
                      )}
                    </td>
                    <td className="py-2 px-2 text-xs">
                      {m.top_tools.length > 0
                        ? m.top_tools.slice(0, 3).map(t => `${t.tool} (${t.count})`).join(', ')
                        : '—'}
                    </td>
                    <td className="py-2 px-2 text-xs text-muted-foreground">
                      {fmtDate(m.ultimo_uso_mcp)}
                    </td>
                    <td className="text-right py-2 px-2">
                      <div className="flex gap-1 justify-end">
                        <Button
                          variant="default" size="sm"
                          onClick={() => gerarDxt(m)}
                          className="text-xs"
                          title="Gera token + arquivo .dxt pro Claude Desktop"
                        >
                          📦 + DXT
                        </Button>
                        <Button
                          variant="outline" size="sm"
                          onClick={() => gerarToken(m)}
                          className="text-xs"
                          title="Gera token raw (Claude Code CLI / setup manual)"
                        >
                          + Token
                        </Button>
                        <Button
                          variant="ghost" size="sm"
                          onClick={() => setEditQuotaUser(m)}
                          className="text-xs"
                        >
                          ⚙️
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          </Deferred>
        </CardContent>
      </Card>

      {/* Modal: token gerado (mostra raw 1 vez) */}
      <Dialog open={tokenGerado !== null} onOpenChange={(o) => !o && setTokenGerado(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Token gerado pra {tokenGerado?.user}</DialogTitle>
            <DialogDescription>
              <strong>COPIE AGORA</strong> — não será mostrado de novo. Apenas o hash fica gravado.
            </DialogDescription>
          </DialogHeader>
          <div className="my-4">
            <Label>Token (Bearer pra header Authorization)</Label>
            <Input
              readOnly
              value={tokenGerado?.raw ?? ''}
              className="font-mono text-xs"
              onClick={(e) => (e.target as HTMLInputElement).select()}
            />
            <p className="text-xs text-muted-foreground mt-2">
              Entrega via Vaultwarden ou canal seguro. Setup do dev:<br/>
              <code className="block mt-1 p-2 bg-muted rounded text-xs whitespace-pre">
{`# .claude/settings.local.json:
{ "mcpServers": { "oimpresso": {
  "url": "https://mcp.oimpresso.com/api/mcp",
  "headers": { "Authorization": "Bearer ${tokenGerado?.raw ?? '<TOKEN>'}" }
}}}`}
              </code>
            </p>
          </div>
          <DialogFooter>
            <Button onClick={() => {
              if (tokenGerado?.raw) {
                navigator.clipboard.writeText(tokenGerado.raw);
                toast.success('Copiado pro clipboard');
              }
            }}>
              📋 Copiar
            </Button>
            <Button variant="outline" onClick={() => setTokenGerado(null)}>Fechar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Modal: editar quota */}
      <Dialog open={editQuotaUser !== null} onOpenChange={(o) => !o && setEditQuotaUser(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Quota — {editQuotaUser?.nome}</DialogTitle>
            <DialogDescription>
              Define limite de gasto MCP. Quando atingir, MCP retorna 429 (se block_on_exceed=true).
            </DialogDescription>
          </DialogHeader>
          {editQuotaUser && (
            <QuotaForm
              user={editQuotaUser}
              onClose={() => {
                setEditQuotaUser(null);
                router.reload({ only: ['team'] });
              }}
            />
          )}
        </DialogContent>
      </Dialog>

      {/* G-DESIGN-01/02/04/05 — drill-down tokens individuais (FICHA CAPTERRA) */}
      {tokensListUser && (
        <TokensListDialog
          user={tokensListUser}
          onClose={() => setTokensListUser(null)}
          requestConfirm={(action) => setConfirmAction(action)}
          afterRevoke={() => router.reload({ only: ['team'] })}
        />
      )}

      {/* G-DESIGN-03 — AlertDialog destrutivo (substitui window.confirm + prompt) */}
      <AlertDialog open={confirmAction !== null} onOpenChange={(o) => !o && setConfirmAction(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{confirmAction?.title}</AlertDialogTitle>
            <AlertDialogDescription>{confirmAction?.description}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              onClick={() => {
                confirmAction?.onConfirm();
                setConfirmAction(null);
              }}
            >
              {confirmAction?.confirmLabel ?? 'Confirmar'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

// ============================================================================
// G-DESIGN-01/02/04/05 — TokensListDialog (FICHA CAPTERRA 2026-05-25)
//
// Drill-down do contador "N ativos" da tabela team. Lista tokens individuais
// do dev com colunas name/created_at/expires_at/last_used_at/last_used_ip/
// status/action. Empty state se 0 tokens. Revoke por token com AlertDialog
// reusando o confirmAction global (G-DESIGN-03).
//
// Multi-tenant Tier 0 (ADR 0093): backend listTokens já filtra por business_id
// — frontend só renderiza. Reveal-once (ADR 0057 §2): apenas metadados.
// ============================================================================
function TokensListDialog({
  user,
  onClose,
  requestConfirm,
  afterRevoke,
}: {
  user: TeamMember;
  onClose: () => void;
  requestConfirm: (a: {
    title: string;
    description: string;
    confirmLabel: string;
    onConfirm: () => void;
  }) => void;
  afterRevoke: () => void;
}) {
  const [tokens, setTokens] = useState<TokenRow[] | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    setError(null);
    fetch(`/team-mcp/team/${user.id}/tokens`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.ok) {
          setTokens(data.tokens as TokenRow[]);
        } else {
          setError(data.message ?? 'Erro ao carregar tokens');
        }
      })
      .catch(() => setError('Erro de rede ao carregar tokens'))
      .finally(() => setLoading(false));
  }, [user.id]);

  useEffect(() => { load(); }, [load]);

  function revoke(t: TokenRow) {
    requestConfirm({
      title: `Revogar token "${t.name}" de ${user.nome}?`,
      description:
        'O dev perde acesso ao MCP server imediatamente — cortará uso ativo (se houver). ' +
        'O hash fica preservado no audit log (LGPD). Esta ação não pode ser desfeita.',
      confirmLabel: 'Revogar token',
      onConfirm: () => {
        fetch(`/team-mcp/team/${user.id}/token/${t.id}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
          },
        })
          .then((r) => r.json())
          .then((data) => {
            if (data.ok) {
              toast.success('Token revogado');
              load();
              afterRevoke();
            } else {
              toast.error(data.message ?? 'Erro ao revogar');
            }
          })
          .catch(() => toast.error('Erro de rede ao revogar'));
      },
    });
  }

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>Tokens MCP — {user.nome}</DialogTitle>
          <DialogDescription>
            Drill-down dos tokens individuais. Revogue por token; o raw não é exibido.
          </DialogDescription>
        </DialogHeader>

        {loading && <div className="py-6 text-sm text-muted-foreground">Carregando tokens…</div>}
        {error && <div className="py-6 text-sm text-red-600">{error}</div>}
        {!loading && !error && tokens && tokens.length === 0 && (
          <div className="py-8 text-center text-sm text-muted-foreground">
            Nenhum token registrado pra esse dev ainda.
          </div>
        )}
        {!loading && !error && tokens && tokens.length > 0 && (
          <div className="overflow-x-auto max-h-[60vh]">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b">
                  <th className="text-left py-2 px-2 font-medium">Nome</th>
                  <th className="text-left py-2 px-2 font-medium">Criado</th>
                  <th className="text-left py-2 px-2 font-medium">Expira</th>
                  <th className="text-left py-2 px-2 font-medium">Último uso</th>
                  <th className="text-left py-2 px-2 font-medium">IP</th>
                  <th className="text-center py-2 px-2 font-medium">Status</th>
                  <th className="text-right py-2 px-2 font-medium">Ações</th>
                </tr>
              </thead>
              <tbody>
                {tokens.map((t) => {
                  const s = tokenStatus(t);
                  const isInactive = t.revoked_at !== null
                    || (t.expires_at !== null && new Date(t.expires_at).getTime() < Date.now());
                  return (
                    <tr key={t.id} className="border-b hover:bg-muted/40">
                      <td className="py-2 px-2 font-mono text-xs">{t.name}</td>
                      <td className="py-2 px-2 text-xs">{fmtDate(t.created_at)}</td>
                      <td className="py-2 px-2 text-xs">
                        {t.expires_at ? fmtDate(t.expires_at) : '—'}
                      </td>
                      <td
                        className="py-2 px-2 text-xs"
                        title={t.last_used_at ? new Date(t.last_used_at).toLocaleString('pt-BR') : ''}
                      >
                        {t.last_used_at
                          ? fmtRelative(t.last_used_at)
                          : <span className="text-muted-foreground">Nunca usado</span>}
                      </td>
                      <td className="py-2 px-2 font-mono text-xs">
                        {t.last_used_ip ?? <span className="text-muted-foreground">—</span>}
                      </td>
                      <td className="text-center py-2 px-2">
                        <Badge className={s.className}>{s.label}</Badge>
                      </td>
                      <td className="text-right py-2 px-2">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => revoke(t)}
                          disabled={isInactive}
                          aria-label={`Revogar token ${t.name}`}
                          title={isInactive ? 'Já inativo' : `Revogar token ${t.name}`}
                          className="text-xs"
                        >
                          🗑 Revogar
                        </Button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Fechar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function QuotaForm({ user, onClose }: { user: TeamMember; onClose: () => void }) {
  const [period, setPeriod] = useState<'daily' | 'monthly'>('daily');
  const [limit, setLimit] = useState<string>(
    period === 'daily'
      ? String(user.quota_diaria?.limit ?? 5)
      : String(user.quota_mensal?.limit ?? 100)
  );
  const [block, setBlock] = useState<boolean>(true);
  const [saving, setSaving] = useState(false);

  function salvar() {
    setSaving(true);
    fetch(`/team-mcp/team/${user.id}/quota`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        period,
        limit_brl: parseFloat(limit),
        block_on_exceed: block,
      }),
    })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          toast.success('Quota atualizada');
          onClose();
        } else {
          toast.error(data.message ?? 'Erro');
        }
      })
      .catch(() => toast.error('Erro de rede'))
      .finally(() => setSaving(false));
  }

  return (
    <div className="space-y-4">
      <div>
        <Label>Período</Label>
        <div className="flex gap-2 mt-1">
          <Button
            variant={period === 'daily' ? 'default' : 'outline'}
            size="sm"
            onClick={() => {
              setPeriod('daily');
              setLimit(String(user.quota_diaria?.limit ?? 5));
            }}
          >
            Diário
          </Button>
          <Button
            variant={period === 'monthly' ? 'default' : 'outline'}
            size="sm"
            onClick={() => {
              setPeriod('monthly');
              setLimit(String(user.quota_mensal?.limit ?? 100));
            }}
          >
            Mensal
          </Button>
        </div>
      </div>

      <div>
        <Label>Limite em R$ (período {period})</Label>
        <Input
          type="number"
          step="0.01"
          min="0"
          value={limit}
          onChange={(e) => setLimit(e.target.value)}
        />
        <p className="text-xs text-muted-foreground mt-1">
          {period === 'daily' ? 'Reset diário 00:00 BRT' : 'Reset mensal dia 1'}
        </p>
      </div>

      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          id="block"
          checked={block}
          onChange={(e) => setBlock(e.target.checked)}
        />
        <Label htmlFor="block" className="cursor-pointer">
          Bloquear ao exceder (HTTP 429). Desmarcar = só alerta.
        </Label>
      </div>

      <DialogFooter>
        <Button variant="outline" onClick={onClose}>Cancelar</Button>
        <Button onClick={salvar} disabled={saving || !limit}>
          {saving ? 'Salvando...' : 'Salvar quota'}
        </Button>
      </DialogFooter>
    </div>
  );
}

TeamIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Copiloto — Team Admin" breadcrumbItems={[{ label: 'Copiloto' }, { label: 'Team Admin' }]}>
    {page}
  </AppShellV2>
);

export default TeamIndex;
