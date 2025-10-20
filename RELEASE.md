# Release Guide for techgonia/pbac

## Package Information

- **Package Name**: `techgonia/pbac`
- **Type**: Laravel Package (library)
- **License**: MIT
- **First Release**: v1.0.0-alpha.1

## Pre-Release Checklist

### ✅ Files Verified

- [x] composer.json - Properly configured
- [x] README.md - Updated with correct package name
- [x] LICENSE.md - MIT License included
- [x] CHANGELOG.md - Release notes prepared
- [x] CONTRIBUTING.md - Contribution guidelines
- [x] .gitattributes - Export-ignore configured
- [x] .gitignore - Properly configured
- [x] All source files in src/
- [x] All tests in tests/
- [x] Documentation in docs/

### ✅ Composer.json Validation

```json
{
  "name": "techgonia/pbac",
  "type": "library",
  "description": "A powerful Policy-Based Access Control (PBAC) system for Laravel",
  "keywords": [...],
  "homepage": "https://github.com/techgonia/pbac",
  "license": "MIT",
  "authors": [{...}],
  "require": {
    "php": "^8.1",
    "illuminate/database": "^11.0|^12.0",
    "illuminate/support": "^11.0|^12.0",
    "spatie/laravel-package-tools": "^1.16"
  },
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

### ✅ Git Repository

- [x] Git repository initialized
- [x] On branch: `dev`
- [x] Remote: https://github.com/techgonia/pbac

## Publishing to Packagist (Alpha Release)

### Step 1: Prepare Repository

```bash
# Ensure you're in the lib directory
cd /Users/manpreet/Documents/project/tools/php/pbac/lib

# Check git status
git status

# Add all new files
git add .gitattributes LICENSE.md CHANGELOG.md CONTRIBUTING.md RELEASE.md

# Commit changes
git commit -m "Prepare for v1.0.0-alpha.1 release

- Add LICENSE.md (MIT)
- Add CHANGELOG.md with release notes
- Add CONTRIBUTING.md
- Add .gitattributes for export-ignore
- Update composer.json for Packagist
- Fix README.md package name
- Set PHP requirement to ^8.1
- Set Laravel requirement to ^11.0|^12.0
"

# Push to dev branch
git push origin dev
```

### Step 2: Create Alpha Tag

```bash
# Create annotated tag for alpha release
git tag -a v0.0.1 -m "Release v0.0.1

First alpha release of techgonia/pbac
Features:
- Policy-Based Access Control system
- Support for Users, Groups, Teams, Resources
- Deny-first security model
- Priority-based rule evaluation
- Attribute-based conditions
- Laravel Gate integration
- Blade directives
- Super admin bypass
- Comprehensive test suite (212 tests)
"

# Push tag to GitHub
git push origin v0.0.1

# Verify tag
git tag -l
```

### Step 3: Validate composer.json

```bash
# Validate composer.json syntax
composer validate

# Check for issues
composer validate --strict
```

Expected output:
```
./composer.json is valid
```

### Step 4: Test Installation Locally

```bash
# In a test Laravel project
composer require techgonia/pbac:@dev

# Or test with specific version
composer require techgonia/pbac:dev-dev
```

### Step 5: Submit to Packagist

1. Go to https://packagist.org/packages/submit
2. Sign in with GitHub account
3. Enter repository URL: `https://github.com/techgonia/pbac`
4. Click "Check"
5. Click "Submit"

### Step 6: Set up Auto-Update Hook

Packagist will show instructions for:
- GitHub webhook for auto-updates
- Or manual update via Packagist

### Step 7: Verify on Packagist

1. Visit: https://packagist.org/packages/techgonia/pbac
2. Verify package information
3. Check that v1.0.0-alpha.1 appears
4. Verify README displays correctly

## Installation Instructions for Users

### For Alpha Testing

```bash
# Require alpha version explicitly
composer require techgonia/pbac:^1.0.0-alpha

# Or require dev version
composer require techgonia/pbac:dev-dev
```

### In composer.json

```json
{
    "require": {
        "techgonia/pbac": "^1.0.0-alpha"
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

## After Publishing

### Update README Badge

Add Packagist badge to README.md:

```markdown
[![Latest Version on Packagist](https://img.shields.io/packagist/v/techgonia/pbac.svg?style=flat-square)](https://packagist.org/packages/techgonia/pbac)
[![Total Downloads](https://img.shields.io/packagist/dt/techgonia/pbac.svg?style=flat-square)](https://packagist.org/packages/techgonia/pbac)
```

### Announce Release

- GitHub Releases page
- Laravel News (optional)
- Twitter/Social media
- Laravel community forums

## Future Releases

### Beta Release (v1.0.0-beta.1)

After alpha testing and bug fixes:

```bash
git tag -a v1.0.0-beta.1 -m "Beta release"
git push origin v1.0.0-beta.1
```

### Stable Release (v1.0.0)

After beta testing:

```bash
git tag -a v1.0.0 -m "First stable release"
git push origin v1.0.0
```

Update composer.json minimum-stability:
```json
"minimum-stability": "stable"
```

## Troubleshooting

### Package Not Showing Up

- Wait 5-10 minutes after submission
- Check GitHub webhook is configured
- Manually update on Packagist

### Version Not Appearing

- Ensure tag is pushed to GitHub
- Check tag format: v1.0.0-alpha.1
- Update package on Packagist manually

### Installation Fails

- Check PHP version compatibility
- Verify Laravel version compatibility
- Check minimum-stability setting

## Support

- Issues: https://github.com/techgonia/pbac/issues
- Email: phoenix404dev@gmail.com

## Notes

- This is an ALPHA release
- API may change before 1.0.0 stable
- Suitable for testing and feedback
- Not recommended for production use yet
