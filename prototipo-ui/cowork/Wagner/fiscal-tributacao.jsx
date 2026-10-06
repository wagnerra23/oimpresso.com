// fiscal-tributacao.jsx — Fiscal › Tributação · window.FxTributacaoPage
// ÂNCORA DE IMPLEMENTAÇÃO (lida 2026-10-06 @3de6bdc5bf8b): a tela JÁ EXISTE em produção — /nfe-brasil/tributacao
//   resources/js/Pages/NfeBrasil/Tributacao/{Index,RegraForm,ConfigDefault,ImportCsv}.tsx (+ charter + casos cada).
//   Motor: Modules/NfeBrasil/Services/MotorTributarioService.php. Este arquivo é ALVO de layout/fluxo, não tela nova.
// ALVO DE FORMA da Tributação por decisão [W] 2026-10-06 (D-ANCORA). Decisões D-SIM · D-UF · D-IA · D-OPERACAO · D-CONTADOR no playbook fiscal.
const { useState: useStateTr, useMemo: useMemoTr, useEffect: useEffectTr, useRef: useRefTr } = React;
const trU = () => window.FxUI;
const TrI = ({ name, size = 13 }) => { const F = (window.I || {})[name]; return F ? <span className="fx-i" aria-hidden="true" style={{ display: "inline-flex" }}><F size={size} /></span> : null; };
const trPct = (v) => v == null ? "—" : (v * 100).toLocaleString("pt-BR", { maximumFractionDigits: 2 }) + "%";
const trBrl = (v) => "R$ " + Number(v || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const trToast = (m, t) => (window.fxToast ? window.fxToast(m, t) : null);
const TR_ORIGEM = "SP";

const TR_NIVEIS = [
  { n: 1, t: "Exceção do produto", d: "Amarrada direto no produto. Só pra casos fora da curva." },
  { n: 2, t: "Exceção NCM + UF", d: "Pra outro estado: interestadual, FCP, DIFAL, ST." },
  { n: 3, t: "Exceção NCM", d: "Vale pra qualquer destino quando não há exceção por UF." },
  { n: 4, t: "Regra geral da operação", d: "O que a operação (venda, devolução…) aplica quando nenhuma exceção casa." },
];

const TR_REGRAS = [
  { id: "r1", ncm: "39219019", desc: "Lona e laminados plásticos", op: "Venda", ufD: null, cfop: "5102", csosn: "102", icms: 0, pis: 0, cofins: 0, ipi: 0, cClass: "000001", ibs: 0.001, cbs: 0.009, prod: 38, nivel: 3, vigDe: "01/01/2026", vigAte: null, fonte: "CSV" },
  { id: "r2", ncm: "39219019", desc: "Lona e laminados plásticos", op: "Venda", ufD: "RJ", cfop: "6102", csosn: "102", icms: 0, pis: 0, cofins: 0, ipi: 0, cClass: "000001", ibs: 0.001, cbs: 0.009, prod: 38, nivel: 2, vigDe: "01/01/2026", vigAte: null, fonte: "Manual", fcp: 0.02 },
  { id: "r3", ncm: "49119900", desc: "Impressos gráficos diversos", op: "Venda", ufD: null, cfop: "5101", csosn: "101", icms: 0.0186, reducao: 0.3333, cBenef: "informado pelo contador (tabela da UF)", pis: 0, cofins: 0, ipi: 0, cClass: null, ibs: 0, cbs: 0, prod: 112, nivel: 3, vigDe: "01/03/2025", vigAte: null, fonte: "CSV", alerta: "Sem cClassTrib — necessário se mudar pro regime normal e, no Simples, a partir de 2027" },
  { id: "r4", ncm: "94056000", desc: "Letreiros e placas luminosas", op: "Venda", ufD: null, cfop: "5405", csosn: "500", icms: 0, pis: 0, cofins: 0, ipi: 0.0325, cClass: "000001", ibs: 0.001, cbs: 0.009, prod: 9, nivel: 3, vigDe: "01/01/2026", vigAte: null, fonte: "Manual", mva: 0.4 },
  { id: "r5", ncm: "32151900", desc: "Tintas de impressão", op: "Venda", ufD: "MG", cfop: "6102", csosn: "102", icms: 0, pis: 0, cofins: 0, ipi: 0, cClass: "000001", ibs: 0.001, cbs: 0.009, prod: 14, nivel: 2, vigDe: "01/01/2026", vigAte: "31/12/2026", fonte: "IA aceita" },
  { id: "r6", ncm: "39199090", desc: "Adesivo vinil autoadesivo", op: "Venda", ufD: null, cfop: "5102", csosn: "102", icms: 0, pis: 0, cofins: 0, ipi: 0, cClass: "000001", ibs: 0.001, cbs: 0.009, prod: 26, nivel: 3, vigDe: "01/01/2026", vigAte: null, fonte: "CSV" },
  { id: "r7", ncm: "39219019", desc: "Lona — devolução de venda", op: "Devolução", ufD: null, cfop: "1202", csosn: "900", icms: 0, pis: 0, cofins: 0, ipi: 0, cClass: "000001", ibs: 0.001, cbs: 0.009, prod: 38, nivel: 3, vigDe: "01/01/2026", vigAte: null, fonte: "Manual", espelho: true },
  { id: "r8", ncm: "84433229", desc: "Impressora jato de tinta (revenda importada)", op: "Importação", ufD: null, cfop: "3102", csosn: "900", icms: 0.18, pis: 0.021, cofins: 0.0965, ipi: 0, cClass: "000001", ibs: 0.001, cbs: 0.009, prod: 2, nivel: 3, vigDe: "01/01/2026", vigAte: null, fonte: "Manual", ii: 0.112 },
  { id: "r9", ncm: "48211000", desc: "Etiqueta personalizada — cliente Horizonte", op: "Venda", ufD: null, cfop: "5101", csosn: "103", icms: 0, pis: 0, cofins: 0, ipi: 0, cClass: "000001", ibs: 0.001, cbs: 0.009, prod: 1, nivel: 1, vigDe: "01/06/2026", vigAte: null, fonte: "Manual" },
];
TR_REGRAS.forEach(r => { r.opId = { "Venda": "venda", "Devolução": "devol", "Importação": "import" }[r.op]; });

// Naturezas de operação — porta de entrada (padrão Bling · Tiny/Olist · Conta Azul; Omie chama de "cenário fiscal").
// "?" no CFOP vira 5 (mesma UF), 6 (outra UF) ou 7 (exportação) na hora da nota.
const TR_GERAL = { csosn: "102", icms: 0, pis: 0, cofins: 0, ipi: 0, cClass: "000001", ibs: 0.001, cbs: 0.009 };
const TR_OPS = [
  { id: "venda", nome: "Venda de mercadoria", tipo: "Saída", fin: "1 · normal", cfop: "?102", dest: "Contribuinte (com IE)", docs: "NF-e", geral: TR_GERAL, rev: true },
  { id: "venda-cf", nome: "Venda a consumidor final", tipo: "Saída", fin: "1 · normal", cfop: "?102", dest: "Não contribuinte", docs: "NF-e · NFC-e", geral: TR_GERAL, rev: true, obs: "Partilha do ICMS (DIFAL) quando for pra outra UF" },
  { id: "venda-prod", nome: "Venda de produção própria", tipo: "Saída", fin: "1 · normal", cfop: "?101", dest: "Contribuinte (com IE)", docs: "NF-e", geral: { ...TR_GERAL, csosn: "101", icms: 0.0186 }, rev: false },
  { id: "remessa", nome: "Remessa pra instalação", tipo: "Saída", fin: "1 · normal", cfop: "?949", dest: "Qualquer", docs: "NF-e", geral: { ...TR_GERAL, csosn: "400" }, rev: false, obs: "Sem valor comercial; volta com nota de retorno" },
  { id: "devol", nome: "Devolução de venda", tipo: "Entrada", fin: "4 · devolução", cfop: "?202", dest: "Quem devolveu", docs: "NF-e", geral: { ...TR_GERAL, csosn: "900" }, rev: true, obs: "Copia base e alíquotas da nota de origem" },
  { id: "import", nome: "Compra por importação", tipo: "Entrada", fin: "1 · normal", cfop: "3102", dest: "Fornecedor exterior", docs: "NF-e", geral: { ...TR_GERAL, csosn: "900", icms: 0.18 }, rev: true, obs: "Câmbio da DI/DUIMP gravado na nota" },
  { id: "servico", nome: "Prestação de serviço", tipo: "Saída", fin: "—", cfop: "—", dest: "Tomador", docs: "NFS-e", geral: null, rev: true, obs: "ISS pelo item da LC 116 — ver aba Serviços" },
];

// ICMS por UF — EXEMPLO ilustrativo; no vivo vem de tabela versionada revisada pelo contador.
const TR_UFS = [
  ["AC",.19,0,"N"],["AL",.19,.01,"NE"],["AP",.18,0,"N"],["AM",.20,.02,"N"],["BA",.205,.02,"NE"],["CE",.20,.02,"NE"],["DF",.20,.02,"CO"],
  ["ES",.17,.02,"ES"],["GO",.19,.02,"CO"],["MA",.23,.02,"NE"],["MT",.17,.02,"CO"],["MS",.17,.02,"CO"],["MG",.18,.02,"SE"],["PA",.19,0,"N"],
  ["PB",.20,.02,"NE"],["PR",.195,0,"S"],["PE",.205,.02,"NE"],["PI",.225,.01,"NE"],["RJ",.20,.02,"SE"],["RN",.20,.02,"NE"],["RS",.17,.02,"S"],
  ["RO",.195,.02,"N"],["RR",.20,0,"N"],["SC",.17,0,"S"],["SP",.18,0,"SE"],["SE",.20,.01,"NE"],["TO",.20,.02,"N"],
].map(([uf, interno, fcp, reg]) => ({ uf, interno, fcp, inter: uf === TR_ORIGEM ? null : (["S", "SE"].includes(reg) ? 0.12 : 0.07), st: (uf.charCodeAt(0) + uf.charCodeAt(1)) % 4, vig: "01/04/2026" }));

const TR_CNAE = [
  { cnae: "1813-0/01", desc: "Impressão de material para uso publicitário", principal: true, anexo: "III", doc: "NF-e / NFS-e" },
  { cnae: "3299-0/03", desc: "Fabricação de letras, letreiros e placas", principal: false, anexo: "II", doc: "NF-e" },
  { cnae: "4329-1/01", desc: "Instalação de painéis publicitários", principal: false, anexo: "III", doc: "NFS-e" },
  { cnae: "7319-0/99", desc: "Outras atividades de publicidade", principal: false, anexo: "III", doc: "NFS-e" },
];
const TR_SERV = [
  { item: "24.01", nac: "24.01.01", mun: "São Paulo/SP", cMun: "02498", nbs: "1.1806.00.00", indOp: "100301", desc: "Sinalização visual, banners, adesivos e placas", cnae: "7319-0/99", iss: 0.05, ret: "—", prod: 41 },
  { item: "13.05", nac: "13.05.01", mun: "São Paulo/SP", cMun: "02881", nbs: "1.1806.00.00", indOp: "100301", desc: "Composição gráfica e impressos sob encomenda", cnae: "1813-0/01", iss: 0.05, ret: "—", prod: 57 },
  { item: "14.06", nac: "14.06.01", mun: "Guarulhos/SP", cMun: null, nbs: "1.2205.00.00", indOp: "100301", desc: "Instalação e montagem (fachada, painel)", cnae: "4329-1/01", iss: 0.03, ret: "ISS retido pelo tomador", prod: 8 },
  { item: "17.06", nac: "17.06.01", mun: "São Paulo/SP", cMun: "02496", nbs: "1.1806.00.00", indOp: "100301", desc: "Propaganda e publicidade — criação de arte", cnae: "7319-0/99", iss: 0.05, ret: "IRRF 1,5% (tomador PJ)", prod: 6 },
];
const TR_DEVOL = [
  { orig: "5102", origD: "Venda de mercadoria (SP)", dev: "1202", devD: "Devolução de venda — entrada" },
  { orig: "6102", origD: "Venda de mercadoria (outra UF)", dev: "2202", devD: "Devolução de venda — entrada interestadual" },
  { orig: "5101", origD: "Venda de produção própria", dev: "1201", devD: "Devolução de venda de produção" },
  { orig: "5405", origD: "Venda com ST já retida", dev: "1411", devD: "Devolução de venda com ST" },
  { orig: "1102", origD: "Compra pra revenda (SP)", dev: "5202", devD: "Devolução de compra — saída" },
  { orig: "2102", origD: "Compra pra revenda (outra UF)", dev: "6202", devD: "Devolução de compra — saída interestadual" },
];
const TR_PRODUTOS = [
  { id: "p1", nome: "Lona frontlight 440g (m²)", ncm: "39219019", regra: "r1", valor: 38.9, tipo: "mercadoria" },
  { id: "p2", nome: "Adesivo vinil brilho (m²)", ncm: "39199090", regra: "r6", valor: 42, tipo: "mercadoria" },
  { id: "p3", nome: "Placa ACM 3mm com impressão", ncm: null, regra: null, valor: 310, tipo: "mercadoria", ia: { ncm: "76061290", conf: 0.71, por: "Descrição cita ACM (alumínio composto)" } },
  { id: "p4", nome: "Letreiro luminoso LED", ncm: "94056000", regra: "r4", valor: 1850, tipo: "mercadoria" },
  { id: "p5", nome: "Banner personalizado com arte do cliente", ncm: "49119900", regra: "r3", valor: 120, tipo: "mercadoria", ia: { lc: "24.01", conf: 0.82, por: "Sob encomenda, personalizado pro usuário final → serviço (ISS), não mercadoria" } },
  { id: "p6", nome: "Tinta eco-solvente 1L", ncm: "32151900", regra: null, valor: 189, tipo: "mercadoria" },
  { id: "p7", nome: "Instalação de fachada (hora)", ncm: null, regra: null, valor: 95, tipo: "serviço", lc: "14.06" },
  { id: "p10", nome: "Criação de arte publicitária (hora)", ncm: null, regra: null, valor: 150, tipo: "serviço", lc: "17.06" },
  { id: "p8", nome: "Etiqueta adesiva Horizonte", ncm: "48211000", regra: "r9", valor: 0.42, tipo: "mercadoria" },
  { id: "p9", nome: "Papel couché 150g (resma)", ncm: null, regra: null, valor: 96, tipo: "mercadoria", ia: { ncm: "48101390", conf: 0.88, por: "Papel couché revestido, em folhas" } },
];
const TR_IA = [
  { id: "ia1", tipo: "Natureza", alvo: "Banner personalizado com arte do cliente", sug: "Pedir decisão do contador: ICMS ou ISS", conf: 0.5, risco: "alto", por: "Não há resposta única. A SEFAZ-SP trata placas e banners por encomenda como industrialização, com ICMS (Consulta 10406/2016). A Súmula 156 do STJ manda pro ISS a composição gráfica personalizada sob encomenda. A Jana não escolhe: o contador decide por produto e a decisão fica registrada." },
  { id: "ia2", tipo: "NCM", alvo: "Papel couché 150g (resma)", sug: "NCM 4810.13.90", conf: 0.88, risco: "baixo", por: "Descrição e unidade batem com papel revestido em folhas." },
  { id: "ia3", tipo: "NCM", alvo: "Placa ACM 3mm com impressão", sug: "NCM 7606.12.90", conf: 0.71, risco: "médio", por: "ACM = chapa de alumínio composto. Confiança média: impressão pode mudar a classificação." },
  { id: "ia4", tipo: "Regra", alvo: "NCM 4911.99.00 · 112 produtos", sug: "Preencher cClassTrib 000001 + CST 000", conf: 0.95, risco: "baixo", por: "Regra sem campos da Reforma. Hoje (Simples, 2026) não vai pro XML; fica pronta pra 2027 ou pra troca de regime." },
  { id: "ia5", tipo: "Inconsistência", alvo: "Tinta eco-solvente 1L", sug: "Produto com NCM mas sem regra — usando padrão da empresa", conf: 1, risco: "médio", por: "NCM 3215.19.00 tem regra só pra MG. Pra SP cai no nível 4." },
];

const TR_ABAS = [
  { id: "saude", label: "Saúde fiscal" }, { id: "operacoes", label: "Operações" }, { id: "regras", label: "Exceções" }, { id: "simulador", label: "Simulador" }, { id: "bateria", label: "Bateria de notas" }, { id: "contador", label: "Contador" }, { id: "produtos", label: "Vínculo com produtos" },
  { id: "uf", label: "Por estado" }, { id: "servicos", label: "Serviços · NFS-e" }, { id: "importacao", label: "Importação" },
  { id: "devolucoes", label: "Devoluções" }, { id: "entradas", label: "Entradas" }, { id: "ia", label: "Jana · sugestões" },
];

const TR_ACEITE = "M. Ribeiro (contador) · 02/10/2026";
const TR_SAUDE = [
  { id: "s0", sev: "bad", t: "NCM padrão da empresa é 00000000", d: "É o valor de exemplo que vem ao criar a configuração — não é NCM válido. Ele é a rede de segurança de toda nota: com ele inválido, a nota volta da SEFAZ.", acao: "Escolher NCM padrão", um: "NCM padrão salvo — vale pra item sem NCM, que entra na revisão" },
  { id: "s7", sev: "warn", t: "Cidade do cliente não casou com a tabela IBGE", d: "Quando o nome da cidade não bate, a nota sai com o município da sua empresa no lugar do município do cliente. 14 clientes nessa situação.", acao: "Conferir pelos CEPs", um: "14 cidades conferidas pelos CEPs — 2 clientes sem CEP ficaram pra revisão" },
  { id: "s1", sev: "bad", t: TR_PRODUTOS.filter(p => !p.ncm && p.tipo !== "serviço").length + " produtos sem NCM", d: "Nota com esses itens volta da SEFAZ. " + TR_PRODUTOS.filter(p => !p.ncm && p.tipo !== "serviço" && p.ia).length + " já têm sugestão da IA.", acao: "Revisar sugestões", aba: "produtos", alt: "Usar NCM padrão da empresa e marcar revisão" },
  { id: "s2", sev: "bad", t: "Serviço 14.06 sem código municipal", d: "Guarulhos exige o código junto do nacional — a NFS-e voltaria com E0312.", acao: "Preencher código", aba: "servicos" },
  { id: "s3", sev: "bad", t: "NF-e 8425 rejeitada · cStat 110", d: "Causa cadastral: IE do destinatário inválida no cadastro SP. A IE nova já foi consultada no SINTEGRA.", acao: "Corrigir cadastro e reenviar", um: "IE atualizada e nota reenviada — aguardando retorno da SEFAZ" },
  { id: "s4", sev: "warn", t: "Regra NCM 4911.99.00 sem cClassTrib", d: "112 produtos. Não vai pro XML no Simples em 2026; necessário em 2027 ou se mudar de regime.", acao: "Preencher 000001 · CST 000", um: "Nova versão da regra criada — aguarda aceite do contador" },
  { id: "s5", sev: "warn", t: "2 operações sem aceite do contador", d: "Sem aceite registrado, ninguém responde pela regra se ela estiver errada.", acao: "Pedir aceite", um: "Pedido enviado ao contador — ele aceita sem sair do sistema" },
  { id: "s6", sev: "warn", t: "Certificado A1 vence em 47 dias", d: "Sem certificado nenhuma nota sai. Lembrete automático em 30, 15 e 7 dias.", acao: "Enviar novo certificado", rota: "fiscal-config" },
];
const TR_MOTIVOS = [["Rejeição por cadastro (IE, NCM, endereço)", 38], ["Por que saiu esse imposto?", 24], ["Configuração inicial", 18], ["Certificado", 11], ["Outros", 9]];

function TrSaude({ ir }) {
  const [feito, setFeito] = useStateTr({});
  const abertas = TR_SAUDE.filter(s => !feito[s.id]);
  const resolve = (s, msg, t) => { setFeito({ ...feito, [s.id]: true }); trToast(msg, t); };
  return (
    <div className="trb-saude">
      <section className="fx-card" data-contract="saude-fiscal" aria-label="Pendências que viram rejeição">
        <div className="fx-card-h"><span>Vai virar rejeição se ninguém mexer · {abertas.length}</span><span className="trb-mono">checado a cada cadastro e antes de emitir</span></div>
        {abertas.length === 0 ? <div className="fx-empty" style={{ margin: 14 }}><b>Nada pendente</b><small>Cadastro e regras prontos pra emitir sem rejeição conhecida.</small></div> : (
          <ul className="trb-pend">
            {abertas.map(s => (
              <li key={s.id} data-sev={s.sev}>
                <span className="trb-pend-dot" aria-hidden="true"></span>
                <div><b>{s.t}</b><small>{s.d}</small></div>
                <div className="trb-pend-a">
                  {s.alt && <button className="fx-btn" onClick={() => resolve(s, "NCM padrão da empresa aplicado — itens marcados pra revisão", "warn")}>{s.alt}</button>}
                  <button className="fx-btn primary" onClick={() => s.um ? resolve(s, s.um) : s.rota ? trU().go(s.rota) : ir(s.aba)}>{s.acao}</button>
                </div>
              </li>
            ))}
          </ul>)}
      </section>
      <section className="fx-card" aria-label="Chamados fiscais por motivo">
        <div className="fx-card-h"><span>Chamados fiscais · 30 dias</span><span className="fx-sefaz warn">demonstração</span></div>
        <ul className="trb-motivos">
          {TR_MOTIVOS.map(([m, v]) => <li key={m}><span>{m}</span><span className="trb-bar" aria-hidden="true"><i style={{ width: v + "%" }}></i></span><b className="trb-mono">{v}%</b></li>)}
        </ul>
        <p className="fx-nota-rodape" style={{ padding: "0 16px 14px" }}>A contagem real por motivo vem antes de qualquer automação nova: é ela que diz onde o suporte gasta tempo.</p>
      </section>
    </div>
  );
}

function TrNivel({ n }) { return <span className="trb-nivel" data-n={n} title={TR_NIVEIS[n - 1].t}>N{n}</span>; }

function TrCascade({ regras }) {
  return (
    <ol className="trb-cascade" data-contract="cascade" aria-label="Ordem de busca da regra — a primeira que casar vence">
      {TR_NIVEIS.map(l => (
        <li key={l.n}>
          <div className="trb-cascade-h"><TrNivel n={l.n} /><b>{l.t}</b><span className="trb-mono">{l.n === 4 ? TR_OPS.length : regras.filter(r => r.nivel === l.n).length}</span></div>
          <small>{l.d}</small>
        </li>
      ))}
    </ol>
  );
}

function TrRegraDrawer({ regra, onClose }) {
  const xRef = useRefTr(null);
  useEffectTr(() => {
    if (!regra) return;
    const k = (e) => { if (e.key === "Escape") onClose(); };
    document.addEventListener("keydown", k); xRef.current && xRef.current.focus();
    return () => document.removeEventListener("keydown", k);
  }, [regra]);
  if (!regra) return null;
  const r = regra;
  const prods = TR_PRODUTOS.filter(p => p.regra === r.id || (r.nivel !== 1 && p.ncm === r.ncm));
  return (
    <>
      <div className="fx-scrim" onClick={onClose}></div>
      <aside className="fx-drawer" role="dialog" aria-modal="true" aria-labelledby="trb-dr-t">
        <div className="fx-dr-h">
          <div>
            <TrNivel n={r.nivel} />
            <h2 id="trb-dr-t">{r.ncm === "—" ? r.desc : "NCM " + r.ncm}</h2>
            <p>{r.ncm === "—" ? "Usado quando nenhuma regra casa" : r.desc} · {r.op} · {TR_ORIGEM} → {r.ufD || "qualquer UF"}</p>
          </div>
          <button ref={xRef} className="fx-dr-x" onClick={onClose} aria-label="Fechar">×</button>
        </div>
        <div className="fx-dr-b">
          {r.alerta && <div className="fx-recipe"><b>{r.alerta}</b></div>}
          <section className="fx-sec"><h3>Quando vale</h3>
            <dl className="fx-kv"><dt>Operação</dt><dd>{r.op}{r.espelho ? " · copia alíquotas da nota de origem" : ""}</dd><dt>UF origem → destino</dt><dd>{TR_ORIGEM} → {r.ufD || "qualquer"}</dd><dt>Vigência</dt><dd>{r.vigDe} até {r.vigAte || "sem fim"}</dd><dt>Origem da regra</dt><dd>{r.fonte}</dd></dl>
          </section>
          <section className="fx-sec"><h3>ICMS</h3>
            <dl className="fx-kv"><dt>CFOP</dt><dd className="trb-mono">{r.cfop}</dd><dt>CSOSN (Simples)</dt><dd className="trb-mono">{r.csosn}</dd><dt>Alíquota</dt><dd>{trPct(r.icms)}</dd>{r.mva != null && <><dt>MVA (ST)</dt><dd>{trPct(r.mva)}</dd></>}{r.fcp != null && <><dt>FCP</dt><dd>{trPct(r.fcp)}</dd></>}</dl>
          </section>
          <section className="fx-sec"><h3>Federais</h3>
            <dl className="fx-kv"><dt>PIS</dt><dd>{trPct(r.pis)}</dd><dt>COFINS</dt><dd>{trPct(r.cofins)}</dd><dt>IPI</dt><dd>{trPct(r.ipi)}</dd>{r.ii != null && <><dt>Imposto de importação</dt><dd>{trPct(r.ii)}</dd></>}</dl>
          </section>
          <section className="fx-sec"><h3>Reforma tributária</h3>
            <dl className="fx-kv"><dt>cClassTrib</dt><dd className="trb-mono">{r.cClass || "não preenchido"}</dd><dt>IBS</dt><dd>{trPct(r.ibs)}</dd><dt>CBS</dt><dd>{trPct(r.cbs)}</dd></dl>
          </section>
          <section className="fx-sec"><h3>Produtos que usam esta regra · {r.prod}</h3>
            <div className="fx-list">{prods.length ? prods.map(p => <div className="fx-list-i" key={p.id}><b>{p.nome}</b><span className="mono">{p.ncm || "sem NCM"}</span></div>) : <div className="fx-list-i">Vínculo automático pelo NCM — nenhum produto da amostra.</div>}</div>
          </section>
        </div>
        <div className="fx-dr-f">
          <button className="fx-btn primary" onClick={() => trToast("Nova versão da regra criada — a atual encerra na véspera")}><TrI name="pencil" /> Editar (nova vigência)</button>
          <button className="fx-btn" onClick={() => trToast("Duplicada como rascunho pra outra UF")}><TrI name="copy" /> Duplicar pra UF</button>
          <button className="fx-btn danger" onClick={() => trToast("Vigência encerrada hoje", "warn")}>Encerrar vigência</button>
        </div>
      </aside>
    </>
  );
}

function TrRegras({ onOpen }) {
  const [q, setQ] = useStateTr(""); const [op, setOp] = useStateTr("todas");
  const rows = TR_REGRAS.filter(r => (op === "todas" || r.op === op || r.op === "Todas") && (r.ncm + r.desc + r.cfop).toLowerCase().includes(q.toLowerCase()));
  return (
    <>
      <TrCascade regras={TR_REGRAS} />
      <div className="fx-toolbar">
        <label className="fx-search"><TrI name="search" /><input value={q} onChange={e => setQ(e.target.value)} placeholder="NCM, descrição ou CFOP" aria-label="Buscar regra" /></label>
        <select className="fx-select" value={op} onChange={e => setOp(e.target.value)} aria-label="Operação">
          <option value="todas">Todas as operações</option><option>Venda</option><option>Devolução</option><option>Importação</option>
        </select>
        <button className="fx-btn" onClick={() => trToast("Modelo CSV: ncm, uf_origem, uf_destino, cfop, csosn|cst, alíquotas, ibs, cbs")}><TrI name="upload" /> Importar CSV</button>
        <button className="fx-btn primary" onClick={() => onOpen(TR_REGRAS[0])}><TrI name="plus" /> Nova regra</button>
      </div>
      <div className="fx-table" data-contract="regras">
        <table>
          <thead><tr><th style={{ width: 56 }}>Nível</th><th>NCM · descrição</th><th>Operação</th><th>Destino</th><th>CFOP</th><th>CSOSN</th><th style={{ textAlign: "right" }}>ICMS</th><th style={{ textAlign: "right" }}>IPI</th><th style={{ textAlign: "right" }}>IBS / CBS</th><th>Vigência</th><th style={{ textAlign: "right" }}>Produtos</th></tr></thead>
          <tbody>
            {rows.map(r => (
              <tr key={r.id} onClick={() => onOpen(r)} tabIndex={0} onKeyDown={e => e.key === "Enter" && onOpen(r)}>
                <td><TrNivel n={r.nivel} /></td>
                <td className="cli"><b className="trb-mono">{r.ncm}</b><div>{r.desc}</div>{r.alerta && <div className="fx-rej">{r.alerta}</div>}</td>
                <td>{r.op}</td><td className="trb-mono">{r.ufD || "todas"}</td><td className="trb-mono">{r.cfop}</td><td className="trb-mono">{r.csosn}</td>
                <td className="val">{trPct(r.icms)}</td><td className="val">{trPct(r.ipi)}</td>
                <td className="val">{r.ibs || r.cbs ? trPct(r.ibs) + " / " + trPct(r.cbs) : <span className="fx-sefaz bad">vazio</span>}</td>
                <td className="trb-mono">{r.vigDe}{r.vigAte ? " – " + r.vigAte : ""}</td>
                <td className="val">{r.prod}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="fx-decisao"><b>Proposta: exceção pertence a uma operação e tem vigência</b><small>O motor do main hoje busca só por NCM + UF e cai num padrão único da empresa. A proposta troca o padrão único pela regra geral de cada operação e dá a cada exceção <i>válida de/até</i>: a nota usa a versão vigente na data da emissão. Editar nunca sobrescreve, gera nova versão.</small></div>
    </>
  );
}

// Cálculo puro do simulador — o mesmo que a bateria de notas usa (UC-NFTR-08: um cálculo só).
const TR_NOTA_ORIGEM = { num: "8427", data: "18/05/2026", pid: "p1", qtd: 10, uf: "SP", csosn: "102", icms: 0, ipi: 0, cClass: "000001", ibs: 0.001, cbs: 0.009 };
const TR_REGIAO = Object.fromEntries([["AC","N"],["AL","NE"],["AP","N"],["AM","N"],["BA","NE"],["CE","NE"],["DF","CO"],["ES","ES"],["GO","CO"],["MA","NE"],["MT","CO"],["MS","CO"],["MG","SE"],["PA","N"],["PB","NE"],["PR","S"],["PE","NE"],["PI","NE"],["RJ","SE"],["RN","NE"],["RS","S"],["RO","N"],["RR","N"],["SC","S"],["SP","SE"],["SE","NE"],["TO","N"]]);
// Interestadual (Resolução do Senado 22/1989): de S/SE (exceto ES) para N/NE/CO/ES = 7%; demais = 12%.
const trInter = (o, d) => (["S", "SE"].includes(TR_REGIAO[o]) && ["N", "NE", "CO", "ES"].includes(TR_REGIAO[d])) ? 0.07 : 0.12;
const TR_EMITENTES = [{ id: "matriz", nome: "Matriz · São Paulo/SP", uf: "SP" }, { id: "filial", nome: "Filial · Curitiba/PR", uf: "PR" }];
// Fator R (LC 123/2006, art. 18 §5º-J/§5º-M): folha 12m ÷ receita bruta 12m ≥ 28% → Anexo III; abaixo → Anexo V.
const trFatorR = (folha, receita) => { const r = receita > 0 ? folha / receita : 0; return { r, anexo: r >= 0.28 ? "III" : "V" }; };
function trCalcular({ opId, pid, qtd, uf, dest, regime, emitente = "matriz", tomador = "pj" }) {
  const orig = (TR_EMITENTES.find(e => e.id === emitente) || TR_EMITENTES[0]).uf;
  const op = TR_OPS.find(o => o.id === opId); const p = TR_PRODUTOS.find(x => x.id === pid);
  const base = p.valor * qtd; const linhas = []; const avisos = [];
  const ext = uf === "EX"; const interna = uf === orig; const ufI0 = TR_UFS.find(u => u.uf === uf); const ufI = ufI0 && { ...ufI0, inter: interna ? null : trInter(orig, uf) };
  const sufixo = ext ? "7" : interna ? "5" : "6";
  if (opId === "servico") {
    const s = TR_SERV.find(x => x.item === p.lc);
    if (!s) return { doc: "NFS-e", passos: [], regra: null, cfop: "—", linhas, total: 0, base, bloqueio: "Serviço sem item da LC 116", avisos };
    const v = base * s.iss; linhas.push({ t: "ISS · " + s.mun, b: base, a: s.iss, v, obs: s.ret });
    if (/retido/i.test(s.ret)) avisos.push("ISS retido pelo tomador — sai do valor a receber");
    if (tomador === "pj") {
      if (regime === "simples") avisos.push("Prestador no Simples: sem retenção de PIS/COFINS/CSLL na fonte — confirmar com o contador");
      else { const csrf = base * 0.0465;
        if (csrf > 10) linhas.push({ t: "Retenção PIS/COFINS/CSLL", b: base, a: 0.0465, v: csrf, obs: "Lei 10.833/2003, art. 30 — retida pelo tomador PJ" });
        else avisos.push("Retenção PIS/COFINS/CSLL dispensada: valor até R$ 10,00 (Lei 10.833/2003, art. 31 §3º)"); }
      if (/IRRF/.test(s.ret)) { const ir = base * 0.015; if (ir > 10) linhas.push({ t: "Retenção IRRF", b: base, a: 0.015, v: ir, obs: "tomador PJ" }); else avisos.push("IRRF dispensado: até R$ 10,00"); }
    }
    return { doc: "NFS-e", passos: [{ n: 4, ok: true, txt: "Item LC 116 " + s.item + " · código nacional " + s.nac + " · " + s.mun }], regra: { iss: s.iss }, cfop: "—", linhas, total: v, base, avisos };
  }
  if (opId === "devol") {
    const o = TR_NOTA_ORIGEM;
    if (pid !== o.pid) return { doc: "NF-e", passos: [], regra: null, cfop: "—", linhas, total: 0, base, bloqueio: "Produto não está na nota de origem " + o.num, avisos };
    if (qtd > o.qtd) return { doc: "NF-e", passos: [], regra: null, cfop: "—", linhas, total: 0, base, bloqueio: "Devolução de " + qtd + " passa da quantidade vendida (" + o.qtd + ")", avisos };
    const cf = op.cfop.replace("?", o.uf === TR_ORIGEM ? "1" : "2");
    linhas.push({ t: "ICMS", b: base, a: o.icms, v: base * o.icms, obs: "CSOSN " + o.csosn + " copiado da NF " + o.num + " de " + o.data });
    if (regime !== "simples") linhas.push({ t: "CBS", b: base, a: o.cbs, v: base * o.cbs, obs: "da nota de origem" }, { t: "IBS", b: base, a: o.ibs, v: base * o.ibs });
    avisos.push("Finalidade 4 · refNFe = chave da NF " + o.num + " · " + qtd + " de " + o.qtd + " unidades");
    return { doc: "NF-e", passos: [{ n: 4, ok: true, txt: "Copiado da nota de origem " + o.num + " (não usa a regra de hoje)" }], regra: { csosn: o.csosn }, cfop: cf, linhas, total: linhas.reduce((s, l) => s + l.v, 0), base, avisos };
  }
  const exId = opId.indexOf("venda") === 0 ? "venda" : opId;
  const ov = p.regra && TR_REGRAS.find(r => r.id === p.regra && r.nivel === 1);
  const daOrigem = (r) => (r.ufO || TR_ORIGEM) === orig;
  const n2 = p.ncm && !ext && TR_REGRAS.find(r => r.ncm === p.ncm && r.ufD === uf && r.opId === exId && daOrigem(r));
  const n3 = p.ncm && TR_REGRAS.find(r => r.ncm === p.ncm && !r.ufD && r.opId === exId && daOrigem(r));
  const n4 = op.geral ? { ...op.geral, cfop: op.cfop, geral: true, nivel: 4 } : null;
  const passos = [
    { n: 1, ok: !!ov, txt: ov ? "Exceção encontrada no produto" : "Produto sem exceção" },
    { n: 2, ok: !ov && !!n2, txt: !p.ncm ? "Pulado — produto sem NCM" : n2 ? "Exceção NCM " + p.ncm + " → " + uf : "Sem exceção NCM " + p.ncm + " pra " + uf },
    { n: 3, ok: !ov && !n2 && !!n3, txt: !p.ncm ? "Pulado — produto sem NCM" : n3 ? "Exceção NCM " + p.ncm : "Sem exceção pro NCM" },
    { n: 4, ok: !ov && !n2 && !n3 && !!p.ncm, txt: "Regra geral · " + op.nome + (orig !== TR_ORIGEM ? " (as exceções cadastradas são da origem " + TR_ORIGEM + ")" : "") },
  ];
  const doc = opId === "venda-cf" && dest === "consumidor" && interna ? "NFC-e" : "NF-e";
  if (!p.ncm) return { doc, passos, regra: null, cfop: "—", linhas, total: 0, base, bloqueio: "Produto sem NCM — NF-e de mercadoria não sai sem NCM", avisos };
  const regra = ov || n2 || n3 || n4;
  const cfop = regra.geral ? regra.cfop.replace("?", sufixo) : regra.cfop.replace(/^[567]/, sufixo);
  const contrib = dest === "contribuinte";
  if (ext) {
    linhas.push({ t: "ICMS", b: base, a: 0, v: 0, obs: "Exportação — imune" });
    if (regime !== "simples") linhas.push({ t: "PIS / COFINS", b: base, a: 0, v: 0, obs: "Exportação — não incidem" });
    linhas.push({ t: "CBS / IBS", b: base, a: 0, v: 0, obs: "Exportação — imune" });
    avisos.push("Exige dados de exportação no XML (país, moeda, registro)");
    return { doc: "NF-e", passos, regra, cfop, linhas, total: 0, base, avisos, nivel: regra.nivel };
  }
  if (opId === "remessa") {
    linhas.push({ t: "ICMS", b: base, a: 0, v: 0, obs: "CSOSN " + regra.csosn + " — remessa sem valor comercial; volta com nota de retorno" });
    return { doc: "NF-e", passos, regra, cfop, linhas, total: 0, base, avisos: ["Controlar o retorno (saldo em aberto)"], nivel: regra.nivel };
  }
  if (regime === "simples") {
    linhas.push({ t: "ICMS", b: base, a: null, v: 0, obs: "CSOSN " + regra.csosn + " — recolhido no DAS, sem destaque" });
    if (regra.ipi) linhas.push({ t: "IPI", b: base, a: null, v: 0, obs: "Simples — IPI dentro do DAS, sem destaque" });
    if (!interna && !contrib) avisos.push(ufI0 && ufI0.difalSimples ? "DIFAL: " + uf + " cobra do Simples por lei própria (marcado pelo contador) — gerar guia" : "Simples não recolhe DIFAL na venda a consumidor de outra UF (STF ADI 5.464). O contador marca na aba Por estado se " + uf + " cobra por lei própria (STF Tema 1284)");
  } else {
    const a = interna ? ufI.interno : ufI.inter; const bIcms = regra.reducao ? base * (1 - regra.reducao) : base;
    linhas.push({ t: "ICMS", b: bIcms, a, v: bIcms * a, obs: regra.reducao ? "Base reduzida em " + trPct(regra.reducao) + " · cBenef " + regra.cBenef : "" });
    if (regra.reducao) avisos.push("Benefício fiscal: o XML exige o cBenef da UF neste item");
    if (!interna && !contrib) { linhas.push({ t: "DIFAL (destino)", b: base, a: ufI.interno - ufI.inter, v: base * (ufI.interno - ufI.inter), obs: "Não contribuinte em outra UF — base simples (conferir base dupla da UF)" });
      if (ufI.fcp) linhas.push({ t: "FCP " + uf, b: base, a: ufI.fcp, v: base * ufI.fcp }); }
    linhas.push({ t: "PIS", b: base, a: 0.0065, v: base * 0.0065, obs: "Lucro presumido" }, { t: "COFINS", b: base, a: 0.03, v: base * 0.03 });
    if (regra.ipi) linhas.push({ t: "IPI", b: base, a: regra.ipi, v: base * regra.ipi });
  }
  if (regra.mva != null) {
    if (regra.csosn === "500" && regime === "simples") linhas.push({ t: "ICMS-ST", b: base, a: null, v: 0, obs: "CSOSN 500 — ST já retida pelo fornecedor na compra" });
    else if (!contrib) linhas.push({ t: "ICMS-ST", b: base, a: null, v: 0, obs: "Consumidor final — não há ST a reter" });
    else { const bst = base * (1 + (regime === "simples" ? 0 : (regra.ipi || 0))) * (1 + regra.mva); const proprio = regime === "simples" ? 0 : base * (interna ? ufI.interno : ufI.inter);
      linhas.push({ t: "ICMS-ST", b: bst, a: ufI.interno, v: Math.max(0, bst * ufI.interno - proprio), obs: "Base = (valor + IPI) × (1 + MVA " + trPct(regra.mva) + ") − ICMS próprio" }); }
  }
  if (regime === "simples") linhas.push({ t: "CBS / IBS", b: base, a: null, v: 0, obs: "Simples não destaca em 2026 — previsto pra 2027" });
  else linhas.push({ t: "CBS", b: base, a: regra.cbs, v: base * regra.cbs, obs: "Ano-teste 2026 · CST 000 · cClassTrib " + (regra.cClass || "—") }, { t: "IBS", b: base, a: regra.ibs, v: base * regra.ibs });
  return { doc, passos, regra, cfop, linhas, total: linhas.reduce((s, l) => s + l.v, 0), base, avisos, nivel: regra.nivel, orig };
}
function trImportar(v) {
  const va = (v.usd + v.frete + v.seguro) * v.ptax;
  const ii = va * v.ii, ipi = (va + ii) * v.ipi, pis = va * v.pis, cof = va * v.cofins;
  const bIcms = (va + ii + ipi + pis + cof + v.desp) / (1 - v.icms), icms = bIcms * v.icms;
  return { va, ii, ipi, pis, cof, bIcms, icms, custo: va + ii + ipi + pis + cof + icms + v.desp };
}
const TR_IMPORT_PADRAO = { usd: 4200, ptax: 5.42, frete: 380, seguro: 42, ii: 0.112, ipi: 0.0, pis: 0.021, cofins: 0.0965, icms: 0.18, desp: 154.23 };

function TrSimulador() {
  const [pid, setPid] = useStateTr("p1"); const [uf, setUf] = useStateTr("RJ"); const [dest, setDest] = useStateTr("contribuinte");
  const [regime, setRegime] = useStateTr("simples"); const [qtd, setQtd] = useStateTr(10); const [opId, setOpId] = useStateTr("venda");
  const [emitente, setEmitente] = useStateTr("matriz"); const [tomador, setTomador] = useStateTr("pj");
  const r = trCalcular({ opId, pid, qtd, uf, dest, regime, emitente, tomador });
  const prodOpts = TR_PRODUTOS.filter(x => opId === "servico" ? x.tipo === "serviço" : x.tipo === "mercadoria");
  const trocaOp = (v) => { setOpId(v); const lista = TR_PRODUTOS.filter(x => v === "servico" ? x.tipo === "serviço" : x.tipo === "mercadoria"); if (!lista.find(x => x.id === pid)) setPid(lista[0].id); };
  return (
    <div className="trb-sim" data-contract="simulador">
      <div className="fx-card">
        <div className="fx-card-h"><span>Cenário</span><span className="fx-sefaz ok">{r.doc}</span></div>
        <div className="trb-form">
          <label className="trb-f"><span>Emitente</span><select className="fx-select" value={emitente} onChange={e => setEmitente(e.target.value)}>{TR_EMITENTES.map(x => <option key={x.id} value={x.id}>{x.nome}</option>)}</select></label>
          <label className="trb-f"><span>Operação</span><select className="fx-select" value={opId} onChange={e => trocaOp(e.target.value)}>{TR_OPS.filter(o => o.id !== "import").map(o => <option key={o.id} value={o.id}>{o.nome}</option>)}</select></label>
          <label className="trb-f"><span>{opId === "servico" ? "Serviço" : "Produto"}</span><select className="fx-select" value={pid} onChange={e => setPid(e.target.value)}>{prodOpts.map(x => <option key={x.id} value={x.id}>{x.nome}{x.tipo === "mercadoria" && !x.ncm ? " (sem NCM)" : ""}</option>)}</select></label>
          <label className="trb-f"><span>Quantidade</span><input className="fx-select" type="number" min="1" value={qtd} onChange={e => setQtd(Math.max(1, +e.target.value || 1))} /></label>
          <label className="trb-f"><span>UF destino</span><select className="fx-select" value={uf} onChange={e => setUf(e.target.value)}>{TR_UFS.map(u => <option key={u.uf} value={u.uf}>{u.uf}</option>)}<option value="EX">EX · exterior</option></select></label>
          {opId === "servico" ? <label className="trb-f"><span>Tomador</span><select className="fx-select" value={tomador} onChange={e => setTomador(e.target.value)}><option value="pj">Pessoa jurídica</option><option value="pf">Pessoa física</option></select></label>
          : <label className="trb-f"><span>Destinatário</span><select className="fx-select" value={dest} onChange={e => setDest(e.target.value)}><option value="contribuinte">Contribuinte (com IE)</option><option value="consumidor">Consumidor final / sem IE</option></select></label>}
          <label className="trb-f"><span>Regime</span><select className="fx-select" value={regime} onChange={e => setRegime(e.target.value)}><option value="simples">Simples Nacional (atual)</option><option value="normal">Lucro presumido (e se?)</option></select></label>
        </div>
        <p className="fx-nota-rodape" style={{ padding: "0 16px 12px" }}>Importação tem cálculo próprio (câmbio da DI): aba Importação.</p>
      </div>
      <div className="fx-card">
        <div className="fx-card-h"><span>Como a regra foi achada</span><span className="trb-mono">CFOP {r.cfop}</span></div>
        <ol className="trb-trace">
          {r.passos.map(s => <li key={s.n} data-ok={s.ok ? "true" : "false"}><TrNivel n={s.n} /><span>{s.txt}</span>{s.ok && <b>usada</b>}</li>)}
        </ol>
        {r.bloqueio && <div className="fx-recipe" style={{ margin: "0 16px 14px" }}><b>{r.bloqueio}</b>{/NCM/.test(r.bloqueio) && <><ol><li>Aceitar a sugestão da Jana (aba Vínculo)</li><li>Ou usar o NCM padrão da empresa e marcar pra revisão</li></ol><button className="fx-btn" style={{ alignSelf: "flex-start" }} onClick={() => trToast("NCM padrão da empresa aplicado — produto entrou na Saúde fiscal pra revisão", "warn")}>Usar NCM padrão e marcar revisão</button></>}</div>}
        {r.avisos.length > 0 && <ul className="trb-avisos">{r.avisos.map(a => <li key={a}>{a}</li>)}</ul>}
      </div>
      {!r.bloqueio && (
        <div className="fx-table trb-sim-res">
          <table>
            <thead><tr><th>Tributo</th><th style={{ textAlign: "right" }}>Base</th><th style={{ textAlign: "right" }}>Alíquota</th><th style={{ textAlign: "right" }}>Valor</th><th>Observação</th></tr></thead>
            <tbody>
              {r.linhas.map(l => <tr key={l.t}><td><b>{l.t}</b></td><td className="val">{trBrl(l.b)}</td><td className="val">{l.a == null ? "—" : trPct(l.a)}</td><td className="val">{trBrl(l.v)}</td><td>{l.obs || ""}</td></tr>)}
              <tr className="trb-total"><td><b>Total destacado</b></td><td className="val">{trBrl(r.base)}</td><td></td><td className="val"><b>{trBrl(r.total)}</b></td><td></td></tr>
            </tbody>
          </table>
          <p className="fx-nota-rodape" style={{ padding: "10px 14px" }}>Prévia: usa o mesmo motor da emissão, com o cadastro de agora. Se o cadastro do produto ou do cliente mudar, a nota muda junto. Não é garantia do valor que a SEFAZ vai validar.</p>
        </div>
      )}
    </div>
  );
}

// Bateria de notas — cenários com o esperado calculado à parte (não pelo simulador).
const TR_CENARIOS = [
  { id: "C01", nome: "NFC-e no balcão · lona · consumidor SP", ctx: { opId: "venda-cf", pid: "p1", qtd: 2, uf: "SP", dest: "consumidor", regime: "simples" }, esp: { doc: "NFC-e", nivel: 3, cfop: "5102", total: 0 } },
  { id: "C02", nome: "NF-e · lona pra contribuinte no RJ", ctx: { opId: "venda", pid: "p1", qtd: 10, uf: "RJ", dest: "contribuinte", regime: "simples" }, esp: { doc: "NF-e", nivel: 2, cfop: "6102" } },
  { id: "C03", nome: "NF-e · tinta pra contribuinte em MG", ctx: { opId: "venda", pid: "p6", qtd: 3, uf: "MG", dest: "contribuinte", regime: "simples" }, esp: { nivel: 2, cfop: "6102" } },
  { id: "C04", nome: "NF-e · tinta em SP (sem exceção → regra geral)", ctx: { opId: "venda", pid: "p6", qtd: 3, uf: "SP", dest: "contribuinte", regime: "simples" }, esp: { nivel: 4, cfop: "5102" } },
  { id: "C05", nome: "Produção própria · banner (impresso)", ctx: { opId: "venda-prod", pid: "p5", qtd: 5, uf: "SP", dest: "contribuinte", regime: "simples" }, esp: { nivel: 3, cfop: "5101", csosn: "101" } },
  { id: "C06", nome: "Produto sem NCM (placa ACM)", ctx: { opId: "venda", pid: "p3", qtd: 1, uf: "SP", dest: "contribuinte", regime: "simples" }, esp: { bloqueio: true } },
  { id: "C07", nome: "Letreiro com ST já retida · Simples", ctx: { opId: "venda", pid: "p4", qtd: 1, uf: "SP", dest: "contribuinte", regime: "simples" }, esp: { linha: ["ICMS-ST", 0], semIpiDestacado: true, total: 0 } },
  { id: "C08", nome: "Letreiro · regime normal · contribuinte RJ (ST)", ctx: { opId: "venda", pid: "p4", qtd: 1, uf: "RJ", dest: "contribuinte", regime: "normal" }, esp: { cfop: "6405", linha: ["ICMS-ST", 312.84] } },
  { id: "C09", nome: "Consumidor final no RJ · regime normal (DIFAL + FCP)", ctx: { opId: "venda-cf", pid: "p1", qtd: 10, uf: "RJ", dest: "consumidor", regime: "normal" }, esp: { doc: "NF-e", linha: ["DIFAL (destino)", 31.12], linha2: ["FCP RJ", 7.78] } },
  { id: "C10", nome: "Exceção do produto (etiqueta Horizonte)", ctx: { opId: "venda", pid: "p8", qtd: 1000, uf: "SP", dest: "contribuinte", regime: "simples" }, esp: { nivel: 1, csosn: "103", cfop: "5101" } },
  { id: "C11", nome: "Regime normal SP · CBS/IBS ano-teste", ctx: { opId: "venda", pid: "p1", qtd: 10, uf: "SP", dest: "contribuinte", regime: "normal" }, esp: { linha: ["CBS", 3.50], linha2: ["IBS", 0.39] } },
  { id: "C12", nome: "Devolução parcial de venda (4 de 10)", ctx: { opId: "devol", pid: "p1", qtd: 4, uf: "SP", dest: "contribuinte", regime: "simples" }, esp: { cfop: "1202", csosn: "102" } },
  { id: "C13", nome: "Devolução maior que a venda (11 de 10)", ctx: { opId: "devol", pid: "p1", qtd: 11, uf: "SP", dest: "contribuinte", regime: "simples" }, esp: { bloqueio: true } },
  { id: "C14", nome: "NFS-e · instalação em Guarulhos (ISS retido)", ctx: { opId: "servico", pid: "p7", qtd: 8, uf: "SP", dest: "contribuinte", regime: "simples" }, esp: { doc: "NFS-e", linha: ["ISS · Guarulhos/SP", 22.80], aviso: "retido" } },
  { id: "C15", nome: "Exportação · lona", ctx: { opId: "venda", pid: "p1", qtd: 10, uf: "EX", dest: "contribuinte", regime: "simples" }, esp: { cfop: "7102", total: 0 } },
  { id: "C16", nome: "Remessa pra instalação · letreiro", ctx: { opId: "remessa", pid: "p4", qtd: 1, uf: "SP", dest: "contribuinte", regime: "simples" }, esp: { cfop: "5949", csosn: "400", total: 0 } },
  { id: "C17", nome: "Consumidor no RJ · Simples (sem DIFAL, ADI 5.464)", ctx: { opId: "venda-cf", pid: "p1", qtd: 10, uf: "RJ", dest: "consumidor", regime: "simples" }, esp: { semLinha: "DIFAL (destino)", aviso: "ADI 5.464" } },
  { id: "C18", nome: "Importação · impressora (câmbio da DI)", imp: true, esp: { icms: 6794.94, va: 25051.24 } },
  { id: "C19", nome: "Letreiro pra consumidor final · regime normal (sem ST)", ctx: { opId: "venda-cf", pid: "p4", qtd: 1, uf: "SP", dest: "consumidor", regime: "normal" }, esp: { linha: ["ICMS-ST", 0] } },
  { id: "C20", nome: "Tinta pra contribuinte na BA · regime normal (7%)", ctx: { opId: "venda", pid: "p6", qtd: 1, uf: "BA", dest: "contribuinte", regime: "normal" }, esp: { linha: ["ICMS", 13.23] } },
  { id: "C21", nome: "NFS-e · arte publicitária pra PJ · regime normal (retenções)", ctx: { opId: "servico", pid: "p10", qtd: 10, uf: "SP", dest: "contribuinte", regime: "normal", tomador: "pj" }, esp: { linha: ["Retenção PIS/COFINS/CSLL", 69.75], linha2: ["Retenção IRRF", 22.50] } },
  { id: "C22", nome: "NFS-e · mesmo serviço · prestador no Simples", ctx: { opId: "servico", pid: "p10", qtd: 10, uf: "SP", dest: "contribuinte", regime: "simples", tomador: "pj" }, esp: { semLinha: "Retenção PIS/COFINS/CSLL", aviso: "Simples" } },
  { id: "C23", nome: "Impressos com benefício (base reduzida) · regime normal", ctx: { opId: "venda-prod", pid: "p5", qtd: 5, uf: "SP", dest: "contribuinte", regime: "normal" }, esp: { linha: ["ICMS", 72.00], aviso: "cBenef" } },
  { id: "C24", nome: "Filial no PR vendendo pra SC (12%, exceções de SP não valem)", ctx: { opId: "venda", pid: "p1", qtd: 10, uf: "SC", dest: "contribuinte", regime: "normal", emitente: "filial" }, esp: { nivel: 4, cfop: "6102", linha: ["ICMS", 46.68] } },
  { id: "C25", nome: "Filial no PR vendendo pra BA (7%)", ctx: { opId: "venda", pid: "p1", qtd: 10, uf: "BA", dest: "contribuinte", regime: "normal", emitente: "filial" }, esp: { linha: ["ICMS", 27.23] } },
  { id: "C26", nome: "Fator R 30% → Anexo III", fator: [84000, 280000], esp: { anexo: "III" } },
  { id: "C27", nome: "Fator R 17,9% → Anexo V", fator: [50000, 280000], esp: { anexo: "V" } },
];
function trRodarCenario(c) {
  const falhas = []; let obtido = {};
  const perto = (a, b) => Math.abs(a - b) < 0.02;
  if (c.fator) { const f = trFatorR(c.fator[0], c.fator[1]); if (f.anexo !== c.esp.anexo) falhas.push("Anexo " + f.anexo + " ≠ " + c.esp.anexo); return { doc: "—", resumo: "Fator R " + trPct(f.r) + " · Anexo " + f.anexo, falhas }; }
  if (c.imp) { const r = trImportar(TR_IMPORT_PADRAO); obtido = { va: r.va, icms: r.icms };
    if (!perto(r.va, c.esp.va)) falhas.push("valor aduaneiro " + r.va.toFixed(2) + " ≠ " + c.esp.va);
    if (!perto(r.icms, c.esp.icms)) falhas.push("ICMS " + r.icms.toFixed(2) + " ≠ " + c.esp.icms);
    return { doc: "NF-e entrada", resumo: "VA " + trBrl(r.va) + " · ICMS " + trBrl(r.icms), falhas };
  }
  const r = trCalcular(c.ctx); const e = c.esp;
  const linha = (t) => r.linhas.find(l => l.t === t);
  if (e.bloqueio != null && !!r.bloqueio !== e.bloqueio) falhas.push(e.bloqueio ? "devia bloquear e não bloqueou" : "bloqueou: " + r.bloqueio);
  if (!e.bloqueio && r.bloqueio) falhas.push("bloqueou: " + r.bloqueio);
  if (e.doc && r.doc !== e.doc) falhas.push("documento " + r.doc + " ≠ " + e.doc);
  if (e.nivel && r.nivel !== e.nivel) falhas.push("nível N" + r.nivel + " ≠ N" + e.nivel);
  if (e.cfop && r.cfop !== e.cfop) falhas.push("CFOP " + r.cfop + " ≠ " + e.cfop);
  if (e.csosn && (!r.regra || r.regra.csosn !== e.csosn)) falhas.push("CSOSN " + (r.regra && r.regra.csosn) + " ≠ " + e.csosn);
  if (e.total != null && !perto(r.total, e.total)) falhas.push("total " + r.total.toFixed(2) + " ≠ " + e.total);
  [e.linha, e.linha2].filter(Boolean).forEach(([t, v]) => { const l = linha(t); if (!l) falhas.push("sem linha " + t); else if (!perto(l.v, v)) falhas.push(t + " " + l.v.toFixed(2) + " ≠ " + v); });
  if (e.semLinha && linha(e.semLinha)) falhas.push("não devia ter " + e.semLinha);
  if (e.semIpiDestacado) { const l = linha("IPI"); if (l && l.v > 0) falhas.push("IPI destacado no Simples"); }
  if (e.aviso && !r.avisos.some(a => a.toLowerCase().includes(e.aviso.toLowerCase())) && !r.linhas.some(l => (l.obs || "").toLowerCase().includes(e.aviso.toLowerCase()))) falhas.push("sem aviso de " + e.aviso);
  const resumo = r.bloqueio ? "bloqueado: " + r.bloqueio : r.doc + " · CFOP " + r.cfop + (r.nivel ? " · N" + r.nivel : "") + " · total " + trBrl(r.total);
  return { doc: r.doc, resumo, falhas };
}
// Mês fechado (exemplo) que o contador confere. Números do protótipo.
const TR_MES = {
  comp: "09/2026", receita12m: 3480000, sublimite: 3600000,
  notas: { emitidas: 412, autorizadas: 398, canceladas: 9, denegadas: 1, rejeitadasPendentes: 4 },
  receitas: [{ t: "Revenda sem ST", v: 61200 }, { t: "Revenda com ST já retida (CSOSN 500)", v: 18400 }, { t: "Produção própria", v: 142800 }, { t: "Serviços (NFS-e)", v: 38600 }],
  receitaMes: 261000, folha12m: 84000, receitaBruta12m: 280000,
  entradas: [{ n: "NF 55.210", forn: "Tintas Sul (PR)", uso: "revenda", inter: true, manif: true }, { n: "NF 9.004", forn: "Máquinas Leste (MG)", uso: "ativo", inter: true, manif: true }, { n: "NF 1.877", forn: "Papelaria Centro (SP)", uso: "consumo", inter: false, manif: false }, { n: "NF 3.391", forn: "Lonas Norte (RJ)", uso: "consumo", inter: true, manif: false }],
  devolucoes: [{ n: "NF 8.901", ref: "8427" }, { n: "NF 8.955", ref: null }],
  retencoes: [{ nfse: "NFS-e 311", retido: 69.75, financeiro: 69.75 }, { nfse: "NFS-e 318", retido: 22.80, financeiro: 0 }],
  semAceite: 3,
};
function trCasosContador(m) {
  const n = m.notas; const somaSeg = m.receitas.reduce((s, r) => s + r.v, 0); const f = trFatorR(m.folha12m, m.receitaBruta12m);
  const difalUso = m.entradas.filter(e => e.inter && (e.uso === "consumo" || e.uso === "ativo"));
  return [
    { id: "CT01", t: "Notas do mês fecham", lei: "escrituração", ok: n.autorizadas + n.canceladas + n.denegadas + n.rejeitadasPendentes === n.emitidas, obt: n.autorizadas + " autorizadas + " + n.canceladas + " canceladas + " + n.denegadas + " denegada + " + n.rejeitadasPendentes + " rejeitadas = " + n.emitidas, acao: "" },
    { id: "CT02", t: "Rejeitadas sem resolver antes de fechar", lei: "rotina", ok: n.rejeitadasPendentes === 0, obt: n.rejeitadasPendentes + " notas rejeitadas sem reenvio nem descarte", acao: "Resolver ou inutilizar a numeração antes de fechar" },
    { id: "CT03", t: "Sublimite do Simples (ICMS/ISS fora do DAS)", lei: "LC 123/2006, art. 13-A", ok: m.receita12m <= m.sublimite, alerta: m.receita12m > m.sublimite * 0.9, obt: trBrl(m.receita12m) + " em 12 meses · " + trPct(m.receita12m / m.sublimite) + " do sublimite de " + trBrl(m.sublimite), acao: "Acima de 90%: avisar o dono. Passou: ICMS e ISS saem do DAS e as notas passam a destacar" },
    { id: "CT04", t: "Segregação de receitas no PGDAS-D", lei: "LC 123/2006, art. 18 §4º", ok: Math.abs(somaSeg - m.receitaMes) < 0.01, obt: m.receitas.map(r => r.t + " " + trBrl(r.v)).join(" · ") + " = " + trBrl(somaSeg), acao: "A receita com ST já retida sai separada, senão paga ICMS duas vezes no DAS" },
    { id: "CT05", t: "Fator R do mês", lei: "LC 123/2006, art. 18 §5º-J", ok: true, obt: trPct(f.r) + " → Anexo " + f.anexo, acao: "" },
    { id: "CT06", t: "DIFAL de compra pra uso, consumo ou ativo", lei: "LC 87/1996 · Simples paga como destinatário", ok: false, alerta: true, obt: difalUso.map(e => e.n + " (" + e.uso + ", " + e.forn + ")").join(" · "), acao: "Compra interestadual que não é revenda gera DIFAL mesmo no Simples — calcular a guia" },
    { id: "CT07", t: "Entradas sem manifestação", lei: "manifestação do destinatário", ok: m.entradas.every(e => e.manif), obt: m.entradas.filter(e => !e.manif).map(e => e.n).join(" · ") + " sem manifestação", acao: "Manifestar na aba Manifesto DF-e" },
    { id: "CT08", t: "Devolução sem nota de origem", lei: "NF-e finalidade 4", ok: m.devolucoes.every(d => d.ref), obt: m.devolucoes.filter(d => !d.ref).map(d => d.n).join(" · ") + " sem refNFe", acao: "Toda devolução cita a chave da nota original" },
    { id: "CT09", t: "Retenções da NFS-e batem com o recebido", lei: "Lei 10.833/2003 · ISS municipal", ok: m.retencoes.every(r => Math.abs(r.retido - r.financeiro) < 0.01), obt: m.retencoes.filter(r => Math.abs(r.retido - r.financeiro) >= 0.01).map(r => r.nfse + ": retido " + trBrl(r.retido) + ", no financeiro " + trBrl(r.financeiro)).join(" · "), acao: "Lançar a retenção no financeiro, senão o recebido fica a menor sem explicação" },
    { id: "CT10", t: "Regras usadas no mês sem aceite", lei: "D-CONTADOR", ok: m.semAceite === 0, obt: m.semAceite + " versões de regra emitiram sem aceite", acao: "Revisar abaixo" },
  ];
}
const TR_CT_ESPERADO = { CT01: true, CT02: false, CT03: true, CT04: true, CT05: true, CT06: false, CT07: false, CT08: false, CT09: false, CT10: false };
const TR_REVISAO = [
  { id: "v1", o: "Template aplicado · Simples SP comércio", de: "—", para: "CSOSN 102 · CFOP 5102/6102 · NCM padrão 3921.90.19", por: "Wagner · 01/10", origem: "template" },
  { id: "v2", o: "Exceção NCM 4911.99.00 → todas as UF", de: "ICMS base cheia", para: "base reduzida 33,33% · cBenef a informar", por: "Larissa · 03/10", origem: "manual" },
  { id: "v3", o: "Natureza do banner personalizado", de: "mercadoria (ICMS)", para: "pedido de decisão", por: "Jana · 04/10", origem: "Jana" },
];
function TrContador() {
  const casos = trCasosContador(TR_MES); const [rev, setRev] = useStateTr({});
  const bateu = casos.filter(c => c.ok === TR_CT_ESPERADO[c.id]).length;
  return (
    <>
      <div className="fx-chips" aria-live="polite"><span className="fx-chip" aria-pressed="true">Fechamento {TR_MES.comp}</span><span className="fx-chip">pendências <b>{casos.filter(c => !c.ok).length}</b></span><span className="fx-chip" data-tone={bateu === casos.length ? null : "bad"}>conferência bate com o esperado <b>{bateu}/{casos.length}</b></span></div>
      <div className="fx-table" data-contract="fechamento-contador">
        <table>
          <thead><tr><th style={{ width: 56 }}>#</th><th>O que o contador confere</th><th>Situação do mês</th><th style={{ width: 120 }}>Resultado</th></tr></thead>
          <tbody>
            {casos.map(c => (
              <tr key={c.id}>
                <td className="trb-mono">{c.id}</td>
                <td className="cli"><b>{c.t}</b><div>{c.lei}</div></td>
                <td>{c.obt}{!c.ok && c.acao && <div className="fx-rej">{c.acao}</div>}</td>
                <td><span className={"fx-sefaz " + (c.ok ? (c.alerta ? "warn" : "ok") : c.alerta ? "warn" : "bad")}>{c.ok ? (c.alerta ? "atenção" : "ok") : c.alerta ? "calcular" : "pendente"}</span></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="fx-card" data-contract="revisao-contador">
        <div className="fx-card-h"><span>Revisão de regras · o que o contador vê pelo link</span><span className="trb-mono">{TR_REVISAO.filter(v => !rev[v.id]).length} pendentes</span></div>
        <div className="fx-table" style={{ border: 0, borderRadius: 0 }}>
          <table>
            <thead><tr><th>Mudança</th><th>De</th><th>Para</th><th>Quem · origem</th><th style={{ width: 210 }}></th></tr></thead>
            <tbody>
              {TR_REVISAO.map(v => (
                <tr key={v.id}>
                  <td><b>{v.o}</b></td><td>{v.de}</td><td>{v.para}</td><td>{v.por} · {v.origem}</td>
                  <td>{rev[v.id] ? <span className={"fx-sefaz " + (rev[v.id] === "aceito" ? "ok" : "warn")}>{rev[v.id]}</span> : <div style={{ display: "flex", gap: 6 }}><button className="fx-btn" onClick={() => { setRev({ ...rev, [v.id]: "aceito" }); trToast("Aceite registrado: M. Ribeiro · CRC 1SP-000000 · versão " + v.id, "ok"); }}>Aceitar</button><button className="fx-btn" onClick={() => { const t = window.prompt("O que precisa mudar? (obrigatório)"); if (t && t.trim()) { setRev({ ...rev, [v.id]: "ajuste pedido" }); trToast("Pedido de ajuste enviado à empresa: " + t.trim(), "warn"); } }}>Pedir ajuste</button></div>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
      <p className="fx-nota-rodape">Mês de exemplo. O contador abre pelo link de revisão (14 dias + código do e-mail) e vê só isto: nenhuma venda, cliente ou valor de nota individual. Sublimite e segregação vêm do Financeiro e das notas; Fator R, do Ponto/RH.</p>
    </>
  );
}

function TrBateria() {
  const res = TR_CENARIOS.map(c => ({ c, r: trRodarCenario(c) }));
  const ok = res.filter(x => !x.r.falhas.length).length;
  return (
    <>
      <div className="fx-chips" aria-live="polite"><span className="fx-chip" aria-pressed="true">{TR_CENARIOS.length} notas simuladas</span><span className="fx-chip" data-tone={ok === res.length ? null : "bad"}>passaram <b>{ok}</b></span><span className="fx-chip" data-tone={res.length - ok ? "bad" : null}>falharam <b>{res.length - ok}</b></span></div>
      <div className="fx-table" data-contract="bateria-notas">
        <table>
          <thead><tr><th style={{ width: 52 }}>#</th><th>Cenário</th><th>Esperado</th><th>Obtido</th><th style={{ width: 110 }}>Resultado</th></tr></thead>
          <tbody>
            {res.map(({ c, r }) => (
              <tr key={c.id}>
                <td className="trb-mono">{c.id}</td>
                <td className="cli"><b>{c.nome}</b><div>{c.imp ? "aba Importação" : c.fator ? "aba Serviços · Fator R" : [c.ctx.emitente === "filial" ? "filial PR" : null, c.ctx.opId, c.ctx.uf, c.ctx.opId === "servico" ? "tomador " + (c.ctx.tomador || "pj") : c.ctx.dest, c.ctx.regime].filter(Boolean).join(" · ")}</div></td>
                <td className="trb-mono" style={{ fontSize: "var(--fs-2)" }}>{Object.entries(c.esp).map(([k, v]) => k + "=" + (Array.isArray(v) ? v.join(" ") : v)).join(" · ")}</td>
                <td>{r.resumo}{r.falhas.map(f => <div key={f} className="fx-rej">{f}</div>)}</td>
                <td><span className={"fx-sefaz " + (r.falhas.length ? "bad" : "ok")}>{r.falhas.length ? "falhou" : "passou"}</span></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="fx-nota-rodape">O esperado de cada cenário foi calculado à parte, não pelo simulador. Alíquotas da tabela por UF são exemplo. Fora da bateria: INSS 11% em cessão de mão de obra, ST com protocolo entre UFs e base dupla do DIFAL por UF — dependem de regra por UF que o contador cadastra.</p>
    </>
  );
}

function TrProdutos() {
  const [f, setF] = useStateTr("todos");
  const st = (p) => p.tipo === "serviço" ? "servico" : !p.ncm ? "semncm" : !p.regra ? "padrao" : TR_REGRAS.find(r => r.id === p.regra).nivel === 1 ? "excecao" : "ok";
  const lbl = { ok: ["Regra pelo NCM", "ok"], padrao: ["Cai no padrão", "warn"], semncm: ["Sem NCM — bloqueia", "bad"], excecao: ["Exceção", "warn"], servico: ["Serviço · LC 116", "ok"] };
  const cont = (k) => TR_PRODUTOS.filter(p => st(p) === k).length;
  const rows = TR_PRODUTOS.filter(p => f === "todos" || st(p) === f);
  return (
    <>
      <div className="trb-vinculo">
        <div><b>Produto</b><small>tem NCM (mercadoria) ou item LC 116 (serviço)</small></div><span aria-hidden="true">→</span>
        <div><b>NCM</b><small>chave automática — não precisa ligar produto a produto</small></div><span aria-hidden="true">→</span>
        <div><b>Regra</b><small>escolhida pela cascata na hora da nota</small></div>
      </div>
      <div className="fx-chips" role="group" aria-label="Filtro de vínculo">
        {[["todos", "Todos", TR_PRODUTOS.length], ["semncm", "Sem NCM", cont("semncm")], ["padrao", "Cai no padrão", cont("padrao")], ["excecao", "Com exceção", cont("excecao")], ["servico", "Serviços", cont("servico")]].map(([k, l, n]) =>
          <button key={k} className="fx-chip" data-tone={k === "semncm" ? "bad" : k === "padrao" ? "warn" : null} aria-pressed={f === k} onClick={() => setF(k)}>{l} <b>{n}</b></button>)}
      </div>
      <div className="fx-table" data-contract="vinculo-produtos">
        <table>
          <thead><tr><th>Produto</th><th>NCM / item</th><th>Situação</th><th>Sugestão da IA</th><th style={{ width: 150 }}></th></tr></thead>
          <tbody>
            {rows.map(p => { const s = st(p); return (
              <tr key={p.id}>
                <td className="cli"><b>{p.nome}</b><div>{p.tipo}</div></td>
                <td className="trb-mono">{p.ncm || (p.lc ? "LC " + p.lc : "—")}</td>
                <td><span className={"fx-sefaz " + lbl[s][1]}>{lbl[s][0]}</span></td>
                <td>{p.ia ? <span className="trb-ia"><TrI name="bot" /> {p.ia.ncm ? "NCM " + p.ia.ncm : "Serviço LC " + p.ia.lc} <small>{Math.round(p.ia.conf * 100)}%</small></span> : <span style={{ color: "var(--text-mute)" }}>—</span>}</td>
                <td>{p.ia ? <button className="fx-btn" onClick={() => trToast("Sugestão aplicada — registrada na auditoria")}>Revisar sugestão</button> : s === "padrao" ? <button className="fx-btn" onClick={() => trToast("Rascunho de regra criado pro NCM " + p.ncm)}>Criar regra</button> : null}</td>
              </tr>); })}
          </tbody>
        </table>
      </div>
    </>
  );
}

function TrUF() {
  return (
    <>
      <div className="fx-table fx-d-comfort" data-contract="icms-uf">
        <table>
          <thead><tr><th>UF</th><th style={{ textAlign: "right" }}>ICMS interno</th><th style={{ textAlign: "right" }}>FCP</th><th style={{ textAlign: "right" }}>Interestadual saindo de {TR_ORIGEM}</th><th style={{ textAlign: "right" }}>DIFAL p/ consumidor</th><th style={{ textAlign: "right" }}>Protocolos de ST</th><th>Vigência</th></tr></thead>
          <tbody>
            {TR_UFS.map(u => (
              <tr key={u.uf}>
                <td className="num"><b>{u.uf}</b>{u.uf === TR_ORIGEM && <small>origem</small>}</td>
                <td className="val">{trPct(u.interno)}</td><td className="val">{u.fcp ? trPct(u.fcp) : "—"}</td>
                <td className="val">{u.inter == null ? "—" : trPct(u.inter)}</td>
                <td className="val">{u.inter == null ? "—" : trPct(u.interno - u.inter)}</td>
                <td className="val">{u.st || "—"}</td><td className="trb-mono">{u.vig}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="fx-nota-rodape">Valores de exemplo. Proposta: tabela versionada por vigência, atualizada por fonte oficial e revisada pelo contador antes de valer. Produto importado usa interestadual de 4% (Resolução do Senado 13/2012). Se a regra por NCM não informar alíquota, o motor usa esta tabela.</p>
    </>
  );
}

function TrFatorR() {
  const [folha, setFolha] = useStateTr(84000); const [receita, setReceita] = useStateTr(280000);
  const f = trFatorR(folha, receita); const num = (s) => parseFloat(String(s).replace(/\./g, "").replace(",", ".")) || 0;
  return (
    <div className="fx-card" data-contract="fator-r">
      <div className="fx-card-h"><span>Fator R · últimos 12 meses</span><span className={"fx-sefaz " + (f.anexo === "III" ? "ok" : "warn")}>Anexo {f.anexo}</span></div>
      <div className="trb-form">
        <label className="trb-f"><span>Folha de salários + pró-labore (R$)</span><input className="fx-select" inputMode="decimal" value={folha} onChange={e => setFolha(num(e.target.value))} /></label>
        <label className="trb-f"><span>Receita bruta (R$)</span><input className="fx-select" inputMode="decimal" value={receita} onChange={e => setReceita(num(e.target.value))} /></label>
      </div>
      <p className="fx-nota-rodape" style={{ padding: "0 16px 12px" }}>Fator R = {trPct(f.r)}. A partir de 28% o serviço fica no Anexo III; abaixo, no Anexo V (LC 123/2006, art. 18). No sistema, a folha vem do Ponto/RH e a receita do Financeiro, com aviso quando cruzar 28%.</p>
    </div>
  );
}

function TrServicos() {
  return (
    <>
      <TrFatorR />
      <div className="fx-grid">
        <div className="fx-card">
          <div className="fx-card-h"><span>CNAEs da empresa</span><span className="trb-mono">Simples · Anexo III</span></div>
          {TR_CNAE.map(c => <div className="fx-row" key={c.cnae}><span className="fx-row-l"><b className="trb-mono">{c.cnae}</b> {c.desc}{c.principal && <span className="fx-sefaz ok" style={{ marginLeft: 6 }}>principal</span>}</span><span className="fx-row-v txt">Anexo {c.anexo} · {c.doc}</span></div>)}
        </div>
        <div className="fx-card">
          <div className="fx-card-h"><span>Pra que o CNAE serve aqui</span></div>
          <div className="fx-row"><span className="fx-row-l">Anexo do Simples</span><span className="fx-row-v txt">define a alíquota do DAS</span></div>
          <div className="fx-row"><span className="fx-row-l">Item da LC 116</span><span className="fx-row-v txt">cada CNAE de serviço → item → código nacional</span></div>
          <div className="fx-row"><span className="fx-row-l">Validação</span><span className="fx-row-v txt">bloqueia NFS-e com item fora dos CNAEs</span></div>
          <div className="fx-row"><span className="fx-row-l">Fator R</span><span className="fx-row-v txt">folha ÷ receita (12 meses) — Anexo III ou V</span></div>
        </div>
      </div>
      <div className="fx-table" data-contract="servicos-nfse">
        <table>
          <thead><tr><th>Item LC 116</th><th>Cód. nacional</th><th>Cód. municipal</th><th>NBS</th><th>Serviço</th><th>Município</th><th style={{ textAlign: "right" }}>ISS</th><th>Retenções</th><th style={{ textAlign: "right" }}>Produtos</th></tr></thead>
          <tbody>
            {TR_SERV.map(s => <tr key={s.item}><td className="num"><b>{s.item}</b></td><td className="trb-mono">{s.nac}</td><td className="trb-mono">{s.cMun || <span className="fx-sefaz bad" title="Prefeitura exige o código municipal junto do nacional — sem ele a NFS-e volta com E0312">falta · E0312</span>}</td><td className="trb-mono">{s.nbs}</td><td className="cli"><b>{s.desc}</b><div>CNAE {s.cnae} · indOp {s.indOp}</div></td><td>{s.mun}</td><td className="val">{trPct(s.iss)}</td><td>{s.ret}</td><td className="val">{s.prod}</td></tr>)}
          </tbody>
        </table>
      </div>
      <p className="fx-nota-rodape">O ISS varia por município (2% a 5%). Onde o ISS é devido (sede ou local da prestação) depende do item: instalação (14.06) e obra costumam ser tributadas no local do serviço. A NFS-e nacional usa o código de 6 dígitos.</p>
    </>
  );
}

function TrImportacao() {
  const [v, setV] = useStateTr(TR_IMPORT_PADRAO);
  const set = (k) => (e) => setV({ ...v, [k]: parseFloat(String(e.target.value).replace(",", ".")) || 0 });
  const { va, ii, ipi, pis, cof, bIcms, icms, custo } = trImportar(v);
  const campos = [["usd", "Mercadoria (FOB) US$"], ["frete", "Frete internacional US$"], ["seguro", "Seguro US$"], ["ptax", "Câmbio da DI/DUIMP"], ["desp", "Taxa Siscomex e despesas R$"]];
  const aliq = [["ii", "Imposto de importação"], ["ipi", "IPI"], ["pis", "PIS-importação"], ["cofins", "COFINS-importação"], ["icms", "ICMS (SP)"]];
  return (
    <div className="trb-sim" data-contract="importacao">
      <div className="fx-card">
        <div className="fx-card-h"><span>Declaração de importação</span><span className="trb-mono">NCM 8443.32.29</span></div>
        <div className="trb-form">
          {campos.map(([k, l]) => <label className="trb-f" key={k}><span>{l}</span><input className="fx-select" inputMode="decimal" value={v[k]} onChange={set(k)} /></label>)}
          {aliq.map(([k, l]) => <label className="trb-f" key={k}><span>{l} (decimal)</span><input className="fx-select" inputMode="decimal" value={v[k]} onChange={set(k)} /></label>)}
        </div>
        <p className="fx-nota-rodape" style={{ padding: "0 16px 14px" }}>O câmbio é o da data de registro da DI/DUIMP e fica gravado na nota de entrada (CFOP 3102). Variação do dólar depois disso não muda o imposto; vai pro custo/financeiro como variação cambial.</p>
      </div>
      <div className="fx-table trb-sim-res">
        <table>
          <thead><tr><th>Etapa</th><th>Cálculo</th><th style={{ textAlign: "right" }}>Valor</th></tr></thead>
          <tbody>
            <tr><td><b>Valor aduaneiro</b></td><td>(FOB + frete + seguro) × câmbio</td><td className="val">{trBrl(va)}</td></tr>
            <tr><td><b>II</b></td><td>valor aduaneiro × {trPct(v.ii)}</td><td className="val">{trBrl(ii)}</td></tr>
            <tr><td><b>IPI</b></td><td>(aduaneiro + II) × {trPct(v.ipi)}</td><td className="val">{trBrl(ipi)}</td></tr>
            <tr><td><b>PIS / COFINS</b></td><td>aduaneiro × {trPct(v.pis)} / {trPct(v.cofins)}</td><td className="val">{trBrl(pis + cof)}</td></tr>
            <tr><td><b>ICMS</b></td><td>base "por dentro" {trBrl(bIcms)} × {trPct(v.icms)}</td><td className="val">{trBrl(icms)}</td></tr>
            <tr className="trb-total"><td><b>Custo de entrada</b></td><td>com despesas aduaneiras</td><td className="val"><b>{trBrl(custo)}</b></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  );
}

function TrDevolucoes() {
  return (
    <>
      <div className="fx-grid">
        <div className="fx-card"><div className="fx-card-h"><span>Regra da devolução</span></div>
          <div className="fx-row"><span className="fx-row-l">Alíquotas e base</span><span className="fx-row-v txt">copiadas da nota de origem, não da regra de hoje</span></div>
          <div className="fx-row"><span className="fx-row-l">Nota referenciada</span><span className="fx-row-v txt">chave obrigatória (refNFe)</span></div>
          <div className="fx-row"><span className="fx-row-l">Parcial</span><span className="fx-row-v txt">imposto proporcional à quantidade</span></div>
          <div className="fx-row"><span className="fx-row-l">Finalidade da NF-e</span><span className="fx-row-v">4 · devolução</span></div>
        </div>
        <div className="fx-card"><div className="fx-card-h"><span>Quem emite</span></div>
          <div className="fx-row"><span className="fx-row-l">Cliente contribuinte devolve</span><span className="fx-row-v txt">ele emite; você escritura a entrada</span></div>
          <div className="fx-row"><span className="fx-row-l">Consumidor sem IE devolve</span><span className="fx-row-v txt">você emite nota de entrada</span></div>
          <div className="fx-row"><span className="fx-row-l">Você devolve ao fornecedor</span><span className="fx-row-v txt">nota de saída 5202 / 6202</span></div>
        </div>
      </div>
      <div className="fx-table" data-contract="devolucao-cfop">
        <table>
          <thead><tr><th>CFOP original</th><th>Operação original</th><th>CFOP da devolução</th><th>Operação de devolução</th></tr></thead>
          <tbody>{TR_DEVOL.map(d => <tr key={d.orig}><td className="num"><b>{d.orig}</b></td><td>{d.origD}</td><td className="num"><b>{d.dev}</b></td><td>{d.devD}</td></tr>)}</tbody>
        </table>
      </div>
    </>
  );
}

const TR_CFOP_ENT = { "5102": "1102", "6102": "2102", "5101": "1101", "6101": "2101", "5405": "1403", "6403": "2403", "5949": "1949" };
const TR_ENTRADAS = [
  { id: "e1", forn: "Lona & Cia Distribuidora", nota: "1174", uf: "SP", itens: [
    { desc: "LONA FRONT 440G 3,20M", ncm: "39219019", cfop: "5102", cst: "000", icms: 0.18, prod: "p1" },
    { desc: "ILHOS LATAO N.0 C/1000", ncm: "83089010", cfop: "5102", cst: "000", icms: 0.18, prod: null }] },
  { id: "e2", forn: "Tintas Prisma S/A", nota: "745", uf: "MG", itens: [
    { desc: "TINTA ECO SOLV CYAN 1L", ncm: "32151900", cfop: "6102", cst: "000", icms: 0.12, prod: "p6" }] },
  { id: "e3", forn: "TechSupply Componentes Ltda", nota: "982", uf: "SP", itens: [
    { desc: "MODULO LED 12V IP65", ncm: "94056000", cfop: "5405", cst: "060", icms: 0, prod: "p4" }] },
];

function TrEntradas() {
  const [map, setMap] = useStateTr({});
  return (
    <>
      <div className="fx-grid">
        <div className="fx-card"><div className="fx-card-h"><span>Como a entrada é lida</span></div>
          <div className="fx-row"><span className="fx-row-l">Origem</span><span className="fx-row-v txt">XML do fornecedor (Manifesto DF-e ou upload)</span></div>
          <div className="fx-row"><span className="fx-row-l">CFOP</span><span className="fx-row-v txt">5 → 1, 6 → 2 (5102 vira 1102)</span></div>
          <div className="fx-row"><span className="fx-row-l">Produto</span><span className="fx-row-v txt">de-para código do fornecedor → seu produto, lembrado</span></div>
          <div className="fx-row"><span className="fx-row-l">Crédito de ICMS</span><span className="fx-row-v txt">Simples não credita — vai pro custo</span></div>
        </div>
        <div className="fx-card"><div className="fx-card-h"><span>O que trava a entrada</span></div>
          <div className="fx-row"><span className="fx-row-l">Item sem produto vinculado</span><span className="fx-row-v txt">pede o de-para uma vez</span></div>
          <div className="fx-row"><span className="fx-row-l">NCM do fornecedor ≠ NCM do produto</span><span className="fx-row-v txt">avisa, não troca sozinho</span></div>
          <div className="fx-row"><span className="fx-row-l">CST 060 (ST retida)</span><span className="fx-row-v txt">marca o produto pra sair com CSOSN 500</span></div>
        </div>
      </div>
      <div className="fx-table" data-contract="entradas-xml">
        <table>
          <thead><tr><th>Fornecedor · nota</th><th>Item do XML</th><th>NCM</th><th>CFOP fornecedor → entrada</th><th>CST</th><th style={{ textAlign: "right" }}>ICMS destacado</th><th>Seu produto</th></tr></thead>
          <tbody>
            {TR_ENTRADAS.flatMap(e => e.itens.map((it, i) => { const k = e.id + i; const pid = map[k] !== undefined ? map[k] : it.prod; const p = TR_PRODUTOS.find(x => x.id === pid); return (
              <tr key={k}>
                <td className="cli">{i === 0 ? <><b>{e.forn}</b><div>NF {e.nota} · {e.uf}</div></> : null}</td>
                <td className="trb-mono">{it.desc}</td>
                <td className="trb-mono">{it.ncm}{p && p.ncm && p.ncm !== it.ncm && <div className="fx-rej">seu produto: {p.ncm}</div>}</td>
                <td className="trb-mono">{it.cfop} → <b>{TR_CFOP_ENT[it.cfop] || "?"}</b></td>
                <td className="trb-mono">{it.cst}{it.cst === "060" && <div style={{ color: "var(--fx-warn)" }}>ST retida</div>}</td>
                <td className="val">{trPct(it.icms)}</td>
                <td>{p ? <span className="fx-sefaz ok">{p.nome}</span> : (
                  <select className="fx-select" aria-label={"Vincular " + it.desc} value="" onChange={ev => { setMap({ ...map, [k]: ev.target.value }); trToast("De-para salvo — próximas notas desse fornecedor já vêm vinculadas"); }}>
                    <option value="">Vincular produto…</option>{TR_PRODUTOS.filter(x => x.tipo === "mercadoria").map(x => <option key={x.id} value={x.id}>{x.nome}</option>)}
                  </select>)}</td>
              </tr>); }))}
          </tbody>
        </table>
      </div>
      <p className="fx-nota-rodape">Hoje o contador de entradas da produção é um literal 0 — a importação de XML de entrada não existe. Esta aba é proposta (UC-TRB-18).</p>
    </>
  );
}

const TR_JANA = [
  { q: "Qual NCM pra placa ACM 3mm impressa?", a: "Sugiro 7606.12.90 (chapa de alumínio composto), confiança média. A impressão pode mudar a classificação se o item for vendido como letreiro pronto (9405). Mandei pra fila de sugestões como risco médio.", fonte: "descrição + categoria do produto" },
  { q: "Banner personalizado é ICMS ou ISS?", a: "Feito sob encomenda pro usuário final tende a ser serviço, item 24.01 (ISS). Vendido em série é mercadoria (ICMS). Isso é decisão do contador: deixei a sugestão como risco alto, e ela só é aceita com a confirmação dele.", fonte: "LC 116 item 24.01 · cadastro do produto" },
  { q: "Por que a nota do RJ saiu com CFOP 6102?", a: "A regra usada foi a exceção NCM 3921.90.19 → RJ (nível 2). Destino fora de SP troca o ? do CFOP por 6. Abra o simulador pra ver o rastro completo.", fonte: "motor tributário · regra r2" },
];
function TrJana() {
  const [i, setI] = useStateTr(null);
  return (
    <section className="fx-card" data-contract="jana-fiscal" aria-label="Perguntar à Jana">
      <div className="fx-card-h"><span><TrI name="bot" /> Perguntar à Jana · tributação</span><span className="trb-mono">só lê · sugestão vai pra fila</span></div>
      <div className="trb-ia-b">
        <div className="fx-chips" role="group" aria-label="Perguntas frequentes">{TR_JANA.map((x, k) => <button key={k} className="fx-chip" aria-pressed={i === k} onClick={() => setI(k)}>{x.q}</button>)}</div>
        {i != null ? <div className="trb-jana-r" aria-live="polite"><p>{TR_JANA[i].a}</p><small>Fonte: {TR_JANA[i].fonte}. A Jana não altera regra nem produto.</small></div> : <small>Escolha uma pergunta. A Jana responde só com o cadastro e as regras desta empresa.</small>}
      </div>
    </section>
  );
}

function TrIA() {
  const [lista, setLista] = useStateTr(TR_IA); const [lido, setLido] = useStateTr({});
  const tira = (id, msg, t) => { setLista(lista.filter(x => x.id !== id)); trToast(msg, t); };
  return (
    <>
      <TrJana />
      <div className="fx-decisao"><b>A IA sugere, nunca aplica sozinha</b><small>Cada sugestão mostra o motivo e a confiança. Aceitar exige permissão de tributação, cria nova versão da regra ou do produto e fica na auditoria com autor e horário. Sugestão de natureza (serviço × mercadoria) sempre pede confirmação do contador.</small></div>
      {lista.length === 0 ? <div className="fx-empty"><b>Nada pendente</b><small>Novas sugestões aparecem quando um produto é cadastrado ou uma regra fica incompleta.</small></div> : (
        <div className="trb-ia-list" data-contract="sugestoes-ia">
          {lista.map(s => (
            <article key={s.id} className="fx-card trb-ia-card">
              <div className="fx-card-h"><span><TrI name="bot" /> {s.tipo} · {s.alvo}</span><span className={"fx-sefaz " + (s.risco === "alto" ? "bad" : s.risco === "médio" ? "warn" : "ok")}>risco {s.risco}</span></div>
              <div className="trb-ia-b">
                <b>{s.sug}</b>
                <small>{s.por}</small>
                <window.OfficeImpressoPontoWR2DesignSystem_019dd0.Progress value={Math.round(s.conf * 100)} label="Confiança" showValue size="sm" />
                {s.risco === "alto" && <label className="trb-check"><input type="checkbox" checked={!!lido[s.id]} onChange={e => setLido({ ...lido, [s.id]: e.target.checked })} /> Li o motivo e confirmei com o contador</label>}
              </div>
              <div className="fx-dr-f">
                <button className="fx-btn primary" disabled={s.risco === "alto" && !lido[s.id]} onClick={() => tira(s.id, "Sugestão aceita — nova versão registrada com seu nome")}><TrI name="check" /> Aceitar</button>
                <button className="fx-btn" onClick={() => tira(s.id, "Enviada ao contador pra revisão")}>Enviar ao contador</button>
                <button className="fx-btn" onClick={() => tira(s.id, "Sugestão descartada", "warn")}>Descartar</button>
              </div>
            </article>
          ))}
        </div>
      )}
    </>
  );
}

const TR_LIDO = [
  { campo: "CNPJ", valor: "04.982.117/0001-45", fonte: "certificado" },
  { campo: "Razão social", valor: "OFFICE IMPRESSO COMUNICACAO VISUAL LTDA", fonte: "SEFAZ-SP" },
  { campo: "UF", valor: "SP", fonte: "SEFAZ-SP" },
  { campo: "Inscrição estadual", valor: "123.456.789.110", fonte: "SEFAZ-SP" },
  { campo: "Situação", valor: "Habilitado", fonte: "SEFAZ-SP" },
  { campo: "CNAE principal", valor: "1813-0/01 · impressão de material publicitário", fonte: "BrasilAPI" },
  { campo: "CNAEs secundários", valor: "3299-0/03 · 4329-1/01 · 7319-0/99", fonte: "BrasilAPI" },
  { campo: "Regime", valor: null, fonte: "divergente", opcoes: [["Simples Nacional", "BrasilAPI (optante)"], ["Normal", "SEFAZ-SP (regime de apuração)"]] },
];
const TR_TEMPLATES_SUG = [
  { slug: "comunicacao-visual-simples-sp", nome: "Comunicação visual · Simples · SP", casa: "regime + UF + CNAE 1813", rec: true, valores: "CFOP ?102 · CSOSN 102 · ICMS no DAS · PIS/COFINS 07" },
  { slug: "industria-grafica-simples-sp", nome: "Indústria gráfica · Simples · SP", casa: "regime + UF", valores: "CFOP ?101 · CSOSN 101 · crédito ICMS 1,86%" },
  { slug: "industria-grafica-presumido-sp", nome: "Indústria gráfica · Lucro presumido · SP", casa: "UF (regime não bate)", valores: "CFOP ?101 · CST 00 · ICMS 18% · PIS 0,65% · COFINS 3%" },
];
function TrOnboarding({ open, onClose }) {
  const xRef = useRefTr(null);
  const [passo, setPasso] = useStateTr(1);
  const [regime, setRegime] = useStateTr(null);
  const [tpl, setTpl] = useStateTr("comunicacao-visual-simples-sp");
  const [ncm, setNcm] = useStateTr("");
  useEffectTr(() => {
    if (!open) return; setPasso(1);
    const k = (e) => { if (e.key === "Escape") onClose(); };
    document.addEventListener("keydown", k); xRef.current && xRef.current.focus();
    return () => document.removeEventListener("keydown", k);
  }, [open]);
  if (!open) return null;
  const ncmOk = /^\d{8}$/.test(ncm) && ncm !== "00000000";
  const t = TR_TEMPLATES_SUG.find(x => x.slug === tpl);
  const PASSOS = ["Certificado", "Dados lidos", "Template", "Confirmar"];
  return (
    <>
      <div className="fx-scrim" onClick={onClose}></div>
      <aside className="fx-drawer" role="dialog" aria-modal="true" aria-labelledby="trb-onb-t">
        <div className="fx-dr-h">
          <div><h2 id="trb-onb-t">Configurar pelo certificado</h2><p>Lemos os dados da empresa, você confirma. Nada é aplicado sem o seu clique.</p></div>
          <button ref={xRef} className="fx-dr-x" onClick={onClose} aria-label="Fechar">×</button>
        </div>
        <ol className="trb-onb-steps" aria-label="Etapas">{PASSOS.map((p, i) => <li key={p} data-st={i + 1 < passo ? "ok" : i + 1 === passo ? "cur" : "next"}><span>{i + 1}</span>{p}</li>)}</ol>
        <div className="fx-dr-b">
          {passo === 1 && (
            <section className="fx-sec"><h3>Certificado A1</h3>
              <dl className="fx-kv"><dt>Arquivo</dt><dd className="trb-mono">office-impresso-2026.pfx</dd><dt>Titular</dt><dd>OFFICE IMPRESSO COMUNICACAO VISUAL LTDA</dd><dt>CNPJ</dt><dd className="trb-mono">04.982.117/0001-45</dd><dt>Válido até</dt><dd>04/07/2027</dd></dl>
              <p className="fx-nota-rodape">Com o próprio certificado consultamos a SEFAZ do seu estado (RS, SP, PR, MG, BA e SC). Nos outros estados, inscrição estadual e regime são digitados.</p>
            </section>)}
          {passo === 2 && (
            <section className="fx-sec"><h3>O que encontramos</h3>
              <div className="fx-list">
                {TR_LIDO.map(l => (
                  <div className="fx-list-i trb-lido" key={l.campo}>
                    <b>{l.campo}</b>
                    {l.opcoes ? (
                      <div className="trb-conflito" role="radiogroup" aria-label="Regime — as fontes divergem">
                        <small>As fontes divergem. Confirme com o contador:</small>
                        {l.opcoes.map(([v, f]) => <label key={v} className="trb-check"><input type="radio" name="trb-regime" checked={regime === v} onChange={() => setRegime(v)} /> {v} <span className="trb-sub">· {f}</span></label>)}
                      </div>
                    ) : <span className="trb-lido-v"><span>{l.valor}</span><span className="trb-sub">{l.fonte}</span></span>}
                  </div>
                ))}
              </div>
            </section>)}
          {passo === 3 && (
            <section className="fx-sec"><h3>Template sugerido</h3>
              <div className="fx-list">
                {TR_TEMPLATES_SUG.map(x => (
                  <label key={x.slug} className="fx-list-i trb-tpl">
                    <span className="trb-check"><input type="radio" name="trb-tpl" checked={tpl === x.slug} onChange={() => setTpl(x.slug)} /> <b>{x.nome}</b>{x.rec && <span className="fx-sefaz ok" style={{ marginLeft: 6 }}>recomendado</span>}</span>
                    <span className="trb-sub">casa: {x.casa} · {x.valores}</span>
                  </label>
                ))}
              </div>
              <label className="trb-f" style={{ marginTop: 10 }}><span>NCM padrão da empresa (obrigatório)</span><input className="fx-select" inputMode="numeric" maxLength={8} placeholder="8 dígitos" value={ncm} onChange={e => setNcm(e.target.value.replace(/\D/g, ""))} aria-invalid={ncm.length > 0 && !ncmOk} /></label>
              <small className="trb-sub">Vale pro item sem NCM, que entra na revisão. 00000000 não é aceito.</small>
            </section>)}
          {passo === 4 && (
            <section className="fx-sec"><h3>Vai ser aplicado</h3>
              <dl className="fx-kv"><dt>Regime</dt><dd>{regime}</dd><dt>Template</dt><dd>{t.nome}</dd><dt>Valores</dt><dd>{t.valores}</dd><dt>NCM padrão</dt><dd className="trb-mono">{ncm}</dd><dt>Regras por NCM</dt><dd>mantidas como estão</dd><dt>Registro</dt><dd>activity('nfe.tributacao') · template.aplicado · seu nome</dd></dl>
              <div className="fx-decisao"><b>Já existe configuração nesta empresa</b><small>Aplicar substitui o regime e a regra geral das operações. As exceções por NCM ficam.</small></div>
            </section>)}
        </div>
        <div className="fx-dr-f">
          {passo > 1 && <button className="fx-btn" onClick={() => setPasso(passo - 1)}>Voltar</button>}
          {passo < 4 && <button className="fx-btn primary" disabled={(passo === 2 && !regime) || (passo === 3 && !ncmOk)} onClick={() => setPasso(passo + 1)}>Continuar</button>}
          {passo === 4 && <button className="fx-btn primary" onClick={() => { trToast("Template aplicado — operações criadas, aguardando aceite do contador"); onClose(); }}><TrI name="check" /> Aplicar template</button>}
          {passo === 2 && !regime && <small className="trb-sub">Escolha o regime pra continuar.</small>}
        </div>
      </aside>
    </>
  );
}

function TrComecar({ ir }) {
  const [onb, setOnb] = useStateTr(false);
  const passos = [
    { ok: true, t: "Certificado lido", d: "CNPJ, UF, IE e situação vieram da SEFAZ-SP; CNAEs e Simples, da BrasilAPI. O regime foi confirmado por você." },
    { ok: true, t: "Template aplicado", d: "Simples Nacional — mesmo mecanismo dos templates L1 da produção; gerou " + TR_OPS.length + " operações" },
    { ok: false, t: "Produtos com NCM", d: TR_PRODUTOS.filter(p => !p.ncm && p.tipo !== "serviço").length + " sem NCM bloqueiam a nota", aba: "produtos", cta: "Resolver" },
    { ok: false, t: "Serviços com código municipal", d: TR_SERV.filter(s => !s.cMun).length + " sem código — a NFS-e seria rejeitada", aba: "servicos", cta: "Resolver" },
    { ok: false, t: "Aceite do contador", d: TR_OPS.filter(o => !o.rev).length + " operações sem aceite registrado", cta: "Convidar contador" },
  ];
  const feitos = passos.filter(p => p.ok).length;
  return (
    <section className="fx-card" data-contract="comecar" aria-label="Configuração inicial">
      <div className="fx-card-h"><span>Pronto pra emitir · {feitos} de {passos.length}</span><button className="fx-btn" onClick={() => setOnb(true)}><TrI name="shield" /> Configurar pelo certificado</button><span style={{ width: 160 }}><window.OfficeImpressoPontoWR2DesignSystem_019dd0.Progress value={feitos} max={passos.length} size="sm" /></span></div>
      <ol className="trb-steps">
        {passos.map((p, i) => (
          <li key={i} data-ok={p.ok ? "true" : "false"}>
            <span className="trb-step-n" aria-hidden="true">{p.ok ? <TrI name="check" size={12} /> : i + 1}</span>
            <div><b>{p.t}</b><small>{p.d}</small></div>
            {!p.ok && p.cta && <button className="fx-btn" onClick={() => p.aba ? ir(p.aba) : trToast("Convite enviado — o contador entra só com acesso de revisão")}>{p.cta}</button>}
          </li>
        ))}
      </ol>
      <TrOnboarding open={onb} onClose={() => setOnb(false)} />
    </section>
  );
}

function TrOperacoes({ onOpen, ir }) {
  return (
    <>
      <TrComecar ir={ir} />
      <div className="fx-table" data-contract="operacoes">
        <table>
          <thead><tr><th>Operação</th><th>CFOP</th><th>Destinatário</th><th>Documentos</th><th>Regra geral</th><th style={{ textAlign: "right" }}>Exceções</th><th>Situação</th></tr></thead>
          <tbody>
            {TR_OPS.map(o => (
              <tr key={o.id} onClick={() => onOpen(o)} tabIndex={0} onKeyDown={e => e.key === "Enter" && onOpen(o)}>
                <td className="cli"><b>{o.nome}</b><div>{o.tipo} · finalidade {o.fin}</div></td>
                <td className="trb-mono">{o.cfop}</td><td>{o.dest}</td><td>{o.docs}</td>
                <td className="trb-mono">{o.geral ? "CSOSN " + o.geral.csosn + (o.geral.icms ? " · ICMS " + trPct(o.geral.icms) : "") : "por item LC 116"}</td>
                <td className="val">{TR_REGRAS.filter(r => r.opId === o.id).length || "—"}</td>
                <td><span className={"fx-sefaz " + (o.rev ? "ok" : "warn")}>{o.rev ? "Aceita" : "Aguarda aceite"}</span>{o.rev && <div className="trb-sub">{TR_ACEITE}</div>}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="fx-nota-rodape">"?" no CFOP vira 5 dentro de {TR_ORIGEM}, 6 pra outra UF e 7 na exportação — uma operação serve pros dois casos. Venda a contribuinte e a consumidor final são operações separadas porque o ICMS muda.</p>
    </>
  );
}

function TrOperacaoDrawer({ op, onClose, onRegra }) {
  const xRef = useRefTr(null);
  useEffectTr(() => {
    if (!op) return;
    const k = (e) => { if (e.key === "Escape") onClose(); };
    document.addEventListener("keydown", k); xRef.current && xRef.current.focus();
    return () => document.removeEventListener("keydown", k);
  }, [op]);
  if (!op) return null;
  const exc = TR_REGRAS.filter(r => r.opId === op.id).sort((a, b) => a.nivel - b.nivel);
  const g = op.geral;
  return (
    <>
      <div className="fx-scrim" onClick={onClose}></div>
      <aside className="fx-drawer" role="dialog" aria-modal="true" aria-labelledby="trb-op-t">
        <div className="fx-dr-h">
          <div><h2 id="trb-op-t">{op.nome}</h2><p>{op.tipo} · {op.docs} · CFOP {op.cfop}</p></div>
          <button ref={xRef} className="fx-dr-x" onClick={onClose} aria-label="Fechar">×</button>
        </div>
        <div className="fx-dr-b">
          {op.obs && <div className="fx-decisao"><small>{op.obs}</small></div>}
          <section className="fx-sec"><h3>Regra geral (N4)</h3>
            {g ? <dl className="fx-kv"><dt>CSOSN</dt><dd className="trb-mono">{g.csosn}</dd><dt>ICMS</dt><dd>{trPct(g.icms)}</dd><dt>PIS / COFINS</dt><dd>{trPct(g.pis)} / {trPct(g.cofins)}</dd><dt>IPI</dt><dd>{trPct(g.ipi)}</dd><dt>cClassTrib</dt><dd className="trb-mono">{g.cClass}</dd><dt>IBS / CBS</dt><dd>{trPct(g.ibs)} / {trPct(g.cbs)} · não destaca no Simples em 2026</dd></dl>
              : <p className="fx-nota-rodape">Serviço não usa NCM: o imposto vem do item da LC 116 do serviço.</p>}
          </section>
          <section className="fx-sec"><h3>Exceções · a primeira que casar vence</h3>
            {exc.length ? <div className="fx-list">{exc.map(r => <button key={r.id} className="fx-list-i trb-list-btn" onClick={() => onRegra(r)}><span><TrNivel n={r.nivel} /> <b>{r.ncm}</b> {r.desc}</span><span className="mono">{r.ufD || "todas UF"}</span></button>)}</div>
              : <div className="fx-empty"><b>Sem exceções</b><small>Todo produto usa a regra geral.</small></div>}
          </section>
          <section className="fx-sec"><h3>Aceite do contador</h3>
            <dl className="fx-kv"><dt>Situação</dt><dd>{op.rev ? "Aceita · " + TR_ACEITE : "Sem aceite — regra sem responsável"}</dd><dt>Registro</dt><dd>activity('nfe.tributacao') · aceite.registrado</dd></dl>
          </section>
        </div>
        <div className="fx-dr-f">
          <button className="fx-btn primary" onClick={() => trToast("Escolha: por produto, por NCM ou por NCM + UF")}><TrI name="plus" /> Adicionar exceção</button>
          <button className="fx-btn" onClick={() => trToast("Nova versão criada — o aceite anterior não vale pra ela")}><TrI name="pencil" /> Editar regra geral</button>
          {!op.rev && <button className="fx-btn" onClick={() => trToast("Pedido de aceite enviado ao contador")}>Pedir aceite</button>}
        </div>
      </aside>
    </>
  );
}

function FxTributacaoPage() {
  const U = trU();
  const [aba, setAba] = useStateTr(() => U.ls("fx.trib.aba3") || "saude");
  const [regra, setRegra] = useStateTr(null); const [op, setOp] = useStateTr(null);
  const troca = (id) => { setAba(id); U.ls("fx.trib.aba3", id); };
  const conta = { saude: TR_SAUDE.length, operacoes: TR_OPS.length, regras: TR_REGRAS.length, produtos: TR_PRODUTOS.filter(p => !p.ncm && p.tipo !== "serviço").length, ia: TR_IA.length };
  return (
    <div className="fx-page">
      <U.Header title="Tributação" crumb={"Regras de imposto por NCM, UF e operação · Simples Nacional · origem " + TR_ORIGEM}>
        <button className="fx-btn" onClick={() => troca("simulador")}><TrI name="calc" /> Simular nota</button>
      </U.Header>
      <U.Subnav current="fiscal-tributacao" />
      <div className="fx-chips" role="tablist" aria-label="Abas de tributação">
        {TR_ABAS.map(a => <button key={a.id} role="tab" className="fx-chip" data-tone={a.id === "produtos" && conta.produtos ? "bad" : null} aria-selected={aba === a.id} aria-pressed={aba === a.id} onClick={() => troca(a.id)}>{a.label}{conta[a.id] != null && <b>{conta[a.id]}</b>}</button>)}
      </div>
      {aba === "saude" && <TrSaude ir={troca} />}
      {aba === "operacoes" && <TrOperacoes onOpen={setOp} ir={troca} />}
      {aba === "regras" && <TrRegras onOpen={setRegra} />}
      {aba === "simulador" && <TrSimulador />}
      {aba === "bateria" && <TrBateria />}
      {aba === "contador" && <TrContador />}
      {aba === "produtos" && <TrProdutos />}
      {aba === "uf" && <TrUF />}
      {aba === "servicos" && <TrServicos />}
      {aba === "importacao" && <TrImportacao />}
      {aba === "devolucoes" && <TrDevolucoes />}
      {aba === "entradas" && <TrEntradas />}
      {aba === "ia" && <TrIA />}
      <p className="fx-nota-rodape">Quem define CST, CFOP e alíquota é o contador; o sistema aplica e mostra de onde veio cada número. Alíquotas desta tela são exemplo.</p>
      <TrRegraDrawer regra={regra} onClose={() => setRegra(null)} />
      <TrOperacaoDrawer op={op} onClose={() => setOp(null)} onRegra={(r) => { setOp(null); setRegra(r); }} />
    </div>
  );
}

Object.assign(window, { FxTributacaoPage });
