// Ponto/Mobile — lembrete de bater ponto por push (ADR 0423).
//
// Só existe DENTRO do app das lojas (Capacitor sobre o ERP web, `com.oimpresso.app`): a ponte
// nativa injeta `window.Capacitor` e o plugin `PushNotifications` na própria página. No navegador
// comum não há ponte e o componente não renderiza nada. Sem pacote npm: o plugin é o proxy que a
// ponte já entrega (`Capacitor.Plugins.PushNotifications`).
//
// Quem envia o lembrete é o servidor (FCM), conforme a escala; aqui só se pede a permissão e se
// registra o token do aparelho na sessão do ERP. O push nunca bate ponto.
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import { Inline } from '@/Components/layout';

type Permissao = 'granted' | 'denied' | 'prompt' | 'prompt-with-rationale';

interface PushPlugin {
  checkPermissions(): Promise<{ receive: Permissao }>;
  requestPermissions(): Promise<{ receive: Permissao }>;
  register(): Promise<void>;
  addListener(evento: string, fn: (dado: never) => void): Promise<{ remove: () => Promise<void> }>;
}

interface CapacitorGlobal {
  isNativePlatform?: () => boolean;
  getPlatform?: () => string;
  Plugins?: { PushNotifications?: PushPlugin };
}

const CHAVE_TOKEN = 'ponto.push.token';
const CHAVE_PAROU = 'ponto.push.parou';

/** O plugin, se a página estiver rodando dentro do app; `null` no navegador comum. */
export function pluginPush(): PushPlugin | null {
  const cap = (window as unknown as { Capacitor?: CapacitorGlobal }).Capacitor;
  if (!cap?.isNativePlatform?.()) return null;
  return cap.Plugins?.PushNotifications ?? null;
}

function plataforma(): 'android' | 'ios' {
  const p = (window as unknown as { Capacitor?: CapacitorGlobal }).Capacitor?.getPlatform?.();
  return p === 'ios' ? 'ios' : 'android';
}

function ler(chave: string): string | null {
  try { return window.localStorage.getItem(chave); } catch { return null; }
}
function gravar(chave: string, valor: string | null): void {
  try {
    if (valor === null) window.localStorage.removeItem(chave);
    else window.localStorage.setItem(chave, valor);
  } catch { /* armazenamento indisponível: o próximo registro reenvia o token */ }
}

async function chamar(metodo: 'POST' | 'DELETE', corpo: Record<string, string>): Promise<Response> {
  const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
  return fetch('/ponto/mobile/push/dispositivo', {
    method: metodo,
    headers: {
      'Content-Type': 'application/json', Accept: 'application/json',
      'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify(corpo),
  });
}

type Estado = 'carregando' | 'ativo' | 'parado' | 'negado';

export default function LembretePush() {
  const [plugin] = useState(pluginPush);
  const [estado, setEstado] = useState<Estado>('carregando');
  const [ocupado, setOcupado] = useState(false);
  const ouvindo = useRef(false);

  const ouvir = useCallback(async (p: PushPlugin) => {
    if (ouvindo.current) return;
    ouvindo.current = true;
    // O token pode mudar a qualquer momento: cada `registration` reenvia ao servidor.
    await p.addListener('registration', async ({ value }: { value: string }) => {
      const r = await chamar('POST', { token: value, plataforma: plataforma() });
      if (r.ok) {
        gravar(CHAVE_TOKEN, value);
        setEstado('ativo');
      } else {
        toast.error('Não foi possível ativar o lembrete neste aparelho.');
        setEstado('parado');
      }
    });
    await p.addListener('registrationError', () => {
      toast.error('O aparelho não liberou as notificações.');
      setEstado('parado');
    });
    // Tocar na notificação abre a tela de bater ponto (só caminho interno do ERP).
    await p.addListener('pushNotificationActionPerformed', (acao: { notification?: { data?: { url?: string } } }) => {
      const url = acao?.notification?.data?.url;
      if (typeof url === 'string' && url.startsWith('/')) window.location.assign(url);
    });
  }, []);

  useEffect(() => {
    if (!plugin) return;
    (async () => {
      const { receive } = await plugin.checkPermissions();
      if (receive === 'denied') { setEstado('negado'); return; }
      if (receive === 'granted' && ler(CHAVE_PAROU) === null) {
        await ouvir(plugin);
        await plugin.register(); // reenvia o token a cada abertura
        return;
      }
      setEstado('parado');
    })().catch(() => setEstado('parado'));
  }, [plugin, ouvir]);

  if (!plugin) return null;

  async function ativar() {
    if (!plugin) return;
    setOcupado(true);
    try {
      const { receive } = await plugin.requestPermissions();
      if (receive !== 'granted') { setEstado('negado'); return; }
      gravar(CHAVE_PAROU, null);
      await ouvir(plugin);
      await plugin.register();
    } finally {
      setOcupado(false);
    }
  }

  async function parar() {
    setOcupado(true);
    try {
      const token = ler(CHAVE_TOKEN);
      if (token) await chamar('DELETE', { token });
      gravar(CHAVE_PAROU, '1');
      setEstado('parado');
    } finally {
      setOcupado(false);
    }
  }

  const texto = {
    carregando: 'Verificando…',
    ativo: 'Você recebe um aviso alguns minutos antes de cada horário da sua escala.',
    parado: 'Receba um aviso alguns minutos antes de cada horário da sua escala.',
    negado: 'Notificações bloqueadas — libere nas configurações do celular.',
  }[estado];

  return (
    <Inline data-contract="repp-lembrete" justify="between" gap={3} className="mx-auto w-full max-w-md rounded-md border p-3 text-sm">
      <div>
        <p className="font-medium">Lembrete de bater ponto</p>
        <p className="text-xs text-muted-foreground">{texto}</p>
      </div>
      {estado === 'ativo' && (
        <Button type="button" variant="ghost" size="sm" className="min-h-11" disabled={ocupado} onClick={parar}>Parar lembretes</Button>
      )}
      {estado === 'parado' && (
        <Button type="button" variant="outline" size="sm" className="min-h-11" disabled={ocupado} onClick={ativar}>Ativar</Button>
      )}
    </Inline>
  );
}
