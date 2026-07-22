#!/usr/bin/env node
// Entrada explícita pro meta-test de governança. O núcleo vive no --selftest
// do script canônico para garantir que CLI e teste exercitam a mesma implementação.

import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const result = spawnSync(process.execPath, [join(here, 'documentation-loop.mjs'), '--selftest'], {
  stdio: 'inherit',
});
process.exit(result.status ?? 1);
