# How to contribute

Contributions are always welcome. Here are a few guidelines to be aware of:

* Include unit tests for new behaviours introduced by PRs.
* Fixed bugs MUST be covered by test(s) to avoid regression.
* All code must follow the `PSR-2` coding standard. Please see [PSR-2](https://www.php-fig.org/psr/psr-2/) for more details. To make this as easy as possible, we use PHP_Codesniffer. Using two simple make commands: `make cs-check` and `make cs-fix`.
* Before implementing a new big feature, consider creating a new Issue on Github. It will save your time when the core team is not going to accept it or has good recommendations about how to proceed.

## Tests

The following commands can be ran to test on your local environment

* `make analyze` to run PHP_Codesniffer and PHPStan.
* `make test` will run all types of tests.
* `make test-unit` will run all unit tests.
* `make test-infection` will run all infection.

Of course, you can use `make all` or just `make` to run both the analysis and tests.

In order to run the test suite, you need to have `xdebug` or `phpdbg` enabled in your development environment.
