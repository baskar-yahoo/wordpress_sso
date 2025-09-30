# WordPress SSO for webtrees

**Version:** 1.0.0
**Status:** Release Candidate

This module provides a seamless Single Sign-On (SSO) experience for webtrees, using a WordPress site as the identity provider.

---

## Features

- **Seamless Login**: When a user who is logged into WordPress visits webtrees, they are automatically logged into their webtrees account.
- **Single Log-Out (SLO)**: When a user logs out of webtrees, they are also logged out of WordPress.
- **Just-In-Time (JIT) Provisioning**: New user accounts can be automatically created in webtrees the first time a user logs in via SSO.
- **Secure by Default**: New accounts created via SSO require administrator approval before the user can log in.
- **Administrator Notifications**: Sends an email to all site administrators when a new user is created and requires approval.

## Installation

1.  Place the `wordpress_sso` directory in your `webtrees/modules_v4` folder.
2.  Navigate to the module directory in your server's command line:
    ```sh
    cd /path/to/webtrees/modules_v4/wordpress_sso
    ```
3.  Install the required dependencies using Composer:
    ```sh
    composer install
    ```
4.  In webtrees, go to **Control Panel > Modules**. Find the **WordPress SSO** module and enable it.

## Configuration

Configuration is handled entirely within the webtrees control panel. Before configuring this module, you must have a functioning OAuth2 server plugin installed and configured on your WordPress site.

1.  **In WordPress:**
    - Go to the settings for your OAuth2 server plugin.
    - Create a new "Client" application.
    - You will be asked for a **Redirect URI** or **Callback URL**. You will get this value from the webtrees module settings in the next step.

2.  **In webtrees:**
    - Go to **Control Panel > Modules**.
    - Find **WordPress SSO** and click the **Configure** button.

3.  **Fill out the settings:**

    - **Enable Seamless SSO**: Check this box to activate the module. Unauthenticated users will be automatically redirected to WordPress to log in.
    - **Allow New User Creation**: Check this box if you want webtrees to automatically create accounts for users who exist in WordPress but not in webtrees. **Note:** These new accounts will require administrator approval before they can be used.

    - **Client ID**: Copy this value from your WordPress OAuth2 client settings.
    - **Client Secret**: Copy this value from your WordPress OAuth2 client settings.

    - **Authorization URL**: The authorization endpoint URL from your WordPress OAuth2 server (e.g., `https://my-site.com/oauth/authorize`).
    - **Access Token URL**: The token endpoint URL from your WordPress OAuth2 server (e.g., `https://my-site.com/oauth/token`).
    - **Resource Owner Details URL**: The user info endpoint URL from your WordPress OAuth2 server (e.g., `https://my-site.com/oauth/me`).
    - **Logout URL**: The logout URL for your WordPress site (e.g., `https://my-site.com/wp-login.php?action=logout`).

4.  **Update WordPress Client:**
    - At the bottom of the webtrees configuration page, you will see a read-only field labeled **Callback URL**.
    - Copy this URL.
    - Go back to your WordPress OAuth2 client settings and paste this value into the **Redirect URI** / **Callback URL** field.

5.  Click **Save** in webtrees.

## User Workflow

Once configured, the login process is fully automatic. When a new user from WordPress accesses webtrees for the first time:

1. They are redirected to webtrees and the SSO module attempts to log them in.
2. The module sees they are a new user and creates a new, unapproved account for them in webtrees.
3. An email is sent to all webtrees administrators, notifying them of the new account.
4. The user's login attempt fails with a message stating that their account is awaiting approval.
5. A site administrator must go to **Control Panel > Users**, find the new account, and approve it.
6. On their next visit to webtrees, the user will be logged in automatically and seamlessly.