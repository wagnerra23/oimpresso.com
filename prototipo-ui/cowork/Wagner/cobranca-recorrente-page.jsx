// cobranca-recorrente-page.jsx — Cobrança Recorrente · F1 [CC] · thread 00 (puxar o vivo) [CL]
// Reescreve a RecurringBilling do git na linguagem do DS (Cockpit V2 warm):
//   stone + roxo var(--accent) · rounded-md · escala warm emerald/amber/rose ·
//   KPI hero warm (var(--text), NÃO bg-zinc-900) · DRAWER LATERAL (não modal/coluna).
// Sub-nav espelha o git RecurringBilling: Assinaturas · Planos · Faturas · Configurações.
// Uma rota por Index (vista fixa) — tabela em window.RbRotas. Planos Create/Edit = drawer
// aberto pelos botões "Novo plano" / "Editar" (sem rota própria: rota nova exige app.jsx).
// Reprodutível: todo "hoje" sai de HOJE (fixo, coerente com o mock de jun/2026).
// Expõe window.CobrancaRecorrentePage + window.RbRotas. Persona: Eliana [E] / Larissa (balcão 1280px).
// Fonte de design ([W] 2026-11-19, b4): este arquivo é o design que a produção segue, não retrato dela.
// Correções da auditoria 2026-11-19 (f1–f6): thread 02 do playbook recorrente.
(function () {
  const { useState, useEffect, useMemo, useRef } = React;

  // ── relógio fixo do mock (medida reprodutível) ─────────────────
  const HOJE = new Date("2026-06-01T12:00");
  const HOJE_ISO = "2026-06-01";

  // ── helpers ────────────────────────────────────────────────────
  const BRL = (n) => (n || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  const hueFor = (name) => {let h = 0;for (let i = 0; i < name.length; i++) h = h * 31 + name.charCodeAt(i) >>> 0;return h % 360;};
  const initials = (name) => (name || "").split(/\s+/).slice(0, 2).map((w) => w[0] || "").join("").toUpperCase();
  const MES = ["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"];
  function daysFromToday(iso) {if (!iso) return null;const d = new Date(iso + "T12:00");return Math.round((d - HOJE) / 86400000);}
  function nextLabel(iso) {const dd = daysFromToday(iso);if (dd === null) return "—";if (dd === 0) return "hoje";if (dd === 1) return "amanhã";if (dd < 0) return `há ${-dd} dias`;return `em ${dd} dias`;}
  function dueLabel(iso) {const dd = daysFromToday(iso);if (dd === null) return "—";if (dd === 0) return "hoje";if (dd < 0) return `há ${-dd}d`;return `em ${dd}d`;}
  function dateBR(iso) {if (!iso) return "—";const p = iso.split("-");return `${+p[2]} ${MES[+p[1] - 1]}`;}
  function dateFull(iso) {if (!iso) return "—";const p = iso.split("-");return `${p[2]}/${p[1]}/${p[0]}`;}
  function sinceLabel(iso) {const dd = daysFromToday(iso);if (dd === null) return "—";const m = Math.round(-dd / 30);if (m < 1) return "este mês";if (m < 12) return `há ${m}m`;return `há ${Math.round(m / 12)}a`;}

  // Valor em campo de formulário: sempre pt-BR com vírgula e 2 casas (nunca String(n), que escreve
  // "450.5" — o ponto vira separador de milhar no parser pt-BR, incidente num_uf). A máscara digita
  // da direita pra esquerda em centavos: "45050" → "450,50".
  const valorCampo = (n) => n === "" || n == null ? "" : Number(n).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const mascaraValor = (txt) => {const d = String(txt).replace(/\D/g, "").replace(/^0+(?=\d)/, "");return d ? valorCampo(Number(d) / 100) : "";};

  // Ciclo em meses (custom usa os dias do plano). MRR conta só assinatura que está cobrando de fato.
  const CYCLE = { mensal: "mensal", trimestral: "trimestral", semestral: "semestral", anual: "anual", custom: "customizado" };
  const CYCLE_DIV = { mensal: 1, trimestral: 3, semestral: 6, anual: 12 };
  const mesesDoCiclo = (cycle, dias) => cycle === "custom" ? Math.max(Number(dias) || 30, 1) / 30 : CYCLE_DIV[cycle] || 1;
  const COBRANDO = (s) => s.status === "em_dia" || s.status === "retentando";

  // Contato: mascarado por padrão, "Mostrar" revela (dado pessoal do cliente).
  const mascaraFone = (f) => f.replace(/(\d{4,5})-(\d{4})$/, (_, a, b) => a.replace(/\d/g, "•") + "-" + b);
  const mascaraEmail = (e) => e.replace(/^(.)[^@]*/, "$1•••");
  const mascaraCnpj = (c) => c.replace(/^(\d{2})\.\d{3}\.\d{3}/, "$1.•••.•••");

  // Estado de demonstração: ?estado=carregando|erro|sem_permissao|vazio (sem o parâmetro = normal).
  const ESTADO = (typeof location !== "undefined" && new URLSearchParams(location.search).get("estado")) || "normal";

  // estilos de formulário/tabela — só tokens do DS (sem CSS novo)
  const FIELD = { width: "100%", boxSizing: "border-box", height: 32, padding: "0 10px", fontSize: 13, color: "var(--text)", background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 6, outline: "none" };
  const AREA = { ...FIELD, height: 64, padding: "6px 10px", resize: "vertical", fontFamily: "inherit" };
  const LBL = { display: "block", fontSize: 11.5, fontWeight: 500, color: "var(--text-dim)", marginBottom: 4 };
  const HINT = { fontSize: 11, color: "var(--text-mute)", marginTop: 3 };
  const TH = { padding: "8px 14px", fontSize: 10.5, textTransform: "uppercase", letterSpacing: "0.06em", fontWeight: 500, color: "var(--text-mute)", textAlign: "left", borderBottom: "1px solid var(--border)", whiteSpace: "nowrap" };
  const TD = { padding: "10px 14px", fontSize: 13, color: "var(--text-dim)", borderBottom: "1px solid var(--border)", verticalAlign: "top" };
  const MONO = { fontFamily: "ui-monospace,monospace", fontSize: 12, fontVariantNumeric: "tabular-nums" };
  const SEC = { margin: "16px 24px 0" };
  const CHIP = (on) => ({ display: "inline-flex", alignItems: "center", gap: 6, padding: "5px 10px", fontSize: 12, fontWeight: 500, borderRadius: 999, cursor: "pointer", border: "1px solid " + (on ? "var(--accent)" : "var(--border)"), background: on ? "var(--accent-soft)" : "var(--surface)", color: on ? "var(--accent)" : "var(--text-dim)" });
  const Req = () => <span style={{ color: "var(--accent)" }}> *</span>;

  // ── mock domínio gráfica (contratos recorrentes) ───────────────
  const SUBS = [
  { id: "AS-1042", client: "Padaria Pão Quente", cnpj: "12.448.770/0001-22", plan: "Sinalização Mensal", cycle: "mensal", since: "2024-08-12", method: "pix", status: "em_dia", retry: null, nextAt: "2026-06-10", nextValue: 450, paid: 22, missed: 0, ltv: 9900, fiscal: "nfse", nf: "NFS-e 1188", os: "OS-3307", fav: true, contact: { name: "Dona Marli", phone: "(11) 99632-1180", email: "marli@paoquente.com.br" }, note: null, pixAuto: { status: "APROVADA" } },
  { id: "AS-1051", client: "Auto Posto Trevo", cnpj: "08.911.332/0001-09", plan: "Adesivos Frota Mensal", cycle: "mensal", since: "2025-02-03", method: "boleto", status: "retentando", retry: 1, nextAt: "2026-06-05", nextValue: 680, paid: 15, missed: 1, ltv: 10200, fiscal: "nfe", nf: "NFe 4471", os: "OS-3312", fav: false, contact: { name: "Cláudio Reis", phone: "(11) 98115-4420", email: "financeiro@postotrevo.com.br" }, note: "Boleto venceu — cliente pediu 2ª via no WhatsApp." },
  { id: "AS-1063", client: "Restaurante Sabor Caseiro", cnpj: "21.005.118/0001-77", plan: "Impressão Trimestral", cycle: "trimestral", since: "2024-11-20", method: "card", status: "em_dia", retry: null, nextAt: "2026-07-20", nextValue: 1200, paid: 6, missed: 0, ltv: 7200, fiscal: "nfe", nf: "NFe 4402", os: null, fav: false, contact: { name: "Sr. Antônio", phone: "(11) 97744-3301", email: "antonio@saborcaseiro.com.br" }, note: null, cartao: { ultimos4: "8829", bandeira: "Mastercard", tentativas: [{ n: 1, status: "soft_decline", em: "2026-05-20", motivo: "Transação não autorizada. Saldo insuficiente." }, { n: 2, status: "succeeded", em: "2026-05-21", motivo: null }] } },
  { id: "AS-1070", client: "Academia CorpoForte", cnpj: "33.671.904/0001-50", plan: "Sinalização Mensal", cycle: "mensal", since: "2025-01-15", method: "boleto", status: "falhou", retry: 3, nextAt: "2026-05-28", nextValue: 450, paid: 13, missed: 3, ltv: 5850, fiscal: "nfse", nf: null, os: "OS-3290", fav: true, contact: { name: "Renata Lima", phone: "(11) 99280-7765", email: "renata@corpoforte.com.br" }, note: "3 tentativas sem sucesso. Avaliar suspensão." },
  { id: "AS-1088", client: "Clínica Vida", cnpj: "45.220.661/0001-18", plan: "Manutenção Fachada Anual", cycle: "anual", since: "2024-03-01", method: "pix", status: "em_dia", retry: null, nextAt: "2027-03-01", nextValue: 3600, paid: 2, missed: 0, ltv: 7200, fiscal: "nfse", nf: "NFS-e 1102", os: "OS-3155", fav: false, contact: { name: "Dra. Helena", phone: "(11) 98870-2244", email: "adm@clinicavida.com.br" }, note: null, pixAuto: { status: "CRIADA", copiaCola: "00020126180014br.gov.bcb.pix2558pix.exemplo.com/qr/v2/rec/AS-1088" } },
  { id: "AS-1094", client: "Mercado União", cnpj: "55.118.022/0001-31", plan: "Adesivos Frota Mensal", cycle: "mensal", since: "2024-09-09", method: "boleto", status: "retentando", retry: 2, nextAt: "2026-06-03", nextValue: 680, paid: 19, missed: 2, ltv: 12920, fiscal: "nfe", nf: "NFe 4468", os: null, fav: false, contact: { name: "Jair Souza", phone: "(11) 99044-1199", email: "jair@mercadouniao.com.br" }, note: null },
  { id: "AS-1101", client: "Escola Saber", cnpj: "60.337.481/0001-04", plan: "Impressão Trimestral", cycle: "trimestral", since: "2024-06-18", method: "boleto", status: "pausada", retry: null, nextAt: null, nextValue: 1200, paid: 7, missed: 0, ltv: 8400, fiscal: "nfe", nf: "NFe 4310", os: null, fav: false, contact: { name: "Coord. Paula", phone: "(11) 97331-8800", email: "paula@escolasaber.com.br" }, note: "Pausado nas férias escolares (jun–jul)." },
  { id: "AS-1109", client: "Farmácia Bem-Estar", cnpj: "71.992.150/0001-66", plan: "Sinalização Mensal", cycle: "mensal", since: "2025-03-22", method: "pix", status: "em_dia", retry: null, nextAt: "2026-06-02", nextValue: 450, paid: 11, missed: 0, ltv: 4950, fiscal: "nfse", nf: "NFS-e 1179", os: "OS-3321", fav: false, contact: { name: "Bruno Tavares", phone: "(11) 98220-5512", email: "bruno@bemestar.com.br" }, note: null },
  { id: "AS-1115", client: "Loja Bella Moda", cnpj: "82.004.773/0001-90", plan: "Sinalização Mensal", cycle: "mensal", since: "2024-04-10", method: "card", status: "cancelada", retry: null, nextAt: null, nextValue: 450, paid: 9, missed: 1, ltv: 4050, fiscal: "nfse", nf: "NFS-e 0980", os: null, fav: false, contact: { name: "Camila Dias", phone: "(11) 99500-3344", email: "camila@bellamoda.com.br" }, churn: "loja fechou", canceladaEm: "2026-04-10", note: null }];


  const monthly = (s) => s.nextValue / mesesDoCiclo(s.cycle, s.dias);

  // Planos: os 4 do SUBS + 1 inativo. Contagem de assinaturas = ativas (não canceladas) do SUBS.
  const PLANOS_BASE = [
  { id: 11, name: "Sinalização Mensal", slug: "sinalizacao-mensal", curta: "Placas e banners trocados todo mês", completa: "", cycle: "mensal", dias: null, trial: 0, valor: 450, fiscal: "nfse", cfop: "", servico: "13.05", ativo: true },
  { id: 12, name: "Adesivos Frota Mensal", slug: "adesivos-frota-mensal", curta: "Reposição de adesivos de frota", completa: "", cycle: "mensal", dias: null, trial: 0, valor: 680, fiscal: "nfe", cfop: "5101", servico: "", ativo: true },
  { id: 13, name: "Impressão Trimestral", slug: "impressao-trimestral", curta: "Lote de impressos a cada 3 meses", completa: "", cycle: "trimestral", dias: null, trial: 0, valor: 1200, fiscal: "nfe", cfop: "5101", servico: "", ativo: true },
  { id: 14, name: "Manutenção Fachada Anual", slug: "manutencao-fachada-anual", curta: "Revisão anual de fachada e letreiro", completa: "", cycle: "anual", dias: null, trial: 0, valor: 3600, fiscal: "nfse", cfop: "", servico: "14.01", ativo: true },
  { id: 15, name: "Banner Promocional Semestral", slug: "banner-promocional-semestral", curta: "Campanha de vitrine a cada 6 meses", completa: "", cycle: "semestral", dias: null, trial: 7, valor: 900, fiscal: "none", cfop: "", servico: "", ativo: false }];

  const assinCount = (name) => SUBS.filter((s) => s.plan === name && COBRANDO(s)).length;

  // Faturas (~10) coerentes com o SUBS. Vencimentos ao redor de HOJE.
  const FATURAS_BASE = [
  { id: 901, numero: "FAT-2026-0512", client: "Padaria Pão Quente", cnpj: "12.448.770/0001-22", plan: "Sinalização Mensal", valor: 450, venc: "2026-05-10", status: "paga", pagoEm: "2026-05-10", gateway: "inter" },
  { id: 902, numero: "FAT-2026-0518", client: "Auto Posto Trevo", cnpj: "08.911.332/0001-09", plan: "Adesivos Frota Mensal", valor: 680, venc: "2026-05-05", status: "atrasada", pagoEm: null, gateway: "inter" },
  { id: 903, numero: "FAT-2026-0521", client: "Academia CorpoForte", cnpj: "33.671.904/0001-50", plan: "Sinalização Mensal", valor: 450, venc: "2026-05-28", status: "atrasada", pagoEm: null, gateway: "asaas" },
  { id: 904, numero: "FAT-2026-0524", client: "Mercado União", cnpj: "55.118.022/0001-31", plan: "Adesivos Frota Mensal", valor: 680, venc: "2026-05-03", status: "atrasada", pagoEm: null, gateway: "c6" },
  { id: 905, numero: "FAT-2026-0527", client: "Farmácia Bem-Estar", cnpj: "71.992.150/0001-66", plan: "Sinalização Mensal", valor: 450, venc: "2026-05-22", status: "paga", pagoEm: "2026-05-22", gateway: "asaas" },
  { id: 906, numero: "FAT-2026-0530", client: "Restaurante Sabor Caseiro", cnpj: "21.005.118/0001-77", plan: "Impressão Trimestral", valor: 1200, venc: "2026-04-20", status: "paga", pagoEm: "2026-04-19", gateway: "inter" },
  { id: 907, numero: "FAT-2026-0601", client: "Farmácia Bem-Estar", cnpj: "71.992.150/0001-66", plan: "Sinalização Mensal", valor: 450, venc: "2026-06-01", status: "paga", pagoEm: "2026-06-01", gateway: "asaas" },
  { id: 908, numero: "FAT-2026-0602", client: "Padaria Pão Quente", cnpj: "12.448.770/0001-22", plan: "Sinalização Mensal", valor: 450, venc: "2026-06-10", status: "pendente", pagoEm: null, gateway: "inter" },
  { id: 909, numero: "FAT-2026-0603", client: "Clínica Vida", cnpj: "45.220.661/0001-18", plan: null, valor: 820, venc: "2026-06-15", status: "pendente", pagoEm: null, gateway: "inter" },
  { id: 910, numero: "FAT-2026-0604", client: "Restaurante Sabor Caseiro", cnpj: "21.005.118/0001-77", plan: "Impressão Trimestral", valor: 1200, venc: "2026-07-20", status: "pendente", pagoEm: null, gateway: "c6" },
  { id: 911, numero: "FAT-2026-0490", client: "Loja Bella Moda", cnpj: "82.004.773/0001-90", plan: "Sinalização Mensal", valor: 450, venc: "2026-04-10", status: "cancelada", pagoEm: null, gateway: "asaas" },
  // Saídas de uma fatura paga: estorno (devolvido pelo gateway), reembolso (devolvido por fora),
  // chargeback (contestação do cartão) e paga parcial (valor menor que o da fatura — não quita).
  { id: 912, numero: "FAT-2026-0498", client: "Escola Saber", cnpj: "60.337.481/0001-04", plan: "Impressão Trimestral", valor: 1200, venc: "2026-04-18", status: "estornada", pagoEm: "2026-04-18", gateway: "asaas", motivo: "duplicidade" },
  { id: 913, numero: "FAT-2026-0505", client: "Farmácia Bem-Estar", cnpj: "71.992.150/0001-66", plan: "Sinalização Mensal", valor: 450, venc: "2026-04-22", status: "reembolsada", pagoEm: "2026-04-22", gateway: "inter", motivo: "solicitação do cliente" },
  { id: 914, numero: "FAT-2026-0509", client: "Restaurante Sabor Caseiro", cnpj: "21.005.118/0001-77", plan: "Impressão Trimestral", valor: 1200, venc: "2026-04-20", status: "chargeback", pagoEm: "2026-04-20", gateway: "asaas", motivo: "contestação no cartão" },
  { id: 915, numero: "FAT-2026-0526", client: "Mercado União", cnpj: "55.118.022/0001-31", plan: "Adesivos Frota Mensal", valor: 680, venc: "2026-05-20", status: "parcial", pagoEm: "2026-05-21", valorPago: 400, gateway: "c6" }];


  // ── átomos ─────────────────────────────────────────────────────
  function Avatar({ name, size = 30 }) {
    const h = hueFor(name);
    return <span className="cr-av" style={{ width: size, height: size, fontSize: size * 0.38, background: `linear-gradient(135deg, oklch(0.68 0.10 ${h}), oklch(0.50 0.13 ${h}))` }}>{initials(name)}</span>;
  }
  // Um vocabulário só para a situação da assinatura: pill, filtro lateral e busca usam ST_LABEL.
  // (O painel de etapa mostra outra coisa — a etapa do processo de contrato — e diz isso no rótulo.)
  const ST_LABEL = { em_dia: "em dia", retentando: "retentando", falhou: "falhou", pausada: "pausada", cancelada: "cancelada" };
  const cap = (t) => t.charAt(0).toUpperCase() + t.slice(1);
  function StatusPill({ status, retry, method }) {
    // retentativas = as da régua da forma de pagamento (Configurações), contadas depois da 1ª cobrança
    const max = (REGUA[method] || REGUA.boleto).length;
    if (status === "retentando" && retry != null) {
      return <span className={"cr-pill " + status}>
        <span className="cr-retry-dots" aria-hidden="true">{Array.from({ length: max }, (_, i) => <i key={i} className={i < retry ? "on" : ""} />)}</span>
        retentando {retry}/{max}
      </span>;
    }
    if (status === "falhou" && retry != null) return <span className={"cr-pill " + status}>falhou {retry}×</span>;
    return <span className={"cr-pill " + status}>{ST_LABEL[status]}</span>;
  }
  const METHOD = { pix: "Pix", boleto: "Boleto", card: "Cartão" };
  const FISCAL = { nfe: { t: "NFe", l: "NFe · Nota Fiscal Eletrônica" }, nfse: { t: "NFS-e", l: "NFS-e · Nota de Serviços" }, none: { t: "Não emite", l: "Sem emissão fiscal" } };
  const GATEWAY = { inter: "Banco Inter", c6: "C6 Bank", asaas: "Asaas" };

  // KPI simples (mesma casca do hero warm)
  function Kpi({ hero, label, value, hint, tone }) {
    return (
      <div className={"cr-stat" + (hero ? " cr-stat-hero" : "")}>
        <small>{label}</small>
        <b className={tone === "neg" ? "cr-num-neg" : ""}>{value}</b>
        {hint && <span className="cr-stat-hint">{hint}</span>}
      </div>);

  }

  // Diálogo (drawer ou confirmação): foco entra no 1º controle ao abrir, Tab fica preso dentro,
  // Esc fecha e o foco volta para quem abriu. Listener registrado uma vez (onClose via ref): se ele
  // re-registrasse a cada render, um re-render no meio do mesmo Esc o tiraria da fila do evento.
  const FOCAVEIS = 'button:not([disabled]),[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
  function useDialogo(ref, onClose) {
    const fechaRef = useRef(onClose);
    fechaRef.current = onClose;
    useEffect(() => {
      const el = ref.current;if (!el) return;
      const anterior = document.activeElement;
      const lista = () => Array.from(el.querySelectorAll(FOCAVEIS));
      (lista()[0] || el).focus();
      const k = (e) => {
        if (e.key === "Escape") {e.stopPropagation();fechaRef.current();return;}
        if (e.key !== "Tab") return;
        const f = lista();if (!f.length) return;
        if (e.shiftKey && document.activeElement === f[0]) {e.preventDefault();f[f.length - 1].focus();} else
        if (!e.shiftKey && document.activeElement === f[f.length - 1]) {e.preventDefault();f[0].focus();}
      };
      el.addEventListener("keydown", k);
      return () => {el.removeEventListener("keydown", k);anterior && anterior.focus && anterior.focus();};
    }, []);
  }

  // casca de drawer lateral reaproveitada pelos formulários
  function FormDrawer({ eyebrow, title, sub, onClose, foot, children }) {
    const ref = useRef(null);
    useDialogo(ref, onClose);
    return (
      <>
        <div className="cr-drawer-ov" onClick={onClose} />
        <aside ref={ref} className="cr-drawer" role="dialog" aria-modal="true" aria-label={title} tabIndex={-1}>
          <div className="cr-dwr-head">
            <div style={{ flex: 1, minWidth: 0 }}>
              {eyebrow && <div className="cr-eyebrow">{eyebrow}</div>}
              <h3>{title}</h3>
              {sub && <div style={{ fontSize: 12, color: "var(--text-mute)", marginTop: 2 }}>{sub}</div>}
            </div>
            <button className="cr-x" onClick={onClose} aria-label="Fechar"><I.x size={16} /></button>
          </div>
          <div className="cr-dwr-body">{children}</div>
          <div className="cr-dwr-foot">{foot}</div>
        </aside>
      </>);

  }

  function Field({ label, req, hint, children }) {
    return <label style={{ display: "block" }}><span style={LBL}>{label}{req && <Req />}</span>{children}{hint && <div style={HINT}>{hint}</div>}</label>;
  }

  // diálogo de confirmação centrado (overlay do drawer + cartão)
  function Confirm({ title, children, onClose, foot }) {
    const ref = useRef(null);
    useDialogo(ref, onClose);
    return (
      <>
        <div className="cr-drawer-ov" style={{ zIndex: 55 }} onClick={onClose} />
        <div ref={ref} className="cr-confirm" role="alertdialog" aria-modal="true" aria-label={title} tabIndex={-1}>
          <h3 style={{ margin: 0, fontSize: 15, fontWeight: 600, color: "var(--text)" }}>{title}</h3>
          <div style={{ marginTop: 10, fontSize: 13, color: "var(--text-dim)", lineHeight: 1.5 }}>{children}</div>
          <div style={{ marginTop: 16, display: "flex", justifyContent: "flex-end", gap: 8 }}>{foot}</div>
        </div>
      </>);

  }

  // ══════════════════════════════════════════════════════════════
  // ASSINATURAS  (Page RecurringBilling/Index)
  // ══════════════════════════════════════════════════════════════
  function KpiStrip({ subs }) {
    // MRR e "ativas" = só o que está cobrando (em dia ou retentando); pausada e falhou não somam.
    // Churn do mês = canceladas DENTRO do mês corrente ÷ quem estava cobrando no começo do mês.
    const ativos = subs.filter(COBRANDO);
    const mrr = ativos.reduce((a, s) => a + monthly(s), 0);
    const churn = subs.filter((s) => s.status === "cancelada" && s.canceladaEm && s.canceladaEm.slice(0, 7) === HOJE_ISO.slice(0, 7)).length;
    const churnRate = ativos.length + churn ? Math.round(churn / (ativos.length + churn) * 100) : 0;
    const churnTotal = subs.filter((s) => s.status === "cancelada").length;
    const prox = ativos.filter((s) => s.nextAt && daysFromToday(s.nextAt) >= 0).sort((a, b) => a.nextAt.localeCompare(b.nextAt));
    const primeira = prox[0];
    const doDia = primeira ? prox.filter((s) => s.nextAt === primeira.nextAt) : [];
    const falhas = subs.filter((s) => s.status === "falhou" || s.status === "retentando");
    const falhaVal = falhas.reduce((a, s) => a + s.nextValue, 0);
    return (
      <div className="cr-stats">
        <div className="cr-stat cr-stat-hero">
          <small>MRR · receita recorrente</small>
          <b>{BRL(mrr)}</b>
          <span className="cr-stat-hint"><b className="cr-num-pos">{ativos.length}</b> assinaturas ativas · ticket médio <b>{BRL(mrr / Math.max(ativos.length, 1))}</b></span>
        </div>
        <Kpi label="Churn este mês" value={`${churn} ${churn === 1 ? "cancelamento" : "cancelamentos"}`} hint={`taxa ${churnRate}% · ${churnTotal} no total`} />
        <Kpi label="Próxima cobrança" value={primeira ? nextLabel(primeira.nextAt) : "—"} hint={`${BRL(doDia.reduce((a, s) => a + s.nextValue, 0))} · ${doDia.length} ${doDia.length === 1 ? "cobrança" : "cobranças"}`} />
        <Kpi label="Retentando ou falhou" value={falhas.length} tone={falhas.length ? "neg" : ""} hint={`requer ação · ${BRL(falhaVal)} a recuperar`} />
      </div>);

  }

  function SubRow({ sub, active, onOpen, fav, onFav }) {
    return (
      <div className={"cr-row" + (active ? " on" : "")}>
        <button className={"cr-star" + (fav ? " on" : "")} aria-pressed={fav} aria-label={`Favoritar ${sub.client}`} title={fav ? "Desfavoritar" : "Favoritar"} onClick={onFav}>
          {fav ? <I.starFill size={14} /> : <I.star size={14} />}
        </button>
        {/* Abrir é um botão de verdade (teclado: Tab + Enter/Espaço), não um div clicável. */}
        <button type="button" className="cr-row-open" onClick={onOpen} aria-label={`Abrir assinatura de ${sub.client}`} aria-expanded={active}>
        <Avatar name={sub.client} />
        <div className="cr-row-main">
          <div className="cr-row-title">{sub.client}</div>
          <div className="cr-row-sub">{sub.plan} · {CYCLE[sub.cycle]} · desde {sinceLabel(sub.since)}{sub.nextAt ? ` · próx. ${dateBR(sub.nextAt)}` : ""}</div>
        </div>
        <div className="cr-row-right">
          <StatusPill status={sub.status} retry={sub.retry} method={sub.method} />
          {sub.status !== "cancelada" && sub.status !== "pausada" &&
          <span className="cr-row-val">{METHOD[sub.method]} · {BRL(sub.nextValue)}</span>
          }
        </div>
        </button>
      </div>);

  }

  // Pix Automático do BCB (US-RB-020 · decisão [W] 2026-10-26): o cliente aprova uma vez no app do
  // banco pelo QR; depois cada fatura é cobrada sozinha de 10 a 2 dias antes do vencimento.
  // Estados = status da recorrência na API Pix (sem mandato · CRIADA · APROVADA · REJEITADA/EXPIRADA/CANCELADA).
  function PixAutoCard({ sub }) {
    const [mandato, setMandato] = useState(sub.pixAuto || null);
    const [copiado, setCopiado] = useState(false);
    const status = mandato ? mandato.status : null;
    const pedir = () => setMandato({ status: "CRIADA", copiaCola: "00020126180014br.gov.bcb.pix2558pix.exemplo.com/qr/v2/rec/" + sub.id });
    const copiar = () => {navigator.clipboard && navigator.clipboard.writeText(mandato.copiaCola);setCopiado(true);setTimeout(() => setCopiado(false), 1500);};
    const texto = {
      CRIADA: "Aguardando o cliente aprovar no app do banco.",
      APROVADA: "Autorizado. Cada fatura é cobrada sozinha, de 10 a 2 dias antes do vencimento.",
      REJEITADA: "O cliente recusou a autorização.",
      EXPIRADA: "A autorização expirou sem resposta do cliente.",
      CANCELADA: "A autorização foi cancelada."
    };
    return (
      <div className="cr-card" data-contract="pix-automatico">
        <div className="cr-blk-label">Pix Automático</div>
        <div style={{ fontSize: 12.5, color: "var(--text-dim)", marginTop: 5 }}>
          {status ? texto[status] : "Cobrança automática por Pix: o cliente autoriza uma vez no app do banco."}
        </div>
        {status === "CRIADA" &&
        <div style={{ display: "flex", gap: 12, alignItems: "flex-start", marginTop: 10 }}>
            <div role="img" aria-label="QR Code da autorização" style={{ width: 96, height: 96, flex: "none", border: "1px solid var(--border)", borderRadius: 6, display: "grid", placeItems: "center", fontSize: 10.5, color: "var(--text-mute)" }}>QR</div>
            <div style={{ minWidth: 0, flex: 1 }}>
              <div style={{ fontFamily: "ui-monospace,monospace", fontSize: 11, color: "var(--text-dim)", wordBreak: "break-all" }}>{mandato.copiaCola}</div>
              <button className="cr-act" style={{ marginTop: 8 }} onClick={copiar}><I.copy size={12} /> {copiado ? "Copiado!" : "Copiar Pix copia e cola"}</button>
            </div>
          </div>
        }
        {(!status || ["REJEITADA", "EXPIRADA", "CANCELADA"].includes(status)) &&
        <button className="cr-act" style={{ marginTop: 10 }} onClick={pedir}><I.refresh size={12} /> {status ? "Pedir nova autorização" : "Pedir autorização"}</button>
        }
      </div>);

  }

  // Cartão recorrente (US-RB-011/013 · reaberto por [W] 2026-10-27): só em assinatura no cartão.
  // O cartão fica salvo como token quando o cliente paga a 1ª fatura no cartão pela página do gateway;
  // o oimpresso nunca vê o número, por isso o bloco mostra só bandeira + final. Cada fatura é cobrada
  // nele no vencimento; recusa leve tenta de novo depois de 1, 3 e 7 dias (até 4 tentativas), recusa
  // definitiva para e avisa o cliente pelo WhatsApp. As tentativas são as da fatura em aberto.
  function CartaoRecorrenteCard({ sub }) {
    const c = sub.cartao || { ultimos4: null, bandeira: null, tentativas: [] };
    const rotulo = {
      succeeded: ["Aprovada", "em_dia"],
      sent: ["Enviada ao gateway", "pausada"],
      soft_decline: ["Recusa leve · tenta de novo", "retentando"],
      failed: ["Erro do gateway · tenta de novo", "retentando"],
      hard_decline: ["Recusa definitiva · cliente avisado", "falhou"]
    };
    return (
      <div className="cr-card" data-contract="cartao-recorrente">
        <div className="cr-blk-label">Cartão recorrente</div>
        <div style={{ fontSize: 12.5, color: "var(--text-dim)", marginTop: 5 }}>
          {c.ultimos4 ?
          `${c.bandeira || "Cartão"} final ${c.ultimos4}. Cada fatura é cobrada nele no vencimento.` :
          "Nenhum cartão salvo. O cartão fica salvo quando o cliente paga a primeira fatura no cartão."}
        </div>
        {c.tentativas.length > 0 &&
        <ol style={{ listStyle: "none", margin: "10px 0 0", padding: 0, display: "grid", gap: 6 }}>
            {c.tentativas.map((t) =>
          <li key={t.n} style={{ fontSize: 11.5, color: "var(--text-mute)" }}>
                <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
                  <span style={{ color: "var(--text)", fontWeight: 500 }}>{t.n}ª tentativa</span>
                  <span>{dateFull(t.em)}</span>
                  <span className={"cr-pill " + rotulo[t.status][1]}>{rotulo[t.status][0]}</span>
                </div>
                {t.motivo && <div style={{ marginTop: 2 }}>{t.motivo}</div>}
              </li>
          )}
          </ol>
        }
      </div>);

  }

  // Histórico dos últimos 12 meses derivado do contrato: só os meses de cobrança do ciclo, a partir
  // do "desde" e até o cancelamento; as falhas são as cobranças mais recentes. A legenda conta as
  // células da janela (não o total do contrato, que fica no "Cobranças pagas").
  function PaymentHeat({ sub }) {
    const passo = Math.max(Math.round(mesesDoCiclo(sub.cycle, sub.dias)), 1);
    const cells = useMemo(() => {
      const ini = new Date(sub.since + "T12:00");
      const fim = sub.canceladaEm ? new Date(sub.canceladaEm + "T12:00") : null;
      const out = Array.from({ length: 12 }, (_, i) => {
        const d = new Date(HOJE.getFullYear(), HOJE.getMonth() - 11 + i, 15);
        const desde = (d.getFullYear() - ini.getFullYear()) * 12 + d.getMonth() - ini.getMonth();
        let k = "none";
        if (desde >= 0 && desde % passo === 0 && !(fim && d > fim)) k = i === 11 ? "future" : "paid";
        return { k, mes: MES[d.getMonth()] };
      });
      let falhas = sub.missed;
      for (let i = 10; i >= 0 && falhas > 0; i--) if (out[i].k === "paid") {out[i].k = "missed";falhas--;}
      return out;
    }, [sub.id]);
    const n = (k) => cells.filter((c) => c.k === k).length;
    const ROT = { paid: "pago", missed: "falhou", future: "a cobrar", none: "sem cobrança" };
    return (
      <div className="cr-card">
        <div className="cr-blk-label">Histórico de pagamentos · 12 meses</div>
        <div className="cr-heat">{cells.map((c, i) => <div className="cr-heat-c" key={i} title={`${c.mes}: ${ROT[c.k]}`}><i className={c.k} /><small>{c.mes[0]}</small></div>)}</div>
        <div className="cr-heat-legend">
          <span><i className="paid" /> pago ({n("paid")})</span>
          <span><i className="missed" /> falhou ({n("missed")})</span>
          <span><i className="future" /> a cobrar</span>
          <span><i className="none" /> sem cobrança</span>
        </div>
      </div>);

  }

  function Drawer({ sub, onClose, onEdit }) {
    const [tab, setTab] = useState("detalhes");
    const [notas, setNotas] = useState([]);
    const [nota, setNota] = useState("");
    const [verContato, setVerContato] = useState(false);
    const [cancelar, setCancelar] = useState(false);
    const [motivo, setMotivo] = useState("");
    const ref = useRef(null);
    useDialogo(ref, () => {if (!cancelar) onClose();});
    useEffect(() => {setTab("detalhes");setNotas([]);setNota("");setVerContato(false);setCancelar(false);setMotivo("");}, [sub && sub.id]);
    if (!sub) return null;
    const inactive = sub.status === "cancelada" || sub.status === "pausada";
    const f = FISCAL[sub.fiscal] || FISCAL.none;
    const tlEvents = [
    ...notas,
    { kind: "cobrança", dot: "var(--pos)", by: "sistema", when: dateBR(sub.nextAt) || "—", body: sub.status === "falhou" ? "Cobrança recusada pelo banco" : "Próxima cobrança agendada" },
    { kind: "nf", dot: "var(--accent)", by: "sistema", when: "12 mai", body: `${f.t} emitida e enviada ao cliente` },
    { kind: "criou", dot: "var(--text-mute)", by: "Eliana", when: sinceLabel(sub.since), body: "Assinatura criada" }];
    const anotar = () => {
      const t = nota.trim();if (!t) return;
      setNotas((p) => [{ kind: "nota", dot: "var(--accent)", by: "você", when: "agora", body: t }, ...p]);setNota("");
    };

    return (
      <>
        <div className="cr-drawer-ov" onClick={onClose} />
        <aside ref={ref} className="cr-drawer" role="dialog" aria-modal="true" aria-labelledby="cr-dwr-titulo" tabIndex={-1}>
          <div className="cr-dwr-head">
            <Avatar name={sub.client} size={38} />
            <div style={{ flex: 1, minWidth: 0 }}>
              <div className="cr-eyebrow">Assinatura · {sub.id}</div>
              <h3 id="cr-dwr-titulo">{sub.client}</h3>
            </div>
            <StatusPill status={sub.status} retry={sub.retry} method={sub.method} />
            <button className="cr-act" title="Extrato da assinatura em PDF"><I.doc size={12} /> PDF</button>
            <button className="cr-x" onClick={onClose} aria-label="Fechar detalhe da assinatura" title="Fechar (Esc)"><I.x size={16} /></button>
          </div>

          <window.CliTabs className="cr-dwr-tabs" ariaLabel="Abas da assinatura" pad={18} size="sm"
            active={tab} onChange={setTab}
            tabs={[{ key: "detalhes", label: "Detalhes" }, { key: "ia", label: "Jana" }]} />

          <div className="cr-dwr-body">
            {tab === "detalhes" && <>
              {/* Etapa do processo de contrato (Ativo/Suspenso/Cancelado) — não é a situação da cobrança da pill. */}
              {window.OiEtapaPainel && <window.OiEtapaPainel modo="leitura" proc="contrato" docId={sub.id}
                estado={{ em_dia: "Ativo", retentando: "Ativo", falhou: "Suspenso", pausada: "Suspenso", cancelada: "Cancelado" }[sub.status] || "Ativo"}
                bloqueios={sub.status === "falhou" ? { "Reativar": sub.missed + " cobranças falharam — regularize ou renegocie antes" } : {}} />}
              {!inactive &&
              <div className={"cr-next-card " + sub.status}>
                  <div className="cr-blk-label">{sub.status === "falhou" ? "Ação manual" : "Próxima cobrança"}</div>
                  <div className="cr-next-row">
                    <div>
                      <div className="cr-next-when">{nextLabel(sub.nextAt)}</div>
                      <div className="cr-next-meta">{dateBR(sub.nextAt)} · ciclo {CYCLE[sub.cycle]}</div>
                    </div>
                    <div>
                      <div className="cr-next-val">{BRL(sub.nextValue)}</div>
                      <div className="cr-next-method">{METHOD[sub.method]}</div>
                    </div>
                  </div>
                </div>
              }

              <dl className="cr-kv">
                <div><dt>Plano</dt><dd>{sub.plan}</dd></div>
                <div><dt>Ciclo</dt><dd>{CYCLE[sub.cycle]}</dd></div>
                <div><dt>Cliente desde</dt><dd>{sinceLabel(sub.since)}</dd></div>
                <div><dt>CNPJ</dt><dd className="mono">{verContato ? sub.cnpj : mascaraCnpj(sub.cnpj)}</dd></div>
                <div><dt>Cobranças pagas</dt><dd className="mono">{sub.paid}</dd></div>
                <div><dt>Falhas</dt><dd className={"mono" + (sub.missed ? " cr-txt-neg" : "")}>{sub.missed}</dd></div>
                <div><dt>LTV acumulado</dt><dd className="mono">{BRL(sub.ltv)}</dd></div>
                <div><dt>OS recente</dt><dd className="mono">{sub.os || "—"}</dd></div>
                <div style={{ gridColumn: "1/-1" }}><dt>Contato</dt><dd>{sub.contact.name} · <span style={{ fontFamily: "ui-monospace,monospace", fontSize: 12 }}>{verContato ? sub.contact.phone : mascaraFone(sub.contact.phone)}</span> · {verContato ? sub.contact.email : mascaraEmail(sub.contact.email)}{" "}
                  <button type="button" className="cr-link" aria-pressed={verContato} onClick={() => setVerContato((v) => !v)}>{verContato ? "Ocultar" : "Mostrar"}</button></dd></div>
                {sub.churn && <><div className="cr-kv-sep" /><div style={{ gridColumn: "1/-1" }}><dt>Motivo do cancelamento</dt><dd>{sub.churn}</dd></div></>}
              </dl>

              {sub.note &&
              <div className="cr-card cr-note">
                  <div className="cr-blk-label">Nota fixada</div>
                  <div className="cr-note-txt">{sub.note}</div>
                </div>
              }

              <PaymentHeat sub={sub} />

              <div className="cr-card">
                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                  <div>
                    <span className={"cr-fiscal-badge " + sub.fiscal}>{f.t}</span>
                    <div style={{ fontSize: 10.5, color: "var(--text-mute)", marginTop: 5 }}>{sub.nf ? `${f.l} · última ${sub.nf}` : f.l}</div>
                  </div>
                  {sub.nf && <button className="cr-act" title={`Reenviar ${sub.nf} por e-mail/WhatsApp`}><I.send size={12} /> Reenviar</button>}
                </div>
              </div>

              {!inactive && <PixAutoCard sub={sub} />}

              {!inactive && sub.method === "card" && <CartaoRecorrenteCard sub={sub} />}

              <div>
                <div className="cr-blk-label">Notas &amp; eventos</div>
                <div style={{ display: "flex", gap: 6, margin: "8px 0 4px" }}>
                  <input style={FIELD} value={nota} onChange={(e) => setNota(e.target.value)} onKeyDown={(e) => {if (e.key === "Enter") anotar();}} placeholder="Anotar internamente…" aria-label="Anotar internamente" />
                  <button className="cr-act" onClick={anotar} disabled={!nota.trim()}>Anotar</button>
                </div>
                <div className="cr-tl">
                  {tlEvents.map((e, i) =>
                  <div className="cr-tl-item" key={i}>
                      <span className="cr-tl-dot" style={{ background: e.dot }} />
                      <div style={{ flex: 1 }}>
                        <div className="cr-tl-meta"><b>{e.kind}</b> · {e.by} · {e.when}</div>
                        <div className="cr-tl-body">{e.body}</div>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </>}

            {tab === "ia" &&
            <div className="cr-ai-card">
                <div className="cr-ai-head">Jana sugere</div>
                <div className="cr-ai-body">
                  {sub.status === "falhou" ?
                `${sub.contact.name} já falhou ${sub.missed}× neste contrato. Antes de suspender, vale um lembrete de Pix com a 2ª via — clientes de ${sub.plan.toLowerCase()} costumam regularizar em 48h quando avisados.` :
                sub.status === "retentando" ?
                `Boleto na ${sub.retry}ª tentativa. ${sub.client} tem ${sub.paid} pagamentos em dia e LTV de ${BRL(sub.ltv)} — é um bom pagador. Reenviar a 2ª via costuma resolver.` :
                `${sub.client} está em dia há ${sub.paid} ciclos. Boa hora pra oferecer um upgrade do "${sub.plan}" pro pacote anual (desconto + reduz churn).`}
                </div>
                <div className="cr-ai-actions">
                  <button className="cr-ai-chip">Gerar lembrete</button>
                  <button className="cr-ai-chip">Resumir contrato</button>
                  <button className="cr-ai-chip">Prever churn</button>
                </div>
              </div>
            }
          </div>

          <div className="cr-dwr-foot">
            {sub.status === "em_dia" && <button className="cr-act"><I.clock size={13} /> Pausar</button>}
            {(sub.status === "retentando" || sub.status === "falhou") && <>
              <button className="cr-act primary"><I.refresh size={13} /> Diagnosticar</button>
              <button className="cr-act"><I.clock size={13} /> Pausar</button>
            </>}
            {sub.status === "pausada" && <button className="cr-act primary"><I.refresh size={13} /> Reativar</button>}
            {sub.status !== "cancelada" && <button className="cr-act" onClick={onEdit}><I.pencil size={13} /> Editar</button>}
            <span className="cr-act-spacer" />
            {!inactive && <button className="cr-act danger" onClick={() => setCancelar(true)}><I.x size={13} /> Cancelar assinatura</button>}
          </div>
        </aside>
        {cancelar &&
        <Confirm title={`Cancelar a assinatura de ${sub.client}?`} onClose={() => {setCancelar(false);setMotivo("");}}
        foot={<><button className="cr-act" onClick={() => {setCancelar(false);setMotivo("");}}>Voltar</button><button className="cr-act danger" disabled={!motivo.trim()} onClick={() => {setCancelar(false);onClose();}}>Confirmar cancelamento</button></>}>
            <div>As cobranças futuras param e o Pix Automático ou o cartão salvo deixam de ser usados no gateway. Faturas já emitidas continuam em Faturas e podem ser canceladas uma a uma.</div>
            <div style={{ marginTop: 12 }}><Field label="Motivo" req><input style={FIELD} value={motivo} onChange={(e) => setMotivo(e.target.value)} placeholder="Ex: loja fechou, trocou de fornecedor" /></Field></div>
          </Confirm>
        }
      </>);

  }

  function EditarCobranca({ sub, onClose }) {
    const [valor, setValor] = useState(valorCampo(sub.nextValue));
    const [ciclo, setCiclo] = useState(sub.cycle);
    const [forma, setForma] = useState(sub.method);
    return (
      <FormDrawer eyebrow={"Assinatura · " + sub.id} title="Editar cobrança" sub={`${sub.client} · atualizar valor, ciclo ou forma de pagamento`} onClose={onClose}
      foot={<><span className="cr-act-spacer" /><button className="cr-act" onClick={onClose}>Cancelar</button><button className="cr-act primary" onClick={onClose}>Salvar alterações</button></>}>
        <Field label="Valor (R$)" req hint="Digite só os números; os centavos entram sozinhos."><input style={FIELD} inputMode="numeric" value={valor} onChange={(e) => setValor(mascaraValor(e.target.value))} placeholder="0,00" /></Field>
        <Field label="Ciclo" req>
          <select style={FIELD} value={ciclo} onChange={(e) => setCiclo(e.target.value)}>
            <option value="mensal">Mensal</option><option value="trimestral">Trimestral</option><option value="semestral">Semestral</option><option value="anual">Anual</option>
          </select>
        </Field>
        <Field label="Forma de pagamento" req>
          <div style={{ display: "flex", gap: 6 }}>
            {[["boleto", "Boleto"], ["pix", "Pix"], ["card", "Cartão"]].map(([k, l]) => <button key={k} type="button" aria-pressed={forma === k} style={CHIP(forma === k)} onClick={() => setForma(k)}>{l}</button>)}
          </div>
        </Field>
      </FormDrawer>);

  }

  function NovaAssinatura({ onClose }) {
    const [cli, setCli] = useState("");
    const [plano, setPlano] = useState("");
    const [valor, setValor] = useState("");
    const [ciclo, setCiclo] = useState("mensal");
    const [prox, setProx] = useState("2026-06-10");
    const [gw, setGw] = useState("inter");
    const [forma, setForma] = useState("boleto");
    const [desc, setDesc] = useState("");
    const sugestoes = cli.trim() ? SUBS.map((s) => s.client).filter((n, i, a) => a.indexOf(n) === i && n.toLowerCase().includes(cli.toLowerCase())).slice(0, 4) : [];
    const pickPlano = (id) => {
      setPlano(id);const p = PLANOS_BASE.find((x) => String(x.id) === id);
      if (p) {setValor(valorCampo(p.valor));setCiclo(p.cycle);}
    };
    return (
      <FormDrawer eyebrow="Assinatura" title="Nova assinatura" sub="Cadastrar cobrança recorrente para um cliente" onClose={onClose}
      foot={<><span className="cr-act-spacer" /><button className="cr-act" onClick={onClose}>Cancelar</button><button className="cr-act primary" onClick={onClose} disabled={!cli.trim() || !valor}>Criar assinatura</button></>}>
        <Field label="Cliente" req>
          <div className="cr-search" style={{ padding: "5px 10px" }}>
            <I.search size={14} style={{ color: "var(--text-mute)" }} />
            <input value={cli} onChange={(e) => setCli(e.target.value)} placeholder="Buscar cliente por nome ou CNPJ" aria-label="Buscar cliente por nome ou CNPJ" />
          </div>
          {sugestoes.length > 0 && <div style={{ display: "flex", flexWrap: "wrap", gap: 6, marginTop: 6 }}>{sugestoes.map((n) => <button key={n} type="button" aria-pressed={cli === n} style={CHIP(cli === n)} onClick={() => setCli(n)}>{n}</button>)}</div>}
        </Field>
        <Field label="Plano" hint="Opcional — escolher um plano preenche valor e ciclo.">
          <select style={FIELD} value={plano} onChange={(e) => pickPlano(e.target.value)}>
            <option value="">Sem plano (avulso)</option>
            {PLANOS_BASE.filter((p) => p.ativo).map((p) => <option key={p.id} value={p.id}>{p.name} · {BRL(p.valor)}</option>)}
          </select>
        </Field>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
          <Field label="Valor (R$)" req><input style={FIELD} inputMode="numeric" value={valor} onChange={(e) => setValor(mascaraValor(e.target.value))} placeholder="0,00" /></Field>
          <Field label="Ciclo" req>
            <select style={FIELD} value={ciclo} onChange={(e) => setCiclo(e.target.value)}>
              <option value="mensal">Mensal</option><option value="trimestral">Trimestral</option><option value="semestral">Semestral</option><option value="anual">Anual</option>
            </select>
          </Field>
          <Field label="Próxima cobrança" req><input style={FIELD} type="date" value={prox} onChange={(e) => setProx(e.target.value)} /></Field>
          <Field label="Gateway" req>
            <select style={FIELD} value={gw} onChange={(e) => setGw(e.target.value)}>
              <option value="inter">Banco Inter</option><option value="asaas">Asaas</option><option value="c6">C6 Bank</option>
            </select>
          </Field>
        </div>
        <Field label="Forma de pagamento" req>
          <div style={{ display: "flex", gap: 6 }}>
            {[["boleto", "Boleto"], ["pix", "Pix"], ["card", "Cartão"]].map(([k, l]) => <button key={k} type="button" aria-pressed={forma === k} style={CHIP(forma === k)} onClick={() => setForma(k)}>{l}</button>)}
          </div>
        </Field>
        <Field label="Descrição" hint="Opcional — aparece no boleto/Pix."><textarea style={AREA} value={desc} onChange={(e) => setDesc(e.target.value)} /></Field>
      </FormDrawer>);

  }

  // Campos de data próprios das assinaturas (espelha o PeriodBar do Financeiro).
  const PB_FIELDS = [
  { id: "prox", label: "Próxima cobrança" },
  { id: "inicio", label: "Início" }];

  const pbDate = (s, field) => {
    const iso = field === "inicio" ? s.since : s.nextAt;
    return iso ? new Date(iso + "T12:00") : null;
  };
  const ST_FILTROS = [
  { key: "todas", label: "Todas" },
  { key: "em_dia", label: "Em dia" },
  { key: "retentando", label: "Retentando" },
  { key: "falhou", label: "Falhou" },
  { key: "pausada", label: "Pausada" },
  { key: "cancelada", label: "Cancelada" }];

  const DT_FILTROS = [
  { key: "any", label: "Qualquer data" },
  { key: "today", label: "Hoje" },
  { key: "tomorrow", label: "Amanhã" },
  { key: "week", label: "Esta semana" },
  { key: "month", label: "Próx. 30 dias" },
  { key: "custom", label: "Personalizado" }];

  function passaData(s, mode, de, ate) {
    if (mode === "any") return true;
    if (!s.nextAt) return false;
    const dd = daysFromToday(s.nextAt);
    if (mode === "today") return dd === 0;
    if (mode === "tomorrow") return dd === 1;
    if (mode === "week") return dd >= 0 && dd <= 6;
    if (mode === "month") return dd >= 0 && dd <= 30;
    if (mode === "custom") return (!de || s.nextAt >= de) && (!ate || s.nextAt <= ate);
    return true;
  }

  function Assinaturas({ onNova }) {
    const [q, setQ] = useState("");
    const [openId, setOpenId] = useState(null);
    const [editId, setEditId] = useState(null);
    const [favs, setFavs] = useState(() => new Set(SUBS.filter((s) => s.fav).map((s) => s.id)));
    const [soFavs, setSoFavs] = useState(false);
    const [st, setSt] = useState("todas");
    const [planoF, setPlanoF] = useState(null);
    const [dt, setDt] = useState("any");
    const [de, setDe] = useState("");
    const [ate, setAte] = useState("");
    const [dateField, setDateField] = useState("prox");
    const [periodMode, setPeriodMode] = useState("tudo");
    const [anchor, setAnchor] = useState(() => new Date(HOJE));
    const searchRef = useRef(null);
    const PeriodBar = window.FinPeriodBar;
    const rows = useMemo(() => {
      const t = q.trim().toLowerCase();
      const win = window.finPeriodWindow ? window.finPeriodWindow(periodMode, anchor) : null;
      return SUBS.filter((s) => {
        if (t && !(s.client + " " + s.cnpj + " " + s.plan + " " + s.id + " " + (s.os || "")).toLowerCase().includes(t)) return false;
        if (st !== "todas" && s.status !== st) return false;
        if (soFavs && !favs.has(s.id)) return false;
        if (planoF && s.plan !== planoF) return false;
        if (!passaData(s, dt, de, ate)) return false;
        if (win) {const d = pbDate(s, dateField);if (!d || d < win[0] || d >= win[1]) return false;}
        return true;
      });
    }, [q, st, soFavs, favs, planoF, dt, de, ate, dateField, periodMode, anchor]);
    useEffect(() => {
      const onKey = (e) => {
        if (e.key === "/" && document.activeElement !== searchRef.current) {e.preventDefault();searchRef.current && searchRef.current.focus();} else
        if (e.key === "Escape") setOpenId(null);
      };
      window.addEventListener("keydown", onKey);return () => window.removeEventListener("keydown", onKey);
    }, []);
    const open = rows.find((s) => s.id === openId) || null;
    const editing = SUBS.find((s) => s.id === editId) || null;
    const toggleFav = (id) => setFavs((p) => {const n = new Set(p);n.has(id) ? n.delete(id) : n.add(id);return n;});
    const ativosAll = SUBS.filter(COBRANDO);
    const ativosF = rows.filter(COBRANDO);
    const mrrF = ativosF.reduce((a, s) => a + monthly(s), 0);
    const porPlano = PLANOS_BASE.filter((p) => p.ativo).map((p) => ({ ...p, n: assinCount(p.name) }));
    const vazioTotal = q.trim() === "" && st === "todas" && !soFavs && !planoF && dt === "any";
    return (
      <>
        <KpiStrip subs={SUBS} />
        {PeriodBar && <PeriodBar dateField={dateField} setDateField={setDateField}
        period={periodMode} setPeriod={setPeriodMode}
        anchor={anchor} setAnchor={setAnchor}
        count={rows.length} fields={PB_FIELDS} countLabel="assinaturas" />}
        <div style={{ display: "grid", gridTemplateColumns: "220px minmax(0,1fr)", gap: 0, alignItems: "start" }}>
          <aside className="cr-card" style={{ margin: "16px 0 24px 24px", background: "var(--surface)", display: "flex", flexDirection: "column", gap: 14 }} aria-label="Filtros">
            <button type="button" aria-pressed={soFavs} style={{ ...CHIP(soFavs), borderRadius: 7, justifyContent: "space-between" }} onClick={() => setSoFavs((v) => !v)}>
              <span style={{ display: "inline-flex", alignItems: "center", gap: 6 }}>{soFavs ? <I.starFill size={13} /> : <I.star size={13} />} {soFavs ? "Mostrando favoritos" : "Mostrar só favoritos"}</span>
              <span style={MONO}>{favs.size}</span>
            </button>
            <div>
              <div className="cr-blk-label" style={{ marginBottom: 6 }}>Status</div>
              <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>
                {ST_FILTROS.map((f) => {
                  const n = f.key === "todas" ? SUBS.length : SUBS.filter((s) => s.status === f.key).length;
                  return <button key={f.key} type="button" aria-pressed={st === f.key} onClick={() => setSt(f.key)} style={{ display: "flex", justifyContent: "space-between", padding: "5px 8px", fontSize: 12.5, borderRadius: 6, border: "none", cursor: "pointer", background: st === f.key ? "var(--accent-soft)" : "transparent", color: st === f.key ? "var(--accent)" : "var(--text-dim)", fontWeight: st === f.key ? 600 : 400 }}><span>{f.label}</span><span style={MONO}>{n}</span></button>;
                })}
              </div>
            </div>
            <div>
              <div className="cr-blk-label" style={{ marginBottom: 6 }}>Próxima cobrança</div>
              <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>
                {DT_FILTROS.map((f) => <button key={f.key} type="button" aria-pressed={dt === f.key} onClick={() => setDt(f.key)} style={{ textAlign: "left", padding: "5px 8px", fontSize: 12.5, borderRadius: 6, border: "none", cursor: "pointer", background: dt === f.key ? "var(--accent-soft)" : "transparent", color: dt === f.key ? "var(--accent)" : "var(--text-dim)", fontWeight: dt === f.key ? 600 : 400 }}>{f.label}</button>)}
              </div>
              {dt === "custom" &&
              <div style={{ display: "grid", gap: 6, marginTop: 6 }}>
                  <Field label="De"><input style={FIELD} type="date" value={de} onChange={(e) => setDe(e.target.value)} aria-label="Próxima cobrança de" /></Field>
                  <Field label="Até"><input style={FIELD} type="date" value={ate} onChange={(e) => setAte(e.target.value)} aria-label="Próxima cobrança até" /></Field>
                </div>
              }
            </div>
            <div>
              <div className="cr-blk-label" style={{ marginBottom: 6 }}>Por plano</div>
              <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>
                {porPlano.map((p) => <button key={p.id} type="button" aria-pressed={planoF === p.name} onClick={() => setPlanoF(planoF === p.name ? null : p.name)} style={{ textAlign: "left", padding: "5px 8px", borderRadius: 6, border: "none", cursor: "pointer", background: planoF === p.name ? "var(--accent-soft)" : "transparent", color: planoF === p.name ? "var(--accent)" : "var(--text-dim)" }}>
                  <div style={{ fontSize: 12.5, fontWeight: 500 }}>{p.name}</div>
                  <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{BRL(p.valor)} · {p.n} {p.n === 1 ? "ativa" : "ativas"}</div>
                </button>)}
              </div>
            </div>
            <div style={{ borderTop: "1px solid var(--border)", paddingTop: 10 }}>
              <div className="cr-blk-label">MRR filtrado</div>
              <div style={{ fontSize: 16, fontWeight: 700, color: "var(--text)", marginTop: 2, fontVariantNumeric: "tabular-nums" }}>{BRL(mrrF)}</div>
              <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{ativosF.length} cobrando de {ativosAll.length}</div>
            </div>
          </aside>
          <div className="cr-list-wrap">
            <div className="cr-list-head">
              <div className="cr-search">
                <I.search size={14} style={{ color: "var(--text-mute)" }} />
                <input ref={searchRef} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar (/) — cliente, CNPJ, OS" aria-label="Buscar assinaturas" />
                <kbd>/</kbd>
              </div>
              <span className="cr-list-count">{rows.length} de {SUBS.length}</span>
            </div>
            {rows.length === 0 ?
            <div style={{ padding: 40, textAlign: "center" }}>
                <div style={{ fontSize: 14, fontWeight: 600, color: "var(--text-dim)" }}>Nada por aqui.</div>
                <div style={{ fontSize: 12.5, color: "var(--text-mute)", marginTop: 4 }}>{vazioTotal ? "Nenhuma assinatura cadastrada ainda." : "Nenhuma assinatura com este filtro + busca."}</div>
                {vazioTotal && <button className="cr-primary" style={{ marginTop: 12 }} onClick={onNova}><I.plus size={14} /> Nova assinatura</button>}
              </div> :
            rows.map((s) => <SubRow key={s.id} sub={s} active={openId === s.id} fav={favs.has(s.id)} onFav={() => toggleFav(s.id)} onOpen={() => setOpenId(s.id)} />)}
          </div>
        </div>
        {open && !editing && <Drawer sub={open} onClose={() => setOpenId(null)} onEdit={() => setEditId(open.id)} />}
        {editing && <EditarCobranca sub={editing} onClose={() => setEditId(null)} />}
      </>);

  }

  // ══════════════════════════════════════════════════════════════
  // PLANOS  (Pages RecurringBilling/Planos/Index · Create · Edit)
  // ══════════════════════════════════════════════════════════════
  const CICLO_OPC = [["mensal", "Mensal"], ["trimestral", "Trimestral"], ["semestral", "Semestral"], ["anual", "Anual"], ["custom", "Customizado (dias)"]];
  const FISCAL_OPC = [["none", "Não emite nota fiscal"], ["nfe", "NFe (produto)"], ["nfse", "NFS-e (serviço)"]];

  function PlanoForm({ plano, onClose }) {
    const novo = !plano;
    const [d, setD] = useState(() => plano ? { ...plano, valor: valorCampo(plano.valor) } : { name: "", slug: "", curta: "", completa: "", cycle: "mensal", dias: "", trial: 0, valor: "", fiscal: "none", cfop: "", servico: "", ativo: true });
    const set = (k, v) => setD((p) => ({ ...p, [k]: v }));
    const slugMudou = !novo && d.slug !== plano.slug;
    useEffect(() => {
      const k = (e) => {if ((e.metaKey || e.ctrlKey) && e.key === "Enter") onClose();};
      window.addEventListener("keydown", k);return () => window.removeEventListener("keydown", k);
    }, [onClose]);
    const SecT = ({ children }) => <div className="cr-blk-label" style={{ marginBottom: 8 }}>{children}</div>;
    return (
      <FormDrawer eyebrow="Plano" title={novo ? "Novo plano" : "Editar plano"} sub={novo ? "Cadastrar plano recorrente" : `${plano.name} · #${plano.id}`} onClose={onClose}
      foot={<><span className="cr-act-spacer" /><button className="cr-act" onClick={onClose}>Cancelar <kbd style={{ ...MONO, fontSize: 10 }}>Esc</kbd></button><button className="cr-act primary" onClick={onClose} disabled={!d.name || !d.valor}>{novo ? "Salvar plano" : "Salvar alterações"} <kbd style={{ ...MONO, fontSize: 10 }}>⌘↵</kbd></button></>}>
        <div className="cr-card" style={{ display: "grid", gap: 12 }}>
          <SecT>Identificação</SecT>
          <Field label="Nome do plano" req><input style={FIELD} value={d.name} onChange={(e) => set("name", e.target.value)} placeholder="Ex: Sinalização Mensal" /></Field>
          <Field label="Slug" hint={slugMudou ? "Atenção: alterar o slug pode quebrar links e integrações que usam o plano antigo." : "Gerado do nome se ficar vazio."}>
            <input style={{ ...FIELD, ...MONO, borderColor: slugMudou ? "var(--warn)" : "var(--border)" }} value={d.slug} onChange={(e) => set("slug", e.target.value.toLowerCase())} />
          </Field>
          <Field label="Descrição curta"><input style={FIELD} value={d.curta} onChange={(e) => set("curta", e.target.value)} /></Field>
          <Field label="Descrição completa"><textarea style={AREA} value={d.completa} onChange={(e) => set("completa", e.target.value)} /></Field>
        </div>
        <div className="cr-card" style={{ display: "grid", gap: 12 }}>
          <SecT>Cobrança</SecT>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
            <Field label="Valor (R$)" req><input style={FIELD} inputMode="numeric" value={d.valor} onChange={(e) => set("valor", mascaraValor(e.target.value))} placeholder="0,00" /></Field>
            <Field label="Ciclo" req>
              <select style={FIELD} value={d.cycle} onChange={(e) => set("cycle", e.target.value)}>{CICLO_OPC.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
            </Field>
            {d.cycle === "custom" && <Field label="Ciclo (dias)" req hint="Entre 1 e 365."><input style={FIELD} type="number" min={1} max={365} value={d.dias || ""} onChange={(e) => set("dias", e.target.value)} /></Field>}
            <Field label="Trial (dias)" hint="Entre 0 e 90."><input style={FIELD} type="number" min={0} max={90} value={d.trial} onChange={(e) => set("trial", e.target.value)} /></Field>
          </div>
          <label style={{ display: "flex", alignItems: "center", gap: 8, fontSize: 13, color: "var(--text-dim)" }}>
            <input type="checkbox" checked={d.ativo} onChange={(e) => set("ativo", e.target.checked)} /> Plano ativo (disponível pra novas assinaturas)
          </label>
        </div>
        <div className="cr-card" style={{ display: "grid", gap: 12 }}>
          <SecT>Emissão fiscal</SecT>
          <Field label="Tipo"><select style={FIELD} value={d.fiscal} onChange={(e) => set("fiscal", e.target.value)}>{FISCAL_OPC.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select></Field>
          {d.fiscal === "nfe" && <Field label="CFOP" hint="Ex: 5101 (venda de produção própria)."><input style={{ ...FIELD, ...MONO }} value={d.cfop} onChange={(e) => set("cfop", e.target.value)} /></Field>}
          {d.fiscal === "nfse" && <Field label="Código de serviço" hint="Item da lista de serviços (LC 116/2003)."><input style={{ ...FIELD, ...MONO }} value={d.servico} onChange={(e) => set("servico", e.target.value)} /></Field>}
        </div>
      </FormDrawer>);

  }

  function Planos({ formPlano, setFormPlano }) {
    const [q, setQ] = useState("");
    const [aplicada, setAplicada] = useState("");
    const [excluir, setExcluir] = useState(null);
    const searchRef = useRef(null);
    useEffect(() => {
      const onKey = (e) => {if (e.key === "/" && document.activeElement !== searchRef.current) {e.preventDefault();searchRef.current && searchRef.current.focus();}};
      window.addEventListener("keydown", onKey);return () => window.removeEventListener("keydown", onKey);
    }, []);
    const planos = PLANOS_BASE.map((p) => ({ ...p, n: assinCount(p.name) }));
    const ativos = planos.filter((p) => p.ativo);
    const mrrPot = ativos.reduce((a, p) => a + p.valor / mesesDoCiclo(p.cycle, p.dias) * p.n, 0);
    const totalAssin = ativos.reduce((a, p) => a + p.n, 0);
    const top = [...planos].sort((a, b) => b.n - a.n)[0];
    const ciclos = [["mensal", "Mensal"], ["trimestral", "Trimestral"], ["semestral", "Semestral"], ["anual", "Anual"], ["custom", "Customizado"]].map(([k, l]) => ({ k, l, n: planos.filter((p) => p.cycle === k).length }));
    const rows = planos.filter((p) => {const t = aplicada.trim().toLowerCase();return !t || (p.name + " " + p.slug).toLowerCase().includes(t);});
    return (
      <>
        <div className="cr-stats">
          <div className="cr-stat cr-stat-hero">
            <small>MRR dos planos</small>
            <b>{BRL(mrrPot)}</b>
            <span className="cr-stat-hint">ticket médio <b>{BRL(mrrPot / Math.max(totalAssin, 1))}</b> · {totalAssin} cobrando</span>
          </div>
          <Kpi label="Total planos" value={planos.length} hint={`${planos.length - ativos.length} ${planos.length - ativos.length === 1 ? "inativo" : "inativos"}`} />
          <Kpi label="Total ativos" value={ativos.length} hint="disponíveis pra novas assinaturas" />
          <Kpi label="Plano top vendido" value={top && top.n ? top.name : "—"} hint={top && top.n ? `${top.n} assin. · ${BRL(top.valor)}` : "sem assinaturas ativas"} />
        </div>
        <div className="cr-card" style={{ ...SEC, background: "var(--surface)" }}>
          <div className="cr-blk-label" style={{ marginBottom: 8 }}>Distribuição por ciclo</div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(5,1fr)", gap: 8 }}>
            {ciclos.map((c) =>
            <div key={c.k} style={{ padding: "8px 10px", borderRadius: 7, background: "color-mix(in oklab,var(--text) 4%,transparent)" }}>
                <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{c.l}</div>
                <div style={{ fontSize: 16, fontWeight: 700, color: c.n ? "var(--text)" : "var(--text-mute)", fontVariantNumeric: "tabular-nums" }}>{c.n}</div>
              </div>
            )}
          </div>
        </div>
        <div className="cr-list-wrap">
          <form className="cr-list-head" onSubmit={(e) => {e.preventDefault();setAplicada(q);}}>
            <div className="cr-search">
              <I.search size={14} style={{ color: "var(--text-mute)" }} />
              <input ref={searchRef} value={q} onChange={(e) => {setQ(e.target.value);setAplicada(e.target.value);}} placeholder="Buscar (/) — nome ou slug" aria-label="Buscar planos" />
              <kbd>/</kbd>
            </div>
            <button type="submit" className="cr-act">Buscar</button>
          </form>
          {planos.length === 0 ?
          <div style={{ padding: 40, textAlign: "center" }}>
              <div style={{ fontSize: 15, fontWeight: 600, color: "var(--text-dim)" }}>Nenhum plano cadastrado.</div>
              <div style={{ fontSize: 12.5, color: "var(--text-mute)", marginTop: 4 }}>Crie o primeiro plano pra começar a vincular assinaturas.</div>
              <button className="cr-primary" style={{ marginTop: 12 }} onClick={() => setFormPlano({ modo: "novo" })}><I.plus size={14} /> Criar primeiro plano</button>
            </div> :
          rows.length === 0 ?
          <div style={{ padding: 40, textAlign: "center" }}>
              <div style={{ fontSize: 14, fontWeight: 600, color: "var(--text-dim)" }}>Nenhum plano encontrado para "{aplicada.trim()}".</div>
              <div style={{ fontSize: 12.5, color: "var(--text-mute)", marginTop: 4 }}>Busque por outro nome ou slug.</div>
              <button className="cr-act" style={{ marginTop: 12 }} onClick={() => {setQ("");setAplicada("");}}>Limpar busca</button>
            </div> :
          <table style={{ width: "100%", borderCollapse: "collapse" }}>
              <thead><tr>
                <th style={TH}>Plano</th><th style={TH}>Ciclo</th><th style={{ ...TH, textAlign: "right" }}>Valor</th><th style={{ ...TH, textAlign: "center" }}>Assinaturas</th><th style={{ ...TH, textAlign: "center" }}>Fiscal</th><th style={{ ...TH, textAlign: "center" }}>Status</th><th style={{ ...TH, textAlign: "right" }}>Ações</th>
              </tr></thead>
              <tbody>
                {rows.map((p) =>
              <tr key={p.id}>
                    <td style={TD}>
                      <div style={{ fontWeight: 600, color: "var(--text)" }}>{p.name}</div>
                      <div style={{ ...MONO, fontSize: 11, color: "var(--text-mute)" }}>{p.slug}</div>
                      {p.curta && <div style={{ fontSize: 11, color: "var(--text-mute)", marginTop: 2 }}>{p.curta}</div>}
                    </td>
                    <td style={TD}>{CYCLE[p.cycle]}{p.cycle === "custom" && p.dias ? ` (${p.dias}d)` : ""}</td>
                    <td style={{ ...TD, ...MONO, textAlign: "right" }}>{BRL(p.valor)}</td>
                    <td style={{ ...TD, ...MONO, textAlign: "center" }}>{p.n}</td>
                    <td style={{ ...TD, textAlign: "center" }}><span className={"cr-fiscal-badge " + p.fiscal}>{FISCAL[p.fiscal].t}</span></td>
                    <td style={{ ...TD, textAlign: "center" }}><span className={"cr-pill " + (p.ativo ? "neutro" : "inativo")}>{p.ativo ? "ativo" : "inativo"}</span></td>
                    <td style={{ ...TD, textAlign: "right", whiteSpace: "nowrap" }}>
                      <button className="cr-act" style={{ padding: "4px 8px", fontSize: 12 }} onClick={() => setFormPlano({ modo: "editar", id: p.id })}><I.pencil size={12} /> Editar</button>{" "}
                      <button className="cr-act danger" style={{ padding: "4px 8px", fontSize: 12 }} onClick={() => setExcluir(p)}><I.x size={12} /> Excluir</button>
                    </td>
                  </tr>
              )}
              </tbody>
            </table>
          }
          {planos.length > 0 &&
          <div style={{ display: "flex", justifyContent: "space-between", padding: "8px 14px", fontSize: 12, color: "var(--text-mute)" }}>
              <span>{planos.length} {planos.length === 1 ? "plano" : "planos"} no total</span><span>Página 1 de 1</span>
            </div>
          }
        </div>
        {formPlano && <PlanoForm plano={formPlano.modo === "editar" ? PLANOS_BASE.find((x) => x.id === formPlano.id) : null} onClose={() => setFormPlano(null)} />}
        {excluir &&
        <Confirm title={`Excluir plano "${excluir.name}"?`} onClose={() => setExcluir(null)}
        foot={<><button className="cr-act" onClick={() => setExcluir(null)}>Cancelar</button><button className="cr-act danger" onClick={() => setExcluir(null)} disabled={excluir.n > 0}>Excluir</button></>}>
            {excluir.n > 0 ?
          <>Atenção: este plano tem <b>{excluir.n} {excluir.n === 1 ? "assinatura ativa" : "assinaturas ativas"}</b>. A exclusão fica bloqueada até elas mudarem de plano ou serem canceladas.</> :
          <>O plano sai da lista e não pode mais ser usado em novas assinaturas.</>}
          </Confirm>
        }
      </>);

  }

  // ══════════════════════════════════════════════════════════════
  // FATURAS  (Page RecurringBilling/Faturas/Index)
  // ══════════════════════════════════════════════════════════════
  // Cor por SENTIDO da fatura (não reaproveita a cor de "retentando" da assinatura): pendente é
  // normal (a vencer) e fica neutra; só atrasada, chargeback e parcial pedem atenção.
  const FAT_PILL = { paga: "em_dia", pendente: "neutro", atrasada: "falhou", parcial: "retentando", estornada: "inativo", reembolsada: "inativo", chargeback: "falhou", cancelada: "cancelada" };
  const FAT_LABEL = { paga: "Paga", pendente: "Pendente", atrasada: "Atrasada", parcial: "Paga parcial", estornada: "Estornada", reembolsada: "Reembolsada", chargeback: "Chargeback", cancelada: "Cancelada" };
  const FAT_ST = [["todas", "Todas"], ["paga", "Pagas"], ["pendente", "Pendentes"], ["atrasada", "Atrasadas"], ["parcial", "Parciais"], ["estornada", "Estornadas"], ["reembolsada", "Reembolsadas"], ["chargeback", "Chargeback"], ["cancelada", "Canceladas"]];
  const FAT_GW = [["all", "Todos gateways"], ["inter", "Banco Inter"], ["c6", "C6 Bank"], ["asaas", "Asaas"]];
  const FAT_PER = [["all", "Qualquer período"], ["mes_atual", "Mês atual"], ["proximo_mes", "Próximo mês"], ["atrasadas", "Apenas atrasadas"]];

  function Faturas() {
    const [fats, setFats] = useState(FATURAS_BASE);
    const [st, setSt] = useState("todas");
    const [gw, setGw] = useState("all");
    const [per, setPer] = useState("all");
    const [q, setQ] = useState("");
    const [cancel, setCancel] = useState(null);
    const [estorno, setEstorno] = useState(null);
    const [valorEstorno, setValorEstorno] = useState("");
    const [motivo, setMotivo] = useState("");
    const searchRef = useRef(null);
    useEffect(() => {
      const onKey = (e) => {if (e.key === "/" && document.activeElement !== searchRef.current) {e.preventDefault();searchRef.current && searchRef.current.focus();}};
      window.addEventListener("keydown", onKey);return () => window.removeEventListener("keydown", onKey);
    }, []);
    const mes = HOJE_ISO.slice(0, 7);
    const proxMes = (() => {const d = new Date(HOJE);d.setMonth(d.getMonth() + 1);return d.toISOString().slice(0, 7);})();
    const pagoMes = fats.filter((f) => f.status === "paga" && f.pagoEm && f.pagoEm.slice(0, 7) === mes).reduce((a, f) => a + f.valor, 0);
    const pend = fats.filter((f) => f.status === "pendente").reduce((a, f) => a + f.valor, 0);
    const atr = fats.filter((f) => f.status === "atrasada");
    const rows = fats.filter((f) => {
      if (st !== "todas" && f.status !== st) return false;
      if (gw !== "all" && f.gateway !== gw) return false;
      if (per === "mes_atual" && f.venc.slice(0, 7) !== mes) return false;
      if (per === "proximo_mes" && f.venc.slice(0, 7) !== proxMes) return false;
      if (per === "atrasadas" && f.status !== "atrasada") return false;
      const t = q.trim().toLowerCase();
      if (t && !(f.client + " " + f.cnpj + " " + f.numero).toLowerCase().includes(t)) return false;
      return true;
    });
    const fechar = () => {setCancel(null);setEstorno(null);setValorEstorno("");setMotivo("");};
    const abrirEstorno = (f) => {setEstorno(f);setValorEstorno(valorCampo(f.status === "parcial" ? f.valorPago : f.valor));};
    return (
      <>
        <div className="cr-stats">
          <div className="cr-stat cr-stat-hero">
            <small>Pago este mês</small>
            <b>{BRL(pagoMes)}</b>
            <span className="cr-stat-hint">faturas liquidadas no mês corrente</span>
          </div>
          <Kpi label="Pendente" value={BRL(pend)} hint="abertas com vencimento futuro" />
          <Kpi label="Atrasado" value={BRL(atr.reduce((a, f) => a + f.valor, 0))} tone={atr.length ? "neg" : ""} hint={`${atr.length} ${atr.length === 1 ? "fatura vencida" : "faturas vencidas"}`} />
          <Kpi label="Total de faturas" value={fats.length.toLocaleString("pt-BR")} hint="histórico completo" />
        </div>
        <div className="cr-card" style={{ ...SEC, background: "var(--surface)", display: "flex", flexWrap: "wrap", alignItems: "center", gap: 8 }}>
          <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
            {FAT_ST.map(([k, l]) => {
              const n = k === "todas" ? fats.length : fats.filter((f) => f.status === k).length;
              return <button key={k} type="button" aria-pressed={st === k} style={CHIP(st === k)} onClick={() => setSt(k)}>{l} <span style={MONO}>{n}</span></button>;
            })}
          </div>
          <select style={{ ...FIELD, width: 150 }} value={gw} onChange={(e) => setGw(e.target.value)} aria-label="Gateway">{FAT_GW.map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>
          <select style={{ ...FIELD, width: 160 }} value={per} onChange={(e) => setPer(e.target.value)} aria-label="Período">{FAT_PER.map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>
        </div>
        <div className="cr-list-wrap">
          <div className="cr-list-head">
            <div className="cr-search">
              <I.search size={14} style={{ color: "var(--text-mute)" }} />
              <input ref={searchRef} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar (/) — cliente, CNPJ, número da fatura" aria-label="Buscar faturas" />
              <kbd>/</kbd>
            </div>
            <span className="cr-list-count">{rows.length} / {fats.length}</span>
          </div>
          {fats.length === 0 ?
          <div style={{ padding: 40, textAlign: "center" }}>
              <div style={{ fontSize: 14, fontWeight: 600, color: "var(--text-dim)" }}>Nenhuma fatura gerada ainda.</div>
              <div style={{ fontSize: 12.5, color: "var(--text-mute)", marginTop: 4 }}>As faturas aparecem aqui quando as assinaturas chegam ao vencimento.</div>
            </div> :
          rows.length === 0 ?
          <div style={{ padding: 40, textAlign: "center" }}>
              <div style={{ fontSize: 14, fontWeight: 600, color: "var(--text-dim)" }}>Nenhuma fatura encontrada.</div>
              <div style={{ fontSize: 12.5, color: "var(--text-mute)", marginTop: 4 }}>Ajuste os filtros ou a busca.</div>
            </div> :
          <table style={{ width: "100%", borderCollapse: "collapse" }}>
              <thead><tr>
                <th style={TH}>Número</th><th style={TH}>Cliente</th><th style={TH}>Plano</th><th style={{ ...TH, textAlign: "right" }}>Valor</th><th style={TH}>Vencimento</th><th style={TH}>Status</th><th style={TH}>Gateway</th><th style={{ ...TH, textAlign: "right" }}>Ações</th>
              </tr></thead>
              <tbody>
                {rows.map((f) => {
                const cancelavel = f.status === "pendente" || f.status === "atrasada";
                const estornavel = f.status === "paga" || f.status === "parcial";
                return (
                  <tr key={f.id}>
                      <td style={{ ...TD, ...MONO }}>{f.numero}</td>
                      <td style={TD}><div style={{ fontWeight: 600, color: "var(--text)" }}>{f.client}</div><div style={{ ...MONO, fontSize: 11, color: "var(--text-mute)" }}>{mascaraCnpj(f.cnpj)}</div></td>
                      <td style={{ ...TD, fontSize: 12 }}>{f.plan || <i style={{ color: "var(--text-mute)" }}>avulsa</i>}</td>
                      <td style={{ ...TD, ...MONO, textAlign: "right", fontWeight: 600 }}>{BRL(f.valor)}{f.status === "parcial" && <div style={{ fontSize: 11, fontWeight: 400, color: "var(--text-mute)" }}>pago {BRL(f.valorPago)} · falta {BRL(f.valor - f.valorPago)}</div>}</td>
                      <td style={{ ...TD, fontSize: 12 }}><div>{dateFull(f.venc)}</div><div className={f.status === "atrasada" ? "cr-txt-neg" : ""} style={{ fontSize: 11, marginTop: 2, color: f.status === "atrasada" ? undefined : "var(--text-mute)" }}>{f.status === "pendente" || f.status === "atrasada" ? dueLabel(f.venc) : "—"}</div></td>
                      <td style={TD}><span className={"cr-pill " + FAT_PILL[f.status]}>{FAT_LABEL[f.status]}</span>{f.motivo && <div style={{ fontSize: 11, color: "var(--text-mute)", marginTop: 3 }}>{f.motivo}</div>}</td>
                      <td style={TD}><span className="cr-fiscal-badge none">{GATEWAY[f.gateway]}</span></td>
                      <td style={{ ...TD, textAlign: "right" }}>{cancelavel ? <button className="cr-act danger" style={{ padding: "4px 8px", fontSize: 12 }} onClick={() => setCancel(f)} aria-label={`Cancelar fatura ${f.numero}`}>Cancelar</button> : estornavel ? <button className="cr-act" style={{ padding: "4px 8px", fontSize: 12 }} onClick={() => abrirEstorno(f)} aria-label={`Estornar fatura ${f.numero}`}>Estornar</button> : <span style={{ color: "var(--text-mute)" }}>—</span>}</td>
                    </tr>);
              })}
              </tbody>
            </table>
          }
        </div>
        {cancel &&
        <Confirm title="Cancelar fatura" onClose={fechar}
        foot={<><button className="cr-act" onClick={fechar}>Voltar</button><button className="cr-act danger" onClick={() => {setFats((p) => p.map((x) => x.id === cancel.id ? { ...x, status: "cancelada" } : x));fechar();}}>Confirmar cancelamento</button></>}>
            <div>{cancel.numero} · {cancel.client} · {BRL(cancel.valor)}. O boleto/Pix é cancelado no gateway e a fatura fica registrada como cancelada.</div>
            <div style={{ marginTop: 12 }}><Field label="Motivo (opcional)"><input style={FIELD} value={motivo} onChange={(e) => setMotivo(e.target.value)} placeholder="Ex: duplicidade, solicitação do cliente" /></Field></div>
          </Confirm>
        }
        {estorno &&
        <Confirm title="Estornar pagamento" onClose={fechar}
        foot={<><button className="cr-act" onClick={fechar}>Voltar</button><button className="cr-act danger" disabled={!valorEstorno || !motivo.trim()} onClick={() => {setFats((p) => p.map((x) => x.id === estorno.id ? { ...x, status: "estornada", motivo } : x));fechar();}}>Confirmar estorno</button></>}>
            <div>{estorno.numero} · {estorno.client} · pago {BRL(estorno.status === "parcial" ? estorno.valorPago : estorno.valor)} via {GATEWAY[estorno.gateway]}. O valor volta ao cliente pelo gateway; o crédito lançado na conta é revertido.</div>
            <div style={{ display: "grid", gap: 12, marginTop: 12 }}>
              <Field label="Valor a estornar (R$)" req hint="Menor que o pago = estorno parcial."><input style={FIELD} inputMode="numeric" value={valorEstorno} onChange={(e) => setValorEstorno(mascaraValor(e.target.value))} /></Field>
              <Field label="Motivo" req><input style={FIELD} value={motivo} onChange={(e) => setMotivo(e.target.value)} placeholder="Ex: duplicidade, serviço não prestado" /></Field>
            </div>
          </Confirm>
        }
      </>);

  }

  // ══════════════════════════════════════════════════════════════
  // CONFIGURAÇÕES  (Page RecurringBilling/Configuracoes/Index)
  // ══════════════════════════════════════════════════════════════
  // conexao: ok · credencial_invalida (o gateway recusou a chave) · expirada (certificado/token vencido).
  const GATEWAYS_CFG = ESTADO === "vazio" ? [] : [
  { id: 1, nome: "Banco Inter", banco: "Inter", ambiente: "Produção · PJ", sandbox: false, ativo: true, conexao: "ok" },
  { id: 2, nome: "Asaas", banco: "Asaas", ambiente: "Sandbox", sandbox: true, ativo: true, conexao: "ok" },
  { id: 3, nome: "C6 Bank", banco: "C6", ambiente: "Produção", sandbox: false, ativo: true, conexao: "expirada" }];
  const CONEXAO = {
    credencial_invalida: "O gateway recusou a credencial. Novas cobranças por ele ficam paradas até corrigir.",
    expirada: "O certificado de acesso venceu. Novas cobranças por ele ficam paradas até renovar."
  };

  // Régua por forma de pagamento: boleto gera boleto novo a cada retentativa; cartão tenta de novo
  // no mesmo cartão salvo (recusa leve) e para na recusa definitiva.
  const REGUA = {
    boleto: [
    { ordem: 1, dias: 3, rotulo: "1ª retentativa", descricao: "Falha leve — provavelmente saldo insuficiente ou instabilidade do banco.", tone: "retentando" },
    { ordem: 2, dias: 7, rotulo: "2ª retentativa", descricao: "Cobrança insistente — reenvia boleto novo por e-mail e WhatsApp.", tone: "retentando" },
    { ordem: 3, dias: 15, rotulo: "3ª retentativa (final)", descricao: "Falha definitiva — a assinatura passa para “falhou” e pede ação manual no detalhe da assinatura.", tone: "falhou" }],
    card: [
    { ordem: 1, dias: 1, rotulo: "2ª tentativa", descricao: "Recusa leve (saldo, limite) — cobra de novo no mesmo cartão.", tone: "retentando" },
    { ordem: 2, dias: 3, rotulo: "3ª tentativa", descricao: "Nova cobrança no cartão salvo.", tone: "retentando" },
    { ordem: 3, dias: 7, rotulo: "4ª tentativa (final)", descricao: "Última tentativa. Recusa definitiva em qualquer etapa para a régua e avisa o cliente pelo WhatsApp.", tone: "falhou" }]
  };

  // ultimo: último evento recebido · falhas24h: entregas recusadas pelo oimpresso nas últimas 24h.
  const WEBHOOKS = [
  { gateway: "asaas", label: "Asaas", rotulo: "Webhook unificado (pagamento, assinatura, transferência)", metodo: "POST", url: "https://oimpresso.com/webhooks/asaas/1", auth: "Header X-Webhook-Token configurado no Asaas (Conta → Integrações → Webhooks).", ultimo: "há 12 min", falhas24h: 0 },
  { gateway: "inter", label: "Banco Inter", rotulo: "Webhook PIX recebido", metodo: "POST", url: "https://oimpresso.com/webhooks/inter/pix/1", auth: "Header X-Inter-Webhook-Secret, conferido com o segredo da credencial Inter ativa.", ultimo: "há 3 dias", falhas24h: 6, erro: "401 — segredo diferente do cadastrado" }];


  function CfgCard({ titulo, sub, children }) {
    return (
      <section className="cr-card" style={{ background: "var(--surface)", padding: 16 }}>
        <h2 style={{ margin: 0, fontSize: 14, fontWeight: 600, color: "var(--text)" }}>{titulo}</h2>
        <div style={{ fontSize: 12, color: "var(--text-mute)", marginTop: 2, marginBottom: 12 }}>{sub}</div>
        {children}
      </section>);

  }

  function WebhookCard({ w }) {
    const [copiado, setCopiado] = useState(false);
    const copiar = () => {
      try {navigator.clipboard && navigator.clipboard.writeText(w.url).catch(() => {});} catch (e) {/* sem clipboard no preview */}
      setCopiado(true);setTimeout(() => setCopiado(false), 1600);
    };
    return (
      <div style={{ border: "1px solid var(--border)", borderRadius: 8, padding: 12 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
          <span className="cr-fiscal-badge nfe">{w.metodo}</span>
          <b style={{ fontSize: 13, color: "var(--text)" }}>{w.label}</b>
          <span className="cr-act-spacer" />
          <span className={"cr-pill " + (w.falhas24h ? "falhou" : "em_dia")}>{w.falhas24h ? `${w.falhas24h} falhas em 24h` : "recebendo"}</span>
        </div>
        <div style={{ fontSize: 12, color: "var(--text-mute)", marginTop: 4 }}>{w.rotulo} · último evento {w.ultimo || "nunca"}</div>
        {w.falhas24h > 0 && <div role="alert" className="cr-txt-neg" style={{ fontSize: 12, marginTop: 6 }}>Pagamentos podem não estar baixando: {w.erro}. Confira o segredo no painel do banco.</div>}
        <div style={{ display: "flex", gap: 6, marginTop: 8 }}>
          <code style={{ ...MONO, flex: 1, minWidth: 0, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", padding: "6px 8px", borderRadius: 6, background: "color-mix(in oklab,var(--text) 5%,transparent)", color: "var(--text-dim)" }}>{w.url}</code>
          <button className="cr-act" style={{ padding: "4px 10px", fontSize: 12 }} onClick={copiar}><I.copy size={12} /> {copiado ? "Copiado!" : "Copiar"}</button>
        </div>
        <div style={{ fontSize: 11.5, color: "var(--text-mute)", marginTop: 8 }}><b style={{ color: "var(--text-dim)" }}>Autenticação:</b> {w.auth}</div>
      </div>);

  }

  function Configuracoes() {
    return (
      <div style={{ display: "grid", gridTemplateColumns: "repeat(2,minmax(0,1fr))", gap: 16, margin: "16px 24px 24px" }}>
        <CfgCard titulo="Gateways de boleto/pix" sub="Credenciais ativas e sandbox cadastradas nesta empresa.">
          {GATEWAYS_CFG.length === 0 ?
          <div style={{ border: "1px dashed var(--border)", borderRadius: 8, padding: 16, textAlign: "center" }}>
              <div style={{ fontSize: 13, fontWeight: 600, color: "var(--text-dim)" }}>Nenhum gateway configurado.</div>
              <div style={{ fontSize: 12, color: "var(--text-mute)", marginTop: 4 }}>Sem gateway, nenhuma assinatura gera boleto, Pix ou cobrança no cartão.</div>
            </div> :
          <div style={{ display: "grid", gap: 8 }}>
            {GATEWAYS_CFG.map((g) =>
            <div key={g.id} style={{ border: "1px solid var(--border)", borderRadius: 8, padding: "9px 12px" }}>
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                  <Avatar name={g.banco} size={30} />
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 13, fontWeight: 600, color: "var(--text)" }}>{g.nome} {g.sandbox && <span className="cr-pill neutro" style={{ marginLeft: 4 }}>sandbox</span>}</div>
                    <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{g.banco} · {g.ambiente}</div>
                  </div>
                  <span className={"cr-pill " + (g.conexao !== "ok" ? "falhou" : g.ativo ? "em_dia" : "inativo")}>{g.conexao === "credencial_invalida" ? "credencial inválida" : g.conexao === "expirada" ? "certificado vencido" : g.ativo ? "conectado" : "inativo"}</span>
                </div>
                {g.conexao !== "ok" &&
                <div role="alert" style={{ display: "flex", alignItems: "center", gap: 8, marginTop: 8 }}>
                    <span className="cr-txt-neg" style={{ flex: 1, fontSize: 12 }}>{CONEXAO[g.conexao]}</span>
                    <button className="cr-act" style={{ padding: "4px 10px", fontSize: 12 }}>{g.conexao === "expirada" ? "Renovar certificado" : "Corrigir credencial"}</button>
                  </div>
                }
              </div>
            )}
          </div>
          }
          <button className="cr-act" style={{ marginTop: 12 }}><I.plus size={13} /> Adicionar gateway</button>
        </CfgCard>

        <CfgCard titulo="Régua de dunning (cobrança)" sub="Cronograma de retentativas quando uma fatura falha no pagamento.">
          <p style={{ margin: "0 0 10px", fontSize: 12.5, color: "var(--text-dim)", lineHeight: 1.5 }}>Cada forma de pagamento tem sua régua. No boleto, cada retentativa gera boleto novo e avisa o cliente por e-mail e WhatsApp; no cartão, a cobrança é refeita no cartão salvo. Ao fim da régua, a assinatura passa a "falhou" e pede ação manual.</p>
          {[["boleto", "Boleto"], ["card", "Cartão recorrente"]].map(([forma, nome]) =>
          <div key={forma} style={{ marginTop: forma === "card" ? 14 : 0 }}>
          <div className="cr-blk-label" style={{ marginBottom: 6 }}>{nome} · {REGUA[forma].length} retentativas depois da 1ª cobrança</div>
          <div style={{ display: "grid", gap: 8 }}>
            {REGUA[forma].map((r) =>
            <div key={r.ordem} style={{ display: "flex", gap: 10, border: "1px solid var(--border)", borderRadius: 8, padding: "9px 12px" }}>
                <div style={{ flex: 1 }}>
                  <div style={{ display: "flex", justifyContent: "space-between", gap: 8 }}>
                    <b style={{ fontSize: 13, color: "var(--text)" }}>{r.rotulo}</b>
                    <span className={"cr-pill " + r.tone} style={MONO}>+{r.dias}d</span>
                  </div>
                  <div style={{ fontSize: 12, color: "var(--text-mute)", marginTop: 3 }}>{r.descricao}</div>
                </div>
              </div>
            )}
          </div>
          </div>
          )}
        </CfgCard>

        <CfgCard titulo="Nota fiscal automática na fatura paga" sub="Emissão fiscal disparada automaticamente ao receber pagamento.">
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", border: "1px solid var(--border)", borderRadius: 8, padding: "10px 12px" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              <span role="switch" aria-checked="false" aria-disabled="true" aria-label="Nota fiscal automática (em breve)" style={{ display: "inline-flex", width: 40, height: 22, borderRadius: 999, padding: 2, boxSizing: "border-box", background: "color-mix(in oklab,var(--text) 18%,transparent)" }}>
                <span style={{ width: 18, height: 18, borderRadius: 999, background: "var(--surface)" }} />
              </span>
              <b style={{ fontSize: 13, color: "var(--text)" }}>Desativada</b>
            </div>
            <span className="cr-pill pausada">Em breve</span>
          </div>
          <p style={{ margin: "10px 0 0", fontSize: 12.5, color: "var(--text-dim)", lineHeight: 1.5 }}>Quando uma fatura é paga (aviso do Asaas ou do Banco Inter), o sistema emite a nota fiscal automaticamente. Vale para planos com emissão NFe ou NFS-e.</p>
        </CfgCard>

        <CfgCard titulo="Webhooks" sub="Endereços pra colar no painel do banco ou gateway.">
          <div style={{ display: "grid", gap: 10 }}>{WEBHOOKS.map((w) => <WebhookCard key={w.gateway} w={w} />)}</div>
        </CfgCard>
      </div>);

  }

  // ── rotas ↔ vista ↔ Page (uma rota por Index; vista fixa) ─────────
  const ROTAS = {
    "recurring": { vista: "assinaturas", page: "RecurringBilling/Index" },
    "rb-assinaturas": { vista: "assinaturas", page: "RecurringBilling/Index" },
    "rb-planos": { vista: "planos", page: "RecurringBilling/Planos/Index", drawers: { novo: "RecurringBilling/Planos/Create", editar: "RecurringBilling/Planos/Edit" } },
    "rb-faturas": { vista: "faturas", page: "RecurringBilling/Faturas/Index" },
    "rb-config": { vista: "config", page: "RecurringBilling/Configuracoes/Index" }
  };

  // ── estados da página (?estado=carregando|erro|sem_permissao) ──
  function EstadoCarregando() {
    return (
      <div role="status" aria-label="Carregando" style={{ margin: "16px 24px", display: "grid", gap: 10 }}>
        <div className="cr-skel" style={{ height: 76 }} />
        {[0, 1, 2, 3, 4].map((i) => <div key={i} className="cr-skel" style={{ height: 48 }} />)}
      </div>);

  }
  function EstadoAviso({ titulo, texto, acao }) {
    return (
      <div role="alert" style={{ margin: "40px 24px", padding: 32, textAlign: "center", border: "1px solid var(--border)", borderRadius: 10, background: "var(--surface)" }}>
        <div style={{ fontSize: 15, fontWeight: 600, color: "var(--text)" }}>{titulo}</div>
        <div style={{ fontSize: 12.5, color: "var(--text-mute)", marginTop: 6 }}>{texto}</div>
        {acao && <button className="cr-act" style={{ marginTop: 14 }}><I.refresh size={13} /> {acao}</button>}
      </div>);

  }

  // ── página ─────────────────────────────────────────────────────
  function CobrancaRecorrentePage({ view }) {
    const tab = view || "assinaturas";
    const [novaAssin, setNovaAssin] = useState(false);
    const [formPlano, setFormPlano] = useState(null);
    useEffect(() => {setNovaAssin(false);setFormPlano(null);}, [tab]);
    useEffect(() => {
      const onKey = (e) => {
        const el = document.activeElement;
        if (el && (el.tagName === "INPUT" || el.tagName === "TEXTAREA" || el.tagName === "SELECT")) return;
        if ((e.key === "n" || e.key === "N") && !e.metaKey && !e.ctrlKey) {
          if (tab === "assinaturas") setNovaAssin(true);
          if (tab === "planos") setFormPlano({ modo: "novo" });
        }
      };
      window.addEventListener("keydown", onKey);return () => window.removeEventListener("keydown", onKey);
    }, [tab]);

    const ativos = SUBS.filter(COBRANDO);
    const mrr = ativos.reduce((a, s) => a + monthly(s), 0);
    const churnMes = SUBS.filter((s) => s.canceladaEm && s.canceladaEm.slice(0, 7) === HOJE_ISO.slice(0, 7)).length;
    const churnRate = ativos.length + churnMes ? Math.round(churnMes / (ativos.length + churnMes) * 100) : 0;
    const planosAtivos = PLANOS_BASE.filter((p) => p.ativo);
    const mrrPot = planosAtivos.reduce((a, p) => a + p.valor / mesesDoCiclo(p.cycle, p.dias) * assinCount(p.name), 0);
    const atrasadas = FATURAS_BASE.filter((f) => f.status === "atrasada").length;
    const HEAD = {
      assinaturas: { h: "Cobrança recorrente", linha: `${ativos.length} COBRANDO · MRR ${BRL(mrr)} · CHURN DO MÊS ${churnRate}%` },
      planos: { h: "Planos", sub: "· cobrança recorrente", linha: `${PLANOS_BASE.length} PLANOS · ${planosAtivos.length} ATIVOS · MRR dos planos ${BRL(mrrPot)}` },
      faturas: { h: "Faturas", sub: "· cobrança recorrente", linha: `${FATURAS_BASE.length} FATURAS · ATRASADAS ${atrasadas}` },
      config: { h: "Configurações", sub: "· cobrança recorrente", linha: "Gateways de pagamento, régua de dunning, NFe automática e webhooks por gateway." }
    }[tab] || {};
    return (
      <div className="cr-root" data-screen-label="Cobrança Recorrente" data-rb-vista={tab}>
        <div className="cr-hero">
          <div className="cr-hero-top">
            <div className="cr-hero-title">
              <h1>{HEAD.h}{HEAD.sub && <span className="cr-sub"> {HEAD.sub}</span>}</h1>
              <p style={tab === "config" ? null : { ...MONO, fontSize: 11, textTransform: "uppercase", letterSpacing: "0.06em" }}>{HEAD.linha}</p>
            </div>
            <div className="cr-hero-actions">
              {ESTADO === "normal" && tab === "assinaturas" && <button className="cr-primary" onClick={() => setNovaAssin(true)}><I.plus size={14} /> Nova assinatura <kbd>N</kbd></button>}
              {ESTADO === "normal" && tab === "planos" && <button className="cr-primary" onClick={() => setFormPlano({ modo: "novo" })}><I.plus size={14} /> Novo plano <kbd>N</kbd></button>}
            </div>
          </div>
        </div>
        {window.PageHeaderNav && <window.PageHeaderNav route={window.__route} />}
        <div className="cr-body" aria-busy={ESTADO === "carregando"}>
          {ESTADO === "carregando" ? <EstadoCarregando /> :
          ESTADO === "erro" ? <EstadoAviso titulo="Não foi possível carregar a cobrança recorrente." texto="O servidor não respondeu. Seus dados não foram alterados." acao="Tentar de novo" /> :
          ESTADO === "sem_permissao" ? <EstadoAviso titulo="Você não tem acesso a esta tela." texto="Peça ao administrador da empresa a permissão de cobrança recorrente." /> :
          <>
          {tab === "assinaturas" && <Assinaturas onNova={() => setNovaAssin(true)} />}
          {tab === "planos" && <Planos formPlano={formPlano} setFormPlano={setFormPlano} />}
          {tab === "faturas" && <Faturas />}
          {tab === "config" && <Configuracoes />}
          </>}
        </div>
        {novaAssin && <NovaAssinatura onClose={() => setNovaAssin(false)} />}
      </div>);

  }

  window.CobrancaRecorrentePage = CobrancaRecorrentePage;
  window.RbRotas = ROTAS;
})();
