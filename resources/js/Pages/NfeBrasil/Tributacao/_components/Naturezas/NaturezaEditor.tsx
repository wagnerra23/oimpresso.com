// Editor da natureza em 7 passos — forma do protótipo `prototipo-ui/cowork/Felipe/Configuracao de Tributos.html`
// (`renderEditor()`, `stepsHtml()`, `renderAside()`): Natureza · CFOP · ICMS · IPI · PIS e COFINS · IBS e CBS ·
// Revisão, com a barra de passos mostrando o estado de cada um, navegação Anterior/Próximo, e a lateral
// "No lançamento" com as ações. A conferência continua sendo do servidor (POST /naturezas/conferir), chamada a
// cada alteração; o salvar trava enquanto houver erro. RUNBOOK-tributacao §Naturezas.
//
// Natureza nova: o passo 1 pergunta como preencher — em branco ou importando a nota de referência (`ImportarNota`,
// UC-NFTR-73..77). O XML é lido no navegador, como no protótipo, e não vai ao servidor.
import { useEffect, useMemo, useRef, useState, type CSSProperties } from 'react';
import {
  ArrowLeft, ArrowRight, Check, CheckCircle2, CircleAlert, Copy, Info, Layers, MousePointerClick, Route, Tag,
} from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Segmented } from '@/Components/ui/segmented';
import { Grid, Inline, Stack } from '@/Components/layout';
import { CamposDoPasso, type Passo } from './EditorCampos';
import ImportarNota, { type ModoPreenchimento } from './ImportarNota';
import { lerNotaReferencia, type Importacao } from './importarXml';
import { DESTINOS, lancamento, type Destino } from './lancamento';
import { REGIME_NOME, conferir, salvar, type Alerta, type EmpresaResumo, type Natureza, type Regime } from './natureza';

const PASSOS: { k: Passo; l: string; ico: typeof Tag; cor?: string; sub: (n: Natureza, sn: boolean, novo: boolean) => string }[] = [
  { k: 'nat', l: 'Natureza', ico: Tag, sub: () => 'Nome, tipo e finalidade da natureza.' },
  { k: 'cfop', l: 'CFOP', ico: Route, sub: (n) => `O lançamento escolhe o CFOP pela UF do ${n.tipo === 'E' ? 'remetente' : 'cliente'}.` },
  { k: 'icms', l: 'ICMS', ico: Layers, cor: 'var(--origin-CRM-fg)', sub: (_n, sn) => `${sn ? 'CSOSN' : 'CST'}, base, origem da alíquota, ST e DIFAL.` },
  { k: 'ipi', l: 'IPI', ico: Layers, cor: 'var(--origin-MFG-fg)', sub: () => 'Só para quem destaca IPI.' },
  { k: 'pc', l: 'PIS e COFINS', ico: Layers, cor: 'var(--origin-FIN-fg)', sub: () => 'CST e alíquota do PIS e da COFINS.' },
  { k: 'ibs', l: 'IBS e CBS', ico: Layers, cor: 'var(--stage-violet)', sub: () => 'Grupos da reforma tributária (ano de teste em 2026).' },
  { k: 'rev', l: 'Revisão', ico: CheckCircle2, sub: () => 'Confira o que a natureza preenche no lançamento e salve.' },
];

type DefPasso = (typeof PASSOS)[number];
/** Passo pelo índice, sempre dentro da lista (o índice vem só de 0..6, mas o tipo não sabe). */
const passoEm = (i: number): DefPasso => PASSOS[Math.min(Math.max(i, 0), PASSOS.length - 1)] as DefPasso;
const st0 = (i: number): Passo => passoEm(i).k;

const COR_NIVEL: Record<Alerta['nivel'], string> = { erro: 'var(--neg)', aviso: 'var(--warn)', info: 'var(--accent)' };
const SUPERFICIE = 'min-w-0 overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--surface)] shadow-[var(--shadow-soft)]';

function mistura(cor: string, p: number, base = 'var(--surface)') {
  return `color-mix(in oklch, ${cor} ${p}%, ${base})`;
}

function Alertas({ lista, irPara }: { lista: (Alerta | { nivel: 'ok'; mensagem: string; secao?: undefined })[]; irPara?: (i: number) => void }) {
  if (!lista.length) return null;
  return (
    <Stack gap={2}>
      {lista.map((a, i) => {
        const cor = a.nivel === 'ok' ? 'var(--pos)' : COR_NIVEL[a.nivel];
        const Ico = a.nivel === 'ok' ? CheckCircle2 : a.nivel === 'info' ? Info : CircleAlert;
        const passo = a.secao ? PASSOS.findIndex((p) => p.k === a.secao) : -1;
        return (
          <Inline key={i} align="start" gap={3} className="rounded-lg border px-3 py-2.5 text-[12.5px] leading-[1.45] text-[var(--text)]"
            style={{ background: mistura(cor, 9), borderColor: `color-mix(in oklch, ${cor} 26%, transparent)` }}>
            <Ico aria-hidden className="mt-0.5 h-[15px] w-[15px] flex-none" style={{ color: mistura(cor, 62, 'var(--text)') }} />
            <div className="min-w-0">{a.mensagem}</div>
            {irPara && passo >= 0 && (
              <Button size="sm" variant="ghost" className="ml-auto h-[26px] flex-none px-2 text-[11.5px]" onClick={() => irPara(passo)}>
                Passo {passo + 1} · {passoEm(passo).l}
              </Button>
            )}
          </Inline>
        );
      })}
    </Stack>
  );
}

export default function NaturezaEditor({ inicial, editavel, regime, empresa, onFechar }: {
  inicial: Natureza; editavel: boolean; regime: Regime; empresa?: EmpresaResumo | null; onFechar: (salvou: boolean) => void;
}) {
  const [n, setN] = useState<Natureza>(inicial);
  const [base, setBase] = useState<string>(() => JSON.stringify(inicial));
  const [passo, setPasso] = useState(0);
  const [visto, setVisto] = useState<Set<Passo>>(() => new Set(inicial.id ? PASSOS.map((p) => p.k) : ['nat']));
  const [destino, setDestino] = useState<Destino>('in');
  const [alertas, setAlertas] = useState<Alerta[] | null>(null);
  const [salvando, setSalvando] = useState(false);
  const [modo, setModo] = useState<ModoPreenchimento>(null);
  const [importacao, setImportacao] = useState<(Importacao & { arquivo: string }) | null>(null);
  const [erroXml, setErroXml] = useState<string | null>(null);
  // Cada importação parte da natureza de antes da primeira: um 2º XML não herda campos do 1º.
  const [semImportacao, setSemImportacao] = useState<Natureza | null>(null);
  const pedido = useRef(0);
  const sn = regime === 'SN';
  const ro = !editavel;
  const novo = !n.id;
  const alterada = JSON.stringify(n) !== base;
  // Natureza nova sem escolher "em branco" ou "importar": os passos 2 a 7 e o salvar ficam travados (protótipo `travado()`).
  const travado = novo && !ro && modo === null;
  // Com "importar", só segue depois de ler um XML (protótipo `podeProx`).
  const podeProx = !(st0(passo) === 'nat' && (travado || (modo === 'importar' && !importacao)));

  useEffect(() => {
    if (ro) return;
    const meu = ++pedido.current;
    const t = setTimeout(() => {
      conferir(n).then((a) => { if (meu === pedido.current) setAlertas(a); }).catch(() => {});
    }, 300);
    return () => clearTimeout(t);
  }, [n, ro]);

  const muda = (f: (x: Natureza) => Natureza) => setN((x) => f(structuredClone(x)));
  const erros = (alertas ?? []).filter((a) => a.nivel === 'erro');
  const errosDo = (k: Passo) => (k === 'rev' ? erros.length : erros.filter((a) => a.secao === k).length);
  const previa = useMemo(() => lancamento(n, destino, regime, empresa ?? null), [n, destino, regime, empresa]);
  const st = passoEm(passo);

  const ir = (i: number) => {
    if (i < 0 || i >= PASSOS.length) return;
    setPasso(i);
    setVisto((v) => new Set(v).add(passoEm(i).k));
  };

  const gravar = async () => {
    setSalvando(true);
    const r: Awaited<ReturnType<typeof salvar>> = await salvar(n)
      .catch(() => ({ ok: false as const, mensagem: 'Não foi possível salvar agora.' }));
    setSalvando(false);
    if (r.ok) { toast.success(novo ? 'Natureza criada.' : 'Natureza salva.'); onFechar(true); return; }
    if (r.alertas) { setAlertas(r.alertas); ir(PASSOS.length - 1); }
    toast.error(r.mensagem ?? 'A conferência encontrou erro. Corrija antes de salvar.');
  };

  const lerArquivo = async (arquivo: File) => {
    const texto = await new Promise<string>((ok, falha) => {
      const leitor = new FileReader();
      leitor.onload = () => ok(String(leitor.result));
      leitor.onerror = () => falha(leitor.error);
      leitor.readAsText(arquivo);
    }).catch(() => null);
    if (texto === null) { setErroXml('Não foi possível abrir o arquivo.'); return; }
    const partida = semImportacao ?? n;
    const r = lerNotaReferencia(texto, partida, regime, empresa);
    if ('erro' in r) { setErroXml(r.erro); return; }
    setErroXml(null);
    setSemImportacao(partida);
    setN(r.natureza);
    setImportacao({ ...r, arquivo: arquivo.name });
    toast.success(`XML lido: ${r.linhas.filter((l) => l.situacao !== 'nao').length} campos preenchidos. Revise os passos.`);
  };

  // Duplicar: abre uma cópia ainda não salva, com outro nome — o salvar cria uma natureza nova.
  const duplicar = () => {
    const copia: Natureza = { ...structuredClone(n), id: undefined, nome: `${n.nome} (cópia)` };
    setN(copia);
    setModo('branco');
    setImportacao(null);
    setSemImportacao(null);
    setBase('');
    setVisto(new Set(PASSOS.map((p) => p.k)));
    ir(0);
    toast.success('Cópia aberta. Ajuste o nome e salve.');
  };

  const situacao = novo ? null
    : !n.ativo ? { t: 'Inativa', c: 'var(--text-mute)' }
    : erros.length ? { t: `${erros.length} ${erros.length === 1 ? 'pendência' : 'pendências'}`, c: 'var(--neg)' }
    : { t: 'Em uso', c: 'var(--pos)' };

  const doPasso = st.k === 'rev' ? [] : erros.concat((alertas ?? []).filter((a) => a.nivel !== 'erro')).filter((a) => a.secao === st.k);
  const Ico = st.ico;
  const hue = st.cor ?? 'var(--accent)';

  return (
    <Stack asChild gap={4}>
      <section id="naturezas" data-contract="naturezas-editor" aria-labelledby="ed-titulo">
        <Inline asChild wrap align="end" gap={3} className="gap-x-6">
          <header>
            <div className="min-w-0">
              <button type="button" onClick={() => onFechar(false)}
                className="mb-1.5 inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--text-dim)] hover:text-[var(--text)]">
                <ArrowLeft aria-hidden className="h-3.5 w-3.5" />
                {alterada && !ro ? 'Voltar sem salvar' : 'Naturezas de operação'}
              </button>
              <h2 id="ed-titulo" className="m-0 text-[22px] font-semibold leading-tight tracking-[-.02em] text-[var(--text)]">
                {n.nome || 'Nova natureza'}
              </h2>
              <p className="mt-1 text-[12.5px] leading-[1.45] text-[var(--text-dim)]">
                Passo {passo + 1} de {PASSOS.length} · {st.l} · {ro ? 'só leitura'
                  : novo ? 'natureza nova, ainda não salva' : alterada ? 'alterações não salvas' : 'sem alterações'} · {REGIME_NOME[regime]}
              </p>
            </div>
            <Inline gap={2} className="ml-auto">
              {ro && <Badge variant="info">Só leitura</Badge>}
              {situacao && (
                <span className="inline-flex items-center gap-1.5 text-[10.5px] font-semibold uppercase tracking-[.04em]"
                  style={{ color: mistura(situacao.c, 62, 'var(--text)') }}>
                  <span aria-hidden className="h-1.5 w-1.5 rounded-full" style={{ background: situacao.c }} />{situacao.t}
                </span>
              )}
            </Inline>
          </header>
        </Inline>

        {ro && (
          <p className="m-0 text-xs text-[var(--text-dim)]">
            Esta natureza já é usada pelo cálculo da nota. Ela poderá ser editada aqui quando o cálculo passar a ler esta tela.
          </p>
        )}

        {/* Lateral abaixo do passo até 1080px, como o protótipo (`@media (max-width: 1080px)`); a 1024px fica embaixo. */}
        <Grid cols={1} gap={4} className="items-start min-[1081px]:grid-cols-[minmax(0,1fr)_336px]">
          <section className={SUPERFICIE} aria-labelledby="passo-titulo">
            <Inline asChild gap={0} align="stretch" className="m-0 list-none overflow-x-auto bg-[var(--surface)] px-2">
            <ol aria-label="Passos">
              {PASSOS.map((p, i) => {
                const atual = i === passo;
                const nErr = visto.has(p.k) ? errosDo(p.k) : 0;
                const ok = !atual && visto.has(p.k) && !nErr && alertas !== null;
                const sub = atual ? 'agora' : nErr ? `${nErr} ${nErr === 1 ? 'pendência' : 'pendências'}`
                  : !visto.has(p.k) && p.k !== 'rev' && importacao?.passos.includes(p.k) ? 'do XML, revisar'
                  : visto.has(p.k) ? (p.k === 'rev' ? 'pronta' : 'conferido') : 'a fazer';
                const corN = atual ? 'var(--accent)' : nErr ? 'var(--neg)' : ok ? 'var(--pos)' : null;
                const estilo: CSSProperties = corN
                  ? atual
                    ? { background: corN, borderColor: corN, color: 'var(--accent-fg)' }
                    : { background: mistura(corN, nErr ? 12 : 14), borderColor: mistura(corN, nErr ? 45 : 40, 'var(--border)'), color: mistura(corN, 70, 'var(--text)') }
                  : {};
                return (
                  <Inline asChild key={p.k} gap={0} align="stretch" className="flex-[1_0_auto]">
                  <li>
                    <button type="button" onClick={() => ir(i)} aria-current={atual ? 'step' : undefined} disabled={travado && i > 0}
                      className={`flex w-full items-center gap-2 border-b-2 px-2.5 py-3 text-left ${atual ? 'border-[var(--accent)]' : 'border-transparent'}`}>
                      <span className="inline-flex h-[22px] w-[22px] flex-none items-center justify-center rounded-full border border-[var(--border)] bg-[var(--bg-2)] font-mono text-[11.5px] font-semibold text-[var(--text-dim)]"
                        style={estilo}>
                        {ok ? <Check aria-hidden className="h-[13px] w-[13px]" /> : i + 1}
                      </span>
                      <span>
                        <b className={`block whitespace-nowrap text-[12.5px] font-semibold leading-tight ${atual || ok ? 'text-[var(--text)]' : 'text-[var(--text-dim)]'}`}>{p.l}</b>
                        <small className="block whitespace-nowrap text-[11px] leading-tight"
                          style={{ color: nErr && !atual ? mistura('var(--neg)', 62, 'var(--text)') : 'var(--text-mute)' }}>{sub}</small>
                      </span>
                    </button>
                  </li>
                  </Inline>
                );
              })}
            </ol>
            </Inline>

            <Inline wrap gap={3} className="border-y border-[var(--border)] px-4 py-3" style={{ background: mistura(hue, 5) }}>
              <span aria-hidden className="inline-flex h-[30px] w-[30px] flex-none items-center justify-center rounded-lg"
                style={{ background: `color-mix(in oklch, ${hue} 14%, transparent)`, color: mistura(hue, 62, 'var(--text)') }}>
                <Ico className="h-4 w-4" />
              </span>
              <div className="min-w-0">
                <Inline asChild gap={0}>
                <h3 id="passo-titulo" className="m-0 text-[15px] font-semibold leading-snug text-[var(--text)]">
                  <span aria-hidden className="mr-2 inline-flex h-[18px] w-[18px] items-center justify-center rounded-full bg-[var(--accent)] font-mono text-[11.5px] font-semibold text-[var(--accent-fg)]">
                    {passo + 1}
                  </span>
                  {st.l}
                </h3>
                </Inline>
                <p className="mt-0.5 text-[11.5px] leading-snug text-[var(--text-dim)]">{st.sub(n, sn, novo)}</p>
              </div>
            </Inline>

            <Stack gap={4} className="p-4">
              {st.k === 'rev' ? (
                <>
                  <div>
                    <span className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-[var(--text-dim)]">Cliente</span>
                    <Segmented aria-label="Destino do cliente" value={destino} onValueChange={(v) => setDestino(v as Destino)} options={DESTINOS} />
                  </div>
                  <Grid gap={0} className="gap-px overflow-hidden rounded-lg border border-[var(--border-2)] bg-[var(--border-2)]"
                    style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 230px), 1fr))' }}>
                    {previa.map((x) => (
                      <div key={x.rotulo} className="min-w-0 border-t-[3px] bg-[var(--surface)] px-3 py-2.5" style={{ borderTopColor: x.cor }}>
                        <span className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-[var(--text-dim)]">{x.rotulo}</span>
                        <b className="block break-words text-[13px] font-semibold leading-snug text-[var(--text)]">
                          {x.codigo && <code className="font-mono text-xs font-semibold" style={{ color: mistura(x.cor, 62, 'var(--text)') }}>{x.codigo}</code>}
                          {x.codigo && x.texto ? ' ' : ''}{x.texto}
                        </b>
                        {x.sub && <span className="mt-0.5 block text-[11.5px] leading-snug text-[var(--text-dim)]">{x.sub}</span>}
                      </div>
                    ))}
                  </Grid>
                  <Stack gap={2}>
                    <span className="block text-[10.5px] font-semibold uppercase tracking-[.04em] text-[var(--text-dim)]">Conferência</span>
                    {ro ? <p className="m-0 text-xs text-[var(--text-dim)]">Natureza só de leitura: não é conferida aqui.</p>
                      : alertas === null ? <p className="m-0 text-xs text-[var(--text-dim)]">Conferindo…</p>
                      : <Alertas irPara={ir} lista={alertas.length ? alertas
                          : [{ nivel: 'ok', mensagem: 'Natureza consistente. Pode ser usada no lançamento.' }]} />}
                  </Stack>
                </>
              ) : (
                <>
                  <CamposDoPasso passo={st.k} n={n} muda={muda} regime={regime} ro={ro} uf={empresa?.uf ?? 'da empresa'} />
                  {st.k === 'nat' && novo && !ro && (
                    <ImportarNota modo={modo} resultado={importacao} erro={erroXml}
                      onBranco={() => { setModo('branco'); ir(1); }} onImportar={() => setModo('importar')}
                      onArquivo={(f) => { void lerArquivo(f); }} onOutro={() => { setImportacao(null); setErroXml(null); }} />
                  )}
                  <Alertas lista={doPasso} />
                </>
              )}
            </Stack>

            <Inline wrap gap={2} className="border-t border-[var(--border)] bg-[var(--bg-2)] px-4 py-3">
              {passo > 0 && (
                <Button size="sm" variant="outline" onClick={() => ir(passo - 1)}>
                  <ArrowLeft className="mr-1.5 h-4 w-4" />{passoEm(passo - 1).l}
                </Button>
              )}
              {passo < PASSOS.length - 1 ? (
                <Button size="sm" className="ml-auto" disabled={!podeProx} onClick={() => ir(passo + 1)}>
                  {st.k === 'nat' && modo === 'importar' ? 'Revisar passo a passo' : `Próximo: ${passoEm(passo + 1).l}`}
                  <ArrowRight className="ml-1.5 h-4 w-4" />
                </Button>
              ) : !ro && (
                // Revisão: o salvar também no rodapé do passo (protótipo `renderEditor()`, `nav` sem próximo).
                <Button size="sm" className="ml-auto" onClick={gravar} disabled={salvando || erros.length > 0 || alertas === null}>
                  {novo ? 'Criar natureza' : 'Salvar natureza'}
                </Button>
              )}
            </Inline>
          </section>

          <Stack gap={3} asChild>
            <aside aria-label="No lançamento">
              <section className={SUPERFICIE} aria-labelledby="aside-titulo">
                <Inline gap={3} className="border-b border-[var(--border)] px-4 py-3" style={{ background: mistura('var(--accent)', 5) }}>
                  <span aria-hidden className="inline-flex h-[30px] w-[30px] flex-none items-center justify-center rounded-lg"
                    style={{ background: 'color-mix(in oklch, var(--accent) 14%, transparent)', color: mistura('var(--accent)', 62, 'var(--text)') }}>
                    <MousePointerClick className="h-4 w-4" />
                  </span>
                  <div className="min-w-0">
                    <h3 id="aside-titulo" className="m-0 text-[15px] font-semibold leading-snug text-[var(--text)]">No lançamento</h3>
                    <p className="mt-0.5 text-[11.5px] leading-snug text-[var(--text-dim)]">O que esta natureza preenche nos itens.</p>
                  </div>
                </Inline>
                <Stack gap={3} className="p-4">
                  <div>
                    <span className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-[var(--text-dim)]">Cliente</span>
                    <Segmented aria-label="Destino do cliente na lateral" value={destino} onValueChange={(v) => setDestino(v as Destino)} options={DESTINOS} />
                  </div>
                  <Stack gap={0}>
                    {previa.map((x, i) => (
                      <Grid key={x.rotulo} gap={0} className={`grid-cols-[10px_minmax(0,1fr)] gap-x-2 py-[9px] ${i ? 'border-t border-[var(--border-2)]' : 'pt-0'}`}>
                        <i aria-hidden className="mt-[5px] h-2 w-2 rounded-[2px]" style={{ background: x.cor }} />
                        <div className="min-w-0">
                          <span className="mb-0.5 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-[var(--text-dim)]">{x.rotulo}</span>
                          <b className="block break-words text-[12.5px] font-semibold leading-[1.4] text-[var(--text)]">
                            {x.codigo && <code className="font-mono text-xs font-semibold" style={{ color: mistura(x.cor, 62, 'var(--text)') }}>{x.codigo}</code>}
                            {x.codigo && x.texto ? ' ' : ''}{x.texto}
                          </b>
                          {x.sub && <span className="block text-[11.5px] leading-[1.4] text-[var(--text-dim)]">{x.sub}</span>}
                        </div>
                      </Grid>
                    ))}
                  </Stack>
                  <p className="m-0 rounded-[8px] border border-[var(--border)] bg-[var(--bg-2)] px-2.5 py-2 text-[11.5px] leading-[1.45] text-[var(--text-dim)]">
                    Sem valores: base, imposto e totais são calculados no lançamento, com os produtos e preços da nota.
                  </p>
                </Stack>
              </section>

              {!ro && (
                <Stack gap={2} className="sticky bottom-3 z-30 rounded-lg border bg-[var(--surface)] p-3"
                  style={{ borderColor: mistura('var(--accent)', 22, 'var(--border)') }}>
                  <Button size="lg" onClick={gravar} disabled={travado || salvando || erros.length > 0 || alertas === null}>
                    {novo ? 'Criar natureza' : 'Salvar natureza'}
                  </Button>
                  <Grid cols={2} gap={2}>
                    <Button variant="outline" onClick={duplicar} disabled={novo}>
                      <Copy className="mr-1.5 h-4 w-4" />Duplicar
                    </Button>
                    <Button variant="outline" onClick={() => onFechar(false)}>Cancelar</Button>
                  </Grid>
                  <p className="m-0 text-center text-[11.5px] leading-snug"
                    style={{ color: erros.length && !travado ? mistura('var(--neg)', 62, 'var(--text)') : 'var(--text-dim)' }}>
                    {travado ? 'Escolha começar em branco ou importar uma nota.' : erros.length
                      ? `${erros.length} ${erros.length === 1 ? 'pendência' : 'pendências'} nos passos marcados em vermelho.`
                      : 'Vale para as próximas notas lançadas com esta natureza.'}
                  </p>
                </Stack>
              )}
            </aside>
          </Stack>
        </Grid>
      </section>
    </Stack>
  );
}
