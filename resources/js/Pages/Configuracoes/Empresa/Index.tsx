// @memcofre
//   tela: /business/settings
//   module: Configuracoes
//   stories: thread sistema/playbook/08 (F3-1: Negócio · F3-2: Impostos, Produtos, Contatos · F3-3a: Vendas, Pagamento)
//   permissao: business_settings.access
//
// Configuração da empresa. Atrás da flag `useV2ConfiguracoesEmpresa` (BusinessController::FLAG_EMPRESA_V2);
// desligada, a rota segue na Blade `business/settings`. `?classico=1` abre a Blade mesmo com a flag ligada.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-empresa.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-page.jsx → Empresa()
//
// O salvar (POST /business/update) é substituição total: o que não vem no corpo é zerado. Por isso a tela manda
// o `corpo` inteiro que o servidor montou e só troca os campos das abas que desenha. E-mail e SMS não vêm no corpo
// (guardam senha) e o salvar os preserva.
//
// Produtos: o protótipo mostra só SKU e validade; a Blade tem também marca, categoria, prateleiras, subunidades,
// garantia, 2ª unidade e imagem obrigatória. Ficam todos — tirar seria perder o que o negócio já usa.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Checkbox } from '@/Components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageHeader } from '@/Components/PageHeader';
import { Grid, Inline, Stack } from '@/Components/layout';
import ConfiguracoesSubNav from '@/Pages/Configuracoes/_shared/ConfiguracoesSubNav';

type Valor = string | number | boolean | null | Valor[] | { [k: string]: Valor };
type Corpo = Record<string, Valor>;
type Lista = Record<string, string>;
interface Opcoes { moedas: Lista; fusos: Lista; meses: Lista; metodos: Lista; formatos_data: Lista; unidades: Lista; impostos: Lista; comissao: Lista; pagamentos: Lista }
interface Props { corpo: Corpo; logo: string | null; segredos_gravados: string[]; opcoes?: Opcoes }

/** Abas na ordem e nos grupos do protótipo. As de PRONTAS estão na tela nova; as outras abrem na Blade. */
const ABAS: { g: string; itens: { id: string; label: string }[] }[] = [
  { g: 'Empresa', itens: [{ id: 'negocio', label: 'Negócio' }, { id: 'imposto', label: 'Impostos' }, { id: 'produto', label: 'Produtos' }, { id: 'contato', label: 'Contatos' }] },
  { g: 'Operação', itens: [{ id: 'venda', label: 'Vendas' }, { id: 'pdv', label: 'Venda no PDV' }, { id: 'compra', label: 'Compras' }, { id: 'pagto', label: 'Pagamento' }] },
  { g: 'Sistema', itens: [{ id: 'painel', label: 'Painel' }, { id: 'sistema', label: 'Sistema' }, { id: 'prefixos', label: 'Prefixos' }] },
  { g: 'Integrações', itens: [{ id: 'email', label: 'E-mail' }, { id: 'sms', label: 'SMS' }, { id: 'fidelidade', label: 'Pontos de fidelidade' },
    { id: 'modulos', label: 'Módulos' }, { id: 'rotulos', label: 'Rótulos personalizados' }] },
];
const PRONTAS: Record<string, string> = {
  negocio: 'O que identifica a empresa e como o sistema conta dinheiro, data e quantidade.',
  imposto: 'Dois números fiscais aparecem no cabeçalho de todo documento.',
  produto: 'Como o sistema gera código, trata validade e o que o cadastro de produto mostra.',
  contato: 'Vale para cliente novo que não tem limite próprio.',
  venda: 'Padrões de desconto, imposto e comissão de toda venda.',
  pagto: 'Contagem de cédulas no recebimento em dinheiro.',
};

const PRECISOES: Lista = { 0: '0', 1: '1', 2: '2', 3: '3', 4: '4' };
const SIMBOLO: Lista = { before: 'Antes do valor', after: 'Depois do valor' };
const HORA: Lista = { 24: '24 horas', 12: '12 horas' };
const VALIDADE: Lista = { add_expiry: 'Informar a validade', add_manufacturing: 'Informar a fabricação (validade calculada)' };
const AO_VENCER: Lista = { keep_selling: 'Manter vendendo', stop_selling: 'Parar de vender' };
const NENHUM = '__nenhum__';
const PRECO_IMPOSTO: Lista = { includes: 'Inclui o imposto', excludes: 'Sem o imposto (calculado sobre o preço)' };
const ADICAO: Lista = { 0: 'Adiciona linha nova', 1: 'Aumenta a quantidade' };
const ARREDONDA: Lista = { 1: 'Para o inteiro mais próximo', '0.05': 'Para 0,05', '0.1': 'Para 0,10', '0.5': 'Para 0,50' };
const CALC_COMISSAO: Lista = { invoice_value: 'Sobre o valor da fatura', payment_received: 'Sobre o pagamento recebido' };
const CEDULAS_EM: Lista = { pos_screen: 'Só no PDV', all_screens: 'Em todas as telas' };

function EmpresaIndex({ corpo: inicial, logo, segredos_gravados, opcoes }: Props) {
  const [corpo, setCorpo] = useState<Corpo>(inicial);
  const [aba, setAba] = useState('negocio');
  const [arquivo, setArquivo] = useState<File | null>(null);
  const [sujo, setSujo] = useState(false);
  const [salvando, setSalvando] = useState(false);

  const texto = (k: string) => (corpo[k] === null || corpo[k] === undefined ? '' : String(corpo[k]));
  const muda = (k: string, v: Valor) => { setCorpo((c) => ({ ...c, [k]: v })); setSujo(true); };
  // `common_settings` guarda chaves soltas; checkbox desmarcado some do objeto, como na Blade.
  const comum = (corpo.common_settings && !Array.isArray(corpo.common_settings) ? corpo.common_settings : {}) as Record<string, Valor>;
  const mudaComum = (k: string, v: Valor) => {
    const { [k]: _fora, ...resto } = comum;
    muda('common_settings', v === null || v === '' ? resto : { ...resto, [k]: v });
  };

  // `pos_settings`: igual à Blade, desmarcar ou esvaziar REMOVE a chave (o salvar substitui o objeto inteiro).
  const pos = (corpo.pos_settings && !Array.isArray(corpo.pos_settings) ? corpo.pos_settings : {}) as Record<string, Valor>;
  const mudaPos = (k: string, v: Valor) => {
    const { [k]: _fora, ...resto } = pos;
    muda('pos_settings', v === null || v === '' || (Array.isArray(v) && v.length === 0) ? resto : { ...resto, [k]: v });
  };
  const textoPos = (k: string) => (pos[k] === null || pos[k] === undefined ? '' : String(pos[k]));

  const salvar = () => {
    setSalvando(true);
    router.post('/business/update', arquivo ? { ...corpo, business_logo: arquivo } : corpo, {
      forceFormData: true, preserveScroll: true,
      onSuccess: () => { setSujo(false); setArquivo(null); },
      onFinish: () => setSalvando(false),
    });
  };

  const rotulado = (id: string, rotulo: string, controle: ReactNode, ajuda?: string) => (
    <Stack gap={1}>
      <label htmlFor={id}>{rotulo}</label>
      {controle}
      {ajuda && <span className="text-xs text-muted-foreground">{ajuda}</span>}
    </Stack>
  );
  const campo = (k: string, rotulo: string, extra?: { ajuda?: string; tipo?: string }) =>
    rotulado(`emp-${k}`, rotulo, <Input id={`emp-${k}`} type={extra?.tipo ?? 'text'} value={texto(k)} onChange={(e) => muda(k, e.target.value)} />, extra?.ajuda);
  const escolha = (k: string, rotulo: string, lista: Lista | undefined, vazio?: string) =>
    rotulado(`emp-${k}`, rotulo, (
      <Select value={texto(k) || (vazio ? NENHUM : '')} onValueChange={(v) => muda(k, v === NENHUM ? '' : v)} disabled={!lista}>
        <SelectTrigger id={`emp-${k}`}><SelectValue placeholder={lista ? 'Escolha…' : 'Carregando…'} /></SelectTrigger>
        <SelectContent>
          {vazio && <SelectItem value={NENHUM}>{vazio}</SelectItem>}
          {Object.entries(lista ?? {}).filter(([v]) => v !== '').map(([v, l]) => <SelectItem key={v} value={v}>{l}</SelectItem>)}
        </SelectContent>
      </Select>
    ));
  const liga = (id: string, rotulo: string, marcado: boolean, onMuda: (v: boolean) => void, ajuda?: string) => (
    <Inline key={id} gap={2}>
      <Checkbox id={id} checked={marcado} onCheckedChange={(v) => onMuda(v === true)} />
      <label htmlFor={id}>{rotulo}{ajuda && <span className="text-muted-foreground"> — {ajuda}</span>}</label>
    </Inline>
  );
  const ligaCampo = (k: string, rotulo: string, ajuda?: string) => liga(`emp-${k}`, rotulo, !!corpo[k], (v) => muda(k, v), ajuda);
  const ligaComum = (k: string, rotulo: string, ajuda?: string) => liga(`emp-cs-${k}`, rotulo, !!comum[k], (v) => mudaComum(k, v ? '1' : null), ajuda);
  const ligaPos = (k: string, rotulo: string, ajuda?: string) => liga(`emp-pos-${k}`, rotulo, !!pos[k], (v) => mudaPos(k, v ? '1' : null), ajuda);
  const campoPos = (k: string, rotulo: string, ajuda?: string) =>
    rotulado(`emp-pos-${k}`, rotulo, <Input id={`emp-pos-${k}`} value={textoPos(k)} onChange={(e) => mudaPos(k, e.target.value)} />, ajuda);
  const escolhaPos = (k: string, rotulo: string, lista: Lista, vazio?: string) =>
    rotulado(`emp-pos-${k}`, rotulo, (
      <Select value={textoPos(k) || (vazio ? NENHUM : '')} onValueChange={(v) => mudaPos(k, v === NENHUM ? null : v)}>
        <SelectTrigger id={`emp-pos-${k}`}><SelectValue placeholder="Escolha…" /></SelectTrigger>
        <SelectContent>
          {vazio && <SelectItem value={NENHUM}>{vazio}</SelectItem>}
          {Object.entries(lista).map(([v, l]) => <SelectItem key={v} value={v}>{l}</SelectItem>)}
        </SelectContent>
      </Select>
    ));
  // Segredo: a tela não recebe o valor. Em branco = mantém o gravado (a chave nem viaja); digitar troca.
  const segredo = (k: string, rotulo: string) =>
    rotulado(`emp-pos-${k}`, rotulo, (
      <Input id={`emp-pos-${k}`} type="password" autoComplete="off" value={textoPos(k)} onChange={(e) => mudaPos(k, e.target.value)}
        placeholder={segredos_gravados.includes(k) ? 'Gravada — digite para trocar' : 'Não informada'} />
    ), 'Para apagar uma chave gravada, use a tela antiga.');
  const metodosCedula = Array.isArray(pos.enable_cash_denomination_for_payment_methods) ? (pos.enable_cash_denomination_for_payment_methods as string[]) : [];
  const titulo = (t: string) => <h3 className="m-0 text-xs font-semibold uppercase text-muted-foreground">{t}</h3>;
  const rotuloAba = ABAS.flatMap((g) => g.itens).find((i) => i.id === aba)?.label;

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Configuração da empresa" subtitle={texto('name')} subnav={<ConfiguracoesSubNav />}
          actions={<Button data-contract="salvar" disabled={!sujo || salvando} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>} />
      </div>

      <Grid gap={6} className="px-6 pt-4 md:grid-cols-[13rem_1fr]">
        <nav data-contract="abas" aria-label="Abas da configuração">
          <Stack gap={3}>
            {ABAS.map((g) => (
              <Stack key={g.g} gap={1}>
                <span className="text-xs font-semibold uppercase text-muted-foreground">{g.g}</span>
                {g.itens.map((i) => i.id in PRONTAS ? (
                  <button key={i.id} type="button" data-contract={`aba-${i.id}`} aria-current={aba === i.id ? 'page' : undefined} onClick={() => setAba(i.id)}
                    className={`rounded-md px-2 py-1 text-left text-sm ${aba === i.id ? 'bg-muted font-medium' : 'text-muted-foreground hover:bg-muted'}`}>{i.label}</button>
                ) : (
                  <a key={i.id} data-contract={`aba-${i.id}`} href="/business/settings?classico=1" title="Esta aba ainda abre na tela antiga"
                    className="rounded-md px-2 py-1 text-sm text-muted-foreground hover:bg-muted">{i.label}</a>
                ))}
              </Stack>
            ))}
          </Stack>
        </nav>

        <section aria-labelledby="emp-titulo">
          <Stack gap={1} className="mb-4">
            <h2 id="emp-titulo" className="m-0 text-lg font-semibold">{rotuloAba}</h2>
            <p className="m-0 text-sm text-muted-foreground">{PRONTAS[aba]}</p>
          </Stack>
          <Deferred data="opcoes" fallback={<p className="text-sm text-muted-foreground">Carregando opções…</p>}>
            <Stack gap={5} className="max-w-3xl text-sm">
              {aba === 'negocio' && (
                <>
                  <Grid cols={2} gap={3}>
                    {campo('name', 'Nome do negócio *')}
                    {campo('start_date', 'Data de início', { ajuda: `No formato ${texto('date_format') || 'do negócio'}.` })}
                    {campo('default_profit_percent', 'Margem de lucro padrão (%)', { ajuda: 'Usada para sugerir o preço de venda a partir do custo.' })}
                    {escolha('currency_id', 'Moeda', opcoes?.moedas)}
                    {escolha('currency_symbol_placement', 'Posição do símbolo', SIMBOLO)}
                    {escolha('time_zone', 'Fuso horário', opcoes?.fusos)}
                    {escolha('fy_start_month', 'Mês que abre o exercício', opcoes?.meses)}
                    {escolha('accounting_method', 'Método de custo', opcoes?.metodos)}
                    {campo('transaction_edit_days', 'Dias para editar uma transação', { tipo: 'number', ajuda: 'Depois disso, a venda ou compra só muda com estorno.' })}
                    {escolha('date_format', 'Formato de data', opcoes?.formatos_data)}
                    {escolha('time_format', 'Formato de hora', HORA)}
                    {escolha('currency_precision', 'Casas decimais do valor', PRECISOES)}
                    {escolha('quantity_precision', 'Casas decimais da quantidade', PRECISOES)}
                  </Grid>
                  <Stack gap={2}>
                    {titulo('Logo do negócio')}
                    <Inline gap={3}>
                      {logo && <img src={logo} alt="Logo atual" className="h-12 w-auto rounded-md border" />}
                      <Input type="file" accept="image/*" aria-label="Enviar logo" className="max-w-xs"
                        onChange={(e) => { setArquivo(e.target.files?.[0] ?? null); setSujo(true); }} />
                    </Inline>
                  </Stack>
                  <Stack gap={2}>
                    {titulo('Códigos da empresa na nota')}
                    <Grid cols={2} gap={3}>
                      {campo('code_label_1', 'Nome do código 1')}
                      {campo('code_1', 'Código 1')}
                      {campo('code_label_2', 'Nome do código 2')}
                      {campo('code_2', 'Código 2')}
                    </Grid>
                  </Stack>
                  {ligaComum('is_enabled_export', 'Permitir exportar listas', 'botões de Excel, CSV e PDF nas tabelas.')}
                </>
              )}

              {aba === 'imposto' && (
                <>
                  <Grid cols={2} gap={3}>
                    {campo('tax_label_1', 'Nome do imposto 1')}
                    {campo('tax_number_1', 'Número do imposto 1')}
                    {campo('tax_label_2', 'Nome do imposto 2')}
                    {campo('tax_number_2', 'Número do imposto 2')}
                  </Grid>
                  {ligaCampo('enable_inline_tax', 'Imposto por item', 'escolher o imposto em cada linha da compra e da venda.')}
                  <p className="m-0 text-xs text-muted-foreground">Alíquota, CFOP, NCM e CST ficam em NF-e Brasil — aqui só os números que aparecem impressos.</p>
                </>
              )}

              {aba === 'produto' && (
                <>
                  <Grid cols={2} gap={3}>
                    {campo('sku_prefix', 'Prefixo do SKU', { ajuda: 'Entra antes do número quando o SKU é automático.' })}
                    {escolha('default_unit', 'Unidade padrão', opcoes?.unidades, 'Nenhuma')}
                  </Grid>
                  {ligaCampo('enable_product_expiry', 'Controlar validade do produto', 'data de validade na compra e no estoque.')}
                  {!!corpo.enable_product_expiry && (
                    <Grid cols={2} gap={3}>
                      {escolha('expiry_type', 'Como informar', VALIDADE)}
                      {escolha('on_product_expiry', 'Ao vencer', AO_VENCER)}
                      {texto('on_product_expiry') === 'stop_selling' && campo('stop_selling_before', 'Parar quantos dias antes', { tipo: 'number' })}
                    </Grid>
                  )}
                  <Stack gap={2}>
                    {titulo('O que o cadastro de produto mostra')}
                    {ligaCampo('enable_brand', 'Marca')}
                    {ligaCampo('enable_category', 'Categoria')}
                    {ligaCampo('enable_sub_category', 'Subcategoria')}
                    {ligaCampo('enable_price_tax', 'Preço com imposto')}
                    {ligaCampo('enable_sub_units', 'Subunidades')}
                    {ligaComum('enable_secondary_unit', 'Segunda unidade')}
                    {ligaCampo('enable_racks', 'Prateleira')}
                    {ligaCampo('enable_row', 'Linha da prateleira')}
                    {ligaCampo('enable_position', 'Posição na prateleira')}
                    {ligaComum('enable_product_warranty', 'Garantia')}
                    {ligaComum('is_product_image_required', 'Imagem obrigatória')}
                  </Stack>
                </>
              )}

              {aba === 'venda' && (
                <>
                  <Grid cols={2} gap={3}>
                    {campo('default_sales_discount', 'Desconto padrão (%)')}
                    {escolha('default_sales_tax', 'Imposto padrão da venda', opcoes?.impostos, 'Nenhum')}
                    {escolha('sell_price_tax', 'Preço de venda', PRECO_IMPOSTO)}
                    {escolha('item_addition_method', 'Ao repetir um item', ADICAO)}
                    {escolhaPos('amount_rounding_method', 'Arredondamento do total', ARREDONDA, 'Não arredondar')}
                  </Grid>
                  <Stack gap={2}>
                    {ligaPos('enable_msp', 'Preço de venda é o mínimo', 'não deixa vender abaixo dele.')}
                    {ligaPos('allow_overselling', 'Permitir vender sem estoque')}
                    {ligaPos('bloquear_venda_preco_zero_app', 'Bloquear venda com preço zero no app')}
                    {ligaPos('enable_sales_order', 'Pedido de venda')}
                    {ligaPos('is_pay_term_required', 'Prazo de pagamento obrigatório')}
                  </Stack>
                  <Stack gap={2}>
                    {titulo('Comissão')}
                    <Grid cols={2} gap={3}>
                      {escolha('sales_cmsn_agnt', 'Quem recebe comissão', opcoes?.comissao, 'Desativado')}
                      {escolhaPos('cmmsn_calculation_type', 'Calcular sobre', CALC_COMISSAO)}
                    </Grid>
                    {ligaPos('is_commission_agent_required', 'Comissionado obrigatório na venda')}
                  </Stack>
                  <Stack gap={2}>
                    {titulo('Link de pagamento')}
                    {ligaPos('enable_payment_link', 'Enviar link de pagamento')}
                    <Grid cols={2} gap={3}>
                      {campoPos('razor_pay_key_id', 'Razorpay · Key ID')}
                      {segredo('razor_pay_key_secret', 'Razorpay · Key Secret')}
                      {campoPos('stripe_public_key', 'Stripe · chave pública')}
                      {segredo('stripe_secret_key', 'Stripe · chave secreta')}
                    </Grid>
                  </Stack>
                </>
              )}

              {aba === 'pagto' && (
                <>
                  <Grid cols={2} gap={3}>
                    {campoPos('cash_denominations', 'Cédulas e moedas', 'Separadas por vírgula, ex.: 200,100,50,20,10,5,2.')}
                    {escolhaPos('enable_cash_denomination_on', 'Contar cédulas', CEDULAS_EM)}
                  </Grid>
                  <Stack gap={2}>
                    {titulo('Formas de pagamento com contagem')}
                    {Object.entries(opcoes?.pagamentos ?? {}).map(([k, l]) => liga(`emp-cedula-${k}`, l, metodosCedula.includes(k),
                      (v) => mudaPos('enable_cash_denomination_for_payment_methods', v ? [...metodosCedula, k] : metodosCedula.filter((x) => x !== k))))}
                  </Stack>
                  {ligaPos('cash_denomination_strict_check', 'Conferência estrita', 'o total contado tem de bater com o recebido.')}
                </>
              )}

              {aba === 'contato' && (
                <Grid cols={2} gap={3}>
                  {rotulado('emp-cs-default_credit_limit', 'Limite de crédito padrão', (
                    <Input id="emp-cs-default_credit_limit" value={comum.default_credit_limit == null ? '' : String(comum.default_credit_limit)}
                      onChange={(e) => mudaComum('default_credit_limit', e.target.value)} />
                  ), 'Sugerido no cadastro de cliente novo. Em branco = sem sugestão.')}
                </Grid>
              )}
            </Stack>
          </Deferred>
        </section>
      </Grid>
    </div>
  );
}

EmpresaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default EmpresaIndex;
