// @memcofre
//   tela: /business/settings
//   module: Configuracoes
//   stories: thread sistema/playbook/08 (F3-1: aba Negócio)
//   permissao: business_settings.access
//
// Configuração da empresa. Atrás da flag `useV2ConfiguracoesEmpresa` (BusinessController::FLAG_EMPRESA_V2);
// desligada, a rota segue na Blade `business/settings`. `?classico=1` abre a Blade mesmo com a flag ligada.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-empresa.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-page.jsx → Empresa()
//
// O salvar (POST /business/update) é substituição total: o que não vem no corpo é zerado. Por isso a tela manda
// o `corpo` inteiro que o servidor montou e só troca os campos da aba que desenha. E-mail e SMS não vêm no corpo
// (guardam senha) e o salvar os preserva.

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
interface Opcoes { moedas: Lista; fusos: Lista; meses: Lista; metodos: Lista; formatos_data: Lista }
interface Props { corpo: Corpo; logo: string | null; opcoes?: Opcoes }

/** Abas na ordem e nos grupos do protótipo. Só `negocio` está na tela nova; as outras abrem na Blade. */
const ABAS: { g: string; itens: { id: string; label: string }[] }[] = [
  { g: 'Empresa', itens: [{ id: 'negocio', label: 'Negócio' }, { id: 'imposto', label: 'Impostos' }, { id: 'produto', label: 'Produtos' }, { id: 'contato', label: 'Contatos' }] },
  { g: 'Operação', itens: [{ id: 'venda', label: 'Vendas' }, { id: 'pdv', label: 'Venda no PDV' }, { id: 'compra', label: 'Compras' }, { id: 'pagto', label: 'Pagamento' }] },
  { g: 'Sistema', itens: [{ id: 'painel', label: 'Painel' }, { id: 'sistema', label: 'Sistema' }, { id: 'prefixos', label: 'Prefixos' }] },
  { g: 'Integrações', itens: [{ id: 'email', label: 'E-mail' }, { id: 'sms', label: 'SMS' }, { id: 'fidelidade', label: 'Pontos de fidelidade' },
    { id: 'modulos', label: 'Módulos' }, { id: 'rotulos', label: 'Rótulos personalizados' }] },
];
const PRONTAS = new Set(['negocio']);

const PRECISOES: Lista = { 0: '0', 1: '1', 2: '2', 3: '3', 4: '4' };
const SIMBOLO: Lista = { before: 'Antes do valor', after: 'Depois do valor' };
const HORA: Lista = { 24: '24 horas', 12: '12 horas' };

function EmpresaIndex({ corpo: inicial, logo, opcoes }: Props) {
  const [corpo, setCorpo] = useState<Corpo>(inicial);
  const [arquivo, setArquivo] = useState<File | null>(null);
  const [sujo, setSujo] = useState(false);
  const [salvando, setSalvando] = useState(false);

  const texto = (k: string) => (corpo[k] === null || corpo[k] === undefined ? '' : String(corpo[k]));
  const muda = (k: string, v: Valor) => { setCorpo((c) => ({ ...c, [k]: v })); setSujo(true); };
  const exportar = !!(corpo.common_settings as Record<string, Valor> | null)?.is_enabled_export;
  const mudaExportar = (on: boolean) => {
    const atual = (corpo.common_settings && !Array.isArray(corpo.common_settings) ? corpo.common_settings : {}) as Record<string, Valor>;
    const { is_enabled_export: _fora, ...resto } = atual;
    muda('common_settings', on ? { ...resto, is_enabled_export: '1' } : resto);
  };

  const salvar = () => {
    setSalvando(true);
    router.post('/business/update', arquivo ? { ...corpo, business_logo: arquivo } : corpo, {
      forceFormData: true, preserveScroll: true,
      onSuccess: () => { setSujo(false); setArquivo(null); },
      onFinish: () => setSalvando(false),
    });
  };

  const campo = (k: string, rotulo: string, extra?: { ajuda?: string; tipo?: string; obrigatorio?: boolean }) => (
    <Stack gap={1}>
      <label htmlFor={`emp-${k}`}>{rotulo}{extra?.obrigatorio ? ' *' : ''}</label>
      <Input id={`emp-${k}`} type={extra?.tipo ?? 'text'} value={texto(k)} onChange={(e) => muda(k, e.target.value)} />
      {extra?.ajuda && <span className="text-xs text-muted-foreground">{extra.ajuda}</span>}
    </Stack>
  );
  const escolha = (k: string, rotulo: string, lista: Lista | undefined) => (
    <Stack gap={1}>
      <label htmlFor={`emp-${k}`}>{rotulo}</label>
      <Select value={texto(k)} onValueChange={(v) => muda(k, v)} disabled={!lista}>
        <SelectTrigger id={`emp-${k}`}><SelectValue placeholder="Carregando…" /></SelectTrigger>
        <SelectContent>
          {Object.entries(lista ?? {}).filter(([v]) => v !== '').map(([v, l]) => <SelectItem key={v} value={v}>{l}</SelectItem>)}
        </SelectContent>
      </Select>
    </Stack>
  );

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Configuração da empresa" subtitle={texto('name')} subnav={<ConfiguracoesSubNav />}
          actions={<Button data-contract="salvar" disabled={!sujo || salvando} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>} />
      </div>

      <div className="grid gap-6 px-6 pt-4 md:grid-cols-[13rem_1fr]">
        <nav data-contract="abas" aria-label="Abas da configuração">
          <Stack gap={3}>
            {ABAS.map((g) => (
              <Stack key={g.g} gap={1}>
                <span className="text-xs font-semibold uppercase text-muted-foreground">{g.g}</span>
                {g.itens.map((i) => PRONTAS.has(i.id) ? (
                  <span key={i.id} data-contract={`aba-${i.id}`} aria-current="page" className="rounded-md bg-muted px-2 py-1 text-sm font-medium">{i.label}</span>
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
            <h2 id="emp-titulo" className="m-0 text-lg font-semibold">Negócio</h2>
            <p className="m-0 text-sm text-muted-foreground">O que identifica a empresa e como o sistema conta dinheiro, data e quantidade.</p>
          </Stack>
          <Deferred data="opcoes" fallback={<p className="text-sm text-muted-foreground">Carregando opções…</p>}>
            <Stack gap={5} className="max-w-3xl text-sm">
              <Grid cols={2} gap={3}>
                {campo('name', 'Nome do negócio', { obrigatorio: true })}
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
                <h3 className="m-0 text-xs font-semibold uppercase text-muted-foreground">Logo do negócio</h3>
                <Inline gap={3}>
                  {logo && <img src={logo} alt="Logo atual" className="h-12 w-auto rounded-md border" />}
                  <Input type="file" accept="image/*" aria-label="Enviar logo" className="max-w-xs"
                    onChange={(e) => { setArquivo(e.target.files?.[0] ?? null); setSujo(true); }} />
                </Inline>
              </Stack>

              <Stack gap={2}>
                <h3 className="m-0 text-xs font-semibold uppercase text-muted-foreground">Códigos da empresa na nota</h3>
                <Grid cols={2} gap={3}>
                  {campo('code_label_1', 'Nome do código 1')}
                  {campo('code_1', 'Código 1')}
                  {campo('code_label_2', 'Nome do código 2')}
                  {campo('code_2', 'Código 2')}
                </Grid>
              </Stack>

              <Inline gap={2}>
                <Checkbox id="emp-exportar" checked={exportar} onCheckedChange={(v) => mudaExportar(v === true)} />
                <label htmlFor="emp-exportar">Permitir exportar listas <span className="text-muted-foreground">— botões de Excel, CSV e PDF nas tabelas.</span></label>
              </Inline>
            </Stack>
          </Deferred>
        </section>
      </div>
    </div>
  );
}

EmpresaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default EmpresaIndex;
