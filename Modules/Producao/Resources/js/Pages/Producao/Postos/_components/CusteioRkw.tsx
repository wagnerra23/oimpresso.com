// Custo-hora pelo rateio das despesas fixas (RKW) — US-FIN-070 · UC-PCCH-05.
//
// O cálculo é do Financeiro (CusteioRkwService); esta tela só PEDE e MOSTRA: lê
// GET /financeiro/custeio-rkw?mes= e grava com POST /financeiro/custeio-rkw/aplicar/{posto}.
// Os valores chegam em reais como string de 2 casas e não são recalculados aqui.
//
// Mexe em VALOR (regra mestre, proibicoes.md): aplicar é um clique de confirmação que mostra
// antes → depois do posto, e a resposta do servidor (antes/depois gravados) é o que fica na tela.
// Apontamentos já gravados guardam o custo do dia em que começaram (UC-PCCH-01).
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/Components/ui/button';

interface PostoRkw {
  posto_id: number;
  codigo: string;
  nome: string;
  horas: string;
  rateio_fixo: string;
  custo_hora_atual: string;
  parcela_fixa_por_hora: string;
  custo_hora_rkw: string;
}
interface Custeio {
  mes: string;
  despesas_fixas: string;
  horas: string;
  parcela_fixa_por_hora: string | null;
  avisos: string[];
  postos: PostoRkw[];
}
interface Troca { posto_id: number; antes: string; depois: string }

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const brl = (v: string | null) => (v === null ? '—' : BRL.format(Number(v)));
const horas = (v: string) => Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function mesAnterior(): string {
  const d = new Date();
  d.setDate(1);
  d.setMonth(d.getMonth() - 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

async function pedir(url: string, method: 'GET' | 'POST'): Promise<{ ok: boolean; dados?: unknown; msg?: string }> {
  const r = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  if (r.ok) return { ok: true, dados: await r.json() };
  const corpo = await r.json().catch(() => null);
  const msg = (corpo as { message?: string } | null)?.message;
  return { ok: false, msg: msg || (r.status === 403 ? 'Sem permissão para o custeio.' : `Não foi possível (HTTP ${r.status}).`) };
}

export default function CusteioRkw() {
  const [mes, setMes] = useState(mesAnterior);
  const [custeio, setCusteio] = useState<Custeio | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [confirmando, setConfirmando] = useState<number | null>(null);
  const [enviando, setEnviando] = useState(false);
  // Aplicado nesta tela, por mês: o custo-hora RKW soma a parcela fixa ao custo-hora ATUAL, então
  // aplicar de novo o mesmo mês somaria a parcela duas vezes. O botão some depois de aplicar.
  const [aplicados, setAplicados] = useState<Record<string, Troca>>({});

  const carregar = useCallback(async (alvo: string) => {
    setErro(null);
    setCusteio(null);
    const r = await pedir(`/financeiro/custeio-rkw?mes=${alvo}`, 'GET');
    if (r.ok) setCusteio(r.dados as Custeio);
    else setErro(r.msg ?? null);
  }, []);

  useEffect(() => { void carregar(mes); }, [mes, carregar]);

  const aplicar = async (p: PostoRkw) => {
    setEnviando(true);
    const r = await pedir(`/financeiro/custeio-rkw/aplicar/${p.posto_id}?mes=${mes}`, 'POST');
    setEnviando(false);
    setConfirmando(null);
    if (!r.ok) { setErro(r.msg ?? null); return; }
    setAplicados((a) => ({ ...a, [`${mes}:${p.posto_id}`]: r.dados as Troca }));
    router.reload({ only: ['postos'] });
  };

  return (
    <div className="flex flex-col gap-3" data-contract="producao-custeio-rkw">
      <div className="flex flex-wrap items-center gap-3">
        <label className="flex items-center gap-2 text-sm">
          Mês do fechamento
          <input
            type="month"
            aria-label="Mês do fechamento"
            className="h-8 rounded-md border border-border bg-background px-2 text-sm"
            value={mes}
            onChange={(e) => { if (/^\d{4}-(0[1-9]|1[0-2])$/.test(e.target.value)) setMes(e.target.value); }}
          />
        </label>
        {custeio && (
          <span className="pcv-help">
            {brl(custeio.despesas_fixas)} de despesas fixas ÷ {horas(custeio.horas)} h = {brl(custeio.parcela_fixa_por_hora)}/h somados ao custo-hora de cada posto.
          </span>
        )}
      </div>

      {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}
      {custeio && custeio.avisos.length > 0 && (
        <ul className="pcv-help flex flex-col gap-0.5">{custeio.avisos.map((a) => <li key={a}>ⓘ {a}</li>)}</ul>
      )}
      {!custeio && !erro && <p className="pcv-help">Calculando o fechamento…</p>}

      {custeio && custeio.postos.length > 0 && (
        <ul className="flex flex-col gap-2">
          {custeio.postos.map((p) => {
            const feito = aplicados[`${mes}:${p.posto_id}`];
            return (
              <li key={p.posto_id} className="flex flex-wrap items-center gap-3 rounded-lg border border-border p-3" data-contract="producao-custeio-rkw-posto">
                <div className="min-w-0 flex-1">
                  <b className="text-sm">{p.nome}</b>
                  <span className="pcv-help block">{horas(p.horas)} h no mês · rateio fixo {brl(p.rateio_fixo)}</span>
                </div>
                {feito ? (
                  <span className="mono text-sm">Aplicado: {brl(feito.antes)} → {brl(feito.depois)}/h</span>
                ) : confirmando === p.posto_id ? (
                  <>
                    <span className="text-sm">
                      Trocar o custo-hora de <b className="mono">{brl(p.custo_hora_atual)}</b> para <b className="mono">{brl(p.custo_hora_rkw)}</b>/h?
                      <span className="pcv-help block">Apontamentos já gravados mantêm o custo antigo.</span>
                    </span>
                    <Button size="sm" disabled={enviando} onClick={() => void aplicar(p)}>Confirmar</Button>
                    <Button size="sm" variant="ghost" disabled={enviando} onClick={() => setConfirmando(null)}>Cancelar</Button>
                  </>
                ) : (
                  <>
                    <span className="mono text-sm">{brl(p.custo_hora_atual)} → {brl(p.custo_hora_rkw)}/h</span>
                    <Button size="sm" variant="outline" onClick={() => setConfirmando(p.posto_id)} aria-label={`Aplicar custo-hora em ${p.nome}`}>
                      Aplicar custo-hora
                    </Button>
                  </>
                )}
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
