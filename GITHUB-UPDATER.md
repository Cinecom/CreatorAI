# GitHub Updater for Creator AI Plugin

This plugin now includes automatic GitHub-based updates, allowing you to receive updates directly from the Cinecom/CreatorAI repository without publishing to WordPress.org.

## How It Works

The GitHub updater system:

1. **Checks for updates** by comparing the version in your main plugin file with the version in GitHub
2. **Shows notifications** in your WordPress admin when updates are available
3. **Handles downloads** from GitHub's ZIP archive
4. **Integrates seamlessly** with WordPress's built-in plugin update system

## Configuration

The updater is configured automatically with these settings:
- **GitHub Username**: Cinecom
- **Repository**: CreatorAI
- **Branch**: main
- **Update Check**: Daily (automatic)

## Features

### Automatic Update Checks
- Checks for updates daily via WordPress cron
- Compares your current version with the latest version in GitHub
- Shows update notifications in the WordPress admin area

### Manual Update Checks
- Go to **Creator AI > Settings > API Settings**
- Find the **GitHub Updates** section
- Click **"Check for Updates"** to manually check for new versions
- View your current version and repository information

### Standard WordPress Integration
- Updates appear in **Plugins > Updates** just like regular WordPress plugins
- Install updates through the standard WordPress interface
- View changelog information before updating
- Rollback capability through WordPress

### Security Features
- Uses WordPress's built-in update system for secure installations
- Validates nonces and user permissions
- Sanitizes all input data
- Rate limiting for API requests

## Usage

### Installing Updates

1. **Automatic notification**: You'll see update notifications in your admin area
2. **Manual check**: Use the "Check for Updates" button in settings
3. **Install**: Go to Plugins page and install the update like any other plugin
4. **Activate**: The plugin will remain activated during the update process

### Viewing Changes

- Click "View Repository" to see the GitHub project
- View changelog in the plugin update details
- Compare versions directly on GitHub

## Technical Details

### Files Added
- `includes/github-updater.php` - Main updater class
- Updates to `creator-ai.php` - Integration code
- Updates to `pages/settings.php` - Admin interface
- Updates to `js/settings.js` - Frontend functionality

### WordPress Hooks Used
- `pre_set_site_transient_update_plugins` - Inject update information
- `plugins_api` - Provide plugin information
- `upgrader_post_install` - Handle post-installation tasks
- `wp` - Schedule daily update checks
- Daily cron job for automatic update checking

### Transients Used
- `{plugin_slug}_github_data` - Cached version data (1 hour)
- `update_plugins` - WordPress update transient

## Troubleshooting

### Updates Not Showing

1. Check if your server can access GitHub API
2. Verify the repository is public (Cinecom/CreatorAI)
3. Try manually checking for updates in settings
4. Clear transients: deactivate and reactivate the plugin

### Update Failed

1. Ensure sufficient permissions for file operations
2. Check available disk space
3. Verify network connectivity to GitHub
4. Try updating manually via FTP if needed

### GitHub API Limits

- The updater uses GitHub's public API (60 requests per hour per IP)
- Caches results for 1 hour to minimize API calls
- Rate limiting is built into the WordPress functions

## Development Notes

### Version Detection
The updater reads the version from the plugin header comment:
```php
Version: 4.7.0
```

Make sure to update this version number in `creator-ai.php` when releasing updates.

### Branch Management
- Updates are pulled from the `main` branch
- The entire repository is downloaded as a ZIP file
- No release tags are required

### Testing
Test the updater by:
1. Temporarily changing your local version to a lower number
2. Using the manual update check
3. Verifying the update notification appears
4. Testing the update installation process

## Security Considerations

- Only administrators can check for or install updates
- All AJAX requests are nonce-protected
- File operations use WordPress's secure file handling
- No sensitive data is transmitted to external servers
- Repository must remain public for the updater to work

## Support

If you encounter issues with the GitHub updater:

1. Check the WordPress debug log for error messages
2. Verify your server can access api.github.com
3. Ensure the Creator AI plugin is properly activated
4. Try deactivating/reactivating the plugin to reset transients

The updater integrates seamlessly with WordPress's existing plugin system, so standard WordPress troubleshooting techniques apply.