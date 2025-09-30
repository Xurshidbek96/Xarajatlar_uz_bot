# Finance Telegram Bot

A Laravel-based Telegram bot for personal finance management with income and expense tracking.

## Features

- Income and expense tracking
- Category-based organization
- Date filtering and statistics
- Pagination support (10 items per page)
- Telegram bot integration

## Local Development

1. Clone the repository
2. Copy `.env.example` to `.env`
3. Configure your local database and Telegram bot settings
4. Run `composer install`
5. Run `php artisan key:generate`
6. Run `php artisan migrate`
7. Run `php artisan serve`

## Bot Commands

- `/start` - Start the bot
- Use inline keyboard to navigate through income/expense tracking
- View statistics and filter by date ranges
- Navigate through paginated lists (10 items per page)

## Support

For issues and questions, please create an issue in the GitHub repository.

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
