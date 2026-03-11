# Contributing to Filexus

Thank you for considering contributing to Filexus!

## Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Run tests: `composer test`

## Pull Request Process

1. Update the README.md with details of changes if applicable
2. Update the CHANGELOG.md with a note describing your changes
3. Ensure all tests pass
4. Follow PSR-12 coding standards
5. Write clear, descriptive commit messages

## Coding Standards

- Follow PSR-12
- Use strict types: `declare(strict_types=1);`
- Add type hints to all parameters and return types
- Write PHPDoc comments for all public methods
- Write tests for new features

## Running Tests

```bash
composer test
```

## Code Style

We use PHP CS Fixer for code formatting:

```bash
vendor/bin/php-cs-fixer fix
```

## Reporting Bugs

Please use the GitHub issue tracker to report bugs. Include:

- PHP version
- Laravel version
- Steps to reproduce
- Expected vs actual behavior

## Feature Requests

Feature requests are welcome! Please open an issue describing:

- The problem you're trying to solve
- Your proposed solution
- Any alternatives you've considered
