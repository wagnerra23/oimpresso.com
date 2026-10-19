// App instalável do portal do paciente (etapa 02 §3.6 · T-13 · UC-FPP-26, UC-FPP-28).
// - registrarAppDoPortal (em portal.ts): liga o service worker do portal (public/sw-fisio-portal.js), que
//   guarda a tela do dia para abrir sem rede. Falha calada: o portal funciona igual sem ele.
// - ConviteInstalar: a partir do 3º dia cumprido, "Adicionar à tela inicial". No Android usa o
//   convite do navegador; no iPhone não existe convite, então só mostra o passo a passo para
//   quem tocar. Não aparece em app já instalado nem depois de "Agora não".
// Não reusa o PwaInstallBanner: ele é do Financeiro (rota fixa, cor literal) e aparece sempre.
import { Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/Components/ui/button';

interface ConviteDoNavegador extends Event {
  prompt(): Promise<void>;
  readonly userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const DIAS_PARA_CONVIDAR = 3;
const CHAVE_DISPENSA = 'fisio_portal_convite_dispensado';

function instalado(): boolean {
  return window.matchMedia('(display-mode: standalone)').matches || (window.navigator as { standalone?: boolean }).standalone === true;
}

function ehIphone(): boolean {
  return /iPhone|iPad|iPod/.test(window.navigator.userAgent);
}

function dispensado(): boolean {
  try {
    return window.localStorage.getItem(CHAVE_DISPENSA) === '1';
  } catch {
    return false;
  }
}

export function ConviteInstalar({ diasCumpridos }: { diasCumpridos: number }) {
  const [convite, setConvite] = useState<ConviteDoNavegador | null>(null);
  const [iphone, setIphone] = useState(false);
  const [passos, setPassos] = useState(false);
  const [fechado, setFechado] = useState(true);

  useEffect(() => {
    if (instalado() || dispensado()) return;
    setFechado(false);
    setIphone(ehIphone());
    const aoConvidar = (e: Event) => {
      e.preventDefault();
      setConvite(e as ConviteDoNavegador);
    };
    const aoInstalar = () => setFechado(true);
    window.addEventListener('beforeinstallprompt', aoConvidar);
    window.addEventListener('appinstalled', aoInstalar);
    return () => {
      window.removeEventListener('beforeinstallprompt', aoConvidar);
      window.removeEventListener('appinstalled', aoInstalar);
    };
  }, []);

  if (fechado || diasCumpridos < DIAS_PARA_CONVIDAR || (!convite && !iphone)) return null;

  const agoraNao = () => {
    try {
      window.localStorage.setItem(CHAVE_DISPENSA, '1');
    } catch {
      // sem armazenamento: fecha só nesta visita
    }
    setFechado(true);
  };

  const instalar = async () => {
    if (!convite) return setPassos(true);
    await convite.prompt().catch(() => undefined);
    const escolha = await convite.userChoice.catch(() => null);
    setConvite(null);
    if (escolha?.outcome === 'accepted') setFechado(true);
  };

  return (
    <section aria-label="Adicionar à tela inicial" className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4" data-contract="fisio-portal-instalar">
      <p className="flex items-start gap-2 text-sm">
        <Smartphone className="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
        <span>Coloque seus exercícios na tela inicial do celular. Abre com um toque, mesmo sem internet.</span>
      </p>
      {passos ? (
        <ol className="list-decimal pl-5 text-sm text-muted-foreground">
          <li>Toque em Compartilhar, embaixo da tela do Safari.</li>
          <li>Escolha "Adicionar à Tela de Início".</li>
          <li>Toque em "Adicionar".</li>
        </ol>
      ) : (
        <div className="flex gap-2">
          <Button type="button" className="h-11 flex-1 rounded-lg" onClick={instalar}>{convite ? 'Adicionar à tela inicial' : 'Como adicionar'}</Button>
          <Button type="button" variant="ghost" className="h-11" onClick={agoraNao}>Agora não</Button>
        </div>
      )}
    </section>
  );
}
