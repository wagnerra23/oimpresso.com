export interface Tenant {
  id: string;
  name: string;
  color: string;
  initials: string;
}

export interface Client {
  id: string;
  name: string;
  email: string;
  phone: string;
  type: 'pf' | 'pj';
  document: string;
  address: string;
  city: string;
  status: 'active' | 'inactive';
  avatar: string;
  createdAt: string;
  ordersCount: number;
  totalSpent: number;
}

export interface Product {
  id: string;
  name: string;
  description: string;
  category: string;
  price: number;
  cost: number;
  stock: number;
  unit: string;
  status: 'active' | 'inactive';
  sku: string;
  minStock: number;
}

export interface OrderItem {
  id: string;
  productId: string;
  productName: string;
  quantity: number;
  unitPrice: number;
  total: number;
}

export interface Order {
  id: string;
  number: string;
  clientId: string;
  clientName: string;
  clientAvatar: string;
  status: 'pending' | 'approved' | 'in_production' | 'ready' | 'delivered' | 'cancelled';
  priority: 'low' | 'medium' | 'high' | 'urgent';
  items: OrderItem[];
  total: number;
  createdAt: string;
  deliveryDate: string;
  notes: string;
}

export interface Task {
  id: string;
  title: string;
  description: string;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  status: 'pending' | 'in_progress' | 'completed' | 'blocked';
  dueDate: string;
  assignee: string;
  assigneeAvatar: string;
  category: string;
  subtasks: { id: string; title: string; completed: boolean }[];
  createdAt: string;
}

export interface ProductionStage {
  id: string;
  name: string;
  status: 'pending' | 'in_progress' | 'completed' | 'blocked';
  startDate?: string;
  endDate?: string;
  operator?: string;
  notes?: string;
}

export interface ProductionOrder {
  id: string;
  number: string;
  orderId: string;
  orderNumber: string;
  clientName: string;
  productName: string;
  quantity: number;
  status: 'pending' | 'in_preparation' | 'in_printing' | 'in_finishing' | 'quality_check' | 'completed';
  priority: 'low' | 'medium' | 'high' | 'urgent';
  stages: ProductionStage[];
  startDate: string;
  estimatedEnd: string;
  actualEnd?: string;
  progress: number;
  operator: string;
  operatorAvatar: string;
}

export const TENANTS: Tenant[] = [
  { id: 'wr2', name: 'WebRun 2', color: '#0A84FF', initials: 'WR' },
  { id: 'graf', name: 'Gráfica Central', color: '#30D158', initials: 'GC' },
  { id: 'poli', name: 'Poligráfica Sul', color: '#FF9F0A', initials: 'PS' },
  { id: 'mega', name: 'MegaPrint', color: '#FF453A', initials: 'MP' },
];

export const CLIENTS: Client[] = [
  { id: 'c1', name: 'Empresa ABC Ltda', email: 'contato@abc.com', phone: '(11) 3456-7890', type: 'pj', document: '12.345.678/0001-90', address: 'Rua das Flores, 123', city: 'São Paulo, SP', status: 'active', avatar: 'AB', createdAt: '2024-01-15', ordersCount: 12, totalSpent: 45600 },
  { id: 'c2', name: 'João Silva', email: 'joao@email.com', phone: '(11) 98765-4321', type: 'pf', document: '123.456.789-00', address: 'Av. Paulista, 1000', city: 'São Paulo, SP', status: 'active', avatar: 'JS', createdAt: '2024-02-20', ordersCount: 5, totalSpent: 12800 },
  { id: 'c3', name: 'Mariana Costa', email: 'mariana@email.com', phone: '(21) 99876-5432', type: 'pf', document: '987.654.321-00', address: 'Rua do Comércio, 45', city: 'Rio de Janeiro, RJ', status: 'active', avatar: 'MC', createdAt: '2024-03-10', ordersCount: 8, totalSpent: 23400 },
  { id: 'c4', name: 'PrintSolucoes S.A.', email: 'pedidos@printsolucoes.com', phone: '(31) 3456-7890', type: 'pj', document: '98.765.432/0001-10', address: 'Av. Industrial, 500', city: 'Belo Horizonte, MG', status: 'active', avatar: 'PS', createdAt: '2024-01-05', ordersCount: 25, totalSpent: 89200 },
  { id: 'c5', name: 'Carlos Mendes', email: 'carlos@email.com', phone: '(11) 97654-3210', type: 'pf', document: '456.789.123-00', address: 'Rua dos Artistas, 78', city: 'São Paulo, SP', status: 'inactive', avatar: 'CM', createdAt: '2024-04-01', ordersCount: 2, totalSpent: 3400 },
  { id: 'c6', name: 'Editora Horizonte', email: 'compras@editorahorizonte.com', phone: '(19) 3234-5678', type: 'pj', document: '55.444.333/0001-22', address: 'Rua das Letras, 200', city: 'Campinas, SP', status: 'active', avatar: 'EH', createdAt: '2024-02-15', ordersCount: 18, totalSpent: 56700 },
  { id: 'c7', name: 'Fernanda Lima', email: 'fernanda@email.com', phone: '(47) 98877-6655', type: 'pf', document: '789.123.456-00', address: 'Av. Beira Mar, 1500', city: 'Florianópolis, SC', status: 'active', avatar: 'FL', createdAt: '2024-05-20', ordersCount: 3, totalSpent: 7800 },
  { id: 'c8', name: 'Distribuidora Norte', email: 'vendas@distribuidoranorte.com', phone: '(85) 3333-4444', type: 'pj', document: '11.222.333/0001-44', address: 'Rua do Comércio, 800', city: 'Fortaleza, CE', status: 'active', avatar: 'DN', createdAt: '2024-03-25', ordersCount: 10, totalSpent: 34500 },
];

export const PRODUCTS: Product[] = [
  { id: 'p1', name: 'Cartão de Visita Couchê 300g', description: 'Cartão de visita premium em couchê 300g com verniz UV', category: 'Cartões', price: 89.90, cost: 45.00, stock: 5000, unit: 'un', status: 'active', sku: 'CDV-COU-300', minStock: 1000 },
  { id: 'p2', name: 'Folder A4 150g', description: 'Folder A4 em couchê 150g com dobra central', category: 'Folders', price: 125.00, cost: 62.50, stock: 3000, unit: 'un', status: 'active', sku: 'FLD-A4-150', minStock: 500 },
  { id: 'p3', name: 'Banner Lona 440g', description: 'Banner em lona 440g com ilhós', category: 'Banners', price: 59.90, cost: 28.00, stock: 200, unit: 'm²', status: 'active', sku: 'BNR-LON-440', minStock: 50 },
  { id: 'p4', name: 'Adesivo Vinil Branco', description: 'Adesivo vinil branco com corte reto', category: 'Adesivos', price: 34.90, cost: 16.00, stock: 8000, unit: 'm²', status: 'active', sku: 'ADS-VIN-BRC', minStock: 2000 },
  { id: 'p5', name: 'Revista 16 páginas', description: 'Revista em couchê 90g, 16 páginas, capa 250g', category: 'Revistas', price: 450.00, cost: 225.00, stock: 500, unit: 'un', status: 'active', sku: 'REV-16P-C90', minStock: 100 },
  { id: 'p6', name: 'Envelope Ofício', description: 'Envelope saco ofício 90g', category: 'Envelopes', price: 45.00, cost: 20.00, stock: 10000, unit: 'un', status: 'active', sku: 'ENV-OFC-90', minStock: 2000 },
  { id: 'p7', name: 'Papel Timbrado A4', description: 'Papel timbrado A4 90g em 1 cor', category: 'Papelaria', price: 78.00, cost: 35.00, stock: 15000, unit: 'un', status: 'active', sku: 'PTB-A4-90', minStock: 3000 },
  { id: 'p8', name: 'Cartaz A3 150g', description: 'Cartaz A3 em couchê 150g', category: 'Cartazes', price: 12.90, cost: 5.50, stock: 2500, unit: 'un', status: 'active', sku: 'CTZ-A3-150', minStock: 500 },
  { id: 'p9', name: 'Bloco Notas 100fl', description: 'Bloco de notas 100 folhas 75g', category: 'Papelaria', price: 15.90, cost: 7.00, stock: 3000, unit: 'un', status: 'active', sku: 'BLN-100-75', minStock: 500 },
  { id: 'p10', name: 'Calendário de Parede', description: 'Calendário de parede 28x40cm couchê 250g', category: 'Calendários', price: 29.90, cost: 13.00, stock: 800, unit: 'un', status: 'active', sku: 'CLD-PRD-250', minStock: 200 },
];

export const ORDERS: Order[] = [
  {
    id: 'o1', number: 'PED-2024-001', clientId: 'c1', clientName: 'Empresa ABC Ltda', clientAvatar: 'AB',
    status: 'in_production', priority: 'high',
    items: [
      { id: 'i1', productId: 'p1', productName: 'Cartão de Visita Couchê 300g', quantity: 1000, unitPrice: 89.90, total: 89900 },
      { id: 'i2', productId: 'p2', productName: 'Folder A4 150g', quantity: 500, unitPrice: 125.00, total: 62500 },
    ],
    total: 152400, createdAt: '2024-06-01', deliveryDate: '2024-06-15', notes: 'Urgente para evento'
  },
  {
    id: 'o2', number: 'PED-2024-002', clientId: 'c4', clientName: 'PrintSolucoes S.A.', clientAvatar: 'PS',
    status: 'pending', priority: 'urgent',
    items: [
      { id: 'i3', productId: 'p5', productName: 'Revista 16 páginas', quantity: 200, unitPrice: 450.00, total: 90000 },
    ],
    total: 90000, createdAt: '2024-06-02', deliveryDate: '2024-06-10', notes: 'Cliente VIP'
  },
  {
    id: 'o3', number: 'PED-2024-003', clientId: 'c2', clientName: 'João Silva', clientAvatar: 'JS',
    status: 'approved', priority: 'medium',
    items: [
      { id: 'i4', productId: 'p3', productName: 'Banner Lona 440g', quantity: 5, unitPrice: 59.90, total: 29950 },
      { id: 'i5', productId: 'p4', productName: 'Adesivo Vinil Branco', quantity: 10, unitPrice: 34.90, total: 34900 },
    ],
    total: 64850, createdAt: '2024-06-03', deliveryDate: '2024-06-20', notes: ''
  },
  {
    id: 'o4', number: 'PED-2024-004', clientId: 'c6', clientName: 'Editora Horizonte', clientAvatar: 'EH',
    status: 'ready', priority: 'high',
    items: [
      { id: 'i6', productId: 'p5', productName: 'Revista 16 páginas', quantity: 500, unitPrice: 450.00, total: 225000 },
      { id: 'i7', productId: 'p7', productName: 'Papel Timbrado A4', quantity: 5000, unitPrice: 78.00, total: 390000 },
    ],
    total: 615000, createdAt: '2024-05-28', deliveryDate: '2024-06-12', notes: 'Edição especial junho'
  },
  {
    id: 'o5', number: 'PED-2024-005', clientId: 'c3', clientName: 'Mariana Costa', clientAvatar: 'MC',
    status: 'delivered', priority: 'low',
    items: [
      { id: 'i8', productId: 'p1', productName: 'Cartão de Visita Couchê 300g', quantity: 500, unitPrice: 89.90, total: 44950 },
    ],
    total: 44950, createdAt: '2024-05-20', deliveryDate: '2024-05-30', notes: 'Entregue com sucesso'
  },
  {
    id: 'o6', number: 'PED-2024-006', clientId: 'c8', clientName: 'Distribuidora Norte', clientAvatar: 'DN',
    status: 'pending', priority: 'medium',
    items: [
      { id: 'i9', productId: 'p8', productName: 'Cartaz A3 150g', quantity: 1000, unitPrice: 12.90, total: 12900 },
      { id: 'i10', productId: 'p10', productName: 'Calendário de Parede', quantity: 200, unitPrice: 29.90, total: 5980 },
    ],
    total: 18880, createdAt: '2024-06-04', deliveryDate: '2024-06-25', notes: 'Campanha de midias'
  },
  {
    id: 'o7', number: 'PED-2024-007', clientId: 'c1', clientName: 'Empresa ABC Ltda', clientAvatar: 'AB',
    status: 'in_production', priority: 'urgent',
    items: [
      { id: 'i11', productId: 'p2', productName: 'Folder A4 150g', quantity: 2000, unitPrice: 125.00, total: 250000 },
      { id: 'i12', productId: 'p6', productName: 'Envelope Ofício', quantity: 5000, unitPrice: 45.00, total: 225000 },
    ],
    total: 475000, createdAt: '2024-06-05', deliveryDate: '2024-06-18', notes: 'Feira do setor'
  },
  {
    id: 'o8', number: 'PED-2024-008', clientId: 'c7', clientName: 'Fernanda Lima', clientAvatar: 'FL',
    status: 'approved', priority: 'low',
    items: [
      { id: 'i13', productId: 'p9', productName: 'Bloco Notas 100fl', quantity: 100, unitPrice: 15.90, total: 1590 },
    ],
    total: 1590, createdAt: '2024-06-06', deliveryDate: '2024-06-30', notes: ''
  },
];

export const TASKS: Task[] = [
  {
    id: 't1', title: 'Aprovar arte final - Pedido 001', description: 'Revisar e aprovar a arte final dos cartões de visita', priority: 'urgent', status: 'pending',
    dueDate: '2024-06-07', assignee: 'Carlos Designer', assigneeAvatar: 'CD', category: 'Design',
    subtasks: [
      { id: 'st1', title: 'Verificar cores CMYK', completed: false },
      { id: 'st2', title: 'Confirmar sangria', completed: false },
      { id: 'st3', title: 'Aprovar prova digital', completed: false },
    ],
    createdAt: '2024-06-05'
  },
  {
    id: 't2', title: 'Preparar chapas - Revista Horizonte', description: 'Preparar chapas de impressão para a revista de 500 unidades', priority: 'high', status: 'in_progress',
    dueDate: '2024-06-08', assignee: 'Maria Operadora', assigneeAvatar: 'MO', category: 'Produção',
    subtasks: [
      { id: 'st4', title: 'Ripar arquivos', completed: true },
      { id: 'st5', title: 'Montar chapas', completed: true },
      { id: 'st6', title: 'Enviar para CTP', completed: false },
    ],
    createdAt: '2024-06-04'
  },
  {
    id: 't3', title: 'Conferir estoque de papel', description: 'Verificar disponibilidade de papel couchê 300g para próximos pedidos', priority: 'medium', status: 'pending',
    dueDate: '2024-06-10', assignee: 'Ana Estoque', assigneeAvatar: 'AE', category: 'Estoque',
    subtasks: [
      { id: 'st7', title: 'Contagem física', completed: false },
      { id: 'st8', title: 'Atualizar sistema', completed: false },
    ],
    createdAt: '2024-06-05'
  },
  {
    id: 't4', title: 'Manutenção Heidelberg', description: 'Manutenção preventiva semanal na impressora Heidelberg', priority: 'high', status: 'blocked',
    dueDate: '2024-06-09', assignee: 'Pedro Técnico', assigneeAvatar: 'PT', category: 'Manutenção',
    subtasks: [
      { id: 'st9', title: 'Limpeza de cilindros', completed: false },
      { id: 'st10', title: 'Lubrificação', completed: false },
      { id: 'st11', title: 'Calibração de cores', completed: false },
    ],
    createdAt: '2024-06-03'
  },
  {
    id: 't5', title: 'Embalar pedido 005', description: 'Embalar e preparar pedido 005 para entrega', priority: 'low', status: 'completed',
    dueDate: '2024-06-05', assignee: 'Lucas Logística', assigneeAvatar: 'LL', category: 'Expedição',
    subtasks: [
      { id: 'st12', title: 'Separar itens', completed: true },
      { id: 'st13', title: 'Embalar', completed: true },
      { id: 'st14', title: 'Gerar etiqueta', completed: true },
    ],
    createdAt: '2024-06-04'
  },
  {
    id: 't6', title: 'Orçamento folder institucional', description: 'Preparar orçamento para folder A4 com 8 páginas', priority: 'medium', status: 'pending',
    dueDate: '2024-06-12', assignee: 'Carlos Designer', assigneeAvatar: 'CD', category: 'Comercial',
    subtasks: [
      { id: 'st15', title: 'Calcular custos', completed: false },
      { id: 'st16', title: 'Definir margem', completed: false },
    ],
    createdAt: '2024-06-06'
  },
  {
    id: 't7', title: 'Reclamação cliente ABC', description: 'Resolver problema de tonalidade no último lote', priority: 'urgent', status: 'in_progress',
    dueDate: '2024-06-07', assignee: 'Maria Operadora', assigneeAvatar: 'MO', category: 'Qualidade',
    subtasks: [
      { id: 'st17', title: 'Analisar amostra', completed: true },
      { id: 'st18', title: 'Identificar causa raiz', completed: true },
      { id: 'st19', title: 'Propor solução', completed: false },
    ],
    createdAt: '2024-06-06'
  },
  {
    id: 't8', title: 'Treinamento novo operador', description: 'Treinar novo operador na máquina de corte e vinco', priority: 'low', status: 'pending',
    dueDate: '2024-06-15', assignee: 'Pedro Técnico', assigneeAvatar: 'PT', category: 'RH',
    subtasks: [
      { id: 'st20', title: 'Preparar material', completed: false },
      { id: 'st21', title: 'Agendar prática', completed: false },
    ],
    createdAt: '2024-06-05'
  },
];

export const PRODUCTION_ORDERS: ProductionOrder[] = [
  {
    id: 'po1', number: 'OP-2024-001', orderId: 'o1', orderNumber: 'PED-2024-001', clientName: 'Empresa ABC Ltda', productName: 'Cartão de Visita Couchê 300g', quantity: 1000,
    status: 'in_printing', priority: 'high',
    stages: [
      { id: 'ps1', name: 'Preparação', status: 'completed', startDate: '2024-06-01', endDate: '2024-06-02', operator: 'Maria Operadora', notes: 'Arquivos ripados' },
      { id: 'ps2', name: 'Impressão', status: 'in_progress', startDate: '2024-06-03', operator: 'João Impressor', notes: '50% concluído' },
      { id: 'ps3', name: 'Acabamento', status: 'pending', operator: 'Ana Acabamento' },
      { id: 'ps4', name: 'Controle de Qualidade', status: 'pending', operator: 'Pedro Qualidade' },
      { id: 'ps5', name: 'Expedição', status: 'pending', operator: 'Lucas Logística' },
    ],
    startDate: '2024-06-01', estimatedEnd: '2024-06-14', progress: 45,
    operator: 'João Impressor', operatorAvatar: 'JI'
  },
  {
    id: 'po2', number: 'OP-2024-002', orderId: 'o4', orderNumber: 'PED-2024-004', clientName: 'Editora Horizonte', productName: 'Revista 16 páginas', quantity: 500,
    status: 'in_finishing', priority: 'high',
    stages: [
      { id: 'ps6', name: 'Preparação', status: 'completed', startDate: '2024-05-28', endDate: '2024-05-29', operator: 'Maria Operadora', notes: 'Chapas montadas' },
      { id: 'ps7', name: 'Impressão', status: 'completed', startDate: '2024-05-30', endDate: '2024-06-02', operator: 'João Impressor', notes: 'Impressão finalizada' },
      { id: 'ps8', name: 'Acabamento', status: 'in_progress', startDate: '2024-06-03', operator: 'Ana Acabamento', notes: 'Dobras e grampos' },
      { id: 'ps9', name: 'Controle de Qualidade', status: 'pending', operator: 'Pedro Qualidade' },
      { id: 'ps10', name: 'Expedição', status: 'pending', operator: 'Lucas Logística' },
    ],
    startDate: '2024-05-28', estimatedEnd: '2024-06-12', progress: 72,
    operator: 'Ana Acabamento', operatorAvatar: 'AA'
  },
  {
    id: 'po3', number: 'OP-2024-003', orderId: 'o7', orderNumber: 'PED-2024-007', clientName: 'Empresa ABC Ltda', productName: 'Folder A4 150g + Envelope Ofício', quantity: 7000,
    status: 'in_preparation', priority: 'urgent',
    stages: [
      { id: 'ps11', name: 'Preparação', status: 'in_progress', startDate: '2024-06-05', operator: 'Maria Operadora', notes: 'Ripando arquivos' },
      { id: 'ps12', name: 'Impressão', status: 'pending', operator: 'João Impressor' },
      { id: 'ps13', name: 'Acabamento', status: 'pending', operator: 'Ana Acabamento' },
      { id: 'ps14', name: 'Controle de Qualidade', status: 'pending', operator: 'Pedro Qualidade' },
      { id: 'ps15', name: 'Expedição', status: 'pending', operator: 'Lucas Logística' },
    ],
    startDate: '2024-06-05', estimatedEnd: '2024-06-17', progress: 15,
    operator: 'Maria Operadora', operatorAvatar: 'MO'
  },
  {
    id: 'po4', number: 'OP-2024-004', orderId: 'o2', orderNumber: 'PED-2024-002', clientName: 'PrintSolucoes S.A.', productName: 'Revista 16 páginas', quantity: 200,
    status: 'pending', priority: 'urgent',
    stages: [
      { id: 'ps16', name: 'Preparação', status: 'pending', operator: 'Maria Operadora' },
      { id: 'ps17', name: 'Impressão', status: 'pending', operator: 'João Impressor' },
      { id: 'ps18', name: 'Acabamento', status: 'pending', operator: 'Ana Acabamento' },
      { id: 'ps19', name: 'Controle de Qualidade', status: 'pending', operator: 'Pedro Qualidade' },
      { id: 'ps20', name: 'Expedição', status: 'pending', operator: 'Lucas Logística' },
    ],
    startDate: '2024-06-07', estimatedEnd: '2024-06-13', progress: 0,
    operator: 'Pendente', operatorAvatar: 'PN'
  },
  {
    id: 'po5', number: 'OP-2024-005', orderId: 'o3', orderNumber: 'PED-2024-003', clientName: 'João Silva', productName: 'Banner + Adesivo', quantity: 15,
    status: 'completed', priority: 'medium',
    stages: [
      { id: 'ps21', name: 'Preparação', status: 'completed', startDate: '2024-06-03', endDate: '2024-06-03', operator: 'Maria Operadora' },
      { id: 'ps22', name: 'Impressão', status: 'completed', startDate: '2024-06-04', endDate: '2024-06-04', operator: 'João Impressor' },
      { id: 'ps23', name: 'Acabamento', status: 'completed', startDate: '2024-06-05', endDate: '2024-06-05', operator: 'Ana Acabamento' },
      { id: 'ps24', name: 'Controle de Qualidade', status: 'completed', startDate: '2024-06-05', endDate: '2024-06-05', operator: 'Pedro Qualidade' },
      { id: 'ps25', name: 'Expedição', status: 'completed', startDate: '2024-06-06', endDate: '2024-06-06', operator: 'Lucas Logística' },
    ],
    startDate: '2024-06-03', estimatedEnd: '2024-06-10', actualEnd: '2024-06-06', progress: 100,
    operator: 'Lucas Logística', operatorAvatar: 'LL'
  },
  {
    id: 'po6', number: 'OP-2024-006', orderId: 'o6', orderNumber: 'PED-2024-006', clientName: 'Distribuidora Norte', productName: 'Cartaz A3 + Calendário', quantity: 1200,
    status: 'quality_check', priority: 'medium',
    stages: [
      { id: 'ps26', name: 'Preparação', status: 'completed', startDate: '2024-06-04', endDate: '2024-06-04', operator: 'Maria Operadora' },
      { id: 'ps27', name: 'Impressão', status: 'completed', startDate: '2024-06-05', endDate: '2024-06-05', operator: 'João Impressor' },
      { id: 'ps28', name: 'Acabamento', status: 'completed', startDate: '2024-06-06', endDate: '2024-06-06', operator: 'Ana Acabamento' },
      { id: 'ps29', name: 'Controle de Qualidade', status: 'in_progress', startDate: '2024-06-07', operator: 'Pedro Qualidade', notes: 'Verificando cores' },
      { id: 'ps30', name: 'Expedição', status: 'pending', operator: 'Lucas Logística' },
    ],
    startDate: '2024-06-04', estimatedEnd: '2024-06-24', progress: 88,
    operator: 'Pedro Qualidade', operatorAvatar: 'PQ'
  },
];

export function getTenantById(id: string): Tenant | undefined {
  return TENANTS.find(t => t.id === id);
}

export function getClientById(id: string): Client | undefined {
  return CLIENTS.find(c => c.id === id);
}

export function getProductById(id: string): Product | undefined {
  return PRODUCTS.find(p => p.id === id);
}

export function getOrderById(id: string): Order | undefined {
  return ORDERS.find(o => o.id === id);
}

export function getTaskById(id: string): Task | undefined {
  return TASKS.find(t => t.id === id);
}

export function getProductionOrderById(id: string): ProductionOrder | undefined {
  return PRODUCTION_ORDERS.find(po => po.id === id);
}

export function getOrdersByStatus(status: Order['status']): Order[] {
  return ORDERS.filter(o => o.status === status);
}

export function getTasksByStatus(status: Task['status']): Task[] {
  return TASKS.filter(t => t.status === status);
}

export function getProductionOrdersByStatus(status: ProductionOrder['status']): ProductionOrder[] {
  return PRODUCTION_ORDERS.filter(po => po.status === status);
}
