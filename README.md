# Clock.it - Advanced Time Tracking &amp; Productivity Application

**Version:** 2.0.0  
**License:** MIT

## 🎯 Overview

Clock.it is a full-featured, advanced time tracking and productivity management application built with PHP, SQLite/MySQL, and modern JavaScript. It provides comprehensive tools for tracking work sessions, managing projects, analyzing productivity metrics, and exporting data.

## ✨ Features

### Core Functionality
- **⏱️ Advanced Stopwatch**: Real-time time tracking with pause functionality
- **☕ Break Tracking**: Track productive vs. non-productive time
- **📁 Project Management**: Create and organize projects with custom colors
- **📊 Time Sessions**: Detailed session logging with descriptions
- **📈 Reports & Analytics**: Comprehensive reporting with data visualization
- **📥 Data Export**: Export sessions to CSV format
- **📅 Calendar View**: Visual calendar showing tracked time

### Security Features
- **CSRF Token Protection**: All forms protected with CSRF tokens
- **Rate Limiting**: Prevents brute-force login attacks (5 attempts per 15 minutes)
- **Password Security**: Bcrypt hashing with strength validation
- **Session Management**: Secure session handling with timeout
- **Activity Logging**: All user actions tracked for audit purposes
- **Input Validation**: Comprehensive sanitization and validation

### Advanced Features
- **🌓 Dark Mode**: Fully implemented dark/light theme toggle
- **👥 User Management**: Admin panel for managing users
- **📋 Activity Logs**: Complete audit trail of system actions
- **⚡ Real-time Updates**: AJAX-based session updates
- **🎨 Responsive Design**: Mobile-friendly UI
- **🔐 Admin Dashboard**: System overview and statistics
- **🌐 RESTful API**: API endpoints for session management

### UI/UX Enhancements
- Modern card-based layout
- Smooth animations and transitions
- Mobile responsive design
- Dark mode with system preference detection
- Accessibility features
- Toast notifications
- Loading states and spinners

## 📋 Requirements

- PHP 7.4+
- SQLite3 (built-in) or MySQL 5.7+
- Web Server (Apache, Nginx)
- Modern web browser with JavaScript enabled

## 🚀 Installation

### Quick Start

1. **Clone/Extract Files**
   ```bash
   cd /path/to/clock-it
   ```

2. **Set Permissions**
   ```bash
   chmod 755 /path/to/clock-it
   chmod 777 /path/to/clock-it/data (for SQLite database)
   ```

3. **Access Application**
   Navigate to `http://localhost/clock-it/index.php`

4. **Create Demo Account**
   - Email: `demo@example.com`
   - Password: `Demo123!@` (must contain upper, lower, number)

### Database Setup

The application automatically creates the SQLite database on first access. Tables created:
- `users` - User accounts and preferences
- `projects` - Project categories
- `time_sessions` - Individual tracking sessions
- `breaks` - Break tracking within sessions
- `activity_logs` - Audit trail
- `login_attempts` - Rate limiting
- `sessions` - Session management
- `team_members` - Team features (future)

## 📁 Project Structure

```
clock-it/
├── config/
│   ├── init.php           # Application initialization
│   ├── database.php       # Database configuration & helpers
│   ├── security.php       # Security utilities (CSRF, rate limiting)
│   └── html-helper.php    # HTML component rendering
├── api/
│   ├── sessions/
│   │   ├── save.php       # Save time session endpoint
│   │   └── today.php      # Get today's sessions endpoint
│   └── user/
│       └── theme.php      # Theme preference endpoint
├── admin/
│   └── index.php          # Admin dashboard
├── assets/
│   ├── css/
│   │   ├── style.css      # Main stylesheet
│   │   ├── responsive.css # Responsive design
│   │   └── dark-mode.css  # Dark mode styles
│   └── js/
│       ├── main.js        # Core utilities
│       └── theme.js       # Theme management
├── data/
│   └── clock-it.db        # SQLite database (auto-created)
├── index.php              # Login page
├── signup.php             # Registration page
├── dashboard.php          # Main dashboard
├── stopwatch.php          # Advanced time tracker
├── projects.php           # Project management
├── reports.php            # Analytics &amp; reports
├── profile.php            # User profile
├── settings.php           # User settings
├── logout.php             # Logout handler
└── calendar.php           # Calendar view (optional)
```

## 🔐 Authentication

### Login
- Email-based authentication
- Password hashing with bcrypt
- Rate limiting (5 attempts per 15 minutes)
- Session timeout (1 hour of inactivity)
- CSRF protection on all forms

### Password Requirements
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- Special characters recommended

### Demo Credentials
```
Email: demo@example.com
Password: Demo123!@
```

## 👥 User Roles

### Regular User
- Create and manage personal projects
- Track time sessions
- View personal reports
- Manage profile and settings
- Export personal data
- Toggle dark/light theme

### Admin User
- All regular user features
- View all users and activity logs
- Manage user roles (promote/demote admin)
- Delete user accounts
- System statistics and overview
- Activity log monitoring

## 📊 Usage Guide

### Tracking Time

1. **Start a Session**
   - Go to Stopwatch
   - Select a project (required)
   - Enter what you're working on (optional)
   - Click "Start" button
   - Timer begins

2. **Take Breaks**
   - Click "Take Break" button
   - Break time tracked separately
   - Click "Resume" when done

3. **Complete Session**
   - Click "Stop" when finished
   - Session automatically saved to database
   - Enter details if needed
   - View in "Today's Sessions"

### Managing Projects

1. **Create Project**
   - Go to Projects page
   - Click "New Project"
   - Enter name and description
   - Choose custom color
   - Click Create

2. **Track Project Time**
   - Select project when tracking
   - All sessions linked to project
   - View project statistics
   - Export project-specific data

### Viewing Reports

1. **Generate Report**
   - Go to Reports page
   - Filter by project or date range
   - View time statistics
   - Analyze productivity trends

2. **Export Data**
   - Click "Export CSV" button
   - Download detailed session data
   - Use in spreadsheets or analysis tools

### Settings

1. **Theme**
   - Toggle between light and dark modes
   - Preference saved automatically
   - Respects system preference on first visit

2. **Notifications**
   - Enable/disable Toast notifications
   - Alerts for important events
   - Activity confirmations

3. **Profile**
   - Update full name
   - Change password
   - View personal statistics
   - Export or delete data

## 🔌 REST API

### Save Time Session
```
POST /api/sessions/save.php
Content-Type: application/json

{
  "project_id": 1,
  "description": "Working on documentation",
  "duration_seconds": 3600,
  "break_seconds": 300
}
```

### Get Today's Sessions
```
GET /api/sessions/today.php
```

Response:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "project_name": "Project A",
      "description": "Task description",
      "duration_seconds": 3600
    }
  ]
}
```

## 🛠️ Security Best Practices

### Implemented Protections
- ✅ CSRF Token validation on all POST requests
- ✅ SQL Injection prevention with PDO prepared statements
- ✅ XSS protection with htmlspecialchars()
- ✅ Bcrypt password hashing (cost: 12)
- ✅ Rate limiting on login attempts
- ✅ Secure session cookies (HttpOnly, SameSite=Strict)
- ✅ Activity logging for all critical actions
- ✅ IP address tracking
- ✅ Input validation and sanitization

### Recommendations
- Use HTTPS in production
- Keep PHP and dependencies updated
- Regular backups of SQLite database
- Monitor activity logs for suspicious behavior
- Change demo account password
- Enable security headers (Content-Security-Policy, etc.)

## 📈 Database Schema

### Users Table
```sql
id (PK), email, password_hash, full_name, created_at, updated_at, is_admin, theme, notifications_enabled
```

### Time Sessions Table
```sql
id (PK), user_id (FK), project_id (FK), start_time, end_time, duration_seconds, description, notes, created_at
```

### Projects Table
```sql
id (PK), user_id (FK), name, description, color, is_active, created_at
```

### Activity Logs Table
```sql
id (PK), user_id (FK), action, entity_type, entity_id, details, ip_address, created_at
```

## 🎨 Customization

### Changing Colors
Edit CSS variables in `assets/css/style.css`:
```css
:root {
  --primary: #667eea;
  --primary-dark: #764ba2;
  --success: #27ae60;
  /* ... other colors ... */
}
```

### Adding New Features
1. Update database schema in `config/database.php`
2. Add API endpoints in `api/` directory
3. Create UI pages in root directory
4. Add routes to navigation in `config/html-helper.php`

## 🐛 Troubleshooting

### Database Connection Issues
- Check `data/` directory permissions (777)
- Verify SQLite3 PHP extension is installed

### Login Problems
- Clear browser cookies and cache
- Check rate limiting - wait 15 minutes
- Verify email and password are correct

### Import Issues
- Check `config/database.php` is loaded
- Verify require_once statements in files
- Check file paths are correct

### CSS/JS Not Loading
- Check file paths in HTML
- Verify files exist in `assets/` folder
- Clear browser cache
- Check console for errors

## 📚 Development

### Adding Authentication to New Pages
```php
<?php
require_once 'config/init.php';
requireAuth();  // Blocks unauthenticated users

$user = getCurrentUser();
// Now you can use $user data
?>
```

### Creating Admin-Only Pages
```php
<?php
require_once 'config/init.php';
requireAdmin();  // Requires admin role

// Admin-only code here
?>
```

### Logging Activity
```php
ActivityLogger::log($userId, 'ACTION_NAME', 'entity_type', $entityId);
```

### API Response Format
```php
// Success
ResponseHelper::success('Operation successful', ['data' => 'value'], 200);

// Error
ResponseHelper::error('Something went wrong', 400);
```

## 📝 Changelog

### Version 2.0.0 (Current)
- ✨ Advanced database integration (SQLite/MySQL)
- ✨ CSRF token protection
- ✨ Rate limiting on login
- ✨ Break tracking
- ✨ Project management system
- ✨ Comprehensive reporting
- ✨ Data export (CSV)
- ✨ Admin dashboard
- ✨ Activity logging
- ✨ Dark mode support
- ✨ User profile management
- ✨ REST API endpoints
- ✨ Enhanced UI/UX
- 🐛 Fixed security vulnerabilities
- 🐛 Improved error handling

## 🙋 Support

For issues, feature requests, or questions:
1. Check this README
2. Review the code comments
3. Check browser console for JavaScript errors
4. Review activity logs for system issues

## 📄 License

MIT License - Feel free to use, modify, and distribute.

## 🎉 Credits

Built with modern web technologies:
- PHP 7.4+ for backend
- SQLite3 for database
- Vanilla JavaScript (no frameworks)
- CSS3 with responsive design
- Security best practices

---

**Enjoy tracking your time and boosting productivity!** ⏱️
