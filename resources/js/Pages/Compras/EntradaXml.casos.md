---
casos: Compras/EntradaXml — carimbado do Padrão de Tela
irmaos: EntradaXml.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Compras/EntradaXml

> Nascido de `criar-tela.mjs`. **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão. Os casos são provados por `EntradaXmlTelaContratoTest.php`; o stub `e2e/compras-entrada-xml.spec.ts` segue pendente.

---

## UC-ENTRAD-01 · Sem a flag a tela não existe; sem a permissão, 403
- **Fonte:** RUNBOOK-entrada-xml §1 · ADR entrada por XML (feature nova atrás de flag).
- **Aceite:** Dado a flag desligada · Quando abre a tela · Então 404. Dado usuário sem `compras.import_xml` · Então 403.
- **Teste:** `Modules/Compras/Tests/Feature/EntradaXmlTelaContratoTest.php`
- **Status: 🧪**

---

## UC-ENTRAD-02 · Vejo só as notas da minha empresa e confiro os itens
- **Fonte:** ADR D2/D3 · protótipo TrEntradas (UC-TRB-18).
- **Aceite:** Dado notas da empresa e de outra · Quando abro a tela · Então só as minhas aparecem, e a conferência mostra CFOP de entrada proposto (5405 → 1403) e "ST retida" no CST 060.
- **Teste:** `Modules/Compras/Tests/Feature/EntradaXmlTelaContratoTest.php`
- **Status: 🧪**

---

## UC-ENTRAD-03 · XML de outro CNPJ é recusado com o motivo
- **Fonte:** ADR D2 (Tier 0, ADR 0093).
- **Aceite:** Dado um XML cujo destinatário não é a empresa · Quando envio · Então volta "Esta nota foi emitida para outro CNPJ, não para esta empresa." e nada é gravado.
- **Teste:** `Modules/Compras/Tests/Feature/EntradaXmlTelaContratoTest.php`
- **Status: 🧪**

---

## UC-ENTRAD-04 · Item sem produto não vira compra
- **Fonte:** ADR D3 · US-COM-003.
- **Aceite:** Dado uma nota com item sem vínculo · Quando clico Gerar compra · Então volta o motivo e nenhuma compra é criada.
- **Teste:** `Modules/Compras/Tests/Feature/EntradaXmlTelaContratoTest.php`
- **Status: 🧪**

---

## UC-ENTRAD-05 · A busca para vincular só mostra produtos da minha empresa
- **Fonte:** Tier 0 (ADR 0093).
- **Aceite:** Dado produtos de mesmo nome em duas empresas · Quando busco · Então só o meu aparece.
- **Teste:** `Modules/Compras/Tests/Feature/EntradaXmlTelaContratoTest.php`
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Chego na tela pela aba "Entrada por XML" do cabeçalho de Compras (flag ligada + `compras.import_xml`); prova = smoke com screenshot.
- **[BACKLOG]** Aviso de NCM divergente entre o XML e o produto (o produto não guarda NCM hoje).
- **[BACKLOG]** Criar fornecedor e produto na hora, quando não existem (US-COM-003, R-COM-202).
- **[BACKLOG]** Gerar compra pela tela, conta conferida pelos dois caminhos do ImportarDfeComoCompraContratoTest.

## Trilha do tempo
- 2026-10-27 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste). Refs: UI-0013 · ADR 0264 G-1/G-2.
