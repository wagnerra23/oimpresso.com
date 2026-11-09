// Card "Produção" do Editar (CU-PROD-22, decisão [W] opção A · ADR 0436).
//
// Só leitura: mostra o roteiro de produção que o produto segue. O vínculo produto → roteiro é
// trocado em Produção → Roteiros, não aqui. Sem roteiro o produto não vira OS (UC-PCROT-02); o
// texto do protótipo para esse caso ("usa as etapas do modelo da empresa") só vale quando a ADR
// 0436 item 3 existir.
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

export interface ProducaoDoProduto {
  roteiro: { nome: string; etapas: number; ativo: boolean } | null;
  /** Null quando o usuário não tem `producao.access`: o card fica sem o link. */
  linkRoteiros: string | null;
}

function etapas(n: number): string {
  return n === 1 ? '1 etapa' : `${n} etapas`;
}

export function BlocoProducao({ producao }: { producao: ProducaoDoProduto }) {
  const r = producao.roteiro;

  return (
    <Card data-testid="bloco-producao">
      <CardHeader>
        <CardTitle>Produção</CardTitle>
      </CardHeader>
      <CardContent>
        <p className="text-[12px] text-muted-foreground">Roteiro de produção · só leitura</p>
        <p className="text-[13px] font-medium mt-1">
          {r ? `${r.nome} · ${etapas(r.etapas)}` : 'Sem roteiro: este produto não vira OS.'}
          {r && !r.ativo && <span className="text-warning-fg font-normal"> · roteiro inativo</span>}
        </p>
        <p className="text-[12px] text-muted-foreground mt-1">
          {r
            ? 'A OS deste produto copia essas etapas. Para trocar, use Produção → Roteiros.'
            : 'Para gerar OS, ligue um roteiro em Produção → Roteiros.'}
          {producao.linkRoteiros && (
            <>
              {' '}
              <a href={producao.linkRoteiros} className="underline">
                {r ? 'Ver o roteiro' : 'Abrir Roteiros'}
              </a>
            </>
          )}
        </p>
      </CardContent>
    </Card>
  );
}
