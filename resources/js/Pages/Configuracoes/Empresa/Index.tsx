// @memcofre
//   tela: /business/settings
//   module: Configuracoes
//   stories: thread sistema/playbook/08 (F3-1: Negócio · F3-2: Impostos, Produtos, Contatos · F3-3a: Vendas, Pagamento · F3-3b: Venda no PDV · F3-3c: Compras · F3-4: Painel, Sistema, Prefixos · F3-5a: E-mail, SMS · F3-5b: Pontos de fidelidade, Módulos)
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
interface Opcoes { modulos: Lista; drivers_email: Lista; temas: Lista; moedas: Lista; fusos: Lista; meses: Lista; metodos: Lista; formatos_data: Lista; unidades: Lista; impostos: Lista; comissao: Lista; pagamentos: Lista }
interface Props { corpo: Corpo; logo: string | null; segredos_gravados: string[]; email_superadmin: boolean; opcoes?: Opcoes }

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
  pdv: 'O que o caixa mostra, atalhos de teclado e a balança de código de barras.',
  compra: 'Como a compra entra: status, lote, pedido e moeda do fornecedor.',
  painel: 'O que o painel inicial avisa.',
  sistema: 'Aparência e comportamento das tabelas.',
  prefixos: 'Letras que vêm antes do número de cada documento.',
  email: 'Servidor que envia os e-mails do negócio.',
  sms: 'Serviço que envia os SMS do negócio.',
  fidelidade: 'Quanto a venda rende em pontos e quanto o ponto vale no resgate.',
  modulos: 'O que aparece no menu e nas telas para este negócio.',
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
const LINHAS: Lista = { 25: '25', 50: '50', 100: '100', 200: '200', 500: '500', 1000: '1000', '-1': 'Todas' };
const PREFIXOS: [string, string][] = [['purchase', 'Compra'], ['purchase_return', 'Devolução de compra'],
  ['purchase_requisition', 'Requisição de compra'], ['purchase_order', 'Pedido de compra'], ['stock_transfer', 'Transferência de estoque'],
  ['stock_adjustment', 'Ajuste de estoque'], ['sell_return', 'Devolução de venda'], ['expense', 'Despesa'], ['contacts', 'Contatos'],
  ['purchase_payment', 'Pagamento de compra'], ['sell_payment', 'Pagamento de venda'], ['expense_payment', 'Pagamento de despesa'],
  ['business_location', 'Local'], ['username', 'Usuário'], ['subscription', 'Assinatura'], ['draft', 'Rascunho'], ['sales_order', 'Pedido de venda']];
const VALIDADE_PONTOS: Lista = { month: 'Meses', year: 'Anos' };
const SMS_SERVICO: Lista = { nexmo: 'Nexmo', twilio: 'Twilio', other: 'Outro (requisição própria)' };
const SMS_METODO: Lista = { get: 'GET', post: 'POST' };
const CEDULAS_EM: Lista = { pos_screen: 'Só no PDV', all_screens: 'Em todas as telas' };
const ATALHOS: [string, string][] = [['express_checkout', 'Finalizar rápido'], ['pay_n_ckeckout', 'Pagar e finalizar'], ['draft', 'Rascunho'],
  ['cancel', 'Cancelar'], ['recent_product_quantity', 'Quantidade do último produto'], ['weighing_scale', 'Balança'],
  ['edit_discount', 'Editar desconto'], ['edit_order_tax', 'Editar imposto do pedido'], ['add_payment_row', 'Nova linha de pagamento'],
  ['finalize_payment', 'Concluir pagamento'], ['add_new_product', 'Novo produto']];
const PDV_LIGA: [string, string][] = [['disable_pay_checkout', 'Esconder "Pagar e finalizar"'], ['disable_draft', 'Esconder rascunho'],
  ['disable_express_checkout', 'Esconder finalizar rápido'], ['hide_product_suggestion', 'Esconder sugestão de produtos'],
  ['hide_recent_trans', 'Esconder vendas recentes'], ['disable_discount', 'Esconder desconto'], ['disable_order_tax', 'Esconder imposto do pedido'],
  ['is_pos_subtotal_editable', 'Subtotal editável'], ['disable_suspend', 'Esconder suspender venda'], ['enable_transaction_date', 'Data da venda editável'],
  ['inline_service_staff', 'Atendente por item'], ['is_service_staff_required', 'Atendente obrigatório'],
  ['disable_credit_sale_button', 'Esconder venda a prazo'], ['enable_weighing_scale', 'Balança de código de barras'],
  ['show_invoice_scheme', 'Mostrar esquema de fatura'], ['show_invoice_layout', 'Mostrar layout de fatura'],
  ['print_on_suspend', 'Imprimir ao suspender'], ['show_pricing_on_product_sugesstion', 'Preço na sugestão de produtos']];
// Balança: a Blade usa a LISTA [1..n] no Form::select, então o valor gravado é o ÍNDICE (0-based) e o rótulo é índice+1.
// O SellPosController lê `product_sku_length + 1`. Os seletores daqui gravam o índice, igual.
const indices = (n: number): Lista => Object.fromEntries(Array.from({ length: n }, (_, i) => [String(i), String(i + 1)]));
const BALANCA: [string, string, number, string][] = [['product_sku_length', 'Dígitos do SKU', 9, '4'],
  ['qty_length', 'Dígitos inteiros da quantidade', 5, '3'], ['qty_length_decimal', 'Dígitos decimais da quantidade', 4, '2']];

function EmpresaIndex({ corpo: inicial, logo, segredos_gravados, email_superadmin, opcoes }: Props) {
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
    <Stack key={id} gap={1}>
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
  // Atalhos (`keyboard_shortcuts.pos`) e balança (`weighing_scale_setting`): objetos à parte, gravados inteiros.
  const atalhos = ((corpo.shortcuts as Record<string, Record<string, Valor>> | null)?.pos ?? {}) as Record<string, Valor>;
  const mudaAtalho = (k: string, v: string) => muda('shortcuts', { ...((corpo.shortcuts as Record<string, Valor>) || {}), pos: { ...atalhos, [k]: v } });
  const balanca = (corpo.weighing_scale_setting && !Array.isArray(corpo.weighing_scale_setting) ? corpo.weighing_scale_setting : {}) as Record<string, Valor>;
  // Como a Blade: ao mexer, o objeto vai completo, com os padrões dela nos campos que faltam.
  const mudaBalanca = (k: string, v: string) => muda('weighing_scale_setting', {
    label_prefix: balanca.label_prefix ?? '', ...Object.fromEntries(BALANCA.map(([c, , , p]) => [c, balanca[c] ?? p])), [k]: v });
  const valorBalanca = (k: string, padrao: string) => (balanca[k] === null || balanca[k] === undefined ? padrao : String(balanca[k]));
  // Prefixos: como a Blade, ao mexer o objeto vai com as 17 chaves (vazio onde não há).
  const prefixos = (corpo.ref_no_prefixes && !Array.isArray(corpo.ref_no_prefixes) ? corpo.ref_no_prefixes : {}) as Record<string, Valor>;
  const mudaPrefixo = (k: string, v: string) =>
    muda('ref_no_prefixes', { ...Object.fromEntries(PREFIXOS.map(([c]) => [c, prefixos[c] ?? ''])), [k]: v });
  // E-mail e SMS: objetos gravados inteiros. Campo comum guarda '' (como a Blade); segredo só viaja se digitado.
  const objeto = (campo: string) => (corpo[campo] && !Array.isArray(corpo[campo]) ? corpo[campo] : {}) as Record<string, Valor>;
  const mudaObj = (campo: string, k: string, v: string, eSegredo = false) => {
    const { [k]: _fora, ...resto } = objeto(campo);
    muda(campo, eSegredo && v === '' ? resto : { ...resto, [k]: v });
  };
  const campoObj = (campo: string, k: string, rotulo: string, tipo = 'text') => {
    const o = objeto(campo);
    return rotulado(`emp-${campo}-${k}`, rotulo,
      <Input id={`emp-${campo}-${k}`} type={tipo} value={o[k] == null ? '' : String(o[k])} onChange={(e) => mudaObj(campo, k, e.target.value)} />);
  };
  const segredoObj = (campo: string, prefixo: string, k: string, rotulo: string) => {
    const o = objeto(campo);
    return rotulado(`emp-${campo}-${k}`, rotulo, (
      <Input id={`emp-${campo}-${k}`} type="password" autoComplete="off" value={o[k] == null ? '' : String(o[k])}
        onChange={(e) => mudaObj(campo, k, e.target.value, true)}
        placeholder={segredos_gravados.includes(`${prefixo}.${k}`) ? 'Gravado — digite para trocar' : 'Não informado'} />
    ));
  };
  const escolhaObj = (campo: string, k: string, rotulo: string, lista: Lista | undefined, padrao: string) =>
    rotulado(`emp-${campo}-${k}`, rotulo, (
      <Select value={objeto(campo)[k] == null ? padrao : String(objeto(campo)[k])} onValueChange={(v) => mudaObj(campo, k, v)} disabled={!lista}>
        <SelectTrigger id={`emp-${campo}-${k}`}><SelectValue /></SelectTrigger>
        <SelectContent>{Object.entries(lista ?? {}).map(([v, l]) => <SelectItem key={v} value={v}>{l}</SelectItem>)}</SelectContent>
      </Select>
    ));
  const servicoSms = objeto('sms_settings').sms_service == null ? 'other' : String(objeto('sms_settings').sms_service);
  const usaSuperadmin = !!objeto('email_settings').use_superadmin_settings;
  const modulos = Array.isArray(corpo.enabled_modules) ? (corpo.enabled_modules as string[]) : [];
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

              {aba === 'pdv' && (
                <>
                  <Stack gap={2}>
                    {titulo('O que o caixa mostra')}
                    {PDV_LIGA.map(([k, l]) => ligaPos(k, l))}
                  </Stack>
                  <Stack gap={2}>
                    {titulo('Atalhos de teclado')}
                    <span className="text-xs text-muted-foreground">Ex.: ctrl+shift+b, ctrl+h. Em branco = sem atalho.</span>
                    <Grid cols={2} gap={3}>
                      {ATALHOS.map(([k, l]) => rotulado(`emp-atalho-${k}`, l,
                        <Input id={`emp-atalho-${k}`} value={atalhos[k] == null ? '' : String(atalhos[k])} onChange={(e) => mudaAtalho(k, e.target.value)} />))}
                    </Grid>
                  </Stack>
                  <Stack gap={2}>
                    {titulo('Balança de código de barras')}
                    <Grid cols={2} gap={3}>
                      {rotulado('emp-balanca-label_prefix', 'Prefixo da etiqueta',
                        <Input id="emp-balanca-label_prefix" value={balanca.label_prefix == null ? '' : String(balanca.label_prefix)}
                          onChange={(e) => mudaBalanca('label_prefix', e.target.value)} />, 'Em branco se a balança não usa prefixo.')}
                      {BALANCA.map(([k, l, n, p]) => rotulado(`emp-balanca-${k}`, l, (
                        <Select value={valorBalanca(k, p)} onValueChange={(v) => mudaBalanca(k, v)}>
                          <SelectTrigger id={`emp-balanca-${k}`}><SelectValue /></SelectTrigger>
                          <SelectContent>{Object.entries(indices(n)).map(([v, r]) => <SelectItem key={v} value={v}>{r}</SelectItem>)}</SelectContent>
                        </Select>
                      )))}
                    </Grid>
                  </Stack>
                </>
              )}

              {aba === 'compra' && (
                <>
                  <Stack gap={2}>
                    {ligaCampo('enable_purchase_status', 'Status da compra', 'recebida, pendente ou pedida.')}
                    {ligaCampo('enable_lot_number', 'Número de lote')}
                    {ligaCampo('enable_editing_product_from_purchase', 'Atualizar preço do produto pela compra')}
                    {ligaComum('enable_purchase_order', 'Pedido de compra')}
                    {ligaComum('enable_purchase_requisition', 'Requisição de compra')}
                  </Stack>
                  <Stack gap={2}>
                    {titulo('Moeda do fornecedor')}
                    {ligaCampo('purchase_in_diff_currency', 'Comprar em outra moeda')}
                    {!!corpo.purchase_in_diff_currency && (
                      <Grid cols={2} gap={3}>
                        {escolha('purchase_currency_id', 'Moeda da compra', opcoes?.moedas)}
                        {/* type=number como na Blade: o salvar grava a taxa crua (sem num_uf), então ela tem de chegar com ponto decimal. */}
                        {campo('p_exchange_rate', 'Taxa de câmbio', { tipo: 'number', ajuda: 'Quantas unidades da sua moeda vale 1 da moeda da compra.' })}
                      </Grid>
                    )}
                    {!corpo.purchase_in_diff_currency && (
                      <span className="text-xs text-muted-foreground">Desligado, o salvar grava moeda vazia e taxa 1.</span>
                    )}
                  </Stack>
                </>
              )}

              {aba === 'painel' && (
                <Grid cols={2} gap={3}>
                  {campo('stock_expiry_alert_days', 'Avisar validade com quantos dias', { tipo: 'number', ajuda: 'Em branco, o salvar usa 30.' })}
                </Grid>
              )}

              {aba === 'sistema' && (
                <>
                  <Grid cols={2} gap={3}>
                    {escolha('theme_color', 'Cor do tema', opcoes?.temas, 'Padrão')}
                    {rotulado('emp-cs-default_datatable_page_entries', 'Linhas por tabela', (
                      <Select value={comum.default_datatable_page_entries == null ? '25' : String(comum.default_datatable_page_entries)}
                        onValueChange={(v) => mudaComum('default_datatable_page_entries', v)}>
                        <SelectTrigger id="emp-cs-default_datatable_page_entries"><SelectValue /></SelectTrigger>
                        <SelectContent>{Object.entries(LINHAS).map(([v, l]) => <SelectItem key={v} value={v}>{l}</SelectItem>)}</SelectContent>
                      </Select>
                    ))}
                  </Grid>
                  {ligaCampo('enable_tooltip', 'Mostrar textos de ajuda')}
                </>
              )}

              {aba === 'prefixos' && (
                <Grid cols={2} gap={3}>
                  {PREFIXOS.map(([k, l]) => rotulado(`emp-prefixo-${k}`, l,
                    <Input id={`emp-prefixo-${k}`} value={prefixos[k] == null ? '' : String(prefixos[k])} onChange={(e) => mudaPrefixo(k, e.target.value)} />))}
                </Grid>
              )}

              {aba === 'email' && (
                <>
                  {email_superadmin && liga('emp-email-superadmin', 'Usar o servidor de e-mail do sistema', usaSuperadmin,
                    (v) => mudaObj('email_settings', 'use_superadmin_settings', v ? '1' : '', true))}
                  {!usaSuperadmin && (
                    <Grid cols={2} gap={3}>
                      {escolhaObj('email_settings', 'mail_driver', 'Driver', opcoes?.drivers_email, 'smtp')}
                      {campoObj('email_settings', 'mail_host', 'Servidor')}
                      {campoObj('email_settings', 'mail_port', 'Porta')}
                      {campoObj('email_settings', 'mail_encryption', 'Criptografia (tls/ssl)')}
                      {campoObj('email_settings', 'mail_username', 'Usuário')}
                      {segredoObj('email_settings', 'email', 'mail_password', 'Senha')}
                      {campoObj('email_settings', 'mail_from_address', 'E-mail do remetente', 'email')}
                      {campoObj('email_settings', 'mail_from_name', 'Nome do remetente')}
                    </Grid>
                  )}
                  <p className="m-0 text-xs text-muted-foreground">Senha em branco mantém a gravada. Para apagá-la ou enviar um e-mail de teste, use a tela antiga.</p>
                </>
              )}

              {aba === 'sms' && (
                <>
                  <Grid cols={2} gap={3}>{escolhaObj('sms_settings', 'sms_service', 'Serviço', SMS_SERVICO, 'other')}</Grid>
                  {servicoSms === 'nexmo' && (
                    <Grid cols={2} gap={3}>
                      {campoObj('sms_settings', 'nexmo_key', 'Chave')}
                      {segredoObj('sms_settings', 'sms', 'nexmo_secret', 'Segredo')}
                      {campoObj('sms_settings', 'nexmo_from', 'Remetente')}
                    </Grid>
                  )}
                  {servicoSms === 'twilio' && (
                    <Grid cols={2} gap={3}>
                      {campoObj('sms_settings', 'twilio_sid', 'Conta (SID)')}
                      {segredoObj('sms_settings', 'sms', 'twilio_token', 'Token')}
                      {campoObj('sms_settings', 'twilio_from', 'Remetente')}
                    </Grid>
                  )}
                  {servicoSms === 'other' && (
                    <>
                      <Grid cols={2} gap={3}>
                        {campoObj('sms_settings', 'url', 'URL')}
                        {escolhaObj('sms_settings', 'request_method', 'Método', SMS_METODO, 'post')}
                        {campoObj('sms_settings', 'send_to_param_name', 'Parâmetro do destinatário')}
                        {campoObj('sms_settings', 'msg_param_name', 'Parâmetro da mensagem')}
                      </Grid>
                      <Stack gap={2}>
                        {titulo('Cabeçalhos')}
                        <Grid cols={2} gap={3}>
                          {[1, 2, 3].flatMap((i) => [campoObj('sms_settings', `header_${i}`, `Cabeçalho ${i}`),
                            segredoObj('sms_settings', 'sms', `header_val_${i}`, `Valor do cabeçalho ${i}`)])}
                        </Grid>
                      </Stack>
                      <Stack gap={2}>
                        {titulo('Parâmetros')}
                        <Grid cols={2} gap={3}>
                          {Array.from({ length: 10 }, (_, j) => j + 1).flatMap((i) => [campoObj('sms_settings', `param_${i}`, `Parâmetro ${i}`),
                            segredoObj('sms_settings', 'sms', `param_val_${i}`, `Valor do parâmetro ${i}`)])}
                        </Grid>
                      </Stack>
                    </>
                  )}
                  <p className="m-0 text-xs text-muted-foreground">Valores de cabeçalho e parâmetro podem carregar o token da API e não vêm para esta tela: em branco mantém o gravado. Para apagar ou testar o envio, use a tela antiga.</p>
                </>
              )}

              {aba === 'fidelidade' && (
                <>
                  {ligaCampo('enable_rp', 'Ligar pontos de fidelidade')}
                  {!!corpo.enable_rp && (
                    <>
                      <Grid cols={2} gap={3}>{campo('rp_name', 'Nome dos pontos', { ajuda: 'Como o cliente vê, ex.: Pontos.' })}</Grid>
                      <Stack gap={2}>
                        {titulo('Ganhar pontos')}
                        <Grid cols={2} gap={3}>
                          {campo('amount_for_unit_rp', 'Valor da venda para 1 ponto', { ajuda: 'Ex.: 1,00 = cada 1,00 vendido rende 1 ponto.' })}
                          {campo('min_order_total_for_rp', 'Venda mínima para ganhar pontos')}
                          {campo('max_rp_per_order', 'Máximo de pontos por venda', { tipo: 'number' })}
                        </Grid>
                      </Stack>
                      <Stack gap={2}>
                        {titulo('Resgatar pontos')}
                        <Grid cols={2} gap={3}>
                          {campo('redeem_amount_per_unit_rp', 'Valor de 1 ponto no resgate')}
                          {campo('min_order_total_for_redeem', 'Venda mínima para resgatar')}
                          {campo('min_redeem_point', 'Mínimo de pontos por resgate', { tipo: 'number' })}
                          {campo('max_redeem_point', 'Máximo de pontos por resgate', { tipo: 'number' })}
                        </Grid>
                      </Stack>
                      <Grid cols={2} gap={3}>
                        {campo('rp_expiry_period', 'Validade dos pontos', { tipo: 'number', ajuda: 'Em branco = não vencem.' })}
                        {escolha('rp_expiry_type', 'Contada em', VALIDADE_PONTOS)}
                      </Grid>
                    </>
                  )}
                </>
              )}

              {aba === 'modulos' && (
                <Stack gap={2}>
                  {Object.entries(opcoes?.modulos ?? {}).map(([k, l]) => liga(`emp-modulo-${k}`, l, modulos.includes(k),
                    (v) => muda('enabled_modules', v ? [...modulos, k] : modulos.filter((x) => x !== k))))}
                </Stack>
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
