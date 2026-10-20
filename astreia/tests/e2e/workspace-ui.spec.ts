import { expect, test } from '@playwright/test';

test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page
        .getByLabel('Email address', { exact: true })
        .fill('admin@astreia.test');
    await page
        .getByLabel('Password', { exact: true })
        .fill('browser-test-password');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).toHaveURL(/\/projects$/);
    await page.evaluate(() => document.fonts.ready);
    expect(await page.evaluate(() => document.fonts.check('14px Geist'))).toBe(
        true,
    );
});

test('project dialog restores focus and ticket drawer creates work on the animated board', async ({
    page,
}, testInfo) => {
    const createProject = page.getByRole('button', {
        name: 'New project',
        exact: true,
    });
    await createProject.click();
    const dialog = page.getByRole('dialog', {
        name: 'Create a project',
        exact: true,
    });
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
    await expect(createProject).toBeFocused();
    await createProject.click();
    await dialog.getByLabel('Code').fill('E2EUI');
    await dialog.getByLabel('Name', { exact: true }).fill('Product experience');
    await dialog
        .getByRole('button', { name: 'Create project', exact: true })
        .click();
    await expect(page).toHaveURL(/\/projects\/\d+$/);
    const base = new URL(page.url()).pathname;
    await page.getByRole('button', { name: 'New ticket', exact: true }).click();
    const drawer = page.getByRole('dialog', {
        name: 'New ticket',
        exact: true,
    });
    await expect(drawer).toBeVisible();
    await drawer
        .getByLabel('Title', { exact: true })
        .fill('Polish the project experience');
    await drawer
        .getByLabel('Description', { exact: true })
        .fill('Keep interactions clear and accessible.');
    await drawer
        .getByRole('button', { name: 'Create ticket', exact: true })
        .click();
    await expect(page).toHaveURL(/\/tickets\/\d+$/);
    await page.goto(`${base}?view=board`);
    const board = page.getByRole('region', { name: 'Kanban board' });
    await expect(
        board.getByRole('link', { name: /Polish the project experience/ }),
    ).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('kanban-desktop.png'),
        animations: 'disabled',
        fullPage: true,
    });
    await page.getByRole('button', { name: 'New ticket', exact: true }).click();
    await expect(
        page.getByRole('dialog', { name: 'New ticket', exact: true }),
    ).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('ticket-drawer-desktop.png'),
        animations: 'disabled',
        fullPage: true,
    });
    await page.keyboard.press('Escape');
    await page
        .getByRole('button', { name: 'Switch to dark theme', exact: true })
        .click();
    await page.screenshot({
        path: testInfo.outputPath('kanban-dark.png'),
        animations: 'disabled',
        fullPage: true,
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(
        board.getByRole('link', { name: /Polish the project experience/ }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await page.screenshot({
        path: testInfo.outputPath('kanban-mobile.png'),
        animations: 'disabled',
        fullPage: true,
    });
    await page.getByRole('button', { name: 'New ticket', exact: true }).click();
    const mobileDrawer = page.getByRole('dialog', {
        name: 'New ticket',
        exact: true,
    });
    await expect(mobileDrawer).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('ticket-drawer-mobile.png'),
        animations: 'disabled',
        fullPage: true,
    });
    await page.keyboard.press('Escape');
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.getByRole('button', { name: 'Filters', exact: true }).click();
    const filters = page.getByRole('dialog', {
        name: 'Filter tickets',
        exact: true,
    });
    await filters
        .getByRole('textbox', { name: 'Search tickets' })
        .fill('no-match-for-this-ticket');
    await filters
        .getByRole('button', { name: 'Apply filters', exact: true })
        .click();
    await expect(filters).not.toBeVisible();
    await expect(board.getByText('Polish the project experience')).toHaveCount(
        0,
    );
    await page
        .getByRole('button', { name: 'Clear filters', exact: true })
        .click();
    await expect(
        board.getByRole('link', { name: /Polish the project experience/ }),
    ).toBeVisible();
});

test('a server validation error keeps the dialog open with entered values', async ({
    page,
}) => {
    await page
        .getByRole('button', { name: 'New project', exact: true })
        .click();
    const dialog = page.getByRole('dialog', {
        name: 'Create a project',
        exact: true,
    });
    await dialog.getByLabel('Code').fill('INVALID CODE');
    await dialog
        .getByLabel('Name', { exact: true })
        .fill('Keep this project name');
    await dialog
        .getByRole('button', { name: 'Create project', exact: true })
        .click();
    await expect(dialog.getByRole('alert')).toBeVisible();
    await expect(dialog.getByLabel('Name', { exact: true })).toHaveValue(
        'Keep this project name',
    );
});

test('project settings and workflow changes save from their dialogs and drawers', async ({
    page,
}) => {
    await page
        .getByRole('button', { name: 'New project', exact: true })
        .click();
    const projectDialog = page.getByRole('dialog', {
        name: 'Create a project',
        exact: true,
    });
    await projectDialog.getByLabel('Code').fill('E2ESET');
    await projectDialog
        .getByLabel('Name', { exact: true })
        .fill('Workspace settings');
    await projectDialog
        .getByRole('button', { name: 'Create project', exact: true })
        .click();
    await expect(page).toHaveURL(/\/projects\/\d+$/);
    const base = new URL(page.url()).pathname;
    await page.getByRole('link', { name: 'Settings', exact: true }).click();
    await page
        .getByRole('button', { name: 'Edit project details', exact: true })
        .click();
    const details = page.getByRole('dialog', {
        name: 'Project details',
        exact: true,
    });
    await details.getByLabel('Description').fill('A clearer place to work.');
    await details
        .getByRole('button', { name: 'Save project', exact: true })
        .click();
    await expect(details).not.toBeVisible();
    await expect(page.locator('#details')).toContainText(
        'A clearer place to work.',
    );
    await page.getByRole('button', { name: 'Add module', exact: true }).click();
    const module = page.getByRole('dialog', {
        name: 'Add module',
        exact: true,
    });
    await module.getByLabel('Code').fill('DESIGN');
    await module.getByLabel('Name', { exact: true }).fill('Design system');
    await module
        .getByRole('button', { name: 'Add module', exact: true })
        .click();
    await expect(module).not.toBeVisible();
    await expect(page.locator('#modules')).toContainText('Design system');
    await page.getByRole('button', { name: 'Add label', exact: true }).click();
    const label = page.getByRole('dialog', { name: 'Add label', exact: true });
    await label.getByLabel('Label name').fill('craft');
    await label.getByRole('button', { name: 'Add label', exact: true }).click();
    await expect(label).not.toBeVisible();
    await expect(page.locator('#labels')).toContainText('craft');
    await page
        .getByRole('button', { name: 'Create role', exact: true })
        .click();
    const role = page.getByRole('dialog', { name: 'Create role', exact: true });
    await role.getByLabel('Role name').fill('Design partner');
    await role.getByLabel('project.view', { exact: true }).check();
    await role
        .getByRole('button', { name: 'Create role', exact: true })
        .click();
    await expect(role).not.toBeVisible();
    await expect(page.locator('#roles')).toContainText('Design partner');
    await page.goto(`${base}/workflows`);
    await page
        .getByRole('button', { name: 'Edit workflow', exact: true })
        .click();
    const workflow = page.getByRole('dialog', {
        name: 'Workflow editor',
        exact: true,
    });
    await workflow
        .getByRole('button', { name: 'Save draft', exact: true })
        .click();
    await expect(workflow).not.toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Publish saved draft', exact: true }),
    ).toBeVisible();
});

test('mobile drawer fits the viewport and respects reduced motion', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page
        .getByRole('button', { name: 'New project', exact: true })
        .click();
    const dialog = page.getByRole('dialog', {
        name: 'Create a project',
        exact: true,
    });
    await expect(dialog).toBeVisible();
    const bounds = await dialog.boundingBox();
    expect(bounds).not.toBeNull();
    expect(bounds!.x).toBeGreaterThanOrEqual(0);
    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(391);
    expect(
        await dialog.evaluate(
            (element) => getComputedStyle(element).animationDuration,
        ),
    ).toBe('0.001s');
});
