import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { readFile } from 'node:fs/promises';

for (const colorScheme of ['light', 'dark']) {
    test(`native forms are accessible and usable in ${colorScheme} mode`, async ({ page }) => {
        await page.emulateMedia({ colorScheme });
        await page.setContent(await readFile(new URL('./artifacts/form.html', import.meta.url), 'utf8'));
        await expect(page.getByRole('textbox', { name: 'Title', exact: true })).toHaveValue('');
        await page.getByRole('textbox', { name: 'Title', exact: true }).fill('A request');
        await expect(page.getByLabel('Date', { exact: true })).toHaveValue('0001-01-01');
        await expect(page.getByLabel('Active', { exact: true })).not.toHaveAttribute('required');
        await expect(page.getByLabel('Enabled', { exact: true })).not.toHaveAttribute('required');
        await page.getByRole('button', { name: 'Submit preference', exact: true }).click();
        await expect(page.locator('#result')).toHaveText('filament submitted');
        await page.getByRole('button', { name: 'Submit request', exact: true }).click();
        await expect(page.locator('#result')).toHaveText('native submitted');
        await page.getByRole('textbox', { name: 'Title', exact: true }).fill('');
        const placeholderContrast = await page.getByRole('textbox', { name: 'Title', exact: true }).evaluate(element => {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 1;
            const context = canvas.getContext('2d');
            const background = getComputedStyle(element).backgroundColor;
            const luminance = color => {
                context.clearRect(0, 0, 1, 1);
                context.fillStyle = background;
                context.fillRect(0, 0, 1, 1);
                context.fillStyle = color;
                context.fillRect(0, 0, 1, 1);
                const channels = Array.from(context.getImageData(0, 0, 1, 1).data).slice(0, 3).map(value => {
                    const channel = value / 255;
                    return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
                });
                return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
            };
            const values = [luminance(background), luminance(getComputedStyle(element, '::placeholder').color)].sort((a, b) => a - b);
            return (values[1] + 0.05) / (values[0] + 0.05);
        });
        expect(placeholderContrast).toBeGreaterThanOrEqual(4.5);
        const accessibility = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
        expect(accessibility.violations).toEqual([]);
        await page.getByRole('textbox', { name: 'Title', exact: true }).focus();
        await page.keyboard.press('Tab');
        await expect(page.getByLabel('Priority', { exact: true })).toBeFocused();
        await expect(page.getByLabel('Priority', { exact: true })).toHaveCSS('outline-style', 'none');
        await expect.poll(() => page.getByLabel('Priority', { exact: true }).evaluate(element => getComputedStyle(element).borderColor)).toContain('0.585');
    });
}
