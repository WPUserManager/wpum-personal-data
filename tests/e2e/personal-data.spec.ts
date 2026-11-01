import {
  test,
  expect,
  member,
  createMember,
  createRequest,
  lockWpLogin,
  exportedValues,
  erase,
  loginAs,
  wpAdminLogin,
  submitExportForm,
  submitErasureForm,
  EXPORT_FORM,
  ERASURE_FORM,
  PASSWORD,
} from './fixtures';

const CONFIRMED = 'Your request has been successfully confirmed.';

test.describe('The Personal Data tab', () => {
  test('a visitor is sent to the login page and never sees the forms', async ({ page, accountPage }) => {
    await page.goto(`${accountPage}personal-data`);

    await expect(page).toHaveURL(/\/wpum-login\//);
    await expect(page.locator(EXPORT_FORM)).toHaveCount(0);
    await expect(page.locator(ERASURE_FORM)).toHaveCount(0);
  });

  test('a logged-in member gets the tab, and it opens both forms', async ({ page, accountPage }) => {
    createMember('pd_tab');
    await loginAs(page, 'pd_tab');

    await page.goto(accountPage);
    await page.locator('.wpum-account-navigation a.tab-personal-data').click();

    await expect(page).toHaveURL(/\/wpum-account\/personal-data/);
    await expect(page.locator(EXPORT_FORM)).toBeVisible();
    await expect(page.locator(ERASURE_FORM)).toBeVisible();
    await expect(page.locator(`${EXPORT_FORM} input[name="password"]`)).toHaveAttribute('type', 'password');
    await expect(page.locator(`${ERASURE_FORM} input[name="password_erasure"]`)).toHaveAttribute('type', 'password');
  });
});

test.describe('Asking for an export', () => {
  test('the right password files a request and says an email is on its way', async ({ page, accountPage }) => {
    createMember('pd_export');
    await loginAs(page, 'pd_export');

    await page.goto(`${accountPage}personal-data`);
    await submitExportForm(page, PASSWORD);

    await expect(page.locator('.wpum-message.success')).toContainText('A confirmation email has been sent to pd_export@example.com');
    await expect(page.locator(ERASURE_FORM)).toBeVisible();

    expect(member('pd_export').requests).toMatchObject([{ action: 'export_personal_data', status: 'request-pending' }]);
  });

  test('a wrong password files nothing', async ({ page, accountPage }) => {
    createMember('pd_wrong');
    await loginAs(page, 'pd_wrong');

    await page.goto(`${accountPage}personal-data`);
    await submitExportForm(page, 'NotMyP@ssw0rd!');

    await expect(page.locator('.wpum-message.error')).toContainText('The password you entered is incorrect.');

    expect(member('pd_wrong').requests).toEqual([]);
  });

  test('the request reaches the administrator\'s list', async ({ page, accountPage }) => {
    createMember('pd_listed');
    await loginAs(page, 'pd_listed');

    await page.goto(`${accountPage}personal-data`);
    await submitExportForm(page, PASSWORD);

    await page.context().clearCookies();
    await wpAdminLogin(page);
    await page.goto('/wp-admin/export-personal-data.php');

    await expect(page.locator('.wp-list-table').getByText('pd_listed@example.com').first()).toBeVisible();
  });
});

test.describe('Asking for an erasure', () => {
  test('the right password files a request and erases nothing yet', async ({ page, accountPage }) => {
    createMember('pd_erasure');
    await loginAs(page, 'pd_erasure');

    await page.goto(`${accountPage}personal-data`);
    await submitErasureForm(page, PASSWORD);

    await expect(page.locator('.wpum-message.success')).toContainText('A confirmation email has been sent to pd_erasure@example.com');
    await expect(page.locator(EXPORT_FORM)).toBeVisible();

    const { requests, phone } = member('pd_erasure');

    expect(requests).toMatchObject([{ action: 'remove_personal_data', status: 'request-pending' }]);
    expect(phone).toBe('Phone of pd_erasure');
  });

  test('a wrong password files nothing', async ({ page, accountPage }) => {
    createMember('pd_wrong_erasure');
    await loginAs(page, 'pd_wrong_erasure');

    await page.goto(`${accountPage}personal-data`);
    await submitErasureForm(page, 'NotMyP@ssw0rd!');

    await expect(page.locator('.wpum-message.error')).toContainText('The password you entered is incorrect.');

    expect(member('pd_wrong_erasure').requests).toEqual([]);
  });
});

test.describe('The link in the email, with wp-login.php locked', () => {
  test.afterEach(() => {
    lockWpLogin('off');
  });

  test('it confirms the request and comes back to the tab', async ({ page, loginPage }) => {
    createMember('pd_link');
    const request = createRequest('pd_link', 'export_personal_data');

    // Logging in goes through wp-login.php, so it comes before the lock.
    await loginAs(page, 'pd_link');
    lockWpLogin('on');

    await page.goto(`${loginPage}?action=confirmaction&request_id=${request.id}&confirm_key=${request.key}`);

    await expect(page).toHaveURL(/\/wpum-account\/personal-data/);
    await expect(page.locator('.wpum-message.success')).toContainText(CONFIRMED);

    expect(member('pd_link').requests).toMatchObject([{ id: request.id, status: 'request-confirmed' }]);
  });

  test('a wrong key confirms nothing', async ({ page, loginPage }) => {
    createMember('pd_bad_link');
    const request = createRequest('pd_bad_link', 'remove_personal_data');

    lockWpLogin('on');

    await page.goto(`${loginPage}?action=confirmaction&request_id=${request.id}&confirm_key=NotTheKey1234567890`);

    await expect(page.locator('.wpum-message.success')).toHaveCount(0);

    expect(member('pd_bad_link').requests).toMatchObject([{ id: request.id, status: 'request-pending' }]);
  });
});

test.describe('The administrator runs the request', () => {
  test('an export holds the member\'s values and nobody else\'s', () => {
    createMember('pd_mine');
    createMember('pd_theirs');

    const values = exportedValues('pd_mine');

    expect(values).toContain('Phone of pd_mine');
    expect(values).not.toContain('Phone of pd_theirs');
  });

  test('an erasure removes the member\'s values and leaves everyone else\'s', async ({ page, accountPage }) => {
    createMember('pd_gone');
    createMember('pd_stays');

    const result = erase('pd_gone');

    expect(result.items_removed).toBe(true);
    expect(result.items_retained).toBe(false);
    expect(member('pd_gone').phone).toBe('');
    expect(member('pd_stays').phone).toBe('Phone of pd_stays');

    // The account is still there, and so is its login.
    await loginAs(page, 'pd_gone');
    await page.goto(`${accountPage}personal-data`);
    await expect(page.locator(EXPORT_FORM)).toBeVisible();
  });
});

test.describe('Members can\'t run a request themselves', () => {
  for (const [action, name] of [
    ['wp-privacy-export-personal-data', 'export'],
    ['wp-privacy-erase-personal-data', 'erasure'],
  ]) {
    test(`admin-ajax refuses another member's ${name}`, async ({ page }) => {
      createMember('pd_victim');
      const request = createRequest('pd_victim', name === 'export' ? 'export_personal_data' : 'remove_personal_data');

      createMember('pd_attacker');
      await loginAs(page, 'pd_attacker');

      const response = await page.request.post('/wp-admin/admin-ajax.php', {
        form: { action, id: String(request.id), exporter: '1', eraser: '1', page: '1', security: 'forged', sendAsEmail: 'true' },
      });

      expect(await response.text()).not.toContain('Phone of pd_victim');
      expect(member('pd_victim').phone).toBe('Phone of pd_victim');
      expect(member('pd_victim').requests).toMatchObject([{ id: request.id, status: 'request-pending' }]);
    });
  }
});
