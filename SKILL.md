---
name: fullsystem-recipe
description: Start a new recipe project for fullsystem/install — ask where it will live, download the boilerplate, extract it, fill in the org and repo it belongs to, put it under git, and hand over to the AGENTS.md that came with it, which is what knows the project from there. Use when someone wants to start a new recipe, or points at this file.
---

# Starting a new recipe project

You are putting a recipe project on disk and handing it over. That is the whole
job, and it is deliberately small.

**You know almost nothing about what you are downloading**, and you must not act
as though you do. The boilerplate ships an `AGENTS.md`; that is the one thing
this file relies on. Whether it also has a README, a `composer.json`, a test
suite, a frontend or none of those is not yours to assume, to check for, or to
work around. `AGENTS.md` knows the project. You get it on disk, name it, and get
out of the way.

Four things happen, and then you hand over.

## 1. Ask where it will live

**One question at a time, waiting for each.** Two questions in a single message
read as a form, and forms get answered in a hurry.

> Which GitHub organisation will this recipe live in?

A personal account is a valid answer; `xpto` is as good as `acme`. GitHub names
are letters, digits, hyphens, underscores and dots, and never start with a
hyphen.

> And what should the repository be called?

Same rules.

If they do not know where it will live yet, that is a fine answer: say the
placeholders stay in the documentation until someone fills them in, and carry
on.

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
recipe — the one the installer reaches for when nobody names another, meant to
be installed into applications. `fullsystem/recipe` is where a new one begins.
Different repositories, different jobs.

Two things that go wrong here if nobody says them:

- GitHub wraps the contents of every archive in one top-level folder named
  `<repo>-<ref>`, so this unpacks as `recipe-main/`. **That wrapper is not
  part of the project** — what belongs in `<dir>` is what is inside it.
- `cp -R <source>/. <target>` is deliberate. It copies hidden files, and
  `.gitignore` is one of them.

Then confirm `AGENTS.md` is at the root of `<dir>`. If it is not, the extraction
did not land where you think it did — and it is the only file this skill counts
on, so stop and say so rather than carrying on.

## 3. Fill in the placeholders

The boilerplate names no repository. Every place one belongs is written as
`{org}` and `{repo}`, waiting for the answers from step 1. Handed over unfilled,
it is a project whose own documentation points at nothing.

Find them rather than guessing where they are — which files exist is not
something you know:

```bash
grep -rlF -e '{org}' -e '{repo}' . --exclude-dir=.git
```

Replace both in every file that list names. It is a search and replace with no
judgement in it: do not go hunting for other mentions of where the project came
from.

The one exception is text that *discusses* the placeholders rather than using
them — documentation explaining the convention. Rewriting that turns an
explanation into nonsense.

Then run the search again and **show what is left**. Everything remaining should
be one of those explanations. Anything else is a broken reference you are about
to hand over as finished.

## 4. Give it a repository

This directory is the code that goes to the repository from step 1. If it is not
a git repository yet, make it one — without asking. A project with no history is
one where the next change cannot be undone, and that is not a decision worth a
question.

```bash
git init
git add -A
git commit -m "chore: start <org>/<repo>"
```

After step 3, so the first commit already has the placeholders filled.

**The repository almost certainly does not exist on GitHub yet.** That is
normal, it is not a problem, and it is not something to raise. Do not create it,
do not add a remote, and do not ask whether to — putting code into somebody's
account is theirs to do.

Give them the command once, at the handover, so it is there when they want it:

```bash
git remote add origin git@github.com:<org>/<repo>.git
```

If the directory is already a repository, leave its setup alone: commit what you
added and say which branch they are on.

## 5. Hand over to `AGENTS.md`

The project is on disk, it carries its own name, and it is under git. **Your
part is finished.**

Read `AGENTS.md` now and do what it says. It is what knows this project: what a
recipe is, what belongs in it, what has to be installed or run, and what to ask
the user next. Everything you might be tempted to do here — set up dependencies,
start something, write documentation, decide what the recipe should contain —
is on the other side of that file, and doing it from here means doing it from
assumptions.

If `AGENTS.md` asks for something the machine does not have, that is the moment
to find out, and its instructions are what say how to handle it.
