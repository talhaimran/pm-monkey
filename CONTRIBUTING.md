# Contributing to PmOps

Thanks for helping. A few things to know before you open a pull request.

## Contributor License Agreement

PmOps is licensed under AGPL-3.0-only, and ogmnt.io also offers it as a hosted service. To make that possible, every outside contributor signs the [Contributor License Agreement](CLA.md) once.

When you open your first pull request, a bot comments with a link to the agreement. To sign, reply on the pull request with exactly:

> I have read the CLA Document and I hereby sign the CLA

The check then turns green, and it stays signed for your later pull requests. A pull request is not merged until everyone who has commits in it has signed. If the check looks stuck, comment `recheck`.

## Before you open a pull request

- Read [CLAUDE.md](CLAUDE.md) for the architecture rules and [docs/specs/pmops-spec.md](docs/specs/pmops-spec.md) for what is planned.
- Run the checks: `php artisan test`, `npm test`, `npm run build`, `vendor/bin/pint --test`.
- Do not add dependencies or code whose licence is incompatible with AGPL-3.0.
