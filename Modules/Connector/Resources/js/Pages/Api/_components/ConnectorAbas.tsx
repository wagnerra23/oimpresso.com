// Abas Documentação, Saúde e Módulo do painel do Conector — thread Connector/04, PR-b (2026-10-01).
// Âncora de design: prototipo-ui/cowork/Wagner/connector-api.jsx (DocsView, SaudeView) +
// connector-page.jsx (ModuloView). O que o protótipo escreveu à mão aqui é DERIVADO:
//   - o catálogo vem das rotas registradas (prop `endpoints`), não de uma lista copiada;
//   - a Saúde lê o histórico que o `connector:health` publica (thread 08): última execução, série
//     de 14 dias (uma barra por dia, a última execução do dia) e os desvios do DelphiSync. Valor
//     que a rotina não mediu (null) aparece "não medido", nunca como zero;
//   - o Módulo mostra estado/versão/migrações medidos e leva às confirmações do InstallController.
import { useMemo, useState } from 'react';
import { Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import EmptyState from '@/Components/shared/EmptyState';
import Chart from '@/Components/shared/Chart';

export interface Endpoint { metodos: string; rota: string; acao: string }
export interface Modulo { instalado: boolean; versao: string; migracoes: number }

const DELPHI = ['LicencaComputadorController', 'BusinessController', 'OImpressoRegistroController', 'CheckUpdateController'];
const CADASTROS = ['BusinessLocationController', 'ContactController', 'UnitController', 'CategoryController', 'BrandController',
  'ProductController', 'TaxController', 'TableController', 'TypesOfServiceController', 'ProductSellController'];
const FINANCEIRO_COMUM = ['getPaymentAccounts', 'getPaymentMethods', 'getProfitLoss', 'getProductStock'];

// Grupos do protótipo. Rota que não casa com nenhum vai para "Outras rotas" — nunca some.
const GRUPOS = [
  { id: 'delphi', label: 'Delphi · WR Comercial', desc: 'Fluxo de licenciamento do sistema desktop. O Delphi lê a resposta literal: mudar o formato quebra o cliente em campo (ADR 0021).' },
  { id: 'cadastros', label: 'Cadastros e catálogo' },
  { id: 'vendas', label: 'Vendas e caixa' },
  { id: 'financeiro', label: 'Financeiro e relatórios' },
  { id: 'pessoas', label: 'Pessoas, ponto e conta' },
  { id: 'crm', label: 'CRM', desc: 'Prefixo próprio: connector/api/crm' },
  { id: 'ff', label: 'Field Force' },
  { id: 'outros', label: 'Outras rotas' },
] as const;

function grupoDe(e: Endpoint): string {
  const [classe = '', metodo = ''] = e.acao.split('@');
  if (e.rota.startsWith('crm/')) return 'crm';
  if (e.rota.startsWith('field-force')) return 'ff';
  if (DELPHI.includes(classe)) return 'delphi';
  if (['SellController', 'CashRegisterController'].includes(classe)) return 'vendas';
  if (classe === 'ExpenseController' || (classe === 'CommonResourceController' && FINANCEIRO_COMUM.includes(metodo))) return 'financeiro';
  if (['UserController', 'AttendanceController', 'SuperadminController', 'CommonResourceController'].includes(classe)
    && metodo !== 'getBusinessDetails') return 'pessoas';
  if (CADASTROS.includes(classe) || metodo === 'getBusinessDetails') return 'cadastros';
  return 'outros';
}

// Observação só onde o contrato com o desktop está escrito no arquivo de rotas (Routes/api.php).
function observacao(e: Endpoint): string {
  if (e.rota === 'processa-dados-cliente') return 'Body: array com NOME_TABELA EMPRESA + LICENCIAMENTO. Resposta texto: S;liberado ou N;motivo';
  if (e.rota === 'oimpresso/registrar') return 'Body JSON plano. Resposta JSON: autorizado · licenca_id · dias_restantes · data_expiracao';
  if (e.rota === 'check-update') return 'Body texto CNPJ;VersaoAtual. Resposta VersaoNova;VersaoMinObrigatoria ou N;VersaoMinObrigatoria';
  if (e.acao.endsWith('@cedidoAoPonto')) return 'Responde 410: a presença foi cedida ao Ponto';
  return '—';
}

const FORMATOS = [
  { k: 'array_tabelas', t: 'Delphi legado (3.7)', d: 'JSON em array com NOME_TABELA = EMPRESA e LICENCIAMENTO. O HD sai da linha LICENCIAMENTO, o CNPJ da linha EMPRESA.' },
  { k: 'json_flat', t: 'WR Comercial atual', d: 'JSON plano com cnpj, serial_hd e versao.' },
  { k: 'pipe', t: 'Fallback TThreadLicenca', d: 'Texto puro SERIAL|HOST|VERSAO|IP|CNPJ|RAZAO; o serial é o primeiro campo.' },
];

const card = 'rounded-md border p-4 text-sm';
const dl = 'grid grid-cols-[8rem_1fr] gap-1 text-xs';

export function DocsAba({ endpoints }: { endpoints: Endpoint[] }) {
  const [q, setQ] = useState('');
  const busca = q.trim().toLowerCase();
  const grupos = useMemo(() => GRUPOS.map((g) => ({
    ...g,
    eps: endpoints.filter((e) => grupoDe(e) === g.id
      && (!busca || `${e.rota} ${e.acao} ${observacao(e)}`.toLowerCase().includes(busca))),
  })).filter((g) => g.eps.length > 0), [endpoints, busca]);
  const achados = grupos.reduce((n, g) => n + g.eps.length, 0);

  return (
    <div className="flex flex-col gap-4">
      <div data-contract="docs-como-entra" className="grid gap-3 md:grid-cols-2">
        <section className={card}>
          <h3 className="mb-2 font-semibold">Como um app externo entra</h3>
          <ol className="list-decimal space-y-1 pl-5">
            <li>Você cria um <b>API client</b> na aba API clients: ele devolve <code>client_id</code> e <code>client_secret</code>.</li>
            <li>O app pede o token em <code>POST /oauth/token</code> (usuário e senha do colaborador + as duas credenciais).</li>
            <li>Toda chamada vai com <code>Authorization: Bearer …</code>; o <code>auth:api</code> resolve o negócio pelo usuário do token.</li>
          </ol>
        </section>
        <section className={card}>
          <h3 className="mb-2 font-semibold">Regras que valem em todo endpoint</h3>
          <dl className={dl}>
            <dt className="text-muted-foreground">Limite</dt><dd className="font-mono">120 req/min por token</dd>
            <dt className="text-muted-foreground">Autenticação</dt><dd className="font-mono">auth:api (OAuth do Passport)</dd>
            <dt className="text-muted-foreground">Registro</dt><dd className="font-mono">log.delphi grava corpo e formato</dd>
            <dt className="text-muted-foreground">Fuso</dt><dd className="font-mono">middleware timezone</dd>
          </dl>
        </section>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-64 flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
            placeholder="Buscar endpoint, controller ou observação…" aria-label="Buscar endpoint" />
        </div>
        <span className="text-xs text-muted-foreground">{busca ? `${achados} de ${endpoints.length} rotas` : `${endpoints.length} rotas lidas do arquivo de rotas`}</span>
      </div>

      <div data-contract="docs-catalogo" className="flex flex-col gap-4">
        {grupos.map((g) => (
          <section key={g.id}>
            <h3 className="font-semibold">{g.label} <span className="font-mono text-xs text-muted-foreground">{g.eps.length}</span></h3>
            {'desc' in g && <p className="text-xs text-muted-foreground">{g.desc}</p>}
            <table className="mt-1 w-full text-sm [&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-0 [&_th:first-child]:pl-0 [&_td:last-child]:pr-0 [&_th:last-child]:pr-0">
              <thead><tr className="border-b text-left text-xs text-muted-foreground"><th className="w-32 py-1">Método</th><th>Rota</th><th>Controller</th><th>Observação</th></tr></thead>
              <tbody>{g.eps.map((e) => (
                <tr key={`${e.metodos} ${e.rota}`} className="border-b">
                  <td className="py-1 font-mono text-xs">{e.metodos}</td>
                  <td className="font-mono text-xs">connector/api/{e.rota}</td>
                  <td className="font-mono text-xs">{e.acao}</td>
                  <td className="text-xs">{observacao(e)}</td>
                </tr>))}</tbody>
            </table>
          </section>
        ))}
        {grupos.length === 0 && (
          <EmptyState variant="search" title={`Nenhum endpoint casa com “${q}”`} description="A busca cobre rota, controller e observação."
            action={<Button variant="outline" onClick={() => setQ('')}>Limpar busca</Button>} />
        )}
      </div>

      <section data-contract="docs-formatos">
        <h3 className="font-semibold">Formatos de corpo que o desktop manda</h3>
        <p className="text-xs text-muted-foreground">O DelphiSyncService detecta o formato sozinho; os três convivem em campo.</p>
        <div className="mt-2 grid gap-3 md:grid-cols-3">
          {FORMATOS.map((f) => (
            <div key={f.k} className={card}><span className="font-mono text-xs">{f.k}</span><b className="block">{f.t}</b><p className="text-xs">{f.d}</p></div>
          ))}
        </div>
      </section>
    </div>
  );
}

// Uma entrada do histórico publicado pelo `connector:health` (storage/app/connector/health-history.json).
// Os números são de todos os negócios — o painel é só de superadmin.
export interface Execucao {
  executado_em: string; ok: boolean;
  tokens_active_24h: number | null; licencas_recent_24h: number | null; rotas_registradas: number;
  issues: string[];
  delphi: { chamadas_24h: number | null; desvios_24h: number | null; taxa_desvio: number | null };
}

const CHECKS = [
  { k: 'tokens_active_24h', label: 'Tokens ativos em 24 h', min: 1, fonte: 'oauth_access_tokens sem revogação, sem vencimento passado, tocados em 24 h. Zero: nenhum app externo autenticou.' },
  { k: 'licencas_recent_24h', label: 'Licenças com acesso em 24 h', min: 1, fonte: 'licenca_computador.dt_ultimo_acesso em 24 h. Zero: nenhum WR Comercial abriu.' },
  { k: 'rotas_registradas', label: 'Rotas registradas', min: 20, fonte: 'Rotas com prefixo connector/api. Abaixo de 20 o provedor do módulo não subiu.' },
] as const;

const dia = (iso: string) => iso.slice(0, 10);
const dataHora = (iso: string) => new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

export function SaudeAba({ historico }: { historico: Execucao[] }) {
  // Série de 14 dias: a última execução de cada dia (a rotina roda 1× ao dia; execução manual repete o dia).
  const porDia = useMemo(() => {
    const m = new Map<string, Execucao>();
    for (const e of historico) m.set(dia(e.executado_em), e);
    return [...m.entries()].sort(([a], [b]) => a.localeCompare(b)).slice(-14);
  }, [historico]);
  const ultima = historico[historico.length - 1];

  if (!ultima) {
    return (
      <div data-contract="saude-checks">
        <EmptyState title="Nenhuma execução publicada nos últimos 14 dias"
          description="A rotina connector:health roda às 06:15 (Brasília) e publica uma entrada por execução. Esta tela não executa o comando: se o histórico está vazio, a rotina não rodou." />
      </div>
    );
  }

  const d = ultima.delphi;
  return (
    <div className="flex flex-col gap-4">
      <div data-contract="saude-ultima" className={`rounded-md border p-3 text-sm ${ultima.ok ? 'border-info/30 bg-info/5' : 'border-warning/30 bg-warning/5'}`}>
        <b>{ultima.ok ? 'Última execução sem alerta' : 'Última execução com alerta'}</b> · {dataHora(ultima.executado_em)}
        {ultima.issues.length > 0 && <ul className="mt-1 list-disc pl-5 font-mono text-xs">{ultima.issues.map((i) => <li key={i}>{i}</li>)}</ul>}
        <p className="mt-1 text-xs text-muted-foreground">Os números vêm do registro da rotina, de todos os negócios — esta tela não executa o comando. {historico.length} execução(ões) em 14 dias.</p>
      </div>
      <div data-contract="saude-checks" className="grid gap-3 md:grid-cols-3">
        {CHECKS.map((c) => {
          const v = ultima[c.k];
          const serie = porDia.flatMap(([d1, e]) => (e[c.k] === null ? [] : [{ label: `${d1.slice(8, 10)}/${d1.slice(5, 7)}`, value: e[c.k] as number }]));
          return (
            <section key={c.k} className={card}>
              <div className="flex items-start justify-between gap-2">
                <span className="text-xs text-muted-foreground">{c.label}</span>
                {v !== null && <span className={`text-xs ${v >= c.min ? 'text-success' : 'text-destructive'}`}>{v >= c.min ? 'Dentro do limiar' : 'Abaixo do limiar'}</span>}
              </div>
              <b className="block text-2xl tabular-nums">{v ?? '—'}</b>
              <p className="font-mono text-xs text-muted-foreground">limiar ≥ {c.min}{v === null ? ' · não medido nesta execução' : ''}</p>
              {serie.length > 1 && <div className="mt-2"><Chart type="bar" data={serie} height={56} /></div>}
              <p className="mt-1 text-xs">{c.fonte}</p>
            </section>
          );
        })}
      </div>
      <section data-contract="saude-desvios" className={card}>
        <h3 className="mb-1 font-semibold">Desvios do DelphiSync em 24 h</h3>
        {d.chamadas_24h === null ? (
          <p className="text-xs">Não medido: o registro do <code>log.delphi</code> (<code>licenca_log</code>) não existe nesta base.</p>
        ) : (
          <p className="text-sm"><b className="tabular-nums">{d.desvios_24h}</b> de <span className="tabular-nums">{d.chamadas_24h}</span> chamadas com corpo em formato desconhecido
            {d.taxa_desvio === null ? ' — sem chamada com corpo, sem taxa.' : ` · taxa ${(d.taxa_desvio * 100).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`}</p>
        )}
        <p className="mt-1 text-xs text-muted-foreground">Formato fora de array_tabelas, json_flat e pipe. Taxa acima de zero merece olhada: é cliente legado mandando formato novo sem avisar.</p>
      </section>
    </div>
  );
}

export function ModuloAba({ modulo, rotas }: { modulo: Modulo; rotas: number }) {
  return (
    <div className="flex flex-col gap-4">
      <div className="rounded-md border border-warning/30 bg-warning/5 p-3 text-sm">
        <b>Instalar e atualizar têm um passo extra.</b> Depois das migrações o instalador roda <code>passport:install --force</code>,
        que regera as chaves de OAuth da plataforma: todo app externo, inclusive o WR Comercial, precisa autenticar de novo.
      </div>
      <section data-contract="modulo-estado" className={card}>
        <h3 className="mb-2 font-semibold">Estado</h3>
        <dl className={dl}>
          <dt className="text-muted-foreground">Módulo</dt><dd className="font-mono">Connector · connector</dd>
          <dt className="text-muted-foreground">Versão</dt><dd className="font-mono">{modulo.versao}</dd>
          <dt className="text-muted-foreground">Migrações</dt><dd className="font-mono">{modulo.migracoes}</dd>
          <dt className="text-muted-foreground">Situação</dt><dd>{modulo.instalado ? 'Instalado' : 'Não instalado'}</dd>
          <dt className="text-muted-foreground">Endpoints</dt><dd className="font-mono">{rotas}</dd>
          <dt className="text-muted-foreground">Área</dt><dd>Integrações</dd>
        </dl>
        <div className="mt-3 flex gap-2">
          {/* Cada botão abre a confirmação do InstallController: o GET não faz nada, a ação roda no POST. */}
          {modulo.instalado ? (
            <>
              <Button asChild variant="outline"><a href="/connector/install/update">Atualizar</a></Button>
              <Button asChild variant="outline" className="text-destructive"><a href="/connector/install/uninstall">Desinstalar</a></Button>
            </>
          ) : (
            <Button asChild><a href="/connector/install">Instalar</a></Button>
          )}
        </div>
      </section>
    </div>
  );
}
