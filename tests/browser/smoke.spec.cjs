const { test, expect } = require('@playwright/test');

const adminEmail = process.env.PLAYWRIGHT_ADMIN_EMAIL || 'admin@ictfoundation.org.np';
const adminPassword = process.env.PLAYWRIGHT_ADMIN_PASSWORD || 'password';

async function login(page) {
    await page.goto('/admin/login');
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await page.locator('input[type="email"]').fill(adminEmail);
    await page.locator('input[type="password"]').fill(adminPassword);
    await page.getByRole('button', { name: /sign in|login/i }).click();
    await expect(page).not.toHaveURL(/\/admin\/login/);
}

test.describe('public pages', () => {
    test('landing page and registration page render on desktop and mobile', async ({ browser, baseURL }) => {
        const errors = [];
        const desktop = await browser.newPage({ baseURL, viewport: { width: 1440, height: 900 } });
        desktop.on('pageerror', error => errors.push(`desktop: ${error.message}`));

        const landing = await desktop.goto('/');
        expect(landing.status()).toBe(200);
        await expect(desktop.locator('body')).toContainText(/event/i);

        const registrationLink = desktop.locator('a[href*="/event/"][href$="/register"]').first();
        if (await registrationLink.count()) {
            await registrationLink.click();
            await expect(desktop).toHaveURL(/\/event\/[^/]+\/register/);
            await expect(desktop.locator('form')).toBeVisible();
        }

        const mobile = await browser.newPage({ baseURL, viewport: { width: 390, height: 844 } });
        mobile.on('pageerror', error => errors.push(`mobile: ${error.message}`));
        const mobileLanding = await mobile.goto('/');
        expect(mobileLanding.status()).toBe(200);
        await expect(mobile.locator('body')).toBeVisible();

        expect(errors, errors.join('\n')).toEqual([]);
    });

    test('invalid ticket and check-in tokens return safe 404 pages', async ({ request }) => {
        expect((await request.get('/ticket/invalid-token')).status()).toBe(404);
        expect((await request.get('/checkin/t/invalid-token')).status()).toBe(404);
    });

    test('protected reports and labels do not expose data anonymously', async ({ page }) => {
        await page.goto('/labels/1/print');
        await expect(page).toHaveURL(/\/admin\/login/);
        await page.goto('/reports/1/pdf-summary');
        await expect(page).toHaveURL(/\/admin\/login/);
    });
});

test.describe('admin panel', () => {
    test('login and all static admin resource pages render', async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await login(page);

        const paths = [
            '/admin',
            '/admin/events',
            '/admin/events/create',
            '/admin/registrations',
            '/admin/registrations/create',
            '/admin/participant-categories',
            '/admin/participant-categories/create',
            '/admin/scan-action-types',
            '/admin/scan-action-types/create',
            '/admin/payments',
            '/admin/communications',
            '/admin/import-batches',
            '/admin/import-preview',
            '/admin/label-templates',
            '/admin/label-templates/create',
            '/admin/promo-codes',
            '/admin/promo-codes/create',
            '/admin/users',
            '/admin/users/create',
        ];

        for (const path of paths) {
            const response = await page.goto(path);
            expect(response.status(), path).toBeLessThan(400);
            await expect(page.locator('body'), path).toBeVisible();
        }

        expect(errors, errors.join('\n')).toEqual([]);
    });

    test('event-scoped pages and report downloads render', async ({ page }) => {
        await login(page);
        const eventsResponse = await page.request.get('/event-switcher/events');
        expect(eventsResponse.ok()).toBeTruthy();
        const eventsPayload = await eventsResponse.json();
        const event = eventsPayload.data?.[0] || eventsPayload[0];
        test.skip(!event, 'No event available in the current database.');

        const paths = [
            `/admin/onsite-register/${event.id}`,
        ];

        for (const path of paths) {
            const response = await page.goto(path);
            expect(response.status(), path).toBeLessThan(400);
        }

        await page.goto('/admin/events');
        await expect(page.locator('body')).toContainText(`/event/${event.slug}/register`);

        const reportPaths = [
            `/reports/${event.id}/pdf-summary`,
            `/reports/${event.id}/payments`,
            `/reports/${event.id}/scanner-activity`,
            `/reports/${event.id}/category-summary`,
            `/reports/${event.id}/card-delivery`,
        ];

        for (const path of reportPaths) {
            const response = await page.request.get(path);
            expect(response.status(), path).toBeLessThan(400);
            expect(response.headers()['content-disposition'], path).toMatch(/attachment/);
            expect(response.headers()['content-type'], path).toMatch(/(application\/(pdf|csv|vnd)|text\/(plain|csv))/);
        }
    });
});
