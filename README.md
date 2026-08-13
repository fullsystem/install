# fullsystem/install

Install a recipe into your project.

A recipe is a repository: some files that mirror your project root, and a
`schema.json` saying what has to happen for them to work — packages to install,
files to remove, commands to run. This runs it.

```bash
cpx fullsystem/install
```

That installs `fullsystem/starter-kit` into the current directory. To install a
different recipe, or somewhere else:

```bash
cpx fullsystem/install ../my-app --recipe=acme/dashboard
```

> It deletes files, rewrites migrations and runs commands. Everything happens
> on a branch, and a failure anywhere puts the project back — but read
> `--dry-run` first on a project you care about.

## How a run goes

```
detect the project      →  laravel-react, or an empty directory to start one in
fetch the recipe        →  download, unpack, read the schema
checks                  →  what the driver needs, and what the recipe requires
branch                  →  feat/fullsystem-install, off where you are
pre-install             →  what the recipe declares: packages, removals, shadcn
install                 →  the recipe's files land on the project
post-install            →  what the recipe declares: artisan, and so on
verify                  →  lint, build, test
commit, then ask        →  apply it to your branch, or keep it on this one
```

Any failure rolls the project back to where it started. Nothing is ever pushed.

### It can start the project too

Point it at an empty directory and it offers to create one first:

```
./app is empty.
A new project has to be created before fullsystem/starter-kit can go in.

Run `composer create-project laravel/react-starter-kit` here? [yes]
```

Which project is not a guess — the recipe declares the driver it was written
for, and the driver knows the command. A directory with something else in it
is left alone.

### The branch

The work happens on `feat/fullsystem-install`, branched from wherever you
were, and is committed there. When it finishes you are standing on that
branch with the recipe installed, so you can run the app and look before
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
| `--recipe=<owner/repo>`, `-r` | Recipe to install. Defaults to [`fullsystem/starter-kit`](https://github.com/fullsystem/starter-kit). |
| `--dry-run` | Prints everything that would happen, writes nothing. |
| `--force` | Answers yes to the risk checks up front. |
| `-v` | Lets composer, npm and shadcn write to the terminal. Off by default: their output is kept and printed only if something fails. |
| `--no-interaction`, `-n` | Never asks. Every question resolves to its default. |

## Starting a recipe of your own

Give this URL to Claude, Cursor, or whatever you code with:

```
https://raw.githubusercontent.com/fullsystem/install/main/SKILL.md
```

[`SKILL.md`](SKILL.md) gets a recipe project onto disk and running: it asks
where the repository will live, checks that composer and npm work, downloads
the boilerplate, points it at your repository, and proves it starts before
handing back.

It stops there on purpose. What a recipe may declare, and how to build one, is
documented inside the project it hands you — so it travels with the recipe
rather than going stale in someone else's README.

## Caveats

**A recipe deleting what it does not replace.** `remove` and the recipe's files are not
checked against each other, so a recipe that deletes `resources/js/pages`
without shipping pages leaves a project that does not build. The verification
catches it and the rollback undoes it, but only after the whole run.

**Auth pages have an untyped contract with the backend.** Inertia props,
wayfinder route names and validation error keys are agreed by convention, not
by types. Replacing `pages/auth` while keeping Fortify's controllers compiles
fine and fails at runtime.

**Private recipes are not supported yet.** A recipe you cannot reach is a 404 on
the archive. Authenticating for exclusive recipes is what `login` will be for.

**The default branch is a moving target.** The fetch takes whatever `main`
holds right now; pinning a recipe to a tag is not supported yet.

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

How the code is laid out, how to run it against a project, what the checks are
and what the conventions expect is in [AGENTS.md](AGENTS.md) — written for the
assistant you will be contributing with, and just as readable without one.

## License

MIT
