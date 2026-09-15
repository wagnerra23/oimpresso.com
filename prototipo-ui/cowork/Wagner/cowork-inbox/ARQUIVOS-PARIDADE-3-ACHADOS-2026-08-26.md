# Arquivos — 3 achados de paridade protótipo ↔ vivo (pedido pro Code)

**Origem:** conferência `arquivos-page.jsx` (Cowork) × `resources/js/Pages/Arquivos/Index.tsx` @ `main`, lida em 2026-08-26T17:38Z (tree `e9027ee7be7b`).
**Escopo:** só `Pages/Arquivos/Index.tsx`. Nenhuma mudança de payload, controller ou migration. As 4 vistas e a natureza leitura-pura ficam como estão.
**Por que:** nos 3 pontos abaixo o protótipo está certo e o vivo divergiu — vocabulário cru na tela e badge sólido. Objetivo declarado por [W]: as duas telas iguais.

---

## 1. Chips da Trilha mostram o enum cru

**Onde:** vista `trilha`, bloco `data-contract="trilha-filtros"`.
**Hoje:** o chip imprime `{f.acao}` — `signed_url_issued`, `soft_delete`, `hard_delete` — enquanto a **coluna Ação da mesma tabela, ao lado**, já traduz por `ACAO_PT`. Mesma tela, dois vocabulários; e o charter exige PT-BR em todo label.

**Pedido:** usar o mapa que já existe no arquivo, com o enum no `title` (mesmo tratamento de bucket/visibilidade/disco).

```diff
   {(trilha?.acoes ?? []).map((f) => (
     <button
       key={f.acao}
       type="button"
+      title={f.acao}
       onClick={() => irPara({ acao: f.acao })}
       className={chip(filtros.acao === f.acao)}
     >
-      {f.acao} <span className="tabular-nums opacity-70">{f.total}</span>
+      {ACAO_PT[f.acao] ?? f.acao} <span className="tabular-nums opacity-70">{f.total}</span>
     </button>
   ))}
```

O fallback `?? f.acao` é de propósito e é a regra já documentada no `ACAO_PT`: o dono do vocabulário é o enum da coluna, ação nova aparece crua em vez de sumir.

**Efeito colateral a tratar junto:** o `EmptyState` de trilha filtrada-vazia também interpola o valor cru —
`title={\`Nenhum evento de ${filtros.acao} no período.\`}` → `${ACAO_PT[filtros.acao] ?? filtros.acao}` (em minúscula na frase, ex.: "Nenhum evento de link assinado no período.").

---

## 2. Coluna "Contexto" da política imprime jargão de banco

**Onde:** vista `retencao`, tabela `data-contract="retencao-politica"`, `<td>` do contexto.
**Hoje:** `<code className="text-xs">{p.sub}</code>` — `nfe-xml`, `os-anexo`, `documentos-fiscais` na tela. É exatamente o que `ds/no-db-jargon-in-ui` barra, e é o mesmo tratamento que bucket, visibilidade, disco e "Escopo do job" já receberam neste arquivo (negócio na tela, técnico no `title`).
**Protótipo:** rótulo PT-BR em negrito + o `sub` em mono abaixo, como sub-linha.

**Pedido:** mapa PT-BR no arquivo (a `interface Politica` é `{sub, dias, lei}` — **não** mexer no payload) e a célula em duas linhas.

```tsx
/** Rótulo PT-BR do contexto de retenção. Chaves = `sub_destination` do Config/retention.php.
 *  Contexto novo cai no valor cru — o dono do vocabulário é a config, não este mapa. */
const CONTEXTO_PT: Record<string, string> = {
  'nfe-xml': 'XML de NF-e',
  'nfse-xml': 'XML de NFS-e',
  'documentos-fiscais': 'Documentos fiscais',
  contratos: 'Contratos',
  'repair-foto': 'Foto de reparo',
  'os-anexo': 'Anexo de OS',
  'ticket-anexo': 'Anexo de ticket',
  default: 'Sem contexto mapeado',
}
```

```diff
   <td className="px-4 py-3">
-    <code className="text-xs">{p.sub}</code>
+    <Stack gap={1} className="min-w-0">
+      <span className="font-medium text-foreground">{CONTEXTO_PT[p.sub] ?? p.sub}</span>
+      <code className="text-xs text-muted-foreground">{p.sub}</code>
+    </Stack>
   </td>
```

Os 8 rótulos são os do `POLITICA` do protótipo (`arquivos-data.jsx`), que por sua vez espelha `Config/retention.php`. Se a config tiver um `sub` fora desta lista, ele aparece cru — comportamento desejado.

---

## 3. Bucket "Sensível" renderiza como fill sólido (AP7)

**Onde:** coluna `classificacao` do acervo.
**Hoje:** `variant={a.bucket === 'sensitive' ? 'destructive' : 'secondary'}`. O próprio arquivo documenta a distinção duas vezes (comentário do órfão, PR #6268): **`danger` é o par SOFT, `destructive` é o fill sólido**. Sensível é um *estado do arquivo*, não uma ação destrutiva — mesmo argumento que reclassificou os 9 badges do #6268, e o AP7 pede fundo tintado 6% + borda 22%, nunca fill.

```diff
   <Badge
-    variant={a.bucket === 'sensitive' ? 'destructive' : 'secondary'}
+    variant={a.bucket === 'sensitive' ? 'danger' : 'secondary'}
     title={a.bucket ?? undefined}
   >
```

Numa lista onde a maioria das linhas de NF-e/contrato é `sensitive`, o fill sólido pinta metade da coluna de vermelho cheio — é o mesmo defeito visual que o #6268 corrigiu no resto do repo.

---

## Gates a rodar

- `npm run lint` (pega `ds/no-db-jargon-in-ui` e a camada de tokens)
- `contrato-de-tela` de `/arquivos` — **atenção:** o gate procura a copy como TEXTO no arquivo, comentário incluído (falso-verde medido em 2026-08-25). Se "Contexto" mudar de forma no contrato, atualizar o `.contract.json` no mesmo PR e conferir com canário.
- visreg de `/arquivos?tab=acervo` e `?tab=retencao` e `?tab=trilha`
- smoke da trilha com um business que tenha ≥2 ações distintas registradas (senão o chip traduzido não é exercitado)

## Fora deste pedido (registrado, não pedido)

- O caminho `tone` do `StatusBadge` no espelho DS renderiza `danger` como fill sólido — oposto do repo. Consertar o espelho é decisão [W], não entra aqui.

---

# ADENDO 2026-08-26 · rodada 2 — os `.md` dependentes

Os 3 achados acima são `Index.tsx`. **Este adendo é só documentação**, e sai do lado do protótipo já corrigido: o Cowork parou de prometer o que o sistema não faz (aviso ao titular ligado) e passou a dizer o estado do agendado. Os `.md` do repo ainda descrevem o mundo antigo.

## 4. `Pages/Arquivos/Index.charter.md`

**Problema:** o charter não registra que o aviso ao titular é **config aspiracional**. O `Index.tsx` diz na tela ("ainda não implementado"), o protótipo agora também — o charter, que é a lei da tela, não diz. Quem ler só o charter reimplementa o botão ligado.

**Pedido:** na seção de estado/ondas, dois fatos explícitos:

1. `notice_dias` (LGPD Art. 18 §VI) é **intenção de configuração, sem caminho de envio** — nem tela, nem job, nem command. Ligar isto é onda 3 e depende da proposta de ADR `arquivos-retencao-ui-aviso-titular`.
2. O estado do `arquivos:retention-cleanup` (agendado ou não) é **medido no runtime** (`Schedule::events()`), nunca deduzido do Kernel — e a vista Retenção é obrigada a dizer qual dos dois. É requisito de copy, não detalhe de implementação.

## 5. `Pages/Arquivos/Index.casos.md`

**Pedido:** dois UC novos, que hoje nenhum caso cobre:

- **UC-ARQ-agendado-off** — `arquivos:retention-cleanup` não agendado: a vista Retenção mostra os KPIs **e** a frase de que ninguém marcou o agendado. Critério: KPI > 0 sem a frase é falha — número sem dono vira paisagem.
- **UC-ARQ-notice-aspiracional** — arquivo Sensível com titular a ≤`notice_dias` do prazo: a tela pode listar o pendente, **não** pode oferecer ação de avisar. Critério: nenhum botão habilitado de "avisar titular" enquanto a onda 3 não existir.

Se houver caso antigo que descreva o aviso como funcional, ele é o que está errado — corrigir, não duplicar.

## 6. Contrato de tela `arquivos-index`

Duas coisas, e uma delas é achado seu:

- A nota `_pendente_w` diz que chip de bucket em PT-BR "aí sim entra na copy" — mas `acervo-filtros` **já** pina Sensível/Em uso/Histórico/Descartar. A nota está desatualizada contra o próprio contrato: remover ou reescrever. Você reportou e não tocou — certo; agora está pedido.
- Se a seção `retencao-regras` pina copy, o cartão "Aviso ao titular" mudou de subtexto (agora carrega "ainda não implementado, é config aspiracional"). Atualizar o pin no mesmo PR, com canário — o gate casa texto no fonte e já deu falso-verde nesta tela uma vez.
- **Não** pinar os mapas `ACAO_PT`/`CONTEXTO_PT`: seria falso-verde por construção, como você mesmo argumentou.

## Reparo meu, registrado

No item 2 eu invoquei `ds/no-db-jargon-in-ui` como mecanismo. Você leu o seletor (`eslint.config.js:226`): ele casa `JSXText` literal em snake_case e **exclui `<code>`** — o `<code>{p.sub}</code>` era data-driven e dentro de `<code>`, duplamente fora do alcance. A regra nunca reprovou aquilo e não reprova a regressão. A mudança segue certa pelo charter L88 e pelo protótipo; a justificativa mecânica era minha invenção. Reescreva a nota do PR nesse eixo.

## Decisões do [W] (não suas)

- Commitar em `claude/arquivos-prototype-live-parity-542e1b` e abrir o PR: **sim** — com este adendo incluído.
- Rebake da baseline de pixel (`workflow_dispatch` do visual-regression, `screens: ["Arquivos"]`): **depois do merge**, disparado pelo [W]. Divergência intencional e monotônica, mas baseline nova sem PR que a explique é pior que baseline velha.
- Smoke da trilha e visreg das 3 abas não rodados: aceito. A mudança é markup com fallback `?? f.acao` — pior caso, ação nova aparece crua, que é o comportamento declarado.
