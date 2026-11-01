import { test as base, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

export const PASSWORD = 'TestPass123!';

export const EXPORT_FORM = '#wpum-submit-personal-data-form';
export const ERASURE_FORM = '#wpum-submit-data-erasure-form';

/**
 * Run a WP-CLI command via wp-env against the test site.
 */
export function wpCli(command: string): string {
  const result = execSync(`npx @wordpress/env run tests-cli wp ${command}`, {
    cwd: process.env.PLUGIN_DIR || process.cwd(),
    encoding: 'utf-8',
    timeout: 60000,
    stdio: ['pipe', 'pipe', 'pipe'],
  });
  return result.trim();
}

/**
 * Run one of the suite's own WP-CLI commands (tests/mu-plugins/wpumpd-e2e.php) and return
 * its last line of output.
 */
export function e2e(command: string): string {
  const output = execSync(`npx @wordpress/env run tests-cli wp wpumpd-e2e ${command}`, {
    cwd: process.env.PLUGIN_DIR || process.cwd(),
    encoding: 'utf-8',
    timeout: 60000,
    stdio: ['pipe', 'pipe', 'pipe'],
  });
  const lines = output.split('\n').map((line) => line.trim()).filter(Boolean);
  return lines.length ? lines[lines.length - 1] : '';
}

export type Member = {
  phone: string;
  requests: { id: number; action: string; status: string }[];
};

/**
 * Create (or recreate) a member with PASSWORD and a value in the custom field.
 */
export function createMember(login: string, role = 'subscriber'): { id: number } {
  return JSON.parse(e2e(`create_member ${login} --role=${role}`));
}

/**
 * A member's custom field and privacy requests.
 */
export function member(login: string): Member {
  return JSON.parse(e2e(`member ${login}`));
}

/**
 * Create a request the way the form does, with the key its email carries.
 */
export function createRequest(login: string, action: string): { id: number; key: string } {
  return JSON.parse(e2e(`request ${login} ${action}`));
}

export function lockWpLogin(state: 'on' | 'off'): void {
  e2e(`lock_wp_login ${state}`);
}

/**
 * Run the addon's exporter for a member, and return every value it exported.
 */
export function exportedValues(login: string): string[] {
  const exported = JSON.parse(e2e(`export ${login}`));
  return exported.data.flatMap((item: { data: { value: string }[] }) => item.data.map((datum) => datum.value));
}

/**
 * Run the addon's eraser for a member.
 */
export function erase(login: string): { items_removed: boolean; items_retained: boolean } {
  return JSON.parse(e2e(`erase ${login}`));
}

/**
 * Log in through wp-login.php. The cookies land in the page's browser context.
 */
export async function loginAs(page: Page, login: string, password = PASSWORD): Promise<void> {
  const response = await page.request.post('/wp-login.php', {
    form: {
      log: login,
      pwd: password,
      'wp-submit': 'Log In',
      redirect_to: '/',
      testcookie: '1',
    },
  });
  await response.dispose();
}

export async function wpAdminLogin(page: Page): Promise<void> {
  await loginAs(page, 'admin', 'password');
}

/**
 * Fill in the export form and press the button.
 */
export async function submitExportForm(page: Page, password: string): Promise<void> {
  await page.locator(`${EXPORT_FORM} input[name="password"]`).fill(password);
  await page.locator('input[name="submit_personal_data"]').click();
  await page.waitForLoadState();
}

/**
 * Fill in the erasure form and press the button.
 */
export async function submitErasureForm(page: Page, password: string): Promise<void> {
  await page.locator(`${ERASURE_FORM} input[name="password_erasure"]`).fill(password);
  await page.locator('input[name="submit_data_erasure"]').click();
  await page.waitForLoadState();
}

/**
 * Ensure .htaccess exists for pretty permalinks.
 */
export function ensureHtaccess(): void {
  wpCli(
    `eval 'file_put_contents(ABSPATH . ".htaccess", "# BEGIN WordPress\\n<IfModule mod_rewrite.c>\\nRewriteEngine On\\nRewriteBase /\\nRewriteRule ^index\\\\.php$ - [L]\\nRewriteCond %{REQUEST_FILENAME} !-f\\nRewriteCond %{REQUEST_FILENAME} !-d\\nRewriteRule . /index.php [L]\\n</IfModule>\\n# END WordPress");'`
  );
}

/**
 * Set up the four core WPUM pages (login, register, account, profile).
 */
export function setupWpumPages(): void {
  const pages = [
    { slug: 'wpum-login', title: 'Login', shortcode: '[wpum_login_form]', option: 'login_page' },
    { slug: 'wpum-register', title: 'Register', shortcode: '[wpum_register]', option: 'registration_page' },
    { slug: 'wpum-account', title: 'Account', shortcode: '[wpum_account]', option: 'account_page' },
    { slug: 'wpum-profile', title: 'Profile', shortcode: '[wpum_profile]', option: 'profile_page' },
  ];

  for (const p of pages) {
    let pageId = wpCli(`post list --post_type=page --name="${p.slug}" --post_status=publish --field=ID`);

    if (!pageId?.match(/^\d+$/)) {
      pageId = wpCli(
        `post create --post_type=page --post_title="${p.title}" --post_name="${p.slug}" --post_status=publish --post_content='${p.shortcode}' --porcelain`
      );
    }

    // Stored as a string, the way WPUM's settings panel saves page IDs.
    wpCli(`eval 'wpum_update_option("${p.option}", array("${pageId.trim()}"));'`);
  }
}

export const test = base.extend<{
  loginPage: string;
  accountPage: string;
}>({
  loginPage: '/wpum-login/',
  accountPage: '/wpum-account/',
});

export { expect };
