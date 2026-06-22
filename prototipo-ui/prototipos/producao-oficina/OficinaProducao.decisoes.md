---
register: Produção / Kanban da Oficina · window.OficinaPage
irmao_charter: OficinaProducao.charter.md
tecnica: Decision Register (ADR 0293 D-B · anéis Avaliar/Testar/Adotar/Descartar)
owner: wagner
last_update: 2026-06-22
schema: ADR-0293-D-B
related_adr: 0293-governanca-decisao-design-responsavel-registro-veredito
pii: false
---

# Decision Register — Produção / Kanban da Oficina

> **Materialização do par charter↔decisoes que o `integrity-check` IT2 exige** (ADR 0293 D-B).
> Este Register usa o schema mínimo `D-NN` da ADR 0293 (responsável · detecção · padrão · opções · status).
> O debate detalhado item-a-item (anéis Radar, D-01…D-08, notas funcionais do painel [W]) vive no
> arquivo irmão histórico [`decisoes.md`](./decisoes.md) (técnica Decision Register / Radar, criado 2026-06-02).
> Aqui ficam as decisões **no schema canônico ADR 0293** — quem decidiu, o que disparou, qual padrão se aplica.

## D-01 · Arrastar para avançar, com o gate como guarda
- responsável: [W]
- detecção: review de interação 2026-06-02 ("ideia melhor de interação") — protótipo entregue, aguarda veredito visual [W]
- padrão: Cockpit V2 (ADR 0110) · StageGate como guarda do avanço
- opções: (a) clicar card → drawer → StageGate (lento); (b) arrastar para a coluna seguinte, gate valida no drop
- status: PENDENTE [W] (nota funcional 9/10 no painel [W]; falta veredito de screenshot — gate visual ADR 0107)

## D-02 · Botão "→ próxima etapa" no próprio card
- responsável: [W]
- detecção: achado #2 do teste funcional D-01 (HTML5 drag falha em touch/tablet)
- padrão: uma máquina, duas portas — botões do card e arrasto chamam o MESMO `tryAdvance` gate-guardado
- opções: (a) só arrasto (quebra no tablet); (b) botão no card compartilhando o avanço gate-guardado
- status: PENDENTE [W] (implementado dentro do D-01 na 2ª passada; gradua junto)

## D-03 · Cor / identidade / dark / tokens (Tier-0)
- responsável: [W] (Tier-0 · ADR 0293 D-A regra-mestre · ADR 0094 princípio 7)
- detecção: padrão preventivo — qualquer paleta por-tela (`--ofc-*` bespoke) é barrada pelo `ds-guard` (L-02)
- padrão: token canônico do DS (ADR 0235) · dark por `[data-theme="dark"]` sem paleta por-tela (ADR 0281)
- opções: n/a — decisão Tier-0 sempre [W]; [CC] nunca cunha cor por-tela sozinho
- status: DECIDIDO (sem bespoke — segue token canônico; veredito automático do gate registra desvio se ocorrer)

## D-04 · Capacidade / SLA / KPI clicável / persistência / atalhos / foto (backlog do anel)
- responsável: [W]
- detecção: inventário ⬜/💡 do charter — itens D-03…D-08 do Register histórico, ainda em 🔍 AVALIAR
- padrão: Cockpit V2 (ADR 0110) · ler o detalhe no Register histórico antes de promover
- opções: ver [`decisoes.md`](./decisoes.md) D-03…D-08 (capacidade por coluna · borda SLA · KPI filtro · persistir foco · atalhos teclado · foto real)
- status: PENDENTE [W] (anel AVALIAR — sem protótipo decidido)

---

## Graduados (saíram daqui → viraram ✅ no charter)
- _(nenhum ainda — primeiro ciclo)_

## Devolvidos ([Design/Cowork] · ledger governance/design-requests/)
- _(nenhum ainda)_

## Trilha do tempo
- 2026-06-22 · [CC] materializou o par `OficinaProducao.decisoes.md` no schema ADR 0293 D-B (o IT2 deixa de passar no vácuo). Decisões inferidas do Register histórico `decisoes.md` + charter — nenhuma fabricada.
- 2026-06-02 · Register histórico `decisoes.md` criado (anéis Radar, D-01…D-08).
