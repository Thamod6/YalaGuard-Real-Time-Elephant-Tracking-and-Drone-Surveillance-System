# 🐘 YalaGuard - Elephant Monitoring System

A comprehensive PHP-based web application for monitoring elephant activities, managing alerts, and providing real-time camera feeds for wildlife conservation.

## ✨ Features

- **User Authentication System** - Secure login and registration
- **Dashboard** - Centralized monitoring and control center
- **Camera Management** - Live drone and IP camera feeds
- **Recording System** - Video recording and playback capabilities
- **Alert Management** - Real-time elephant detection alerts
- **Responsive Design** - Works on all devices
- **Modern UI/UX** - Clean, professional interface

## 🏗️ Project Structure

```
YalaGuard/
├── assets/
│   ├── css/
│   │   └── style.css          # Main stylesheet for all pages
│   └── js/
│       └── main.js            # Common JavaScript functionality
├── api/
│   ├── alerts.php             # Alert management API
│   ├── auth/
│   │   ├── login.php          # Login API endpoint
│   │   ├── logout.php         # Logout handler
│   │   └── register.php       # Registration API endpoint
│   └── cameras/
│       ├── config.php         # Camera configuration API
│       └── recording.php      # Camera recording API
├── config/
│   └── database.php           # Database configuration
├── pages/
│   ├── dashboard.php          # Main dashboard
│   ├── camera.php             # Camera management interface
│   ├── login.php              # Login page
│   └── register.php           # Registration page
├── vendor/                    # Composer dependencies
├── .env                       # Environment configuration
├── composer.json              # PHP dependencies
├── index.php                  # Main landing page
├── setup-cameras.php          # Camera system setup script
└── README.md                  # This file
```

## 🚀 Quick Start

### Prerequisites

- PHP 8.0 or higher
- MongoDB (local or Atlas)
- Composer
- Web server (Apache/Nginx) or PHP built-in server

### Installation

1. **Clone or download the project**
   ```bash
   git clone <repository-url>
   cd YalaGuard
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your MongoDB connection details
   ```

4. **Set up the camera system**
   ```bash
   php setup-cameras.php
   ```

5. **Start the development server**
   ```bash
   php -S localhost:8000
   ```

6. **Access the application**
   - Main page: http://localhost:8000
   - Login: http://localhost:8000/pages/login.php
   - Dashboard: http://localhost:8000/pages/dashboard.php
   - Camera System: http://localhost:8000/pages/camera.php

## 🎨 CSS Architecture

The project uses a **centralized CSS approach** with `assets/css/style.css` containing all styles:

- **Modular Structure** - Organized by component (header, dashboard, camera, forms)
- **Responsive Design** - Mobile-first approach with media queries
- **Consistent Theming** - Unified color scheme and typography
- **Component Classes** - Reusable CSS classes for common elements

### CSS Organization

```css
/* Reset and Base Styles */
/* Header Styles */
/* Container and Layout */
/* Button Styles */
/* Dashboard Styles */
/* Camera System Styles */
/* Fullscreen Overlay */
/* Login and Registration Styles */
/* Home Page Styles */
/* Responsive Design */
```

## 🔧 JavaScript Architecture

The project uses `assets/js/main.js` for common functionality:

- **Utility Functions** - Date formatting, validation, storage
- **API Utilities** - HTTP request helpers
- **Camera Utilities** - Stream management and snapshots
- **UI Utilities** - Loading spinners, notifications, modals
- **Performance Tools** - Debouncing and throttling

### JavaScript Features

```javascript
YalaGuard.showNotification('Message', 'success');
YalaGuard.formatDate(new Date());
YalaGuard.validateEmail('user@example.com');
YalaGuard.api.get('/api/endpoint');
YalaGuard.camera.checkStreamAccess(url);
```

## 📹 Camera System

### Supported Camera Types

- **Drone Cameras** - Mobile aerial monitoring
- **IP Cameras** - Fixed position monitoring
- **RTSP Streams** - Real-time video streaming
- **HTTP Snapshots** - Image capture and display

### Camera Features

- Live feed monitoring
- Recording start/stop
- Full-screen viewing
- Status monitoring
- Location tracking

## 🗄️ Database Collections

- **users** - User accounts and authentication
- **cameras** - Camera configurations and settings
- **recordings** - Video recording metadata
- **alerts** - Elephant detection alerts

## 🔐 Security Features

- Session-based authentication
- Password hashing with `password_hash()`
- Input validation and sanitization
- CORS headers for API endpoints
- Secure database connections

## 📱 Responsive Design

- Mobile-first approach
- Flexible grid layouts
- Touch-friendly controls
- Optimized for all screen sizes

## 🚀 Performance Features

- Optimized CSS with minimal redundancy
- Efficient JavaScript with utility functions
- Debounced and throttled event handlers
- Optimized database queries

## 🛠️ Development

### Adding New Pages

1. Create the PHP file in the appropriate directory
2. Link to the main CSS: `<link rel="stylesheet" href="../assets/css/style.css">`
3. Include the main JS: `<script src="../assets/js/main.js"></script>`
4. Use existing CSS classes for consistency

### Adding New Styles

1. Add styles to `assets/css/style.css`
2. Follow the existing organization pattern
3. Include responsive design considerations
4. Test across different devices

### Adding New JavaScript

1. Add functions to `assets/js/main.js`
2. Follow the existing namespace pattern
3. Include error handling and validation
4. Test across different browsers

## 📋 API Endpoints

### Authentication
- `POST /api/auth/login.php` - User login
- `POST /api/auth/register.php` - User registration
- `GET /api/auth/logout.php` - User logout

### Cameras
- `GET /api/cameras/config.php` - List cameras
- `POST /api/cameras/config.php` - Add camera
- `PUT /api/cameras/config.php?id={id}` - Update camera
- `DELETE /api/cameras/config.php?id={id}` - Remove camera

### Recordings
- `GET /api/cameras/recording.php` - List recordings
- `POST /api/cameras/recording.php` - Start/stop recording
- `DELETE /api/cameras/recording.php?id={id}` - Delete recording

### Alerts
- `GET /api/alerts.php` - List alerts
- `POST /api/alerts.php` - Create alert

## 🐛 Troubleshooting

### Common Issues

1. **MongoDB Connection Failed**
   - Check `.env` file configuration
   - Verify network access and credentials
   - Run `php setup-cameras.php` to test connection

2. **CSS Not Loading**
   - Verify file paths in HTML
   - Check file permissions
   - Clear browser cache

3. **JavaScript Errors**
   - Check browser console for errors
   - Verify file paths in HTML
   - Ensure PHP server is running

### Debug Tools

- `setup-cameras.php` - Test database connection
- `test-mongodb.php` - Verify MongoDB setup
- Browser Developer Tools - Debug CSS/JS issues

## 🤝 Contributing

1. Follow the existing code structure
2. Maintain CSS and JavaScript separation
3. Test across different devices and browsers
4. Include proper error handling
5. Document new features

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

For support and questions:
- Check the troubleshooting section
- Review the API documentation
- Test with the provided setup scripts

---

**YalaGuard** - Protecting elephants, preserving nature 🐘🌿
