# Roteiro vídeo demo — 3 min — versão NFe-de-boleto-pago (cold email A)

> Vídeo assíncrono Loom/OBS pra prospect que respondeu cold email A com "3 clientes que mais atrasam".
> Wagner grava em casa, ~3 takes, anexa link Loom no reply em até 24h.
> Tom: Wagner-style — direto, sem "revolucionário", PT-BR brasileiro.

---

## Setup pré-gravação (10min — 1× por dia de gravação)

- [ ] Browser **Chrome incognito** (sem cookies vazando outro cliente)
- [ ] Banco demo populado em `business_id=99` stub (NÃO biz=4 ROTA LIVRE — Tier 0)
  - 8 clientes fake (nomes plausíveis: "Visual Master ME", "Plotter SC LTDA", "Sinaliza Brasil EIRELI", etc — NÃO PII real)
  - ~30 vendas últimos 90 dias
  - 5 OS abertas em colunas variadas (Repair Kanban)
  - 3 vendas com boleto pago + NFe autorizada já no histórico (timeline visível)
- [ ] User logado: `demo@oimpresso.com` (senha cofre)
- [ ] Janela Chrome em **1280×720** (gráfica típica) ou **1920×1080** (mais nítido pro vídeo)
  - **Recomendado: 1920×1080** — Loom comprime; 1280 fica pixelizado no fullscreen do prospect
- [ ] 3 abas pré-abertas em ordem de uso:
  1. `/copiloto/chat` (Jana)
  2. `/sells/{id}` venda 217 paga (timeline NFe)
  3. `/repair` (Kanban)
- [ ] **Som silenciado:** Slack, WhatsApp, Discord, e-mail desktop, system sounds
- [ ] **Webcam:** fundo limpo, luz frontal, sem espelho atrás
- [ ] Áudio testado (5s sample no Loom — ouvir antes de gravar de verdade)
- [ ] Hotkey de start/stop do Loom/OBS confirmado (Ctrl+Shift+L padrão Loom)
- [ ] **Card de pagamento NÃO compartilhar** (cliente não pediu pricing ainda)

## Personalização pré-gravação (30s por prospect)

Substituir 4 placeholders com regex find/replace antes de gravar:

| Placeholder | Exemplo |
|---|---|
| `{Nome}` | "Marcos" |
| `{Empresa}` | "Plotter Express" |
| `{Cidade}` | "Curitiba" |
| `{DorEspecífica}` | "clientes atrasando 30+ dias e perdeu venda recorrente" — pega do reply do prospect |

---

## Cena 1 — Abertura (0:00–0:15) — câmera Wagner

**Visual:** Wagner em câmera (rosto visível), fundo limpo, sem tela compartilhada ainda.

**Frase exata (ler — não improvisar):**

> "Oi {Nome}, aqui é Wagner do oimpresso. Você me mandou aqueles 3 clientes que mais atrasam — me chamou atenção {DorEspecífica}. Vou te mostrar em 2 minutos, com dados de demo, como o sistema trata isso. Compartilhando tela agora."

**Ações:** ao terminar a frase, **fecha câmera (PiP off)** e ativa share screen.

**Trapas a evitar:**
- ❌ Falar mais de 15s — perde timing das 3 wow-moments
- ❌ Dizer "essa solução revolucionária" / "única no mercado" — Wagner-style proíbe
- ❌ Compartilhar tela ANTES de terminar a frase de abertura — corta o "humano" do vídeo
- ❌ Esquecer de fechar PiP (rosto sobreposto na tela atrapalha leitura)

---

## Cena 2 — Wow #1: NFe automática a partir de boleto pago (0:15–1:00) — 45s

> **MOVIDO PRA #1 porque é a promessa do cold email A.** O prospect veio por isso. Entregar primeiro.

**Visual:** Tela compartilhada, aba `/sells/217` (venda demo paga, timeline visível).

**Ações na tela (ordem cronológica):**

1. (0:15) Aponta cursor pro topo da venda — campo "Status: Pago via Asaas"
2. (0:22) Scroll até timeline de eventos da venda. Mostra 4 linhas com timestamp:
   - `14:32:00 — Boleto Asaas pago (webhook recebido)`
   - `14:32:08 — NFC-e enviada SEFAZ`
   - `14:32:14 — Autorizada (cstat 100)`
   - `14:32:18 — DANFE enviada por e-mail pro cliente`
3. (0:35) Clica no link "DANFE.pdf" — abre o PDF em nova aba (já gerado)
4. (0:45) Volta pra aba da venda

**Narração (Wagner voiceover, sem aparecer):**

> "Olha o timestamp. Boleto caiu 14:32:00. NFe autorizada 14 segundos depois. E-mail com DANFE saiu 18 segundos depois. **Ninguém clicou em nada.** Hoje, sua equipe ou contador faz isso de manhã, atrasa 3 a 5 horas, às vezes esquece. Aqui é o webhook do Asaas disparando o pipeline NFe direto na SEFAZ."

**Trapas a evitar:**
- ❌ NÃO tentar emitir NFe ao vivo — SEFAZ homologação é lenta/instável, mata o vídeo
- ❌ Mostrar venda real biz=4 — Tier 0 violation, PII de cliente RotaLivre
- ❌ Mencionar "flag .env" ou "auto_emission_enabled" — detalhe técnico interno
- ❌ Demorar mais de 45s — wow #2 e #3 ficam apertados

---

## Cena 3 — Wow #2: Jana respondendo dor real (1:00–1:45) — 45s

**Visual:** Troca pra aba `/copiloto/chat` (rota Jana).

**Ações na tela:**

1. (1:00) Click na aba Jana, chat vazio
2. (1:05) Digita pergunta no chat (Wagner digita ao vivo, não cola): 
   > `Quais clientes atrasaram mais de 30 dias últimos 90 dias?`
3. (1:12) Resposta da Jana aparece (~3-5s) — lista top 5 com nome+valor+dias atrasados
4. (1:25) Segunda pergunta:
   > `Mostra a última OS da Visual Master que atrasou e por quê`
5. (1:35) Resposta: OS-DEMO-105, 4 dias parada em "acabamento", responsável "João" — link clicável

**Narração:**

> "Isso aqui é Jana, IA com memória do seu negócio. Pesquisa que hoje você faz manual em planilha mais WhatsApp do encarregado — aqui é uma frase. Ela buscou nos seus dados, não inventou. Tudo o que aparece tem link clicável pra venda, OS, cliente."

**Trapas a evitar:**
- ❌ Pergunta que Jana erra — testar 2x ANTES de gravar com a base biz=99
- ❌ Resposta demora mais de 8s — se acontecer no take, recortar e refazer
- ❌ Aparecer dado feio na resposta (cliente "TESTE TESTE", valor R$ 0,01) — auditar seed antes
- ❌ Falar "agente IA autônomo" — vende mal, soa hype

---

## Cena 4 — Wow #3: Repair Kanban drag-drop ao vivo (1:45–2:30) — 45s

> **WOW #3 trocado de "bulk update Jana" pra "Repair Kanban drag-drop"** — bulk update via chat NÃO está confirmado em prod, risco alto de wow falhar. Drag-drop entre colunas FOI entregue (PR #363 — US-REPAIR-PROD-4 mergeado 2026-05-09). Risco zero, wow alto.

**Visual:** Aba `/repair` (Kanban com 5 colunas, ~12 cards).

**Ações na tela:**

1. (1:45) Aponta as 5 colunas: "Aguardando", "Corte", "Impressão", "Acabamento", "Entrega"
2. (1:52) Mouse hover em card "OS-DEMO-105 — Visual Master — banner 3x2m" (vermelho, atrasada)
3. (2:00) Filtro por cor — clica no toggle "SLA estourado" — filtra pra 2 cards vermelhos
4. (2:08) Limpa filtro
5. (2:12) **Drag ao vivo:** arrasta OS-DEMO-105 da coluna "Impressão" pra "Acabamento" — animação suave, status atualiza, timestamp na coluna muda
6. (2:22) Aponta o badge novo "Movido agora 14:38"

**Narração:**

> "Ordem de serviço com Kanban. Drag-drop entre etapas — produção arrasta, dono enxerga em tempo real. Filtro vermelho mostra onde tá pegando fogo. Tudo isso multi-usuário: produção mexe no celular, dono vê no notebook, ninguém abre 3 sistemas."

**Trapas a evitar:**
- ❌ Drag falhar (bug de drop zone) — testar 3 cards antes de gravar
- ❌ Animação gaguejar (CPU/GPU travada) — fechar Slack/Spotify antes
- ❌ Mencionar "ADR" ou "MWART" — jargão interno

---

## Cena 5 — CTA fechamento (2:30–3:00) — 30s — câmera Wagner

**Visual:** Para de compartilhar tela. Wagner volta na câmera.

**Frase exata:**

> "Você viu 3 coisas em 2 minutos: NFe sozinha quando boleto cai, Jana respondendo o que tu pergunta no chat, e Kanban de produção drag-drop. Próximo passo — me responde esse e-mail com 1 horário entre quarta e sexta. Faço uma call de 25 minutos com SEUS dados, sem compromisso. Se topar depois, trial guiado de 30 dias sem cartão. Valeu, {Nome}, qualquer dúvida me chama no WhatsApp 11-9XXXX-XXXX."

**Ações:**
- Acena na câmera no "valeu"
- Para gravação (Ctrl+Shift+L)

**Trapas a evitar:**
- ❌ Esquecer de reabrir câmera — cliente acha que vídeo travou
- ❌ Falar "vou te mandar proposta" — empurra venda; deixa ele puxar
- ❌ Passar de 3:00 — Loom mostra dropoff em 3min consistentemente

---

## Plano B — features que NÃO entram no vídeo

| Feature | Por quê fora |
|---|---|
| **Bulk update via Jana ("aumenta 5% em papéis MMHL")** | NÃO confirmado em prod 2026-05-09. Wow legal mas risco de "Jana, não consegui executar essa ação ainda". Se Wagner quiser testar antes, ok promover pra wow #4 (versão B do vídeo) |
| **Auto-emissão NFe ao vivo** | SEFAZ homologação lenta/instável — mostrar timeline JÁ-gerada |
| **Officeimpresso (superadmin)** | Tela legacy 3.7→6.7, design feio |
| **PontoWr2** | Específico WR2, não cabe pra gráfica |
| **Visão Unificada Financeiro** | Cabe NA CALL de 25min, não no vídeo de 3min — economiza pro próximo passo |
| **Pricing/tiers** | Próximo passo — cliente puxa, não Wagner empurra |
| **MCP server / Constituição v2 / ADR** | Governança interna |
| **Vendas reais biz=4 ROTA LIVRE** | Tier 0 violation, PII real |
| **Charter / Pest / mwart-process** | Jargão interno, não vende |

---

## Variações temáticas — Wagner escolhe qual gravar 1º

### Versão A (DEFAULT — esta) — ângulo "NFe-de-boleto-pago"
- Wow #1: NFe automática (45s) — promessa do cold email
- Wow #2: Jana chat (45s)
- Wow #3: Repair Kanban (45s)
- Total: 3min
- **Pra prospect que respondeu cold email A**

### Versão B — ângulo "Jana IA — chat com seu negócio"
- **Diferença na abertura:** "Você me mandou uma pergunta que queria fazer pro ERP. Vou te mostrar Jana respondendo, e mais 2 coisas que normalmente vão junto."
- **Wow order:** Jana #1 (45s, com a pergunta DELE digitada ao vivo) → NFe #2 (45s) → Repair #3 (45s)
- **Pra prospect que respondeu cold email B**

### Versão C — ângulo "saia do Bling"
- **Diferença na abertura:** "Você me mandou que sua equipe gasta {N} horas/semana passando dado entre Bling, Asaas, planilha. Vou te mostrar como vira 1 sistema."
- **Wow order:** Visão Unificada Financeiro #1 (substitui Repair Kanban — mostra AR+AP+Asaas em 1 tela) → NFe #2 → Jana #3
- **Pra prospect que respondeu cold email C**
- **Risco:** Visão Unificada tem charter recente (PR #349) — confirmar smoke biz=99 antes

---

## Métrica de sucesso pós-vídeo

Track manualmente em planilha `memory/sales/2026-05/metricas-video-demo.tsv` (criar 1ª gravação):

| Métrica | Como medir | Meta inicial |
|---|---|---|
| Taxa de resposta | replies / vídeos enviados | ≥30% (vs 8% baseline cold email sem vídeo) |
| Tempo médio assistido | Loom analytics dashboard | ≥2min de 3min (66%+) |
| Drop-off por cena | Loom heatmap | Cena 5 (CTA) deve ter ≥80% retenção |
| Reply qualificada | Reply com horário marcado / total replies | ≥50% |
| Taxa "agendou call" | calls agendadas / vídeos enviados | ≥15% |

**Threshold de pivot:** se 10 vídeos versão A → <3 replies, testar versão B com próximos 10. Se A+B+C todos <30%, problema é cold email (não vídeo) — refazer skill `cold-email-rewrite`.

---

## Tempo de gravação estimado

| Etapa | Tempo |
|---|---|
| Setup pré-gravação (1× por dia) | 10min |
| Personalização placeholders | 30s |
| Take 1 (raw, identificar quirks) | 4min |
| Retake da cena que estragou | 3min × 2 médias = 6min |
| Edição mínima Loom (corte início/fim, sem efeito) | 3min |
| Upload + copy do link | 2min |
| **Total por vídeo personalizado** | **~25min** |

**Lote de 5 vídeos no mesmo dia:** ~1h45 (setup amortizado). Recomendação: gravar batch de 5 por sessão semanal pra manter promessa "24h" do cold email.

---

## Recomendação Wagner — qual versão gravar 1º

**Gravar versão A primeiro.** Razões:
1. Cold email A (NFe) é o mais alinhado com o diferencial concreto e checável (NfeBrasil US-NFE-002 fechada server-side, runbook smoke fiscal pronto pra biz=1)
2. NFe automática é mensurável — prospect testa fácil, vira reference customer
3. Versões B e C derivam de A com 80% do material reutilizado (só muda abertura + ordem dos wows)
4. Risco técnico mínimo: timeline já-gerada não depende de SEFAZ ao vivo, drag-drop entregue 2026-05-09, Jana chat estável em prod
