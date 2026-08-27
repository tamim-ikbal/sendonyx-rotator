---
paths:
  - 'resources/js/actions/**'
---

# Actions

## Run `wayfinder:generate` with `--with-form`
`vite.config.ts` configures the plugin with `formVariants: true`, so the committed actions carry `.form()` helpers, which pages pass straight into Inertia's `<Form>`.

Running `php artisan wayfinder:generate` by hand omits that flag and silently strips `.form` from **every** generated file, not just the new one. The breakage shows up as a TypeScript error in unrelated pages.

Always run `php artisan wayfinder:generate --with-form`, or just let `yarn dev` / `yarn build` regenerate.
