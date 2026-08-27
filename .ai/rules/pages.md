---
paths:
  - 'resources/js/pages/**'
---

# Pages

## A new Inertia page needs a build before its feature test passes
`app.blade.php` resolves `resources/js/pages/{component}.tsx` through the Vite manifest, so a feature test hitting a brand new page 500s with "Unable to locate file in Vite manifest" until `yarn build` has run. It is not a routing or Inertia fault — run the build, then the test.
