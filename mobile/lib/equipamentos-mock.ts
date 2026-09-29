/**
 * Mock de equipamentos / frota (Bloco 4).
 *
 * Equipamento ≠ Veículo: o primeiro pode ter `serie` + `horimetro` em vez de
 * placa + hodômetro. Ambos têm `owner_id` apontando pra uma pessoa (cliente).
 */

export type EquipKind = "veiculo" | "equipamento";

export type Equipamento = {
  id: string;
  kind: EquipKind;
  ownerId: string;
  // comuns
  marca: string;
  modelo: string;
  apelido?: string;
  ano: number;
  tipo: string;
  cor?: string;
  // veículo
  placa?: string;
  placa2?: string;
  chassi?: string;
  chassi2?: string;
  renavam?: string;
  hodometro?: number;
  // equipamento
  serie?: string;
  horimetro?: number;
};

export type Pessoa = {
  id: string;
  nome: string;
  doc: string;
  papeis: ("cliente" | "fornecedor" | "transportadora" | "funcionario")[];
};

export const PESSOAS_SEED: Pessoa[] = [
  { id: "PE-1", nome: "Transportes Lima Ltda", doc: "12.345.678/0001-90", papeis: ["cliente", "transportadora"] },
  { id: "PE-2", nome: "Distribuidora Sul SA", doc: "98.765.432/0001-12", papeis: ["cliente"] },
  { id: "PE-3", nome: "Rotas BR Express", doc: "55.444.333/0001-21", papeis: ["cliente", "transportadora"] },
  { id: "PE-4", nome: "Comercial Norte", doc: "11.222.333/0001-44", papeis: ["cliente"] },
  { id: "PE-5", nome: "André Silva", doc: "111.222.333-44", papeis: ["funcionario"] },
  { id: "PE-6", nome: "Bruno Cruz", doc: "222.333.444-55", papeis: ["funcionario"] },
];

export const EQUIPAMENTOS_SEED: Equipamento[] = [
  {
    id: "EQ-1",
    kind: "veiculo",
    ownerId: "PE-1",
    marca: "Mercedes-Benz",
    modelo: "Atego 1719",
    apelido: "Branca",
    ano: 2019,
    tipo: "Caminhão 3/4",
    cor: "Branco",
    placa: "ABC1D23",
    chassi: "9BM958134KB123456",
    renavam: "01234567890",
    hodometro: 184523,
  },
  {
    id: "EQ-2",
    kind: "veiculo",
    ownerId: "PE-1",
    marca: "Volkswagen",
    modelo: "Constellation 24.280",
    ano: 2021,
    tipo: "Caminhão",
    cor: "Cinza",
    placa: "DEF2G34",
    placa2: "GHI3J45",
    chassi: "9BWZZZ24MMK654321",
    chassi2: "REB987654321",
    renavam: "98765432100",
    hodometro: 92010,
  },
  {
    id: "EQ-3",
    kind: "veiculo",
    ownerId: "PE-2",
    marca: "Iveco",
    modelo: "Daily 70C17",
    ano: 2017,
    tipo: "VUC",
    cor: "Branco",
    placa: "JKL4M56",
    chassi: "93ZA1NFH08842221",
    renavam: "11223344556",
    hodometro: 245901,
  },
  {
    id: "EQ-4",
    kind: "veiculo",
    ownerId: "PE-3",
    marca: "Scania",
    modelo: "R 450",
    apelido: "Vermelhão",
    ano: 2022,
    tipo: "Cavalo mecânico",
    cor: "Vermelho",
    placa: "NOP5Q67",
    placa2: "QRS6T78",
    chassi: "9BSR4X20003111111",
    chassi2: "REB111222333",
    renavam: "55667788990",
    hodometro: 312455,
  },
  {
    id: "EQ-5",
    kind: "equipamento",
    ownerId: "PE-2",
    marca: "Atlas Copco",
    modelo: "XAS 138",
    apelido: "Compressor Pátio",
    ano: 2020,
    tipo: "Compressor",
    serie: "ACX-0202-138",
    horimetro: 4521,
  },
  {
    id: "EQ-6",
    kind: "equipamento",
    ownerId: "PE-4",
    marca: "Caterpillar",
    modelo: "C9.3 ACERT",
    apelido: "Gerador",
    ano: 2018,
    tipo: "Grupo gerador",
    serie: "CAT-93-0091",
    horimetro: 12380,
  },
];

export function getPessoa(id: string): Pessoa | undefined {
  return PESSOAS_SEED.find((p) => p.id === id);
}
