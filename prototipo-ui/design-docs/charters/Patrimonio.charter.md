# Patrimônio (AssetManagement) — Charter de tela

> Destino no SSOT: `resources/js/Pages/AssetManagement/Patrimonio.charter.md`.
> Produzido no Cowork por [CC] (F1). **Não commitado** — ponte pro `main` via cola zero-toque, `cowork-inbox/` ou Issue `cowork-intake`.
> Build correspondente: `patrimonio-page.jsx` · `patrimonio-forms.jsx` · `patrimonio-data.jsx` · `patrimonio-page.css` (rota `assets` do shell `oimpresso.com.html`).

## 1. Para que serve

Saber **o que a empresa tem, quanto vale, quem está com o quê e o que está parado**. O patrimônio de uma gráfica é concentrado: duas máquinas de impressão respondem por metade do valor, e a parada de uma delas para a produção. A tela existe pra que a decisão de manutenção, garantia e substituição não dependa de memória.

Não serve pra: controle de estoque de insumo (é Compras/Produtos), nem para manutenção veicular operacional (é Oficina Auto — o patrimônio só aponta pra lá pela placa).

## 2. Quem usa

| Persona | O que faz aqui | Papel no módulo |
| --- | --- | --- |
| **Wagner** (dono) | Cadastra bem, decide manutenção, revoga alocação, mexe em prefixo e notificação | `gestor` — todas as permissões, todos os locais |
| **Larissa** (balcão) | Aloca notebook/monitor, envia ferramenta pra manutenção | `operador` — sem excluir, sem revogar, só Matriz e Loja Centro, só manutenção própria |
| **Eliana** (financeiro) | Confere valor de aquisição, depreciação e custo de manutenção | `financeiro` — leitura + export, sem alocar, sem configurar |
| **Marcos** (técnico) | Recebe e devolve ferramenta, em pé, no tablet | usa `operador` com alvo de toque ≥44px |

## 3. Vocabulário (canon `Resources/lang/pt/lang.php`)

**bem/ativo** (nunca "item") · **alocação** e **revogação** (nunca "empréstimo"/"devolução") · **é atribuível?** (não "alocável") · **custo adicional** (o gasto da manutenção) · **período de garantia em meses** · **código do ativo** (PAT-), **código de alocação** (ALO-), **código de revogação** (REV-).

## 4. Estrutura da tela

Padrão de módulo (`modulo-padrao.jsx`): header com contexto + reapuração → abas de área → corpo.

1. **Painel** — resumo do dia, 4 KPIs (bruto, residual, alocados, garantia crítica), 3 análises com drill (por categoria, situação da garantia, manutenção em aberto), "o que fazer primeiro".
2. **Bens** — sub-abas (Todos / Alocáveis / Garantia crítica / Em manutenção), filtros (categoria, local, tipo de compra, por-página), export CSV + imprimir, visibilidade de colunas, tabela densa, paginação. Linha abre o **drawer PT-02**.
3. **Alocações** — ativas / revogadas / todas, com ação Revogar.
4. **Manutenções** — 3 cards de leitura + tabela; concluir gera título no Financeiro.
5. **Garantias** — atalho declarado: não existe tela própria no módulo real; leva à lista filtrada.
6. **Auditoria** — trilha append-only do `LogsActivity`.
7. **Configurações** — prefixos e notificações (só `gestor`).

**Drawer do bem:** Resumo (identificação, placa quando veículo, descrição, "onde este bem aparece") · Garantia · Alocações (saldo livre) · Manutenção · Depreciação · Histórico auditado.

## 5. Regras que a tela precisa respeitar

- **R1 — alocação só de bem atribuível com saldo.** Espelha `Asset::forDropdown`: `quantidade − alocada + revogada > 0`. Bem não atribuível não entra na lista.
- **R2 — revogar devolve ao saldo** e gera código REV- próprio, sem apagar a alocação original.
- **R3 — garantia é janela.** `asset_warranties.start_date/end_date` comparada com hoje: na garantia / vence em ≤30 dias / vencida. **Sem registro ≠ vencida** — é "sem garantia".
- **R4 — locais permitidos filtram tudo.** `permitted_locations` do usuário corta lista, contadores e KPIs; o que ele não vê não entra na conta.
- **R5 — manutenção própria vs. todas.** `asset.view_all_maintenance` × `asset.view_own_maintenance`.
- **R6 — auditoria só da whitelist.** `name, asset_code, business_id, category_id, location_id, quantity, is_allocatable, purchase_date, purchase_amount, created_by`. **`description` não é auditada** (pode conter PII — LGPD).
- **R7 — excluir bloqueado com alocação ativa.** Revogue antes.
- **R8 — custo de manutenção vira título a pagar** no fechamento, não na abertura.

## 6. Estados

`dados` · `carregando` (skeleton) · `vazio` (primeira vez — convida a cadastrar) · `erro` (explica que nada se perdeu e oferece reapurar) · **sem permissão** (Configurações para não-gestor).

## 7. Acessibilidade (F3.5)

Foco preso no drawer e devolvido ao gatilho; menu de ações com ↑↓/Home/End/Enter/Esc; erro de campo ligado por `aria-describedby` + `aria-invalid`; toasts em `role="status" aria-live="polite"`; `aria-selected` nas abas do drawer; `:focus-visible` visível em tudo; alvo ≥44px no modo tablet.

## 8. Fora de escopo — precisa de decisão [W]

- **Regra de depreciação.** Hoje: linear, prazo do cadastro (padrão 10 anos máquina / 5 informática e veículo). Não há ADR — não usar como número contábil.
- **Etiqueta/QR do bem** e **termo de responsabilidade** assinável na alocação: não existem no módulo real.
- **Multi-empresa** (`business_id`) e `InstallController` (instalar/desinstalar).

## 9. Débito conhecido

Dados são mock coerente com ROTA LIVRE/OFFICEIMPRESSO, não leitura de banco. Upload de imagem é simulado (liga/desliga `media`). Export PDF/Excel ainda não existe — só CSV e imprimir.
