// Router raiz — combina os routers de domínio. Um por módulo.
import { router } from "./trpc";
import { pedidosRouter } from "../routers/pedidos";
import { orcamentoRouter } from "../routers/orcamento";
import { estoqueRouter } from "../routers/estoque";
import { producaoRouter } from "../routers/producao";
import { financeiroRouter } from "../routers/financeiro";
import { fiscalRouter } from "../routers/fiscal";

export const appRouter = router({
  pedidos: pedidosRouter,
  orcamento: orcamentoRouter,   // Fase 1 — o fosso
  estoque: estoqueRouter,       // Fase 1 — estoque dimensional + custeio
  producao: producaoRouter,     // Fase 1 — fecha o laço: produzir baixa o estoque
  financeiro: financeiroRouter, // Fase 2 — títulos ligados à OS por id
  fiscal: fiscalRouter,         // Fase 2 — NF-e/NFS-e via provider + ISS×ICMS
});

export type AppRouter = typeof appRouter;
