// @memcofre
//   tela: /import-products (Importação · modo produtos)
//   module: Produto
//   stories: playbook Produto thread 05 (ImportProductsController@index · Blade → Inertia)
//   permissao: product.create
//
// Importação de produtos. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/produto-acoes.jsx → TelaImportar/ImportarProdutos
// Contrato: governance/design/contracts/produto-importacao.contract.json
//
// A tela NÃO calcula nem grava nada: "Conferir planilha" manda `conferir=1` pro mesmo store(),
// que valida, desfaz e devolve o que criaria; "Enviar planilha" é o store() de sempre e só libera
// depois de uma conferência sem erro do mesmo arquivo (regra mestre VALOR/ESTOQUE).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useRef, useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { PageHeader } from '@/Components/PageHeader';
import { Inline, Stack } from '@/Components/layout';

interface Msg { success?: number | boolean; msg?: string }
interface LinhaConferida {
  linha: number; nome: string; sku: string; tipo: string;
  estoque: string | number | null; custo: string | number | null; preco: string | number | null;
}
interface Props {
  modo: 'produtos';
  zip: boolean;
  aviso?: Msg | null;
  resultado?: Msg | null;
  conferencia?: LinhaConferida[] | null;
  modelo: string;
}

const REQ = 'obrigatório', OPC = 'opcional', UM = 'um dos dois é obrigatório';
// Copy de produto-acoes.jsx (COLS_PRODUTO). 3 textos corrigidos pelo que o store() faz — ver _saida-05.
const COLUNAS: [string, string, string][] = [
  ['Nome do produto', REQ, 'Nome como aparece no balcão e na nota.'],
  ['Marca', OPC, 'Se a marca não existir, é criada na hora.'],
  ['Unidade', REQ, 'Precisa já existir em Cadastros de apoio → Unidades.'],
  ['Categoria', OPC, 'Se não existir, é criada.'],
  ['Subcategoria', OPC, 'Só entra se a categoria pai estiver preenchida.'],
  ['SKU', OPC, 'Em branco, o sistema gera.'],
  ['Tipo de código de barras', OPC + ', padrão C128', 'C128, C39, EAN-13, EAN-8, UPC-A, UPC-E, ITF-14.'],
  ['Gerenciar estoque', REQ, '1 = sim · 0 = não.'],
  ['Quantidade de alerta', OPC, 'Abaixo disso o produto aparece em Análises → Reposição.'],
  ['Vence em', OPC, 'Número de dias ou meses de validade.'],
  ['Unidade do prazo de validade', OPC, 'days ou months.'],
  ['Imposto aplicável', OPC, 'Nome exato do imposto cadastrado (ex.: ICMS 18%).'],
  ['Tipo de imposto na venda', REQ, 'inclusive (preço já com imposto) ou exclusive.'],
  ['Tipo de produto', REQ, 'single ou variable.'],
  ['Nome da variação', 'obrigatório se variable', 'Ex.: Cor, Acabamento.'],
  ['Valores da variação', 'obrigatório se variable', 'Separados por | — ex.: Branco|Preto|Azul.'],
  ['SKU da variação', OPC, 'Um por valor, na mesma ordem, separados por |.'],
  ['Preço de compra com imposto', UM, 'Custo unitário já com imposto.'],
  ['Preço de compra sem imposto', UM, 'Custo unitário antes do imposto.'],
  ['Margem de lucro (%)', OPC, 'Em branco, usa a margem padrão do negócio.'],
  ['Preço de venda', OPC, 'Em branco, é calculado pela margem.'],
  ['Estoque inicial', OPC, 'Só vale se Gerenciar estoque = 1.'],
  ['Local do estoque inicial', OPC, 'Nome do local (Matriz, Filial Centro). Em branco = primeiro local do negócio.'],
  ['Data de validade', OPC, 'mm-dd-aaaa (mês-dia-ano).'],
  ['Controla IMEI / nº de série', OPC + ', padrão 0', '1 = sim · 0 = não.'],
  ['Peso', OPC, 'Usado no frete e no romaneio de entrega.'],
  ['Prateleira', OPC, 'Localização física no estoque.'],
  ['Fileira', OPC, 'Localização física no estoque.'],
  ['Posição', OPC, 'Localização física no estoque.'],
  ['Imagem', OPC, 'Nome do arquivo já enviado ou URL pública.'],
  ['Descrição do produto', OPC, 'Texto livre — sai na proposta.'],
  ['Campo personalizado 1', OPC, 'Campo livre do negócio.'],
  ['Campo personalizado 2', OPC, 'Campo livre do negócio.'],
  ['Campo personalizado 3', OPC, 'Campo livre do negócio.'],
  ['Campo personalizado 4', OPC, 'Campo livre do negócio.'],
  ['Não para venda', OPC, '1 = insumo, não aparece no PDV · 0 = vende.'],
  ['Locais do produto', OPC, 'Nomes separados por vírgula — em branco entra em todos.'],
];

const chave = (f: File | null) => (f ? `${f.name}|${f.size}|${f.lastModified}` : '');
const th = 'border-b border-border py-2 pr-3 font-medium';
const td = 'border-b border-border py-2 pr-3';

export default function ImportacaoIndex({ zip, aviso, resultado, conferencia, modelo }: Props) {
  const [arquivo, setArquivo] = useState<File | null>(null);
  const [conferido, setConferido] = useState('');
  const [enviando, setEnviando] = useState(false);
  const ref = useRef<HTMLInputElement>(null);
  const liberado = !!arquivo && conferido === chave(arquivo);

  const enviar = (conferir: boolean) => {
    if (!arquivo) return;
    setEnviando(true);
    router.post('/import-products/store', { products_csv: arquivo, ...(conferir ? { conferir: 1 } : {}) }, {
      forceFormData: true,
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        const p = page.props as unknown as Props;
        if (conferir) setConferido(p.conferencia?.length ? chave(arquivo) : '');
        else if (p.resultado?.success) { setArquivo(null); setConferido(''); }
      },
      onFinish: () => setEnviando(false),
    });
  };

  const erro = aviso && !aviso.success ? aviso.msg : null;

  return (
    <Stack gap={4}>
      <div data-contract="produto-importacao-header">
        <PageHeader title="Importar produtos" subtitle=".xls, .xlsx, .csv" />
      </div>

      <Card data-contract="produto-importacao-enviar">
        <CardContent className="p-4">
          <Stack gap={3}>
            <h2 className="text-sm font-medium">Importar produtos</h2>
            <Alert>
              <AlertTitle>A planilha grava direto no catálogo</AlertTitle>
              <AlertDescription>
                Confira antes de enviar: uma coluna fora de ordem cria produto errado em lote. Erro em qualquer
                linha cancela a importação inteira — nada entra pela metade.
              </AlertDescription>
            </Alert>
            {!zip && <p role="alert" className="text-sm text-destructive">O servidor está sem a extensão PHP Zip — a importação não roda até o administrador habilitar.</p>}
            <Inline wrap gap={2} align="end">
              <Button type="button" variant="outline" onClick={() => ref.current?.click()}>Escolher arquivo</Button>
              <input ref={ref} type="file" accept=".xls,.xlsx,.csv" className="hidden"
                onChange={(e) => { setArquivo(e.target.files?.[0] ?? null); setConferido(''); }} />
              <Stack gap={1}>
                <label htmlFor="pimp-arquivo" className="text-sm">Arquivo para importar *</label>
                <Input id="pimp-arquivo" readOnly value={arquivo?.name ?? 'Nenhum arquivo escolhido'} className="min-w-64" />
              </Stack>
              <Button type="button" variant="outline" disabled={!arquivo || enviando || !zip} onClick={() => enviar(true)}>Conferir planilha</Button>
              <Button type="button" disabled={!liberado || enviando || !zip} onClick={() => enviar(false)}
                title={liberado ? undefined : 'Confira a planilha antes — sem erro, o envio libera.'}>Enviar planilha</Button>
              <Button asChild variant="outline"><a href={modelo} download>Baixar modelo</a></Button>
            </Inline>

            {resultado?.success ? <p role="status" className="text-sm">{resultado.msg}</p> : null}
            {erro && (
              <Alert variant="destructive">
                <AlertTitle>O servidor recusou a planilha — nada foi gravado</AlertTitle>
                <AlertDescription>{erro}. A linha 1 é a primeira depois do cabeçalho.</AlertDescription>
              </Alert>
            )}
            {liberado && conferencia?.length ? (
              <Stack gap={2}>
                <p className="text-sm">
                  <b>{conferencia.length} produto(s) seriam criados.</b> Nada foi gravado ainda — confira e envie.
                </p>
                <table className="w-full text-sm">
                  <thead className="text-left text-xs text-muted-foreground">
                    <tr>{['Linha', 'Nome', 'SKU', 'Tipo', 'Estoque inicial', 'Custo c/ imposto', 'Preço c/ imposto'].map((c) => <th key={c} className={th}>{c}</th>)}</tr>
                  </thead>
                  <tbody>
                    {conferencia.map((l) => (
                      <tr key={l.linha}>
                        <td className={`${td} tabular-nums`}>{l.linha}</td>
                        <td className={td}>{l.nome}</td>
                        <td className={`${td} font-mono text-xs`}>{l.sku || 'gerado'}</td>
                        <td className={td}>{l.tipo}</td>
                        <td className={`${td} tabular-nums`}>{l.estoque ?? '—'}</td>
                        <td className={`${td} tabular-nums`}>{l.custo ?? '—'}</td>
                        <td className={`${td} tabular-nums`}>{l.preco ?? '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </Stack>
            ) : null}
          </Stack>
        </CardContent>
      </Card>

      <Card data-contract="produto-importacao-instrucoes">
        <CardContent className="p-4">
          <Stack gap={2}>
            <h2 className="text-sm font-medium">Instruções</h2>
            <p className="text-sm text-muted-foreground">
              Uma linha por produto. A primeira linha da planilha é cabeçalho e é ignorada. Mantenha a ordem
              das {COLUNAS.length} colunas do modelo.
            </p>
            <table className="w-full text-sm">
              <thead className="text-left text-xs text-muted-foreground">
                <tr><th className={`${th} text-right`}>Nº</th><th className={th}>Coluna</th><th className={th}>O que colocar</th></tr>
              </thead>
              <tbody>
                {COLUNAS.map(([col, req, ins], i) => (
                  <tr key={col}>
                    <td className={`${td} text-right font-mono tabular-nums`}>{i + 1}</td>
                    <td className={td}>{col} <span className="text-xs text-muted-foreground">({req})</span></td>
                    <td className={td}>{ins}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Stack>
        </CardContent>
      </Card>
    </Stack>
  );
}

ImportacaoIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
