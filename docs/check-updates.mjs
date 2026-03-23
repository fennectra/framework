#!/usr/bin/env node

/**
 * Detecte les modules dont la documentation est obsolete
 * en comparant les dates git des fichiers avec celles de docs/index.json.
 *
 * Usage : node docs/check-updates.mjs [--json]
 */

import { readFileSync } from 'fs';
import { execSync } from 'child_process';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..');
const jsonFlag = process.argv.includes('--json');

// Lire l'index
const indexPath = resolve(__dirname, 'index.json');
let index;
try {
    index = JSON.parse(readFileSync(indexPath, 'utf-8'));
} catch {
    console.error('[!] docs/index.json introuvable ou invalide');
    process.exit(1);
}

function gitDate(filePath) {
    try {
        const out = execSync(
            `git log -1 --format=%ai -- "${filePath}"`,
            { cwd: root, encoding: 'utf-8', stdio: ['pipe', 'pipe', 'pipe'] }
        ).trim();
        return out ? out.slice(0, 10) : null;
    } catch {
        return null;
    }
}

const stale = [];

for (const [module, meta] of Object.entries(index.modules)) {
    const changed = [];

    for (const [filePath, indexDate] of Object.entries(meta.files)) {
        const currentDate = gitDate(filePath);

        if (!currentDate) continue; // fichier supprime ou non-tracked
        if (currentDate > indexDate) {
            changed.push({ file: filePath, was: indexDate, now: currentDate });
        }
    }

    if (changed.length > 0) {
        stale.push({ module, doc: meta.doc_file, lastDoc: meta.last_documented, changed });
    }
}

// Sortie
if (jsonFlag) {
    console.log(JSON.stringify(stale, null, 2));
    process.exit(stale.length > 0 ? 1 : 0);
}

if (stale.length === 0) {
    console.log('\n  [ok] Toute la documentation est a jour.\n');
    process.exit(0);
}

console.log(`\n  ${stale.length} module(s) a mettre a jour :\n`);

for (const { module, doc, lastDoc, changed } of stale) {
    console.log(`  ${module}  (doc: ${lastDoc})`);
    console.log(`    -> ${doc}`);
    for (const { file, was, now } of changed) {
        console.log(`       ${file}  ${was} -> ${now}`);
    }
    console.log();
}

console.log(`  Pour mettre a jour : /doc ${stale.map(s => s.module).join(' ')}\n`);
process.exit(1);
