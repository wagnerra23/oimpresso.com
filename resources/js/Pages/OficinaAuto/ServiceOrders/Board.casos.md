---
casos: Quadro de OS da Oficina · /oficina-auto/ordens-servico/board
irmaos: Board.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-16"
---

# Casos de Uso & Aceite — Quadro de OS da Oficina

> **Re-ancorado no Board canônico (Onda Q2, 2026-06-11).** Nasceu como `ProducaoOficina/Index.casos.md`
> (handoff Cowork 2026-06-04); o workspace unificado (#2551) substituiu aquela tela pelo
> `ServiceOrders/Board.tsx` e `/producao-oficina` virou redirect permanente. Os UCs abaixo são o
> contrato VIVO do Board — cada `Status: ✅` é derivado do veredito real (manifesto G-7,
> `scripts/casos-test-results.json`), nunca declarado de cabeça.
>
> **Status:** ✅ passa (com prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou (regressão → vira lição).
>
> **Revalidado 2026-07-16** (`last_run` bumpado) — a tela migrou pros tokens semânticos do DS
> (14 `bg-white` → `bg-card` etc, PR #4367): mudança de **cor**, não de comportamento. O G-6/G-7
> acusou `stale`/`stale-results` corretamente (o `.tsx` ficou mais novo que o teste), então os UCs
> foram **re-provados**, não re-declarados: o E2E Playwright rodou contra este código no CI
> (`E2E Playwright · UCs críticos` verde) e o veredito real foi coletado do JUnit pro manifesto
> — **9/9 UCs do Board `pass`, `ran_at: 2026-07-16`**. Nenhum `last_run` bumpado no escuro.

---

## UC-01 · Ver o pátio num relance
- **Persona:** Larissa (balcão, 1280px), 8h da manhã.
- **Como usa:** abre Oficina e vê todas as OS organizadas por etapa, sem rolar, pra saber o que está pegando.
- **Aceite:** Dado a tela carregada · Quando foco=Etapa · Então **5 colunas** (Recepção → Diagnóstico → Aguardando peças → Em execução → Pronto p/ retirar), cada uma com a contagem de OS.
- **Teste:** `e2e/oficina-uc06-gate-etapa.spec.ts`
- **Status: ✅**

## UC-02 · Os números do dia
- **Persona:** Larissa / Wagner (governança).
- **Aceite:** Então **6 KPIs** com valor numérico (Recepção · Diagnóstico · Aguardando peças · Execução · Urgentes · Valor em curso).
- **Teste:** `e2e/oficina-uc06-gate-etapa.spec.ts`
- **Status: ✅**

## UC-03 · Achar uma OS na hora
- **Persona:** Larissa (cliente ligou citando a placa).
- **Aceite:** Quando digita na busca (placa/nome/#OS) · Então o quadro mostra só as OS que casam (busca server-side, sem quebrar as colunas).
- **Teste:** `e2e/oficina-uc06-gate-etapa.spec.ts`
- **Status: ✅**

## UC-04 · Reorganizar por box ou mecânico
- **Persona:** gestor de oficina.
- **Aceite:** Quando abre Visão · Então o controle **Foco das colunas** oferece Etapa/Box/Mecânico; Box/Mecânico ficam **desabilitados quando não há box/mecânico cadastrado** (estado honesto) e, com mecânicos, as colunas viram os mecânicos (+ "Sem mecânico").
- **Teste:** `e2e/oficina-uc06-gate-etapa.spec.ts` (prova o mecanismo + estado desabilitado; o pivot com mecânicos reais depende de seed com mecânico — fora do seed mínimo biz=1).
- **Status: 🧪** (prova parcial no CI; pivot populado validado em design 2026-06-02)

## UC-05 · Abrir a OS como documento vivo
- **Persona:** Larissa / mecânico (tablet).
- **Aceite:** Quando clica no card · Então abre o **drawer** rico (Fotos & Laudo, FSM, Linha do tempo). Fecha no backdrop/✕.
- **Teste:** `e2e/oficina-uc06-gate-etapa.spec.ts`
- **Status: ✅**

## UC-06 · Avançar de etapa sem furar a regra (D-01)
- **Persona:** mecânico.
- **Como usa:** arrasta o card pra outra coluna (ou usa o botão de ação da etapa); o gate opina **no drop**: transição não-listada/backward = toast "Transição não permitida" · OS fora do pipeline = toast "OS sem pipeline iniciado" · transição crítica = diálogo de confirmação.
- **Aceite:** Dado drop num alvo inválido · Então o gate dá veredito visível e o card NÃO avança.
- **Teste:** `e2e/oficina-uc06-gate-etapa.spec.ts`
- **Status: ✅**

## UC-07 · Cadastrar um carro que chegou
- **Persona:** Larissa (veículo no balcão).
- **Aceite:** Quando clica "Nova OS" no quadro · Então abre a criação de OS (`/oficina-auto/ordens-servico/create`) com o seletor de veículo; o caminho completo criar→card em Recepção é coberto pelo UC-11.
- **Teste:** `e2e/oficina-uc06-gate-etapa.spec.ts` (CTA) + `e2e/oficina-os-funcional-fluxo.spec.ts` (fluxo completo)
- **Status: ✅**

## UC-09 · Ritmo de balcão sem pensar em imposto
- **Persona:** Larissa (1280px, fila no balcão).
- **Aceite:** Dado viewport 1280px · Então a página **não tem scroll horizontal**; o quadro rola por dentro (chrome fixo, #2551).
- **Teste:** `e2e/oficina-uc06-gate-etapa.spec.ts`
- **Status: ✅**

## UC-11 · OS funcional fim-a-fim (caminho da Larissa)
- **Persona:** Larissa (balcão, 1280px) — o dia inteiro dela numa OS só.
- **Como usa:** cadastra o carro que chegou → cria a OS (cliente+veículo) → vistoria com semáforo DVI → foto no laudo → pede aprovação do cliente (WhatsApp) → avança a etapa pelo gate → imprime a folha A4.
- **Aceite:** Dado veículo+cliente cadastrados · Quando percorre criar OS → DVI (item com severidade) → foto enviada no laudo → "Pedir aprovação" → avançar etapa (checklist ou override de responsável, registrado) → "Imprimir OS" · Então cada passo dá feedback visível (card em Recepção, item na lista, foto no grid, "Aguardando aprovação", "Etapa avançada", iframe de impressão) **sem erro**.
- **Nota:** a aprovação do cliente em si é fora da tela (link público + PIN — `/aprovar-os/{token}`); o lado Larissa é o coberto aqui.
- **Teste:** `e2e/oficina-os-funcional-fluxo.spec.ts` (Playwright, harness G-3).
- **Status: ✅**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Estes dois ficam SEM token de UC
> de propósito até existir teste real — visíveis, não esquecidos, sem virar dívida no baseline.

- **[BACKLOG] Ver o que vira nota quando o carro fica pronto** — Dado stage=pronto + venda derivada · drawer mostra card Vendas×Oficina com Total · Peças(NF-e) · MO(NFS-e). Depende de venda derivada no harness (ex-caso 08, D-09).
- **[BACKLOG] Trabalhar do jeito que prefere (Pressão calmo/pico)** — Pressão ficou **FORA** do Board por decisão [W] (comentários no Board.tsx); contrato era do protótipo ProducaoOficina (ex-caso 10). Reentra se o modo voltar ao escopo.

## Como rodar a suíte
1. **E2E:** `npm run e2e:check` no harness do CI (e2e-gate, gate de PR desde Onda Q1) — vereditos viram manifesto via `npm run casos:results`.
2. **Cadência:** rodar ao fim de toda mexida na Oficina. UC que vira ❌ = regressão → lição + conserto antes de seguir.

## Trilha do tempo
- 2026-06-02 · [CC] criou a suíte (10 UCs) a partir do inventário aprovado do charter (design).
- 2026-06-04 · [CC] importado pro repo via handoff. Pointers `Index.*`; nota de mapeamento design→produção adicionada.
- 2026-06-10 · [CL] UC-11 adicionado (PACOTE QUALIDADE-9 PR-1 — caminho fim-a-fim da Larissa) + spec Playwright `oficina-os-funcional-fluxo.spec.ts`.
- 2026-06-11 · [CL] **Re-ancorado no Board canônico** (Onda Q2): movido de `ProducaoOficina/Index.casos.md` (tela substituída pelo workspace #2551, rota virou redirect). UC-04/05/07/09 ganharam teste e2e real; UC-06 re-contratado pro veredito no drop; ex-casos 08/10 → Backlog sem token (zero órfão novo); `Status: ✅` só com veredito `pass` no manifesto G-7.
