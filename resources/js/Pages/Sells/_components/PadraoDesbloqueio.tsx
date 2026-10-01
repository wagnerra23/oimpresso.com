import { Button } from '@/Components/ui/button';
import { tocarPonto } from './reparoVenda';

type Props = {
  id: string;
  valor: string;
  onChange: (padrao: string) => void;
};

/**
 * Padrão de desbloqueio do aparelho (UC-S05) — grade 3×3, mesmo formato do patternlock.js
 * do POS Blade: a sequência de pontos 1–9 tocados ("14789"). Toque em ordem; cada ponto
 * mostra a posição em que entrou. Sem arrastar: funciona igual com mouse, toque e teclado.
 */
export default function PadraoDesbloqueio({ id, valor, onChange }: Props) {
  return (
    <div className="flex items-start gap-3">
      <div
        id={id}
        role="group"
        aria-label={`Padrão de desbloqueio${valor ? `: ${valor.split('').join(' → ')}` : ''}`}
        className="grid grid-cols-3 gap-2 rounded-md border border-border p-2"
      >
        {[1, 2, 3, 4, 5, 6, 7, 8, 9].map((ponto) => {
          const ordem = valor.indexOf(String(ponto));
          const usado = ordem >= 0;
          return (
            <button
              key={ponto}
              type="button"
              aria-label={usado ? `Ponto ${ponto}, ${ordem + 1}º da sequência` : `Ponto ${ponto}`}
              aria-pressed={usado}
              onClick={() => onChange(tocarPonto(valor, ponto))}
              className={
                'flex h-8 w-8 items-center justify-center rounded-full border text-xs font-medium transition-colors ' +
                (usado ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-muted/50')
              }
            >
              {usado ? ordem + 1 : ''}
            </button>
          );
        })}
      </div>
      <Button type="button" variant="ghost" size="sm" onClick={() => onChange('')} disabled={valor === ''}>
        Limpar
      </Button>
    </div>
  );
}
