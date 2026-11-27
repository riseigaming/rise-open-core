# First operator walkthrough

After successful installation and the first admin bootstrap, run these **on your own authorized local instance** with synthetic data.

1. Confirm unauthenticated admin access redirects to login, and CSRF-less state-changing POST is rejected.
2. Sign in using the admin credentials created during manual bootstrap (no default password).
3. Change Global site title, menu/content and a supported appearance setting, save, then verify persistence with a new browser session.
4. Confirm the current language and country visibility rules: TR/EN/ES UI selection does not automatically translate every string.
5. Create a synthetic customer. Follow the approved promo and canonical wallet flow; replaying the same promo must not generate a second credit.
6. Inspect one internal game with a supported synthetic balance, then compare its transaction and ledger references with operator evidence.
7. Review Ops Radar. Do not interpret a pending or unknown record as applied money.

Check the default installation before customizing it. After changes, verify persistence by reopening the admin form and viewing the result in a separate guest session.

[Detailed installation runbook](https://github.com/riseigaming/rise-open-core/blob/main/docs/INSTALL_RUN34.md) · [First admin walkthrough](https://github.com/riseigaming/rise-open-core/blob/main/docs/FIRST_HUMAN_TEST.md).
