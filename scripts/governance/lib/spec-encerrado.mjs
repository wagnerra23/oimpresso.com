// @ts-check
/**
 * spec-encerrado.mjs — SPEC declarado encerrado não é dívida: sai do corpus dos gates.
 *
 * POR QUE EXISTE (medido 2026-07-28). Oito `memory/requisitos/*​/SPEC.md` declaram
 * `status: historical` ou `arquivado` no frontmatter — cinco com lápide ⚰️ no corpo — e
 * mesmo assim suas **56 US** entravam nos denominadores de DOIS gates *required*
 * (`anchor-lint ADR 0273` e `doneness-lint ADR 0302`), inflando `anchor_coverage`,
 * `req_sem_aceite`, `req_sem_covering_test` e a zona-cinza.
 *
 * O caso mais claro é o `memory/requisitos/PontoWr2/SPEC.md:10`, que diz textualmente
 * *"⚰️ HISTORICAL … As `US-PONT-NNN` aqui NÃO SÃO CONTRATO VIVO"* — e as 12 US dele
 * seguiam contando como dívida viva.
 *
 * O PREDICADO É DETERMINÍSTICO, NÃO HEURÍSTICO. Lê o campo `status:` do frontmatter,
 * que já é enum validado por schema (`memory-health` STATUS_OK). **Não** adivinha por
 * nome de arquivo, pasta ou vocabulário no corpo — essa família (allowlist-de-pasta
 * 2026-06-30 · guard `@scope` 2026-07-09 · vocabulário 2026-07-16 · `toHaveKey`
 * 2026-07-26) está morta 4× no §5 de `memory/proibicoes.md`, sempre pelo mesmo motivo:
 * critério sintático reprova o legítimo.
 *
 * NÃO é gate novo — é correção de DENOMINADOR de gate existente. Não julga nada, não
 * fecha nada, não exige nada de ninguém: só para de contar contrato que o próprio autor
 * declarou encerrado.
 *
 * @see scripts/governance/anchor-lint.mjs · scripts/governance/doneness-lint.mjs
 * @see memory/sessions/2026-07-28-grade-ciclo-de-vida-da-tarefa.md
 */
import { readFileSync } from 'node:fs';

/** Valores de `status:` do frontmatter que tiram o SPEC do corpus dos gates. */
export const STATUS_ENCERRADO = new Set(['historical', 'arquivado']);

/**
 * Devolve o status de encerramento do SPEC, ou `null` se ele é contrato vivo.
 *
 * Fail-open de propósito: arquivo ilegível / sem frontmatter / sem `status:` conta como
 * VIVO. Um SPEC nunca sai do gate por acidente de parsing — só por declaração explícita.
 *
 * @param {string} caminho caminho do SPEC.md
 * @returns {string|null} `'historical'` | `'arquivado'` | `null` (vivo)
 */
export function specEncerrado(caminho) {
  let fm;
  try {
    fm = /^---\r?\n([\s\S]*?)\r?\n---/.exec(readFileSync(caminho, 'utf8').slice(0, 4000));
  } catch {
    return null; // ilegível = vivo (fail-open)
  }
  if (!fm) return null;
  const m = /^status:\s*["']?([A-Za-z-]+)["']?\s*$/m.exec(fm[1]);
  const v = m && m[1].toLowerCase();
  return v && STATUS_ENCERRADO.has(v) ? v : null;
}

/**
 * Particiona a lista de SPECs em vivos × encerrados.
 *
 * O chamador **deve** reportar os encerrados (stderr, nunca stdout — o stdout do `--json`
 * alimenta o `sdd-scorecard`). Silêncio aqui reconstrói o "gate mudo", que a regra
 * "Sempre fazer" #5 de `memory/proibicoes.md` classifica como pior que gate ausente.
 *
 * @param {string[]} specs
 * @returns {{vivos: string[], encerrados: Array<[string, string]>}}
 */
export function particionarSpecs(specs) {
  const vivos = [];
  const encerrados = [];
  for (const p of specs) {
    const s = specEncerrado(p);
    if (s) encerrados.push([p, s]);
    else vivos.push(p);
  }
  return { vivos, encerrados };
}
