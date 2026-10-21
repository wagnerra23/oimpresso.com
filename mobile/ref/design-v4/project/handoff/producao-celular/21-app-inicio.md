---
sessao: "21"
titulo: App das lojas: alertas de produção no Início
dono: "[CL]"
forma: Mobile Producao.dc.html · L1
---
# 21 · App das lojas: alertas de produção no Início

PR 1 (ERP): `GET /api/producao/resumo` — atrasadas, paradas por arte, máquinas acima do turno, notas ≤ 2 — cada item com link. PR 2 (`oimpresso-app`): bloco "Produção agora" em `src/telas/Inicio.tsx`, só aparece com módulo Produção ativo; sem alerta, o bloco some.

**Casos e teste:** UC-PCV-50 — `ProducaoResumoApiContratoTest.php`. Um `it` por UC, tenant 98×99, discriminante e controle negativo no recibo.
