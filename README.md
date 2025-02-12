![Packagist version](https://flat.badgen.net/packagist/v/lucapon/statamic-content-backup/latest) ![Packagist Total Downloads](https://flat.badgen.net/packagist/dt/lucapon/statamic-content-backup)


# Statamic Content Backup

Statamic Content Backup is a Statamic addon that allows control panel users to download and restore content backups easily.

<img src="images/image.png" />


## New features added

- Added support for backing up database-stored content.

## Installation

Install this addon running the following command from your project's root directory:

```bash
composer require lucapon/statamic-content-backup
```

After installation, publish the configuration file with:

```bash
php artisan vendor:publish --tag=statamic-content-backup
```

## Customization

You can customize which files and database tables are included in the backup by editing the configuration file located at: `config/statamic-content-backup.php`

## Upgrading

If you upgrade to version `^2.0.0`, it is recommended to republish the configuration file to ensure compatibility with the updated features. Run the following command:

```bash
php artisan vendor:publish --tag=statamic-content-backup --force
```

This will overwrite your old configuration file with the updated version. Be sure to review the new configuration file for any additional options or changes.
