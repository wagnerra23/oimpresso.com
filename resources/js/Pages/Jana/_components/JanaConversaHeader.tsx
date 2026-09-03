// Cabeçalho da thread ativa — porte 1:1 de `prototipo-ui/cowork/jana-merge.jsx`
// §`JmConversa` (`.jm-conv-h`, medido no espelho): título 13px/600 + `só sua` em
// mono 10.5px + pílula `arquivada` quando for o caso.
//
// Por que NÃO o `ThreadHeader` de `@/Components/cockpit/Thread` (que estava aqui):
//   · ele é vocabulário de MENSAGEIRO — avatar `JA` circular com gradiente,
//     subtítulo "Assistente IA · Jana" e três ícones (telefone / info / ⋯). Ligar
//     pra uma IA e "ver detalhes do contato" não são ações desta tela;
//   · o avatar circular viola literalmente um §UX Anti-pattern do próprio charter:
//     *"❌ Avatar circular emoji-style (canon = letra/glyph monocromático)"*;
//   · a âncora não desenha nada disso.
// O `ThreadHeader` NÃO foi tocado (é componente compartilhado) — só deixou de ser
// consumido aqui. Medido em 2026-09-03: `@/Components/cockpit/Thread` tinha UM
// consumidor no repo inteiro, este arquivo, e só do `ThreadHeader`.
//
// `só sua` é CONSTANTE, e isso não é dado inventado: a âncora carrega o mesmo
// comentário — *"Conversa é sempre só sua: não existe compartilhamento na
// produção"* —, e o `ChatController` prova a afirmação com
// `abort_unless($conversa->user_id === auth()->id(), 403)` em quatro pontos. Se um
// dia existir participante, o rótulo passa a vir do payload; hoje ele descreve o
// modelo real. Já `arquivada` lê `conversa.status`, que o payload JÁ manda.

const STATUS_ARQUIVADA = 'arquivada';

export function JanaConversaHeader({
  titulo,
  status,
}: {
  titulo: string;
  status?: string | null;
}) {
  const arquivada = (status ?? '').toLowerCase() === STATUS_ARQUIVADA;

  return (
    <header className="jana-conv-h flex flex-wrap items-baseline gap-2.5 px-4 pt-3 pb-2">
      <b className="text-[13px] font-semibold text-foreground">{titulo}</b>
      <span className="font-mono text-[10.5px] text-muted-foreground">só sua</span>
      {arquivada && (
        <span className="rounded-full border border-border px-2 py-0.5 font-mono text-[10.5px] text-muted-foreground">
          arquivada
        </span>
      )}
    </header>
  );
}

export default JanaConversaHeader;
