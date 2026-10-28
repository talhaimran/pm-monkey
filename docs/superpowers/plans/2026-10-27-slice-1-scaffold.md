# Slice 1: Scaffold Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (native). Steps use checkbox (`- [ ]`) syntax.

**Goal:** Bootable Laravel app with Breeze React/TS, Sanctum, laravel/mcp, Scramble, Pest, and dnd-kit on MySQL. Tests and build must be green.

**Architecture:** Laravel scaffolded into the repo root. No domain code yet.

**Tech Stack:** Laravel 12+, PHP 8.5, MySQL, Inertia + React + TS + Vite + Tailwind, Pest.

**Spec:** SPEC.md

## Global Constraints
- Latest stable Laravel (12+), PHP 8.3+, MySQL 8
- Breeze React/TS variant; Sanctum; laravel/mcp; dedoc/scramble; dnd-kit; Pest
- No other heavy dependencies without asking

## Review Focus
- An existing README.md, SPEC.md, CLAUDE.md, and docs/ must survive the scaffold (scaffold into a temp dir, then move it in).
- `.env` must not be committed; `.env.example` must carry MySQL settings.
- Scramble docs must be reachable at /docs/api and /docs/api.json (Scramble's default routes). One Pest test pins this.
- `Model::preventLazyLoading(! app()->isProduction())` goes in AppServiceProvider.

---

### Task 1: Scaffold and packages
- [ ] Run `composer create-project laravel/laravel` into a temp dir and move it into the repo root, keeping the existing docs.
- [ ] Run `composer require laravel/breeze --dev`, then `php artisan breeze:install react --typescript --pest`.
- [ ] Run `php artisan install:api` (Sanctum), `composer require laravel/mcp dedoc/scramble`, and `npm i @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities`.
- [ ] Set the MySQL database `pm_monkey` (and `pm_monkey_test` for Pest via phpunit.xml) in `.env` and `.env.example`.
- [ ] Add preventLazyLoading.
- [ ] Add a test in `tests/Feature/ScaffoldTest.php`: GET /docs/api.json returns 200 with an `openapi` key.
- [ ] Verify: `php artisan migrate`, `php artisan test` all green, `npm run build` succeeds.
- [ ] Commit: "chore: scaffold Laravel, Breeze React/TS, Sanctum, MCP, Scramble, Pest".
