#!/usr/bin/env node
// @ts-check
/**
 * zip-reader.mjs — leitor de ZIP mínimo, sem dependência, com CRC-32 CONFERIDO.
 *
 * POR QUE EXISTE, e por que não é uma dependência nem um binário:
 *   · dependência nova (fflate/jszip/yauzl) exige ADR neste repo (CLAUDE.md §"Não criar nova
 *     tecnologia/dependência sem registrar ADR") — caro demais pra um formato estável de 1989;
 *   · binário externo (`unzip`, `tar`, `Expand-Archive`) NÃO é garantido no ambiente onde isto
 *     roda: o Git Bash daqui tem `unzip` mas seu `tar` é GNU (não lê zip), o PowerShell não tem
 *     `unzip`, e a lápide §5 de 2026-08-11 é exatamente sobre instrumento que depende de binário
 *     ausente e transforma o silêncio em "nada a reportar".
 *   `node:zlib` está garantido em todo lugar que roda Node. Então é ele.
 *
 * O QUE ELE GARANTE (≠ promessa):
 *   1. CRC-32 de CADA arquivo conferido contra o que o próprio ZIP declara. Divergiu = lança.
 *      `unzip -q` não avisa nada nesse caso; aqui é erro duro.
 *   2. Tamanho descomprimido conferido contra o declarado.
 *   3. ZIP64 e método de compressão desconhecido são RECUSADOS explicitamente — nunca lidos
 *      pela metade (fail-closed: não medi ≠ está vazio).
 *   4. zip-slip barrado: entrada com `..` ou caminho absoluto é recusada antes de escrever.
 *
 * Uso (biblioteca):
 *   import { lerZip, extrairZip } from './zip-reader.mjs';
 *   const entradas = lerZip(buffer);            // [{ nome, dados }]
 *   extrairZip(buffer, destino);                // grava a árvore e devolve a lista
 */
import { inflateRawSync } from 'node:zlib';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';

const SIG_EOCD = 0x06054b50;
const SIG_EOCD64_LOC = 0x07064b50;
const SIG_CD = 0x02014b50;
const SIG_LFH = 0x04034b50;
const U32_MAX = 0xffffffff;

const TABELA_CRC = (() => {
  const t = new Int32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    t[n] = c;
  }
  return t;
})();

/** CRC-32 (IEEE 802.3), o mesmo que o ZIP grava. */
export function crc32(buf) {
  let c = -1;
  for (let i = 0; i < buf.length; i++) c = TABELA_CRC[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ -1) >>> 0;
}

/** Acha o End Of Central Directory varrendo de trás pra frente (o comentário pode ter 64 KiB). */
function acharEocd(buf) {
  const minimo = Math.max(0, buf.length - 0xffff - 22);
  for (let i = buf.length - 22; i >= minimo; i--) {
    if (buf.readUInt32LE(i) === SIG_EOCD) return i;
  }
  throw new Error('ZIP inválido: End Of Central Directory não encontrado');
}

/**
 * Lê o ZIP inteiro e devolve as entradas de ARQUIVO (diretórios são omitidos).
 * @returns {{nome: string, dados: Buffer}[]}
 */
export function lerZip(buf) {
  const eocd = acharEocd(buf);
  if (eocd >= 20 && buf.readUInt32LE(eocd - 20) === SIG_EOCD64_LOC) {
    throw new Error('ZIP64 não suportado — recuso ler pela metade (fail-closed)');
  }
  const total = buf.readUInt16LE(eocd + 10);
  const cdOffset = buf.readUInt32LE(eocd + 16);
  if (cdOffset === U32_MAX) throw new Error('ZIP64 não suportado (offset sentinela)');

  const saida = [];
  let p = cdOffset;
  for (let i = 0; i < total; i++) {
    if (buf.readUInt32LE(p) !== SIG_CD) throw new Error(`ZIP inválido: central directory #${i} corrompida`);
    const metodo = buf.readUInt16LE(p + 10);
    const crcDecl = buf.readUInt32LE(p + 16);
    const compSize = buf.readUInt32LE(p + 20);
    const rawSize = buf.readUInt32LE(p + 24);
    const nomeLen = buf.readUInt16LE(p + 28);
    const extraLen = buf.readUInt16LE(p + 30);
    const comentLen = buf.readUInt16LE(p + 32);
    const lfhOffset = buf.readUInt32LE(p + 42);
    const nome = buf.toString('utf8', p + 46, p + 46 + nomeLen);
    p += 46 + nomeLen + extraLen + comentLen;

    if (compSize === U32_MAX || rawSize === U32_MAX || lfhOffset === U32_MAX) {
      throw new Error(`ZIP64 não suportado (entrada ${nome})`);
    }
    if (nome.endsWith('/')) continue; // diretório
    if (metodo !== 0 && metodo !== 8) {
      throw new Error(`método de compressão ${metodo} não suportado (entrada ${nome})`);
    }
    if (buf.readUInt32LE(lfhOffset) !== SIG_LFH) throw new Error(`ZIP inválido: local header de ${nome}`);

    const lfhNome = buf.readUInt16LE(lfhOffset + 26);
    const lfhExtra = buf.readUInt16LE(lfhOffset + 28);
    const ini = lfhOffset + 30 + lfhNome + lfhExtra;
    const bruto = buf.subarray(ini, ini + compSize);
    const dados = metodo === 0 ? Buffer.from(bruto) : inflateRawSync(bruto);

    if (dados.length !== rawSize) {
      throw new Error(`${nome}: tamanho ${dados.length} != declarado ${rawSize}`);
    }
    const crcReal = crc32(dados);
    if (crcReal !== crcDecl) {
      throw new Error(`${nome}: CRC-32 ${crcReal.toString(16)} != declarado ${crcDecl.toString(16)}`);
    }
    saida.push({ nome, dados });
  }
  return saida;
}

/** Entrada é segura pra gravar? (zip-slip: sem `..` de segmento, sem caminho absoluto) */
export function nomeSeguro(nome) {
  if (nome.startsWith('/') || /^[A-Za-z]:/.test(nome)) return false;
  return !nome.split('/').includes('..');
}

/** Extrai pro destino. Devolve as entradas gravadas. */
export function extrairZip(buf, destino) {
  const entradas = lerZip(buf);
  for (const e of entradas) {
    if (!nomeSeguro(e.nome)) throw new Error(`entrada recusada (zip-slip): ${e.nome}`);
  }
  for (const e of entradas) {
    const alvo = join(destino, ...e.nome.split('/'));
    mkdirSync(dirname(alvo), { recursive: true });
    writeFileSync(alvo, e.dados);
  }
  return entradas;
}
