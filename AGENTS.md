# fullsystem/install

A command that takes a Laravel project and replaces its frontend with a recipe.

A recipe is a repository with two parts: a directory of files mirroring the
project root, and a `schema.json` declaring what has to happen for those files
to work — packages to install, files to remove, commands to run. This package
runs that.

It deletes files, rewrites migrations and runs processes. All of it happens on
a branch, and any failure puts the project back where it started. Nothing is
ever pushed.

The default recipe it installs,
[`fullsystem/starter`](https://github.com/fullsystem/starter), lives in a
separate repository and documents itself there — questions about what a recipe
contains, rather than what the command does with one, are answered by its
README and its `AGENTS.md`.

The two are coupled only at runtime, over an HTTP download: no symlink, no path
repository, no local wiring. Editing a local checkout of the recipe does **not**
affect a local run; the installer fetches whatever `main` holds on GitHub.

## Where the documentation lives

`README.md` is for humans: what this project is, and how it works from the
outside. It sells the thing. It does not explain how to build on it.

This file is the other half: how the code is put together, what may not be
broken, and how to contribute. Everyone contributing is assumed to be doing so
with an assistant, and this is what the assistant reads — so guidance about
writing code belongs here, not in the README.

`SKILL.md` is the third audience: an assistant pointed at this repository by
URL, with nothing else read, asked to set someone up with a new recipe project.
It covers exactly that — prerequisites, downloading the boilerplate, extracting
it, pointing it at the right repository, proving it runs — and then hands over
to the `AGENTS.md` that came with the boilerplate.

**It is not documentation of the recipe format, and must not become it.** The
temptation is obvious: the format is defined by this package, so it looks like
it belongs in this package's skill. But the format is what someone needs while
*writing* a recipe, which is after the handoff, in a project that ships its own
documentation. A copy here would be a second source of truth read by something
that acts without checking.

`SKILL.md` changes when the way a recipe project is *obtained* changes — the
boilerplate it downloads, what has to be installed first, what gets rewritten
after extraction. Not when the format changes.

`.ai/` in the repository root holds documentation too long for this file —
design notes, decisions, anything that needs room. Read what is in there before
assuming this file is everything. It is empty for now: the directory is the
convention, not a promise that it has been filled in.

## The execution line

```
resolve the directory   →  creates only the last level; ./a/b/c with no `a` is a typo
detect the driver       →  laravel-react, or an empty directory to start one in
fetch the recipe        →  zip from GitHub, unpack, read the schema
Plan                    →  validate the WHOLE schema against what the driver knows
checks                  →  the driver's own, plus the ones the recipe asked for
workspace               →  mark fullsystem/pre-install, create feat/fullsystem-install
pre-install             →  what the recipe declared
install                 →  copy the recipe's src/ over the project
post-install            →  what the recipe declared
verify                  →  lint, build, test
commit, then ask        →  apply to the origin branch, or stay on this one
```

Entry point: [bin/fullsystem](bin/fullsystem) →
[src/Application.php](src/Application.php) →
[InstallCommand::__invoke()](src/Commands/InstallCommand.php).

`Application::route()` decides whether the first token is a command name or the
installer's `<path>` argument — Symfony reads the first argument as a command
name unconditionally, so that choice has to be made before it sees the input.

## Layout

```
.ai/                    longer-form documentation, when there is any
bin/fullsystem          the executable cpx resolves from composer.json "bin"
src/Application.php     routes the first token: a command, or a path
src/Commands/           the command
src/Drivers/            what a kind of project is, and how to work with it
src/Actions/            what a recipe can declare, one handler per action
src/Checks/             what has to be true before anything is written
src/Install/            copying the recipe in, and proving the result works
src/Recipes/            downloading and unpacking a recipe
src/Workspace.php       the branch, and how to get back
tests/fixtures/         the reference schema
```

## Invariants — breaking any of these is a regression

**Phase order belongs to the driver, not the recipe.** A recipe declares
`pre-install` and `post-install` and nothing else. Copying the files and
verifying the result are the driver's. A recipe that could reorder them could
put the copy before the deletions that clear the way for it. See
[Plan::PHASES](src/Actions/Plan.php) and the reasoning in
[Driver](src/Drivers/Driver.php).

**The whole schema is validated before the first action runs.** `Plan::from()`
refuses the schema entirely if any action is unknown. Being told at action four
that action five does not exist would leave the project half-prepared. The same
rule applies inside [Remove](src/Actions/Remove.php): the full list is checked
before a single path goes.

**Nothing in `src/` runs a process directly.** Every handler that shells out
takes a [ProcessRunner](src/Support/ProcessRunner.php) through its constructor.
That is what keeps the suite from installing packages on whoever runs it, and
it is what `FakeProcess` replaces.

**Commands are argument lists, never strings.** Nothing reaches a shell, so
`migrate; rm -rf /` arrives at artisan as one literal argument and dies there.

**Every value from a recipe is `mixed` until proven otherwise.** The schema is
JSON from someone else's repository. [Parameters](src/Actions/Parameters.php)
exists for this: `stringList`, `options`, `rejecting`. A handler that assumes a
shape breaks on the first recipe that typed a string where a list belonged.

**Every path from a recipe goes through [SafePath](src/Support/SafePath.php).**
Archive entries, `remove` entries, the schema's `source`. An archive with one
bad entry is refused whole rather than unpacked halfway.

**Package names are checked for shape.** Not against a shell — there is none —
but against a "package" called `--ignore-platform-reqs`, which composer would
obey as a flag.

**Nothing is ever pushed.** `Workspace` has no push path. The rollback is local.

**A failure always carries a reason.** [Result](src/Result.php) has no way to
`fail()` without one — it is the only thing the user sees when the run stops.

## Extending it

**A new action** is one file in `src/Actions/` implementing
[Handler](src/Actions/Handler.php).
[ActionRegistry](src/Actions/ActionRegistry.php) discovers handlers by
interface, not by filename — which is why `Action.php` and `Plan.php` can live
in the same directory without being mistaken for handlers. After that, the
driver still has to list the action in `actions()`, or no recipe can declare it.

**A new driver** implements [Driver](src/Drivers/Driver.php) and is registered
in `DriverRegistry::default()`. A driver that cannot execute an action has to
**narrow** what it takes from `ActionRegistry::names()` — `laravel-vue` has no
shadcn to run, and accepting a schema that declares shadcn would hand back a
project missing the components the recipe assumed.

**A new check** implements [Check](src/Checks/Check.php). The driver's
`checks()` run for every recipe; `optionalChecks()` run only when a recipe names
them in `requires`. `forceable()` decides whether a failure is a verdict or a
question — false for what the command needs to work at all, true for what
describes risk.

## The schema format

What the parser has to accept, summarised. The documentation a recipe author
reads lives in the [fullsystem/starter](https://github.com/fullsystem/starter)
README — a change to the format has to reach it too.

```json
{
  "name": "acme/dashboard",
  "version": "1.0.0",
  "driver": "laravel-react",
  "requires": ["fresh-project"],
  "phases": {
    "pre-install": [
      { "composer": ["laravel/reverb"] },
      { "composer": ["pestphp/pest"], "dev": true },
      { "packages": ["date-fns"] },
      { "remove": ["resources/js/pages"] },
      { "shadcn": { "preset": "vega", "components": "all", "pointer": true } }
    ],
    "post-install": [
      { "artisan": ["wayfinder:generate --with-form"] }
    ]
  }
}
```

A phase is a **list**, so the same action can appear more than once and the
order is the order it was written. Each item names exactly one action; every
other key is a modifier of it, which is what makes `{ "composer": [...], "dev":
true }` read the way it does.

| action | parameters | what it does |
|---|---|---|
| `composer` | list of packages | `composer require`, with `"dev": true` for require-dev |
| `packages` | list of packages | JS dependencies, with the manager the lockfile implies — npm, pnpm, yarn or bun |
| `remove` | list of paths | deletes them, relative to the project root |
| `shadcn` | `preset`, `base`, `template`, `components`, `pointer` | `shadcn init` then `add` |
| `artisan` | list of commands | `php artisan …` |

`db:wipe`, `migrate:fresh`, `migrate:reset` and `migrate:rollback` are refused
outright. That is a denylist on purpose: an allowlist also blocked
`reverb:install` and every other legitimate `*:install`, and would never keep up
with the ecosystem.

## Running and testing

PHP 8.3+ and Composer are all that is needed to work on the command itself.
Node is only required to run it against a real project.

```bash
composer install
```

```bash
composer test
```

Runs the three below in order, and is exactly what CI runs, on PHP 8.3, 8.4 and
8.5. As of 2026-08-13: **223 tests passing**.

| | |
|---|---|
| `composer analyse` | PHPStan, level 6, over `src` |
| `composer lint` | Pint, applies the fixes |
| `composer lint:check` | Pint, fails instead of fixing |
| `composer test:unit` | Pest |

A single test while working on it:

```bash
vendor/bin/pest --filter="rolls the project back"
```

The command acts on the project it is given, not on the package it lives in, so
run it from a clone against a throwaway project — never against this repository:

```bash
php bin/fullsystem ../throwaway-app --dry-run
```

Read the dry run before dropping `--dry-run`. Without it the command deletes
files and runs installers, and while a failure rolls the project back, a
throwaway project is still the only sensible target.

Testing through `cpx` while developing is not useful: cpx installs the
published package into its own directory and will not see local changes.

**The tests never touch the network.** `cli()` in [tests/Pest.php](tests/Pest.php)
builds the Application with `FakeRecipeSource` and `FakeProcess`. A new test
that needs the network or a real process is doing the wrong thing.

`tests/fixtures/schema.json` is the reference example of the format — what a
recipe author copies to start from. [tests/SchemaTest.php](tests/SchemaTest.php)
keeps it honest: it has to parse, use only actions the driver knows, and
exercise every one of them. It is deliberately a superset of the starter's own
schema; the two do not have to match.

## Conventions

- `declare(strict_types=1)` everywhere, `final` by default, `readonly` where it fits
- Pint's Laravel preset, plus `declare_strict_types` and `strict_comparison`
- **Do not override `ordered_imports` in `pint.json`.** Combined with the
  preset's `blank_line_between_import_groups` it never converges, and
  `composer test` fails on a file Pint has just formatted.
- PHPStan level 6, with generics in docblocks (`list<string>`,
  `array{label: string, command: list<string>}`)

**The comment style is part of the project.** The docblocks here explain *why* a
decision is what it is, usually by naming the alternative that was rejected and
what it cost. See [Artisan](src/Actions/Artisan.php) on denylist vs allowlist,
or [FreshProject](src/Checks/FreshProject.php) on false positives vs false
negatives. New code that does not do this reads as foreign. New code that does
it without having a real reason reads worse.

## Known weak spots

Documented in the [README](README.md#caveats), repeated here because they are
the kind of thing a fresh session reintroduces:

- **A recipe deleting what it does not replace.** `remove` and the recipe's files
  are not checked against each other. Verification catches it and the rollback
  undoes it, but only after the whole run.
- **Auth pages have an untyped contract with the backend.** Inertia props,
  wayfinder route names and validation error keys are agreed by convention, not
  by types. Replacing `pages/auth` while keeping Fortify's controllers compiles
  fine and fails at runtime.
- **Private recipes are not supported yet.** A recipe that cannot be reached is a
  404 on the archive.
- **The default branch is a moving target.** The fetch takes whatever `main`
  holds right now; pinning a recipe to a tag is not supported.

The security boundary is in [SECURITY.md](SECURITY.md): none of this protects
against a recipe that should not have been trusted. A recipe that can add a
Composer package can already run code. The guards are there to stop an
accident, not an attacker.
