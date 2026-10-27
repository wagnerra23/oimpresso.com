/**
 * Cartões de ação proposta pela Jana (ADR 0440) — bloco `action_card` do charter.
 *
 * Forma: `.jc-action` do `chat-jana.jsx`, que a âncora `jana-merge.jsx` §JmConversa
 * renderiza na thread — ícone + título + resumo + Confirmar/Cancelar; aceito fica
 * verde, cancelado fica vermelho. Os tokens do protótipo (`--warn-soft`, `--pos`,
 * `--neg`) viram os do DS (`warning`, `success`, `destructive`).
 *
 * O cartão só EXIBE o que o servidor devolve: o resumo é o recibo gravado na
 * proposta, e o status final vem da resposta da rota. Nada é decidido aqui.
 * Sem a flag `copiloto.chat_acoes.enabled` a rota dá 404 e nada é desenhado.
 */
import { useCallback, useEffect, useState } from 'react';
import { Check, ShieldCheck, X } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import { cn } from '@/Lib/utils';

export interface AcaoProposta {
  id: number;
  acao: string;
  status: 'proposta' | 'executada' | 'cancelada' | 'expirada' | 'falhou';
  resumo: string;
  expira_em: string | null;
  resultado: { id?: number; rotulo?: string; url?: string | null; erro?: string } | null;
}

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const TITULO: Record<AcaoProposta['status'], string> = {
  proposta: 'Confirma esta ação?',
  executada: 'Feito',
  cancelada: 'Ação cancelada',
  expirada: 'A proposta expirou',
  falhou: 'Não foi possível executar',
};

/** Busca as propostas da conversa e recarrega quando `versao` muda (fim de cada resposta). */
export function useAcoesPropostas(conversaId: number, versao: number) {
  const [acoes, setAcoes] = useState<AcaoProposta[]>([]);

  useEffect(() => {
    const ctrl = new AbortController();
    fetch(`/ia/conversas/${conversaId}/acoes`, {
      signal: ctrl.signal,
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then((r) => (r.ok ? r.json() : { acoes: [] }))
      .then((d) => setAcoes(Array.isArray(d.acoes) ? d.acoes : []))
      .catch(() => {});

    return () => ctrl.abort();
  }, [conversaId, versao]);

  return [acoes, setAcoes] as const;
}

export function JanaAcoesPropostas({ conversaId, versao }: { conversaId: number; versao: number }) {
  const [acoes, setAcoes] = useAcoesPropostas(conversaId, versao);
  const [enviando, setEnviando] = useState<number | null>(null);

  const decidir = useCallback(
    async (id: number, verbo: 'confirmar' | 'cancelar') => {
      setEnviando(id);
      try {
        const r = await fetch(`/ia/acoes-chat/${id}/${verbo}`, {
          method: 'POST',
          headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        });
        const d = await r.json().catch(() => ({}));
        if (!r.ok) {
          toast.error(d.erro ?? 'Não foi possível concluir.');
          if (r.status === 410 || r.status === 409) {
            setAcoes((xs) => xs.map((a) => (a.id === id ? { ...a, status: r.status === 410 ? 'expirada' : a.status } : a)));
          }
          return;
        }
        setAcoes((xs) => xs.map((a) => (a.id === id ? d.acao : a)));
      } finally {
        setEnviando(null);
      }
    },
    [setAcoes],
  );

  if (acoes.length === 0) return null;

  return (
    <div className="space-y-2 px-5 pb-3" data-jana-acoes>
      {acoes.map((a) => (
        <CartaoAcao key={a.id} acao={a} ocupado={enviando === a.id} onDecidir={decidir} />
      ))}
    </div>
  );
}

function CartaoAcao({
  acao,
  ocupado,
  onDecidir,
}: {
  acao: AcaoProposta;
  ocupado: boolean;
  onDecidir: (id: number, verbo: 'confirmar' | 'cancelar') => void;
}) {
  const aberta = acao.status === 'proposta';
  const ok = acao.status === 'executada';
  const Icone = ok ? Check : aberta ? ShieldCheck : X;

  return (
    <div
      role="group"
      aria-label={TITULO[acao.status]}
      data-acao-status={acao.status}
      className={cn(
        'flex items-start gap-[11px] rounded-md border px-[15px] py-[13px]',
        aberta && 'border-warning/30 bg-warning/10',
        ok && 'border-success/30 bg-success/10',
        !aberta && !ok && 'border-destructive/30 bg-destructive/10',
      )}
    >
      <Icone
        aria-hidden
        className={cn(
          'mt-px h-[18px] w-[18px] shrink-0',
          aberta && 'text-warning',
          ok && 'text-success',
          !aberta && !ok && 'text-destructive',
        )}
      />
      <div className="min-w-0 flex-1">
        <b className="block text-[13px] font-semibold leading-snug text-foreground">{TITULO[acao.status]}</b>
        <small className="mt-[3px] block text-[11.5px] text-muted-foreground">{acao.resumo}</small>
        {ok && acao.resultado?.url && (
          <a href={acao.resultado.url} className="mt-1 inline-block text-[11.5px] font-medium text-primary hover:underline">
            Abrir {acao.resultado.rotulo ?? 'registro'}
          </a>
        )}
        {aberta && (
          <div className="mt-[11px] flex gap-2">
            <Button size="sm" disabled={ocupado} onClick={() => onDecidir(acao.id, 'confirmar')}>
              <Check className="h-3.5 w-3.5" aria-hidden /> Confirmar
            </Button>
            <Button size="sm" variant="outline" disabled={ocupado} onClick={() => onDecidir(acao.id, 'cancelar')}>
              Cancelar
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}
