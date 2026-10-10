// Menu "Colunas" — o `MenuColunas` do protótipo (produto-blade.jsx) no `ui/dropdown-menu`.
// É o "Visibilidade da coluna" da lista Blade, agora visível pro operador e lembrado no
// navegador (`oimpresso.produto.lista.colunas`).

import { Columns3 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { visibilidadePadrao, type ColunaOpcional } from './tipos';

interface Props {
  colunas: ColunaOpcional[];
  visiveis: Record<string, boolean>;
  aoMudar: (visiveis: Record<string, boolean>) => void;
}

export default function MenuColunas({ colunas, visiveis, aoMudar }: Props) {
  const n = colunas.filter((c) => visiveis[c.id]).length;
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm">
          <Columns3 size={12} aria-hidden /> Colunas <span className="font-mono opacity-60">{n}</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="min-w-[236px]">
        {colunas.map((c) => (
          <DropdownMenuCheckboxItem
            key={c.id}
            checked={visiveis[c.id] === true}
            // Mantém o menu aberto: o operador costuma ligar/desligar várias de uma vez.
            onSelect={(e) => e.preventDefault()}
            onCheckedChange={(v) => aoMudar({ ...visiveis, [c.id]: v === true })}
          >
            {c.rotulo}
          </DropdownMenuCheckboxItem>
        ))}
        <DropdownMenuSeparator />
        <DropdownMenuItem onSelect={() => aoMudar(visibilidadePadrao(colunas))}>Voltar ao padrão</DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
