// Natureza fiscal do produto no Editar (UC-PEDIT-14 · CU-PROD-19).
//
// Quem decide se o produto é mercadoria (ICMS, nota de mercadoria) ou serviço (ISS, nota de
// serviço) é o contador, na tela de Tributação. O cadastro só mostra a decisão e leva até lá.
import type { NaturezaFiscalProduto } from './tipos';

const TEXTOS: Record<string, { titulo: string; detalhe: string }> = {
  servico: {
    titulo: 'Serviço',
    detalhe: 'A nota deste produto é de serviço e não leva NCM.',
  },
  mercadoria: {
    titulo: 'Mercadoria',
    detalhe: 'A nota deste produto é de mercadoria e precisa do NCM.',
  },
  pendente: {
    titulo: 'A decidir pelo contador',
    detalhe: 'O contador ainda não decidiu se este produto é mercadoria ou serviço.',
  },
};

export function NaturezaFiscal({ natureza }: { natureza: NaturezaFiscalProduto }) {
  const texto = TEXTOS[natureza.natureza] ?? TEXTOS.pendente;

  return (
    <div className="col-span-2" data-testid="natureza-fiscal">
      <p className="text-[13px]">
        <span className="font-medium">Natureza fiscal:</span> {texto.titulo}
        {natureza.natureza === 'servico' && natureza.codigoServico && (
          <span className="text-muted-foreground"> · item {natureza.codigoServico} da LC 116</span>
        )}
      </p>
      <p className="text-[12px] text-muted-foreground mt-1">
        {texto.detalhe} Quem decide é o contador, na Tributação.
        {natureza.link && (
          <>
            {' '}
            <a href={natureza.link} className="underline">
              Ver na Tributação
            </a>
          </>
        )}
      </p>
    </div>
  );
}
