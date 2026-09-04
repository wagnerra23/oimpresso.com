// SeloProcedencia.tsx — CU-FISC-16 · a superfície visual do selo de procedência.
//
// A lógica (store, preferência, vocabulário) vive em `_lib/procedencia.ts`; aqui fica só
// o JSX. A separação não é estética: `react-refresh/only-export-components` reprova
// arquivo que mistura componentes com não-componentes, e a catraca `eslint-baseline`
// conta cada warning.
//
// REUSO DO DS
// -----------
// `Badge` (variantes de ESTADO tokenizadas + `dot`, que é o AP7 do PRE-MERGE-UI) e
// `Tooltip`, ambos de `@/Components/ui`. NÃO o `shared/StatusBadge`: o mapping dele é
// `value -> {variant, label}` e não tem slot para a EXPLICAÇÃO, que é metade do contrato
// aqui — e estender a camada que 82 telas consomem, para um vocabulário de um módulo,
// aumentaria o raio sem ganho. Zero classe `fx-*` nova, zero token novo.

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/tooltip';
import { ShieldQuestion } from 'lucide-react';

import { btnProps } from '../_lib/botao-fiscal';
import {
  alternarProcedencia,
  ROTULO,
  TOM_DO_BADGE,
  useProcedenciaLigada,
  type MapaProcedencia,
} from '../_lib/procedencia';

interface SeloProps {
  mapa?: MapaProcedencia;
  /** Chave da superfície dentro do mapa (ex.: `sefaz`, `kpis`, `notas`). */
  chave: string;
}

/**
 * O selo de UMA superfície. Some quando o toggle está desligado, quando o
 * controller não declarou aquela chave, ou quando o mapa não veio.
 *
 * Ele ACOMPANHA o número — nunca o esconde nem o substitui.
 *
 * `tabIndex={0}` no `Badge` não é enfeite: o `Badge` é um `<span>`, e `<span>` não
 * recebe foco. Sem isso o Radix só abriria o tooltip no HOVER, e a EXPLICAÇÃO — que é
 * metade do contrato deste selo — ficaria inalcançável por teclado e por leitor de
 * tela. Um selo que diz "demonstração" sem dizer por quê é meia informação. Os selos
 * só entram no tab-order quando o operador liga a procedência, que é ação deliberada.
 */
export function SeloProcedencia({ mapa, chave }: SeloProps) {
  const ligadoAgora = useProcedenciaLigada();
  const p = mapa?.[chave];

  if (!ligadoAgora || !p) return null;

  return (
    <TooltipProvider delayDuration={150}>
      <Tooltip>
        <TooltipTrigger asChild>
          <Badge
            variant={TOM_DO_BADGE[p.origem]}
            dot
            data-contract="procedencia-selo"
            data-origem={p.origem}
            data-superficie={chave}
            tabIndex={0}
            className="ml-1.5 cursor-help align-middle text-[10px] font-normal"
          >
            {ROTULO[p.origem]}
          </Badge>
        </TooltipTrigger>
        <TooltipContent className="max-w-[280px]">{p.explica}</TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}

/**
 * O botão do cabeçalho. `aria-pressed` porque é um toggle de estado, não um comando —
 * é o que o leitor de tela precisa para anunciar "ativado/desativado".
 */
export function BotaoProcedencia() {
  const ligadoAgora = useProcedenciaLigada();

  return (
    <Button
      type="button"
      {...btnProps('ghost')}
      data-contract="procedencia"
      aria-pressed={ligadoAgora}
      onClick={alternarProcedencia}
      title="Mostra, por superfície, o que é leitura real e o que é demonstração"
    >
      <ShieldQuestion size={13} aria-hidden="true" />
      <span>Procedência</span>
    </Button>
  );
}
