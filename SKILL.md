---
name: fullsystem-recipe
description: Set up a new recipe project for fullsystem/install — ask where it will live and what it is for, check that composer and npm work, download the boilerplate, extract it, fill in the org and repo it belongs to, write it a README of its own, put it under git, and prove it runs. Then hand over to the AGENTS.md that came with it, which is what knows how recipes work. Use when someone wants to start a new recipe, or points at this file.
---

# Setting up a new recipe project

You are getting a recipe project onto disk and running. That is the whole job.

**You are not learning the recipe format here, and you are not writing a recipe.**
The boilerplate you are about to download ships an `AGENTS.md` that knows what a
recipe is, what it may declare, and how to build one. It takes over at the end of
this file. Do not anticipate it, and do not improvise what it covers.

Seven things happen, and then you hand over.

## 1. Ask two things

Ask both together, in one message, before anything is downloaded. Finding out
afterwards means going back over files you already touched.

> **1. Which GitHub organisation and repository will this recipe live in?**

Take an **org** and a **repo** separately. Both are GitHub names: letters,
digits, hyphens, underscores and dots, not starting with a hyphen. A personal
account is a valid org — `xpto/my-recipe` is as good an answer as
`acme/dashboard`.

If they do not know yet, that is a fine answer. Say that the placeholders stay
in the documentation until someone fills them in, and carry on.

> **2. What is this recipe for?**

What kind of application it is meant to produce, what it should bring with it,
who would install it. A sentence is enough; a paragraph is better.

You are not designing the recipe from this — that comes later, and it is
`AGENTS.md` that explains how. You are asking because step 5 writes the README,
and a README written without knowing what the thing is ends up describing the
example it was copied from.

If they would rather get to it later, take what they have and say the README is
a placeholder until they tell you more.

## 2. Composer and npm have to work

Both are required. Check before downloading anything — a project extracted onto
a machine that cannot run it is worse than no project, because it looks like
progress.

```bash
composer --version
npm --version
```

Both answering is the whole requirement. Composer answering means PHP is there
and working; npm answering means Node is. A version number you can read is
better evidence than a `command -v` that only proves a file exists.

### If one of them is missing

**Ask first.** Installing a language runtime changes the user's machine outside
this project, and it is not yours to decide. Say which one is missing, what you
propose to run, and wait for a yes. Never install anything on a maybe.

If they agree, you may attempt it **only** where a package manager is already
present and does not need a password:

| platform | attempt |
|---|---|
| macOS with Homebrew | `brew install composer`, `brew install node` |
| anywhere else | do not attempt — send them to the documentation below |

Do not install Homebrew, or any other package manager, to satisfy this. That is
a far larger change to someone's machine than they agreed to, and agreeing to
"install npm" is not agreeing to it.

**You cannot type a password.** If a command asks for one — anything through
`sudo`, most Linux package managers, every Windows installer — stop there and
hand the command to the user to run themselves. Do not retry it, and do not look
for a way around the prompt.

### When you cannot install it

Say which one is missing, what you tried, and what it said. Then point at the
documentation and stop. Do not download the project: an install that
half-worked is harder to diagnose than one that never started.

| missing | where to send them |
|---|---|
| Composer | https://getcomposer.org/download/ |
| Node and npm | https://nodejs.org/en/download |
| both, on macOS or Windows | https://herd.laravel.com — one installer, aimed at people who do not want to assemble a PHP toolchain by hand |

Send them to the one that matches what is actually missing.

## 3. Download the boilerplate and extract it

**`fullsystem/recipe`** is how a starter kit begins — the structure a recipe
should have, the conventions it ships with, and the documentation that travels
with it. It is the only thing you download here:

```bash
curl -fsSL -o /tmp/fullsystem-recipe.zip https://github.com/fullsystem/recipe/archive/refs/heads/main.zip
unzip -q /tmp/fullsystem-recipe.zip -d /tmp/fullsystem-recipe
cp -R /tmp/fullsystem-recipe/recipe-main/. .
rm -rf /tmp/fullsystem-recipe /tmp/fullsystem-recipe.zip
```

**Do not confuse it with `fullsystem/starter-kit`.** That one is a finished
recipe — the one the installer reaches for when nobody names another, meant to
be installed into applications. `fullsystem/recipe` is where a new one begins.
Different repositories, different jobs; downloading the wrong one gives you
somebody's finished work instead of a starting point.

Two more things that go wrong here if nobody says them:

- GitHub wraps the contents of every archive in one top-level folder named
  `<repo>-<ref>`, so this unpacks as `recipe-main/`. **That wrapper is not
  part of the project** — what belongs in the working directory is what is
  inside it.
- `cp -R <source>/. <target>` is deliberate. It copies hidden files, and
  `.gitignore` is one of them.

Confirm `AGENTS.md` is at the root of the working directory before going on. If
it is not, the extraction did not land where you think it did.

## 4. Point it at the new repository

**Do not skip this, and do not leave it for later.** What you extracted names no
repository at all: every place one belongs is written as `{org}` and `{repo}`,
waiting to be filled. Handed over unfilled, it is a project whose own
documentation tells people to install `{org}/{repo}`, and whose recipe is called
`{org}/{repo}` when the installer prints it.

Replace both, everywhere they appear, with the answers from step 1:

```bash
grep -rlF -e '{org}' -e '{repo}' . --exclude-dir=.git
```

That list is the work. `README.md` and `AGENTS.md` are the obvious ones;
`SKILL.md` and the **`name` in `schema.json`** are the ones that get forgotten.

It is a search and replace with no judgement in it. Do not go hunting for other
mentions of where the project came from — if a string is meant to change, it is
a placeholder.

The one exception: sentences that *discuss* `{org}` and `{repo}` rather than use
them. `AGENTS.md` explains the convention, and `SKILL.md` may too. Rewriting
those turns an explanation into nonsense — a paragraph about "the `acme` and
`dashboard` placeholders" helps nobody.

Then run the same search again and **show the user what is left**. Everything
that remains should be one of those explanatory sentences. Anything else is a
broken URL you are about to hand over as finished.

## 5. Make the README describe this recipe

The README you extracted describes the boilerplate's own example — the packages
it happens to declare, the frontend it happens to ship. Left alone, it is a
project whose front page is about somebody else's work.

Rewrite it from the answer to question 2. What this recipe is for, what it
brings, who would install it. Keep the parts that are true of any recipe — how
it is installed, that the installer works on a branch and rolls back — and
replace everything that was describing the example.

**Do not let it promise what the schema does not do.** `schema.json` still
declares the boilerplate's packages and commands, and a README announcing one
thing while the recipe installs another is worse than the stale one you started
from. Either say plainly that the recipe is not written yet, or say what the
schema actually declares. Which of those it becomes is the next conversation,
and it belongs to `AGENTS.md`.

If they had nothing to tell you yet, say so in the README itself rather than
leaving the example in place. "This recipe does not do anything yet" is honest;
a description of reverb and horizon that nobody chose is not.

## 6. Give it a repository

This directory is the code that goes to the repository from step 1. Treat it as
that, and if it is not a git repository yet, make it one — without asking. A
project with no history is one where the next change cannot be undone, and that
is not a decision worth a question.

```bash
git init
git add -A
git commit -m "chore: start <org>/<repo>"
```

After steps 4 and 5, so the first commit already has the placeholders filled and
a README that describes this recipe.

**The repository almost certainly does not exist on GitHub yet.** That is
normal, it is not a problem, and it is not something to raise. Do not create it,
do not add a remote, and do not ask whether to — putting code into somebody's
account is theirs to do, and there is nothing here that needs it done.

Give them the command once, when you hand over, so it is there when they want
it:

```bash
git remote add origin git@github.com:<org>/<repo>.git
```

If the directory is already a repository, leave its setup alone: commit what you
added and say which branch they are on.

## 7. Prove it runs

Find out that it works here — this machine, these versions — while a failure
still has exactly one possible cause.

```bash
composer install
```

It has to finish clean. If it does not, stop and report it: a dependency that
will not install is not something to hand over.

Then start it the way the project itself says to, which is `composer dev` unless
its `composer.json` says otherwise. **Read what that script actually runs before
you run it** — it is usually several processes at once, a server and a bundler
and often a queue worker, and what it binds to is declared there rather than
here.

**`composer dev` does not exit.** That is what it is for, and it is the trap:

- start it in the background, never in the foreground where it blocks
- give it a few seconds, then check the application answers on the address it
  reported
- **stop it, and confirm it stopped**

Leaving it running is not untidiness. The port stays held after this session
ends, and the next thing that tries to bind fails for a reason nobody will
connect back to you.

If it does not come up, say what failed and what the output was. Do not hand
over a project you never saw working.

## 8. Hand over to `AGENTS.md`

The project is on disk, it is pointed at the right repository, and it runs.
Your part is finished.

`AGENTS.md` in the working directory is what knows how recipes work — the format,
what a recipe may declare, what the installer refuses, how to build and test one.
**Read it now**, and work from it rather than from anything you assumed while
reading this file.

If the user has not said what they want the recipe to do yet, that is the
conversation to have next — and `AGENTS.md` is what tells you how to have it.
