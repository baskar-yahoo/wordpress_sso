# WordPress SSO for webtrees

**Status:** This is a functional prototype and is **NOT READY FOR PRODUCTION USE**.

This module provides a seamless Single Sign-On (SSO) experience for webtrees, using a WordPress site as the identity provider.

## Features

- **Seamless Login**: When a user who is logged into WordPress visits webtrees, they are automatically logged into their webtrees account.
- **Single Log-Out (SLO)**: When a user logs out of webtrees, they are also logged out of WordPress.
- **Just-In-Time (JIT) Provisioning**: New user accounts can be automatically created in webtrees the first time a user logs in via SSO (this feature is configurable and requires admin approval for new users).

## Current State (Prototype)

The core SSO logic is functional but the module is missing key features required for a production environment. See the roadmap below.

## Installation and Configuration (for Testing Only)

1.  Place the `wordpress_sso` directory in your `webtrees/modules_v4` folder.
2.  Navigate to the module directory in your terminal:
    ```sh
    cd webtrees/modules_v4/wordpress_sso
    ```
3.  Install the required dependencies:
    ```sh
    composer install
    ```
4.  In your webtrees `data/config.ini.php` file, add the following settings. You must get these values from your WordPress OAuth2 Server plugin.
    ```ini
    sso_enabled="1"
    sso_allow_creation="1"
    sso_client_id="your_wordpress_client_id"
    sso_client_secret="your_wordpress_client_secret"
    sso_url_authorize="https://your-site.com/wordpress/oauth/authorize"
    sso_url_access_token="https://your-site.com/wordpress/oauth/token"
    sso_url_resource_owner_details="https://your-site.com/wordpress/oauth/me"
    sso_url_logout="https://your-site.com/wordpress/wp-login.php?action=logout"
    ```
5.  Go to the webtrees Control Panel -> Modules and enable the "WordPress SSO" module.

## Roadmap to Production

- [ ] **Configuration UI**: Create a settings page in the webtrees control panel.
- [ ] **Robust Error Handling**: Add error handling for API/network failures.
- [ ] **Performance Optimization**: Improve the efficiency of finding users by their WordPress ID.
- [ ] **Admin Notifications**: Implement email notifications for new user creation.
- [ ] **Language and Translation Support**.
