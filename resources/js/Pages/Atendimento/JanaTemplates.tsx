// @memcofre
//   tela: /atendimento/canais/jana-templates
//   stories: US-WA-070 (renomeação pós-Canais)
//   herda: US-WA-067 (bloco Templates+Bot Jana migrado de /whatsapp/settings)
//   charter: resources/js/Pages/Atendimento/JanaTemplates.charter.md
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   permissao: whatsapp.settings.manage

import { useState, type FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';

interface ConfigForUi {
  bot_enabled: boolean;
  template_repair_ready_name: string | null;
  template_repair_waiting_parts_name: string | null;
  template_billing_due_name: string | null;
  template_billing_paid_name: string | null;
}

interface Props {
  config: ConfigForUi | null;
}

export default function JanaTemplates({ config }: Props) {
  const [botEnabled, setBotEnabled] = useState(config?.bot_enabled ?? false);
  const [tplReady, setTplReady] = useState(config?.template_repair_ready_name ?? '');
  const [tplWaiting, setTplWaiting] = useState(config?.template_repair_waiting_parts_name ?? '');
  const [tplBillingDue, setTplBillingDue] = useState(config?.template_billing_due_name ?? '');
  const [tplBillingPaid, setTplBillingPaid] = useState(config?.template_billing_paid_name ?? '');

  const [submitting, setSubmitting] = useState(false);

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitting(true);

    router.put(
      route('atendimento.canais.jana_templates.update'),
      {
        bot_enabled: botEnabled,
        template_repair_ready_name: tplReady || null,
        template_repair_waiting_parts_name: tplWaiting || null,
        template_billing_due_name: tplBillingDue || null,
        template_billing_paid_name: tplBillingPaid || null,
      },
      {
        preserveScroll: true,
        onFinish: () => setSubmitting(false),
      },
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        icon="bot"
        title="Templates + Bot Jana"
        description="Toggle do bot Jana e nomes dos templates HSM que disparam automaticamente (cobrança, repair). Drivers Whatsapp ficam em Canais."
      />

      <form onSubmit={onSubmit} className="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Templates + Bot</CardTitle>
            <CardDescription>Nomes dos templates HSM Meta (ou locais Z-API/Baileys) que disparam automaticamente.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex items-center gap-3">
              <Switch checked={botEnabled} onCheckedChange={setBotEnabled} />
              <Label>Bot Jana (HITL — handoff humano via PolicyEngine ADS)</Label>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <Label htmlFor="tpl_ready">Repair: status ready</Label>
                <Input id="tpl_ready" value={tplReady} onChange={(e) => setTplReady(e.target.value)} placeholder="repair_status_ready" />
              </div>
              <div>
                <Label htmlFor="tpl_waiting">Repair: aguardando peças</Label>
                <Input id="tpl_waiting" value={tplWaiting} onChange={(e) => setTplWaiting(e.target.value)} placeholder="repair_status_waiting_parts" />
              </div>
              <div>
                <Label htmlFor="tpl_due">Cobrança: vencimento próximo</Label>
                <Input id="tpl_due" value={tplBillingDue} onChange={(e) => setTplBillingDue(e.target.value)} placeholder="billing_due_reminder" />
              </div>
              <div>
                <Label htmlFor="tpl_paid">Cobrança: pagamento confirmado</Label>
                <Input id="tpl_paid" value={tplBillingPaid} onChange={(e) => setTplBillingPaid(e.target.value)} placeholder="billing_paid_thank_you" />
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="flex justify-end gap-2">
          <Button type="submit" disabled={submitting}>
            {submitting ? <><Loader2 className="h-4 w-4 animate-spin mr-1" /> Salvando...</> : 'Salvar templates'}
          </Button>
        </div>
      </form>
    </div>
  );
}

JanaTemplates.layout = (page: any) => <AppShellV2>{page}</AppShellV2>;
