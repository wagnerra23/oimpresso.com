// Missões do dia, desafio semanal e medalhas do portal (US-FISIO-008 · T-08c · UC-FPP-33, UC-FPP-34).
// Tudo vem do servidor (JogoService::constancia): o portal só mostra e envia a decisão do desafio.
// Sem ranking e sem moeda. Recusar o desafio não tira nada.
import { Award, Check, Target } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/Lib/utils';
import { enviar, type Constancia } from './portal';

export function CartaoConstancia({ token, inicial }: { token: string; inicial: Constancia }) {
  const [c, setC] = useState<Constancia>(inicial);
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const d = c.desafio;

  async function decidir(aceitar: boolean, meta?: number) {
    setEnviando(true);
    setErro(null);
    const r = await enviar('POST', `/fisio/p/${token}/desafio`, aceitar ? { aceitar, meta } : { aceitar });
    setEnviando(false);
    if (r.status === 200 && r.json.constancia) setC(r.json.constancia as Constancia);
    else setErro('Não deu para registrar sua escolha agora. Tente de novo mais tarde.');
  }

  return (
    <div className="flex flex-col gap-3" data-contract="fisio-portal-constancia">
      {c.missoes.length > 0 && (
        <section aria-label="Missões de hoje" className="flex flex-col gap-2 rounded-lg border border-border bg-card p-4">
          <h2 className="text-sm font-semibold">Missões de hoje</h2>
          <ul className="flex flex-col gap-2">
            {c.missoes.map((m) => (
              <li key={m.chave} className="flex items-center gap-3 text-sm">
                <span className={cn('flex size-6 shrink-0 items-center justify-center rounded-full border', m.cumprida ? 'border-primary bg-primary text-primary-foreground' : 'border-border')}>
                  {m.cumprida && <Check className="size-4" aria-hidden="true" />}
                </span>
                <span className={cn(m.cumprida && 'text-muted-foreground line-through')}>{m.texto}</span>
                <span className="sr-only">{m.cumprida ? 'cumprida' : 'a fazer'}</span>
              </li>
            ))}
          </ul>
        </section>
      )}

      {(d.oferecido || (d.decidido && d.aceito)) && (
        <section aria-label="Desafio da semana" className="flex flex-col gap-2 rounded-lg border border-border bg-card p-4" data-contract="fisio-portal-desafio">
          <h2 className="flex items-center gap-2 text-sm font-semibold"><Target className="size-4 text-primary" aria-hidden="true" />Desafio da semana</h2>
          {d.oferecido ? (
            <>
              <p className="text-sm text-muted-foreground">Quer combinar uma meta para esta semana? É opcional: não aceitar não tira nada.</p>
              <div className="flex flex-wrap gap-2">
                {d.opcoes.map((n) => (
                  <Button key={n} type="button" variant="secondary" className="h-11" disabled={enviando} onClick={() => decidir(true, n)}>
                    Cumprir {n} dias
                  </Button>
                ))}
                <Button type="button" variant="ghost" className="h-11" disabled={enviando} onClick={() => decidir(false)}>Agora não</Button>
              </div>
            </>
          ) : (
            <p className="text-sm">{d.pago ? `Meta cumprida: ${d.meta} dias nesta semana.` : `${d.cumpridos} de ${d.meta} dias cumpridos nesta semana.`}</p>
          )}
          {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}
        </section>
      )}

      {c.medalhas.length > 0 && (
        <section aria-label="Suas medalhas" className="flex flex-col gap-2 rounded-lg border border-border bg-card p-4">
          <h2 className="text-sm font-semibold">Suas medalhas</h2>
          <ul className="flex flex-wrap gap-2">
            {c.medalhas.map((m) => (
              <li key={m.codigo} className="flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-sm">
                <Award className="size-4 text-primary" aria-hidden="true" />{m.nome}
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}
