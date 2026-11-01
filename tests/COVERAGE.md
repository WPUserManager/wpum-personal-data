# Test coverage

Every feature the addon has, and which suite covers it. Update this file in the PR that
adds or changes a test. A feature with no test is a gap, not an oversight to hide.

- **WPUnit**: `tests/wpunit`, Codeception in wp-env, with WP User Manager from its default branch.
- **E2E**: `tests/e2e`, Playwright on the wp-env site (default theme, pretty permalinks). Setup is
  `wp wpumpd-e2e` in `tests/mu-plugins/wpumpd-e2e.php`, which creates the field and members the
  specs use and drops mail, as wp-env has no mail server.
- **Custom fields**: the Custom Fields addon is private, so this repo's CI can't check it out.
  Its fields reach the exporter and eraser through Carbon Fields, and both suites register
  test fields the same way, with the datastore it uses and the Carbon Fields type it picks for
  each WPUM field type. If that addon changes how it registers fields, these tests won't notice.
- Status: ✅ covered, ❌ not covered, 🐞 `known-bug` test that fails until the bug is fixed.
  A blank cell means the other suite covers it.

## Known bugs

Tests for open bugs are tagged `known-bug` (WPUnit `@group known-bug`, Playwright
`@known-bug`), and CI skips them. The fix for a bug removes the tag from its tests. To run
them: `codecept run wpunit -g known-bug` or `npx playwright test --grep @known-bug`.
Not every `known-bug` test is listed here.

| Bug | Test |
|---|---|
| A User or Role field allowing several values, a Taxonomy field or a Repeater is exported as an array, which WordPress can't write to the export file | `ExporterTest::test_a_field_with_several_values_exports_as_text` |
| A dropdown or radio the member left empty is exported as its first option: Carbon Fields answers with that for a select with no value | `ExporterTest::test_a_dropdown_the_member_left_empty_is_left_out` |
| The same dropdown makes a clean erasure report that a value couldn't be removed | `EraserTest::test_a_dropdown_the_member_left_empty_is_not_reported_as_kept` |
| With the whole site locked and wp-login.php not, the email's link goes to WPUM's login page, where nothing handles it, so the request can't be confirmed | `ConfirmationTest::test_the_link_works_when_the_whole_site_is_locked` |
| `user_request_action_confirmed` doesn't fire when the addon confirms a request | `ConfirmationTest::test_other_plugins_hear_about_the_confirmation` |
| When the confirmation email can't be sent the member is told it was, and the open request refuses their next try | `RequestTest::test_a_mail_failure_is_reported_to_the_member` |
| `?step=1` shows "A confirmation email has been sent" with no request filed | `RequestTest::test_the_confirmation_step_needs_a_request` |
| The text domain never loads: the `plugins_loaded` hook at priority 0 is added while priority 0 is already running | `SmokeTest::test_the_text_domain_loads_while_wordpress_boots` |

Behaviour that's pinned by a passing test but may not be what's wanted:

- **The addon doesn't handle export files.** WordPress writes them, stores them and emails the
  link, and the addon adds no filter on any of that (`ExporterTest`).
- **Custom fields are what this addon handles.** Name, email, bio and the like are left to
  WordPress, which doesn't erase them (`EraserTest`).
- **An erasure leaves the account**, its role and its login (`EraserTest`).
- **A wrong, used or expired link does nothing visible** on the front end: the visitor just gets
  the login page. wp-login.php shows an error (`ConfirmationTest`).

Not tested anywhere:

- **Multisite.** Neither suite runs on it.
- **The real Custom Fields addon**, see above.
- **Repeaters**, as rows. A repeater's value is removed along with its key, and the export
  bug above covers its output through a field with several values.
- **WordPress's own export run**: the admin's Send export button, the zip and the email.
  The E2E suite runs the addon's exporter and eraser through WP-CLI.
- **The missing vendor directory notice** (`vendor_failed_notice`), which deactivates the plugin.
- **Theme overrides** of `forms/personal-data-form.php` and `forms/data-erasure-form.php`.

## Loading and release

| Feature | WPUnit | E2E |
|---|---|---|
| Starts on WPUM's `after_wpum_init`, defines its constants | ✅ | |
| Version is the same in the plugin header, `$version`, `readme.txt` Stable tag and `package.json`, and is in the changelog | ✅ | |
| `release.json` deploys to EDD item 25551; its version check finds the version | ✅ | |
| Requirements checks pass | ✅ | |
| The `.pot` file ships with the addon | ✅ | |
| Text domain loads | 🐞 | |

## The account tab

| Feature | WPUnit | E2E |
|---|---|---|
| Personal Data tab registered after Settings; core tabs kept | ✅ | |
| Logged-in members see the tab, and it opens both forms | ✅ | ✅ |
| The forms are only on their own tab | ✅ | |
| Visitors are sent from the account page to the login page and never see the forms | ✅ | ✅ |
| Visitors never get a form, wherever `[wpum_account]` is | ✅ | |
| The "request confirmed" message, only for `user_action=confirmed` | ✅ | ✅ |
| WPUM loads the addon's form files, and only for these forms | ✅ | |
| The addon's templates are on WPUM's template path | ✅ | |

## The two forms

Each row is tested on both the export and the erasure form.

| Feature | WPUnit | E2E |
|---|---|---|
| A submit step and a confirmation step, and only the first has a handler | ✅ | |
| Asks for the current password: required, a password input, empty | ✅ | ✅ |
| Heading and button name the form | ✅ | |
| Posts with the nonce, valid for the current member | ✅ | |
| Posts nothing that identifies a user | ✅ | |
| Translated labels escaped | ✅ | |
| The hooks before and after the form fire | ✅ | |
| The fields filter adds fields, and a required one is enforced | ✅ | |
| Processes on the `wp` hook; WPUM loads the form when it's posted | ✅ | |

## Filing a request

Each row is tested on both forms unless it says otherwise.

| Feature | WPUnit | E2E |
|---|---|---|
| The right password and nonce file a pending request for the member | ✅ | ✅ |
| The member is emailed a link to confirm it, and nobody else is emailed | ✅ | |
| The confirmation step names their email | ✅ | ✅ |
| The success message filters | ✅ | |
| The other form still shows afterwards | ✅ | ✅ |
| A member can ask for an export and an erasure | ✅ | |
| A second request is refused while the first is open | ✅ | |
| The request reaches the administrator's list (export) | | ✅ |
| A request erases nothing | ✅ | ✅ |
| A password with quotes and a backslash works as it does at login | ✅ | |
| A password hashed by an older WordPress (phpass) is accepted | ✅ | |
| A mail failure is reported | 🐞 | |
| `?step=1` needs a request | 🐞 | |

## What must file nothing

Each row is tested on both forms.

| Feature | WPUnit | E2E |
|---|---|---|
| A wrong password: error shown, nothing filed, nothing emailed | ✅ | ✅ |
| Another member's password, the stored hash, the wrong case | ✅ | |
| An empty password, none at all, one posted as an array | ✅ | |
| No nonce, and forged nonces: empty, made up, `0`, an array, too long | ✅ | |
| The other form's nonce, and a nonce for another action | ✅ | |
| A nonce issued to another member (CSRF) | ✅ | |
| No submit button | ✅ | |
| A later step, or one that doesn't exist | ✅ | |
| A GET request | ✅ | |
| A tampered `user_id`, `ID`, `id`, `user`, `uid`, `author`, `member_id`, `email`, `user_email`, `email_address` or `username_or_email`: only ever the current user's request and email | ✅ | |
| A tampered ID with a wrong password, or with the victim's password | ✅ | |
| An administrator can't file for someone else | ✅ | |
| A visitor with a valid nonce | ✅ | |

## The link in the email

The addon handles the link when wp-login.php is locked. WordPress handles it otherwise.

| Feature | WPUnit | E2E |
|---|---|---|
| Confirms an export or an erasure request and returns to the tab | ✅ | ✅ |
| The link in the email the form sends works | ✅ | |
| The administrator is told, once | ✅ | |
| A wrong or empty key confirms nothing | ✅ | ✅ |
| One member's key doesn't confirm another's request | ✅ | |
| A link works once | ✅ | |
| An expired link confirms nothing | ✅ | |
| Needs the lock on wp-login.php, and the `confirmaction` action | ✅ | |
| Confirming an erasure erases nothing | ✅ | |
| Works with the whole site locked | 🐞 | |
| Fires `user_request_action_confirmed` | 🐞 | |

## The exporter

| Feature | WPUnit | E2E |
|---|---|---|
| Registered with WordPress; other exporters kept | ✅ | |
| An unknown email exports nothing | ✅ | |
| A custom field exports under its name, in the Additional account details group | ✅ | ✅ |
| Only the requested member's values, never another's | ✅ | ✅ |
| Goes by the email, not the logged-in administrator | ✅ | |
| Empty fields are left out | ✅ | |
| A dropdown or radio left empty is left out | 🐞 | |
| Primary fields are left to WordPress | ✅ | |
| Profile picture and cover | ✅ | |
| Checkbox as Yes; dropdown and radio as the label; multiselect and multiple checkboxes as the labels | ✅ | |
| Fields with several values as text | 🐞 | |
| WordPress can write the values, and strips scripts from them | ✅ | |
| WordPress keeps the file and chooses who gets it | ✅ | |
| Members can't run another member's request through admin-ajax | | ✅ |

## The eraser

| Feature | WPUnit | E2E |
|---|---|---|
| Registered with WordPress; other erasers kept | ✅ | |
| An empty or unknown email erases nothing | ✅ | |
| Text, checkbox, dropdown, radio, multiselect and multiple checkbox values | ✅ | ✅ (text) |
| Reports what it removed | ✅ | ✅ |
| A value that can't be removed is reported as kept, with a message | ✅ | |
| Other members keep their values | ✅ | ✅ |
| Goes by the email, not the logged-in administrator | ✅ | |
| The account, role and login stay | ✅ | ✅ |
| Meta that isn't a custom field stays | ✅ | |
| Primary fields stay | ✅ | |
| Nothing stored, nothing reported | ✅ | |
| A dropdown left empty isn't reported as kept | 🐞 | |
| An erased value is gone from the next export | ✅ | |
| Members can't run another member's request through admin-ajax | | ✅ |
