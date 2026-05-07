// @memcofre
//   tela: /whatsapp/templates
//   stories: US-WA-013 (Templates HSM Meta + locais Z-API/Baileys)
//   adrs: 0096
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   status: implementada Lote 2e (lista; sync Meta em Lote 2f Sprint 2)
//   permissao: whatsapp.templates.manage

import { router } from '@inertiajs/react';
import { useState } from 'react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

interface Template {
  id: number;
  provider: 'meta_cloud' | 'zapi' | 'baileys';
  name: string;
  language: string;
  category: 'UTILITY' | 'MARKETING' | 'AUTHENTICATION';
  status: 'PENDING' | 'APPROVED' | 'REJECTED' | 'PAUSED' | 'DISABLED' | 'LOCAL';
  rejection_reason: string | null;
  last_synced_at: string | null;
  body_preview: string;
  has_meta_counterpart: boolean;
  is_ready: boolean;
}

interface Props {
  templates: Template[];
  filters: { provider: string; status: string };
}

export default function TemplatesIndex({ templates, filters }: Props) {
  const [syncing, setSyncing] = useState(false);
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [createSubmitting, setCreateSubmitting] = useState(false);
  const [newTpl, setNewTpl] = useState({
    provider: 'zapi',
    name: '',
    language: 'pt_BR',
    category: 'UTILITY',
    body: '',
  });

  function applyFilter(key: string, value: string) {
    router.get(route('whatsapp.templates.index'), { ...filters, [key]: value }, { preserveScroll: true });
  }

  function syncMeta() {
    if (syncing) return;
    setSyncing(true);
    router.post(route('whatsapp.templates.sync_meta'), {}, {
      preserveScroll: true,
      onFinish: () => setSyncing(false),
    });
  }

  function createLocalTemplate() {
    if (createSubmitting) return;
    setCreateSubmitting(true);
    router.post(route('whatsapp.templates.store'), newTpl, {
      preserveScroll: true,
      onSuccess: () => {
        setShowCreateForm(false);
        setNewTpl({ provider: 'zapi', name: '', language: 'pt_BR', category: 'UTILITY', body: '' });
      },
      onFinish: () => setCreateSubmitting(false),
    });
  }

  const orphanCount = templates.filter((t) => !t.has_meta_counterpart && t.provider !== 'meta_cloud').length;

  return (
    <div className="space-y-4">
      <PageHeader
        title="Templates Whatsapp"
        subtitle="HSM Meta + locais Z-API/Baileys (LOCAL = sempre disponível)"
        actions={
          <div className="flex gap-2">
            <Button onClick={() => setShowCreateForm((s) => !s)} variant="outline">
              {showCreateForm ? 'Cancelar' : '+ Novo template LOCAL'}
            </Button>
            <Button onClick={syncMeta} disabled={syncing} variant="outline">
              {syncing ? 'Sincronizando Meta...' : 'Sincronizar Meta HSM'}
            </Button>
          </div>
        }
      />

      {/* Form criar template LOCAL Z-API/Baileys */}
      {showCreateForm && (
        <Card>
          <CardHeader>
            <CardTitle>Novo template LOCAL</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <Label>Provider</Label>
                <Select value={newTpl.provider} onValueChange={(v) => setNewTpl({ ...newTpl, provider: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="zapi">Z-API</SelectItem>
                    <SelectItem value="baileys">Baileys (Sprint 3)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Nome (snake_case)</Label>
                <Input
                  value={newTpl.name}
                  onChange={(e) => setNewTpl({ ...newTpl, name: e.target.value.toLowerCase() })}
                  placeholder="repair_status_ready"
                />
              </div>
              <div>
                <Label>Idioma</Label>
                <Input value={newTpl.language} onChange={(e) => setNewTpl({ ...newTpl, language: e.target.value })} />
              </div>
              <div>
                <Label>Categoria</Label>
                <Select value={newTpl.category} onValueChange={(v) => setNewTpl({ ...newTpl, category: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="UTILITY">UTILITY</SelectItem>
                    <SelectItem value="MARKETING">MARKETING</SelectItem>
                    <SelectItem value="AUTHENTICATION">AUTHENTICATION</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div>
              <Label>Body (use {'{{1}}, {{2}}'} pra placeholders)</Label>
              <Textarea
                value={newTpl.body}
                onChange={(e) => setNewTpl({ ...newTpl, body: e.target.value })}
                rows={4}
                placeholder="Olá {{1}}, sua OS #{{2}} está pronta para retirada!"
              />
            </div>
            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={() => setShowCreateForm(false)}>Cancelar</Button>
              <Button onClick={createLocalTemplate} disabled={createSubmitting || !newTpl.name || !newTpl.body}>
                {createSubmitting ? 'Salvando...' : 'Salvar template LOCAL'}
              </Button>
            </div>
            <div className="text-xs text-muted-foreground border-t pt-2">
              ⚠️ Recomendamos criar template HSM correspondente na Meta Business Manager + sincronizar
              (botão acima) pra fallback Meta Cloud funcionar em caso de ban.
            </div>
          </CardContent>
        </Card>
      )}

      {orphanCount > 0 && (
        <Card className="p-3 border-amber-300 bg-amber-50">
          <div className="text-sm text-amber-900">
            ⚠️ {orphanCount} template{orphanCount > 1 ? 's' : ''} Z-API/Baileys sem contraparte Meta aprovada. Em
            caso de ban Z-API, fallback Meta Cloud não vai conseguir enviar essas mensagens.
          </div>
        </Card>
      )}

      {/* Filtros */}
      <div className="flex flex-wrap gap-3">
        <div>
          <label className="text-xs text-muted-foreground">Provider</label>
          <Select value={filters.provider} onValueChange={(v) => applyFilter('provider', v)}>
            <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todos</SelectItem>
              <SelectItem value="meta_cloud">Meta Cloud</SelectItem>
              <SelectItem value="zapi">Z-API</SelectItem>
              <SelectItem value="baileys">Baileys</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <label className="text-xs text-muted-foreground">Status</label>
          <Select value={filters.status} onValueChange={(v) => applyFilter('status', v)}>
            <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todos</SelectItem>
              <SelectItem value="LOCAL">Local (Z-API/Baileys)</SelectItem>
              <SelectItem value="APPROVED">Aprovado (Meta)</SelectItem>
              <SelectItem value="PENDING">Pendente</SelectItem>
              <SelectItem value="REJECTED">Rejeitado</SelectItem>
              <SelectItem value="PAUSED">Pausado</SelectItem>
              <SelectItem value="DISABLED">Desabilitado</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Lista */}
      {templates.length === 0 ? (
        <Card className="p-8 text-center text-muted-foreground">
          Nenhum template cadastrado. Cadastre HSM na Meta Business Manager e clique "Sincronizar Meta HSM",
          ou crie templates locais Z-API/Baileys diretamente no DB (UI de criação local em Lote 2f).
        </Card>
      ) : (
        <div className="space-y-2">
          {templates.map((t) => (
            <Card key={t.id} className="p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-mono font-medium">{t.name}</span>
                    <Badge variant="outline">{t.language}</Badge>
                    <CategoryBadge category={t.category} />
                    <StatusBadge status={t.status} provider={t.provider} />
                    <ProviderBadge provider={t.provider} />
                    {t.is_ready && <Badge variant="outline" className="border-green-500 text-green-700">pronto</Badge>}
                  </div>
                  <div className="mt-2 text-sm text-muted-foreground whitespace-pre-wrap">
                    {t.body_preview || <em>(sem corpo)</em>}
                  </div>
                  {t.rejection_reason && (
                    <div className="mt-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1">
                      Rejeitado: {t.rejection_reason}
                    </div>
                  )}
                  {t.provider !== 'meta_cloud' && !t.has_meta_counterpart && (
                    <div className="mt-2 text-xs text-amber-700">
                      ⚠️ Sem contraparte Meta aprovada — fallback automático não vai mandar essa mensagem
                    </div>
                  )}
                </div>
                <div className="text-xs text-muted-foreground text-right shrink-0">
                  {t.last_synced_at && (
                    <div>sync: {new Date(t.last_synced_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}</div>
                  )}
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}

TemplatesIndex.layout = (page: any) => <AppShellV2>{page}</AppShellV2>;

function ProviderBadge({ provider }: { provider: string }) {
  const map: Record<string, { label: string; className: string }> = {
    meta_cloud: { label: 'Meta', className: 'border-blue-500 text-blue-700' },
    zapi: { label: 'Z-API', className: 'border-purple-500 text-purple-700' },
    baileys: { label: 'Baileys', className: 'border-orange-500 text-orange-700' },
  };
  const conf = map[provider] ?? { label: provider, className: '' };
  return <Badge variant="outline" className={conf.className}>{conf.label}</Badge>;
}

function CategoryBadge({ category }: { category: string }) {
  const map: Record<string, string> = {
    UTILITY: 'border-emerald-500 text-emerald-700',
    MARKETING: 'border-pink-500 text-pink-700',
    AUTHENTICATION: 'border-slate-500 text-slate-700',
  };
  return <Badge variant="outline" className={map[category] ?? ''}>{category}</Badge>;
}

function StatusBadge({ status, provider }: { status: string; provider: string }) {
  if (status === 'LOCAL') {
    return <Badge variant="outline" className="border-emerald-500 text-emerald-700">LOCAL</Badge>;
  }
  const map: Record<string, string> = {
    APPROVED: 'border-green-500 text-green-700',
    PENDING: 'border-amber-500 text-amber-700',
    REJECTED: 'border-red-500 text-red-700',
    PAUSED: 'border-slate-500 text-slate-700',
    DISABLED: 'border-slate-400 text-slate-600',
  };
  return <Badge variant="outline" className={map[status] ?? ''}>{status}</Badge>;
}
