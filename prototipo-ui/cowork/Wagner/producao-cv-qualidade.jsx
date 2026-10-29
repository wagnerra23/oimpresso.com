// producao-cv-qualidade.jsx — inspeção da etapa e não conformidade (US-PCP-033, P2; lacunas de 2026-10-25,
// referência TOTVS e SAP). Amplia o que já existe: o refugo com motivo e o "Reprovar no controle" (papel
// producao.qualidade, US-PCP-027) mandam a OS para retrabalho. Aqui entram a inspeção que APROVA, a não
// conformidade como registro próprio e a ação corretiva que a encerra, para o mesmo defeito não voltar sem
// ninguém saber por quê. Só quem tem o papel de qualidade inspeciona e encerra.
// Casos: cowork-inbox/producao/Producao.casos.md, UC-PCV-53. Expõe window.PcvQualidade. Depende de window.PCVD.
(() => {
const { useState } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const P = () => window.PCVD;
const B = (p) => (window.PcvBadge ? <window.PcvBadge par={p} /> : null);
const MOTIVOS = ["Banding / falha de cabeça", "Cor fora do padrão", "Medida errada", "Mídia com defeito", "Erro de arte", "Dano no manuseio", "Acabamento fora do padrão"];

// Inspeções por OS (append-only: corrigir é inspecionar de novo). `nc` liga a inspeção reprovada à não conformidade.
const INSPECOES = {
  "OS-2603": [
    { etapa: "Arte e prova", resultado: "aprovada", quem: "Larissa", quando: "12/10 10:05" },
    { etapa: "Impressão látex", resultado: "nao conforme", motivo: "Banding / falha de cabeça", obs: "Faixas claras a cada 40 cm em 2 das 12 lonas", foto: "banding-lona-07.jpg", quem: "Larissa", quando: "14/10 15:30", nc: "NC-07" },
  ],
  "OS-2598": [{ etapa: "Bainha + ilhós", resultado: "aprovada", quem: "Larissa", quando: "14/10 08:50" }],
};
// Não conformidades: aberta → em ação (com a ação corretiva) → encerrada (com a verificação).
const NCS = [
  { id: "NC-07", os: "OS-2603", etapa: "Impressão látex", maq: "latex", motivo: "Banding / falha de cabeça", estado: "em ação", abertaEm: "14/10 15:30",
    acao: { texto: "Limpar e alinhar a cabeça 3 antes da próxima lona; teste de bicos no início do turno", resp: "Diego", prazo: "2026-10-16" } },
  { id: "NC-05", os: "OS-2590", etapa: "Impressão eco-solvente", maq: "eco", motivo: "Cor fora do padrão", estado: "encerrada", abertaEm: "09/10 11:20",
    acao: { texto: "Perfil de cor refeito para o vinil novo", resp: "Diego", prazo: "2026-10-10" }, verificacao: "Três lotes seguintes aprovados na inspeção" },
];
let seq = 8;
const EST = { aberta: ["Aberta", "danger"], "em ação": ["Em ação", "warning"], encerrada: ["Encerrada", "success"] };
const inspDe = (id) => INSPECOES[id] || [];
const ncsDe = (id) => NCS.filter((n) => n.os === id);
const abertas = () => NCS.filter((n) => n.estado !== "encerrada");

function CartaoNc({ n, D }) {
  const { Button, Input, Select } = DS();
  const [f, setF] = useState(null);
  const pessoas = Object.keys(D.PESSOAS);
  return (
    <li className="pcv-nc">
      <div className="pcv-nc-h"><b className="mono">{n.id}</b> · {n.etapa}{n.maq && D.maqDe(n.maq) ? " · " + D.maqDe(n.maq).nome : ""} {B(EST[n.estado])}</div>
      <small>{n.motivo} · aberta em {n.abertaEm}</small>
      {n.acao && <small>Ação corretiva: {n.acao.texto} · {n.acao.resp} · prazo {D.d2(n.acao.prazo)}</small>}
      {n.verificacao && <small>Verificação: {n.verificacao}</small>}
      {f ? <div className="pcv-form">
        {f.modo === "acao" ? <>
          {Input && <Input label="O que vai ser feito para o defeito não voltar" value={f.texto} onChange={(e) => setF({ ...f, texto: e.target.value })} />}
          {Select && <Select label="Responsável" value={f.resp} onChange={(e) => setF({ ...f, resp: e.target.value })} options={pessoas.map((p) => ({ value: p, label: p }))} />}
          {Input && <Input label="Prazo" type="date" value={f.prazo} onChange={(e) => setF({ ...f, prazo: e.target.value })} />}
        </> : Input && <Input label="Como foi verificado que resolveu" value={f.texto} placeholder="Ex.: os 3 lotes seguintes passaram na inspeção" onChange={(e) => setF({ ...f, texto: e.target.value })} />}
        <div className="pcv-bar"><Button onClick={() => setF(null)}>Cancelar</Button><Button variant="primary" disabled={f.texto.trim().length < 5} onClick={() => {
          if (f.modo === "acao") { n.acao = { texto: f.texto.trim(), resp: f.resp, prazo: f.prazo }; n.estado = "em ação"; D.registrar({ os: n.os, tipo: "pcp", texto: n.id + ": ação corretiva para " + f.resp + " até " + D.d2(f.prazo) + " — " + f.resp + " foi avisado no app" }); }
          else { n.verificacao = f.texto.trim(); n.estado = "encerrada"; D.registrar({ os: n.os, tipo: "pcp", texto: "Encerrou a " + n.id + ": " + f.texto.trim() }); }
          setF(null); }}>{f.modo === "acao" ? "Registrar ação" : "Encerrar"}</Button></div>
      </div> : n.estado !== "encerrada" && <div className="pcv-bar">
        {Button && <Button size="sm" onClick={() => setF({ modo: "acao", texto: n.acao ? n.acao.texto : "", resp: n.acao ? n.acao.resp : pessoas[0], prazo: n.acao ? n.acao.prazo : D.HOJE })}>{n.acao ? "Mudar a ação" : "Registrar ação corretiva"}</Button>}
        {Button && n.acao && <Button size="sm" onClick={() => setF({ modo: "verificar", texto: "" })}>Encerrar</Button>}
      </div>}
    </li>
  );
}

// Aba "Qualidade" da ficha da OS.
function AbaQualidade({ os }) {
  const D = P(); const { Button, Input, Select, Alert } = DS();
  const [nova, setNova] = useState(null);
  const lista = inspDe(os.id);
  const etapas = D.etapas(os).filter((e) => e.estado !== "fila" && e.estado !== "a agendar");
  const inspecionar = () => {
    const ok = nova.resultado === "aprovada";
    const reg = { etapa: nova.etapa, resultado: nova.resultado, quem: "Você", quando: "agora", ...(ok ? {} : { motivo: nova.motivo, obs: nova.obs.trim(), foto: nova.foto }) };
    if (!ok) { reg.nc = "NC-" + String(seq++).padStart(2, "0"); NCS.unshift({ id: reg.nc, os: os.id, etapa: nova.etapa, maq: (D.etapas(os).find((e) => e.nome === nova.etapa) || {}).maq, motivo: nova.motivo, estado: "aberta", abertaEm: "agora" }); }
    (INSPECOES[os.id] = INSPECOES[os.id] || []).push(reg);
    D.registrar({ os: os.id, tipo: ok ? "etapa" : "refugo", texto: ok ? "Inspecionou " + nova.etapa + ": aprovada" : "Inspecionou " + nova.etapa + ": não conforme (" + nova.motivo + "). Abriu a " + reg.nc });
    setNova(null);
  };
  const podeSalvar = nova && nova.etapa && (nova.resultado === "aprovada" || (nova.motivo && nova.foto));
  return (
    <div className="pcv-sec">
      {lista.length ? <ul className="pcv-refugo" aria-label={"Inspeções da " + os.id}>{lista.map((r, i) => (
        <li key={i}><div><b>{r.etapa}</b><small>{r.resultado === "aprovada" ? "aprovada" : r.motivo + (r.obs ? " — " + r.obs : "") + " · foto " + r.foto} · {r.quem} · {r.quando}</small></div>
          {B(r.resultado === "aprovada" ? ["Aprovada", "success"] : ["Não conforme", "danger"])}{r.nc && <span className="mono">{r.nc}</span>}</li>))}</ul>
        : <p className="pcv-help">Nenhuma etapa inspecionada nesta OS.</p>}
      {nova ? <div className="pcv-form">
        {Select && <Select label="Etapa inspecionada" value={nova.etapa} onChange={(e) => setNova({ ...nova, etapa: e.target.value })} options={[{ value: "", label: "Escolha" }].concat(etapas.map((e) => ({ value: e.nome, label: e.nome })))} />}
        <div className="pcv-mot" role="radiogroup" aria-label="Resultado da inspeção">{[["aprovada", "Aprovada"], ["nao conforme", "Não conforme"]].map(([v, l]) => <label key={v}><input type="radio" name="resultado" checked={nova.resultado === v} onChange={() => setNova({ ...nova, resultado: v })} /> {l}</label>)}</div>
        {nova.resultado !== "aprovada" && <>
          {Select && <Select label="Motivo" value={nova.motivo} onChange={(e) => setNova({ ...nova, motivo: e.target.value })} options={[{ value: "", label: "Escolha" }].concat(MOTIVOS.map((m) => ({ value: m, label: m })))} />}
          {Input && <Input label="O que foi visto (opcional)" value={nova.obs} onChange={(e) => setNova({ ...nova, obs: e.target.value })} />}
          <label className="pcv-ql-foto">Foto do defeito (obrigatória)<input type="file" accept="image/*" onChange={(e) => setNova({ ...nova, foto: e.target.files && e.target.files[0] ? e.target.files[0].name : "" })} /></label>
          <p className="pcv-help">Abre uma não conformidade. Para refazer a peça, use também "Reprovar no controle", que manda a etapa de origem para retrabalho.</p>
        </>}
        <div className="pcv-bar"><Button onClick={() => setNova(null)}>Cancelar</Button><Button variant="primary" disabled={!podeSalvar} onClick={inspecionar}>Registrar inspeção</Button></div>
      </div> : Button && <Button disabled={!etapas.length} onClick={() => setNova({ etapa: "", resultado: "aprovada", motivo: "", obs: "", foto: "" })}>Inspecionar etapa</Button>}
      <h4 className="pcv-eyebrow">Não conformidades</h4>
      {ncsDe(os.id).length ? <ul className="pcv-ncs">{ncsDe(os.id).map((n) => <CartaoNc key={n.id} n={n} D={D} />)}</ul> : <p className="pcv-help">Nenhuma não conformidade nesta OS.</p>}
      <p className="pcv-help">Só quem tem o papel de qualidade inspeciona e encerra. A inspeção não se apaga: para corrigir, inspecione de novo.</p>
    </div>
  );
}

// Painel: não conformidades abertas ou em ação. Sem nenhuma, o widget não aparece.
function NcsPainel({ onAbrir }) {
  const D = P(); const { Widget } = DS();
  const lst = abertas(); if (!lst.length || !Widget) return null;
  return (
    <Widget title="Não conformidades abertas" note="cada uma fica aqui até a ação corretiva ser verificada" flush>
      <ul className="pcv-fila-arte">{lst.map((n) => (
        <li key={n.id}><div><b className="mono">{n.id}</b> · {n.os} · {n.etapa}<small>{n.motivo}{n.acao ? " · ação com " + n.acao.resp + " até " + D.d2(n.acao.prazo) : " · sem ação corretiva"}</small></div>
          {B(EST[n.estado])}<button className="pcv-link" onClick={() => onAbrir(n.os)}>Abrir</button></li>))}</ul>
    </Widget>
  );
}

window.PcvQualidade = { AbaQualidade, NcsPainel, ncsDe };
})();
