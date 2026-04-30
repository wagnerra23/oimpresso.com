#!/usr/bin/env node
/**
 * generate-dxt.js — Gera arquivo .dxt (Claude Desktop Extension) para membros do time oimpresso.
 *
 * Uso:
 *   node scripts/generate-dxt.js --name="Eliana" --token="mcp_xxx..." --out=./dxt
 *   node scripts/generate-dxt.js --all --tokens-file=./scripts/tokens.json --out=./dxt
 *
 * Flags:
 *   --name        Nome do membro (ex: Wagner, Felipe, Maira, Luiz, Eliana)
 *   --token       Token MCP pessoal (mcp_...)
 *   --out         Pasta de saída (default: ./dxt)
 *   --all         Gera pra todos usando --tokens-file
 *   --tokens-file JSON com { "Nome": "mcp_token", ... }
 *   --url         MCP server URL (default: https://mcp.oimpresso.com/api/mcp)
 *
 * Gera: <out>/oimpresso-mcp-<nome-lowercase>.dxt
 *
 * O .dxt é um ZIP com:
 *   manifest.json  — metadata + token embutido
 *   server/index.js — bridge stdio↔HTTP (idêntico para todos)
 */

'use strict';

const fs   = require('fs');
const path = require('path');
const zlib = require('zlib');

// ---------- mini ZIP writer (sem dep externa) ----------

function uint32LE(n) {
  const b = Buffer.alloc(4);
  b.writeUInt32LE(n >>> 0, 0);
  return b;
}
function uint16LE(n) {
  const b = Buffer.alloc(2);
  b.writeUInt16LE(n & 0xffff, 0);
  return b;
}

function crc32(buf) {
  const table = crc32.table || (crc32.table = (() => {
    const t = new Uint32Array(256);
    for (let i = 0; i < 256; i++) {
      let c = i;
      for (let j = 0; j < 8; j++) c = (c & 1) ? (0xedb88320 ^ (c >>> 1)) : (c >>> 1);
      t[i] = c;
    }
    return t;
  })());
  let crc = 0xffffffff;
  for (let i = 0; i < buf.length; i++) crc = table[(crc ^ buf[i]) & 0xff] ^ (crc >>> 8);
  return (crc ^ 0xffffffff) >>> 0;
}

function buildZip(files) {
  // files: [{ name: string, data: Buffer }]
  const parts   = [];
  const central = [];
  let offset    = 0;

  for (const { name, data } of files) {
    const nameBuf  = Buffer.from(name, 'utf8');
    const crc      = crc32(data);
    const dosDate  = 0x5699; // 2026-04-26
    const dosTime  = 0x8800; // 17:00

    // Local file header
    const localHeader = Buffer.concat([
      Buffer.from([0x50,0x4b,0x03,0x04]), // sig
      uint16LE(20),           // version needed
      uint16LE(0),            // flags
      uint16LE(0),            // compression: stored
      uint16LE(dosTime),
      uint16LE(dosDate),
      uint32LE(crc),
      uint32LE(data.length),
      uint32LE(data.length),
      uint16LE(nameBuf.length),
      uint16LE(0),            // extra len
      nameBuf,
    ]);
    parts.push(localHeader, data);

    // Central directory entry
    central.push(Buffer.concat([
      Buffer.from([0x50,0x4b,0x01,0x02]), // sig
      uint16LE(20),           // version made by
      uint16LE(20),           // version needed
      uint16LE(0),            // flags
      uint16LE(0),            // compression: stored
      uint16LE(dosTime),
      uint16LE(dosDate),
      uint32LE(crc),
      uint32LE(data.length),
      uint32LE(data.length),
      uint16LE(nameBuf.length),
      uint16LE(0),            // extra
      uint16LE(0),            // comment
      uint16LE(0),            // disk start
      uint16LE(0),            // int attrs
      uint32LE(0),            // ext attrs
      uint32LE(offset),       // local header offset
      nameBuf,
    ]));

    offset += localHeader.length + data.length;
  }

  const centralBuf  = Buffer.concat(central);
  const eocd = Buffer.concat([
    Buffer.from([0x50,0x4b,0x05,0x06]),
    uint16LE(0), uint16LE(0),
    uint16LE(files.length),
    uint16LE(files.length),
    uint32LE(centralBuf.length),
    uint32LE(offset),
    uint16LE(0),
  ]);

  return Buffer.concat([...parts, centralBuf, eocd]);
}

// ---------- bridge server/index.js (idêntico para todos) ----------

const BRIDGE_JS = `#!/usr/bin/env node
// Oimpresso MCP DXT — bridge stdio<=>HTTP nativo (Node 18+ fetch).
const fs = require('fs');
const path = require('path');
const os = require('os');

const LOG = path.join(os.tmpdir(), 'oimpresso-mcp-debug.log');
function log(msg) { try { fs.appendFileSync(LOG, \`[\${new Date().toISOString()}] \${msg}\\n\`); } catch {} }

const url  = process.env.MCP_URL;
const auth = process.env.MCP_AUTHORIZATION;

log('========== START ==========');
log(\`platform=\${process.platform} node=\${process.version}\`);
log(\`url=\${url || '<MISSING>'} auth=\${auth ? '<SET>' : '<MISSING>'}\`);

if (!url || !auth) { log('FATAL env'); process.exit(1); }
if (typeof fetch !== 'function') { log('FATAL: fetch ausente — Node < 18'); process.exit(1); }

let sessionId = null;
let buffer = '';

async function postOne(line) {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json, text/event-stream',
    'Authorization': auth,
  };
  if (sessionId) headers['Mcp-Session-Id'] = sessionId;
  let res;
  try {
    res = await fetch(url, { method: 'POST', headers, body: line });
  } catch (e) {
    log(\`fetch error: \${e.message}\`);
    try {
      const msg = JSON.parse(line);
      if (msg.id !== undefined) {
        process.stdout.write(JSON.stringify({ jsonrpc: '2.0', id: msg.id, error: { code: -32603, message: 'Bridge fetch error: ' + e.message } }) + '\\n');
      }
    } catch {}
    return;
  }
  const newSession = res.headers.get('mcp-session-id');
  if (newSession && newSession !== sessionId) { sessionId = newSession; log(\`session=\${sessionId}\`); }
  if (res.status === 202 || res.status === 204) { log(\`-> \${res.status} (no body)\`); return; }
  const ct = (res.headers.get('content-type') || '').toLowerCase();
  if (ct.includes('text/event-stream') && res.body) {
    const reader = res.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let sseBuf = '';
    try {
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        sseBuf += decoder.decode(value, { stream: true });
        let evEnd;
        while ((evEnd = sseBuf.indexOf('\\n\\n')) >= 0) {
          const ev = sseBuf.slice(0, evEnd);
          sseBuf = sseBuf.slice(evEnd + 2);
          for (const evLine of ev.split('\\n')) {
            if (evLine.startsWith('data:')) { const data = evLine.slice(5).trim(); if (data) process.stdout.write(data + '\\n'); }
          }
        }
      }
    } catch (e) { log(\`SSE read error: \${e.message}\`); }
  } else {
    const text = await res.text();
    if (text) process.stdout.write(text.endsWith('\\n') ? text : text + '\\n');
  }
}

process.stdin.setEncoding('utf-8');
process.stdin.on('data', (chunk) => {
  buffer += chunk;
  let nl;
  while ((nl = buffer.indexOf('\\n')) >= 0) {
    const line = buffer.slice(0, nl).replace(/\\r$/, '').trim();
    buffer = buffer.slice(nl + 1);
    if (!line) continue;
    postOne(line).catch((e) => log(\`postOne uncaught: \${e.message}\`));
  }
});
process.stdin.on('end', () => { log('stdin ended'); process.exit(0); });
process.stdin.on('error', (e) => { log(\`stdin error: \${e.message}\`); process.exit(1); });
log('bridge listening');
`;

// ---------- geração principal ----------

function generateDxt({ name, token, url, outDir }) {
  const slug        = name.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, '-');
  const displayName = `Oimpresso MCP - ${name}`;
  const fileName    = `oimpresso-mcp-${slug}.dxt`;

  const manifest = {
    dxt_version:  '0.1',
    name:         `oimpresso-mcp-${slug}`,
    display_name: displayName,
    version:      '1.0.0',
    description:  `Acesso MCP ao Oimpresso ERP - memória, ADRs, sessões, decisões. Token pessoal de ${name} embutido. Bridge via Node fetch.`,
    author: {
      name:  'Oimpresso ERP',
      email: 'wagner@oimpresso.com',
      url:   'https://oimpresso.com',
    },
    server: {
      type:        'node',
      entry_point: 'server/index.js',
      mcp_config: {
        command: 'node',
        args:    ['${__dirname}/server/index.js'],
        env: {
          MCP_URL:           url,
          MCP_AUTHORIZATION: `Bearer ${token}`,
        },
      },
    },
  };

  const zip = buildZip([
    { name: 'manifest.json',   data: Buffer.from(JSON.stringify(manifest, null, 4), 'utf8') },
    { name: 'server/index.js', data: Buffer.from(BRIDGE_JS, 'utf8') },
  ]);

  fs.mkdirSync(outDir, { recursive: true });
  const outPath = path.join(outDir, fileName);
  fs.writeFileSync(outPath, zip);
  return outPath;
}

// ---------- CLI ----------

function parseArgs() {
  const args = {};
  process.argv.slice(2).forEach(a => {
    const m = a.match(/^--([^=]+)(?:=(.*))?$/);
    if (m) args[m[1]] = m[2] === undefined ? true : m[2];
  });
  return args;
}

function main() {
  const args   = parseArgs();
  const outDir = args.out || './dxt';
  const mcpUrl = args.url || 'https://mcp.oimpresso.com/api/mcp';

  if (args.all) {
    const tokensFile = args['tokens-file'] || './scripts/tokens.json';
    if (!fs.existsSync(tokensFile)) {
      console.error(`Arquivo ${tokensFile} não encontrado.`);
      console.error('Crie um JSON com: { "Wagner": "mcp_xxx", "Eliana": "mcp_yyy", ... }');
      process.exit(1);
    }
    const tokens = JSON.parse(fs.readFileSync(tokensFile, 'utf8'));
    for (const [name, token] of Object.entries(tokens)) {
      const out = generateDxt({ name, token, url: mcpUrl, outDir });
      console.log(`✅ ${name} → ${out}`);
    }
    console.log(`\n${Object.keys(tokens).length} DXT(s) gerados em ${outDir}/`);
    return;
  }

  if (!args.name || !args.token) {
    console.log(`
Uso:
  node scripts/generate-dxt.js --name="Eliana" --token="mcp_xxx..."
  node scripts/generate-dxt.js --all --tokens-file=./scripts/tokens.json

Flags:
  --name        Nome do membro (Wagner / Felipe / Maira / Luiz / Eliana)
  --token       Token MCP pessoal (começa com mcp_)
  --out         Pasta de saída (default: ./dxt)
  --all         Gera pra todos via --tokens-file
  --tokens-file JSON: { "Nome": "mcp_token", ... }  (default: ./scripts/tokens.json)
  --url         MCP server URL (default: https://mcp.oimpresso.com/api/mcp)
`);
    process.exit(1);
  }

  const out = generateDxt({ name: args.name, token: args.token, url: mcpUrl, outDir });
  console.log(`✅ ${args.name} → ${out}`);
}

main();
