---
id: resources-js-pages-sells-createv3-casos
casos: Venda V3 (preview de design) · /sells/create-v3
irmaos: CreateV3.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: luiz
last_run: "2026-08-07"
---

# Casos de Uso & Aceite — Venda V3 (preview)

> **Tela de PREVIEW, não de produção.** Dono: **[L] Luiz**. A venda real continua em `/pos/create`
> (`Sells/Create.tsx`), operada pela ROTA LIVRE — e essa tela **não pode ser alterada**
> (restrição de negócio [L] 2026-08-06). Lei da tela: [`CreateV3.charter.md`](CreateV3.charter.md).
>
> **Status:** ✅ passa (com prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## Por que não há UC numerado ainda

Um `UC-*` só existe honestamente quando **≥1 teste o cita** — é o G-2 do `casos-gate`, que é **required**.
Criar UC agora, sem teste, faria o contrato nascer órfão e **bloquearia o merge de quem viesse atendê-lo**.

Então o que esta tela tem hoje é **prosa declarada**, no formato `[BACKLOG]` (sem id): visível pra
quem for escrever o teste, sem gate que ela não possa cumprir. Cada item vira `UC-V3xx` **no PR que
trouxer o teste que o cita** — não antes.

---

## Backlog de contrato

- **[BACKLOG]** A rota `/sells/create-v3` responde **403** a usuário autenticado sem `sell.create` e sem `superadmin` — mesma alçada da tela de venda real, o preview não afrouxa permissão.
- **[BACKLOG]** A rota responde **302** (login) a usuário não autenticado.
- **[BACKLOG]** A resposta Inertia renderiza o componente `Sells/CreateV3` e traz a prop `cena` com as três chaves `cliente`, `itens`, `fechamento`.
- **[BACKLOG]** A tela renderiza a **faixa de preview** — quem abre por engano precisa saber em 1 segundo que não é produção.
- **[BACKLOG]** O botão "Finalizar venda" renderiza **`disabled`**, e não existe rota de escrita associada a esta tela.
- **[BACKLOG]** Nenhum número exibido é calculado no front nem no controller: os valores de `fechamento` são strings já formatadas em pt-BR, vindas de `SellsV3Controller::dadosDeCena()`.

---

## Fronteira que os testes desta tela devem preservar

Não é caso de uso desta tela, mas é o contrato que a existência dela serve — e o teste que o provar
pertence a `Sells/Create`, não aqui:

- `/pos/create` continua servido por `SellPosController@create` renderizando `Sells/Create`, **sem alteração de comportamento**, com esta tela existindo ao lado.

---

## Pendências declaradas

- Testes: nenhum ainda. O RUNBOOK ([`RUNBOOK-create-v3.md`](../../../../memory/requisitos/Sells/RUNBOOK-create-v3.md) §F4) prevê smoke em staging; a lane Pest roda no CT 100, nunca local ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
- Tenant de teste é o fictício **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — `biz=4` é proibido em teste, fixture ou smoke.
