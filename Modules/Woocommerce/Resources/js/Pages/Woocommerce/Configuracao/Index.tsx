// @memcofre
//   tela: /woocommerce/api-settings
//   module: Woocommerce
//   stories: thread woocommerce/playbook/02 (Configuração da API)
//   permissao: woocommerce.access_woocommerce_api_settings
//
// Configuração da loja WooCommerce. Atrás da flag `useV2WoocommercePainel` (a mesma do painel); desligada, ou com
// `?classico=1`, a rota segue na Blade `woocommerce::woocommerce.api_settings`.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Woocommerce/RUNBOOK-configuracao.md
// Âncora de design: governance/design/targets/woocommerce--api--index.alvo.json (A1) ← integra-extras.jsx WooCommercePage.
//
// Tier 0: a página não recebe nenhum dos 6 segredos (consumer key/secret, 4 de webhook) — só se cada um está
// preenchido. Campo de segredo vazio no salvar mantém o salvo (`manter_segredos`, WoocommerceController::updateSettings).

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode } from 'react';
import { KeyRound, Lock, Package, Save, ShoppingCart, Store, Webhook } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Switch } from '@/Components/ui/switch';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { FormGrid, FormSection } from '@/Components/ui/form-section';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { PageHeader } from '@/Components/PageHeader';
import { Stack } from '@/Components/layout';
import WoocommerceAbas from '../_components/WoocommerceAbas';

type Segredo = 'woocommerce_consumer_key' | 'woocommerce_consumer_secret'
  | 'woocommerce_wh_oc_secret' | 'woocommerce_wh_ou_secret' | 'woocommerce_wh_od_secret' | 'woocommerce_wh_or_secret';
type TriEstado = 'true' | 'false' | 'none';
interface Config {
  woocommerce_app_url: string;
  location_id: string | number | null;
  enable_auto_sync: boolean;
  default_tax_class: string;
  product_tax_type: 'inc' | 'exc';
  default_selling_price_group: string | number;
  sync_description_as: 'short' | 'long' | 'both';
  product_fields_for_create: string[];
  product_fields_for_update: string[];
  manage_stock_for_create: TriEstado;
  in_stock_for_create: TriEstado;
  manage_stock_for_update: TriEstado;
  in_stock_for_update: TriEstado;
  order_statuses: Record<string, string>;
  shipping_statuses: Record<string, string>;
}
interface Opcao { id: string; nome: string }
interface Props {
  config: Config;
  segredos: Record<Segredo, boolean>;
  demonstracao: boolean;
  opcoes: {
    locais: Opcao[];
    grupos_preco: Opcao[];
    status_loja: Record<string, string>;
    status_venda: Record<string, string>;
    status_envio: Record<string, string>;
    webhooks: Record<'oc' | 'ou' | 'od' | 'or', string>;
    cron: string | null;
    versao_modulo: string | null;
  };
}

// Radix Select não aceita value="" — sentinela no lugar do vazio, mapeada de volta no estado.
const VAZIO = '__vazio__';
const deVazio = (v: string) => (v === VAZIO ? '' : v);
const paraVazio = (v: string | number | null | undefined) => (v === null || v === undefined || v === '' ? VAZIO : String(v));

const CAMPOS_PRODUTO: { valor: string; rotulo: string; fixo?: boolean }[] = [
  { valor: 'name', rotulo: 'Nome', fixo: true },
  { valor: 'price', rotulo: 'Preço', fixo: true },
  { valor: 'category', rotulo: 'Categoria' },
  { valor: 'quantity', rotulo: 'Quantidade' },
  { valor: 'weight', rotulo: 'Peso' },
  { valor: 'image', rotulo: 'Imagens' },
  { valor: 'description', rotulo: 'Descrição' },
];
const TRI: { valor: TriEstado; rotulo: string }[] = [
  { valor: 'true', rotulo: 'Sim' },
  { valor: 'false', rotulo: 'Não' },
  { valor: 'none', rotulo: 'Não atualizar' },
];
const WEBHOOKS: { sigla: 'oc' | 'ou' | 'od' | 'or'; campo: Segredo; rotulo: string }[] = [
  { sigla: 'oc', campo: 'woocommerce_wh_oc_secret', rotulo: 'Pedido criado' },
  { sigla: 'ou', campo: 'woocommerce_wh_ou_secret', rotulo: 'Pedido atualizado' },
  { sigla: 'od', campo: 'woocommerce_wh_od_secret', rotulo: 'Pedido excluído' },
  { sigla: 'or', campo: 'woocommerce_wh_or_secret', rotulo: 'Pedido restaurado' },
];

function Campo({ rotulo, htmlFor, ajuda, children, inteiro }: { rotulo: string; htmlFor?: string; ajuda?: ReactNode; children: ReactNode; inteiro?: boolean }) {
  return (
    <div className={inteiro ? 'cw-field full-row' : 'cw-field'}>
      <Label htmlFor={htmlFor}>{rotulo}</Label>
      {children}
      {ajuda && <p className="text-xs text-muted-foreground">{ajuda}</p>}
    </div>
  );
}

function Escolha({ id, valor, aoMudar, opcoes, vazio }: { id: string; valor: string; aoMudar: (v: string) => void; opcoes: Opcao[]; vazio?: string }) {
  return (
    <Select value={valor} onValueChange={aoMudar}>
      <SelectTrigger id={id} className="cw-input"><SelectValue /></SelectTrigger>
      <SelectContent>
        {vazio && <SelectItem value={VAZIO}>{vazio}</SelectItem>}
        {opcoes.map((o) => <SafeSelectItem key={o.id} value={o.id}>{o.nome}</SafeSelectItem>)}
      </SelectContent>
    </Select>
  );
}

function CampoSegredo({ id, rotulo, preenchido, valor, aoMudar }: { id: Segredo; rotulo: string; preenchido: boolean; valor: string; aoMudar: (v: string) => void }) {
  return (
    <Campo rotulo={rotulo} htmlFor={id}
      ajuda={preenchido ? 'Já configurado. Deixe vazio para manter; escreva para trocar.' : 'Não configurado.'}>
      <Input id={id} type="password" autoComplete="new-password" variant="cowork" value={valor}
        onChange={(e) => aoMudar(e.target.value)} placeholder={preenchido ? '•••••••• (guardado no servidor)' : ''}
        data-segredo={preenchido ? 'preenchido' : 'vazio'} />
    </Campo>
  );
}

const lista = (r: Record<string, string>): Opcao[] => Object.entries(r).map(([id, nome]) => ({ id, nome }));

function ConfiguracaoIndex({ config, segredos, demonstracao, opcoes }: Props) {
  const segredosVazios = {
    woocommerce_consumer_key: '', woocommerce_consumer_secret: '',
    woocommerce_wh_oc_secret: '', woocommerce_wh_ou_secret: '', woocommerce_wh_od_secret: '', woocommerce_wh_or_secret: '',
  } satisfies Record<Segredo, string>;
  const { data, setData, post, processing, transform, reset } = useForm({
    ...config,
    location_id: config.location_id === null || config.location_id === '' ? (opcoes.locais[0]?.id ?? '') : String(config.location_id),
    default_selling_price_group: String(config.default_selling_price_group ?? ''),
    order_statuses: { ...config.order_statuses },
    shipping_statuses: { ...config.shipping_statuses },
    ...segredosVazios,
    manter_segredos: '1',
  });

  const marcar = (campo: 'product_fields_for_create' | 'product_fields_for_update', valor: string, ligado: boolean) =>
    setData(campo, ligado ? [...data[campo], valor] : data[campo].filter((v) => v !== valor));

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    // Como a Blade: o checkbox desligado não vai no envio (o ServiceProvider lê `!empty(enable_auto_sync)`).
    transform((d) => {
      const { enable_auto_sync, ...resto } = d;
      return enable_auto_sync ? { ...resto, enable_auto_sync: '1' } : resto;
    });
    post('/woocommerce/update-api-settings', { preserveScroll: true, onSuccess: () => reset(...(Object.keys(segredosVazios) as Segredo[])) });
  };

  const estoque = (momento: 'create' | 'update') => !data[`product_fields_for_${momento}`].includes('quantity') && (
    <>
      <Campo rotulo="Gerenciar estoque" htmlFor={`manage_stock_for_${momento}`}>
        <Escolha id={`manage_stock_for_${momento}`} valor={data[`manage_stock_for_${momento}`]}
          aoMudar={(v) => setData(`manage_stock_for_${momento}`, v as TriEstado)} opcoes={TRI.map((t) => ({ id: t.valor, nome: t.rotulo }))} />
      </Campo>
      <Campo rotulo="Em estoque" htmlFor={`in_stock_for_${momento}`}>
        <Escolha id={`in_stock_for_${momento}`} valor={data[`in_stock_for_${momento}`]}
          aoMudar={(v) => setData(`in_stock_for_${momento}`, v as TriEstado)} opcoes={TRI.map((t) => ({ id: t.valor, nome: t.rotulo }))} />
      </Campo>
    </>
  );

  const camposDoProduto = (momento: 'create' | 'update') => {
    const campo = `product_fields_for_${momento}` as const;
    return (
      <Campo rotulo={momento === 'create' ? 'Campos enviados ao criar o produto na loja' : 'Campos enviados ao atualizar o produto na loja'} inteiro>
        <div className="flex flex-wrap gap-x-5 gap-y-2" data-contract={`campos-${momento}`}>
          {CAMPOS_PRODUTO.map((c) => {
            const fixo = momento === 'create' && c.fixo;
            return (
              <label key={c.valor} className="flex items-center gap-2 text-sm">
                <Checkbox checked={fixo || data[campo].includes(c.valor)} disabled={fixo}
                  onCheckedChange={(v) => marcar(campo, c.valor, v === true)} />
                {c.rotulo}
              </label>
            );
          })}
        </div>
      </Campo>
    );
  };

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="WooCommerce" subtitle="configuração da loja"
          subnav={<WoocommerceAbas ativa="/woocommerce/api-settings" podeConfigurar />}
          actions={<Button type="submit" form="woocommerce-configuracao" disabled={processing || demonstracao}><Save className="size-4" /> Salvar</Button>} />
      </div>

      <form id="woocommerce-configuracao" onSubmit={salvar}>
        <Stack gap={4} className="px-6 pt-4">
          {demonstracao && (
            <Alert><AlertTitle>Ambiente de demonstração</AlertTitle><AlertDescription>A configuração da loja não é salva aqui.</AlertDescription></Alert>
          )}

          <Alert data-contract="aviso-segredos">
            <Lock className="size-4" />
            <AlertTitle>Chaves ficam no servidor</AlertTitle>
            <AlertDescription>
              A consumer key, o secret e os segredos dos webhooks não aparecem nesta tela. Ela mostra só se cada um está
              configurado; para trocar, escreva o novo valor.
            </AlertDescription>
          </Alert>

          <FormSection title="Loja" icon={<Store />} data-contract="loja">
            <FormGrid>
              <Campo rotulo="Endereço da loja" htmlFor="woocommerce_app_url" inteiro>
                <Input id="woocommerce_app_url" variant="cowork" value={data.woocommerce_app_url}
                  onChange={(e) => setData('woocommerce_app_url', e.target.value)} placeholder="https://minhaloja.com.br" />
              </Campo>
              <Campo rotulo="Local do estoque enviado" htmlFor="location_id" ajuda="O estoque deste local é o que sobe para a loja.">
                <Escolha id="location_id" valor={paraVazio(data.location_id)} aoMudar={(v) => setData('location_id', deVazio(v))} opcoes={opcoes.locais} />
              </Campo>
              <Campo rotulo="Sincronização automática" htmlFor="enable_auto_sync" ajuda="Precisa do agendador do servidor (instruções no fim da página).">
                <label className="flex items-center gap-2 text-sm">
                  <Switch id="enable_auto_sync" checked={data.enable_auto_sync} onCheckedChange={(v) => setData('enable_auto_sync', v === true)} />
                  {data.enable_auto_sync ? 'Ligada' : 'Desligada'}
                </label>
              </Campo>
            </FormGrid>
          </FormSection>

          <FormSection title="Chaves da API" icon={<KeyRound />} data-contract="chaves">
            <FormGrid>
              <CampoSegredo id="woocommerce_consumer_key" rotulo="Consumer key" preenchido={segredos.woocommerce_consumer_key}
                valor={data.woocommerce_consumer_key} aoMudar={(v) => setData('woocommerce_consumer_key', v)} />
              <CampoSegredo id="woocommerce_consumer_secret" rotulo="Consumer secret" preenchido={segredos.woocommerce_consumer_secret}
                valor={data.woocommerce_consumer_secret} aoMudar={(v) => setData('woocommerce_consumer_secret', v)} />
            </FormGrid>
          </FormSection>

          <FormSection title="Produtos" icon={<Package />} data-contract="produtos">
            <FormGrid>
              <Campo rotulo="Classe de imposto padrão" htmlFor="default_tax_class">
                <Input id="default_tax_class" variant="cowork" value={data.default_tax_class} onChange={(e) => setData('default_tax_class', e.target.value)} />
              </Campo>
              <Campo rotulo="Preço enviado" htmlFor="product_tax_type">
                <Escolha id="product_tax_type" valor={data.product_tax_type} aoMudar={(v) => setData('product_tax_type', v as 'inc' | 'exc')}
                  opcoes={[{ id: 'inc', nome: 'Com imposto' }, { id: 'exc', nome: 'Sem imposto' }]} />
              </Campo>
              <Campo rotulo="Grupo de preço" htmlFor="default_selling_price_group">
                <Escolha id="default_selling_price_group" valor={paraVazio(data.default_selling_price_group)} vazio="Padrão"
                  aoMudar={(v) => setData('default_selling_price_group', deVazio(v))} opcoes={opcoes.grupos_preco} />
              </Campo>
              <Campo rotulo="Descrição enviada" htmlFor="sync_description_as">
                <Escolha id="sync_description_as" valor={data.sync_description_as} aoMudar={(v) => setData('sync_description_as', v as Config['sync_description_as'])}
                  opcoes={[{ id: 'short', nome: 'Descrição curta' }, { id: 'long', nome: 'Descrição longa' }, { id: 'both', nome: 'As duas' }]} />
              </Campo>
              {camposDoProduto('create')}
              {estoque('create')}
              {camposDoProduto('update')}
              {estoque('update')}
            </FormGrid>
          </FormSection>

          <FormSection title="Pedidos" icon={<ShoppingCart />} data-contract="pedidos">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-muted-foreground">
                  <th className="py-2 font-medium">Status na loja</th>
                  <th className="py-2 font-medium">Status da venda</th>
                  <th className="py-2 font-medium">Status de entrega</th>
                </tr>
              </thead>
              <tbody>
                {Object.entries(opcoes.status_loja).map(([chave, rotulo]) => (
                  <tr key={chave} className="border-t">
                    <td className="py-2 pr-3">{rotulo}</td>
                    <td className="py-2 pr-3">
                      <Escolha id={`order_statuses_${chave}`} valor={paraVazio(data.order_statuses[chave])} vazio="Selecione"
                        aoMudar={(v) => setData('order_statuses', { ...data.order_statuses, [chave]: deVazio(v) })} opcoes={lista(opcoes.status_venda)} />
                    </td>
                    <td className="py-2">
                      <Escolha id={`shipping_statuses_${chave}`} valor={paraVazio(data.shipping_statuses[chave])} vazio="Selecione"
                        aoMudar={(v) => setData('shipping_statuses', { ...data.shipping_statuses, [chave]: deVazio(v) })} opcoes={lista(opcoes.status_envio)} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </FormSection>

          <FormSection title="Webhooks de pedido" icon={<Webhook />} data-contract="webhooks">
            <FormGrid>
              {WEBHOOKS.map((w) => (
                <div key={w.sigla} className="full-row grid gap-3 md:grid-cols-2">
                  <CampoSegredo id={w.campo} rotulo={`${w.rotulo} — segredo`} preenchido={segredos[w.campo]}
                    valor={data[w.campo]} aoMudar={(v) => setData(w.campo, v)} />
                  <Campo rotulo="Endereço de entrega (na loja)">
                    <code className="break-all rounded bg-muted px-2 py-1.5 text-xs">{opcoes.webhooks[w.sigla]}</code>
                  </Campo>
                </div>
              ))}
            </FormGrid>
          </FormSection>

          <section data-contract="instrucoes" className="text-sm text-muted-foreground">
            <p>
              As chaves são geradas no painel do WooCommerce (Configurações → Avançado → REST API), com permissão de leitura e
              escrita. Os links permanentes da loja não podem estar no modo "simples".
            </p>
            {opcoes.cron && (
              <p className="mt-2">
                Para a sincronização automática, o servidor precisa rodar o agendador: <code className="break-all">{opcoes.cron}</code>
              </p>
            )}
            {opcoes.versao_modulo && <p className="mt-2 text-xs">Versão do módulo: {opcoes.versao_modulo}</p>}
          </section>
        </Stack>
      </form>
    </div>
  );
}

ConfiguracaoIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ConfiguracaoIndex;
