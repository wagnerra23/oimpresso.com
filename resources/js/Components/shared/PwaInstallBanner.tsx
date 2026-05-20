// @memcofre
//   componente: PwaInstallBanner
//   us: US-FIN-036 (Onda 30) — PWA Financeiro
//   nota: Banner fixo topo só na rota /financeiro/* quando não está em standalone.
//         Captura beforeinstallprompt → expõe botão "Instalar app". Botão "Mais
//         tarde" esconde por 30 dias via localStorage. PT-BR.

import { useEffect, useState } from 'react';

const DISMISS_KEY = 'pwa_install_banner_dismissed_until';
const DISMISS_DAYS = 30;

interface BeforeInstallPromptEvent extends Event {
    readonly platforms: ReadonlyArray<string>;
    readonly userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
    prompt(): Promise<void>;
}

/**
 * Banner sutil topo de tela que oferece "Instalar app" quando:
 * - Browser suporta PWA install (capturou beforeinstallprompt)
 * - Rota atual começa com /financeiro
 * - Não está em standalone (já instalado)
 * - Usuário não dispensou nos últimos 30 dias
 */
export default function PwaInstallBanner() {
    const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (typeof window === 'undefined') return;

        // Não mostra se já está em standalone (PWA instalado e aberto)
        const isStandalone =
            window.matchMedia('(display-mode: standalone)').matches ||
            // iOS Safari não suporta display-mode standalone — usa navigator.standalone
            (window.navigator as unknown as { standalone?: boolean }).standalone === true;
        if (isStandalone) return;

        // Não mostra fora de /financeiro/*
        if (!window.location.pathname.startsWith('/financeiro')) return;

        // Verifica se foi dispensado recentemente
        try {
            const until = localStorage.getItem(DISMISS_KEY);
            if (until && Date.now() < parseInt(until, 10)) return;
        } catch {
            // localStorage indisponível (modo privado, quota) — ignora, mostra banner
        }

        const handler = (e: Event) => {
            e.preventDefault();
            setDeferredPrompt(e as BeforeInstallPromptEvent);
            setVisible(true);
        };

        window.addEventListener('beforeinstallprompt', handler as EventListener);

        // Se o app for instalado durante a sessão, esconde
        const onInstalled = () => {
            setVisible(false);
            setDeferredPrompt(null);
        };
        window.addEventListener('appinstalled', onInstalled);

        return () => {
            window.removeEventListener('beforeinstallprompt', handler as EventListener);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    if (!visible || !deferredPrompt) return null;

    const handleInstall = async () => {
        try {
            await deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            if (choice.outcome === 'accepted') {
                setVisible(false);
            }
        } catch {
            // prompt() falhou (ex.: já foi chamado) — só esconde
            setVisible(false);
        } finally {
            setDeferredPrompt(null);
        }
    };

    const handleDismiss = () => {
        try {
            const until = Date.now() + DISMISS_DAYS * 24 * 60 * 60 * 1000;
            localStorage.setItem(DISMISS_KEY, String(until));
        } catch {
            // ignora — fecha mesmo sem persistir
        }
        setVisible(false);
        setDeferredPrompt(null);
    };

    return (
        <div
            role="region"
            aria-label="Instalar aplicativo Financeiro"
            style={{
                position: 'fixed',
                top: 12,
                left: '50%',
                transform: 'translateX(-50%)',
                zIndex: 9999,
                width: '92%',
                maxWidth: 448,
                background: '#fafaf9',
                color: '#1c1917',
                border: '1px solid #e7e5e4',
                borderRadius: 10,
                boxShadow: '0 4px 20px rgba(0,0,0,0.08)',
                padding: '10px 14px',
                display: 'flex',
                alignItems: 'center',
                gap: 12,
                fontSize: 13.5,
                lineHeight: 1.35,
            }}
        >
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 600 }}>Instalar Financeiro</div>
                <div style={{ color: '#57534e', fontSize: 12.5 }}>
                    Acesso rápido do celular, mesmo sem o navegador aberto.
                </div>
            </div>
            <button
                type="button"
                onClick={handleDismiss}
                style={{
                    background: 'transparent',
                    border: 'none',
                    color: '#78716c',
                    cursor: 'pointer',
                    padding: '6px 10px',
                    borderRadius: 6,
                    fontSize: 12.5,
                    fontWeight: 500,
                }}
            >
                Mais tarde
            </button>
            <button
                type="button"
                onClick={handleInstall}
                style={{
                    background: '#1c1917',
                    color: '#fafaf9',
                    border: 'none',
                    cursor: 'pointer',
                    padding: '7px 14px',
                    borderRadius: 6,
                    fontSize: 12.5,
                    fontWeight: 600,
                    whiteSpace: 'nowrap',
                }}
            >
                Instalar app
            </button>
        </div>
    );
}
