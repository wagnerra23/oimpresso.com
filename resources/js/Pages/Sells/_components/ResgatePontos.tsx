// Resgate de pontos de fidelidade na venda (US-SELL-079).
//
// Os pontos nativos (business.enable_rp) já ganham, vencem e aparecem na ficha do cliente; faltava
// resgatar no caixa React. A tela calcula o valor para mostrar e descontar do total; o servidor
// confere pontos e valor antes de gravar (App\Services\Sells\ResgatePontosFidelidade) e recusa se
// não baterem. O saldo do cliente vem de POST /sells/pos/get-reward-details, o mesmo da Blade.
//
// Valor = pontos × valor do ponto, arredondado a 2 casas, contado em inteiros (décimos de milésimo
// de real) para não cair em ponto flutuante — a mesma conta do servidor.
import { useEffect, useState } from 'react';
import { Inline } from '@/Components/layout';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { valorDoResgate } from './resgate-pontos-valor';

export interface PontosFidelidadeConfig {
  nome: string;
  valor_ponto: string;
  minimo_venda: string;
  minimo_pontos: number;
}

function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

interface Props {
  config: PontosFidelidadeConfig;
  /** null = Cliente padrão (não resgata). */
  contactId: number | null;
  pontos: number;
  /** Total da venda antes do resgate, para o mínimo. */
  totalAntes: number;
  onChange: (pontos: number, valor: string) => void;
  erro?: string;
}

export default function ResgatePontos({ config, contactId, pontos, totalAntes, onChange, erro }: Props) {
  const [disponivel, setDisponivel] = useState<number | null>(null);

  useEffect(() => {
    onChange(0, '0.00');
    setDisponivel(null);
    if (contactId === null) return;
    let vivo = true;
    fetch('/sells/pos/get-reward-details', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf(),
      },
      credentials: 'same-origin',
      body: JSON.stringify({ customer_id: contactId }),
    })
      .then((r) => (r.ok ? r.json() : null))
      .then((d: { points?: number } | null) => {
        if (vivo) setDisponivel(d && typeof d.points === 'number' ? d.points : 0);
      })
      .catch(() => vivo && setDisponivel(0));
    return () => {
      vivo = false;
    };
    // onChange muda a cada render do pai; só o cliente reinicia o resgate.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [contactId]);

  if (contactId === null) return null;

  const abaixoDoMinimo = Number(config.minimo_venda) > 0 && totalAntes < Number(config.minimo_venda);
  const max = disponivel ?? 0;
  const pode = max > 0 && max >= config.minimo_pontos && !abaixoDoMinimo;
  const valor = valorDoResgate(pontos, config.valor_ponto);
  const formatBRL = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

  return (
    <div className="space-y-1.5" data-testid="resgate-pontos">
      <Label htmlFor="rp_redeemed">Resgatar {config.nome.toLowerCase()}</Label>
      <Inline gap={2}>
        <Input
          id="rp_redeemed"
          type="number"
          min={0}
          max={max}
          step={1}
          inputMode="numeric"
          className="w-28 tabular-nums"
          value={pontos || ''}
          disabled={!pode}
          onChange={(e) => {
            const n = Math.min(Math.max(Math.trunc(Number(e.target.value) || 0), 0), max);
            onChange(n, valorDoResgate(n, config.valor_ponto));
          }}
        />
        <span className="text-sm text-muted-foreground tabular-nums">
          = {formatBRL(Number(valor))}
        </span>
      </Inline>
      <p className="text-xs text-muted-foreground">
        {disponivel === null
          ? 'Consultando o saldo do cliente…'
          : abaixoDoMinimo
            ? `A venda precisa chegar a ${formatBRL(Number(config.minimo_venda))} para resgatar.`
            : max < config.minimo_pontos || max === 0
              ? `O cliente tem ${max} ${config.nome.toLowerCase()} — abaixo do mínimo para resgatar.`
              : `Disponível nesta venda: ${max} (cada um vale ${formatBRL(Number(config.valor_ponto))}).`}
      </p>
      {erro && <p className="text-xs text-destructive">{erro}</p>}
    </div>
  );
}
