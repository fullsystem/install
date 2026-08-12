# Security

## Reporting a vulnerability

Report privately, not in a public issue. This package deletes files and runs
commands in other people's projects, so a working proof of concept posted
publicly is a weapon before it is a bug report.

Use GitHub's [private vulnerability reporting](https://github.com/fullsystem/install/security/advisories/new).
If that is unavailable, email renatotkd@gmail.com.

You should get an acknowledgement within a few days. Please give us a chance
to release a fix before disclosing.

## Supported versions

The package is pre-1.0 and under active development. Fixes go to `main`; there
is no backport branch yet.

## What counts as a vulnerability here

The installer's job is to run instructions declared by a theme. That makes the
boundary between "working as designed" and "vulnerable" worth stating.

**In scope** — report these:

- A theme escaping the project directory: a `remove` path, or an archive entry,
  that writes or deletes outside the directory being installed into.
- Anything that reaches a shell. Every command is built as an argument list and
  executed without a shell; a place where a value is concatenated into a
  command string is a bug even if you cannot yet exploit it.
- A schema value reaching a program as a flag rather than as data — a package
  name that arrives at Composer as `--ignore-platform-reqs`, for example.
- Once authentication exists: a token written somewhere world-readable, sent to
  a host other than the configured one, or logged.
- Destructive behaviour that no confirmation preceded and no flag requested.

**Out of scope** — these are the design, not defects:

- A theme installing a Composer or npm package that runs code. Package managers
  execute install scripts and plugins by design; a theme that can add a
  dependency can run code, and no validation here changes that. The protection
  against a hostile theme is choosing which theme you install, not this tool.
- A theme deleting files it declared in `remove`, when the paths stay inside
  the project and the run was confirmed. That is the whole point of the tool.
- Commands a theme declares that are allowed and non-destructive, even if you
  personally would not run them.

If you are unsure which side something falls on, report it privately and we
will work it out.

## What the tool guarantees

- Commands are executed as argument lists, never through a shell.
- Paths declared by a theme are validated against the project root before
  anything is deleted, and the whole list is validated before the first
  deletion happens.
- Destructive steps are shown and confirmed; `--force` is how you say yes in
  advance, and without a terminal the answer is no.
- A failed run restores the project to the commit it started from.

None of these protect you from a theme you should not have trusted.
