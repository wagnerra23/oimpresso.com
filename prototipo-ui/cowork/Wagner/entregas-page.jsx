// entregas-page.jsx — Produção · Entregas e instalação (rota nova "entregas", aprovada por [W] em 06/10).
// Do pronto até o cliente assinar: agenda de equipe, campo com foto e assinatura, protocolo.
// Usa o processo "entrega" de fluxos-processos.jsx via OiEtapaPainel. Expõe window.EntregasPage.
(() => {
const { useState } = React;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const ENTREGAS = [
  { id: "ENT-0412", os: "OS-2291", cliente: "Rota Livre Confecções", end: "Rua das Palmeiras, 120 · Gravatal", servico: "Fachada ACM 6×1,2 m + letra caixa", janela: "08/10 · 08h–12h", equipe: "Equipe A · Fiorino", etapa: "Agendado", altura: true },
  { id: "ENT-0411", os: "OS-2288", cliente: "Padaria Pão Dourado", end: "Av. Central, 455 · Tubarão", servico: "Adesivo de vitrine 3 m²", janela: "", equipe: "", etapa: "Pronto", altura: false },
  { id: "ENT-0410", os: "OS-2284", cliente: "Clínica Sorriso", end: "Rua XV, 88 · Laguna", servico: "Placa PS 2 mm + totem", janela: "07/10 · 14h–17h", equipe: "Equipe B · Strada", etapa: "Em campo", altura: false },
  { id: "ENT-0409", os: "OS-2279", cliente: "Martinho Auto Center", end: "BR-101 km 330 · Capivari", servico: "Lona 440 g 8×3 m com estrutura", janela: "05/10 · 09h–11h", equipe: "Equipe A · Fiorino", etapa: "Instalado", altura: true },
  { id: "ENT-0408", os: "OS-2270", cliente: "Mercado Bom Preço", end: "Rua do Comércio, 12 · Tubarão", servico: "Banners 4 un (retirada)", janela: "04/10", equipe: "Balcão", etapa: "Entregue", altura: false },
];

function EntregasPage() {
  const { PageHeader, DataGrid, Drawer, Button, Checkbox } = ds();
  const [sel, setSel] = useState(null);
  const [campo, setCampo] = useState({});
  const [, tick] = useState(0);
  React.useEffect(() => { const h = () => tick((n) => n + 1); window.addEventListener("oi-etapa", h); return () => window.removeEventListener("oi-etapa", h); }, []);
  if (!PageHeader || !DataGrid || !window.OiEtapaPainel) return <div role="status" style={{ padding: 20 }}>Carregando entregas…</div>;
  const etapaDe = (e) => { const s = window.OiEtapa && window.OiEtapa.ler()["entrega:" + e.id]; return (s && s.estado) || e.etapa; };
  const e = ENTREGAS.find((x) => x.id === sel);
  const c = (e && campo[e.id]) || {};
  const setC = (k, v) => setCampo({ ...campo, [e.id]: { ...c, [k]: v } });
  const bloq = e ? {
    "Agendar": !e.equipe ? "Sem equipe e veículo livres na janela — escolha a equipe" : "",
    "Concluir instalação": !(c.foto && c.assinatura) ? "Falta " + [!c.foto && "foto do serviço", !c.assinatura && "assinatura do cliente"].filter(Boolean).join(" e ") : "",
  } : {};
  const abertas = ENTREGAS.filter((x) => etapaDe(x) !== "Entregue");
  const Chk = ({ k, l }) => Checkbox ? <Checkbox checked={!!c[k]} onChange={(v) => setC(k, typeof v === "boolean" ? v : !c[k])} label={l} />
    : <label className="ent-chk"><input type="checkbox" checked={!!c[k]} onChange={(ev) => setC(k, ev.target.checked)} /> {l}</label>;
  return (
    <div className="ent-root" data-screen-label="Entregas e instalação">
      <PageHeader title="Entregas e instalação" stats={[{ value: abertas.length, label: "em aberto" }, { value: ENTREGAS.filter((x) => x.altura && etapaDe(x) !== "Entregue").length, label: "em altura", tone: "warn" }]} />
      <div className="ent-body">
        <DataGrid caption="Entregas" totalLabel="entregas"
          columns={[{ key: "id", label: "Entrega", mono: true, width: 110 }, { key: "cli", label: "Cliente e serviço" }, { key: "jan", label: "Janela" }, { key: "eq", label: "Equipe" }, { key: "et", label: "Etapa" }]}
          rows={ENTREGAS.map((x) => ({ id: x.id, cells: { id: { primary: x.id, sub: x.os }, cli: { primary: x.cliente, sub: x.servico }, jan: x.janela || "—", eq: x.equipe || "—", et: etapaDe(x) } }))}
          onRowClick={(row) => setSel(row.id)} page={1} pageSize={20} pageSizeOptions={[20]} onPageChange={() => {}} />
      </div>
      <Drawer open={!!e} onClose={() => setSel(null)} width={540} title={e ? e.cliente : ""} subtitle={e ? e.id + " · " + e.os + " · " + e.end : ""}
        footer={e && <Button variant="ghost" onClick={() => setSel(null)}>Fechar</Button>}>
        {e && <div className="ent-det">
          <p className="ent-serv">{e.servico}{e.altura ? " · trabalho em altura" : ""}</p>
          <window.OiEtapaPainel proc="entrega" docId={e.id} estado={e.etapa} bloqueios={bloq}
            resolver={{ "Agendar": { label: "Usar Equipe B · Strada (livre amanhã 14h–17h)", onClick: () => { e.equipe = "Equipe B · Strada"; e.janela = "amanhã · 14h–17h"; tick((n) => n + 1); } } }} />
          {etapaDe(e) === "Em campo" && (
            <section className="ent-campo">
              <span className="oie-l">Registro de campo</span>
              <Chk k="foto" l="Foto do serviço instalado" />
              <Chk k="assinatura" l="Assinatura do cliente" />
            </section>
          )}
        </div>}
      </Drawer>
    </div>
  );
}
window.EntregasPage = EntregasPage;
})();
