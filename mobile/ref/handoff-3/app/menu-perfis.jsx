// ────────────────────────────────────────────────
// PERFIS DE MENU — barra de módulos personalizável por função
// Início e Mais são fixos; o miolo (até 3 módulos) vem do perfil ativo.
// ────────────────────────────────────────────────

// Módulos que podem ocupar um slot da barra (id = chave do stack = rota raiz)
const MENU_MODULES = [
  { id: "tarefas",    label: "Tarefas",    ic: "inbox",   desc: "Inbox de pendências (OS, CRM, financeiro)" },
  { id: "pedidos",    label: "Pedidos",    ic: "file",    desc: "Ordens de serviço e seu andamento" },
  { id: "vendas",     label: "Vendas",     ic: "tag",     desc: "Consulta de vendas, orçamentos e faturamento" },
  { id: "producao",   label: "Produção",   ic: "printer", desc: "Fila de produção gráfica / chão de fábrica" },
  { id: "manutencao", label: "Oficina",    ic: "wrench",  desc: "Manutenção de frota — OS, pátio e peças" },
  { id: "equipamentos",label: "Equipamentos",ic: "truck", desc: "Frota e máquinas vinculadas a um cliente" },
  { id: "financas",   label: "Financeiro", ic: "dollar",  desc: "Caixa, contas a pagar e receber" },
  { id: "relatorios", label: "Relatórios", ic: "chart",   desc: "Vendas, fluxo, margem e produção" },
  { id: "clientes",   label: "Pessoas",    ic: "user",    desc: "Clientes, fornecedores e equipe" },
  { id: "produtos",   label: "Produtos",   ic: "box",     desc: "Catálogo e estoque" },
];

// Perfis pré-cadastrados (o admin pode editar / criar novos)
const MENU_PERFIS = [
  { id: "comvis",      nome: "Comunicação visual", funcao: "Produção gráfica",   mods: ["tarefas", "pedidos", "producao"],     sys: true },
  { id: "oficina",     nome: "Oficina / Mecânica", funcao: "Manutenção de frota", mods: ["manutencao", "pedidos", "clientes"],  sys: true },
  { id: "faturamento", nome: "Faturamento",        funcao: "Financeiro / fiscal", mods: ["vendas", "financas", "relatorios"],  sys: true },
  { id: "balcao",      nome: "Balcão / Vendas",    funcao: "Atendimento",         mods: ["vendas", "clientes", "produtos"],     sys: true },
];

window.MOCK.MENU_MODULES = MENU_MODULES;
window.MOCK.MENU_PERFIS = MENU_PERFIS;
window.MOCK.menuModule = (id) => MENU_MODULES.find(m => m.id === id);
