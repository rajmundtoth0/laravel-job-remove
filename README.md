[![Latest Stable Version](http://poser.pugx.org/rajmundtoth0/laravel-job-remove/v)](https://packagist.org/packages/rajmundtoth0/laravel-job-remove)
[![codecov](https://codecov.io/gh/rajmundtoth0/laravel-job-remove/graph/badge.svg?token=BKO7DT2WT9)](https://codecov.io/gh/rajmundtoth0/laravel-job-remove)
![PHPSTAN](https://img.shields.io/badge/PHPStan-Level_MAX-brightgreen)

# Laravel Job Remove

This package provides functionality to remove jobs from the Laravel queue.

## Installation

To install the package, use Composer:

```bash
composer require rajmundtoth0/laravel-job-remove
```

## Usage

```bash
php artisan queue:remove [queue] [job] --limit 1
```
Like:
```bash
php artisan queue:remove myJobQueue App\Jobs\MyJob --limit 1
```

**Use at your own risk.** This package directly manipulates the job queue and should be used with caution.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
