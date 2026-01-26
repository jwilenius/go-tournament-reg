# Go Tournament Registration

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

WordPress plugin for managing Go tournament player registrations with automatic player strength sorting.

## Features

- **Player strength sorting**: Dan (9d→1d) then Kyu (1k→30k)
- **Multi-tournament support**: Run multiple independent tournaments simultaneously
- **International support**: ISO country selection with Unicode name support
- **EGD lookup**: Search the European Go Database to auto-fill player details
- **CSV export**: Download registration lists for tournament management
- **Security hardened**: ABSPATH protection, capability checks, XSS protection, rate limiting

## Installation

### Method 1: Download from GitHub Releases (Recommended)

1. Go to the [Releases page](https://github.com/jwilenius/go-tournament-reg/releases)
2. Download the latest `go-tournament-registration.zip` file
3. In WordPress Admin, go to **Plugins → Add New → Upload Plugin**
4. Choose the downloaded ZIP file and click **Install Now**
5. Click **Activate Plugin**

### Method 2: Download from GitHub Actions

1. Go to the [Actions tab](https://github.com/jwilenius/go-tournament-reg/actions)
2. Click on the latest successful workflow run
3. Download the artifact ZIP file
4. Follow steps 3-5 from Method 1 above

### Method 3: Manual Installation

1. Clone or download this repository
2. Copy the entire folder to `wp-content/plugins/`
3. Go to WordPress Admin → Plugins
4. Activate "Go Tournament Registration"

## Usage

### Create a Tournament Registration Page

Add this shortcode to any WordPress page:

```
[go_tournament_registration tournament="spring-2024" title="Spring Championship 2024"]
```

**Parameters:**
- `tournament` - Unique identifier for the tournament (required for multiple tournaments)
- `title` - Optional title displayed above the form

**Examples:**
```
[go_tournament_registration tournament="autumn-2024" title="Autumn Tournament"]
[go_tournament_registration tournament="summer-cup"]
[go_tournament_registration]  (uses 'default' tournament)
```

### Manage Registrations

Go to WordPress Admin → Tournament Registration

- Select tournament from dropdown
- Export to CSV
- Delete individual or all registrations for a tournament

### EGD Player Lookup

The registration form includes an "EGD Lookup" button that searches the [European Go Database](https://www.europeangodatabase.eu/):

1. Enter the player's first name, last name, and/or select a country
2. Click the "EGD Lookup" button
3. Select a matching player from the dropdown
4. EGD Number, Player Strength, and Country are auto-filled

**Features:**
- Supports Unicode names (e.g., Müller, José, Björk)
- Shows up to 9 results; links to EGD website for more
- "Not registered in EGD" option for manual entry
- Rate limited to 10 lookups per minute per IP

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Development

### Local Development with Docker

The easiest way to test the plugin locally is using Docker:

```bash
./start-local.sh
```

This will:
- Create a `.env` file with default database credentials (if needed)
- Start WordPress and MySQL containers
- Mount the plugin directory for live code changes

Once running:
1. Visit http://localhost:8080 to complete WordPress setup
2. Go to **Plugins** and activate "Go Tournament Registration"
3. Create a page with the shortcode `[go_tournament_registration]`

**Useful commands:**
```bash
docker-compose logs -f      # View logs
docker-compose stop         # Stop containers
docker-compose down         # Stop and remove containers
docker-compose down -v      # Stop and remove containers + all data
```

### Prerequisites

- PHP 7.4 or higher
- [Composer](https://getcomposer.org/) - Install via Homebrew: `brew install composer`
- [Docker](https://www.docker.com/products/docker-desktop) (for local WordPress testing)

### Setup

```bash
# Install dependencies
composer install
```

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run tests with verbose output
./vendor/bin/phpunit --testdox
```

### Building Distribution ZIP

```bash
./build.sh
```

This creates a WordPress-installable ZIP file. Development files (tests, composer files, etc.) are automatically excluded.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Run tests to ensure everything works: `composer test`
4. Commit your changes (`git commit -m 'Add some amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Support

For bug reports and feature requests, please use the [GitHub Issues](https://github.com/jwilenius/go-tournament-reg/issues) page.

## Author

Jim Wilenius - [GitHub](https://github.com/jwilenius)
