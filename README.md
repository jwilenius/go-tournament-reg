# Go Tournament Registration

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

WordPress plugin for managing Go tournament player registrations with automatic player strength sorting.

## Features

- **Player strength sorting**: Dan (9d→1d) then Kyu (1k→30k)
- **Multi-tournament support**: Run multiple independent tournaments simultaneously
- **International support**: ISO country selection
- **EGD integration**: Optional European Go Database number field
- **CSV export**: Download registration lists for tournament management
- **Security hardened**: ABSPATH protection, capability checks, XSS protection

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

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Development

### Prerequisites

- PHP 7.4 or higher
- [Composer](https://getcomposer.org/) - Install via Homebrew: `brew install composer`

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
