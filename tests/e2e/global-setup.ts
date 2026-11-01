import { wpCli, e2e, ensureHtaccess, setupWpumPages } from './fixtures';

async function globalSetup(): Promise<void> {
  console.log('\n[PD E2E] Running global setup...');

  wpCli('rewrite structure "/%postname%/"');
  ensureHtaccess();

  wpCli('plugin activate wp-user-manager');
  wpCli('plugin activate wpum-personal-data');

  // Notices on screen would break redirects.
  wpCli('config set WP_DEBUG_DISPLAY false --raw');

  setupWpumPages();

  console.log(`[PD E2E] Seeded: ${e2e('setup')}`);

  wpCli('rewrite flush');

  console.log('[PD E2E] Global setup complete!\n');
}

export default globalSetup;
