#!/usr/bin/env node
// @ts-check
/**
 * grader-multitenant.mjs — ORÁCULO ESTÁTICO da tarefa "modelo business-scoped".
 *
 * Grader determinístico (não o olho do agente — lápide §5 2026-07-16 "medir a
 * propriedade errada e chamar de verificado"). Recebe o CÓDIGO retornado por um agente
 * e decide pass/fail pelos invariantes que a skill multi-tenant-patterns ENSINA:
 *   1. Model aplica global scope por business_id (booted()+addGlobalScope OU trait
 *      HasBusinessScope / ScopeByBusiness).
 *   2. Migration tem coluna business_id.
 *   3. Migration indexa business_id.
 *   4. Migration tem FK de business_id.
 * pass = TODOS os 4. É o "o agente aplicou o padrão Tier 0?" objetivamente.
 *
 * ⚠️ ORÁCULO DE SMOKE (proxy declarado): checagem ESTÁTICA do código, não Pest real no
 * CT100. Prova o mecanismo/harness barato. O pilot troca isto por teste de isolamento
 * cross-tenant rodado (o oráculo forte), sem mudar a forma do runs.json.
 */
export function gradeMultiTenant(code) {
  const c = String(code || '');
  const temScopeInline = /booted\s*\(\s*\)/.test(c) && /addGlobalScope/.test(c);
  const temScopeTrait = /use\s+\w*BusinessScope\b|ScopeByBusiness/.test(c);
  const globalScope = temScopeInline || temScopeTrait;
  const temColuna = /business_id/.test(c);
  const temIndex = /->index\(\s*['"]?business_id|index\(['"]business_id/.test(c);
  const temFK = /foreign\(\s*['"]business_id['"]\s*\)/.test(c);
  const criterios = { globalScope, temColuna, temIndex, temFK };
  const pass = Object.values(criterios).every(Boolean);
  return { pass, criterios };
}

// entry-point: node grader-multitenant.mjs <arquivo-de-codigo>
import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
if (import.meta.url === pathToFileURL(process.argv[1] || '').href) {
  const file = process.argv[2];
  if (!file) { console.error('uso: node grader-multitenant.mjs <arquivo>'); process.exit(1); }
  const r = gradeMultiTenant(readFileSync(file, 'utf8'));
  console.log(JSON.stringify(r, null, 2));
  process.exit(0);
}
