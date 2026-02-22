# Statamic Content Backup

![Packagist version](https://flat.badgen.net/packagist/v/lucapon/statamic-content-backup/latest) ![Packagist Total Downloads](https://flat.badgen.net/packagist/dt/lucapon/statamic-content-backup)

Statamic Content Backup is a Statamic addon that allows control panel users to create, download, upload and restore content backups easily.

<img src="images/screenshot-1.png" />

## Features

- Create and restore full content backups from the control panel
- Back up database-stored content
- Upload and download backup archives
- Backup history with size and creation date
- Background backup creation via Laravel queues

## Installation

Install this addon running the following command from your project's root directory:

```bash
composer require lucapon/statamic-content-backup
```

After installation, publish the configuration file with:

```bash
php artisan vendor:publish --tag=statamic-content-backup
```

Run the following command to start the Laravel queue to handle backups creation in the background:

```bash
php artisan queue:work
```

## Usage

Once installed, the addon is available under **Utilities > Content Backup** in the Statamic control panel.

## Customization

You can customize which files and database tables are included in the backup by editing the configuration file located at: `config/statamic-content-backup.php`

## Upgrading

### Upgrading to v3

If you upgrade to version `^2.0.0` or `^3.0.0`, it is recommended to republish the configuration file to ensure compatibility with the updated features. Run the following command:

```bash
php artisan vendor:publish --tag=statamic-content-backup --force
```

This will overwrite your old configuration file with the updated version. Be sure to review the new configuration file for any additional options or changes.

### Upgrading to v4

Version `4.x` requires **Statamic 6** (and Laravel 12). It is not compatible with earlier versions of Statamic.

```bash
composer require lucapon/statamic-content-backup:^4.0
```
