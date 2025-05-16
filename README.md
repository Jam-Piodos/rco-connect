# RCO Connect

RCO Connect is a web application for managing Recognized Campus Organizations (RCOs), their activities, and events.

## Features

- User registration and authentication
- Profile management with profile pictures
- Event management system (create, update, delete)
- Time conflict detection for events
- Event archiving for deleted events
- User activity tracking
- Admin dashboard with RCO rankings
- Analytics and reporting

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache or Nginx)
- Composer (PHP package manager)

## Installation

1. Clone the repository to your web server's document root:

   ```
   git clone https://github.com/your-username/rco-connect.git
   ```

   Or download and extract the ZIP file.

2. Configure your database connection by editing `config.php`:

   ```php
   define('DB_HOST', 'localhost');     // Your database host
   define('DB_USER', 'your_username'); // Your database username
   define('DB_PASS', 'your_password'); // Your database password
   define('DB_NAME', 'rco_connect');   // Your database name
   ```

3. Initialize the system by visiting `initialize_system.php` in your web browser:

   ```
   http://your-domain.com/rco-connect/initialize_system.php
   ```

   This will:
   - Create necessary directories
   - Set up database tables
   - Create an admin account

4. Log in with the default admin credentials:
   - Username: admin@rcoconnect.com
   - Password: admin123

   **Important**: Change the admin password immediately after first login!

## Directory Structure

- `admin/` - Admin dashboard and functionality
- `admin_components/` - Admin UI components
- `user/` - User dashboard and functionality
- `user_components/` - User UI components
- `shared_components/` - Shared components between admin and user interfaces
- `uploads/` - User uploaded files (profile pictures, etc.)
- `vendor/` - Composer dependencies

## User Roles

- **Admin**: Manages users, monitors activities, and views analytics
- **User**: Represents an RCO, can create and manage events

## Security Considerations

- All passwords are securely hashed using PHP's `password_hash()` function
- Input validation and sanitization is performed throughout the application
- User sessions are protected with appropriate security measures

## Development

To contribute to the project:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For questions or support, please contact [your-email@example.com](mailto:your-email@example.com). 