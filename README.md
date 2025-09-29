# Finance Telegram Bot

A Laravel-based Telegram bot for personal finance management with income and expense tracking.

## Features

- Income and expense tracking
- Category-based organization
- Date filtering and statistics
- Pagination support (10 items per page)
- Telegram bot integration

## Deployment to Railway

### Prerequisites

1. A Telegram bot token from [@BotFather](https://t.me/BotFather)
2. A GitHub account
3. A Railway account

### Step-by-Step Deployment

1. **Fork or clone this repository to your GitHub account**

2. **Create a new project on Railway:**
   - Go to [Railway.app](https://railway.app)
   - Click "New Project"
   - Select "Deploy from GitHub repo"
   - Choose this repository

3. **Add a MySQL database:**
   - In your Railway project, click "New"
   - Select "Database" → "Add MySQL"
   - Railway will automatically provide database credentials

4. **Configure environment variables:**
   - Go to your service settings in Railway
   - Add the following environment variables:
   
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:your_generated_key_here
   APP_URL=https://your-app-name.up.railway.app
   
   TELEGRAM_BOT_TOKEN=your_telegram_bot_token
   TELEGRAM_WEBHOOK_URL=https://your-app-name.up.railway.app/api/telegram/webhooks
   TELEGRAM_CHANNEL_ID=@your_channel_id
   
   DB_CONNECTION=mysql
   DB_HOST=${{MYSQL_HOST}}
   DB_PORT=${{MYSQL_PORT}}
   DB_DATABASE=${{MYSQL_DATABASE}}
   DB_USERNAME=${{MYSQL_USER}}
   DB_PASSWORD=${{MYSQL_PASSWORD}}
   ```

5. **Generate APP_KEY:**
   - Run `php artisan key:generate --show` locally
   - Copy the generated key to Railway's APP_KEY variable

6. **Deploy:**
   - Railway will automatically deploy when you push to your main branch
   - The deployment process includes:
     - Installing dependencies
     - Running database migrations
     - Caching configuration

7. **Set up Telegram webhook:**
   - After deployment, set your bot's webhook URL to:
   - `https://your-app-name.up.railway.app/api/telegram/webhooks`

### Automatic Deployment

Once set up, any push to your main branch will automatically trigger a new deployment on Railway.

### Local Development

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
