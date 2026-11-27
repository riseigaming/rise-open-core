# First admin and CMS use

After completing the [installation runbook](INSTALL_RUN34.md), start in your own isolated local instance. Use sample users and content. You do not need real provider or customer data.

## Check the locked profile

The web guide shows English by default, optional Turkish and Spanish, UTC system time, TRY/USD/EUR fiat wallet assets, and the Normal/VIP/Riskli user classifications. Choose the installation country and currency in the terminal. A user classification grants no permissions, and a language record does not automatically translate all text. Enter the first admin credentials in the terminal.

## Sign in as admin

1. Run `app:install:check` before changing the default content.
2. Open `/cms/admin` and verify that a guest session redirects to sign-in.
3. Sign in with the account created during bootstrap. There is no default password.
4. Save the site title, description, and logo. Refresh the page in a separate guest session to check the result.

## Prepare content

- Save menu destinations for TR/EN and open the links in a separate guest session.
- Upload two sliders, save their order and titles, and check the Global view.
- In **Content Pages**, create a draft with titles and body text in TR/EN/ES. The draft URL should be inaccessible. After publishing, verify that the content opens in the correct language. Check menu visibility again after unpublishing.
- Select a supported accent color preset. Verify that the selection persists after saving and reopening the settings.

## Review members and records

Enable registration only for isolated sample use, create a sample member, and set up a TRY wallet through `/cms/wallet`. Create a promotion code as an authorized admin and redeem it as the member. Redeeming the same code again should produce no additional credit. Compare the transaction and ledger references.

Start with the example in the [Internal Games guide](developer/INTERNAL_GAME_EXTENSION.md) and open the corresponding record in Ops Radar. Treat `pending` and `unknown` as distinct from applied funds.

When reporting an issue, include the revision, OS and runtime, step, expected and observed behavior, and error text with secrets removed. Keep passwords, cookies, tokens, dumps, and customer information private. Use [private disclosure](../SECURITY.md) for security issues.
