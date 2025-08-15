# Deployment Guide

This guide will help you deploy the Recipe Box application to your web server.

## Prerequisites

- Web server (Apache, Nginx, or similar)
- PHP 7.4 or higher
- File system write permissions
- Git (for deployment)

## Deployment Steps

### 1. Server Setup

Ensure your server has the following PHP extensions enabled:
- `fileinfo` (for image upload validation)
- `curl` (for URL fetching functionality)
- `json` (for recipe data handling)

### 2. File Permissions

Set the correct permissions for the application directories:

```bash
# Make uploads directory writable
chmod 755 uploads/recipes/
chmod 644 uploads/.htaccess

# Make data directory writable for recipe storage
chmod 755 data/
chmod 644 data/recipes/

# Set proper ownership (replace www-data with your web server user)
chown -R www-data:www-data uploads/
chown -R www-data:www-data data/
```

### 3. Configuration

#### Admin PIN Setup
Create a secure admin PIN by either:

**Option A: Environment Variable**
```bash
export RECIPE_ADMIN_PIN="your-secure-pin-here"
```

**Option B: File-based PIN**
```bash
echo "your-secure-pin-here" > data/admin_pin.txt
chmod 600 data/admin_pin.txt
```

#### PHP Configuration
Ensure your `php.ini` has appropriate limits:
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20
file_uploads = On
```

### 4. Web Server Configuration

#### Apache (.htaccess)
The application includes an `.htaccess` file for uploads security. Ensure Apache has `mod_rewrite` enabled.

#### Nginx
Add this to your server block:
```nginx
location /uploads/ {
    try_files $uri $uri/ =404;
    
    # Prevent execution of uploaded files
    location ~* \.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$ {
        deny all;
    }
}
```

### 5. Security Considerations

- **HTTPS**: Always use HTTPS in production
- **Admin PIN**: Use a strong, unique PIN
- **File Uploads**: The application validates file types, but consider additional server-side security
- **Directory Listing**: Ensure sensitive directories are not publicly accessible

### 6. Testing

After deployment, test the following:
- [ ] Recipe creation and editing
- [ ] Image uploads
- [ ] Search functionality
- [ ] Tag system
- [ ] Review system
- [ ] Admin PIN access

### 7. Monitoring

Monitor these aspects:
- File upload success rates
- Server error logs
- Disk space usage (for uploads)
- PHP error logs

## Troubleshooting

### Common Issues

**Uploads not working:**
- Check file permissions
- Verify PHP upload settings
- Check server error logs

**Admin access denied:**
- Verify PIN configuration
- Check file permissions for PIN file
- Clear browser cache

**Images not displaying:**
- Check file paths
- Verify .htaccess configuration
- Check file permissions

### Performance Tips

- Enable PHP OPcache
- Use a CDN for static assets
- Consider image optimization for uploads
- Implement caching for recipe listings

## Support

For deployment issues:
1. Check server error logs
2. Verify file permissions
3. Test with a simple PHP file
4. Check PHP version compatibility

---

**Happy Cooking! 🍳**
