#!/usr/bin/env node
import { readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const rawVersion = process.argv[2] ?? '';
const version = rawVersion.trim().replace(/^v/i, '');
const versionPattern =
	/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/;

if (rawVersion === '--help' || rawVersion === '-h') {
	process.stdout.write('Usage: npm run version:bump -- <version>\n');
	process.exit(0);
}

if (!versionPattern.test(version)) {
	process.stderr.write(
		'Version must be valid SemVer, for example 0.2.0 or v0.2.0.\n'
	);
	process.exit(1);
}

/**
 * Updates one JSON file and preserve tab indentation.
 */
async function updateJson(relativePath, update) {
	const filePath = resolve(rootDir, relativePath);
	const data = JSON.parse(await readFile(filePath, 'utf8'));
	update(data);
	await writeFile(filePath, `${JSON.stringify(data, null, '\t')}\n`);
}

/**
 * Updates one text file with a caller provided transform.
 */
async function updateText(relativePath, update) {
	const filePath = resolve(rootDir, relativePath);
	const current = await readFile(filePath, 'utf8');
	const next = update(current);

	await writeFile(filePath, next);
}

await updateJson('package.json', (data) => {
	data.version = version;
});

await updateJson('package-lock.json', (data) => {
	data.version = version;

	if (data.packages?.['']) {
		data.packages[''].version = version;
	}
});

await updateJson('blocks/chat-react/block.json', (data) => {
	data.version = version;
});

await updateJson('blocks/chat-interactivity/block.json', (data) => {
	data.version = version;
});

await updateText('wpclaw.php', (current) => {
	const pattern = /^ \* Version: .+$/m;

	if (!pattern.test(current)) {
		throw new Error('Plugin header version was not found in wpclaw.php');
	}

	return current.replace(pattern, ` * Version: ${version}`);
});

process.stdout.write(`Updated WPClaw version to ${version}.\n`);
process.stdout.write(`Create the release tag with: git tag v${version}\n`);
