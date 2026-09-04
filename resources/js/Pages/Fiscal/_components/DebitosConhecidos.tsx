// DebitosConhecidos.tsx — "Débitos conhecidos desta tela"
//
// Port da Onda 5 do protótipo Cowork (`FsDebitos` / `.fx-debitos` em
// `prototipo-ui/cowork/fiscal-subpages.jsx:9-25`, e `FxDebitosPage` em `fiscal-page.jsx:69`):
// a tela declara a própria dívida em vez de fingir completude.
//
// A LISTA NÃO É ESCRITA AQUI. Ela é derivada dos bullets `[BACKLOG]` dos sete
// `<Tela>.casos.md` por `scripts/governance/fiscal-debitos-derive.mjs`, cujo docblock
// registra a medição que motivou derivar: a constante escrita à mão do protótipo já
// afirmava duas coisas falsas sobre o sistema, porque o repo andou e ela não.
//
// DELTA CONSCIENTE vs o protótipo, e o motivo: a moldura ali é a classe `.fx-debito`, com
// `border-left` tonal própria. Aqui a moldura é o `Alert` do Design System e o tom vira
// `Badge` nas variantes de ESTADO já tokenizadas (`danger`/`warning`/`info`) — classe
// `fx-*` nova é proibida, e a variante de estado do Badge é exatamente o par
// `-soft`/`-fg` que o AP7 do PRE-MERGE-UI pede (dot + texto colorido, sem fill sólido).
// A borda esquerda tonal sobrevive por token (`border-l-warning` etc.), não por hex.
//
// `Badge` e não `StatusBadge`: o `StatusBadge` é status-de-DOMÍNIO, resolvido por
// `kind` + `value` num mapping interno. Severidade de débito não é valor de domínio, e
// abrir um `kind` para ela editaria um arquivo que 14 telas importam — alcance que este
// bloco não precisa. As variantes de estado do `Badge` são o mesmo DS, sem esse raio.

import { Stack } from '@/Components/layout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';

import { DEBITOS_CONHECIDOS, type TomDebito } from '../_lib/debitos-conhecidos';

/** Borda esquerda tonal — o equivalente do `.fx-debito[data-tom]` do protótipo, por token. */
const BORDA_POR_TOM: Record<TomDebito, string> = {
  danger: 'border-l-destructive',
  warning: 'border-l-warning',
  info: 'border-l-info',
};

interface DebitosConhecidosProps {
  /** A `route` da tela (o mesmo id de `_lib/paginas-fiscais`). */
  route: string;
}

export default function DebitosConhecidos({ route }: DebitosConhecidosProps) {
  const itens = DEBITOS_CONHECIDOS.filter(d => d.tela === route);

  // Estado vazio = NÓ AUSENTE, não contêiner vazio nem mensagem — o mesmo contrato que a
  // fila de alertas do cockpit já segue (§"Contrato da fila de alertas" do Cockpit.charter).
  if (itens.length === 0) return null;

  return (
    // `Stack asChild` e não `flex flex-col` solto: ADR 0253 (o `layout:check` é ratchet e
    // mordeu este arquivo). `gap={2}` (8px) porque o Stack enumera o espaço em token — o
    // `gap-1.5` (6px) que estava aqui era px literal, que é justamente o que a ADR remove.
    <Stack gap={2} asChild className="mt-4">
      <section data-contract="debitos-conhecidos">
        <h3 className="m-0 text-[11px] font-semibold uppercase tracking-[0.07em] text-muted-foreground">
          Débitos conhecidos desta tela
        </h3>

        {itens.map(d => (
          <Alert
            key={`${d.ancora}::${d.titulo}`}
            data-tom={d.tom}
            data-ancora={d.ancora}
            className={`border-l-2 ${BORDA_POR_TOM[d.tom]}`}
          >
            {/* `line-clamp-none` desfaz o `line-clamp-1` que o AlertTitle traz por padrão:
                o título aqui é o contrato do débito, e truncar contrato é esconder a
                violação em vez de acomodá-la. */}
            <AlertTitle className="line-clamp-none text-pretty">{d.titulo}</AlertTitle>
            <AlertDescription>
              <p className="max-w-[92ch] text-pretty">{d.texto}</p>
              <Badge variant={d.tom} dot>
                {d.rotulo}
              </Badge>
            </AlertDescription>
          </Alert>
        ))}
      </section>
    </Stack>
  );
}
