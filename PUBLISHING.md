# Publishing to Packagist

## Prerequisites

- GitHub account
- Packagist account
- Repository pushed to GitHub

## Steps

### 1. Prepare Repository

```bash
git add .
git commit -m "v1.0.0 - Initial release"
git tag v1.0.0
git push origin main
git push --tags
```

### 2. Register on Packagist

1. Go to https://packagist.org
2. Sign in with GitHub
3. Click "Submit"
4. Enter repository URL: `https://github.com/kendenigerian/payments-router`
5. Click "Check"
6. Click "Submit"

### 3. Set up Auto-Update

1. Go to your package page on Packagist
2. Click "Edit"
3. Enable "Enable auto-updating"
4. Copy the GitHub webhook URL
5. Go to GitHub repository → Settings → Webhooks
6. Add webhook with:
   - Payload URL: (from Packagist)
   - Content type: application/json
   - Event: Just the push event

### 4. Verify Installation

```bash
composer require kendenigerian/payments-router
```

### 5. Future Releases

```bash
# Make changes
git add .
git commit -m "Fix: description"
git tag v1.0.1
git push origin main
git push --tags
```

Packagist will automatically update via webhook!

## Version Naming

Follow Semantic Versioning:
- MAJOR version: Breaking changes
- MINOR version: New features (backward compatible)
- PATCH version: Bug fixes

Examples:
- v1.0.0 - Initial release
- v1.1.0 - Added new provider
- v1.1.1 - Fixed webhook bug
- v2.0.0 - Breaking API changes

## Badges

Add to README:

```markdown
[![Latest Version](https://img.shields.io/packagist/v/kendenigerian/payments-router.svg)](https://packagist.org/packages/kendenigerian/payments-router)
[![Total Downloads](https://img.shields.io/packagist/dt/kendenigerian/payments-router.svg)](https://packagist.org/packages/kendenigerian/payments-router)
```
