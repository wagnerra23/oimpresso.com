// exportar-dados-page.jsx — EXPORTAR OS DADOS DA EMPRESA (US-BKP-001). Tela NOVA, não existe no vivo.
// Diferente do Backup (/backup, banco inteiro, só superadmin): aqui o DONO da empresa baixa um zip
// com um CSV por tabela, só com as linhas dela. Fatos do servidor (branch backup-exportacao-empresa-2):
//   POST /backup/empresa/exportacoes (só business.owner_id) → job na fila `backups`
//   GET  .../{id} → status + link assinado · GET .../{id}/baixar → zip; vencido = 410
//   pedido e download vão para o registro de atividades · validade padrão 24 h · zip sem cifra ([W] 2026-10-27)
// Fica fora do pacote: certificado NF-e, chaves de gateway/boleto/NFS-e/WhatsApp, senhas.
// Montada com o DS (PageHeader · Button · DataTable · Alert · StatusBadge). Tweak "Ver como" simula o papel.
// Expõe window.ExportarDadosPage.
(() => {
const { useState } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const AGORA = new Date(2026, 9, 27, 10, 0);
const p2 = (n) => String(n).padStart(2, "0");
const dt = (d) => `${p2(d.getDate())}/${p2(d.getMonth() + 1)}/${d.getFullYear()} ${p2(d.getHours())}:${p2(d.getMinutes())}`;
const tam = (b) => (b >= 1048576 ? (b / 1048576).toFixed(1).replace(".", ",") + " MB" : Math.round(b / 1024) + " KB");
const horas = (h) => new Date(AGORA.getTime() + h * 3600e3);

function Btn({ children, ...p }) {
  const { Button } = DS();
  if (!Button) return <button className={`os-btn ${p.variant === "primary" ? "primary" : "ghost"}`} disabled={p.disabled} onClick={p.onClick}>{children}</button>;
  return <Button {...p}>{children}</Button>;
}

const INICIAL = [
  { id: 14, pedido: horas(-50), status: "pronto", bytes: 18_874_368, expira: horas(-26), downloads: 1, por: "Wagner" },
  { id: 9, pedido: horas(-24 * 30), status: "falhou", bytes: null, expira: null, downloads: 0, por: "Wagner" },
];

function ExportarDadosPage() {
  const { PageHeader, DataTable, Alert, StatusBadge } = DS();
  const [dono, setDono] = useState(true);
  const [lista, setLista] = useState(INICIAL);
  const [aviso, setAviso] = useState(null);
  const pendente = lista.find((x) => x.status === "pendente");

  const pedir = () => {
    if (pendente) return;
    const novo = { id: 15, pedido: AGORA, status: "pendente", bytes: null, expira: null, downloads: 0, por: "Wagner" };
    setLista((l) => [novo, ...l]);
    setAviso("Pedido recebido. O arquivo fica pronto em alguns minutos; esta tela atualiza sozinha.");
    setTimeout(() => setLista((l) => l.map((x) => (x.id === 15 ? { ...x, status: "pronto", bytes: 19_136_512, expira: horas(24) } : x))), 2500);
  };
  const baixar = (x) => {
    setLista((l) => l.map((y) => (y.id === x.id ? { ...y, downloads: y.downloads + 1 } : y)));
    setAviso("Download iniciado e registrado nas atividades da empresa.");
  };
  const situacao = (x) => {
    if (x.status === "pendente") return ["Gerando", "info"];
    if (x.status === "falhou") return ["Falhou", "danger"];
    return x.expira > AGORA ? ["Pronto", "success"] : ["Vencido", "neutral"];
  };

  const colunas = [
    { key: "pedido", label: "Pedido em" }, { key: "por", label: "Por" }, { key: "situacao", label: "Situação" },
    { key: "tamanho", label: "Tamanho", align: "right" }, { key: "validade", label: "Válido até" },
    { key: "downloads", label: "Downloads", align: "right" }, { key: "acao", label: "" },
  ];
  const linhas = lista.map((x) => {
    const [rot, tom] = situacao(x);
    const pode = rot === "Pronto";
    return {
      id: x.id, pedido: dt(x.pedido), por: x.por,
      situacao: StatusBadge ? <StatusBadge tone={tom}>{rot}</StatusBadge> : rot,
      tamanho: x.bytes ? tam(x.bytes) : "—", validade: x.expira ? dt(x.expira) : "—", downloads: x.downloads,
      acao: pode ? <Btn size="sm" onClick={() => baixar(x)}>Baixar</Btn>
        : rot === "Vencido" ? <span className="exd-nota">Peça de novo</span> : null,
    };
  });

  const acoes = <>
    <label className="exd-papel"><input type="checkbox" checked={dono} onChange={(e) => setDono(e.target.checked)} /> Ver como o dono</label>
    <Btn variant="primary" disabled={!dono || !!pendente} onClick={pedir}>{pendente ? "Gerando…" : "Exportar os dados"}</Btn>
  </>;

  return (
    <div className="os-page exd-page" data-screen-label="Sistema · Exportar dados da empresa">
      {PageHeader
        ? <PageHeader title="Exportar dados da empresa" subtitle="Um arquivo .zip com uma planilha CSV por tabela, só com os dados desta empresa." actions={acoes} />
        : <header className="os-page-h"><h1>Exportar dados da empresa</h1><div>{acoes}</div></header>}
      {aviso && Alert && <Alert tone="success" title={aviso} onClose={() => setAviso(null)} />}
      {!dono && Alert && <Alert tone="warn" title="Só o dono da empresa exporta os dados">
        O arquivo tem dados pessoais de clientes e funcionários. Peça ao dono da empresa.</Alert>}
      <div className="exd-fatos">
        <div><b>O que vai</b><p>Vendas, compras, estoque, financeiro, fiscal, OS, clientes, fornecedores e o registro de atividades.</p></div>
        <div><b>O que não vai</b><p>Certificado digital, chaves de banco e de integração, e senhas de usuário.</p></div>
        <div><b>Segurança</b><p>O link vale 24 horas e cada download fica registrado nas atividades.</p></div>
      </div>
      {dono && (DataTable ? <DataTable columns={colunas} rows={linhas} />
        : <table className="os-table"><tbody>{linhas.map((l) => <tr key={l.id}><td>{l.pedido}</td><td>{l.validade}</td></tr>)}</tbody></table>)}
    </div>);
}
window.ExportarDadosPage = ExportarDadosPage;
})();
