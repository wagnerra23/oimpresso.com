// DebitosConhecidos.tsx — "Débitos conhecidos desta tela"
//
// Port da Onda 5 do protótipo Cowork (`FsDebitos` / `.fx-debitos` em
// `prototipo-ui/cowork/Wagner/fiscal-subpages.jsx:9-25`, e `FxDebitosPage` em `fiscal-page.jsx:69`):
// a tela declara a própria dívida em vez de fingir completude.
//
// A LISTA NÃO É ESCRITA AQUI. Ela é derivada dos bullets `[BACKLOG]` dos sete
// `<Tela>.casos.md` por `scripts/governance/fiscal-debitos-derive.mjs`, cujo docblock
// registra a medição que motivou derivar: a constante escrita à mão do protótipo já
// afirmava duas coisas falsas sobre o sistema, porque o repo andou e ela não.
//
// FORMA = A DO PROTÓTIPO (2026-11-23). Até aqui o item era o `Alert` do DS com um `Badge` de
// rótulo, declarado como "delta consciente". Medido produção × protótipo no escuro, o Alert
// saía com padding 12/16, raio 12px, título e texto em 14px e um selo que o protótipo não tem;
// a regra `.fx-debito` do `fiscal-page.css` (L216-223) é padding 10/14, raio 8px, fundo
// `--bg-2`, título `--fs-3` e texto `--fs-2`. No eixo forma o protótipo manda (UI-0029), então
// o markup e a regra agora são os dele. O RÓTULO do marcador (`sem teste`, `source-grep`…) não
// aparece no protótipo; ele segue no dado como `data-rotulo`, para o teste e para quem inspeciona.

import { DEBITOS_CONHECIDOS } from '../_lib/debitos-conhecidos';

interface DebitosConhecidosProps {
  /** A `route` da tela (o mesmo id de `_lib/paginas-fiscais`). */
  route: string;
}

export default function DebitosConhecidos({ route }: DebitosConhecidosProps) {
  // `!d.decisao` — os itens que esperam decisão [W] têm bloco próprio (`DecisaoPendente`), e
  // um item aparece em UM lugar só. Sem o filtro, os 4 seriam listados duas vezes na mesma
  // tela, e a repetição leria como duas dívidas onde há uma.
  const itens = DEBITOS_CONHECIDOS.filter(d => d.tela === route && !d.decisao);

  // Estado vazio = NÓ AUSENTE, não contêiner vazio nem mensagem — o mesmo contrato que a
  // fila de alertas do cockpit já segue (§"Contrato da fila de alertas" do Cockpit.charter).
  if (itens.length === 0) return null;

  return (
    <section className="fx-debitos" data-contract="debitos-conhecidos">
      <h3>Débitos conhecidos desta tela</h3>
      {itens.map(d => (
        <div
          key={`${d.ancora}::${d.titulo}`}
          className="fx-debito"
          data-tom={d.tom}
          data-ancora={d.ancora}
          data-rotulo={d.rotulo}
        >
          <b>{d.titulo}</b>
          <small>{d.texto}</small>
        </div>
      ))}
    </section>
  );
}
