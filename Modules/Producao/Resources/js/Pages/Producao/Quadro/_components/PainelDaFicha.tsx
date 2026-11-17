// UC-PCOSF-08 · a Ficha da OS abre no painel lateral do Quadro (decisão [W] no #10850: segue o protótipo `FichaOs` de
// prototipo-ui/cowork/Wagner/producao-cv-os.jsx, painel de 760px). O conteúdo é o mesmo `FichaDaOs` da página.
//
// O Quadro recebe `ficha` (a base) e as abas como props optional `ficha_*` (UC-PCOSF-07). As abas chegam por partial
// reload e ficam guardadas aqui com o id da OS: o Inertia mantém as props de uma visita parcial para a outra, e sem o
// id a aba de uma OS apareceria na seguinte. `async` para duas abas pedidas juntas não cancelarem uma à outra.
import { router } from '@inertiajs/react';
import { useLayoutEffect, useState } from 'react';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Skeleton } from '@/Components/ui/skeleton';
import FichaDaOs, { type AbaNome, type FichaAbas, type FichaBase } from '../../_components/FichaDaOs';

const PROP: Record<AbaNome, string> = { custo: 'ficha_custo', materiais: 'ficha_materiais', artes: 'ficha_artes', pessoas: 'ficha_pessoas' };

interface Props {
  /** OS aberta (da URL ou do clique em curso); null = painel fechado. */
  osId: number | null;
  /** `ficha` do Quadro: undefined enquanto não chegou, null quando a OS não é desta empresa ou não existe. */
  ficha: FichaBase | null | undefined;
  /** true entre o clique e a resposta: a `ficha` que está nas props ainda é a anterior. */
  carregando: boolean;
  onFechar: () => void;
}

export default function PainelDaFicha({ osId, ficha, carregando, onFechar }: Props) {
  const [cockpit, setCockpit] = useState<HTMLElement | null>(null);
  useLayoutEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), []);
  const [abas, setAbas] = useState<{ os: number; v: FichaAbas } | null>(null);

  const daOs = !carregando && ficha && ficha.os.id === osId ? ficha : null;
  const naoAchou = !carregando && ficha === null && osId !== null;
  const abasDaOs: FichaAbas = abas && daOs && abas.os === daOs.os.id ? abas.v : {};

  const pedir = (lista: AbaNome[], junto: string[] = []) => {
    if (!daOs) return;
    const id = daOs.os.id;
    router.reload({
      only: [...junto, ...lista.map((a) => PROP[a])],
      async: true,
      onSuccess: (page) => setAbas((cur) => ({
        os: id,
        v: { ...(cur?.os === id ? cur.v : {}), ...Object.fromEntries(lista.map((a) => [a, page.props[PROP[a]]])) },
      })),
    });
  };
  // Depois de uma ação (liberar, refazer, cancelar, trocar arte) a OS pode mudar de coluna: o quadro vem junto.
  const recarregar = (lista?: AbaNome[]) => pedir(lista ?? (Object.keys(abasDaOs) as AbaNome[]), ['ficha', 'cartoes', 'midias']);

  return (
    <Sheet open={osId !== null} onOpenChange={(o) => { if (!o) onFechar(); }}>
      <SheetContent side="right" container={cockpit} className="w-full overflow-y-auto sm:max-w-[760px]" data-contract="producao-quadro-ficha">
        <SheetHeader>
          <SheetTitle>{daOs ? daOs.os.numero : naoAchou ? 'OS não encontrada' : 'Abrindo a OS…'}</SheetTitle>
          <SheetDescription>{daOs ? (daOs.os.produto ?? 'Ficha da OS') : naoAchou ? 'Esta OS não existe ou é de outra empresa.' : 'Ficha da OS'}</SheetDescription>
        </SheetHeader>
        {daOs
          ? <FichaDaOs key={daOs.os.id} ficha={daOs} abas={abasDaOs} carregar={(a) => pedir([a])} recarregar={recarregar} />
          : !naoAchou && <div className="px-4" aria-busy="true"><Skeleton className="h-64 w-full" /></div>}
      </SheetContent>
    </Sheet>
  );
}
