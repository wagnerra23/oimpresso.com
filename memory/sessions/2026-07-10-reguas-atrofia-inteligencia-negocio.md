# Sessão 2026-07-10 — Grade de réguas → adversário → atrofia da inteligência de negócio (ADR 0334)

**Worktree:** `ecstatic-taussig-e2a4df` (stale −5001; commits partiram de `reguas-base-fresh` @ origin/main). Off-cycle.

## O que foi feito (narrativa)

Wagner: **"rode a grade de réguas"**. Rodei o workflow canônico `reguas-do-sistema` (41 agentes: dossiê → 6 pesquisadores → refutador → verificação → grade). Resultado bruto: "0 de 26 acima". Wagner cortou: **"foi perdido meus diferenciais que me destacavam. reanalise"**.

**A reanálise achou o furo do método:** o refutador julga **slice-a-slice** (contexto zero) e comete falácia de composição — acha um peer de categoria pra cada peça e declara o todo refutado. Mas o diferencial nunca foi a peça; é a **montagem recursiva** dentro de um ERP vertical multi-tenant em prod, aplicado a si mesmo. Recuperei os diferenciais na altitude certa (Artifact rev.2) e provei que a grade tinha se auto-desatualizado (re-listou como "gap" o que já shipou).

**Wagner: "merge e pode fazer"** → consertei o método em código (não só prosa):
- **#4074** — Fase `Integração` no workflow (veredito `DIFERENCIAL_SISTEMA` vs `REFUTADO_TB`) + 7ª regra dura na skill + entrada §5 (falácia de composição). Merged.

**Wagner: "eu tenho dois módulos, 1 a IA e outro o ERP… desconfiado que perdi minha inteligência de negócio, pode colocar um adversário".** SpawneI `adversario-inteligencia-negocio` (8 agentes: 5 forenses paralelos → promotor → defesa → juiz), moldura das 3 camadas (A ERP · B Jana-BI · C IA-OS). **Veredito: SIM.** Não é atrofia do músculo (vertical dispara com cliente pagante — Martinho biz=164) — é atrofia do **nervo**: `client_signal`=0 (14 meses pós-ADR 0105), nenhum cycle ativo, priorização dirigida por régua/regulação, não por sinal de cliente. Memória = cofre cheio (wr-comercial 415 arquivos) mas lacrado (0 commits desde 09/jun) e desconectado. O juiz refutou a rebatida da defesa (os "293 docs de negócio" eram artefato do commit de restauração; authoring real = 3 SPEC, 0 domínio).

**Wagner: "bom documentar o sistema para que isso não aconteça novamente"** →
- **#4077** — **ADR 0334** (modelo 3-camadas + invariante duro: C serve A+B, nunca cresce enquanto A/B atrofia sem sinal) + alarme executável `negocio-vs-governanca-ratio.mjs` (mede fluxo negócio÷governança; trend real mai 38%→jun 64%→jul 78%, dispara agora; self-test 15/15) + **3º eixo `SERVIR-O-NEGÓCIO`** na grade. Rejeitei o teto rígido (governança é CAPEX + já se auto-poda 0271/0314; o problema é o fluxo, não o estoque). Merged.
- **#4078** — ratifica 0334 (`aceito`). Merged.

**Wagner: "e tem que atualizar as réguas do projeto"** → nomeei a ironia (adicionar governança pra consertar excesso de governança) e separei: a dimensão nova na grade (feita, #4077) vs wiring do alarme como workflow CI (deixei como decisão de custo dele — não empilhei gate).

**Wagner: "tudo feito, pode reavaliar"** → re-rodei a grade (77 agentes) com as correções. **Auto-validou:** placar 0 acima-de-categoria + 8/8 à-frente-por-integração (sem "0 falso"); creditou 11 gaps já shipados (a pesquisa leu retrato de 8 dias); o eixo novo deu nota 2🔴 ao `client_signal`; gerou 5 lápides §5 sozinha.
- **#4080** — fix `args.base` (o tool serializa args pra string; bugou 2×, agentes se auto-curavam) + corrigi minha nota errada na skill. Merged.

**Wagner: "sim eu quero, e ainda estou incomodado com a perda de inteligência"** → validei que o incômodo está certo (a sessão inteira foi camada C — mais governança), e que a cura é produto (A/B) + sinal dele. Criei **CYCLE-BI-01** (goal canon Jana-BI) + **US-COPI-132** (descongelar Jana-BI, recall 0.38→0.60). Wagner esclareceu: a Jana-BI **está só em teste, não colocou pra Larissa usar** — o nervo nunca disparou porque o produto nunca chegou nela. Ordem correta: confiabilidade → mão dela → sinal.

## Números / provas

- 4 PRs merged (#4074, #4077, #4078, #4080) + 1 ratificação. 0 falhas de CI (2 corrigidas em voo: índice ADR + Check L proposto-citado-por-código).
- 3 workflows: grade (41 ag) + adversário (8 ag) + re-grade (77 ag) = ~13,4M tokens.
- Alarme `negocio-vs-governanca-ratio`: jul **78%** governança (dispara).

## Meta-lição da sessão

Loop completo do IA OS num arco só: **MEDIR** (grade) achou o furo → **CORRIGIR** (adversário + ADR 0334) → **TRAVAR** (Fase Integração + eixo novo + alarme) → **RE-MEDIR** (validou). E a ironia central, honesta: consertar a grade foi mais camada C; a inteligência de negócio (A/B) só começa a voltar com US-COPI-132 numa sessão limpa.
