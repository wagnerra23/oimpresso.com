// Aba Processos — catálogo dos processos com fluxo: lista por área e detalhe com estados
// (filtro por estado de origem), roteiro em paralelo, transições e o comparativo NF-e × NFS-e.
// Fonte visual: `FluxosProcessos` / `Detalhe` / `Estados` em
// prototipo-ui/cowork/Wagner/fluxos-processos.jsx. Dados: CatalogoProcessos (#9496).
// Cobertura do ERP (`CobItem`, resumo e coluna "No oimpresso" do protótipo, #9506): vem pronta do
// catálogo, a tela só mostra.
// "Aparece nas telas" do protótipo fica fora: aponta rotas do protótipo, não do ERP.

import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import type { EstadoCobertura, ProcessoCatalogo } from './tipos';

interface Props {
  processos: ProcessoCatalogo[];
  inicial: string;
}

/** Comparativo do protótipo (`NFX` em fluxos-processos.jsx) — copy fixa da tela. */
const NFX: [string, string, string][] = [
  ['Documenta', 'Circulação de mercadoria', 'Prestação de serviço'],
  ['Imposto', 'ICMS (e IPI, se industrializa)', 'ISS'],
  ['Quem autoriza', 'SEFAZ do estado', 'Emissor nacional ou do município, sempre integrado ao ADN (padrão nacional desde 1º/01/2026)'],
  ['Corrigir depois', 'Carta de correção (CC-e) — sem mexer em valor, imposto, destinatário ou data', 'Substituição: emite-se uma nova NFS-e no lugar da anterior'],
  ['Cancelar', 'Prazo curto (em regra 24 h, varia por UF) e só se a mercadoria não circulou', 'Regra e prazo do emissor/município'],
  ['Estado só dela', 'Denegada · Inutilizada · contingência (SVC/EPEC)', 'Substituída'],
  ['Retenções', 'Raras (ICMS-ST é outra coisa)', 'ISS retido pelo tomador e, para PJ, retenções federais — o título recebe o valor líquido'],
  ['Onde se recolhe', 'UF de origem / destino (DIFAL)', 'Em regra no município do prestador; algumas atividades no local da execução (LC 116, art. 3º)'],
  ['Reforma tributária', 'Ganha campos de IBS/CBS (transição 2026–2032)', 'Ganha campos de IBS/CBS (transição 2026–2032)'],
];

/** `COB_EST` do protótipo. */
const COB_EST: Record<EstadoCobertura, { l: string; tone: string }> = {
  sim: { l: 'executa', tone: 'ok' },
  parcial: { l: 'parcial', tone: 'warn' },
  nao: { l: 'não existe', tone: 'danger' },
  nao_verificado: { l: 'não verificado', tone: 'mute' },
};

/** Selo do processo na lista (`CobItem` do protótipo). */
function CobItem({ r }: { r: ProcessoCatalogo['cobertura'] }) {
  if (!r.total) return null;
  if (r.nao_verificado === r.total) return <span className="flx-pill mute flp-cob">ERP não verificado</span>;
  const tone = r.sim === r.total ? 'ok' : r.sim + r.parcial > 0 ? 'warn' : 'danger';
  return (
    <span
      className={`flx-pill flp-cob ${tone}`}
      title={`${r.sim} executa · ${r.parcial} parcial · ${r.nao} não existe · ${r.nao_verificado} não verificado`}
    >
      {r.sim} de {r.total} no ERP{r.parcial ? ` · ${r.parcial} ${r.parcial === 1 ? 'parcial' : 'parciais'}` : ''}
    </span>
  );
}

function Bloco({ titulo, nota, acao, children }: { titulo: string; nota?: string; acao?: React.ReactNode; children: React.ReactNode }) {
  return (
    <Card className="gap-3 py-4">
      <CardHeader className="px-4">
        <CardTitle className="text-[13.5px]">{titulo}</CardTitle>
        {nota && <CardDescription className="text-[11.5px]">{nota}</CardDescription>}
        {acao && <CardAction>{acao}</CardAction>}
      </CardHeader>
      <CardContent className="px-4">{children}</CardContent>
    </Card>
  );
}

function Detalhe({ p, nomeDe, onIr }: { p: ProcessoCatalogo; nomeDe: (id: string) => string | null; onIr: (id: string) => void }) {
  const [filtro, setFiltro] = useState<string | null>(null);
  const nome = (k: string) => p.estados.find((e) => e.chave === k)?.nome ?? k;
  const saem = (k: string) => p.transicoes.filter((t) => t.de === k).length;
  const lista = filtro ? p.transicoes.filter((t) => t.de === filtro) : p.transicoes;
  const fiscal = p.id === 'nfe' || p.id === 'nfse';

  return (
    <div className="flp-det">
      <div className="flp-det-h">
        <span className="flx-lbl">{p.area} · {p.doc}</span>
        <h3>{p.nome}</h3>
        <p>{p.porque}</p>
      </div>

      <Bloco titulo="Estados" nota="Clique numa etapa para filtrar as ações. O número é quantas ações saem dali.">
        <div className="flp-est" role="group" aria-label="Filtrar transições pelo estado de origem">
          <button type="button" className={`flp-est-b todos${!filtro ? ' on' : ''}`} aria-pressed={!filtro} onClick={() => setFiltro(null)}>
            Todos<small>{p.transicoes.length}</small>
          </button>
          {p.estados.map((e) => {
            const n = saem(e.chave);
            return (
              <button
                key={e.chave}
                type="button"
                aria-pressed={filtro === e.chave}
                className={`flp-est-b t-${e.tipo}${filtro === e.chave ? ' on' : ''}`}
                onClick={() => setFiltro(filtro === e.chave ? null : e.chave)}
                title={n ? `${n} ${n === 1 ? 'ação sai daqui' : 'ações saem daqui'}` : 'Nenhuma ação sai daqui'}
              >
                {e.nome}<small>{n || 'fim'}</small>
              </button>
            );
          })}
        </div>
        <div className="flp-leg" aria-hidden="true">
          <span className="t-ini">início</span><span className="t-esp">esperando algo de fora</span>
          <span className="t-ok">fim</span><span className="t-x">fim por exceção</span>
        </div>
      </Bloco>

      {p.roteiro && (
        <Bloco titulo="Roteiro por item, com etapas em paralelo" nota={p.roteiro.titulo}>
          <div className="flp-rot">
            <div className="flp-rot-tr">{p.roteiro.trilhas.map((tr, i) => <ol key={i}>{tr.map((e) => <li key={e}>{e}</li>)}</ol>)}</div>
            <span className="flp-rot-j" aria-hidden="true" />
            <ol className="flp-rot-fim">{p.roteiro.junta.map((e) => <li key={e}>{e}</li>)}</ol>
          </div>
          <p className="flx-nota">As três trilhas andam juntas; Montagem só libera quando as três concluem. O estado da OS é o do item mais atrasado.</p>
        </Bloco>
      )}

      <Bloco
        titulo={filtro ? `A partir de ${nome(filtro)}` : 'Transições'}
        nota={filtro
          ? `${lista.length} ${lista.length === 1 ? 'ação possível' : 'ações possíveis'}`
          : 'Só passa se = condição para a ação acontecer · efeito = o que o sistema faz sozinho'}
        acao={filtro ? <Button size="sm" variant="ghost" onClick={() => setFiltro(null)}>Ver todas</Button> : undefined}
      >
        {p.cobertura.total > 0 && (
          <p className="flp-cob-r">
            No oimpresso: <b>{p.cobertura.sim}</b> executa · <b>{p.cobertura.parcial}</b> parcial · <b>{p.cobertura.nao}</b> não
            existe · <b>{p.cobertura.nao_verificado}</b> não verificado
          </p>
        )}
        {lista.length === 0 ? (
          <p className="flp-fim">Nenhuma ação sai de <b>{nome(filtro ?? '')}</b> — o documento termina aqui.</p>
        ) : (
          <div className="flp-tw">
            <table className="flp-t">
              <thead>
                <tr><th scope="col">De → para</th><th scope="col">Ação</th><th scope="col">Só passa se</th><th scope="col">Efeito</th><th scope="col">Quem</th><th scope="col">No oimpresso</th></tr>
              </thead>
              <tbody>
                {lista.map((t, i) => (
                  <tr key={i}>
                    <td><span className="flp-de">{nome(t.de)}</span><span className="flp-para">{t.para ? `→ ${nome(t.para)}` : 'sem mudar de estado'}</span></td>
                    <td><b>{t.rotulo}</b></td>
                    <td>{t.guarda || <span className="flx-dim">—</span>}</td>
                    <td>{t.efeito || <span className="flx-dim">—</span>}</td>
                    <td className="flp-quem">{t.quem || <span className="flx-dim">—</span>}</td>
                    <td className="flp-cobc">
                      <span className={`flx-pill ${COB_EST[t.cobertura.estado].tone}`}>{COB_EST[t.cobertura.estado].l}</span>
                      {t.cobertura.onde && <small title={t.cobertura.onde}>{t.cobertura.onde}</small>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Bloco>

      {fiscal && (
        <Bloco titulo="NF-e × NFS-e" nota="As duas são FSMs diferentes — e numa OS de comunicação visual podem nascer as duas.">
          <div className="flp-tw">
            <table className="flp-t flp-cmp">
              <thead><tr><th scope="col" /><th scope="col">NF-e (modelo 55)</th><th scope="col">NFS-e (padrão nacional)</th></tr></thead>
              <tbody>{NFX.map((r) => <tr key={r[0]}><th scope="row">{r[0]}</th><td>{r[1]}</td><td>{r[2]}</td></tr>)}</tbody>
            </table>
          </div>
          <Alert className="flp-alerta">
            <AlertTitle>Qual nota sai é decisão fiscal do item, não da tela</AlertTitle>
            <AlertDescription>
              <p>
              Impresso personalizado e sob encomenda, em regra, é serviço — só ISS (Súmula 156 do STJ), mesmo levando
              material. Revenda de material sem personalização é mercadoria (NF-e). Instalação é serviço. Há exceções
              (impresso que vira embalagem ou insumo de indústria). Por isso cada item carrega a <b>natureza fiscal</b>,
              definida com o contador, e o faturamento decide NF-e, NFS-e ou as duas.
              </p>
            </AlertDescription>
          </Alert>
        </Bloco>
      )}

      {p.liga.length > 0 && (
        <div className="flp-liga">
          <span className="flx-lbl">Conversa com</span>
          {p.liga.map((id) => {
            const n = nomeDe(id);
            return n ? <button key={id} type="button" className="flp-chip" onClick={() => onIr(id)}>{n}</button> : null;
          })}
        </div>
      )}
    </div>
  );
}

export default function AbaProcessos({ processos, inicial }: Props) {
  const [sel, setSel] = useState(inicial);
  const p = processos.find((x) => x.id === sel) ?? processos[0];
  // Áreas na ordem em que aparecem no catálogo (a mesma ordem do protótipo).
  const areas = [...new Set(processos.map((x) => x.area))];
  const nomeDe = (id: string) => processos.find((x) => x.id === id)?.nome ?? null;
  if (!p) return null;

  return (
    <div className="flp">
      <p className="flx-intro">
        Cada documento passa por <b>etapas</b>. Para mudar de etapa alguém faz uma <b>ação</b>, que só vale se uma
        <b> condição</b> for cumprida; ao passar, o sistema faz coisas sozinho — o <b>efeito</b>. O modelo da empresa
        escolhe quais processos usar e com quais regras.
      </p>
      <div className="flp-wrap">
        <nav className="flp-nav" aria-label="Processos com fluxo">
          {areas.map((a) => (
            <div key={a} className="flp-grp">
              <span className="flx-lbl">{a}</span>
              {processos.filter((x) => x.area === a).map((x) => (
                <button
                  key={x.id}
                  type="button"
                  aria-current={x.id === p.id ? 'true' : undefined}
                  className={`flp-item${x.id === p.id ? ' on' : ''}`}
                  onClick={() => setSel(x.id)}
                >
                  <span>{x.nome}</span>
                  <small>{x.estados.length} estados · {x.transicoes.length} transições</small>
                  <CobItem r={x.cobertura} />
                </button>
              ))}
            </div>
          ))}
        </nav>
        <Detalhe key={p.id} p={p} nomeDe={nomeDe} onIr={setSel} />
      </div>
    </div>
  );
}
