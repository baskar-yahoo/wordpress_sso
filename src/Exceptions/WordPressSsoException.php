<?php

namespace Webtrees\WordPressSso\Exceptions;

/**
 * Base exception for WordPress SSO module
 */
class WordPressSsoException extends \Exception
{
}

/**
 * Configuration error exception
 */
class ConfigurationException extends WordPressSsoException
{
}

/**
 * State validation error (CSRF protection)
 */
class StateValidationException extends WordPressSsoException
{
}

/**
 * Token exchange error
 */
class TokenExchangeException extends WordPressSsoException
{
}

/**
 * User data validation error
 */
class UserDataException extends WordPressSsoException
{
}

/**
 * User creation error
 */
class UserCreationException extends WordPressSsoException
{
}

/**
 * Login error
 */
class LoginException extends WordPressSsoException
{
}

/**
 * Security violation error
 */
class SecurityException extends WordPressSsoException
{
}
