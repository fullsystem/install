# fullsystem/install

Install a theme into a Laravel project.

A theme is a repository: some files that mirror your project root, and a
`schema.json` saying what has to happen for them to work — packages to install,
files to remove, commands to run. This runs it.

```bash
cpx fullsystem/install
```

That installs `fullsystem/starter` into the current directory. To install a
different theme, or somewhere else:

```bash
cpx fullsystem/install ~/code/my-app --theme=acme/dashboard
```

> It deletes files, rewrites migrations and runs commands. Everything happens
> on a branch, and a failure anywhere puts the project back — but read
> `--dry-run` first on a project you care about.

## How a run goes

```
detect the project      →  laravel-react, or an empty directory to start one in
fetch the theme         →  download, unpack, read the schema
checks                  →  what the driver needs, and what the theme requires
branch                  →  feat/fullsystem-install, off where you are
pre-install             →  what the theme declares: packages, removals, shadcn
install                 →  the theme's files land on the project
post-install            →  what the theme declares: artisan, and so on
verify                  →  lint, build, test
commit, then ask        →  apply it to your branch, or keep it on this one
```

Any failure rolls the project back to where it started. Nothing is ever pushed.

### It can start the project too

Point it at an empty directory and it offers to create one first:

```
./app is empty.
A new project has to be created before fullsystem/starter can go in.

Run `composer create-project laravel/react-starter-kit` here? [yes]
```

Which project is not a guess — the theme declares the driver it was written
for, and the driver knows the command. A directory with something else in it
is left alone.

### The branch

The work happens on `feat/fullsystem-install`, branched from wherever you
were, and is committed there. When it finishes you are standing on that
branch with the theme installed, so you can run the app and look before
deciding:

```
Apply it to main? [yes]
```

Saying no keeps the branch and puts you back — `git merge feat/fullsystem-install`
whenever you want. `fullsystem/pre-install` marks the commit you started on,
so `git reset --hard fullsystem/pre-install` undoes everything either way.

A project with no git gets one, with a first commit, because without a commit
there is nothing to go back to.

## Requirements

- PHP 8.3+ with `ext-zip` and `ext-curl`
- `git`, `composer` and Node on PATH
- [cpx](https://github.com/laravel/cpx) — `composer global require cpx/cpx`

Node is still required. This is a PHP command, but the frontend it installs is
built with Node tooling and verified with a real build.

If you would rather not go through cpx, `composer global require
fullsystem/install` puts the same command on your PATH as `fullsystem`.

## Options

| | |
|---|---|
| `<path>` | Directory to install into. Defaults to the current one. Created if its parent exists. |
| `--theme=<owner/repo>`, `-t` | Theme to install. Defaults to `fullsystem/starter`. |
| `--dry-run` | Prints everything that would happen, writes nothing. |
| `--force` | Answers yes to the risk checks up front. |
| `-v` | Lets composer, npm and shadcn write to the terminal. Off by default: their output is kept and printed only if something fails. |
| `--no-interaction`, `-n` | Never asks. Every question resolves to its default. |

## Writing a theme

A `schema.json` in the root, and a directory of files that mirrors the project
root:

```
acme/dashboard
├── schema.json
└── source/
    ├── app/Models/User.php
    ├── resources/js/app.tsx
    └── routes/web.php
```

`source/resources/js/app.tsx` lands at `resources/js/app.tsx`. No path mapping.
Anything outside `source/` — README, licence, CI config — stays in the theme.

```json
{
  "name": "acme/dashboard",
  "version": "1.0.0",
  "driver": "laravel-react",
  "source": "source",
  "requires": ["fresh-project"],
  "phases": {
    "pre-install": [
      { "composer": ["laravel/reverb", "intervention/image"] },
      { "composer": ["pestphp/pest"], "dev": true },
      { "packages": ["@laravel/echo-react", "date-fns"] },
      { "remove": ["resources/js/pages", "routes/web.php"] },
      { "shadcn": { "preset": "vega", "components": "all", "pointer": true } }
    ],
    "post-install": [
      { "artisan": ["wayfinder:generate --with-form"] }
    ]
  }
}
```

`tests/fixtures/schema.json` in this repository is the same thing, kept honest
by tests: it has to parse, use only actions the driver knows, and exercise
every one of them.

### Phases

A phase is a **list**, so the same action can appear more than once and the
order is the order you wrote:

```json
"pre-install": [
  { "remove": ["routes/web.php"] },
  { "composer": ["acme/router"] },
  { "remove": ["config/router.php"] }
]
```

Each item names exactly one action; any other key is a modifier of it, which
is what makes `{ "composer": [...], "dev": true }` read the way it does.

You declare `pre-install` and `post-install`. The two phases in between —
copying `source/` over the project, and verifying the result — belong to the
driver: a theme that could reorder them could put the copy before the
deletions that clear the way for it.

### Actions

| action | parameters | what it does |
|---|---|---|
| `composer` | list of packages | `composer require`, with `"dev": true` for require-dev |
| `packages` | list of packages | installs JS dependencies with whichever manager the project's lockfile says — npm, pnpm, yarn or bun |
| `remove` | list of paths | deletes them, relative to the project root |
| `shadcn` | `preset`, `base`, `template`, `components`, `pointer` | `shadcn init` then `add` |
| `artisan` | list of commands | `php artisan …` |

An action the driver does not know is refused before anything runs, and the
error says what it does know.

### requires

Some conditions belong to the theme rather than the environment. A theme that
rewrites the users migration needs a project nobody has built on; one that
only adds a module would never pass that check and should not be asked to.

| check | fails when |
|---|---|
| `fresh-project` | more than one commit, the starter kit pages are gone, or there are models besides `User` |

A failing check of this kind is a question, not a verdict: it says what it saw
and asks whether to continue. `--force` answers yes in advance, which is also
what happens where there is no terminal to ask in.

### What is not allowed

The installer runs what a theme declares, so what it will not run is worth
knowing:

- **Commands are argument lists, never strings.** Nothing reaches a shell, so
  `migrate; rm -rf /` arrives at artisan as one literal argument and dies
  there.
- **Package names are checked for shape.** Not against a shell — there is
  none — but against a "package" called `--ignore-platform-reqs`, which
  composer would obey as a flag.
- **Paths cannot leave the project.** `remove` entries and archive entries
  alike; an archive with one bad entry is refused whole rather than unpacked
  halfway.
- **Some artisan commands are refused outright**: `db:wipe`, `migrate:fresh`,
  `migrate:reset`, `migrate:rollback`.

None of this protects you from a theme you should not have trusted — see
[SECURITY.md](SECURITY.md) for where that line is.

## Caveats

**A theme deleting what it does not replace.** `remove` and `source/` are not
checked against each other, so a theme that deletes `resources/js/pages`
without shipping pages leaves a project that does not build. The verification
catches it and the rollback undoes it, but only after the whole run.

**Auth pages have an untyped contract with the backend.** Inertia props,
wayfinder route names and validation error keys are agreed by convention, not
by types. Replacing `pages/auth` while keeping Fortify's controllers compiles
fine and fails at runtime.

**Private themes are not supported yet.** A theme you cannot reach is a 404 on
the archive. Authenticating for exclusive themes is what `login` will be for.

**The default branch is a moving target.** The fetch takes whatever `main`
holds right now; pinning a theme to a tag is not supported yet.

**cpx caches the installer.** A run reuses the version it already has until its
update check fires. `cpx update fullsystem/install` forces it.

## Contributing

```bash
git clone git@github.com:fullsystem/install.git
cd install
composer install
```

PHP 8.3+ and Composer are all you need to work on the command itself. Node is
only required to run it against a real project.

### Running it

The command acts on the directory it is called from, not the one it lives in,
so point it at a throwaway project rather than at the package:

```bash
php ~/code/fullsystem/install/bin/fullsystem ~/tmp/app --dry-run
```

Testing through `cpx` is not useful while developing: cpx installs the
published package into its own directory and will not see your changes.

### Checks

```bash
composer test
```

Runs the three below in order, and is exactly what CI runs.

| | |
|---|---|
| `composer analyse` | PHPStan, level 6, over `src` |
| `composer lint` | Pint, applies the fixes |
| `composer lint:check` | Pint, fails instead of fixing |
| `composer test:unit` | Pest |

A single test while you work on it:

```bash
vendor/bin/pest --filter="rolls the project back"
```

### Layout

```
bin/fullsystem          the executable cpx resolves from composer.json "bin"
src/Application.php     routes the first token: a command, or a path
src/Commands/           the command
src/Drivers/            what a kind of project is, and how to work with it
src/Actions/            what a theme can declare, one handler per action
src/Checks/             what has to be true before anything is written
src/Install/            copying the theme in, and proving the result works
src/Themes/             downloading and unpacking a theme
src/Workspace.php       the branch, and how to get back
tests/fixtures/         the reference schema
```

Adding an action is one file in `src/Actions/`: implement `Handler`, and the
registry finds it.

Nothing in `src/` runs a process directly — handlers take a `ProcessRunner`,
which is what keeps the suite from installing packages on whoever runs it.

### Conventions

Strict types everywhere, `final` by default, Pint's Laravel preset. Do not
override `ordered_imports` in `pint.json`: combined with the preset's
`blank_line_between_import_groups` it never converges, and `composer test`
fails on a file Pint just formatted.

## License

MIT
