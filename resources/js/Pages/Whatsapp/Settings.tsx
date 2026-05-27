// @memcofre
//   tela: /whatsapp/settings
//   stories: US-WA-310 (Embedded Signup v4 wizard)
//   adrs: 0202 (Meta Cloud default universal + Baileys OUT)
//   spec: memory/requisitos/Whatsapp/SPEC.md US-WA-310
//   status: implementada Fase 2 ADR 0202 — wizard "Conectar com Meta" via popup OAuth
//   permissao: whatsapp.settings.manage
//
// Substitui o 301 redirect legacy US-WA-070 (jana-templates). Templates HSM
// continuam em /atendimento/canais/jana-templates — esta tela só conecta o
// WhatsApp via Embedded Signup v4.

import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CheckCircle2, AlertTriangle, Loader2, ExternalLink } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';

interface CurrentConfig {
  driver: 'meta_cloud' | 'zapi' | 'null' | null;
  display_phone: string | null;
  meta_waba_id: string | null;
  driver_health: 'healthy' | 'degraded' | 'disconnected' | 'banned' | 'never_checked';
  connected_at: string | null;
}

interface Props {
  currentConfig: CurrentConfig | null;
  metaAppId: string; // env('META_APP_ID') passado via Controller
  metaBusinessConfigId: string; // env('META_BUSINESS_CONFIG_ID')
  metaGraphVersion: string; // env('WHATSAPP_META_API_VERSION') default v21.0
}

// Mensagem Meta vem via window.postMessage do popup OAuth quando user
// completa Embedded Signup. Schema documentado em:
// https://developers.facebook.com/docs/whatsapp/embedded-signup/implementation
interface MetaPostMessage {
  type: 'WA_EMBEDDED_SIGNUP';
  event: 'FINISH' | 'CANCEL' | 'ERROR';
  data: {
    phone_number_id?: string;
    waba_id?: string;
  };
  // O code OAuth vem via URL parameter (response_type=code +
  // override_default_response_type=true), capturado pelo backend via
  // pular: simplificamos lendo o code via fetch ao callback que valida state.
  code?: string;
}

function csrfToken(): string {
  const el = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
  return el?.content ?? '';
}

export default function Settings({
  currentConfig,
  metaAppId,
  metaBusinessConfigId,
}: Props) {
  const [connecting, setConnecting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [popup, setPopup] = useState<Window | null>(null);

  // Cleanup: fecha popup se page unmount durante connecting
  useEffect(() => {
    return () => {
      if (popup && !popup.closed) {
        popup.close();
      }
    };
  }, [popup]);

  const metaAppConfigured = metaAppId !== '' && metaBusinessConfigId !== '';

  async function startEmbeddedSignup() {
    setErrorMessage(null);
    setConnecting(true);

    try {
      // 1. Fetch state CSRF + URL popup
      const initRes = await fetch('/whatsapp/settings/meta-oauth-init', {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      if (!initRes.ok) {
        const body = await initRes.json().catch(() => ({}));
        throw new Error(body.message || `OAuth init falhou (HTTP ${initRes.status})`);
      }

      const { state, url } = (await initRes.json()) as { state: string; url: string };

      // 2. Abrir popup Facebook OAuth
      const popupWin = window.open(
        url,
        'fb-embedded-signup',
        'width=600,height=750,scrollbars=yes,resizable=yes',
      );

      if (!popupWin) {
        throw new Error('Popup bloqueado pelo navegador. Habilite popups pra continuar.');
      }

      setPopup(popupWin);

      // 3. Listener postMessage do popup Meta
      const listener = async (event: MessageEvent) => {
        // Defesa origin — popup só posta de facebook.com
        if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://m.facebook.com') {
          return;
        }

        let payload: MetaPostMessage | null = null;
        try {
          payload = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
        } catch {
          return;
        }

        if (!payload || payload.type !== 'WA_EMBEDDED_SIGNUP') return;

        // Cancel/error do user no popup
        if (payload.event === 'CANCEL') {
          setConnecting(false);
          setErrorMessage('Conexão cancelada no popup do Meta.');
          window.removeEventListener('message', listener);
          popupWin.close();
          return;
        }

        if (payload.event === 'ERROR') {
          setConnecting(false);
          setErrorMessage('Erro no popup do Meta. Tente novamente.');
          window.removeEventListener('message', listener);
          popupWin.close();
          return;
        }

        if (payload.event !== 'FINISH') return;

        // 4. Postar code pro callback backend
        // Code vem na URL do popup (response_type=code). Como o popup
        // tem origin facebook.com e nosso parent é oimpresso.com, não
        // podemos ler URL diretamente — Meta envia code no postMessage data
        // junto com event=FINISH (signup v4 spec).
        const code = payload.code;
        if (!code) {
          setConnecting(false);
          setErrorMessage('Meta finalizou sem retornar o code. Tente novamente.');
          window.removeEventListener('message', listener);
          popupWin.close();
          return;
        }

        try {
          const cbRes = await fetch('/whatsapp/settings/meta-embedded-callback', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              'X-CSRF-TOKEN': csrfToken(),
              'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ code, state }),
          });

          const cbBody = await cbRes.json().catch(() => ({}));

          if (cbRes.ok && cbBody.success) {
            // Inertia reload pra atualizar currentConfig
            router.reload({ only: ['currentConfig'] });
          } else {
            setErrorMessage(cbBody.message || cbBody.error || `Erro ${cbRes.status}`);
          }
        } catch (err) {
          setErrorMessage((err as Error).message);
        } finally {
          setConnecting(false);
          popupWin.close();
          window.removeEventListener('message', listener);
        }
      };

      window.addEventListener('message', listener);

      // Watchdog — se user fecha popup sem completar
      const watchdog = setInterval(() => {
        if (popupWin.closed) {
          clearInterval(watchdog);
          window.removeEventListener('message', listener);
          // Só seta erro se ainda estiver "connecting" (não foi FINISH)
          setConnecting((prev) => {
            if (prev) {
              setErrorMessage('Popup fechado sem completar a conexão.');
            }
            return false;
          });
        }
      }, 1000);
    } catch (err) {
      setConnecting(false);
      setErrorMessage((err as Error).message);
    }
  }

  return (
    <AppShellV2 title="WhatsApp — Configurações">
      <Head title="WhatsApp — Configurações" />

      <PageHeader title="WhatsApp do negócio" description="Conecte o número WhatsApp do seu negócio via Meta Cloud (oficial)." />

      <div className="max-w-3xl mx-auto p-6 space-y-6">
        {/* Estado: conectado via Meta Cloud */}
        {currentConfig?.driver === 'meta_cloud' && currentConfig.driver_health === 'healthy' ? (
          <Card className="p-6 bg-green-50 border-green-200">
            <div className="flex items-start gap-3">
              <CheckCircle2 className="h-6 w-6 text-green-600 mt-0.5 flex-shrink-0" />
              <div className="flex-1">
                <div className="font-semibold text-green-900">Conectado via Meta Cloud (oficial)</div>
                <div className="text-sm text-green-800 mt-1">
                  Número: <code className="bg-green-100 px-1.5 py-0.5 rounded">{currentConfig.display_phone}</code>
                </div>
                {currentConfig.meta_waba_id && (
                  <div className="text-xs text-green-700 mt-1">
                    WABA: <code>{currentConfig.meta_waba_id}</code>
                  </div>
                )}
                {currentConfig.connected_at && (
                  <div className="text-xs text-green-700 mt-1">
                    Última verificação: {new Date(currentConfig.connected_at).toLocaleString('pt-BR')}
                  </div>
                )}
                <div className="mt-4">
                  <Badge className="bg-green-100 text-green-900">Meta Business API · sem risco de ban</Badge>
                </div>
              </div>
            </div>
          </Card>
        ) : (
          /* Estado: NÃO conectado — wizard */
          <Card className="p-6">
            <h2 className="text-lg font-semibold mb-2">Conectar WhatsApp do seu negócio</h2>
            <p className="text-sm text-muted-foreground mb-4">
              Em 5-15 minutos você conecta o WhatsApp Business via Meta (oficial).
              Use sua conta Meta Business Manager existente.
            </p>

            {!metaAppConfigured && (
              <div className="rounded-md border border-yellow-300 bg-yellow-50 p-3 mb-4 flex items-start gap-2">
                <AlertTriangle className="h-5 w-5 text-yellow-700 flex-shrink-0" />
                <div className="text-sm text-yellow-900">
                  <div className="font-medium">Meta App não configurado</div>
                  <div className="text-xs mt-1">
                    Wagner precisa configurar <code>META_APP_ID</code> + <code>META_BUSINESS_CONFIG_ID</code> no
                    servidor antes. Ver runbook{' '}
                    <code>memory/requisitos/Whatsapp/runbooks/onboarding-meta-cloud-embedded-signup.md</code>.
                  </div>
                </div>
              </div>
            )}

            <Button
              onClick={startEmbeddedSignup}
              disabled={connecting || !metaAppConfigured}
              className="bg-[#1877F2] hover:bg-[#166FE5] text-white font-medium px-6 py-3"
              size="lg"
            >
              {connecting ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Conectando…
                </>
              ) : (
                <>
                  <ExternalLink className="h-4 w-4 mr-2" />
                  Conectar com Meta
                </>
              )}
            </Button>

            {errorMessage && (
              <div className="mt-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
                <div className="font-medium">Erro</div>
                <div className="text-xs mt-1">{errorMessage}</div>
              </div>
            )}

            <div className="mt-6 text-xs text-muted-foreground space-y-1">
              <div>• Você precisa ter uma conta Meta Business Manager + WhatsApp Business Account (WABA) criado</div>
              <div>• Vamos abrir um popup do Meta pra você autorizar (NÃO vamos pedir senha do WhatsApp)</div>
              <div>• Webhook do seu número será assinado automaticamente</div>
            </div>
          </Card>
        )}

        {/* Estado degraded/disconnected — pode reconectar */}
        {currentConfig?.driver === 'meta_cloud' && currentConfig.driver_health !== 'healthy' && (
          <Card className="p-4 bg-yellow-50 border-yellow-200">
            <div className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-yellow-700" />
              <div className="text-sm text-yellow-900">
                Conexão atual está em estado <strong>{currentConfig.driver_health}</strong>.{' '}
                <button onClick={startEmbeddedSignup} className="underline" disabled={connecting}>
                  Reconectar com Meta
                </button>
              </div>
            </div>
          </Card>
        )}

        {/* Opções avançadas Z-API (legacy) */}
        <details className="group">
          <summary className="cursor-pointer text-sm text-muted-foreground hover:text-foreground select-none py-2">
            Opções avançadas (Z-API legacy — não recomendado pra contas novas)
          </summary>
          <Card className="mt-2 p-4">
            <div className="text-sm text-muted-foreground space-y-2">
              <p>
                Z-API continua disponível como driver opcional. Pra cadastrar, use a tela de canais omnichannel:
              </p>
              <Button variant="outline" size="sm" asChild>
                <a href="/atendimento/canais">Ir pra Canais</a>
              </Button>
              <p className="text-xs mt-3">
                <strong>Nota:</strong> Z-API é proxy não-oficial do WhatsApp Web. Tem custo extra (R$ 50-200/mês) e
                pode banir sem aviso. Meta Cloud (acima) é a recomendação Tier 0 do oimpresso.
              </p>
            </div>
          </Card>
        </details>
      </div>
    </AppShellV2>
  );
}
