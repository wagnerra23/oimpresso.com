// Estado de ERRO das telas do Patrimônio — o `MP.Estado erro` do protótipo
// (`prototipo-ui/cowork/Wagner/patrimonio-page.jsx:846-850`): título dizendo o que não veio,
// a garantia de que nada se perdeu, e "Tentar de novo" (botão fantasma).
//
// É o `EmptyState` do DS na variante `error`, dentro de `role="alert"` para o leitor de tela
// anunciar quando o esqueleto vira erro. O par com a detecção é `useFalhaDeferida`.

import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import type { FalhaDeferida } from './falhaDeferida';

interface Props {
  /** O que não carregou, no texto do protótipo: "Não consegui carregar …". */
  titulo: string;
  falha: FalhaDeferida;
}

export default function FalhaDeCarga({ titulo, falha }: Props) {
  return (
    <div role="alert" data-contract="estado-erro">
      <EmptyState
        variant="error"
        icon="alert-triangle"
        title={titulo}
        description="A consulta falhou. Nada foi perdido — o que estava cadastrado continua no banco. Tente de novo; se insistir, avise o suporte."
        action={
          <Button variant="ghost" onClick={falha.tentarDeNovo} disabled={falha.tentando}>
            {falha.tentando ? 'Tentando…' : 'Tentar de novo'}
          </Button>
        }
      />
    </div>
  );
}
