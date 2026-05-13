/**
 * Configuração remark-lint pro gate ONDA 5 S1 — frontmatter schema validation.
 *
 * Lê schemas em scripts/memory-schemas/*.schema.json.
 * Cada glob é validado contra o schema correspondente via remark-lint-frontmatter-schema
 * (que internamente usa AJV draft 2020-12).
 *
 * Workflow CI: .github/workflows/memory-schema-gate.yml roda matrix 1 job/schema.
 * Modo local PHP: php artisan jana:validate-memory.
 *
 * Grace period: env JANA_VALIDATE_MEMORY_STRICT=false default 14d → workflow respeita.
 *
 * Ver: scripts/memory-schemas/README.md + memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md §5.
 */

const path = require('path');

const SCHEMAS_DIR = 'scripts/memory-schemas';

module.exports = {
  plugins: [
    'remark-frontmatter',
    [
      'remark-lint-frontmatter-schema',
      {
        embed: 'glob',
        schemas: {
          // ADR Nygard
          [`${SCHEMAS_DIR}/adr.schema.json`]: [
            'memory/decisions/[0-9][0-9][0-9][0-9]-*.md',
          ],
          // SPEC por módulo
          [`${SCHEMAS_DIR}/spec.schema.json`]: [
            'memory/requisitos/*/SPEC.md',
          ],
          // RUNBOOK procedural
          [`${SCHEMAS_DIR}/runbook.schema.json`]: [
            'memory/requisitos/**/RUNBOOK*.md',
          ],
          // Session log diário
          [`${SCHEMAS_DIR}/session.schema.json`]: [
            'memory/sessions/[0-9][0-9][0-9][0-9]-*.md',
          ],
          // Handoff append-only
          [`${SCHEMAS_DIR}/handoff.schema.json`]: [
            'memory/handoffs/[0-9][0-9][0-9][0-9]-*.md',
          ],
          // Page Charter (Tier A)
          [`${SCHEMAS_DIR}/charter.schema.json`]: [
            'resources/js/Pages/**/*.charter.md',
          ],
        },
      },
    ],
  ],
  // Ignora docs sem frontmatter (legacy) durante grace period 14d
  settings: {
    bullet: '-',
  },
};
