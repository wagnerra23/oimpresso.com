# HRM (Essentials · RH) — ponte do módulo · reescrita 2026-09-05

> **Este arquivo virou ponteiro.** O export de 04/09 que morava aqui já vive no `main` em `prototipo-ui/design-docs/cowork-inbox/hrm/EXPORT-HRM-2026-09-04.md`; manter cópia local é cache que envelhece (L-42). Ele também errou ao afirmar que não havia `PEDIDO-*hrm*` — o `cowork-inbox/hrm/PEDIDO-CL-hrm.md` existia uma pasta abaixo e hoje carrega as decisões [W] D1/D2/D3 (2026-09-05).
> **Dono do módulo agora:** o playbook `cowork-inbox/hrm/playbook/00-INDICE.md` (`SINCRONIZAR Hrm`), que absorve export + pedido + emenda [W] em 11 threads de sessão limpa, com placar da lista. Destino no `main`: `prototipo-ui/design-docs/cowork-inbox/hrm/playbook/`.

## O que mudou desde o export de 04/09 (lido no `main`, sha `159e572dd448`)
- **D1** Presença sai do HRM (Ponto dono da jornada) → ondas 4–5 do export **mortas** (thread 09).
- **D2** Folha completa com encargos → projeto com ADR própria → ondas 7–8 **bloqueadas** (thread 10).
- **D3** Licença aprovada bloqueia marcação → guard nasce no Ponto.
- Mergeados e reusáveis: #6778 lang PT · #6797 validação + `HrmLicencaTest` · #6799 faixas · #6789 `leave-type destroy` 422 · #6798 import.
- Achado novo: `nav_hrm` tem **Departamentos/Cargos** e o protótipo não (thread 01).
- **Metas (`/hrm/sales-target`) já está em produção** — #6869, `Inertia::render('Essentials/Metas')`, pacote completo (tsx · charter · casos · contrato · Pest · e2e · RUNBOOK · lane). A onda 9 do export está feita; a rev.1 do playbook mandava reconstruí-la (faltava o denominador de runtime). Thread 04 virou PUXAR.

## RESÍDUO HRM — fila [W] (preservada e atualizada)
1. ~~Ordem das 9 ondas~~ → superada: ordem/vagas em `00-INDICE.md §2`.
2. ~~Validação de licença no servidor~~ → **feita** (#6797).
3. `DataTablePro` do DS (3º módulo com `th` sem `scope`/semântica) — pedido de DS próprio? **Segue aberta.**
4. **Novas:** Departamentos/Cargos como abas · `ShiftController::destroy` responde 200? · Metas no protótipo mostra apuração excluída por VALOR — ver `00-INDICE.md §6`. (`<PAGES>` **não é decisão**: a árvore usa `resources/js/Pages/Essentials/`, 14 `.tsx`.)
