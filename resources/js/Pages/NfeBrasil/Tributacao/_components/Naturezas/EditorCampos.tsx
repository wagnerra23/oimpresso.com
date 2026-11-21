// Campos de cada passo do editor de natureza — forma do protótipo `Configuracao de Tributos.html`
// (`passoNat()` e `painel(k)`): rótulos, textos de ajuda, grupos e o que aparece conforme a escolha.
// Só a tela: quem confere é o servidor (POST /naturezas/conferir), como antes.
import { useState, type ReactNode } from 'react';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Grid, Inline, Stack } from '@/Components/layout';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  CSOSN, CST_COM_ST, CST_IBS, CST_ICMS_NATUREZA, CST_IPI_E, CST_IPI_S, CST_PC_E, CST_PC_S, FINS, MODBC, MODBCST,
  ORIG_IBS, ORIG_ICMS, ORIG_IPI, ORIG_MVA, ORIG_PC, REGIME_NOME, TIPOS_NOTA,
  type Natureza, type Opcao, type Regime,
} from './natureza';

export type Passo = 'nat' | 'cfop' | 'icms' | 'ipi' | 'pc' | 'ibs' | 'rev';

type Muda = (f: (x: Natureza) => Natureza) => void;

const LBL = 'mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-[var(--text-dim)]';
// O `Label` do DS põe `.cw-label`, que fica fora de @layer e vence os utilitários de tamanho e peso
// (11px/500, medido em produção 2026-11-21). Inline é o que ganha dele: o protótipo pede 10,5px/600.
const LBL_ESTILO = { fontSize: '10.5px', fontWeight: 600 } as const;
const AJUDA = 'mt-1 block text-[11.5px] leading-snug text-[var(--text-dim)]';
// Grades do protótipo: `.fgrid` (campos, mín. 190px) e `.chks` (marcações, mín. 220px).
const FGRID = { gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 190px), 1fr))' };
const CHKS = { gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 220px), 1fr))' };

/** PIS/COFINS "padrão do regime" em % — os mesmos números do motor (PIS_COFINS_DO_REGIME). */
const PC_PADRAO: Record<'LP' | 'LR', { pis: number; cofins: number }> = { LP: { pis: 0.65, cofins: 3 }, LR: { pis: 1.65, cofins: 7.6 } };

function pct(v: number): string {
  return `${v.toLocaleString('pt-BR', { maximumFractionDigits: 4 })}%`;
}

function Campo({ id, rotulo, ajuda, children }: { id: string; rotulo: string; ajuda?: ReactNode; children: ReactNode }) {
  return (
    <div className="min-w-0">
      <Label htmlFor={id} className={LBL} style={LBL_ESTILO}>{rotulo}</Label>
      {children}
      {ajuda && <span className={AJUDA}>{ajuda}</span>}
    </div>
  );
}

function Escolha({ id, rotulo, valor, opcoes, onChange, disabled, ajuda }: {
  id: string; rotulo: string; valor: string; opcoes: readonly Opcao[]; onChange: (v: string) => void; disabled?: boolean; ajuda?: ReactNode;
}) {
  return (
    <Campo id={id} rotulo={rotulo} ajuda={ajuda}>
      <Select value={valor} onValueChange={onChange} disabled={disabled}>
        <SelectTrigger id={id}><SelectValue /></SelectTrigger>
        <SelectContent>
          {opcoes.map(([v, r]) => <SelectItem key={v} value={v}>{r}</SelectItem>)}
        </SelectContent>
      </Select>
    </Campo>
  );
}

function Texto({ id, rotulo, valor, onChange, disabled, ajuda, placeholder, max, digitos }: {
  id: string; rotulo: string; valor: string; onChange: (v: string) => void; disabled?: boolean; ajuda?: ReactNode;
  placeholder?: string; max?: number; digitos?: boolean;
}) {
  return (
    <Campo id={id} rotulo={rotulo} ajuda={ajuda}>
      <Input id={id} value={valor} disabled={disabled} maxLength={max} placeholder={placeholder}
        className={digitos ? 'font-mono' : undefined} inputMode={digitos ? 'numeric' : undefined}
        onChange={(e) => onChange(digitos ? e.target.value.replace(/\D/g, '') : e.target.value)} />
    </Campo>
  );
}

function Percentual({ id, rotulo, valor, onChange, disabled, ajuda }: {
  id: string; rotulo: string; valor: number; onChange: (v: number) => void; disabled?: boolean; ajuda?: ReactNode;
}) {
  const [texto, setTexto] = useState(valor ? String(valor).replace('.', ',') : '');
  return (
    <Campo id={id} rotulo={`${rotulo} (%)`} ajuda={ajuda}>
      <Input id={id} inputMode="decimal" placeholder="0" value={texto} disabled={disabled} className="text-right font-mono"
        onChange={(e) => {
          setTexto(e.target.value);
          const n = parseFloat(e.target.value.replace(/\./g, '').replace(',', '.'));
          onChange(Number.isFinite(n) ? n : 0);
        }} />
    </Campo>
  );
}

/** Marcação com explicação embaixo (protótipo `.chk`). */
function Marca({ id, rotulo, ajuda, valor, onChange, disabled }: {
  id: string; rotulo: ReactNode; ajuda?: string; valor: boolean; onChange: (v: boolean) => void; disabled?: boolean;
}) {
  return (
    <Inline asChild align="start" gap={2} className="cursor-pointer text-[12.5px] leading-[1.4] text-[var(--text)]">
    <label htmlFor={id}>
      <Checkbox id={id} checked={valor} disabled={disabled} className="mt-0.5" onCheckedChange={(v) => onChange(v === true)} />
      <span>
        {rotulo}
        {ajuda && <small className="block text-[11.5px] text-[var(--text-dim)]">{ajuda}</small>}
      </span>
    </label>
    </Inline>
  );
}

/** Grupo com rótulo e separador tracejado entre grupos (protótipo `.grupo`). */
function Grupo({ titulo, cor, children }: { titulo?: string; cor?: string; children: ReactNode }) {
  return (
    <Stack gap={2} className="[&+&]:mt-4 [&+&]:border-t [&+&]:border-dashed [&+&]:border-[var(--border)] [&+&]:pt-3.5">
      {titulo && (cor
        ? <Inline asChild gap={2}><h4 className="m-0 text-[13px] font-semibold text-[var(--text)]">
            <i aria-hidden className="h-2 w-2 rounded-[2px]" style={{ background: cor }} />{titulo}
          </h4></Inline>
        : <span className={`${LBL} mb-0`}>{titulo}</span>)}
      {children}
    </Stack>
  );
}

function Desligado({ children }: { children: ReactNode }) {
  return (
    <p className="m-0 rounded-lg border border-dashed border-[var(--border)] px-3 py-2.5 text-[12.5px] text-[var(--text-dim)]">{children}</p>
  );
}

export function CamposDoPasso({ passo, n, muda, regime, ro, uf }: {
  passo: Exclude<Passo, 'rev'>; n: Natureza; muda: Muda; regime: Regime; ro: boolean; uf: string;
}) {
  const sn = regime === 'SN';
  const entrada = n.tipo === 'E';
  const cst = sn ? n.icms.csosn : n.icms.cst;

  if (passo === 'nat') {
    return (
      <Grupo>
        <Grid gap={3} style={FGRID}>
          <Texto id="nat-nome" rotulo="Nome da natureza" valor={n.nome} disabled={ro} max={80} placeholder="Ex.: Remessa em garantia"
            ajuda="Aparece no lançamento e vai no campo natOp." onChange={(v) => muda((x) => ({ ...x, nome: v }))} />
          <Escolha id="nat-tipo" rotulo="Tipo da nota" valor={n.tipo} opcoes={TIPOS_NOTA} disabled={ro}
            onChange={(v) => muda((x) => {
              const e = v === 'E';
              x.tipo = v as Natureza['tipo'];
              x.ipi.cst = e ? '49' : '53';
              x.pis.cst = e ? '98' : '01';
              x.cofins.cst = e ? '98' : '01';
              x.pis.cstSN = e ? '98' : '49';
              x.cofins.cstSN = e ? '98' : '49';
              return x;
            })} />
          <Escolha id="nat-fin" rotulo="Finalidade" valor={n.fin} opcoes={FINS} disabled={ro}
            ajuda={n.fin === '4' ? 'Devolução: a nota referencia a original.' : undefined}
            onChange={(v) => muda((x) => ({ ...x, fin: v as Natureza['fin'], ref: v === '4' ? true : x.ref }))} />
        </Grid>
        <Grid gap={2} className="mt-3 gap-x-4" style={CHKS}>
          <Marca id="nat-ref" rotulo="Pede a chave da nota de origem" ajuda="Obrigatório em devolução; útil em garantia e retorno."
            valor={n.ref} disabled={ro} onChange={(v) => muda((x) => ({ ...x, ref: v }))} />
          <Marca id="nat-ativo" rotulo="Natureza ativa" ajuda="Inativa fica guardada e some do lançamento."
            valor={n.ativo} disabled={ro} onChange={(v) => muda((x) => ({ ...x, ativo: v }))} />
        </Grid>
      </Grupo>
    );
  }

  if (passo === 'cfop') {
    const pfx = entrada ? ['1', '2', '3'] : ['5', '6', '7'];
    return (
      <Grid gap={3} style={FGRID}>
        {([['in', 'Dentro do estado'], ['out', 'Outro estado'], ['ext', 'Exterior']] as const).map(([k, r], j) => (
          <Texto key={k} id={`nat-cfop-${k}`} rotulo={r} valor={n.cfop[k]} disabled={ro} max={4} digitos
            placeholder={`Ex.: ${pfx[j]}${n.cfop.in ? n.cfop.in.slice(1) : '102'}`}
            ajuda={n.cfop[k] ? undefined : k === 'ext' ? 'Opcional.' : `Começa com ${pfx[j]}.`}
            onChange={(v) => muda((x) => { x.cfop[k] = v; return x; })} />
        ))}
      </Grid>
    );
  }

  if (passo === 'icms') {
    return (
      <>
        <Grupo>
          <Grid gap={3} style={FGRID}>
            {sn
              ? <Escolha id="icms-csosn" rotulo="CSOSN" valor={n.icms.csosn} opcoes={CSOSN} disabled={ro} ajuda="Simples Nacional usa CSOSN."
                  onChange={(v) => muda((x) => { x.icms.csosn = v; return x; })} />
              : <Escolha id="icms-cst" rotulo="CST do ICMS" valor={n.icms.cst} opcoes={CST_ICMS_NATUREZA} disabled={ro}
                  ajuda={`${REGIME_NOME[regime]} usa CST.`} onChange={(v) => muda((x) => { x.icms.cst = v; return x; })} />}
            <Escolha id="icms-modbc" rotulo="Base de cálculo" valor={n.icms.modbc} opcoes={MODBC} disabled={ro}
              onChange={(v) => muda((x) => { x.icms.modbc = v; return x; })} />
            <Escolha id="icms-origem" rotulo="Alíquota" valor={n.icms.origem} opcoes={ORIG_ICMS} disabled={ro}
              ajuda={n.icms.origem === 'uf' ? `Interna de ${uf} ou 4%, 7%, 12% conforme a UF.` : undefined}
              onChange={(v) => muda((x) => { x.icms.origem = v as Natureza['icms']['origem']; return x; })} />
            {n.icms.origem === 'fixa' && <Percentual id="icms-p" rotulo="Alíquota fixa" valor={n.icms.p} disabled={ro}
              onChange={(v) => muda((x) => { x.icms.p = v; return x; })} />}
            {!sn && ['20', '70'].includes(cst) && <Percentual id="icms-red" rotulo="Redução da base" valor={n.icms.red} disabled={ro}
              onChange={(v) => muda((x) => { x.icms.red = v; return x; })} />}
            {!sn && cst === '51' && <Percentual id="icms-dif" rotulo="Diferimento" valor={n.icms.pDif} disabled={ro}
              onChange={(v) => muda((x) => { x.icms.pDif = v; return x; })} />}
            {sn && ['101', '201'].includes(cst) && <Percentual id="icms-cred" rotulo="Crédito do Simples" valor={n.icms.pCredSN} disabled={ro}
              ajuda="Vazio = usa o do cadastro da empresa." onChange={(v) => muda((x) => { x.icms.pCredSN = v; return x; })} />}
          </Grid>
        </Grupo>
        <Grupo titulo="Entra na base do ICMS">
          <Grid gap={2} className="gap-x-4" style={CHKS}>
            <Marca id="icms-frete" rotulo="Frete, seguro e outras despesas" valor={n.icms.bFrete} disabled={ro}
              onChange={(v) => muda((x) => { x.icms.bFrete = v; return x; })} />
            <Marca id="icms-ipi" rotulo="Valor do IPI" ajuda="Quando o cliente usa ou consome o produto." valor={n.icms.bIPI} disabled={ro}
              onChange={(v) => muda((x) => { x.icms.bIPI = v; return x; })} />
            <Marca id="icms-desc" rotulo="Desconto reduz a base" valor={n.icms.bDesc} disabled={ro}
              onChange={(v) => muda((x) => { x.icms.bDesc = v; return x; })} />
          </Grid>
        </Grupo>
        {CST_COM_ST.includes(cst) && (
          <Grupo titulo="Substituição tributária">
            <Grid gap={3} style={FGRID}>
              <Escolha id="st-modbc" rotulo="Base do ST" valor={n.icms.st.modbcst} opcoes={MODBCST} disabled={ro}
                onChange={(v) => muda((x) => { x.icms.st.modbcst = v; return x; })} />
              <Escolha id="st-mva" rotulo="MVA" valor={n.icms.st.mvaOrig} opcoes={ORIG_MVA} disabled={ro}
                onChange={(v) => muda((x) => { x.icms.st.mvaOrig = v as 'produto' | 'fixa'; return x; })} />
              {n.icms.st.mvaOrig === 'fixa' && <Percentual id="st-mva-p" rotulo="MVA fixa" valor={n.icms.st.mva} disabled={ro}
                onChange={(v) => muda((x) => { x.icms.st.mva = v; return x; })} />}
              <Percentual id="st-red" rotulo="Redução da base do ST" valor={n.icms.st.redB} disabled={ro} ajuda="Opcional."
                onChange={(v) => muda((x) => { x.icms.st.redB = v; return x; })} />
            </Grid>
          </Grupo>
        )}
        {!entrada && n.fin !== '4' && (
          <Grupo titulo="Venda a consumidor final de outro estado">
            <Grid gap={2} className="gap-x-4" style={CHKS}>
              <Marca id="icms-difal" rotulo="Calcular DIFAL" ajuda="Quando o cliente não é contribuinte do ICMS (EC 87/2015)."
                valor={n.icms.difal} disabled={ro} onChange={(v) => muda((x) => { x.icms.difal = v; return x; })} />
              <Marca id="icms-fcp" rotulo="Calcular FCP" ajuda="Quando a UF cobra fundo de combate à pobreza."
                valor={n.icms.fcp} disabled={ro} onChange={(v) => muda((x) => { x.icms.fcp = v; return x; })} />
            </Grid>
          </Grupo>
        )}
      </>
    );
  }

  if (passo === 'ipi') {
    return (
      <>
        <Grupo>
          <Marca id="ipi-on" rotulo={<b>Destacar IPI nesta natureza</b>} ajuda="Só para quem é contribuinte do IPI (industrializa ou importa)."
            valor={n.ipi.on} disabled={ro} onChange={(v) => muda((x) => { x.ipi.on = v; return x; })} />
        </Grupo>
        <Grupo>
          {n.ipi.on ? (
            <Grid gap={3} style={FGRID}>
              <Escolha id="ipi-cst" rotulo="CST do IPI" valor={n.ipi.cst} opcoes={entrada ? CST_IPI_E : CST_IPI_S} disabled={ro}
                onChange={(v) => muda((x) => { x.ipi.cst = v; return x; })} />
              <Texto id="ipi-cenq" rotulo="Enquadramento (cEnq)" valor={n.ipi.cEnq} disabled={ro} max={3} digitos placeholder="999"
                ajuda="999 quando não há enquadramento específico." onChange={(v) => muda((x) => { x.ipi.cEnq = v; return x; })} />
              <Escolha id="ipi-origem" rotulo="Alíquota" valor={n.ipi.origem} opcoes={ORIG_IPI} disabled={ro}
                onChange={(v) => muda((x) => { x.ipi.origem = v as Natureza['ipi']['origem']; return x; })} />
              {n.ipi.origem === 'fixa' && <Percentual id="ipi-p" rotulo="Alíquota fixa" valor={n.ipi.p} disabled={ro}
                onChange={(v) => muda((x) => { x.ipi.p = v; return x; })} />}
            </Grid>
          ) : <Desligado>A nota sai sem o grupo de IPI tributado. Pode seguir para o próximo passo.</Desligado>}
        </Grupo>
      </>
    );
  }

  if (passo === 'pc') {
    return (
      <>
        {(['pis', 'cofins'] as const).map((k) => {
          const nome = k.toUpperCase();
          const padrao = sn ? `No Simples o ${nome} vai no DAS.` : `${REGIME_NOME[regime]}: ${pct(PC_PADRAO[regime as 'LP' | 'LR'][k])}.`;
          return (
            <Grupo key={k} titulo={nome} cor={k === 'pis' ? 'var(--origin-FIN-fg)' : 'var(--stage-blue)'}>
              <Grid gap={3} style={FGRID}>
                <Escolha id={`${k}-cst`} rotulo={`CST do ${nome}`} valor={sn ? n[k].cstSN : n[k].cst}
                  opcoes={entrada ? CST_PC_E : CST_PC_S} disabled={ro}
                  ajuda={sn ? 'CST usado quando a empresa está no Simples.' : undefined}
                  onChange={(v) => muda((x) => { if (sn) x[k].cstSN = v; else x[k].cst = v; return x; })} />
                <Escolha id={`${k}-origem`} rotulo="Alíquota" valor={n[k].origem} opcoes={ORIG_PC} disabled={ro}
                  ajuda={n[k].origem === 'regime' ? padrao : undefined}
                  onChange={(v) => muda((x) => { x[k].origem = v as Natureza['pis']['origem']; return x; })} />
                {n[k].origem === 'fixa' && <Percentual id={`${k}-p`} rotulo="Alíquota fixa" valor={n[k].p} disabled={ro}
                  ajuda="Zero para operações sem receita." onChange={(v) => muda((x) => { x[k].p = v; return x; })} />}
              </Grid>
              {!sn && (
                <Marca id={`${k}-excl`} rotulo="Tirar o ICMS da base" ajuda="Exclusão do ICMS da base (Tema 69 do STF)."
                  valor={n[k].exclICMS} disabled={ro} onChange={(v) => muda((x) => { x[k].exclICMS = v; return x; })} />
              )}
            </Grupo>
          );
        })}
      </>
    );
  }

  return (
    <>
      <Grupo>
        <Marca id="ibs-on" rotulo={<b>Informar IBS e CBS</b>} ajuda="Grupos da reforma tributária; em 2026 os valores são de teste."
          valor={n.ibs.on} disabled={ro} onChange={(v) => muda((x) => { x.ibs.on = v; return x; })} />
      </Grupo>
      <Grupo>
        {n.ibs.on ? (
          <Grid gap={3} style={FGRID}>
            <Escolha id="ibs-cst" rotulo="CST do IBS/CBS" valor={n.ibs.cst} opcoes={CST_IBS} disabled={ro}
              onChange={(v) => muda((x) => { x.ibs.cst = v; return x; })} />
            <Texto id="ibs-cclass" rotulo="Classificação (cClassTrib)" valor={n.ibs.cClass} disabled={ro} max={6} digitos placeholder="000001"
              ajuda="Seis dígitos, da tabela oficial." onChange={(v) => muda((x) => { x.ibs.cClass = v; return x; })} />
            <Escolha id="ibs-origem" rotulo="Alíquotas" valor={n.ibs.origem} opcoes={ORIG_IBS} disabled={ro}
              onChange={(v) => muda((x) => { x.ibs.origem = v as 'padrao' | 'fixa'; return x; })} />
            {n.ibs.origem === 'fixa' && (
              <>
                <Percentual id="ibs-uf" rotulo="IBS estadual" valor={n.ibs.pUF} disabled={ro} onChange={(v) => muda((x) => { x.ibs.pUF = v; return x; })} />
                <Percentual id="ibs-mun" rotulo="IBS municipal" valor={n.ibs.pMun} disabled={ro} onChange={(v) => muda((x) => { x.ibs.pMun = v; return x; })} />
                <Percentual id="ibs-cbs" rotulo="CBS" valor={n.ibs.pCBS} disabled={ro} onChange={(v) => muda((x) => { x.ibs.pCBS = v; return x; })} />
              </>
            )}
            {n.ibs.cst === '200' && <Percentual id="ibs-red" rotulo="Redução de alíquota" valor={n.ibs.pRed} disabled={ro}
              onChange={(v) => muda((x) => { x.ibs.pRed = v; return x; })} />}
          </Grid>
        ) : <Desligado>A nota sai sem os grupos IBS/CBS.</Desligado>}
      </Grupo>
    </>
  );
}
