import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';

const budgets = {
	'build/runtime.js': 2 * 1024,
	'build/editor.js': 30 * 1024,
};

let failed = false;

for ( const [ file, limit ] of Object.entries( budgets ) ) {
	let size;

	try {
		size = gzipSync( readFileSync( file ), { level: 9 } ).length;
	} catch ( error ) {
		console.error( `${ file }: missing, run \`npm run build\` first (${ error.code })` );
		failed = true;
		continue;
	}

	const verdict = size <= limit ? 'ok' : 'OVER BUDGET';
	console.log( `${ file }: ${ size } B gzipped, budget ${ limit } B, ${ verdict }` );
	failed ||= size > limit;
}

process.exit( failed ? 1 : 0 );
