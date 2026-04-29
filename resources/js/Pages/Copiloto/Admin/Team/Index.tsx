// @memcofre
//   tela: /copiloto/admin/team
//   module: Copiloto
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

import AppShell from '@/Layouts/AppShell';
import { Head, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog';
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
  team: TeamMember[];
  stats_globais: StatsGlobais;
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

function quotaBadge(pct: number, block: boolean): { className: string; label: string } {
  if (pct >= 100) return { className: 'bg-red-100 text-red-800', label: block ? '🚫 BLOQUEADO' : '⚠️ excedido' };
  if (pct >= 80)  return { className: 'bg-orange-100 text-orange-800', label: '⚠️ ' + pct + '%' };
  if (pct >= 50)  return { className: 'bg-yellow-100 text-yellow-800', label: pct + '%' };
  return { className: 'bg-green-100 text-green-800', label: pct + '%' };
}

function TeamIndex(props: Props) {
  const { team, stats_globais, pricing_config } = props;
  const [tokenGerado, setTokenGerado] = useState<{ user: string; raw: string } | null>(null);
  const [editQuotaUser, setEditQuotaUser] = useState<TeamMember | null>(null);

  function gerarToken(member: TeamMember) {
    if (!confirm(`Gerar token MCP novo pra ${member.nome}?`)) return;
    fetch(`/copiloto/admin/team/${member.id}/token`, {
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

  function exportarCsv() {
    const periodo = prompt('Período (de,ate em YYYY-MM-DD,YYYY-MM-DD ou ENTER pra mês corrente):', '');
    let url = '/copiloto/admin/team/export.csv';
    if (periodo) {
      const [de, ate] = periodo.split(',').map(s => s.trim());
      if (de && ate) url += `?de=${de}&ate=${ate}`;
    }
    window.location.href = url;
  }

  return (
    <>
      <Head title="Copiloto — Team Admin" />

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

      {/* KPIs globais */}
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

      {/* Tabela team */}
      <Card className="mt-6">
        <CardHeader>
          <CardTitle>Time ({team.length} devs)</CardTitle>
          <CardDescription>
            Tokens MCP ativos, custo hoje/mês, quotas, top tools, último uso
          </CardDescription>
        </CardHeader>
        <CardContent>
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
                      {m.tokens_ativos > 0 ? (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">
                          {m.tokens_ativos} ativo{m.tokens_ativos > 1 ? 's' : ''}
                        </span>
                      ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                      )}
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
                          variant="outline" size="sm"
                          onClick={() => gerarToken(m)}
                          className="text-xs"
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
    </>
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
    fetch(`/copiloto/admin/team/${user.id}/quota`, {
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

TeamIndex.layout = (page: ReactNode) => <AppShell>{page}</AppShell>;

export default TeamIndex;
