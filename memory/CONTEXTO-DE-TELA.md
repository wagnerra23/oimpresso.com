# CONTEXTO-DE-TELA — Ficha de Contexto de Tela (intake F0 → F1)

> **Status:** proposta de [CC] · soberania [W] (numera/versiona/linka no PROTOCOL = [W] via [CL]).
> **O que é:** o contrato de contextualização **antes de qualquer pixel**. Generaliza o "Método Migration→Tela" (fila Tier 0 #1) num checklist de duas pontas.
> **Por que existe:** tela mal-contextualizada vira tela errada (ex.: `Sells/Create` regrediu pra POS por falta deste gate — venda virou "cupom de balcão"). A regra abaixo torna isso impossível de repetir.
> **Quem segue o quê:** **Lado A** = o briefador ([W]/[CL]) preenche. **Lado B+C+Gate** = [CC] executa e não pode pular.

---

## Princípio

> **Não desenho tela sem contexto; não invento o que o domínio já define.**
> O domínio (schema + FSM + fiscal + caso real) **manda** no layout — eu posiciono o que existe, não crio do zero. Charter é o contrato; código de produção é a verdade; memória impede repetir erro.

---

## LADO A — Brief mínimo (o briefador preenche · 6 campos)

> Cole isto no `COWORK_NOTES.md` (local do Cowork). **Sem estes 6, [CC] não inicia F1 — pede o que falta.** Não precisa ser longo; precisa existir.

```
### Tela: <nome + rota real, ex.: Sells/Create · /sells/create>   ### Módulo dono: <ex.: Sells / Oficina Auto>
### Prioridade: <P0–P3>   ### Persona primária: <Larissa balcão 1280 / Eliana fiscal / Técnico tablet / Wagner 1440>
1. INTENÇÃO (1 frase): o que a tela resolve, pra quem.
2. DELTA: o que muda vs hoje (e por que agora).
3. RESTRIÇÃO dura: fiscal/legal/multi-tenant/device que NÃO pode quebrar.
4. NÃO-OBJETIVO: o que essa tela explicitamente NÃO é (ex.: "não é POS").
5. VARIAÇÕES: quantas e em quê (visual / fluxo / copy / nenhuma).
6. FONTE: link/upload/print do alvo, se houver.
```

Regra de ouro do brief: **o campo 4 (não-objetivo) é o que mais protege a tela.** "Sells não é POS" teria evitado a regressão inteira.

---

## LADO B — Pesquisa obrigatória [CC] (Método Migration→Tela · ordem fixa)

> Antes de propor layout, [CC] **lê e cita** cada fonte que existir. Fonte ausente = anotar "não existe" (não inventar). Rankeio por peso no dossiê (Lado C).

1. **Charter da tela** (`<Tela>.charter.md` no git) — missão, **Non-Goals**, seções, anti-patterns. *Não existe → criar o charter É o primeiro passo (Gate).*
2. **Código de produção** (`resources/js/Pages/<…>.tsx`) — `useForm` shape, **nomes de campo reais**, handlers, atalhos. A verdade do que existe.
3. **Schema / migration** (`app/<Model>.php`, migrations) — colunas, FKs, enums. **Migration→Tela**: posiciono o campo que o banco tem; se falta, proponho estender — nunca duplico modelo paralelo (L-21).
4. **FSM / process aplicável** (ADR 0129/0143, tabelas `sale_process*`) — qual trilha a entidade segue; o que muda por vertical/contact-type.
5. **Modelo fiscal** (`Modules/<X>`, validações BR) — como o documento é montado; gates ("sem CNPJ não emite").
6. **Caso prático do domínio** (`memory/requisitos/<Mod>/CASO-PRATICO-*.md`) — o fluxo real do piloto, ponta a ponta.
7. **Review + débitos** (`<Tela>.review.md`, nota mecanizada) — o que já se sabe que está torto.
8. **ADRs de fundo** — Cockpit V2 (0110), multi-tenant (0093), vertical (0121), e os do módulo. Citar, não reler inteiro.
9. **Protótipo Cowork atual + tokens** (`<modulo>-page.jsx`, `BRIEFING §4`) — o que NÃO mexer; paleta/radius/foco canônicos.
10. **Memória de erros** (`memory/proibicoes.md` + `LICOES_CC.md`) — não repetir L-ID registrado.

---

## LADO C — Vocalizar o Sistema (saída obrigatória antes do pixel)

[CC] grava **um dossiê** (`memory/sessions/AAAA-MM-DD-contexto-<tela>.md`) com:
- **Veredito 1 linha** — o que a tela é, em foco.
- **Índice rankeado de fontes** — cada arquivo lido com peso **P0/P1/P2** e *por que importa*.
- **Anatomia** — entidade, FSM/process, seções (visíveis × condicionais), fiscal, o gap real.
- **Decisões de domínio pendentes** — o que o git NÃO responde, marcado `[assumido — confirmar [W]]`.
- **Próximo passo** — charter → desenho.

Só depois desse dossiê (aprovado/ajustado por [W]) eu desenho. Decisão nova → ADR (proposta; [CL] numera). Erro novo → `LICOES_CC.md`.

---

## GATE — travas que [CC] não ultrapassa

- ❌ **Sem charter, não desenho** → escrevo/proponho o charter primeiro (cristaliza Non-Goals).
- ❌ **Não invento campo** que o schema não tem → estendo o real e marco como proposta de migration.
- ❌ **Não invento paleta/radius/foco/process** → uso tokens (`BRIEFING §4`) e FSM existente.
- ❌ **Não duplico modelo** que já existe no repo (L-21) → convergir, não paralelizar.
- ❌ **Não forko tela por variação** → Tweak/seção condicional no mesmo componente.
- ✅ **Uma venda, N jornadas:** seções acendem por `process`/vertical; não há "tela por vertical".

---

## Aplicação imediata (prova viva)

Esta ficha nasceu da sessão `Sells` 2026-06-01: dossiê `memory/sessions/2026-06-01-contexto-venda-dossie-git.md` é exatamente um Lado-C bem-feito. A regressão venda→POS é exatamente o que o Gate impede. A próxima tela (`Vendas.charter.md` + Create reposicionado) será o **primeiro uso formal** desta ficha.
