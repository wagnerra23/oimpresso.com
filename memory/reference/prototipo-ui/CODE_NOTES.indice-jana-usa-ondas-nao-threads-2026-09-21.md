# O `00-INDICE.md` da Jana usa `"ondas"` — e isso derruba o placar dos **13** playbooks, não só o dele

> **O que é:** pedido `[CL]` → `[CC]` para o gerador do playbook. O índice que desceu no handoff 28
> declara a unidade de trabalho como `"ondas"`; o consumidor in-repo exige `"threads"` **com
> `provas`**. Como o agregador avalia os índices num `map`, um índice que ele não entende lança e
> **mata a medição dos outros 12**. Não é preferência de vocabulário: é contrato de máquina.
>
> **Não conserto daqui, e a razão é do processo, não preguiça** — as três vias estão medidas no §4.

## O sintoma

`placar-de-lista (advisory)` ficou vermelho no [#7579](https://github.com/wagnerra23/oimpresso.com/pull/7579)
e segue vermelho no `main` desde o merge (2026-09-21 11:09:45Z):

```
NÃO MEDI: índice sem threads — "0 de 0" não é placar
Process completed with exit code 2
```

## A causa, com três controles

| # | teste | exit |
|---|---|---|
| 1 | com o `cowork-inbox/jana/playbook/00-INDICE.md` | **2** — `índice sem threads` |
| 2 | renomeando só a chave `"ondas"` → `"threads"` | **2** — `thread 01 sem "provas"` |
| 3 | **controle**: removendo o diretório `jana/` do corpus | **0** — placar completo |

O (3) prova que a causa é exatamente esse arquivo. **O (2) é o que muda a conclusão: não é typo de
chave, é outro contrato.** Sinonimizar só move o erro de linha.

Contado nos 13 índices de `cowork-inbox/*/playbook/`:

| chave | módulos |
|---|---|
| `"threads"` | **12** — ancora · compras · ds-atomos · financeiro · fiscal · governance · hrm · patrimonio · ponto · recepcao-pacote · shell-usermenu · sidebar |
| `"ondas"` | **1** — **jana** |

## O contrato que o consumidor exige

[`scripts/qa/placar-indice.mjs`](../../../scripts/qa/placar-indice.mjs), `validarIndice()`:

```js
if (!Array.isArray(indice.threads) || !indice.threads.length)
  throw new NaoMedi('índice sem threads — "0 de 0" não é placar');
…
for (const t of indice.threads) {
  if (!t || !t.id) throw new NaoMedi('thread sem id');
  if (ids.has(t.id)) throw new NaoMedi(`thread duplicada: ${t.id}`);
  if (!Array.isArray(t.provas)) throw new NaoMedi(`thread ${t.id} sem "provas"`);
}
```

Uma `thread` aceita, exemplo real do `hrm`:

```json
{ "id": "01",
  "titulo": "Build: TABS do HRM (−Presença · +Departamentos/Cargos)",
  "dono": "CC",
  "provas": [ { "tipo": "nao_contem",
                "path": "prototipo-ui/cowork/hrm-page.jsx",
                "padrao": "id:\"hrm-presenca\"" } ] }
```

A `onda` que desceu traz `{ id, secao, ficha, arquivos, estado }` — **sem `provas`**, que é o campo
do qual o estado é DERIVADO.

⚠️ **O `estado: "aberta"` do índice da Jana é sintoma do mesmo desencontro.** O placar existe
justamente para **não** ler estado escrito: *"o estado de cada thread é DERIVADO do repo — ninguém
escreve estado (Lei 2)"*. Um campo `estado` no índice é ignorado pelo consumidor e, pior, sugere
uma fonte de verdade que ele não usa.

## As três vias de conserto daqui — todas caem

1. **Fabricar as `provas` que faltam.** É escrever o dado que o medidor consome — o oposto exato da
   Lei 2 que o próprio script defende. Inventaria cobertura.
2. **Editar o `00-INDICE.md` no espelho.** [ADR 0374](../../decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md):
   `prototipo-ui/cowork/Wagner/**` é build-only. O próprio placar imprime isso na saída dele
   (*"editar aqui seria desfeito no próximo pacote"*), e é a lápide §5 2026-08-14.
3. **Tornar o agregador resiliente** (índice incompatível vira "NÃO MEDIDO", os 12 bons são
   medidos). Considerado e descartado: hoje o vermelho é **o que fez alguém investigar**; depois
   dele, um índice incompatível passa despercebido num summary de job advisory que ninguém abre.
   É a lápide §5 2026-07-09 — *advisory = não bloqueia o merge, **nunca** = não pode ficar vermelho*.

## O pedido

**O gerador do playbook deve emitir `threads` com `provas`, como nos outros 12.** Para as 2 ondas
da Jana, as provas naturais são estruturais e já existem no repo — as duas ondas mergearam
([#7587](https://github.com/wagnerra23/oimpresso.com/pull/7587) e
[#7591](https://github.com/wagnerra23/oimpresso.com/pull/7591)), então um `contem` sobre o
`JanaCockpit.tsx` fecharia as duas.

⚠️ **Não estou ditando as provas** — quem as escolhe é quem gera o playbook, e escolher por vocês
seria a mesma fabricação que recusei no §4.1. O que este documento fixa é o **contrato**: `threads`
(array não-vazio) · cada uma com `id` único e `provas` (array).

## O que NÃO é pedido aqui

- **Nada sobre o conteúdo das 2 fichas.** `01-painel.gating-pro.md` e `02-painel.estado-vazio.md`
  estavam corretos e executáveis — as duas ondas foram implementadas a partir deles sem ambiguidade,
  e o `PARAR SE` de ambas foi medido e não disparou. O defeito é só do índice.
- **Nada sobre a âncora.** Medido em 2026-09-21: `jana-merge.jsx` é **byte-idêntico** entre o
  espelho e o vivo (sha256 `7bb8e713130a9f80`, 60.716 bytes, 1141 linhas).

## Dois achados menores das mesmas fichas, para o próximo ciclo

1. **`variant="first"` não existe.** A ficha 02 pede `EmptyState` com essa variante; o componente
   declara `'default' | 'search' | 'error' | 'success'`
   ([`Components/shared/EmptyState.tsx`](../../../resources/js/Components/shared/EmptyState.tsx)).
   Ficou no `default`, declarado no UC-JPAIN-29.
2. **A dúvida do §8 da ficha 02 estava respondida pelo código vivo.** Ela pedia confirmar que
   `topClientes`/`methodsAgg` chegam `[]` e não `null`; as linhas imediatamente acima do predicado
   já faziam `.reduce()` direto nos dois, sem guard, desde sempre — com `null` a tela estaria
   quebrada hoje em qualquer business. Vale como padrão: antes de marcar "não medido" no §8,
   conferir se o código já depende da resposta.

---

**Canal:** PROTOCOL §10.2 (`[CL]` → `[CC]`) · **Origem:** sessão de 2026-09-21, ondas 01 e 02 do
playbook da Jana · **Recibo do vermelho:**
[#7579 comentário](https://github.com/wagnerra23/oimpresso.com/pull/7579#issuecomment-5759577169)
