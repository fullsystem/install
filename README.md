> **Status: entry point only.** The command runs, parses its options and
> prints what it would target. None of the steps below are implemented yet —
> this README describes the shape being built, and the pipeline it documents
> is the one from the npx package it replaces.
>
> There is no tagged release yet, so cpx cannot resolve a stable version.
> Until the first tag, ask for the branch explicitly:
> `cpx fullsystem/install:dev-main`.

> This deletes files. It is built for a project fresh out of `laravel new`,
> where the starter kit frontend is still untouched. On a project with real
> work in it, run `--dry-run` first and read the list.

# fullsystem/install

Replace the Laravel starter kit frontend with your own.

The React starter kit ships a full frontend — components, layouts and pages —
built on Base UI. If you have your own design system, most of that has to go
before yours can go in. The shadcn CLI adds components; nothing removes what
the kit left behind. This does.

```bash
cpx fullsystem/install
```

That installs the `fullsystem/starter` theme. To install a different one:

```bash
cpx fullsystem/install --theme=laravel/starter-kit
```

Runs in the root of a Laravel project. Everything it does is declared by the
theme, so the same command can install any frontend.

## What it does

| step | |
|---|---|
| `fetch` | downloads the theme archive and reads its `schema.json` |
| `composer` | installs the PHP packages the theme declares |
| `npm` | installs the JS packages the theme declares |
| `strip` | removes the starter kit files |
| `shadcn` | runs `shadcn init` and `add` with the declared preset |
| `copy` | copies the theme's files over the project |
| `artisan` | runs the artisan commands the theme declares |
| `verify` | runs the build, rolling back if it fails |

Dependencies install before `strip` because `composer` boots the application
to discover packages, which fails once routes are gone. `copy` runs after
`shadcn` so generated `ui/` components cannot overwrite files shipped by the
theme. `artisan` runs after `copy` because the files it acts on are usually
the ones just copied in.

The whole `schema.json` is validated during `fetch`, before anything is
written. An unlisted artisan command or a path escaping the project root
fails while the project is still untouched.

If the build fails, the project is restored to the commit it was on before
the first step. Nothing is left half-installed.

### How a theme is fetched

A theme is downloaded as an archive, not cloned:

```
https://github.com/<theme>/archive/refs/heads/main.zip
```

Download rather than `git clone` because an HTTP request has somewhere to put
an `Authorization` header and a clone does not — authenticating a clone means
embedding the credential in the URL, where it lands in the shell history and
in the clone's own `.git/config`. Exclusive themes are the reason; for public
themes the difference is invisible.

Two consequences worth knowing:

**Archives are unpacked defensively.** A zip entry can name a path like
`../../etc`, which would write outside the extraction directory. Every entry
is checked before extraction, and an archive with a single bad entry is
rejected whole rather than unpacked halfway.

**The default branch is a moving target.** Today the fetch takes whatever
`main` currently holds, so two people running the same command in the same
week can get different trees. Pinning a theme to a tag is not supported yet.

`git` is still required — the rollback uses it — but it is the *project's*
git, not the theme's. Nothing about the theme touches git anymore.

## Requirements

- A Laravel project with a clean git working tree
- PHP 8.3+ with `ext-zip`
- `git`, `composer`, `node` and `npm` on PATH
- [cpx](https://github.com/laravel/cpx) — `composer global require cpx/cpx`

The clean tree is not optional: it is what makes the rollback possible.

Node is still required. This is a PHP command, but the frontend it installs
is built with the shadcn CLI and verified with `npm run build`.

If you would rather not go through cpx, `composer global require
fullsystem/install` puts the same command on your PATH as `fullsystem`.

## Checks

Four checks run before anything is written.

**laravel-project** — `artisan` and `composer.json` must be present.

**components-directory** — `resources/js/components` must exist. Without the
starter kit frontend there is nothing to replace.

**clean-worktree** — refuses to run with uncommitted changes. Without a
known-good commit there is nothing to roll back to.

**fresh-project** — looks for signs that the project is not a fresh install:
more than one commit, or starter kit pages that are already gone. It is a
heuristic, not a guarantee. A false positive costs one keystroke; a false
negative costs someone's frontend.

The first two describe what the command needs to work at all, and stop the
run. The last two describe risk, so they ask instead: answer no and nothing
happens. With `--no-interaction` — CI, or an agent — there is nobody to ask,
so they stop the run unless `--force` said yes up front.

## Options

| | |
|---|---|
| `--theme=<owner/repo>`, `-t` | Theme to install. Defaults to `fullsystem/starter`. |
| `--dry-run` | Prints the plan without writing anything. |
| `--force`, `-f` | Answers yes to the risk checks up front. |
| `--no-interaction`, `-n` | Never asks. Every question resolves to its default. |

Everything is an option; nothing is positional. A positional argument would
be read as a command name.

Run `--dry-run` before installing an unfamiliar theme — it lists every file
that will be deleted.

## Writing a theme

A `schema.json` in the root and a directory of files:

```
your-theme/
├── schema.json
└── files/
    ├── routes/web.php
    └── resources/js/
        ├── app.tsx
        ├── layouts/
        └── pages/
```

`files/` mirrors the project root, so `files/resources/js/app.tsx` lands at
`resources/js/app.tsx`. No path mapping. Anything outside `files/` — README,
licence, CI config — stays in the theme's repository.

```json
{
  "name": "your/theme",
  "shadcn": {
    "preset": "vega",
    "template": "laravel",
    "components": "all",
    "pointer": true
  },
  "composer": {
    "require": ["laravel/reverb"],
    "require-dev": []
  },
  "npm": {
    "dependencies": ["@laravel/echo-react", "pusher-js"],
    "devDependencies": []
  },
  "artisan": ["wayfinder:generate"],
  "remove": [
    "resources/js/components",
    "resources/js/layouts",
    "resources/js/pages",
    "routes/web.php"
  ],
  "source": "files"
}
```

Every field is optional. A theme that only replaces pages can declare nothing
but `source`, and even that defaults to `files`.

### shadcn

| field | |
|---|---|
| `preset` | A registered name (`vega`, `nova`) or a code generated at [ui.shadcn.com/create](https://ui.shadcn.com/create). |
| `base` | `base`, `radix` or `aria`. Omit it — the preset carries this. |
| `template` | Passed as `-t`. Optional; shadcn detects the framework on its own. |
| `components` | `"all"` or an array of names. |
| `pointer` | `true` or `false` for pointer cursors on buttons. |

An explicit `components` array is usually better than `"all"`. Every
component becomes a real `.tsx` file in the consuming project, linted,
type-checked and reviewed forever. `"all"` is around sixty of them.

The step always passes `-f -y --reinstall`, so it never prompts.

### composer and npm

Package names are validated for format before being passed to the installer.
That prevents argument injection — a "package" named `--ignore-platform-reqs`
would otherwise be read as a flag — but it does not make an unknown package
safe. That trust comes from choosing the theme.

Some packages need more than installation. `laravel/reverb`, for example, has
its own `php artisan reverb:install` that publishes config and writes
environment variables. The schema installs the dependency; finishing the
setup is the user's job.

### artisan

Only these commands are allowed:

```
wayfinder:generate
storage:link
migrate
optimize:clear
```

They run in the project with the same PHP binary that is running the
installer. Flags are checked for shape, so `--force` passes and
`--force=$(whoami)` does not. Anything outside the list fails during `fetch`,
before a single file has been touched.

### remove

Paths are relative to the project root. Absolute paths and anything escaping
the root are rejected, and the whole list is validated before a single file
is deleted. Missing paths are skipped silently.

Whatever you list is removed on top of a small base set the kit always leaves
behind:

```
components.json
pnpm-workspace.yaml
resources/js/hooks/use-mobile.tsx
```

Those three are starter kit artifacts, not design decisions.
`components.json` is rewritten by `shadcn init`. `pnpm-workspace.yaml` ships
even in npm-installed projects and makes the shadcn CLI shell out to pnpm,
which then fails because the kit's `.npmrc` sets `ignore-scripts=true`.
`use-mobile.tsx` collides with the `use-mobile.ts` shadcn generates — two
files, same module, resolved by extension precedence rather than intent.

`resources/js/components/ui` is deliberately not in the base set. It also
holds `icon.tsx` and `placeholder-pattern.tsx`, which ship with Laravel and
are not in the shadcn registry, so `add --all` will not restore them. Declare
it yourself if you want it gone.

Prefer removing directories over individual files. Some kit files depend on
options chosen during `laravel new` — teams, passkeys, two-factor — and won't
exist in every project.

### What survives

`strip` only removes what is declared, so `resources/js/hooks`, `lib`,
`types`, `app.tsx`, `ssr.tsx` and everything under `app/` stay unless you say
otherwise.

That last part matters. If you leave the kit's `app.tsx` in place, it
dictates which layouts must exist — it imports `@/layouts/app-layout`,
`@/layouts/auth-layout`, `@/layouts/settings/layout` and
`@/components/ui/sonner`, and the build fails without them. Ship your own
`app.tsx` (and `ssr.tsx`, which has the same imports) if you want your own
structure.

## Caveats

**Auth pages have an untyped contract with the backend.** Inertia props,
wayfinder route names and validation error keys are agreed by convention, not
by types. If you replace `pages/auth` while keeping Fortify's controllers, a
change on either side compiles fine and fails at runtime. Pin the starter kit
commit you derived from and diff it when the kit updates.

**Private themes are not supported yet.** A theme you cannot reach is a 404
on the archive, and the run stops there. Authenticating for exclusive themes
is what `login` will be for, and the download is what makes it possible.

**Rollback restores tracked files only.** `node_modules` is not tracked by
git, so after a failed run it holds packages the restored `package.json` no
longer declares. Run `npm install` to resynchronise.

**Rollback with `--force` is dangerous.** On a dirty working tree, `git reset
--hard` discards uncommitted work along with everything this command wrote.
The clean-worktree check exists for exactly this reason.

**cpx caches the installer.** A run reuses the version it already has until
its update check fires. Run `cpx update` to force it, or pin explicitly with
`cpx fullsystem/install:^1.0`.

**npm only.** The starter kit installs with npm and ships
`package-lock.json`. Other package managers are not detected or supported.

## Contributing

```bash
git clone git@github.com:fullsystem/install.git
cd install
composer install
```

PHP 8.3+ and Composer are all you need to work on the command itself. Node is
only required to run it against a real project.

### Running it

The command acts on the directory it is called from, not the one it lives in.
So point it at a throwaway Laravel project rather than at the package:

```bash
cd ~/some/laravel-project && php ~/code/fullsystem/install/bin/fullsystem
```

Testing through `cpx` is not useful during development: cpx installs the
published package from Packagist into its own directory, so it will not see
your changes. Call `bin/fullsystem` directly.

Make that throwaway project a real one — `laravel new` with the React starter
kit, committed once. The checks are written against exactly that shape, and a
bare directory will not exercise them.

### Checks

```bash
composer test
```

Runs the three below in order and fails on the first one that complains.

| | |
|---|---|
| `composer analyse` | PHPStan, level 6, over `src` |
| `composer lint` | Pint, applies the fixes |
| `composer lint:check` | Pint, fails instead of fixing — this is what CI runs |
| `composer test:unit` | Pest |

A single test while you work on it:

```bash
vendor/bin/pest --filter="init alias"
```

### Layout

```
bin/fullsystem          the executable cpx resolves from composer.json "bin"
src/Application.php     Symfony Console app; `install` is the default command
src/Commands/           the command itself
tests/Pest.php          cli() helper — drives the app the way cpx does
```

Tests go through `Application`, not through the command class, so the default
command resolution and the `init` alias stay covered.

### Conventions

Strict types everywhere, `final` by default, and Pint's Laravel preset. Do not
override `ordered_imports` in `pint.json` — combined with the preset's
`blank_line_between_import_groups` it never converges, and `composer test`
fails on a file Pint just formatted.

## License

MIT
