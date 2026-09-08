// ForjaRunbook — o painel "Trilhas de papel" da aba Trabalho.
//
// @memcofre
//   tela: drawer da /forja/trabalho (botão "Papéis" da fj-toolbar)
//   module: Forja
//   adrs: 0388 (réplica primeiro) · 0282 (protocolo v2) · UI-0013 · UI-0029 (protótipo soberano na FORMA)
//   permissao: herda a da tela (jana.mcp.usage.all)
//   paridade: forma de `prototipo-ui/cowork/forja-runbook.jsx` (fonte visual)
//   dado: NENHUM — deriva de `trabalhoTokens.ts` (PAPEIS, FASE_HUE), zero query
//
// ── POR QUE O CONTEÚDO NÃO É CÓPIA DO PROTÓTIPO ────────────────────────────
// O `forja-runbook.jsx` declara, em prosa, que sua fonte é "PROTOCOL.md §1–§3".
// Fui conferir antes de copiar, e a tabela de estado do próprio PROTOCOL.md marca:
//
//   §1 Os 6 papéis          🪦 superado  → v2 = 2 papéis ([CC] e [W]); ADR 0282
//   §2 As fases             🟡 corpo superado (só o overlay vige)
//   §3 Critérios de transição 🪦 superado → gates humanos viraram checks de CI
//
// Copiar o texto normativo dele ("F1.5 [CD] design-critique", "escape hatch
// /design-override", "F2 aprovação síncrona do screenshot") produziria uma tela de
// ONBOARDING que ensina o loop v1 — e onboarding errado é pior que onboarding
// ausente, porque tem cara de canon e a próxima sessão obedece.
//
// A saída não é deixar de fazer o painel: é trocar a FONTE do conteúdo, mantendo
// a FORMA. A precedência de FORMA (UI-0029) põe o protótipo acima de tudo — e é ela
// que este arquivo segue: mesmas classes `fj-rb-*`, mesma estrutura de drawer, mesma
// ordem de seções. Mas conteúdo normativo não é eixo do protótipo (proibicoes.md,
// "eixo FORMA tem cadeia própria"), então ele sai das fontes VIVAS do main:
//
//   · os papéis        → `PAPEIS` de trabalhoTokens.ts (7, com nome/agente/cor/desc)
//   · as fases         → `FASE_HUE` de trabalhoTokens.ts (7, com o hue canônico)
//   · quem dona a fase → DERIVADO, invertendo PAPEIS pelo prefixo do `desc`
//                        ("F1 — protótipo visual" ⇒ F1 é do [CC]). Não é um mapa
//                        que eu escrevi: some o papel, some o badge, sem editar aqui.
//
// ── AUSENTE E DECLARADO (não é esquecimento; ver o PLACAR no PR) ───────────
//   · o texto "o que este papel FAZ nesta fase" por passo  → a fonte está superada
//   · os escape hatches (/design-override, /screenshot-override, /a11y-override)
//                                                          → idem, §3 superado
// Os dois voltam quando o PROTOCOL v2 tiver um §1/§3 vigente pra derivar. Enquanto
// não tem, o painel diz o que SABE (quem é quem, que fases existem) e cala o resto,
// em vez de afirmar o que caducou.

import { useEffect, useRef } from 'react';
import { X } from 'lucide-react';
import { RoleBadge } from './trabalhoAtomos';
import { FASE_HUE, PAPEIS } from './trabalhoTokens';
import type { CSSProperties } from 'react';

/**
 * Dono de cada fase, derivado de `PAPEIS` — não declarado à mão.
 *
 * Cada papel carrega no `desc` a fase que ele serve ("F3.5 — WCAG 2.1 AA").
 * Inverter isso mantém UMA fonte: se o token mudar de dono, o painel acompanha
 * sozinho; se o papel sumir, a fase fica sem badge em vez de mentir.
 *
 * `W` ("Decide · aprova screenshot e merge") não casa com o padrão de propósito —
 * ele não é dono de UMA fase, e forçá-lo a uma seria inventar.
 */
function donoDaFase(fase: string): string | null {
  const achado = Object.keys(PAPEIS).find((papel) => {
    const m = PAPEIS[papel]?.desc.match(/^(F[\d.]+)/);
    return m ? m[1] === fase : false;
  });
  return achado ?? null;
}

export default function ForjaRunbook({ onClose }: { onClose: () => void }) {
  const asideRef = useRef<HTMLElement>(null);

  // O protótipo mede 🔴 em "drawer sem role/aria-modal, foco fica no BODY"
  // (pacote de export §2, Onda 0a). Isso é defeito declarado DELE — replicar
  // seria exportar dívida, então o drawer nasce com o contrato certo.
  useEffect(() => {
    asideRef.current?.focus();
  }, []);

  // Esc e clique-fora vivem no `document`, não em handler do JSX. Não é
  // preferência de estilo: pendurar `onClick` no backdrop faz dele um elemento
  // estático interativo sem equivalente por teclado — três violações
  // `jsx-a11y` que a catraca de lint pega, e pegou. Ouvir aqui fecha nos dois
  // caminhos (ponteiro e teclado) e deixa o backdrop puramente decorativo.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    const onDown = (e: MouseEvent) => {
      if (!asideRef.current?.contains(e.target as Node)) onClose();
    };
    document.addEventListener('keydown', onKey);
    // `mousedown` na captura: o clique de ABERTURA ainda está subindo quando
    // este efeito monta, e no bubble ele fecharia o painel no mesmo gesto.
    document.addEventListener('mousedown', onDown, true);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.removeEventListener('mousedown', onDown, true);
    };
  }, [onClose]);

  const papeis = Object.keys(PAPEIS);
  const fases = Object.keys(FASE_HUE);

  return (
    <div className="fj-drawer-back" data-testid="forja-runbook-back">
      <aside
        ref={asideRef}
        className="fj-drawer"
        role="dialog"
        aria-modal="true"
        aria-label="Trilhas de papel"
        tabIndex={-1}
        data-testid="forja-runbook"
      >
        <header className="fj-dr-head">
          <div className="fj-dr-head-l">
            <span className="fj-dr-id">Trilhas de papel</span>
          </div>
          <button type="button" className="icon-btn" onClick={onClose} aria-label="Fechar" data-testid="forja-runbook-fechar">
            <X size={14} aria-hidden />
          </button>
        </header>

        <div className="fj-dr-body">
          <p className="fj-dr-desc">
            Quem é quem no loop e quais fases existem. Onboarding de papel novo — humano ou agente.
          </p>

          <div className="fj-dr-sec">
            {/* Contagem derivada: o protótipo escreve "6 papéis" literal e o main tem
                7. Número escrito à mão apodrece no primeiro papel novo (§5 2026-07-17). */}
            <h3>{papeis.length} papéis</h3>
            <ul className="fj-rb-roles" data-testid="forja-runbook-papeis">
              {papeis.map((papel) => {
                const a = PAPEIS[papel];
                if (!a) return null;
                return (
                  <li key={papel}>
                    <RoleBadge papel={papel} showName />
                    <span className="fj-rb-kind">{a.agente ? 'agente' : 'humano'}</span>
                    <span className="fj-rb-desc">{a.desc}</span>
                  </li>
                );
              })}
            </ul>
          </div>

          <div className="fj-dr-sec">
            <h3>
              {fases.length} fases · {fases[0]}→{fases[fases.length - 1]}
            </h3>
            <ul className="fj-rb-steps" data-testid="forja-runbook-fases">
              {fases.map((fase) => {
                const dono = donoDaFase(fase);
                return (
                  <li key={fase} style={{ '--ph': FASE_HUE[fase] } as CSSProperties}>
                    <div className="fj-rb-step-top">
                      <span className="fj-rb-fase">{fase}</span>
                      {dono && <RoleBadge papel={dono} />}
                    </div>
                  </li>
                );
              })}
            </ul>
            {/* Ausência declarada, com motivo — o padrão do projeto pra slot sem
                fonte. Sem isto, a próxima sessão lê o painel curto como bug e
                "completa" com o texto v1 que este arquivo evitou de propósito. */}
            <p className="fj-dr-desc">
              O que cada papel faz em cada fase não aparece aqui: a fonte desse texto
              (PROTOCOL.md §1–§3) está marcada como superada pelo próprio PROTOCOL, e o
              protocolo v2 ainda não tem seção equivalente pra derivar.
            </p>
          </div>
        </div>
      </aside>
    </div>
  );
}
