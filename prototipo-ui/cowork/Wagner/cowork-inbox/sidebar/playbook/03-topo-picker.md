---
sessao: "03"
titulo: Seção TOPO — paridade do CompanyPicker e o slot de alerta que o protótipo não tem
dono: "[CC]"
base: af09f7c3a0fd
prefixo: prototipo-ui/cowork/sidebar.jsx (só o bloco do topo) · prototipo-ui/cowork/data.jsx
nao_toca: app.jsx · styles.css · Components/cockpit/**
depende: 01 (mesmo arquivo — Lei 1: vaga 2, nunca em paralelo) · RESÍDUO-4
---
# 03 · Seção TOPO (`.sb-top`)

## A · Identidade — ancoragem dupla
- **alvo:** `sidebar.jsx` — `CompanyPicker` (l.42) e `CompanyPickerRail` (l.390), dentro de `.sb-top` (l.561-565).
- **âncora (código):** `AppShellV2.tsx` monta o topo assim, nesta ordem:
  ```
  <div className="sb-top"><CompanyPicker businesses={...} fallbackNome={...} /></div>
  <NfeCertBadge />                 ← alerta de certificado NF-e
  <nav className="sb-body" …>
  ```
  `Components/cockpit/NfeCertBadge.tsx:25` documenta a posição: *"após CompanyPicker, antes do SidebarMenu"*. O badge só renderiza em estado crítico (vencendo/vencido), via shared prop `shell.nfe_cert_status`; silencioso quando OK ou quando o business não emite NF-e.
- O protótipo **não tem esse slot**. É a única divergência estrutural do topo encontrada nesta sha.

## B · O que fazer
1. **Medir primeiro, decidir depois** (medir e aplicar são passos separados): diff `CompanyPicker` protótipo × vivo **nos dois sentidos** — props (`businesses`/`fallbackNome` × `company`/`onChange`), markup, classes, comportamento do dropdown (fechar por clique-fora, `esc`, foco), e a variante rail. Registrar a tabela no `_saida-03.md` antes de tocar em qualquer linha.
2. **Slot de alerta** — depende de RESÍDUO-4:
   - se [W] disser "real": representar no build um `AlertaCertificado` que só aparece em estado crítico, alimentado por `data.jsx` (mock com os 3 estados: ok/vencendo/vencido) — **usando o `Alert` do DS**, tom `warn`/`danger`, nunca pastel sólido;
   - se disser "placeholder": um slot vazio nomeado no JSX com comentário apontando pro `NfeCertBadge.tsx`, e ponto.
3. Divergência que **não** se resolve puxando: o protótipo simula empresa por `window.__company`; o vivo recebe `businesses` do Inertia. Isso é instrumento — declarar no `_saida`, não "corrigir".

## C · Não inventar
- Sem token novo · sidebar preta nos dois modos · sem cor crua · PT-BR sem emoji.
- Não trocar o `CompanyPicker` do protótipo por outro componente do DS sem [W]: ele é o alvo medido de outras telas.

## Execução
```
ARQUIVOS A EDITAR : prototipo-ui/cowork/sidebar.jsx (bloco .sb-top) · prototipo-ui/cowork/data.jsx (estado do certificado, se RESÍDUO-4 = real)
PASSO A PASSO     : 1) diff nos dois sentidos → tabela no _saida  2) aplicar só o que a tabela justificar
                    3) slot conforme RESÍDUO-4  4) conferir que o rail não ganha o alerta (não cabe em 56px — decidir e dizer)
PARAR SE          : (a) 01 não estiver fechada (mesmo arquivo)  (b) RESÍDUO-4 sem resposta → entregar só a tabela do diff
```

## Prova
- `_saida-03.md` com o diff bidirecional do `CompanyPicker` (linha a linha, valores medidos) e o veredito de cada divergência: puxar · declarar · ignorar.
- Se slot aplicado: os 3 estados visíveis por tweak, e o comportamento silencioso em "ok" demonstrado.
