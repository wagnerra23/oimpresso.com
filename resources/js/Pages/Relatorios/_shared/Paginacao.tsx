// Paginação dos relatórios com tabela paginada no servidor (playbook sistema/07).
// Recebe o que o paginator do Laravel devolve e só navega: quem pagina é o servidor.
import { Inline } from '@/Components/layout';
import { Button } from '@/Components/ui/button';

export type PaginacaoInfo = { atual: number; ultima: number; total: number };

export function Paginacao({ info, irPara }: { info: PaginacaoInfo; irPara: (pagina: number) => void }) {
  if (info.ultima <= 1) {
    return <p className="text-xs text-muted-foreground">{info.total} {info.total === 1 ? 'registro' : 'registros'}</p>;
  }

  return (
    <Inline data-contract="paginacao" justify="between" gap={2} className="text-xs text-muted-foreground">
      <span aria-live="polite">Página {info.atual} de {info.ultima} · {info.total} registros</span>
      <Inline gap={2}>
        <Button variant="outline" size="sm" disabled={info.atual <= 1} onClick={() => irPara(info.atual - 1)}>Anterior</Button>
        <Button variant="outline" size="sm" disabled={info.atual >= info.ultima} onClick={() => irPara(info.atual + 1)}>Próxima</Button>
      </Inline>
    </Inline>
  );
}
