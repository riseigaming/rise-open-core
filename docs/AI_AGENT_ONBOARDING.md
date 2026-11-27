# Developing with AI

An AI assistant working on Rise Open Core should first read [AGENTS.md](../AGENTS.md), the [getting started guide](START_HERE.md), the [distribution terms](../DISTRIBUTION.md), and the relevant source and test files. The source is covered by Apache-2.0. Full modules and the private Nova and ChessBet engines are separate products.

## Example task

> Review validation and idempotency for a Dice roll request. Read the route, controller, service, and relevant tests first. Explain how the user, session, wallet, currency, and request key are bound. If a change is needed, propose the smallest patch and preserve the existing canonical ledger, audit, and authorization controls.

The [HTTP reference](developer/OPENAPI_REFERENCE.md), [integration examples](developer/INTEGRATION_EXAMPLES.md), and [capability map](CAPABILITY_MAP.md) identify starting points.

## Working method

1. Inspect the exact revision and current working tree. Preserve unrelated changes.
2. Find the file, route, parameter, and test that support a claim. Distinguish tests you read from tests you ran.
3. Make a minimal, reversible change. Use relevant tests to check invalid input, unauthorized access, duplicates, and conflicts.
4. If installation or a database is needed, follow the [installation runbook](INSTALL_RUN34.md). Do not connect to another instance, transfer credentials, or bypass guards.
5. Report the changed files, checks run, and remaining limitations. Do not present a local fixture as acceptance of a real provider or real funds.

AI can explain, research, propose code, and summarize authorized operational records. Deterministic server rules retain authority over permissions, money movement, game outcomes, and final financial decisions. Ops Radar is a read model. AI interpretation confers no additional write permissions.

Keep secrets and customer data out of prompts, chats, and reports. Report security findings through the [private disclosure channel](../SECURITY.md).
