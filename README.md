# EAD XML Importer for WordPress

WordPress plugin to import EAD (Encoded Archival Description) XML files into custom post types.

## Features

- Import EAD XML files from URLs
- Convert EAD XML to custom post types
- Support for ACF repeater fields
- Fallback for non-ACF installations
- Automated imports via WP Cron
- Customizable field mappings

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Optional: Advanced Custom Fields Pro plugin

## Installation

1. Download the plugin zip file
2. Upload to your WordPress site
3. Activate the plugin
4. Configure settings under Settings > EAD XML Importer

## Usage

### Manual Import

```php
use EAD_XML_Importer\DataLoader;

// Create a new loader instance
$loader = new DataLoader('https://example.com/ead.xml');

// Convert XML to array/json
$data = $loader->convert();

// Import to custom post type
$loader->populateCustomPostType('archive');

// Show import results as HTML
$loader->htmlTable();
```

### Automated Import

Configure your XML sources in the WordPress admin under Settings > EAD XML Importer.

## Development

### Building from Source

1. Clone the repository
2. Create a new branch for your feature
3. Make your changes
4. Test thoroughly
5. Create a pull request

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## Plugin Structure

```bash
ead-xml-importer/
├── includes/
│   ├── class-data-loader.php
│   ├── class-loader.php
│   └── functions.php
├── admin/
│   ├── css/
│   ├── js/
│   └── class-admin.php
├── languages/
│   └── ead-xml-importer.pot
├── index.php
├── ead-xml-importer.php
├── uninstall.php
├── README.md
└── readme.txt
```

## License

GPLv2 or later
