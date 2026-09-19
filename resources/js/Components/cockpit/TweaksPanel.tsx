// @memcofre
//   modulo: Cockpit (TweaksPanel)
//   adrs: UI-0008 (cockpit como layout-mae)
//   nota: FAB flutuante bottom-right + card com 2 controles (Vibe / Densidade).
//         O 3o — "Tom do accent" — foi REMOVIDO na UI-0034: ele deixava a preferencia
//         de UM navegador mandar na cor do Design System, e o hue nao e ajuste de
//         usuario, e token do DS (protototipo soberano na forma, UI-0029).

import { Sliders, X } from 'lucide-react';

import { VIBES, Vibe } from './shared';

export function TweaksPanel({
  vibe,
  onVibe,
  density,
  onDensity,
  open,
  onToggle,
}: {
  vibe: Vibe;
  onVibe: (v: Vibe) => void;
  density: number;
  onDensity: (n: number) => void;
  open: boolean;
  onToggle: () => void;
}) {
  if (!open) {
    return (
      <div className="cockpit-tweaks">
        <button
          className="cockpit-tweaks-fab"
          type="button"
          title="Abrir Tweaks"
          onClick={onToggle}
        >
          <Sliders size={18} />
        </button>
      </div>
    );
  }
  return (
    <div className="cockpit-tweaks">
      <div className="cockpit-tweaks-card">
        <div className="cockpit-tweaks-card-h">
          <span className="title">Tweaks</span>
          <button className="close" type="button" onClick={onToggle} title="Fechar">
            <X size={14} />
          </button>
        </div>

        <div className="cockpit-tweaks-section">
          <div className="cockpit-tweaks-label">Vibe</div>
          <div className="cockpit-tweaks-sublabel">
            <span>Atmosfera</span>
            <span style={{ color: 'var(--text-mute)' }}>{vibe}</span>
          </div>
          <div className="cockpit-tweaks-radio">
            {VIBES.map((v) => (
              <button
                key={v.id}
                type="button"
                className={vibe === v.id ? 'active' : ''}
                onClick={() => onVibe(v.id)}
              >
                {v.label}
              </button>
            ))}
          </div>
        </div>

        <div className="cockpit-tweaks-section">
          <div className="cockpit-tweaks-label">Densidade</div>
          <div className="cockpit-tweaks-sublabel">
            <span>Skim ↔ Briefing</span>
            <span style={{ color: 'var(--text-mute)' }}>{density}%</span>
          </div>
          <input
            type="range"
            className="cockpit-tweaks-slider"
            min={0}
            max={100}
            step={5}
            value={density}
            onChange={(e) => onDensity(Number(e.target.value))}
          />
        </div>
      </div>
    </div>
  );
}
