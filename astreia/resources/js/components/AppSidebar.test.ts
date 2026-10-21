import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h } from 'vue';
import type { App } from 'vue';
import AppSidebar from './AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';

const page = vi.hoisted(() => ({
    url: '/projects',
    props: {
        auth: {
            canManageUsers: false,
            user: {
                id: 1,
                name: 'Staff',
                email: 'staff@example.test',
                is_system_admin: false,
            },
        },
    },
}));
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    usePage: () => page,
}));

let app: App;
let root: HTMLDivElement;
afterEach(() => {
    app?.unmount();
    root?.remove();
});

describe('Admin Users navigation', () => {
    it.each([
        { canManageUsers: true, isSystemAdmin: false, visible: true },
        { canManageUsers: false, isSystemAdmin: true, visible: false },
    ])(
        'uses the shared capability when canManageUsers=$canManageUsers and is_system_admin=$isSystemAdmin',
        ({ canManageUsers, isSystemAdmin, visible }) => {
            page.props.auth.canManageUsers = canManageUsers;
            page.props.auth.user.is_system_admin = isSystemAdmin;
            root = document.createElement('div');
            document.body.append(root);
            app = createApp({
                render: () =>
                    h(SidebarProvider, {}, { default: () => h(AppSidebar) }),
            });
            app.mount(root);
            expect(root.querySelector('a[href="/admin/users"]') !== null).toBe(
                visible,
            );
            expect(root.querySelector('a[href="/projects"]')).not.toBeNull();
        },
    );
});
