const { defineConfig } = require('@playwright/test');
const fs = require('fs');

const chromePath = process.env.CHROME_PATH
    || (fs.existsSync('/usr/bin/google-chrome') ? '/usr/bin/google-chrome' : null);

module.exports = defineConfig({
    testDir: './tests/browser',
    timeout: 30_000,
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000',
        browserName: 'chromium',
        launchOptions: chromePath
            ? { executablePath: chromePath }
            : {},
        headless: true,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    reporter: [['list'], ['html', { outputFolder: 'storage/playwright-report', open: 'never' }]],
});
