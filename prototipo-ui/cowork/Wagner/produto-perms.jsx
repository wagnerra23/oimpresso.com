// produto-perms.jsx — permissões do módulo Produto com os nomes REAIS do legado.
// Lido no `main` NESTE turno (tree 4235f8df6838):
//   UnitController ....... unit.view · unit.create · unit.update · unit.delete
//   BrandController ...... brand.view · brand.create · brand.update · brand.delete
//   TaxonomyController ... category.view · category.create · category.update · category.delete (só quando category_type == 'product')
//   BarcodeController .... barcode_settings.access (configuração da etiqueta, não a impressão)
// 🔴 SEM GATE NENHUM no legado (achado A-P1): WarrantyController, VariationTemplateController,
//   SellingPriceGroupController, LabelsController, ImportProductsController e
//   ImportOpeningStockController — as rotas (routes/web.php 613-615, 711-712, 729-730, 748-753, 838)
//   só passam pelo middleware de autenticação. Qualquer usuário logado com acesso ao menu
//   cria, edita e exclui. A tela marca isso onde acontece em vez de esconder.
// Expõe window.ProdutoPerms.
(() => {
const TODAS = [
  "unit.view", "unit.create", "unit.update", "unit.delete",
  "brand.view", "brand.create", "brand.update", "brand.delete",
  "category.view", "category.create", "category.update", "category.delete",
  "barcode_settings.access",
];

// Papéis simulados do protótipo — combinações que o balcão realmente usa.
const PAPEIS = {
  "administrador": { label: "Administrador", perms: TODAS },
  "gerente": { label: "Gerente de catálogo", perms: TODAS.filter((p) => !p.endsWith(".delete")) },
  "balcao": { label: "Balcão (só ver)", perms: ["unit.view", "brand.view", "category.view"] },
  "sem-acesso": { label: "Sem acesso ao cadastro", perms: [] },
};

// Cadastros sem gate no legado: a UI não inventa permissão, só sinaliza.
const SEM_GATE = {
  variacoes: "VariationTemplateController",
  grupos: "SellingPriceGroupController",
  garantias: "WarrantyController",
  etiquetas: "LabelsController",
  importacao: "ImportProductsController / ImportOpeningStockController",
};

const criar = (papel) => {
  const lista = (PAPEIS[papel] || PAPEIS.administrador).perms;
  const set = new Set(lista);
  return {
    papel,
    label: (PAPEIS[papel] || PAPEIS.administrador).label,
    // can("unit.create") — nomes iguais aos do blade/controller, sem tradução.
    can: (p) => p == null ? true : set.has(p),
    // Cadastro sem gate no legado → liberado, mas nomeado.
    semGate: (chave) => SEM_GATE[chave] || null,
  };
};

window.ProdutoPerms = { TODAS, PAPEIS, SEM_GATE, criar };
})();
