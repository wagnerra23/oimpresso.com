// "Como fica no lançamento" — prévia por destino (protótipo `Configuracao de Tributos.html`, seção
// azul acima da lista). Só mostra naturezas criadas pela tela: a Venda padrão e as do CSV não têm os
// campos da tela, e mostrar os padrões vazios seria afirmar uma regra que a nota não segue (UC-NFTR-70).
// Quando só elas estão ativas, o vazio não diz "cadastre uma natureza ativa": há uma ativa na tabela.
import { useMemo, useState } from 'react';
import { MousePointerClick } from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Segmented } from '@/Components/ui/segmented';
import { Grid, Inline } from '@/Components/layout';
import { completar, type EmpresaResumo, type LinhaNatureza, type Regime } from './natureza';
import { DESTINOS, lancamento, type Destino } from './lancamento';

const LBL = 'mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-[var(--text-dim)]';

export default function ComoFicaNoLancamento({
  linhas, regime, empresa,
}: { linhas: LinhaNatureza[]; regime: Regime; empresa: EmpresaResumo | null }) {
  const opcoes = useMemo(() => linhas.filter((l) => l.editavel && l.natureza.ativo), [linhas]);
  const doSistema = useMemo(() => linhas.filter((l) => !l.editavel && l.natureza.ativo), [linhas]);
  const [escolhida, setEscolhida] = useState<string>('');
  const [destino, setDestino] = useState<Destino>('in');

  const atual = opcoes.find((l) => String(l.id) === escolhida) ?? opcoes[0];
  const itens = atual ? lancamento(completar(atual.natureza), destino, regime, empresa) : [];

  return (
    <section
      aria-labelledby="lancamento-titulo"
      className="min-w-0 overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--surface)] shadow-[var(--shadow-soft)]"
    >
      <Inline
        asChild
        wrap
        gap={3}
        className="border-b border-[var(--border)] px-4 py-3"
        style={{ background: 'color-mix(in oklch, var(--stage-blue) 5%, var(--surface))' }}
      >
      <header>
        <span
          aria-hidden
          className="inline-flex h-[30px] w-[30px] flex-none items-center justify-center rounded-lg"
          style={{
            background: 'color-mix(in oklch, var(--stage-blue) 14%, transparent)',
            color: 'color-mix(in oklch, var(--stage-blue) 62%, var(--text))',
          }}
        >
          <MousePointerClick className="h-4 w-4" />
        </span>
        <div className="min-w-0">
          <h3 id="lancamento-titulo" className="m-0 text-[15px] font-semibold leading-snug text-[var(--text)]">Como fica no lançamento</h3>
          <p className="mt-0.5 text-[11.5px] leading-snug text-[var(--text-dim)]">
            Escolha a natureza e o destino do cliente para ver o que cai nos itens da nota.
          </p>
        </div>
      </header>
      </Inline>

      <div className="p-4">
        {!atual ? (
          <p className="py-4 text-center text-[12.5px] text-[var(--text-dim)]">
            {doSistema.length
              ? `${doSistema.map((l) => l.natureza.nome).join(', ')} já ${doSistema.length > 1 ? 'são usadas' : 'é usada'} pelo cálculo da nota e não ${doSistema.length > 1 ? 'são montadas' : 'é montada'} nesta tela. Crie uma natureza aqui para ver como ela fica no lançamento.`
              : 'Cadastre uma natureza ativa para ver como ela fica no lançamento.'}
          </p>
        ) : (
          <>
            <Inline wrap align="end" gap={3} className="mb-3">
              <div className="min-w-0 max-w-[420px] flex-[1_1_280px]">
                <span className={LBL} id="lanc-natureza">Natureza da operação</span>
                <Select value={String(atual.id)} onValueChange={setEscolhida}>
                  <SelectTrigger aria-labelledby="lanc-natureza"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {opcoes.map((l) => <SelectItem key={l.id} value={String(l.id)}>{l.natureza.nome}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <span className={LBL}>Cliente</span>
                <Segmented
                  aria-label="Destino do cliente"
                  value={destino}
                  onValueChange={(v) => setDestino(v as Destino)}
                  options={DESTINOS}
                />
              </div>
            </Inline>

            <Grid
              gap={0}
              className="gap-px overflow-hidden rounded-lg border border-[var(--border-2)] bg-[var(--border-2)]"
              style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 230px), 1fr))' }}
            >
              {itens.map((x) => (
                <div
                  key={x.rotulo}
                  data-lanc={x.rotulo}
                  className="min-w-0 border-t-[3px] bg-[var(--surface)] px-3 py-2.5"
                  style={{ borderTopColor: x.cor }}
                >
                  <span className={LBL}>{x.rotulo}</span>
                  <b className="block break-words text-[13px] font-semibold leading-snug text-[var(--text)]">
                    {x.codigo && (
                      <code className="font-mono text-xs font-semibold" style={{ color: `color-mix(in oklch, ${x.cor} 62%, var(--text))` }}>
                        {x.codigo}
                      </code>
                    )}
                    {x.codigo && x.texto ? ' ' : ''}
                    {x.texto}
                  </b>
                  {x.sub && <span className="mt-0.5 block text-[11.5px] leading-snug text-[var(--text-dim)]">{x.sub}</span>}
                </div>
              ))}
            </Grid>
            <p className="mt-2.5 text-[11.5px] leading-[1.45] text-[var(--text-dim)]">
              Sem valores aqui: base, imposto e totais são calculados no lançamento. O produto completa o que é dele (NCM,
              origem, alíquota da TIPI, MVA).
            </p>
          </>
        )}
      </div>
    </section>
  );
}
