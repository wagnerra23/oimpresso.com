// JanaPlanoBadge — o selo `plano Pro` / `plano Grátis` do header da área Jana.
//
// POR QUE ELE NÃO EXISTIA ATÉ AGORA (e por que agora pode existir):
// a onda 4 da paridade deixou este item como **BLOQUEADO por ausência de fonte**
// (`PARIDADE-area-jana-diagnostico-e-ondas.md` §8.1) — não por trabalho. Em produção
// nada sabia o plano: o `ProController` mandava `'plan' => 'free'` LITERAL, não havia
// coluna nem tabela de tier, e no protótipo o selo é `cfg.pro`, um toggle de simulação
// cuja própria legenda diz *"aqui o Pro é simulação pra ver o gating"*. Pintar o selo
// naquele momento seria afirmar um estado que o sistema não sabia.
//
// Em 2026-08-27 a medição fechou pelos dois lados (código E banco de produção) e [W]
// decidiu o caminho: `jana_pro_module` vira chave de pacote marcável no Superadmin,
// SEM billing. O selo passa a ler um fato — `shell`/`jana.pro`, derivado da assinatura
// — em vez de um literal. Billing real segue Sprint JANA-B (ADR 0140, US-COPI-211/212).
//
// ÂNCORA (medida, não presumida — `node prototipo-ui/ancora.mjs Jana/Index`):
//   `prototipo-ui/cowork/jana-merge.jsx:970` →
//     <button className={"jm-plano" + (pro ? " pro" : "")} onClick={abre Configurar}
//             title="Plano atual · abre Configurar">plano {pro ? "Pro" : "Grátis"}</button>
//   `jana-merge.css:57` (base) → radius 99px, mono 10.5px, cor/borda/bg em `--accent`
//   `jana-merge.css:98` (`.pro`) → os mesmos três em `--pos` (verde)
//   Posição: `chat-jana.jsx:217` → slot `{plano}` na zona DIREITA (`jc-header-r`),
//   depois de "Atualizado HH:MM" e antes de Configurar/Exportar.
//
// COPY É CONTRATO: `plano Pro` / `plano Grátis` são literais do protótipo aprovado,
// com o "plano" em minúscula. Não são frase minha e não se reescrevem aqui.
//
// DIVERGÊNCIA declarada (1, e é de token — não de posição nem de copy): a âncora pinta
// com as cores cruas `--accent`/`--pos`; aqui vai pelo par semântico do `Badge` canon
// (`info` / `success`), porque cor crua em `.tsx` reprova no `ds/no-inline-raw-color` e
// porque `JanaCockpit.tsx:188` já fixou a regra da área — *"pill de ESTADO → par SOFT do
// Badge canon"*. `success` é a família de `--pos`; `info`, a de `--accent`.
//
// POR QUE NÃO ENTROU DENTRO DO `JanaAreaHeader`: aquele componente é compartilhado com
// `governance/Custos.tsx` e `governance/QualidadeIa.tsx` (medido: 2 consumidores fora da
// área Jana). Renderizar o selo lá dentro o faria aparecer em telas que não são da Jana e
// cujo plano não significa nada. Entra pelo slot `actions`, que cada tela já injeta.

import { Badge } from '@/Components/ui/badge';

export interface JanaPlanoBadgeProps {
  /**
   * `true` = pacote com `jana_pro_module`. Vem de `shell`/`jana.pro`
   * (`HandleInertiaRequests::janaPlanoPro`), nunca de estado do cliente — o protótipo
   * grava `pro` no localStorage e o `useJanaConfig` RECUSA justamente porque o servidor
   * não honra aquilo. Fail-safe do backend é `false`: sem pacote legível o selo diz
   * "Grátis", que é o estado de quem não comprou, em vez de afirmar Pro por engano.
   */
  pro: boolean;
  /**
   * Abre o drawer Configurar — é o destino da âncora (`onClick={() => setConfig(true)}`),
   * onde a seção "Plano" já existe e linka pra `/ia/pro`. Obrigatório de propósito: as
   * três telas que recebem o selo (Painel · Conversa · Memória) têm o drawer, e um selo
   * que não leva a lugar nenhum seria decoração.
   */
  onConfigurar: () => void;
}

export function JanaPlanoBadge({ pro, onConfigurar }: JanaPlanoBadgeProps) {
  return (
    <Badge
      asChild
      variant={pro ? 'success' : 'info'}
      className="cursor-pointer font-mono text-[10.5px] tracking-tight"
    >
      <button type="button" onClick={onConfigurar} title="Plano atual · abre Configurar">
        plano {pro ? 'Pro' : 'Grátis'}
      </button>
    </Badge>
  );
}

export default JanaPlanoBadge;
