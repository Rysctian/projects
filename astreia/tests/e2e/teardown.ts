import { existsSync, unlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, dirname, resolve } from 'node:path';

export default function teardown() {
    const database = process.env.ASTREIA_E2E_DATABASE;
    if (
        database &&
        resolve(dirname(database)) === resolve(tmpdir()) &&
        /^astreia-e2e-\d+\.sqlite$/.test(basename(database)) &&
        existsSync(database)
    ) {
        unlinkSync(database);
    }
}
