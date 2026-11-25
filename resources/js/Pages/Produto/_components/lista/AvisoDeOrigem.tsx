// "Você veio de… / Voltar e usar" (UC-PIDX-53) — o `.pb-volta` do protótipo
// (`prototipo-ui/cowork/Felipe/produto-blade.jsx`), com o contrato de `origem-de-busca.ts`.
//
// O protótipo volta sem dizer qual produto; aqui o produto é o que o usuário escolheu na lista:
// um único marcado, ou o que está aberto no painel. Sem escolha, o botão fica desligado e diz por quê.

import { ChevronRight, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import type { OrigemDaBusca } from './origem-de-busca';

interface Props {
  origem: OrigemDaBusca;
  /** Produto escolhido (marcado sozinho ou aberto no painel), com o nome para o botão. */
  escolhido: { id: number; nome: string; sku: string | null } | null;
  aoVoltar: (produtoId: number) => void;
  aoDispensar: () => void;
}

export default function AvisoDeOrigem({ origem, escolhido, aoVoltar, aoDispensar }: Props) {
  return (
    <div className="pb-volta" role="status" data-contract="produto-origem-da-busca">
      <ChevronRight size={13} aria-hidden />
      <span>
        Você veio de <b>{origem.rotulo}</b>
        {origem.para ? ` procurando um produto para “${origem.para}”` : ''}.
        {!escolhido && ' Marque um produto ou abra o painel dele para usar.'}
      </span>
      <div className="flex-1" />
      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={!escolhido}
        onClick={() => escolhido && aoVoltar(escolhido.id)}
      >
        {escolhido ? `Voltar e usar “${escolhido.nome}”` : 'Voltar e usar'}
      </Button>
      <Button type="button" variant="ghost" size="icon" aria-label="Dispensar" onClick={aoDispensar}>
        <X size={14} aria-hidden />
      </Button>
    </div>
  );
}
