// Editar produto para quem só pode mexer nos locais de armazenagem (CU-PROD-20, proposta #10765,
// autorizada pelo [F] em 2026-11-12): permissão `product.armazenagem` sem `product.update`.
//
// A tela mostra o nome, o código e o bloco "Locais de armazenagem", e nada mais. O resto do produto
// não aparece, nem bloqueado: o servidor nem envia preço e imposto para quem não pode editá-los
// (UC-PEDIT-23). O envio vai para a rota só de locais (`PATCH /products/{id}/armazenagem`), que grava
// os locais, o endereço e o local padrão.

import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Inline } from '@/Components/layout';
import { BlocoArmazenagem } from './formulario/BlocoArmazenagem';
import { armazenagemInicial, camposDaArmazenagem } from './formulario/armazenagem';
import type { EnderecoNoEstoque, SaldoPorLocal } from './formulario/armazenagem';
import type { OptionMap } from './formulario/tipos';

export interface ProdutoSoArmazenagem {
  id: number;
  name: string;
  sku: string;
  localPadraoId?: number | null;
}

interface Props {
  product: ProdutoSoArmazenagem;
  rackDetails: Record<string, unknown> | unknown[] | null;
  businessLocations: OptionMap;
  enderecoNoEstoque?: EnderecoNoEstoque;
  saldoPorLocal?: SaldoPorLocal;
}

const SEM_ENDERECO: EnderecoNoEstoque = { prateleira: false, fileira: false, posicao: false };

export function EditarSoArmazenagem({ product: p, rackDetails, businessLocations, enderecoNoEstoque, saldoPorLocal }: Props) {
  const [armazenagem, setArmazenagem] = useState(() =>
    armazenagemInicial(rackDetails as never, { salvo: true, padrao: p.localPadraoId ?? null }),
  );
  const { patch, transform, processing, errors } = useForm({});
  const erros = errors as Record<string, string | undefined>;
  const mensagens = [erros.product_racks, erros.product_racks_remover, erros.local_padrao_id].filter(Boolean);

  const salvar = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    transform(() => camposDaArmazenagem(armazenagem));
    patch(`/products/${p.id}/armazenagem`, { preserveScroll: true });
  };

  return (
    <>
      <Head title={`Locais de armazenagem · ${p.name}`} />
      <div className="min-h-screen text-foreground">
        <header className="sticky top-0 z-30 bg-background/85 backdrop-blur border-b border-border">
          <Inline justify="between" gap={4} className="px-6 pt-4 pb-3">
            <div className="min-w-0">
              <Inline gap={1} className="text-[12px] text-muted-foreground">
                <Link href="/products" className="hover:underline">
                  Produtos
                </Link>
                <span className="text-muted-foreground">›</span>
                <span className="text-foreground font-medium">Locais de armazenagem</span>
              </Inline>
              <h1 className="mt-1 text-[24px] font-semibold tracking-tight truncate">{p.name}</h1>
              <div className="mt-1 font-mono text-[12px] text-muted-foreground">{p.sku}</div>
            </div>
            <Button variant="outline" asChild className="shrink-0">
              <Link href="/products">
                <ArrowLeft className="w-4 h-4 mr-1.5" />
                Voltar à lista
              </Link>
            </Button>
          </Inline>
        </header>

        <form id="produto-armazenagem-form" onSubmit={salvar} className="pb-16 px-6 mt-4" data-testid="editar-so-armazenagem">
          <Card>
            <CardHeader>
              <CardTitle>Locais de armazenagem</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-[12px] text-muted-foreground mb-3">
                Seu acesso permite mudar só os locais de armazenagem deste produto. O saldo muda por compra,
                venda, ajuste ou transferência.
              </p>
              <BlocoArmazenagem
                valor={armazenagem}
                mudar={setArmazenagem}
                locais={businessLocations}
                endereco={enderecoNoEstoque ?? SEM_ENDERECO}
                saldos={saldoPorLocal ?? {}}
              />
              {mensagens.map((m) => (
                <p key={m} role="alert" className="mt-2 text-[12px] text-destructive">
                  {m}
                </p>
              ))}
            </CardContent>
          </Card>
          <Inline wrap justify="end" gap={2} className="mt-6">
            <Button variant="ghost" asChild>
              <Link href="/products">Cancelar</Link>
            </Button>
            <Button type="submit" disabled={processing}>
              Salvar locais
            </Button>
          </Inline>
        </form>
      </div>
    </>
  );
}
