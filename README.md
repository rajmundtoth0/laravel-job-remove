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
