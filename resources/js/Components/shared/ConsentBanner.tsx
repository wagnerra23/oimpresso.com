// @memcofre
//   componente: ConsentBanner
//   adrs: ADR 0191 (consent banner LGPD pra Microsoft Clarity), ADR 0190 (primary roxo 295)
//   nota: Barra inferior fixa não-modal (padrão Linear/Notion 2026). Portão LGPD
//         pra qualquer analytics futuro. Botões: Aceitar tudo / Só essenciais /
//         Personalizar (abre dialog com checkboxes per-categoria).

import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { setConsent, syncConsentToWindow, type ConsentSnapshot } from '@/Lib/consent';

// ADR 0190 — primary roxo universal 295. Sobrescreve `bg-primary` default
// (que varia por theme) pra ação forte do portão LGPD.
const PRIMARY_PURPLE_CLASS =
  '!bg-[oklch(0.55_0.15_295)] !text-white !border-[oklch(0.45_0.15_295)] hover:!bg-[oklch(0.50_0.15_295)]';

interface ConsentPageProps { consent?: ConsentSnapshot }

export default function ConsentBanner() {
  const consent = usePage<ConsentPageProps>().props.consent;

  const [open, setOpen] = useState(false);
  const [analytics, setAnalytics] = useState(true);
  const [marketing, setMarketing] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [dismissed, setDismissed] = useState(false);

  // Espelha em window.__consent pra hasConsent() funcionar fora de React.
  useEffect(() => { if (consent) syncConsentToWindow(consent); }, [consent]);

  if (!consent?.needs_banner || dismissed) return null;

  const submit = (a: boolean, m: boolean) => {
    if (submitting) return;
    setSubmitting(true);
    setConsent({ analytics: a, marketing: m })
      .then(() => { setDismissed(true); setOpen(false); })
      .catch(() => { /* silencioso — user pode tentar de novo */ })
      .finally(() => setSubmitting(false));
  };

  return (
    <>
      <div
        role="region"
        aria-label="Aviso de cookies"
        className="fixed bottom-0 inset-x-0 z-[9998] border-t border-zinc-200 bg-white shadow-[0_-2px_8px_rgba(0,0,0,0.04)]"
        style={{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }}
      >
        <div className="mx-auto max-w-7xl px-4 py-3 sm:px-6 sm:py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-sm text-zinc-700 leading-relaxed max-w-3xl">
            Usamos cookies pra entender como você usa o oimpresso e melhorar a experiência.
            Dados sensíveis (CPF, email, telefone) <strong>não são capturados</strong>.
          </p>
          <div className="flex flex-wrap items-center gap-2 sm:flex-nowrap sm:gap-3 sm:shrink-0">
            <button
              type="button"
              onClick={() => setOpen(true)}
              disabled={submitting}
              className="text-sm text-zinc-600 underline-offset-4 hover:underline disabled:opacity-50"
            >Personalizar</button>
            <Button variant="ghost" size="sm" onClick={() => submit(false, false)} disabled={submitting}>
              Só essenciais
            </Button>
            <Button size="sm" onClick={() => submit(true, true)} disabled={submitting} className={PRIMARY_PURPLE_CLASS}>
              Aceitar tudo
            </Button>
          </div>
        </div>
      </div>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Preferências de cookies</DialogTitle>
            <DialogDescription>
              Escolha quais categorias você aceita. Pode mudar depois nas configurações.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2">
            <label className="flex items-start gap-3 cursor-not-allowed opacity-70">
              <Checkbox checked disabled className="mt-0.5" />
              <div className="grid gap-1">
                <span className="text-sm font-medium">Essenciais (obrigatório)</span>
                <span className="text-xs text-zinc-500">Sessão, login, segurança CSRF. Sem isso o sistema não funciona.</span>
              </div>
            </label>
            <label className="flex items-start gap-3 cursor-pointer">
              <Checkbox checked={analytics} onCheckedChange={(v) => setAnalytics(v === true)} className="mt-0.5" />
              <div className="grid gap-1">
                <span className="text-sm font-medium">Análise de uso</span>
                <span className="text-xs text-zinc-500">Mapas de calor e replays anonimizados. Dados sensíveis são mascarados.</span>
              </div>
            </label>
            <label className="flex items-start gap-3 cursor-pointer">
              <Checkbox checked={marketing} onCheckedChange={(v) => setMarketing(v === true)} className="mt-0.5" />
              <div className="grid gap-1">
                <span className="text-sm font-medium">Marketing</span>
                <span className="text-xs text-zinc-500">Pixels e tags pra mensurar campanhas (caso ativemos no futuro).</span>
              </div>
            </label>
          </div>

          <DialogFooter>
            <Button variant="ghost" onClick={() => setOpen(false)} disabled={submitting}>Cancelar</Button>
            <Button onClick={() => submit(analytics, marketing)} disabled={submitting} className={PRIMARY_PURPLE_CLASS}>
              Salvar preferências
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
