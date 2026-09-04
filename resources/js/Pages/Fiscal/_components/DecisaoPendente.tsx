// DecisaoPendente.tsx — "Decisão [W] pendente"
//
// Port do bloco `.fx-decisao` do protótipo Cowork (`fiscal-subpages.jsx:196,306,315`): quando a
// tela depende de uma decisão que só [W] pode tomar, ela **diz isso ao usuário** em vez de
// apresentar o estado provisório como se fosse o definitivo.
//
// A FONTE É A MESMA DO BLOCO DE DÉBITOS, e isso não é economia — é o que impede inventar:
// são os bullets `[BACKLOG]` cujo marcador contém literalmente `decisão [W]`, já marcados
// `decisao: true` por `scripts/governance/fiscal-debitos-derive.mjs`. São 4 hoje, e três deles
// são exatamente as três instâncias que o protótipo desenha (DF-e Histórico · Config Séries ·
// Config Ambiente); o quarto (as 4 superfícies de demonstração do Cockpit) o protótipo ainda
// não pintou. Derivar achou-o sozinho.
//
// DELTA CONSCIENTE vs o protótipo, e o motivo:
//
// 1. RESOLVE POR TELA, NÃO POR ABA. Ali o bloco vive dentro da aba a que se refere (Histórico,
//    Séries, Ambiente). Os `.casos.md` NÃO carregam o vínculo com a aba — derivá-lo do texto
//    ("A aba de séries...") seria adivinhação, e conteúdo sem âncora é justamente o que este
//    bloco existe para não fazer. A decisão é da tela; é assim que ela aparece.
//
// 2. A COPY PERDE O "no vivo". O protótipo escreve "Decisão [W] pendente **no vivo**" porque
//    ele é o protótipo apontando para produção. Em produção esse dêitico não tem referente —
//    o leitor JÁ está no vivo. O resto da copy é literal.
//
// 3. A MOLDURA É O `Alert` DO DS com borda tracejada (o `.fx-decisao` usa `1px dashed`), não a
//    classe `fx-*`. O tracejado sobrevive porque é o que distingue "ainda não decidido" de
//    "assim é" — a única parte da aparência que carrega significado aqui.

import { Stack } from '@/Components/layout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';

import { DEBITOS_CONHECIDOS } from '../_lib/debitos-conhecidos';

interface DecisaoPendenteProps {
  /** A `route` da tela (o mesmo id de `_lib/paginas-fiscais`). */
  route: string;
}

export default function DecisaoPendente({ route }: DecisaoPendenteProps) {
  const itens = DEBITOS_CONHECIDOS.filter(d => d.tela === route && d.decisao);

  // Estado vazio = NÓ AUSENTE. Uma tela sem decisão pendente não deve desenhar um lugar onde
  // decisões pendentes apareceriam — isso convidaria a lê-lo como "nada pendente por ora",
  // que é afirmação, não ausência.
  if (itens.length === 0) return null;

  return (
    // `Stack asChild`, não `flex flex-col` solto: ADR 0253 (o `layout:check` é ratchet).
    // `gap={2}` porque o Stack enumera o espaço em token — o mesmo do bloco de dívida.
    <Stack gap={2} asChild className="mt-4">
      <section data-contract="decisao-pendente">
        <h3 className="m-0 text-[11px] font-semibold uppercase tracking-[0.07em] text-muted-foreground">
          Decisão [W] pendente
        </h3>

        {itens.map(d => (
          <Alert
            key={`${d.ancora}::${d.titulo}`}
            data-ancora={d.ancora}
            className="border-dashed border-warning/40 bg-warning-soft/30"
          >
            {/* `line-clamp-none` desfaz o `line-clamp-1` padrão do AlertTitle: aqui o título é a
                decisão em aberto, e truncá-la é esconder metade da pergunta. */}
            <AlertTitle className="line-clamp-none text-pretty">{d.titulo}</AlertTitle>
            <AlertDescription>
              <p className="max-w-[92ch] text-pretty">{d.texto}</p>
            </AlertDescription>
          </Alert>
        ))}
      </section>
    </Stack>
  );
}
