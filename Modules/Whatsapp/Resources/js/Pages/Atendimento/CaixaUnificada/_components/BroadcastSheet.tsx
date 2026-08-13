// BroadcastSheet.tsx — broadcast cross-canal FASE 1 (US-WA-306 · ADR 0268).
//
// Pre-flight REAL: conta de envio → "Calcular audiência" chama
// atendimento.broadcast.preflight e mostra contagens (total / com opt-in LGPD /
// na janela 24h / só-HSM). "Salvar rascunho" persiste campanha auditável
// (status=draft, snapshot congelado server-side).
//
// DISPARO É FASE 2 (Job rate-limited com gate [W]) — botão nasce disabled com
// aviso explícito (anti M-AP-2: nada de fingir envio em massa que não roda).
// Referência visual: om-drawer Broadcast do inbox-page.jsx (que era mock).

import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, Calculator, Save } from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Inline, Stack } from '@/Components/layout';
import type { ReadyTemplate } from '@/Pages/Whatsapp/_components/helpers';
import type { AccountItem } from './helpers';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  accounts: AccountItem[];
  templates: ReadyTemplate[];
}

interface PreflightResult {
  total: number;
  with_opt_in: number;
  without_opt_in: number;
  in_window: number;
  hsm_only: number;
}

const PROVIDER_BY_CHANNEL_TYPE: Record<string, string> = {
  whatsapp_baileys: 'baileys',
  whatsapp_meta: 'meta_cloud',
  meta_cloud: 'meta_cloud',
  whatsapp_zapi: 'zapi',
};

export default function BroadcastSheet({ open, onOpenChange, accounts, templates }: Props) {
  const activeAccounts = useMemo(() => accounts.filter(a => a.status === 'ativo'), [accounts]);
  const [channelId, setChannelId] = useState('');
  const [kind, setKind] = useState<'freeform' | 'template'>('freeform');
  const [templateName, setTemplateName] = useState('');
  const [body, setBody] = useState('');
  const [preflight, setPreflight] = useState<PreflightResult | null>(null);
  const [loadingPreflight, setLoadingPreflight] = useState(false);
  const [saving, setSaving] = useState(false);

  const selectedAccount = activeAccounts.find(a => String(a.id) === channelId);
  const channelProvider = selectedAccount ? PROVIDER_BY_CHANNEL_TYPE[selectedAccount.channel_type] : undefined;
  const channelTemplates = useMemo(
    () => (channelProvider ? templates.filter(t => t.provider === channelProvider) : []),
    [templates, channelProvider],
  );

  function runPreflight() {
    if (!channelId || loadingPreflight) return;
    setLoadingPreflight(true);
    fetch(route('atendimento.broadcast.preflight'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
      },
      body: JSON.stringify({ channel_id: Number(channelId) }),
    })
      .then(r => r.json())
      .then((data: PreflightResult) => setPreflight(data))
      .catch(() => setPreflight(null))
      .finally(() => setLoadingPreflight(false));
  }

  function saveDraft() {
    if (saving || !channelId) return;
    setSaving(true);
    router.post(
      route('atendimento.broadcast.store'),
      {
        channel_id: Number(channelId),
        kind,
        template_name: kind === 'template' ? templateName : null,
        body: kind === 'freeform' ? body : null,
      },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => onOpenChange(false),
        onFinish: () => setSaving(false),
      },
    );
  }

  const canSave = channelId !== ''
    && (kind === 'freeform' ? body.trim() !== '' : templateName !== '')
    && preflight !== null
    && preflight.with_opt_in > 0
    && !saving;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:max-w-md overflow-y-auto">
        <SheetHeader>
          <SheetTitle>Broadcast cross-canal</SheetTitle>
          <SheetDescription>
            Fase 1: audiência (opt-in LGPD + janela 24h Meta) + rascunho auditável.
            O disparo em massa rate-limited é a fase 2 (ADR 0268).
          </SheetDescription>
        </SheetHeader>

        <Stack gap={3} className="mt-4">
          <Stack gap={1}>
            <Label className="text-[11px]">Conta de envio</Label>
            <Select value={channelId} onValueChange={v => { setChannelId(v); setPreflight(null); setTemplateName(''); }}>
              <SelectTrigger data-testid="caixa-unif-bcast-conta">
                <SelectValue placeholder={activeAccounts.length === 0 ? 'Nenhuma conta ativa' : 'Selecione a conta'} />
              </SelectTrigger>
              <SelectContent>
                {activeAccounts.map(a => (
                  <SelectItem key={a.id} value={String(a.id)}>
                    {a.label} · {a.handle}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Stack>

          <Stack gap={1}>
            <Label className="text-[11px]">Tipo de mensagem</Label>
            <Select value={kind} onValueChange={v => setKind(v as 'freeform' | 'template')}>
              <SelectTrigger data-testid="caixa-unif-bcast-kind">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="freeform">Texto livre (só janela 24h aberta)</SelectItem>
                <SelectItem value="template">Template HSM (fora da janela)</SelectItem>
              </SelectContent>
            </Select>
          </Stack>

          {kind === 'template' ? (
            <Stack gap={1}>
              <Label className="text-[11px]">Template do canal</Label>
              <Select value={templateName} onValueChange={setTemplateName}>
                <SelectTrigger data-testid="caixa-unif-bcast-template">
                  <SelectValue placeholder={channelTemplates.length === 0 ? 'Nenhum template ready' : 'Selecione'} />
                </SelectTrigger>
                <SelectContent>
                  {channelTemplates.map(t => (
                    <SelectItem key={t.id} value={t.name}>{t.name} · {t.language}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Stack>
          ) : (
            <Stack gap={1}>
              <Label htmlFor="bcast-body" className="text-[11px]">Mensagem</Label>
              <Input
                id="bcast-body"
                value={body}
                onChange={e => setBody(e.target.value)}
                placeholder="Texto do broadcast…"
                data-testid="caixa-unif-bcast-body"
              />
            </Stack>
          )}

          <Button
            type="button"
            variant="outline"
            onClick={runPreflight}
            disabled={!channelId || loadingPreflight}
            className="gap-1.5"
            data-testid="caixa-unif-bcast-preflight"
          >
            <Calculator size={14} aria-hidden />
            {loadingPreflight ? 'Calculando…' : 'Calcular audiência (pre-flight)'}
          </Button>

          {preflight && (
            <Stack gap={1} className="border rounded-md p-3 bg-muted/20" data-testid="caixa-unif-bcast-result">
              <Inline gap={2} align="baseline" justify="between">
                <small className="text-[11px] text-muted-foreground">Conversas no canal</small>
                <b className="font-mono text-[12.5px]">{preflight.total}</b>
              </Inline>
              <Inline gap={2} align="baseline" justify="between">
                <small className="text-[11px] text-muted-foreground">Com opt-in LGPD (entram na lista)</small>
                <b className="font-mono text-[12.5px]">{preflight.with_opt_in}</b>
              </Inline>
              <Inline gap={2} align="baseline" justify="between">
                <small className="text-[11px] text-muted-foreground">Sem opt-in (FORA — LGPD)</small>
                <b className="font-mono text-[12.5px]">{preflight.without_opt_in}</b>
              </Inline>
              <Inline gap={2} align="baseline" justify="between">
                <small className="text-[11px] text-muted-foreground">Janela 24h aberta (freeform ok)</small>
                <b className="font-mono text-[12.5px]">{preflight.in_window}</b>
              </Inline>
              <Inline gap={2} align="baseline" justify="between">
                <small className="text-[11px] text-muted-foreground">Fora da janela (só template HSM)</small>
                <b className="font-mono text-[12.5px]">{preflight.hsm_only}</b>
              </Inline>
              {preflight.with_opt_in === 0 && (
                <Inline gap={1} align="center" className="text-destructive mt-1">
                  <AlertTriangle size={13} aria-hidden />
                  <small className="text-[11px]">
                    Nenhum contato com opt-in — broadcast sem consentimento é proibido.
                  </small>
                </Inline>
              )}
            </Stack>
          )}

          <Button
            type="button"
            onClick={saveDraft}
            disabled={!canSave}
            className="gap-1.5"
            data-testid="caixa-unif-bcast-save"
          >
            <Save size={14} aria-hidden />
            {saving ? 'Salvando…' : 'Salvar rascunho (auditável)'}
          </Button>

          <Button type="button" disabled variant="outline" title="Fase 2 — Job rate-limited com gate Wagner (ADR 0268)">
            Disparar broadcast — fase 2 (em construção)
          </Button>
          <small className="text-[10.5px] text-muted-foreground">
            O motor de disparo em massa (fila + rate-limit anti-ban + retry por
            destinatário) entra na fase 2 com aprovação explícita — este painel já
            deixa a campanha pronta e auditável.
          </small>
        </Stack>
      </SheetContent>
    </Sheet>
  );
}
