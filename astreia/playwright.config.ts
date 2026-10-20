import { defineConfig, devices } from '@playwright/test';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

process.env.ASTREIA_E2E_DATABASE ??= join(
    tmpdir(),
    `astreia-e2e-${process.pid}.sqlite`,
);
const testEnvironment = {
    ...process.env,
    APP_ENV: 'testing',
    APP_URL: 'http://127.0.0.1:8017',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: process.env.ASTREIA_E2E_DATABASE,
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'array',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
};

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    globalSetup: './tests/e2e/setup.ts',
    globalTeardown: './tests/e2e/teardown.ts',
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: 'http://127.0.0.1:8017',
        trace: 'on-first-retry',
    },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8017',
        url: 'http://127.0.0.1:8017/login',
        env: testEnvironment,
        reuseExistingServer: false,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
