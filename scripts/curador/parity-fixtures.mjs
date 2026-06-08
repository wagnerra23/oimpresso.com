#!/usr/bin/env node
// Curador — gerador de fixtures pra ParityTest JS×PHP (US-ARQ-007).
//
// Lê uma lista de paths sintéticos de scripts/curador/parity-fixtures-input.json
// (default) e roda classifyFile(rules.mjs) em cada um, salvando expected output
// em tests/Fixtures/CuradorParity/{name}.expected.json.
//
// O Pest test correspondente em Modules/Arquivos/Tests/Feature/CuradorParityTest.php
// itera os mesmos paths via CuradorEngine.php e compara — divergência > 5% trava.
//
// Uso:
//   node scripts/curador/parity-fixtures.mjs              # gera default
//   node scripts/curador/parity-fixtures.mjs --input X    # input customizado
//   node scripts/curador/parity-fixtures.mjs --print      # imprime sem salvar

import { promises as fs } from 'node:fs';
import { dirname, resolve, join, extname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { classifyFile } from './lib/rules.mjs';

const SCRIPT_DIR = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = resolve(SCRIPT_DIR, '..', '..');
const FIXTURES_DIR = join(REPO_ROOT, 'tests', 'Fixtures', 'CuradorParity');

// 30 fixtures sintéticas cobrindo as 18 regras + edge cases.
// Formato: { name, path, sizeBytes, mtimeIso, basename, extension, md5, dirname, isDuplicate, duplicateOf }
const DEFAULT_FIXTURES = [
  // SENSITIVE
  { name: 'env_real',           basename: '.env',                          path: 'D:\\app\\.env',                                          sizeBytes: 9280 },
  { name: 'env_dify',           basename: '.env.1.4.0-dify.md',            path: 'D:\\Conhecimento\\Infra\\Dify\\.env.1.4.0-dify.md',     sizeBytes: 36530 },
  { name: 'env_example',        basename: '.env.example',                  path: 'D:\\repo\\.env.example',                                 sizeBytes: 200 },
  { name: 'env_sample',         basename: '.env.sample',                   path: 'D:\\repo\\.env.sample',                                  sizeBytes: 220 },
  { name: 'pfx_cert',           basename: 'cert.pfx',                      path: 'D:\\Cert\\cert.pfx',                                     sizeBytes: 9508 },
  { name: 'rdp_client',         basename: 'cliente.rdp',                   path: 'D:\\RDP\\cliente.rdp',                                   sizeBytes: 2900 },
  { name: 'pem_key',            basename: 'private.pem',                   path: 'D:\\keys\\private.pem',                                  sizeBytes: 1600 },
  { name: 'kdbx_password',      basename: 'cofre.kdbx',                    path: 'D:\\backup\\cofre.kdbx',                                 sizeBytes: 5000 },
  { name: 'ssh_id_rsa',         basename: 'id_rsa',                        path: 'C:\\Users\\wagne\\.ssh\\id_rsa',                         sizeBytes: 3200 },
  { name: 'ssh_id_ed25519',     basename: 'id_ed25519',                    path: 'C:\\Users\\wagne\\.ssh\\id_ed25519',                     sizeBytes: 411 },
  { name: 'pii_xml_cliente',    basename: 'NFE.xml',                       path: 'D:\\Suporte\\XML Clientes\\NFE.xml',                     sizeBytes: 5100 },
  { name: 'credentials_json',   basename: 'credentialsChatWoot.json',      path: 'D:\\Infra\\chatwoot\\credentialsChatWoot.json',          sizeBytes: 186 },

  // DISCARD
  { name: 'oss_node_modules',   basename: 'index.js',                      path: 'D:\\app\\node_modules\\foo\\index.js',                   sizeBytes: 2400 },
  { name: 'oss_software_folder', basename: 'rb_controller.rb',             path: 'D:\\Conhecimento\\Software\\chatwoot\\app\\controllers\\foo.rb', sizeBytes: 1800 },
  { name: 'docs_git_internals', basename: 'COMMIT_EDITMSG',                path: 'D:\\Conhecimento\\Docs\\.git\\COMMIT_EDITMSG',           sizeBytes: 200 },
  { name: 'oss_readme_large',   basename: 'README.md',                     path: 'D:\\Software\\foo\\README.md',                           sizeBytes: 60_000 },
  { name: 'old_meeting',        basename: 'Ata_de_Reuniao_2024-01.txt',    path: 'D:\\foo\\Ata_de_Reuniao_2024-01.txt',                    sizeBytes: 800, mtimeIsoOverride: '2023-01-01T00:00:00Z' },

  // MEMORY
  { name: 'cnab_itau',          basename: 'Cnab240_Itau.pdf',              path: 'D:\\Conhecimento\\ManuaisTecnicos\\Itau\\Cnab240_Itau.pdf', sizeBytes: 4_000_000 },
  { name: 'cnab_sicred_basename', basename: 'Sicred_ManualCnab400.pdf',    path: 'D:\\foo\\Sicred_ManualCnab400.pdf',                      sizeBytes: 3_000_000 },
  { name: 'cnab_bradesco',      basename: 'CNAB240.pdf',                   path: 'D:\\Conhecimento\\ManuaisTecnicos\\Bradesco\\CNAB240.pdf', sizeBytes: 2_000_000 },
  { name: 'fiscal_sped',        basename: 'GUIA_PRATICO_EFD_ICMS_IPI.pdf', path: 'D:\\fiscal\\GUIA_PRATICO_EFD_ICMS_IPI.pdf',              sizeBytes: 2_500_000 },
  { name: 'branding_jana',      basename: 'logo.png',                      path: 'D:\\Conhecimento\\Imagens\\Jana\\logo.png',              sizeBytes: 50_000 },
  { name: 'branding_office',    basename: 'logo.svg',                      path: 'D:\\Conhecimento\\Imagens\\Office Impresso\\logo.svg',   sizeBytes: 40_000 },
  { name: 'kb_legacy_faq',      basename: 'faq.txt',                       path: 'D:\\Conhecimento\\Suporte ao Cliente\\Base de Conhecimento (KB)\\FAQs\\faq.txt', sizeBytes: 3000 },
  { name: 'infra_portainer',    basename: 'chatwoot.yaml',                 path: 'D:\\Conhecimento\\Infraestrutura & Operações\\Portainer\\Docker\\chatwoot.yaml', sizeBytes: 800 },
  { name: 'infra_evolution',    basename: 'traefik.yaml',                  path: 'D:\\Conhecimento\\Infraestrutura & Operações\\Evolution API 2.0\\traefik.yaml', sizeBytes: 1100 },
  { name: 'office_legacy_venda', basename: 'Venda.txt',                    path: 'D:\\Conhecimento\\Manuais Técnicos\\Venda.txt',          sizeBytes: 25_501 },
  { name: 'office_legacy_producao', basename: 'Producao.txt',              path: 'D:\\Conhecimento\\Manuais Técnicos\\Producao.txt',       sizeBytes: 12_000 },
  { name: 'large_pdf_indexed',  basename: 'big.pdf',                       path: 'D:\\foo\\big.pdf',                                       sizeBytes: 2_000_000 },

  // FALLBACK
  { name: 'common_active',      basename: 'relatorio.txt',                 path: 'D:\\foo\\relatorio.txt',                                 sizeBytes: 1024 },
];

function expandFixture(f) {
  // extname(".env") === "" em Node — espelha comportamento real do discover.mjs
  const ext = extname(f.basename).toLowerCase();
  const md5 = 'a'.repeat(32);
  const mtime = f.mtimeIsoOverride || new Date().toISOString();
  // path.dirname não funciona bem em Windows misto (\\ + /); usar split simples
  const idx = f.path.lastIndexOf(f.basename);
  const dir = idx >= 0 ? f.path.slice(0, idx) : '';
  return {
    name: f.name,
    input: {
      path: f.path,
      sizeBytes: f.sizeBytes,
      mtime,
      extension: ext,
      basename: f.basename,
      dirname: dir,
      md5,
      isDuplicate: false,
      duplicateOf: null,
    },
  };
}

async function main() {
  const args = process.argv.slice(2);
  const printOnly = args.includes('--print');

  if (!printOnly) {
    await fs.mkdir(FIXTURES_DIR, { recursive: true });
  }

  const out = {};
  for (const raw of DEFAULT_FIXTURES) {
    const fx = expandFixture(raw);
    const result = classifyFile(fx.input);
    out[fx.name] = {
      input: fx.input,
      expected: result,
    };
  }

  if (printOnly) {
    console.log(JSON.stringify(out, null, 2));
    return;
  }

  const filePath = join(FIXTURES_DIR, 'fixtures.json');
  await fs.writeFile(filePath, JSON.stringify(out, null, 2), 'utf8');

  console.log(`Wrote ${DEFAULT_FIXTURES.length} fixtures to ${filePath}`);
  const summary = {};
  for (const [name, item] of Object.entries(out)) {
    const bucket = item.expected.bucket;
    summary[bucket] = (summary[bucket] || 0) + 1;
  }
  console.log('Bucket distribution:', summary);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
