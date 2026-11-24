# Statamic Content Backup

![Packagist version](https://flat.badgen.net/packagist/v/lucapon/statamic-content-backup/latest) ![Packagist Total Downloads](https://flat.badgen.net/packagist/dt/lucapon/statamic-content-backup)

Statamic Content Backup is a Statamic addon that allows control panel users to create, download, upload and restore content backups easily.

<img src="images/screenshot-1.png" />

## Features

- Create and restore full content backups from the control panel
- Back up database-stored content
- Upload and download backup archives (large backups are uploaded in chunks)
- Backup history with size and creation date
- Background backup creation via Laravel queues

## Installation

Install this addon running the following command from your project's root directory:

```bash
composer require lucapon/statamic-content-backup
```

After installation, publish the configuration file with:

```bash
php artisan vendor:publish --tag=statamic-content-backup-config
```

Run the following command to start the Laravel queue to handle backups creation in the background:

```bash
php artisan queue:work
```

## Usage

Once installed, the addon is available under **Utilities > Content Backup** in the Statamic control panel.

## Customization

You can customize which files and database tables are included in the backup by editing the configuration file located at: `config/statamic-content-backup.php`

### Uploading large backups

Backups are uploaded in chunks, so their size is not limited by PHP or the web server. Each chunk is 1 MB by default and must stay below `upload_max_filesize`, `post_max_size` and the web server body limit (e.g. nginx `client_max_body_size`). You can change it with the `upload_chunk_size` option.

Interrupted uploads older than 24 hours are deleted automatically when a new upload starts.

## Upgrading

### Upgrading to v3

If you upgrade to version `^2.0.0` or `^3.0.0`, it is recommended to republish the configuration file to ensure compatibility with the updated features. Run the following command:

```bash
php artisan vendor:publish --tag=statamic-content-backup-config --force
```

This will overwrite your old configuration file with the updated version. Be sure to review the new configuration file for any additional options or changes.

### Upgrading to v4

Version `4.x` requires **Statamic 6** (and Laravel 12). It is not compatible with earlier versions of Statamic.

```bash
composer require lucapon/statamic-content-backup:^4.0
```

**Permissions:** In v3, the addon used a custom permission (`statamic-content-backup-permission`). In v4, permissions are managed automatically by Statamic's utility system. The permission is now `access content_backup utility` and can be configured under **Users > Roles > Utilities** in the control panel.
