---
sessao: "04"
titulo: Locais · Impressoras · Código de barras → Inertia — saída
playbook: sistema
thread: "04"
dono: "[CL]"
data: "2026-10-07"
base: wagnerra23/oimpresso.com@main 03fcdc18b3 (lido 2026-10-07; #8975 e #8976 ainda abertos)
---

# _saida-04 · Locais · Impressoras · Código de barras → Inertia

Uma tela por vez, pelo MWART (ADR 0104): F1 (RUNBOOK + paridade) → F2 (Pest baseline da Blade) → F3 (Page atrás de
flag). **F4 (smoke biz=1 com a flag ligada) e F5 (cutover) não foram feitas**: F4 depende de ligar a flag em produção
para biz=1, que espera o OK do [W]; F5 é decisão [W]. Com as flags desligadas (default), produção segue nas Blades.

## 1 · Feito

| Tela | Rota | Flag (default OFF) | F1+F2 | F3-1 (lista) | F3-2 (drawer + abas) |
|---|---|---|---|---|---|
| Impressoras | `/printers` | `useV2ConfiguracoesImpressoras` | #8920 | #8947 | #8966 |
| Código de barras | `/barcodes` | `useV2ConfiguracoesCodigoBarras` | #8930 | #8964 | #8975 |
| Locais comerciais | `/business-location` | `useV2ConfiguracoesLocais` | #8931 | #8965 | #8976 |

- Pages em `resources/js/Pages/Configuracoes/<Aba>/Index.tsx`, cada uma com charter + casos + teste de contrato
  (`tests/Feature/Configuracoes/*ContratoTest.php`). Baselines da Blade em `*BaselineTest.php`.
- **D1 ([W] 2026-10-06, "Configurações com abas")**: `Pages/Configuracoes/_shared/ConfiguracoesSubNav.tsx` deriva as
  abas dos filhos do dropdown de configurações do `shell.menu`, já filtrados por permissão — sem lista própria. Cada
  aba mantém a própria URL e permissão.
- `tests/Feature/Configuracoes/` entrou na lane `acessos-pest`, com os 3 controllers no gatilho.
- `memory/requisitos/Configuracoes/`: BRIEFING + RUNBOOK e paridade das 3 telas.
- **Tier 0 corrigido (#8924):** `BarcodeController::update/destroy/setDefault` alcançavam, pelo id, a etiqueta de
  outro negócio e os modelos globais (`business_id NULL`). Vermelho antes, verde depois, no CT 100.

## 2 · Não feito e por quê
- **F4 e F5** (acima).
- **Medidas de etiqueta em mm**, como o protótipo pede: o banco e a impressão são em polegada. Converter muda o que é
  gravado e impresso — **decisão [W]**. A tela segue em polegada.
- "Testar" impressora e "Imprimir prova" de etiqueta: **não existe endpoint** no legado.
- **Produtos em destaque** do local: o drawer preserva (o `update()` apagaria se não viessem) mas não edita; a escolha
  segue na Blade.
- "Configurações de recibo" do local: é outra tela (`location_settings`), fora do prefixo.
- Ordenação explícita das listas, exportar seleção: sem fonte/endpoint.

## 3 · Achados para o índice (não editei o índice — é do Cowork)
1. **Menu Configurações e `access_printers`:** a condição externa do dropdown no `AdminSidebarMenu` não lista
   `access_printers`. Quem tem só essa permissão não vê o grupo inteiro (nem na Blade, nem nas abas). Medido no
   UC-IMPR-05. Fora do prefixo da 04 — sugere thread própria.
2. `PrinterController::edit` e `BarcodeController::edit` com id de outro negócio usam `find()` (→ `null`) e a Blade
   quebra; as telas novas não passam por ali. Some no cutover.
3. `BusinessLocationController::update` com id de outro negócio não altera nada mas responde `success: true`.
4. `business_locations.zip_code` é `char(7)`: CEP com hífen não cabe.
5. A thread 05 (Esquemas de fatura · Impostos · Tipos de serviço) já tem o caminho pronto: mesma pasta de Pages, mesmo
   `ConfiguracoesSubNav`, mesma lane, mesmo BRIEFING (adicionar as linhas).

## 4 · Provas
- `Inertia::render(` nos 3 controllers (provas do índice): no `main` desde #8947 / #8964 / #8965.
- Pest no CT 100 (cópia isolada, sem tocar o checkout compartilhado): `tests/Feature/Configuracoes/` 35 passed;
  mordida provada por mutante em cada defesa de isolamento e no ida e volta de Locais (UC-LOCAL-04).
- Lane `acessos-pest` conferida por nome de teste pelo gerente da fila em cada PR.

## 5 · Placar
entregue 3 de 3 telas até F3 · ausentes F4/F5 por decisão [W] (flag em produção / cutover) · mm por decisão [W].
