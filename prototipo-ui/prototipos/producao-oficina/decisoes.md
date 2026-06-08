---
register: Produção / Kanban da Oficina · window.OficinaPage
irmao_charter: OficinaProducao.charter.md
tecnica: Decision Register (anéis estilo Technology Radar — Avaliar/Testar/Adotar/Descartar)
owner: wagner
last_update: 2026-06-02
---

# Decision Register — Produção / Kanban da Oficina

> **O chão de debate da tela.** Aqui vivem as opções que ainda estão sendo discutidas, testadas e formando, com o tempo, como o sistema deve se comportar. O **charter** guarda só o que já fechou; este arquivo guarda o que está **em movimento**.
>
> **Ciclo de vida (anéis):**
> - 🔍 **AVALIAR** — ideia levantada, ainda não testada. Só discussão.
> - 🧪 **TESTAR** — [CC] protótipou; [W] está experimentando/decidindo.
> - ✅ **ADOTAR** — [W] aprovou → **grada pro charter** como ✅ e sai daqui.
> - ⛔ **DESCARTAR** — reprovado → vira anti-pattern no charter (memória de "não repetir").
>
> **Como [W] usa:** mexe no campo `estado:` de cada item, ou escreve em `nota [W]:`. [CC] lê isto no início de todo chat de Oficina e age conforme o anel.

---

## D-01 · Arrastar para avançar, com o gate como guarda
- **estado:** 🧪 TESTAR (protótipo entregue 2026-06-02 · aguarda veredito [W])
- **prioridade:** alta (é a "ideia melhor de interação" 2026-06-02)
- **contexto:** hoje avançar etapa = clicar card → abrir drawer → usar StageGate. Lento pro caminho feliz.
- **opção proposta:** arrastar o card pra próxima coluna = intenção de avançar; no *drop*, o StageGate valida. Gate ok → avança sem abrir nada. Gate falha → card volta e o drawer abre já no checklist do que falta.
- **build:** `oficina-page.jsx` (drag state + `dnd` + `gateOf`/`ctxFor` + toast) · `oficina-page.css` (`.ofc-drop-ok/no/over`, `.ofc-dragging`, `.ofc-toast`). Só ativo no **foco=Etapa**. **Drawer travado intacto** — só é aberto quando o gate barra.
- **TESTE FUNCIONAL (painel do usuário, 2026-06-02):**
  - ✅ render: 5 colunas, **12 cards arrastáveis** (`draggable=true`), `evalGate` ligado.
  - ✅ roteamento: cada etapa → `gate.next` correto (recepção→diagnóstico→peças→execução→pronto).
  - ✅ **caminho bloqueado** (default): OS 8804 com gate 3/4 → soltar bloqueia + abre drawer no checklist.
  - ✅ **caminho feliz**: completei o check manual → gate 4/4 `ready:true` → soltar avança peças→execução. As duas ramificações batem com `onColDrop` (`done===total`).
  - ✅ sem erro de console; drawer travado não foi tocado.
- **nota da 1ª passada:** **8/10** (funciona nas duas ramificações, render limpo, zero regressão) — segura os 2 pontos pelos achados abaixo.
- **REFINO 2ª passada (2026-06-02):**
  - **Uma máquina, duas portas:** extraí `tryAdvance(os)` — arrasto E os botões do card ("Triagem →", "Iniciar →", "Entregar →", que antes não faziam nada) chamam o MESMO avanço gate-guardado. Resolve o achado #2 (touch) e funde o **D-02**.
  - **Feedback preditivo:** ao arrastar, a coluna-alvo já mostra o desfecho — verde "solte p/ avançar" se o gate está pronto, âmbar "faltam N · abre checklist" se não. Acaba o bounce-surpresa (toque Linear/Stripe).
  - achado #1 (etapa terminal) mantido como decisão: "Entregar" é botão, não coluna.
  - **nota 2ª passada: 9/10** — falta só o veredito visual do [W] (meu iframe não roda o host; validei via DOM+lógica no painel do [W]).
- **achados (viram refino antes de ✅ ADOTAR):**
  1. **Etapa terminal:** "Pronto" tem `gate.next="entregue"`, mas **não existe coluna "Entregue"** → cards de Pronto não têm pra onde arrastar. O botão "Entregar →" no card já cobre isso — decisão: terminal é botão, não arrasto. Documentar (não é bug).
  2. **Touch (mecânico no tablet):** HTML5 drag é ruim em touch. **D-02 deixa de ser opcional** → o mesmo avanço gate-guardado precisa de um botão no card. Os botões "Triagem →/Iniciar →" já existem no card — devem **compartilhar a função de avanço** do D-01.
  3. **Verificação:** meu iframe não roda o host gigante (Babel trava); testei via eval+screenshot no painel do [W]. Não é defeito do D-01, é limite de ambiente.
- **nota [W]:** _(vazio — seu veredito: ✅ adotar / continuar 🧪 / ⛔)_

## D-02 · Botão "→ próxima etapa" no próprio card
- **estado:** 🧪 TESTAR — **implementado dentro do D-01** (2ª passada, 2026-06-02): os botões do card chamam `tryAdvance`, a mesma porta do arrasto. Gradua junto com o D-01.
- **contexto:** obrigatório pra touch (mecânico no tablet, onde arrasto falha).
- **nota [W]:** _(vazio — veredito junto com o D-01)_

## D-03 · Capacidade visível em todas as colunas
- **estado:** 🔍 AVALIAR
- **contexto:** hoje só "Em execução" mostra X/5 boxes. Estender a todas (ex.: diagnóstico = X/2 elevadores)?
- **dúvida:** Recepção e Pronto não têm capacidade física — faz sentido só onde há recurso (box/elevador)?
- **nota [W]:** _(vazio)_

## D-04 · Borda do card por SLA (verde/âmbar/vermelho)
- **estado:** 🔍 AVALIAR
- **contexto:** hoje urgência é booleano (tira vermelha). Trocar por gradiente de prazo?
- **opção:** borda/realce muda conforme proximidade do prazo, não só on/off.
- **risco:** ruído visual; pode brigar com a calma "Shopmonkey". Testar no modo Pressão.
- **nota [W]:** _(vazio)_

## D-05 · KPI clicável filtra o quadro
- **estado:** 🔍 AVALIAR
- **contexto:** os 6 KPIs são só leitura. Clicar "Urgentes" filtraria o kanban?
- **opção:** KPI vira filtro de 1 clique (toggle), com o card destacando que está filtrado.
- **nota [W]:** _(vazio)_

## D-06 · Persistir visão/foco escolhido
- **estado:** 🔍 AVALIAR
- **contexto:** ao voltar pra tela, volta no default (Etapa/Kanban). Lembrar a última escolha?
- **onde:** localStorage no protótipo; preferência de usuário no real.
- **nota [W]:** _(vazio)_

## D-07 · Atalhos de teclado (N / barra / setas)
- **estado:** 🔍 AVALIAR
- **contexto:** Larissa é teclado-first. `N` nova OS · `/` foca busca · setas navegam cards · Enter abre.
- **nota [W]:** _(vazio)_

## D-08 · Foto real de entrada no card
- **estado:** 🔍 AVALIAR
- **contexto:** card mostra tag textual ("frente", "OBD"). Trocar por thumbnail real da foto de check-in?
- **risco:** densidade/peso a 1280px; talvez só no modo "Detalhe".
- **nota [W]:** _(vazio)_

---

## Graduados (saíram daqui → viraram ✅ no charter)
- _(nenhum ainda — primeiro ciclo)_

## Descartados (viraram anti-pattern no charter)
- _(nenhum ainda)_

## Trilha do tempo
- 2026-06-02 · [CC] criou o Register (técnica Decision Register / anéis Radar). Semeado com D-01…D-08 a partir do inventário ⬜/💡 do charter. Drawer e itens ✅ ficam no charter.
