# Installation and integration troubleshooting

Start by identifying the source revision and runtime environment. Check source integrity against a clean export.

## Source and dependencies

- Identify your source revision with git rev-parse HEAD.
- Compare your PHP and Composer versions with the [installation guide](INSTALL_WIZARD.md).
- Verify SOURCE_SHA256.json in a clean source export. Do not regenerate a mismatched manifest to bypass the check.

## Local database

- Independently verify the MariaDB instance's datadir, port, and server ID.
- Complete OS-protected enrollment and take a real snapshot using the [installation runbook](INSTALL_RUN34.md).
- Record the initial installation check output before customizing CMS settings.

## Integration troubleshooting

Use the [developer HTTP reference](developer/OPENAPI_REFERENCE.md) and [examples](developer/INTEGRATION_EXAMPLES.md) for endpoints and signature fields. In a support ticket, include the source revision, operating system, endpoint tested, and error text with secrets removed.

Report security findings through the [private disclosure channel](../SECURITY.md).
