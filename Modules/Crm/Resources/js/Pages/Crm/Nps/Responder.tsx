// Página pública — não usa AppShellV2/Sidebar do ERP. O cliente abre pelo link do WhatsApp,
// no celular, e dá uma nota de 0 a 10 (US-CRM-097). Forma: quadro A de
// prototipo-ui/cowork/Wagner/prototipos/crm-nps-e-captura.html.

import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';

interface Props {
  token: string;
  empresa: string;
  /** Nota já registrada; null enquanto não respondeu. */
  nota: number | null;
}

const NOTAS = Array.from({ length: 11 }, (_, i) => i);

export default function NpsResponder({ token, empresa, nota }: Props) {
  const nome = empresa || 'a nossa empresa';
  const [escolhida, setEscolhida] = useState<number | null>(null);
  const form = useForm<{ nota: number | null; comentario: string }>({ nota: null, comentario: '' });

  function enviar(e: React.FormEvent) {
    e.preventDefault();
    if (escolhida === null) return;
    form.transform((d) => ({ ...d, nota: escolhida }));
    form.post(`/nps/${token}`, { preserveScroll: true });
  }

  return (
    <main className="flex min-h-screen items-start justify-center bg-muted/40 px-4 py-8">
      <Head title={`Avalie ${nome}`} />
      <div className="w-full max-w-sm overflow-hidden rounded-xl border bg-card">
        <header className="border-b px-5 py-4">
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{nome}</p>
        </header>

        {nota !== null ? (
          <section className="px-5 py-8 text-center" data-testid="nps-obrigado">
            <p className="text-4xl font-semibold tabular-nums text-primary">{nota}</p>
            <p className="mt-2 font-semibold">Obrigado pela resposta</p>
            <p className="mt-1 text-sm text-muted-foreground">Sua nota foi registrada. Pode fechar esta página.</p>
          </section>
        ) : (
          <form onSubmit={enviar} className="flex flex-col gap-4 px-5 py-5">
            <h1 className="text-lg font-semibold leading-snug">
              De 0 a 10, quanto você recomendaria {nome} a um amigo ou colega?
            </h1>
            <div>
              <div className="grid grid-cols-6 gap-1.5" role="group" aria-label="Nota de 0 a 10">
                {NOTAS.map((n) => (
                  <button
                    key={n}
                    type="button"
                    aria-pressed={escolhida === n}
                    onClick={() => setEscolhida(n)}
                    className={
                      'h-11 rounded-md border text-base font-semibold tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ' +
                      (escolhida === n ? 'border-primary bg-primary text-primary-foreground' : 'bg-card hover:border-primary')
                    }
                    data-testid={`nps-nota-${n}`}
                  >
                    {n}
                  </button>
                ))}
              </div>
              <div className="mt-1.5 flex justify-between text-xs text-muted-foreground">
                <span>0 · nada provável</span>
                <span>10 · muito provável</span>
              </div>
              {form.errors.nota && <p className="mt-1 text-sm text-destructive">{form.errors.nota}</p>}
            </div>
            <label htmlFor="nps-comentario" className="flex flex-col gap-1.5 text-sm font-medium text-muted-foreground">
              O que pesou na sua nota? (opcional)
              <Textarea
                id="nps-comentario"
                maxLength={1000}
                placeholder="Conte em poucas palavras"
                value={form.data.comentario}
                onChange={(e) => form.setData('comentario', e.target.value)}
              />
            </label>
            <Button type="submit" disabled={escolhida === null || form.processing}>
              Enviar resposta
            </Button>
          </form>
        )}

        <footer className="border-t px-5 py-3 text-xs text-muted-foreground">
          O link vale para uma resposta.
        </footer>
      </div>
    </main>
  );
}
