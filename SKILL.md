---
name: fullsystem-recipe
description: Start a new recipe project for fullsystem/install — download the boilerplate, extract it, run the BOOT.md it ships, and delete that file once it succeeds, leaving AGENTS.md in charge of the session. Use when someone wants to start a new recipe, or points at this file.
---

# Starting a new recipe project

You are putting a recipe project on disk and running its boot. That is the whole
job, and it is deliberately small.

**You know almost nothing about what you are downloading**, and you must not act
as though you do. The boilerplate ships two files this relies on and no others:

| | |
|---|---|
| `BOOT.md` | The installation, written by people who know what is in the archive. You run it; you do not second-guess it. |
| `AGENTS.md` | Whoever works in the project after the boot. Not your concern until the boot is done. |

Whether the archive also holds a README, a `composer.json`, a test suite, a
frontend or none of those is not yours to assume, to check for, or to work
around.

Four things happen, and then you are gone.

## 1. Ask what it will be called

The name decides the folder, which is the only reason you are asking. **One
question at a time**, waiting for each — two questions in a single message read
as a form, and forms get answered in a hurry.

> Which GitHub organisation will this recipe live in?

A personal account is a valid answer; `xpto` is as good as `acme`. GitHub names
are letters, digits, hyphens, underscores and dots, and never start with a
hyphen.

> And what should the repository be called?

Same rules. Keep both answers — `BOOT.md` is likely to want them, and asking the
same thing twice is its own kind of rude.

**The directory is usually not a question.** The repository name is the obvious
folder name, so use `./<repo>` whenever it is free — missing, or empty apart
from a `.DS_Store`. State which directory you are using; do not ask permission
for the obvious.

Ask only when it is taken:

> `./<repo>` already has files in it. Which directory should I use instead?

Never unpack over somebody's work. If they had no repository name to give, ask
for the directory outright.

## 2. Download the boilerplate and extract it

**`fullsystem/recipe`** is how a recipe begins. It is the only thing you
download, and it goes into the directory settled in step 1 — `<dir>` below:

```bash
mkdir -p <dir>
curl -fsSL -o /tmp/fullsystem-recipe.zip https://github.com/fullsystem/recipe/archive/refs/heads/main.zip
unzip -q /tmp/fullsystem-recipe.zip -d /tmp/fullsystem-recipe
cp -R /tmp/fullsystem-recipe/recipe-main/. <dir>/
rm -rf /tmp/fullsystem-recipe /tmp/fullsystem-recipe.zip
```

Everything after this happens inside `<dir>`, so change into it now.

**Do not confuse it with `fullsystem/starter-kit`.** That one is a finished
recipe — what the installer reaches for when nobody names another, meant to be
installed into applications. `fullsystem/recipe` is where a new one begins.

Two things that go wrong here if nobody says them:

- GitHub wraps the contents of every archive in one top-level folder named
  `<repo>-<ref>`, so this unpacks as `recipe-main/`. **That wrapper is not part
  of the project** — what belongs in `<dir>` is what is inside it.
- `cp -R <source>/. <target>` is deliberate. It copies hidden files, and
  `.gitignore` is one of them.

Then confirm `BOOT.md` is at the root of `<dir>`.

**No `BOOT.md` means one of two things**, and they are not the same:

- the extraction did not land where you think it did — check, and fix it
- or this project has already been booted, and you are looking at a finished
  one. Do not re-run anything. Say so, and go to step 4.

## 3. Run `BOOT.md`

Read it and do what it says, start to finish.

It is the installation: what has to be filled in, what has to be installed, what
has to be running before any of this is usable. It was written with knowledge of
the archive that you do not have, so **follow it rather than improving on it**.
Where it asks for something you already know — the org and repo from step 1 —
supply it instead of asking again.

**If it fails, stop.** Say which instruction failed and what the output was.
Leave `BOOT.md` where it is: the project is half-installed, and the file is the
only thing that says so. A boot that failed and left no trace is worse than one
that failed loudly.

## 4. Delete `BOOT.md` and get out of the way

Only when the boot succeeded end to end:

```bash
rm BOOT.md
```

**That deletion is the record.** There is no install log and no marker file — the
absence of `BOOT.md` is what says this project is installed, and it is what
stops the next session from booting a project that is already running. Deleting
it while the boot is unfinished destroys that, so do it once and only on
success.

If the boot left the project under git, commit the deletion so the finished
state is the state on disk and in history alike.

Then **stop being the installer.** `AGENTS.md` is in charge from here: read it,
work from it, and treat everything in `BOOT.md` as spent — it described how to
start this project, not how to work in it, and carrying its instructions forward
is how a session ends up re-running setup on a project that is already set up.

If the user has not said what they want to build yet, that is the conversation
to have next, and `AGENTS.md` is what tells you how to have it here.
