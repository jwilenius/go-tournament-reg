# Go Tournament Registration

WordPress plugin for managing Go tournament player registrations with automatic player strength sorting.

## Installation

1. Copy the `wp-go-reg` folder to `wp-content/plugins/`
2. Go to WordPress Admin → Plugins
3. Activate "Go Tournament Registration"

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

## Features

- Player strength sorting: Dan (9d→1d) then Kyu (1k→30k)
- Separate data per tournament
- Optional EGD number field
- ISO country selection
- CSV export
