#!/usr/bin/env node

/**
 * Detects modules whose documentation is outdated
 * by comparing file dates with docs/index.json.
 *
 * Uses git log date first, falls back to filesystem mtime if git has no history.
 *
 * Usage: node docs/check-updates.mjs [--json]
 */

import { readFileSync, statSync, existsSync } from 'fs';
import { execSync } from 'child_process';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..');
const jsonFlag = process.argv.includes('--json');

// Read the index
const indexPath = resolve(__dirname, 'index.json');
let index;
try {
    index = JSON.parse(readFileSync(indexPath, 'utf-8'));
} catch {
    console.error('[!] docs/index.json not found or invalid');
    process.exit(1);
}

/**
 * Get the last modification date of a file.
 * Tries git log first, falls back to filesystem mtime.
 */
function getFileDate(filePath) {
    const absPath = resolve(root, filePath);

    // Try git log first (most accurate when history exists)
    try {
        const out = execSync(
            `git log -1 --format=%ai -- "${filePath}"`,
            { cwd: root, encoding: 'utf-8', stdio: ['pipe', 'pipe', 'pipe'] }
        ).trim();
        if (out) return out.slice(0, 10);
    } catch {
        // git not available or not a repo
    }

    // Fallback: filesystem mtime
    try {
        if (existsSync(absPath)) {
            const stat = statSync(absPath);
            return stat.mtime.toISOString().slice(0, 10);
        }
    } catch {
        // file not accessible
    }

    return null;
}

const stale = [];

for (const [module, meta] of Object.entries(index.modules)) {
    const changed = [];

    for (const [filePath, indexDate] of Object.entries(meta.files)) {
        const currentDate = getFileDate(filePath);

        if (!currentDate) continue; // file deleted or not accessible
        if (currentDate > indexDate) {
            changed.push({ file: filePath, was: indexDate, now: currentDate });
        }
    }

    if (changed.length > 0) {
        stale.push({ module, doc: meta.doc_file, lastDoc: meta.last_documented, changed });
    }
}

// Output
if (jsonFlag) {
    console.log(JSON.stringify(stale, null, 2));
    process.exit(stale.length > 0 ? 1 : 0);
}

if (stale.length === 0) {
    console.log('\n  [ok] All documentation is up to date.\n');
    process.exit(0);
}

console.log(`\n  ${stale.length} module(s) need updating:\n`);

for (const { module, doc, lastDoc, changed } of stale) {
    console.log(`  ${module}  (documented: ${lastDoc})`);
    console.log(`    -> ${doc}`);
    for (const { file, was, now } of changed) {
        console.log(`       ${file}  ${was} -> ${now}`);
    }
    console.log();
}

console.log(`  Run: /doc ${stale.map(s => s.module).join(' ')}\n`);
process.exit(1);
