# Quick Fix Guide - Page Not Found Error

## Issue
Getting error: "Page not found: ./pages/Sites/Index.tsx"

## Solution

The files are correctly named, but you need to:

### 1. Restart Development Server
Kill the current `npm run dev` and restart it:

```bash
# Press Ctrl+C to stop the current dev server
# Then restart:
npm run dev
```

### 2. Clear Browser Cache
Hard refresh the page:
- Chrome/Firefox: `Ctrl + Shift + R` (Linux)
- Or open DevTools (F12) → Right-click refresh → "Empty Cache and Hard Reload"

### 3. Verify Build
If still not working, rebuild:

```bash
npm run build
php artisan optimize:clear
```

## Files Are Correct ✅

Current structure (verified):
```
resources/js/pages/Sites/
├── Index.tsx   ✅
├── Create.tsx  ✅
└── Show.tsx    ✅
```

## Why This Happens

Vite dev server may cache the old file paths. Restarting fixes this.

## Test It Works

After restarting dev server, visit:
- http://localhost:8000/sites

Should load without errors!
