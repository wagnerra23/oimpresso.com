---
id: requisitos-repair-repair-settings-gap
tela: Repair/Settings/Index (/repair/repair-settings)
prototipo: prototipo-ui/cowork/repair-page.jsx
tela_viva: resources/js/Pages/Repair/Settings/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Repair/Settings/Index

> Fase 1 do protocolo (`prototipo-ui/PROTOCOL.md`) em modo **PARIDADE**. `repair-page.jsx` é porte REVERSO dos blades (cabeçalho l.1-2; §5 2026-08-28); a aba comparada é `Configurações` (repair-page.jsx:368-425, origem `settings/`). ⚠️ O charter desta tela declara `related_prototype: n/a` **e** explica que `repair-page.jsx` é porte reverso do Blade que ela substitui ("ancorar aqui seria ancorar a tela nela mesma"); diferente das 6 irmãs, não declara `bundle_source`. Este gap **não ancora o design** da tela no mockup — mede só paridade de cobertura, o único uso legítimo de um porte reverso; o `.map.json` correspondente carrega a mesma nota. Estado no vivo medido em 2026-09-06 sobre `origin/main` `80bc4ef8b9`, com `grep -n`. Lidos antes: `Index.charter.md` (Goals l.31-34, Non-Goals l.40-45, Anti-hooks l.51-56) e `Index.casos.md` (UC-RSET-01..08, 8 pass). Dado mock do protótipo não é gap.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header | `PageHeader` "Configurações do Repair" (Index.tsx:187-190) | Nada — paridade. O mockup usa só os `<h3>` de seção dentro do shell (repair-page.jsx:378, 387, 397, 407). |
| Folha de OS (padrões) | Prefixo, status padrão, produto padrão (leitura), etiqueta e tipo de código de barras, 4 textos longos e checklist padrão (Index.tsx:200-321; UC-RSET-01) | Nada — vivo à frente. O mockup tem só prefixo／status padrão／produto padrão, todos `readOnly` (repair-page.jsx:380-384). |
| Campos personalizados | 5 inputs editáveis, cada um lendo a própria chave (Index.tsx:324-344) | Nada — paridade. O mockup tem os 5, `readOnly` (repair-page.jsx:399-401). |
| Impressão (chaves `show_*` e rótulos) | 17 switches em 5 grupos + 3 rótulos editáveis + largura／altura da etiqueta, em form próprio do 2º endpoint (Index.tsx:357-442; UC-RSET-06) | Nada — vivo à frente. O mockup tem só os switches por chave com rótulo (repair-page.jsx:389-393). |
| Aviso "Notificação ao cliente é por status" | Sem o aviso; a capacidade que ele descreve mora em `/repair/status`, para onde a tela linka (Index.tsx:450-452) | Nada — paridade. O `Alert` do mockup (repair-page.jsx:403-405) é texto explicativo, não capacidade; o vivo linka a tela dona. |
| Permissões deste papel | Nenhum painel de permissões; a UI só reflete o gate do Controller (Index.charter.md:43; UC-RSET-04) | Nada — fora do charter. O painel do mockup (repair-page.jsx:407-419; `PERMISSOES`／`PAPEIS` em repair-data.jsx:137-161) é o simulador de papel do protótipo; permissões por papel se gerenciam em `/roles/{id}/edit` (Camada 3, proibicoes.md). |
| Salvar | 2 botões, um por endpoint, com a nota de permissão (Index.tsx:346-353, 437-441) | Nada — paridade com o botão + nota do mockup (repair-page.jsx:421-422); os dois botões são o contrato dos dois endpoints (Anti-hook l.51). |
| Atalhos para telas próprias | Seção com links "Status de OS" e "Modelos de dispositivo" (Index.tsx:445-457) | Nada — paridade com as abas do shell do mockup (repair-page.jsx:709-718); é Goal do charter (l.34) e Non-Goal de duplicar (l.41). |
