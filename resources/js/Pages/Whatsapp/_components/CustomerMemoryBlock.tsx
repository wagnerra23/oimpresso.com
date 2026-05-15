/**
 * US-WA-VOZ-001/002/003 — Bloco Customer 360 dentro do ConversationSidebar.
 *
 * Lê endpoint `GET /atendimento/customer/{external_id}/profile` (lazy fetch
 * client-side fora do Inertia::defer pra não recarregar a página toda no
 * switch de conversa — só este bloco) e renderiza:
 *
 *   - Stats: n_conversations · n_msgs_total · last_interaction
 *   - Identity: contact CRM linkado (ou "+phone redacted")
 *   - Reclamações: total + top 3 recentes com severity badge
 *   - Funcionário: assigned_user_id (último a responder) + most_active
 *   - Fontes externas: Firebird OfficeImpresso match (se houver)
 *
 * Tier 0: endpoint resolve business_id via session — cliente NÃO precisa
 * passar.
 *
 * Idempotente: re-fetch ao trocar `customer_external_id` (effect dep).
 *
 * Estados:
 *   - loading        → skeleton
 *   - ok             → render completo
 *   - building       → "Memória sendo construída..." (lazy create no backend)
 *   - erasure_requested → mostra "Cliente exerceu apagamento LGPD"
 *   - not_found      → bloco oculto (defensivo)
 *
 * @see Modules/Whatsapp/Http/Controllers/Api/CustomerProfileController.php
 */
import { useEffect, useState } from 'react';
import {
  User, Phone, Mail, MessageSquare, AlertTriangle, ShieldOff,
  Database, Clock, TrendingDown, Star,
} from 'lucide-react';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';

interface Reclamacao {
  date: string;
  msg_id: number;
  severity: 'critica' | 'alta' | 'media' | 'baixa';
  preview: string;
}

interface ExternalSource {
  source: string;
  cliente_id?: number;
  nome?: string;
  fone1?: string;
  email?: string;
  bloqueado?: boolean;
  cpf_cnpj?: string;
  cidade?: string;
}

interface ProfilePayload {
  state: 'ok' | 'building' | 'erasure_requested' | 'not_found';
  customer_external_id: string;
  memory?: {
    display_name: string | null;
    identity: {
      contact_id: number | null;
      method: string | null;
      confidence: number | null;
    };
    stats: {
      n_conversations: number;
      n_msgs_inbound: number;
      n_msgs_outbound: number;
      n_msgs_total: number;
      first_interaction_at: string | null;
      last_interaction_at: string | null;
      days_since_last: number | null;
    };
    inferences: {
      temas_recorrentes: string[] | null;
      sentimento_score: number | null;
      churn_risk_score: number | null;
    };
    notes: {
      notas_jana: string | null;
    };
    flags: Array<{ tipo: string; motivo?: string }>;
    consent: {
      status: string | null;
      erasure_requested_at: string | null;
    };
    // US-WA-VOZ-002 extension fields
    assigned_user_id?: number | null;
    most_active_user_id?: number | null;
    total_reclamacoes?: number;
    reclamacoes_recentes?: Reclamacao[] | null;
    external_sources?: ExternalSource[] | null;
  };
  contact?: {
    id: number;
    name: string;
    email: string | null;
    cpf_cnpj: string | null;
    mobile: string | null;
    city: string | null;
    state: string | null;
    whatsapp_consent: boolean | null;
  } | null;
}

interface Props {
  customerExternalId: string;
}

export default function CustomerMemoryBlock({ customerExternalId }: Props) {
  const [data, setData] = useState<ProfilePayload | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!customerExternalId) return;
    const ext = customerExternalId.replace(/^\+/, '');
    setLoading(true);
    setError(null);

    fetch(`/atendimento/customer/${encodeURIComponent(ext)}/profile`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then((r) => r.json().then((body) => ({ status: r.status, body })))
      .then(({ body }) => setData(body as ProfilePayload))
      .catch((e) => setError(String(e?.message ?? e)))
      .finally(() => setLoading(false));
  }, [customerExternalId]);

  if (loading && !data) {
    return (
      <Card className="p-3 space-y-2 animate-pulse">
        <div className="h-4 bg-muted rounded w-32" />
        <div className="h-3 bg-muted rounded w-48" />
        <div className="h-3 bg-muted rounded w-40" />
      </Card>
    );
  }

  if (error) {
    return (
      <Card className="p-3 text-xs text-muted-foreground">
        Memória do cliente indisponível.
      </Card>
    );
  }

  if (!data || data.state === 'not_found') {
    return null;
  }

  if (data.state === 'building') {
    return (
      <Card className="p-3 text-xs text-muted-foreground">
        Construindo memória do cliente… atualize em alguns segundos.
      </Card>
    );
  }

  if (data.state === 'erasure_requested') {
    return (
      <Card className="p-3 space-y-2 border-destructive/30">
        <div className="flex items-center gap-2 text-destructive">
          <ShieldOff className="size-4" />
          <span className="text-xs font-medium">Cliente solicitou apagamento (LGPD)</span>
        </div>
      </Card>
    );
  }

  const m = data.memory!;
  const c = data.contact;

  const severityBadge = (sev: Reclamacao['severity']) => {
    const map = {
      critica: 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30',
      alta:    'bg-orange-500/15 text-orange-700 dark:text-orange-300 border-orange-500/30',
      media:   'bg-yellow-500/15 text-yellow-700 dark:text-yellow-300 border-yellow-500/30',
      baixa:   'bg-muted text-muted-foreground border-border',
    };
    return map[sev] ?? map.baixa;
  };

  return (
    <Card className="p-3 space-y-3">
      <div className="flex items-center gap-2 text-xs uppercase tracking-wider text-muted-foreground">
        <User className="size-3.5" />
        <span>Sobre o cliente</span>
      </div>

      {/* Nome + telefone */}
      <div className="space-y-0.5">
        <div className="text-sm font-medium leading-tight">{m.display_name || '+' + data.customer_external_id}</div>
        <div className="text-xs text-muted-foreground flex items-center gap-1">
          <Phone className="size-3" />
          <span>+{data.customer_external_id}</span>
          {m.identity.method && (
            <Badge variant="outline" className="ml-1 text-[10px] px-1 py-0">
              {m.identity.method}
            </Badge>
          )}
        </div>
      </div>

      {/* Contact CRM linkado */}
      {c && (
        <div className="text-xs space-y-0.5">
          {c.email && (
            <div className="flex items-center gap-1 text-muted-foreground">
              <Mail className="size-3" />
              <span className="truncate">{c.email}</span>
            </div>
          )}
          {(c.city || c.state) && (
            <div className="text-muted-foreground">
              {[c.city, c.state].filter(Boolean).join(' · ')}
            </div>
          )}
        </div>
      )}

      <Separator />

      {/* Stats */}
      <div className="grid grid-cols-3 gap-2 text-xs">
        <div>
          <div className="text-muted-foreground">Conversas</div>
          <div className="font-medium tabular-nums">{m.stats.n_conversations}</div>
        </div>
        <div>
          <div className="text-muted-foreground">Mensagens</div>
          <div className="font-medium tabular-nums">{m.stats.n_msgs_total}</div>
        </div>
        <div>
          <div className="text-muted-foreground flex items-center gap-1">
            <Clock className="size-3" />
            <span>Último</span>
          </div>
          <div className="font-medium tabular-nums">
            {m.stats.days_since_last == null ? '—' : `${m.stats.days_since_last}d`}
          </div>
        </div>
      </div>

      {/* Reclamações */}
      {(m.total_reclamacoes ?? 0) > 0 && (
        <>
          <Separator />
          <div className="space-y-1.5">
            <div className="flex items-center gap-1.5 text-xs">
              <AlertTriangle className="size-3.5 text-orange-500" />
              <span className="font-medium">{m.total_reclamacoes} reclamações nos últimos 30 dias</span>
            </div>
            {(m.reclamacoes_recentes ?? []).slice(0, 3).map((r) => (
              <div key={r.msg_id} className="text-xs space-y-0.5">
                <Badge variant="outline" className={`text-[10px] px-1 py-0 ${severityBadge(r.severity)}`}>
                  {r.severity}
                </Badge>
                <div className="text-muted-foreground line-clamp-2">{r.preview}</div>
              </div>
            ))}
          </div>
        </>
      )}

      {/* Temas recorrentes (Onda 3 IA — pode estar null) */}
      {m.inferences?.temas_recorrentes && m.inferences.temas_recorrentes.length > 0 && (
        <>
          <Separator />
          <div className="space-y-1">
            <div className="text-xs uppercase tracking-wider text-muted-foreground">Temas</div>
            <div className="flex flex-wrap gap-1">
              {m.inferences.temas_recorrentes.map((t) => (
                <Badge key={t} variant="secondary" className="text-[10px]">{t}</Badge>
              ))}
            </div>
          </div>
        </>
      )}

      {/* External sources (Firebird OfficeImpresso) */}
      {m.external_sources && m.external_sources.length > 0 && (
        <>
          <Separator />
          <div className="space-y-1.5">
            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <Database className="size-3.5" />
              <span>Cadastros externos</span>
            </div>
            {m.external_sources.slice(0, 2).map((ext, i) => (
              <div key={i} className="text-xs space-y-0.5 border-l-2 border-border pl-2">
                <div className="font-medium">{ext.nome ?? `Cliente #${ext.cliente_id}`}</div>
                <div className="text-muted-foreground text-[10px]">
                  {ext.source}
                  {ext.bloqueado && <span className="text-destructive ml-1">· bloqueado</span>}
                </div>
              </div>
            ))}
          </div>
        </>
      )}

      {/* Notas Jana qualitativas */}
      {m.notes?.notas_jana && (
        <>
          <Separator />
          <div className="space-y-1">
            <div className="text-xs uppercase tracking-wider text-muted-foreground">Notas</div>
            <div className="text-xs whitespace-pre-wrap text-muted-foreground">
              {m.notes.notas_jana}
            </div>
          </div>
        </>
      )}

      {/* Flags operacionais (VIP / frágil) */}
      {m.flags && m.flags.length > 0 && (
        <>
          <Separator />
          <div className="flex flex-wrap gap-1">
            {m.flags.map((f, i) => (
              <Badge
                key={i}
                variant={f.tipo === 'vip' ? 'default' : f.tipo === 'fragil' ? 'destructive' : 'secondary'}
                className="text-[10px]"
              >
                {f.tipo === 'vip' && <Star className="size-3 mr-0.5" />}
                {f.tipo === 'fragil' && <TrendingDown className="size-3 mr-0.5" />}
                {f.tipo}
              </Badge>
            ))}
          </div>
        </>
      )}
    </Card>
  );
}
