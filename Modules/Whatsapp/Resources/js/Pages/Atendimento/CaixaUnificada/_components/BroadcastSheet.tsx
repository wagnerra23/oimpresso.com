// BroadcastSheet.tsx — broadcast cross-canal FASE 1 (US-WA-306 · ADR 0268).
//
// Pre-flight REAL: conta de envio → "Calcular audiência" chama
// atendimento.broadcast.preflight e mostra contagens (total / com opt-in LGPD /
// na janela 24h / só-HSM). "Salvar rascunho" persiste campanha auditável
// (status=draft, snapshot congelado server-side).
//
// US-WA-323 (ADR 0268 fase 2): "Salvar e disparar" agenda o envio real (US-WA-322 — intervalo
// aleatório, horário comercial, limite diário). O botão só liga com a flag do disparo e com texto
// livre; desligado, diz o porquê (anti M-AP-2: nada de fingir envio que não roda).
// Referência visual: om-drawer Broadcast do inbox-page.jsx (que era mock).

import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, Calculator, Save, Send } from 'lucide-react';
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
  /** US-WA-323 — flag do disparo real da campanha (US-WA-322). */
  disparoHabilitado?: boolean;
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

export default function BroadcastSheet({ open, onOpenChange, accounts, templates, disparoHabilitado = false }: Props) {
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

  function saveDraft(disparar = false) {
    if (saving || !channelId) return;
    setSaving(true);
    router.post(
      route('atendimento.broadcast.store'),
      {
        channel_id: Number(channelId),
        kind,
        template_name: kind === 'template' ? templateName : null,
        body: kind === 'freeform' ? body : null,
        disparar,
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
  const canDispatch = canSave && disparoHabilitado && kind === 'freeform';

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:max-w-md overflow-y-auto">
        <SheetHeader>
          <SheetTitle>Broadcast cross-canal</SheetTitle>
          <SheetDescription>
            Só entra quem deu opt-in (LGPD). O disparo sai aos poucos, em horário comercial,
            para não arriscar o número.
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
            onClick={() => saveDraft(false)}
            disabled={!canSave}
            className="gap-1.5"
            data-testid="caixa-unif-bcast-save"
          >
            <Save size={14} aria-hidden />
            {saving ? 'Salvando…' : 'Salvar rascunho (auditável)'}
          </Button>

          <Button
            type="button"
            variant="outline"
            onClick={() => saveDraft(true)}
            disabled={!canDispatch}
            className="gap-1.5"
            data-testid="caixa-unif-bcast-disparar"
          >
            <Send size={14} aria-hidden />
            Salvar e disparar
          </Button>
          <small className="text-[10.5px] text-muted-foreground" data-testid="caixa-unif-bcast-disparo-aviso">
            {!disparoHabilitado
              ? 'O disparo de campanhas não está ligado nesta empresa. A campanha fica salva como rascunho.'
              : kind !== 'freeform'
                ? 'Por enquanto só campanha de texto livre pode ser disparada.'
                : 'Envia uma mensagem a cada 20 a 45 segundos, das 8h às 20h, até 200 por dia (40 nos primeiros 7 dias do número).'}
          </small>
        </Stack>
      </SheetContent>
    </Sheet>
  );
}
