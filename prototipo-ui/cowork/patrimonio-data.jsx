// patrimonio-data.jsx — dados-semente, regras e permissões do módulo Patrimônio.
// Espelha Modules/AssetManagement: Entities/Asset (+Warranty/Transaction/Maintenance),
// AssetController (colunas do DataTable), Utils/AssetUtil (prefixos), LogsActivity
// (whitelist de campos auditados) e permitted_locations do usuário.
// Expõe window.PatData. Nenhum componente aqui — só domínio.
(() => {
const fmt = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtK = (n) => "R$ " + (Number(n || 0) / 1000).toFixed(1).replace(".", ",") + "k";
const d2 = (s) => { if (!s) return "—"; const [y, m, d] = String(s).split("-"); return d + "/" + m + "/" + y; };
// Data de calendário, nunca instante: "YYYY-MM-DD" parseado como UTC e formatado
// em horário local perde um dia em fuso negativo (o do produto). Entra e sai local.
const dataLocal = (v) => {
  if (!v) return null;
  if (v instanceof Date) return v;
  const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? new Date(+m[1], +m[2] - 1, +m[3]) : new Date(v);
};
const iso = (d) => {
  const x = dataLocal(d);
  if (!x) return "";
  const p = (n) => String(n).padStart(2, "0");
  return x.getFullYear() + "-" + p(x.getMonth() + 1) + "-" + p(x.getDate());
};
const HOJE = new Date("2026-08-21T09:42:00");
const dias = (a, b) => Math.round((dataLocal(b) - dataLocal(a)) / 86400000);
const hhmm = (d) => d.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });

const CATEGORIAS = { impressao: "Impressão", acabamento: "Corte e acabamento", informatica: "Informática", veiculos: "Veículos", ferramentas: "Ferramentas", mobiliario: "Mobiliário" };
const LOCAIS = { matriz: "Matriz · Guarulhos", centro: "Loja Centro", oficina: "Oficina" };
const TIPOS = { owned: "Possuído", rented: "Alugado", leased: "Arrendado" };
// Prazo de depreciação sugerido por categoria (o cadastro do bem manda; isto é o default).
const DEP_PADRAO = { impressao: 10, acabamento: 10, informatica: 5, veiculos: 5, ferramentas: 5, mobiliario: 10 };

const COLABORADORES = [
  { id: "larissa", nome: "Larissa Prado", papel: "Balcão · ROTA LIVRE" },
  { id: "wagner", nome: "Wagner Ramos", papel: "Escritório" },
  { id: "eliana", nome: "Eliana Costa", papel: "Financeiro" },
  { id: "marcos", nome: "Marcos Aurélio", papel: "Técnico · instalação" },
  { id: "bruna", nome: "Bruna Lima", papel: "Arte" },
];

// ─── Papéis (permissões do módulo + permitted_locations) ───
const PAPEIS = {
  gestor: { label: "Gestor do patrimônio", quem: "Wagner", locais: "all",
    perms: ["view", "create", "update", "delete", "allocate", "revoke", "maintenance.create", "view_all_maintenance", "settings", "export"] },
  operador: { label: "Operador de balcão", quem: "Larissa", locais: ["matriz", "centro"],
    perms: ["view", "allocate", "maintenance.create", "view_own_maintenance", "export"] },
  financeiro: { label: "Financeiro", quem: "Eliana", locais: "all",
    perms: ["view", "view_all_maintenance", "export"] },
};
const can = (papel, p) => (PAPEIS[papel] || PAPEIS.gestor).perms.includes(p);
const locaisPermitidos = (papel) => (PAPEIS[papel] || PAPEIS.gestor).locais;
const podeVerLocal = (papel, loc) => { const l = locaisPermitidos(papel); return l === "all" || l.includes(loc); };

// ─── assets (+ asset_warranties) ───
const SEED_BENS = [
  { id: "PAT-0001", nome: "Roland VersaCAMM VS-640", cat: "impressao", loc: "matriz", modelo: "VS-640", serie: "RVS64-2219",
    compra: "2023-03-12", tipo: "owned", valor: 68500, qtd: 1, alocavel: false, img: true, dep: 10,
    garantia: { ini: "2023-03-12", fim: "2026-09-12", fornec: "Roland Care · contrato RC-8842" },
    desc: "Impressora/recortadora eco-solvente 1,62m — coração do setor de comunicação visual." },
  { id: "PAT-0002", nome: "HP Latex 335", cat: "impressao", loc: "matriz", modelo: "335 (V8L38A)", serie: "MY9CM2R0P1",
    compra: "2022-06-30", tipo: "owned", valor: 54900, qtd: 1, alocavel: false, img: true, dep: 10,
    garantia: { ini: "2022-06-30", fim: "2025-06-30", fornec: "HP Brasil · suporte encerrado" },
    desc: "Látex 1,62m para lona e adesivo — sem odor, entrega no mesmo dia." },
  { id: "PAT-0003", nome: "Plotter de corte Summa D60", cat: "acabamento", loc: "matriz", modelo: "D60", serie: "SD60-40917",
    compra: "2024-01-18", tipo: "owned", valor: 21400, qtd: 1, alocavel: false, img: false, dep: 10,
    garantia: { ini: "2024-01-18", fim: "2027-01-18", fornec: "Summa · 36 meses" },
    desc: "Recorte de vinil e refile de adesivo." },
  { id: "PAT-0004", nome: "Laminadora a frio 1,60m", cat: "acabamento", loc: "matriz", modelo: "LF-1600", serie: "LF16-0233",
    compra: "2023-11-04", tipo: "owned", valor: 8200, qtd: 1, alocavel: false, img: false, dep: 10,
    garantia: { ini: "2023-11-04", fim: "2024-11-04", fornec: "Fortec · 12 meses" },
    desc: "Laminação de proteção UV em adesivo e lona." },
  { id: "PAT-0005", nome: "Router CNC 1200×900", cat: "acabamento", loc: "oficina", modelo: "CNC-1290", serie: "RT129-0071",
    compra: "2025-02-20", tipo: "leased", valor: 32000, qtd: 1, alocavel: false, img: true, dep: 10,
    garantia: { ini: "2025-02-20", fim: "2028-02-20", fornec: "Contrato de arrendamento · 36 meses" },
    desc: "Corte de PVC, ACM e MDF para letra caixa e totem." },
  { id: "PAT-0006", nome: "Notebook Dell Latitude 5440", cat: "informatica", loc: "matriz", modelo: "Latitude 5440", serie: "lote 4 un.",
    compra: "2025-07-08", tipo: "owned", valor: 5400, qtd: 4, alocavel: true, img: false, dep: 5,
    garantia: { ini: "2025-07-08", fim: "2028-07-08", fornec: "Dell ProSupport" },
    desc: "Estações de orçamento e arte — atendimento e produção." },
  { id: "PAT-0007", nome: "Monitor 27\" Dell P2723DE", cat: "informatica", loc: "matriz", modelo: "P2723DE", serie: "lote 6 un.",
    compra: "2025-07-08", tipo: "owned", valor: 1890, qtd: 6, alocavel: true, img: false, dep: 5,
    garantia: { ini: "2025-07-08", fim: "2028-07-08", fornec: "Dell ProSupport" },
    desc: "Monitores das bancadas de arte e balcão." },
  { id: "PAT-0008", nome: "Fiat Fiorino Endurance", cat: "veiculos", loc: "matriz", modelo: "2021/2022", serie: "9BD26512MP1234567", placa: "RLV4C21",
    compra: "2022-02-14", tipo: "owned", valor: 78000, qtd: 1, alocavel: false, img: true, dep: 5,
    garantia: { ini: "2022-02-14", fim: "2025-02-14", fornec: "Fiat · 36 meses" },
    desc: "Entrega de comunicação visual e instalação em cliente." },
  { id: "PAT-0009", nome: "Furadeira de impacto Bosch GSB", cat: "ferramentas", loc: "oficina", modelo: "GSB 16 RE", serie: "lote 3 un.",
    compra: "2024-09-02", tipo: "rented", valor: 780, qtd: 3, alocavel: true, img: false, dep: 5, garantia: null,
    desc: "Instalação de fachada e placa — kit do técnico." },
  { id: "PAT-0010", nome: "Compressor de ar 50L", cat: "ferramentas", loc: "oficina", modelo: "CSL 10BR/50", serie: "CP50-1180",
    compra: "2024-05-15", tipo: "owned", valor: 2650, qtd: 1, alocavel: false, img: false, dep: 10,
    garantia: { ini: "2024-05-15", fim: "2026-05-15", fornec: "Schulz · 24 meses" },
    desc: "Pintura e limpeza de peça na oficina." },
  { id: "PAT-0011", nome: "Balcão de atendimento MDF", cat: "mobiliario", loc: "centro", modelo: "sob medida", serie: "—",
    compra: "2023-08-21", tipo: "owned", valor: 4300, qtd: 1, alocavel: false, img: true, dep: 10, garantia: null,
    desc: "Balcão da Loja Centro — ROTA LIVRE." },
];

// ─── asset_transactions: allocate / revoke ───
const SEED_ALOCACOES = [
  { id: "ALO-0031", bem: "PAT-0006", para: "Larissa Prado", papel: "Balcão · ROTA LIVRE", em: "2026-07-02", ate: "2026-12-31", qtd: 1, por: "Wagner", motivo: "Estação de orçamento do balcão", revoke: null },
  { id: "ALO-0030", bem: "PAT-0006", para: "Eliana Costa", papel: "Financeiro", em: "2026-07-02", ate: null, qtd: 1, por: "Wagner", motivo: "Fechamento e conciliação", revoke: null },
  { id: "ALO-0029", bem: "PAT-0007", para: "Wagner Ramos", papel: "Escritório", em: "2026-07-10", ate: null, qtd: 2, por: "Wagner", motivo: "Bancada de dashboards", revoke: null },
  { id: "ALO-0028", bem: "PAT-0009", para: "Marcos Aurélio", papel: "Técnico · instalação", em: "2026-06-18", ate: "2026-08-15", qtd: 1, por: "Wagner", motivo: "Kit de instalação de fachada",
    revoke: { id: "REV-0012", em: "2026-08-14", qtd: 1, por: "Wagner", motivo: "Fim da obra do cliente Martinho" } },
  { id: "ALO-0027", bem: "PAT-0006", para: "Bruna Lima", papel: "Arte", em: "2026-05-04", ate: "2026-06-30", qtd: 1, por: "Wagner", motivo: "Projeto sazonal de vitrine",
    revoke: { id: "REV-0011", em: "2026-06-30", qtd: 1, por: "Bruna", motivo: "Projeto concluído" } },
];

// ─── asset_maintenances ───
const SEED_MANUTENCOES = [
  { id: "MAN-0009", bem: "PAT-0001", status: "andamento", ini: "2026-08-14", fim: null, prestador: "Roland Care", resp: "Wagner", custo: 1850, titulo: null,
    notas: "Troca de dampers e limpeza da cabeça — preventiva de 20 mil m²." },
  { id: "MAN-0008", bem: "PAT-0002", status: "concluida", ini: "2026-07-28", fim: "2026-08-02", prestador: "Tecnojet", resp: "Wagner", custo: 4200, titulo: "TIT-4402",
    notas: "Cabeça de impressão substituída — fora de garantia, custo integral." },
  { id: "MAN-0007", bem: "PAT-0008", status: "agendada", ini: "2026-08-28", fim: null, prestador: "Fiat Rodobens", resp: "Marcos", custo: 890, titulo: null,
    notas: "Revisão de 40 mil km — troca de óleo, filtros e correia." },
  { id: "MAN-0006", bem: "PAT-0004", status: "concluida", ini: "2026-05-11", fim: "2026-05-12", prestador: "Fortec", resp: "Bruna", custo: 420, titulo: "TIT-4188",
    notas: "Rolo de silicone reposto." },
];

// ─── Trilha de auditoria (LogsActivity · assetmanagement.asset) ───
// Whitelist do Entities/Asset: description NÃO é auditada (pode carregar PII).
const CAMPOS_AUDITADOS = { nome: "name", id: "asset_code", cat: "category_id", loc: "location_id", qtd: "quantity", alocavel: "is_allocatable", compra: "purchase_date", valor: "purchase_amount" };
const CAMPO_LABEL = { nome: "Nome", id: "Código do ativo", cat: "Categoria", loc: "Local", qtd: "Quantidade", alocavel: "É atribuível?", compra: "Data da compra", valor: "Valor de aquisição" };
// dia = ISO (filtro e ordenação) · hora = hh:mm. A exibição formata com d2() —
// nunca se extrai data de string de exibição.
const SEED_LOG = [
  { id: "LOG-0112", dia: "2026-08-14", hora: "08:31", quem: "Wagner", alvo: "PAT-0001", evento: "Enviado pra manutenção", campos: [] },
  { id: "LOG-0111", dia: "2026-08-14", hora: "08:29", quem: "Wagner", alvo: "ALO-0028", evento: "Alocação revogada (REV-0012)", campos: [] },
  { id: "LOG-0110", dia: "2026-08-02", hora: "17:04", quem: "Wagner", alvo: "PAT-0002", evento: "Manutenção concluída · custo adicional R$ 4.200,00", campos: [] },
  { id: "LOG-0109", dia: "2026-07-10", hora: "10:12", quem: "Wagner", alvo: "PAT-0007", evento: "Bem atualizado", campos: [{ campo: "qtd", de: "4", para: "6" }] },
  { id: "LOG-0108", dia: "2026-07-08", hora: "09:55", quem: "Wagner", alvo: "PAT-0007", evento: "Bem criado", campos: [] },
];
// Rótulo de um evento de log: dd/mm/aaaa hh:mm (canon do DS).
const logQuando = (l) => d2(l.dia) + " " + l.hora;

// ─── Regras ───
function garantia(b) {
  if (!b.garantia) return { st: "sem", dias: null };
  const d = dias(HOJE, b.garantia.fim);
  if (d < 0) return { st: "out", dias: d, fim: b.garantia.fim };
  if (d <= 30) return { st: "soon", dias: d, fim: b.garantia.fim };
  return { st: "in", dias: d, fim: b.garantia.fim };
}
function depreciacao(b) {
  const meses = Math.max(0, Math.round(dias(b.compra, HOJE) / 30.44));
  const total = (b.dep || DEP_PADRAO[b.cat] || 10) * 12;
  const acum = Math.min(1, meses / total);
  const bruto = b.valor * b.qtd;
  return { meses, total, pct: Math.round(acum * 100), residual: bruto * (1 - acum), bruto };
}
const alocadoQtd = (alocs, id) => alocs.filter((a) => a.bem === id && !a.revoke).reduce((s, a) => s + a.qtd, 0);
const saldo = (alocs, b) => b.qtd - alocadoQtd(alocs, b.id);
const emManutencao = (mans, id) => mans.some((m) => m.bem === id && m.status !== "concluida");
// Asset::forDropdown — só bem atribuível com saldo > 0 entra na alocação.
const paraAlocar = (bens, alocs, papel) => bens.filter((b) => b.alocavel && saldo(alocs, b) > 0 && podeVerLocal(papel, b.loc));
function proximoCodigo(prefixo, lista, campo) {
  const n = lista.reduce((mx, x) => {
    const v = String(campo ? x[campo] : x.id || "");
    const m = v.match(/(\d+)$/);
    return m ? Math.max(mx, +m[1]) : mx;
  }, 0);
  return prefixo + String(n + 1).padStart(4, "0");
}

// CSV do que está na tela (mesmas colunas visíveis) — export real, não toast.
function baixarCsv(nome, colunas, linhas) {
  const esc = (v) => '"' + String(v == null ? "" : v).replace(/"/g, '""') + '"';
  const csv = [colunas.map(esc).join(";")].concat(linhas.map((l) => l.map(esc).join(";"))).join("\r\n");
  const url = URL.createObjectURL(new Blob(["\ufeff" + csv], { type: "text/csv;charset=utf-8" }));
  const a = document.createElement("a");
  a.href = url; a.download = nome + ".csv";
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 4000);
}

window.PatData = {
  fmt, fmtK, d2, iso, dataLocal, dias, hhmm, HOJE,
  CATEGORIAS, LOCAIS, TIPOS, DEP_PADRAO, COLABORADORES, PAPEIS, can, locaisPermitidos, podeVerLocal,
  SEED_BENS, SEED_ALOCACOES, SEED_MANUTENCOES, SEED_LOG, CAMPOS_AUDITADOS, CAMPO_LABEL, logQuando,
  garantia, depreciacao, alocadoQtd, saldo, emManutencao, paraAlocar, proximoCodigo, baixarCsv,
};
})();
