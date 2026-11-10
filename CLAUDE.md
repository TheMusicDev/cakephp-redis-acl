# CLAUDE.md

`themusicdev/redis-acl` — a CakePHP 5 plugin providing **one** cache engine: a `RedisEngine` subclass that can
authenticate as a Redis 6 **ACL user** (username + password), which CakePHP's own engine cannot.

## Start from the org conventions

https://github.com/TheMusicDev/cakephp-conventions — read its README, then the docs the work touches. Each is
self-contained.

**The exemplar plugin is [`themusicdev/edge-cache`](https://github.com/TheMusicDev/cakephp-edge-cache).** Match its
layout, its `composer.json` shape, its quality-gate configs and its CI workflow rather than inventing any of it.

## Rules

- **Never stage, commit or push unless explicitly asked.** No co-author trailers. Every change goes through a pull
  request.
- **Never `git add -A` / `git add .`:** stage explicit paths and read `git status` first.
- Use `[[ ]]` in zsh, never `[ ]`, for string comparisons.
- Quality gates: `composer check` = phpunit + phpcs (CakePHP standard) + phpstan **level 8**. All three must pass
  before anything is called done.
- This plugin stays **one class plus one line of bootstrap**. It exists to be deleted the day upstream CakePHP
  supports ACL usernames. Resist every urge to grow it into a general-purpose Redis plugin. Out of scope: session
  handlers, queues, locks, rate limiters, pub/sub, Sentinel/cluster additions, shells, config generators — see
  [`docs/decisions.md`](docs/decisions.md).

## Raise upstream opportunities

This plugin exists *because* of an upstream gap. If the work uncovers more — in `cakephp/cakephp`, in
`TheMusicDev/cakephp-template`, or in `TheMusicDev/cakephp-conventions` — **file an issue** on the relevant repo
rather than silently working around it. Prefer issues over PRs on org repos: the org decides the fix.

The known one, to be filed as part of this work: `cakephp/cakephp`'s `RedisEngine` ignores a DSN username and calls
`Redis::auth()` with a scalar password only, so Redis 6 ACL users cannot be used. Details and line references are in
[`docs/decisions.md`](docs/decisions.md).

## Consumers

First consumer is **IndieHype**, a private CakePHP app, which needs Redis-backed *sessions* on
its dokploy staging environment. Sessions reach Redis through CakePHP's `CacheSession` handler, which is a thin
wrapper over a named **Cache** configuration — so a cache engine is the right and only extension point. The reasoning
and the settled design decisions are recorded in [`docs/decisions.md`](docs/decisions.md).
