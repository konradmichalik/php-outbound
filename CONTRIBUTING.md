# Contributing

Thanks for helping out. Open an issue first for larger changes.

```bash
composer install
composer fix     # code style
composer sca     # static analysis
composer test    # unit tests
```

- Commit messages follow `<type>: <description>` (feat, fix, refactor, docs, test, chore, perf, ci)
- Add a test for new behaviour and for every bug fix
- The core stays free of framework code: anything that needs TYPO3, Symfony kernel or another framework belongs in an integration package
