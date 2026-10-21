import { expect, test, type Page } from '@playwright/test';

test.beforeEach(async ({ page }) => {
    page.on('pageerror', (error) =>
        console.error('Browser error:', error.message),
    );
    page.on('requestfailed', (request) =>
        console.error(
            'Request failed:',
            request.url(),
            request.failure()?.errorText,
        ),
    );
});

async function login(page: Page) {
    await page.goto('/login');
    await page
        .getByLabel('Email address', { exact: true })
        .fill('admin@astreia.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill('browser-test-password');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).toHaveURL(/\/projects$/);
}

async function createProject(page: Page, code: string) {
    await page.getByRole('link').filter({ hasText: `${code} legacy workspace` }).click();
    await expect(page).toHaveURL(/\/projects\/\d+$/);
    return new URL(page.url()).pathname;
}

async function move(page: Page, stage: string) {
    await page
        .getByRole('button', { name: 'Move ticket', exact: true })
        .click();
    await page.getByLabel('Allowed destination').selectOption({ label: stage });
    await page
        .getByRole('button', { name: 'Move to selected stage', exact: true })
        .click();
    await expect(
        page.getByRole('dialog', { name: 'Move ticket', exact: true }),
    ).not.toBeVisible();
    await expect(page.getByText(stage, { exact: true }).first()).toBeVisible();
}

test('admin manages a real request through assignment, development, QA, closure and reopen', async ({
    page,
}, testInfo) => {
    await login(page);
    const base = await createProject(page, 'E2ECORE');
    await page.getByRole('link', { name: 'Settings', exact: true }).click();
    await page.getByText('Add member', { exact: true }).first().click();
    await page
        .getByLabel('Existing account')
        .selectOption({ label: 'Core Developer · developer@astreia.test' });
    await page.getByLabel('Developer', { exact: true }).check();
    await page.getByRole('button', { name: 'Add member', exact: true }).click();
    await expect(page.locator('#members')).toContainText('Core Developer');
    await page.goto(base);
    await page.getByRole('button', { name: 'New ticket', exact: true }).click();
    await page
        .getByLabel('Title', { exact: true })
        .fill('Correct leave balance');
    await page
        .getByLabel('Description', { exact: true })
        .fill('Count only approved leave in the remaining balance.');
    await page.getByLabel('Category', { exact: true }).selectOption('bug_fix');
    await page
        .getByLabel('Assignee', { exact: true })
        .selectOption({ label: 'Core Developer' });
    await page
        .getByLabel('Acceptance criteria')
        .fill('Approved leave reduces the balance.');
    await page
        .getByRole('button', { name: 'Create ticket', exact: true })
        .click();
    await expect(page).toHaveURL(/\/tickets\/\d+$/);
    await expect(
        page.getByText('E2ECORE-00001', { exact: true }),
    ).toBeVisible();
    await move(page, 'Ready');
    await move(page, 'Development');
    await move(page, 'QA');
    await page
        .getByRole('button', { name: 'Move ticket', exact: true })
        .click();
    await page
        .getByLabel('Allowed destination')
        .selectOption({ label: 'Closed' });
    await page
        .getByLabel('Completion notes')
        .fill('Corrected and tested the leave calculation.');
    await page
        .getByRole('button', { name: 'Close ticket', exact: true })
        .click();
    await expect(
        page.getByText(/passing sign-off|manual QA pass/).first(),
    ).toBeVisible();
    await page
        .getByRole('dialog', { name: 'Move ticket', exact: true })
        .getByRole('button', { name: 'Close', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Record verification', exact: true })
        .click();
    await page
        .getByLabel('Verification notes')
        .fill('Checked approved and pending leave balances.');
    await page
        .getByRole('button', { name: 'Record sign-off', exact: true })
        .click();
    await expect(
        page.getByRole('dialog', { name: 'Record verification', exact: true }),
    ).not.toBeVisible();
    await page
        .getByRole('button', { name: 'Move ticket', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Close ticket', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Completion record', exact: true }),
    ).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('closed-ticket.png'),
        fullPage: true,
    });
    await page
        .getByRole('button', { name: 'Manage ticket', exact: true })
        .click();
    await page
        .getByRole('combobox', { name: 'Action', exact: true })
        .selectOption('reopen');
    await page
        .getByRole('textbox', { name: 'Reason', exact: true })
        .fill('Client found another scenario.');
    await page
        .getByRole('button', { name: 'Apply action', exact: true })
        .click();
    await expect(
        page.getByText('Backlog', { exact: true }).first(),
    ).toBeVisible();
    await expect(
        page.getByText('E2ECORE-00001', { exact: true }),
    ).toBeVisible();
});

test('urgent fieldwork receives a real key and cannot advance until details are completed', async ({
    page,
}) => {
    await login(page);
    await createProject(page, 'E2EFAST');
    await page.getByRole('button', { name: 'New ticket', exact: true }).click();
    await page.getByLabel('Urgent support intake').check();
    await page
        .getByRole('button', { name: 'Issue urgent ticket key', exact: true })
        .click();
    await expect(
        page.getByText('E2EFAST-00001', { exact: true }),
    ).toBeVisible();
    await expect(page.getByText(/Incomplete urgent intake/)).toBeVisible();
    await page
        .getByRole('button', { name: 'Move ticket', exact: true })
        .click();
    await page
        .getByLabel('Allowed destination')
        .selectOption({ label: 'Ready' });
    await page
        .getByRole('button', { name: 'Move to selected stage', exact: true })
        .click();
    await expect(page.getByRole('alert').first()).toContainText(
        'Complete the ticket title and description before advancing.',
    );
    await expect(
        page.getByText('Backlog', { exact: true }).first(),
    ).toBeVisible();
});
