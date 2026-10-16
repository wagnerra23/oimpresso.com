import type { ReactNode } from 'react';
import { useTheme } from '@/Hooks/useTheme';

// Cadeia de etapas numeradas; etapas com regra ficam marcadas (classe `r` do bundle).
// Fonte visual: `Cadeia` em prototipo-ui/cowork/Wagner/fluxos-page.jsx.

export function Cadeia({ etapas, marcadas }: { etapas: string[]; marcadas?: Set<string> }) {
  return (
    <ol className="flx-chain">
      {etapas.map((e, i) => (
        <li key={e} className={marcadas?.has(e) ? 'r' : ''}>
          <span className="n">{i + 1}</span>
          {e}
        </li>
      ))}
    </ol>
  );
}

/** Selo do encaixe: Recomendado (ok) · Serve (accent) · Parcial/Outro perfil (mute). */
export function SeloEncaixe({ n, rotulo }: { n: number; rotulo: string }) {
  const tom = n >= 4 ? 'ok' : n === 3 ? 'accent' : 'mute';
  return <span className={`flx-pill ${tom}`}>{rotulo}</span>;
}

/**
 * Gaveta e diálogo vão em portal, fora do `.cockpit` — os tokens (`--border`, `--text-mute`…) não
 * chegam e as bordas do bundle viram 0. Este invólucro reaplica `.cockpit` + o tema, com
 * `display: contents` porque `.cockpit` também é o grid do shell (100vh/100vw, cockpit.css): sem
 * caixa própria, só a herança das variáveis fica. Inline porque a regra do shell está fora de @layer.
 */
export function TemaDoCockpit({ children }: { children: ReactNode }) {
  const { effective } = useTheme();
  return <div className="cockpit" data-theme={effective} style={{ display: 'contents' }}>{children}</div>;
}
