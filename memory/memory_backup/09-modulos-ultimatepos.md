# 09 — Inventário de Módulos UltimatePOS (instância WR2 / oimpresso.com)

> Lista viva dos módulos ativos no `Modules/` do cliente. Use isto para achar exemplos de padrão (`DataController`, `routes`, ServiceProvider, etc.) sem precisar abrir a instalação cliente toda vez.

Capturado em 2026-04-19 a partir do painel **Manage Modules** e da pasta `Modules/`.

---

## Módulos instalados (status "Uninstall" no painel = INSTALADO)

| # | Módulo        | Versão | Descrição (painel)                                                            | Label na sidebar          | Ordem menu |
|---|---------------|--------|-------------------------------------------------------------------------------|---------------------------|-----------:|
| 1 | Boleto        | 1.0    | Usado para imprimir Boletos do Brasil                                         | —                         | —          |
| 2 | Chat          | 0.7    | Chat de atendimento One Chanel WhatsApp, Telegram, Email                      | Chat                      | —          |
| 3 | Connector     | 0.7    | Provide the API's for POS                                                     | API                       | —          |
| 4 | Crm           | 1.0    | Crm Module                                                                    | Crm                       | —          |
| 5 | Help          | 0.7    | Ajuda, Documentação, Vídeos e Treinamentos                                    | Ajuda                     | —          |
| 6 | Jana          | 0.7    | Jana IA Assistente                                                            | IA                        | 89         |
| 7 | Manufacturing | 2.1    | Fábrica — Usado por empresas produzem de produtos - Composição                | Fabricação                | 21         |
| 8 | Officeimpresso| 0.7    | Sistema Offline do Office Impresso                                            | Office Impresso           | —          |
| 9 | **PontoWr2**  | 0.1    | Módulo de Ponto Eletrônico Portaria MTP 671/2021 — WR2 Sistemas               | **Ponto WR2**             | **25**     |
| 10| ProductCatalogue | 0.8 | Catalogue & Menu module                                                       | Catalogue QR              | —          |
| 11| Project       | 1.6    | Módulo de Produção                                                            | Projeto                   | 86         |
| 12| Repair        | 1.1    | Useful for all kind of repair shops                                           | Reparar                   | 24         |
| 13| Superadmin    | 2.2    | Allows you to create packages & sell subscription to multiple businesses      | Superadmin (top)          | —          |
| 14| Woocommerce   | 3.0    | Allows you to connect POS with WooCommerce website                            | Woocommerce               | —          |

## Módulos disponíveis mas NÃO instalados

| Módulo | Versão | Descrição                                |
|--------|--------|------------------------------------------|
| BI     | —      | BI Busines Inteligence                   |
| Fiscal | —      | Usado para imprimir NFe, NFCe            |

## Módulo "comercial" externo

- **Essentials Module** — "Essentials features for every growing businesses" — ação **Buy** (licenciamento separado; HRM faz parte dele).

## Outros módulos presentes na pasta `Modules/` mas não listados no painel

- `Producao` — pasta presente, verificar se é legado ou substituído por `Project`.

---

## Referências canônicas (por similaridade ao PontoWr2)

Ao precisar de exemplo de padrão, **olhe nesta ordem** antes de inventar:

1. **`Modules/Jana`** — padrão "dropdown com sub-itens" no menu. Mesmo estilo que o PontoWr2 usa.
2. **`Modules/Repair`** — padrão "item único", mas `DataController` com muitos hooks (`after_sale_saved`, `user_permissions`, `get_pos_screen_view`, `addTaxonomies`, etc.). Boa fonte de inspiração para hooks adicionais.
3. **`Modules/Project`** — padrão "item único" + integração com vendas/transactions. Útil para ver como cruzar módulo com o core de finanças.

---

## Padrão de sidebar (o que todo módulo precisa ter para aparecer no menu)

Descoberto via análise dos DataControllers (sessão 03 — 2026-04-19):

1. Arquivo `Modules/<Nome>/Http/Controllers/DataController.php` com:
   - Método `modifyAdminMenu()` — usa `Menu::modify('admin-sidebar-menu', ...)`
   - (Opcional) `superadmin_package()` — registra o pacote no painel Superadmin
   - (Opcional) `user_permissions()` — registra as permissões no cadastro de Roles
2. Rota deve ter o middleware `AdminSidebarMenu` (o core itera módulos instalados e chama seus `modifyAdminMenu` a cada request).
3. O `Menu::modify` funciona em TODA a aplicação — basta estar em qualquer tela com sidebar que o item aparece.
4. A convenção de "nome do pacote" é `<modulo_minusculo>_module` (ex.: `ponto_module`, `jana_module`, `repair_module`). Esse nome é usado em `hasThePermissionInSubscription($business_id, 'ponto_module', 'superadmin_package')`.

Ver ADR 0012 (a ser criada) para registro formal desta convenção se ficar necessária.

---

**Última atualização:** 2026-04-19 (sessão 03 — captura inicial após Eliana replicar módulos para o repo local)
