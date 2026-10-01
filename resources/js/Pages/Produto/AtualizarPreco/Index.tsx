// @memcofre
//   tela: /update-product-price (Atualizar preço por planilha)
//   module: Produto
//   stories: playbook Produto thread 06 (SellingPriceGroupController@updateProductPrice · Blade → Inertia)
//   permissao: product.update
//
// Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/produto-acoes.jsx → AtualizarPreco()
// Contrato: governance/design/contracts/produto-atualizar-preco.contract.json
//
// ⛔ Regra mestre de valor: esta tela NÃO calcula nem lê preço. A planilha sobe como arquivo
// para as MESMAS rotas da Blade (`/export-product-price`, `/import-product-price`). A conferência
// é o import() do servidor rodado dentro de uma transação desfeita — o que aparece aqui é o que
// ele gravaria, não um cálculo do navegador.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router, usePage } from '@inertiajs/react';
import { useRef, useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { PageHeader } from '@/Components/PageHeader';
import { Inline, Stack } from '@/Components/layout';

interface Grupo { id: number; nome: string }
interface Linha { sku: string; produto: string; campo: string; antes: string | null; depois: string | null }
interface Alerta { sku: string; tipo: 'ambiguo' | 'outro_negocio' }
interface Conferencia { ok: boolean; msg: string | null; linhas: Linha[]; alertas: Alerta[] }
interface Props { grupos: Grupo[]; total_linhas?: number; erro?: string | null }

const csrf = () => document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

function Rotulo({ children }: { children: ReactNode }) {
  return <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{children}</span>;
}

export default function AtualizarPrecoIndex({ grupos, total_linhas, erro }: Props) {
  const { flash } = usePage<{ flash?: { success?: string | null } }>().props;
  const inputRef = useRef<HTMLInputElement>(null);
  const [arquivo, setArquivo] = useState<File | null>(null);
  const [conf, setConf] = useState<Conferencia | null>(null);
  const [conferindo, setConferindo] = useState(false);
  const [aplicando, setAplicando] = useState(false);

  const conferir = async (f: File) => {
    setArquivo(f); setConf(null); setConferindo(true);
    const fd = new FormData();
    fd.append('product_group_prices', f);
    fd.append('conferir', '1');
    const dados = await fetch('/import-product-price', {
      method: 'POST', body: fd,
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
    }).then((r) => r.json() as Promise<Conferencia>).catch(() => null);
    setConf(dados ?? { ok: false, msg: 'Não foi possível conferir a planilha.', linhas: [], alertas: [] });
    setConferindo(false);
  };

  // Aplica o MESMO arquivo que foi conferido, na mesma rota da Blade.
  const aplicar = () => {
    if (!arquivo) return;
    setAplicando(true);
    router.post('/import-product-price', { product_group_prices: arquivo }, {
      forceFormData: true,
      onFinish: () => { setAplicando(false); setArquivo(null); setConf(null); },
    });
  };

  const bloqueado = !conf || !conf.ok || conf.alertas.length > 0 || conferindo || aplicando;

  return (
    <Stack gap={4}>
      <div data-contract="produto-atualizar-preco-header">
        <PageHeader title="Atualizar preço" subtitle="Preço de venda e preços por grupo de todos os produtos, por planilha." />
      </div>

      <Card data-contract="produto-atualizar-preco-planilha">
        <CardContent className="p-4">
          <Stack gap={3}>
            <Inline gap={2} className="justify-between">
              <h2 className="text-sm font-medium">Atualizar preço por planilha</h2>
              <span className="text-xs text-muted-foreground">
                <Deferred data="total_linhas" fallback={<span>…</span>}>
                  <span>{total_linhas ?? 0} linha(s) na exportação</span>
                </Deferred>
                {' · '}{grupos.length} grupo(s) ativo(s)
              </span>
            </Inline>
            <div role="note" className="rounded-md border border-border bg-muted/40 p-3 text-sm">
              <b>Exporte, edite, devolva.</b> A planilha sai com uma coluna por grupo de preço ativo.
              Não mexa na coluna de SKU — é ela que casa a linha com o produto.
            </div>
            {flash?.success && <p role="status" className="text-sm text-primary">{flash.success}</p>}
            {erro && <p role="alert" className="text-sm text-destructive">{erro} Nada foi alterado.</p>}

            <div className="grid gap-4 md:grid-cols-2" data-contract="produto-atualizar-preco-passos">
              <Stack gap={2}>
                <Rotulo>Passo 1 — exportar</Rotulo>
                <div><Button asChild variant="outline"><a href="/export-product-price">Exportar preços atuais</a></Button></div>
              </Stack>
              <Stack gap={2}>
                <Rotulo>Passo 2 — devolver</Rotulo>
                <Inline gap={2}>
                  <Button variant="outline" onClick={() => inputRef.current?.click()} disabled={conferindo || aplicando}>Escolher planilha</Button>
                  <input ref={inputRef} type="file" accept=".xls,.xlsx,.csv" className="hidden" aria-label="Planilha de preços"
                    onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ''; if (f) void conferir(f); }} />
                  <Button onClick={aplicar} disabled={bloqueado}>{aplicando ? 'Aplicando…' : 'Aplicar preços'}</Button>
                </Inline>
                <p className="text-xs text-muted-foreground">
                  {!arquivo ? 'Nenhum arquivo escolhido'
                    : conferindo ? `${arquivo.name} · conferindo no servidor…`
                      : `${arquivo.name} · conferido no servidor`}
                </p>
              </Stack>
            </div>

            {conf && <ResultadoConferencia conf={conf} />}
          </Stack>
        </CardContent>
      </Card>

      <Card data-contract="produto-atualizar-preco-instrucoes">
        <CardContent className="p-4">
          <Stack gap={3}>
            <h2 className="text-sm font-medium">Instruções</h2>
            <ol className="list-decimal space-y-1 pl-5 text-sm">
              <li>Exporte primeiro: a planilha já vem com todos os produtos e os grupos de preço ativos.</li>
              <li>Altere só os preços — de venda (com imposto) e por grupo. Não mude SKU nem cabeçalhos.</li>
              <li>Escolha a planilha: a conferência mostra o antes → depois de cada preço sem gravar nada.</li>
              <li>Grupo desativado não sai na exportação. Ative em Grupos de preço antes de exportar.</li>
            </ol>
            <Inline gap={2} wrap>
              {grupos.map((g) => <span key={g.id} className="rounded-full border border-border px-2 py-0.5 text-xs">{g.nome}</span>)}
              <Button asChild variant="outline" size="sm"><a href="/selling-price-group">Gerenciar grupos</a></Button>
              <Button asChild variant="outline" size="sm"><Link href="/products/unificado">Editar preço na tela</Link></Button>
            </Inline>
          </Stack>
        </CardContent>
      </Card>
    </Stack>
  );
}

function ResultadoConferencia({ conf }: { conf: Conferencia }) {
  if (!conf.ok) return <p role="alert" className="text-sm text-destructive">{conf.msg ?? 'A planilha foi recusada.'} Nada foi alterado.</p>;
  return (
    <Stack gap={2}>
      {conf.alertas.length > 0 && (
        <div role="alert" className="rounded-md border border-destructive p-3 text-sm text-destructive">
          {conf.alertas.length} SKU(s) também existem fora deste negócio ({conf.alertas.map((a) => a.sku).join(', ')}).
          Aplicar está bloqueado: troque esses SKUs antes.
        </div>
      )}
      {conf.linhas.length === 0 ? (
        <p className="text-sm text-muted-foreground">A planilha não muda nenhum preço.</p>
      ) : (
        <table className="w-full text-sm">
          <thead className="text-left text-xs text-muted-foreground">
            <tr>{['SKU', 'Produto', 'Preço', 'Antes', 'Depois'].map((c) => <th key={c} className="border-b border-border py-2 pr-3 font-medium">{c}</th>)}</tr>
          </thead>
          <tbody>
            {conf.linhas.map((l) => (
              <tr key={`${l.sku}-${l.campo}`} className="border-b border-border">
                <td className="py-2 pr-3 font-mono text-xs">{l.sku}</td>
                <td className="py-2 pr-3">{l.produto}</td>
                <td className="py-2 pr-3">{l.campo}</td>
                <td className="py-2 pr-3 tabular-nums">{l.antes ?? '—'}</td>
                <td className="py-2 pr-3 font-medium tabular-nums">{l.depois ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Stack>
  );
}

AtualizarPrecoIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
