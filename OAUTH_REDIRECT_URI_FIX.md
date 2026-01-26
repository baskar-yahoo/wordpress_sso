# OAuth Redirect URI Fix (Resolved)

**Status:** Resolved in v2.0.0

## Update
As of version 2.0.0, the module automatically handles URI decoding. You no longer need to manually encode slashes (`%2F`) in your WordPress OAuth configuration.

Please use standard forward slashes (`/`) in your Redirect URI.

Example:
`.../index.php?route=/wordpress-sso/callback`
