---
sessao: "E05"
titulo: Etiqueta configurável
dono: "[CL]"
forma: Mobile Producao.dc.html · E1, E2
---
# E05 · Etiqueta configurável

Emenda da thread 05.
- Configuração por empresa: `etiqueta_modelo` = `termica_100x50_etapa` | `a4_os`.
- E1: uma etiqueta por etapa, QR → etapa. E2: faixa A4 por OS, QR → tela "escolha sua etapa" com as etapas do usuário em destaque.
- Preto sobre branco, QR com margem branca ≥ 4 módulos, data de entrega em caixa.
- Assistência (Repair): só acrescentar o QR na etiqueta existente, sem mexer no controller.

**Casos e teste:** UC-PCV-37 — `ProducaoApontamentoContratoTest.php`. Um `it` por UC, tenant 98×99, discriminante e controle negativo no recibo.
