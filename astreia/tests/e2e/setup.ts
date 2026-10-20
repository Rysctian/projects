import { closeSync, openSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { dirname, basename, resolve } from 'node:path';

export default function setup() {
    const database = process.env.ASTREIA_E2E_DATABASE;
    if (
        !database ||
        resolve(dirname(database)) !== resolve(tmpdir()) ||
        !/^astreia-e2e-\d+\.sqlite$/.test(basename(database))
    ) {
        throw new Error(
            'Browser tests require their own temporary SQLite database.',
        );
    }
    closeSync(openSync(database, 'wx'));
    const env = {
        ...process.env,
        APP_ENV: 'testing',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: database,
        MAIL_MAILER: 'array',
        CACHE_STORE: 'array',
        SESSION_DRIVER: 'file',
    };
    for (const args of [
        ['artisan', 'migrate', '--force', '--no-interaction'],
        ['tests/e2e/prepare.php'],
    ]) {
        const result = spawnSync('php', args, { env, encoding: 'utf8' });
        if (result.status !== 0) {
            throw new Error(
                result.stderr ||
                    result.stdout ||
                    'Browser fixture preparation failed.',
            );
        }
    }
}
