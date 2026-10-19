---
sessao: "E03"
titulo: Cadastros: motivos e custos
dono: "[CL]"
forma: Mobile Producao.dc.html · A4, A5, I7
---
# E03 · Cadastros: motivos e custos

Emenda da thread 03.
- Motivos de pausa e de refugo por posto (lista fechada, com padrão sugerido: troca de bobina, setup, falta de material, manutenção, dúvida na arte, intervalo / defeito de mídia, cor, cabeça, corte, arte).
- Custo-hora da pessoa no colaborador (só visível com `producao.desempenho.ver`).
- Cadastro de veículo (placa, custo/h, custo/km) e de ferramenta (código QR).

**Casos e teste:** UC-PCV-33 — `ProducaoRoteirosContratoTest.php`. Um `it` por UC, tenant 98×99, discriminante e controle negativo no recibo.
