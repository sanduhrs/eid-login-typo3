<?php
/**
 * This file is part of php-saml.
 *
 * (c) Ecsec\Eidlogin\Dep\OneLogin Inc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Ecsec\Eidlogin\Dep\OneLogin
 * @author  Ecsec\Eidlogin\Dep\OneLogin Inc <saml-info@onelogin.com>
 * @license MIT https://github.com/onelogin/php-saml/blob/master/LICENSE
 * @link    https://github.com/onelogin/php-saml
 */

namespace Ecsec\Eidlogin\Dep\OneLogin\Saml2;

use Exception;

/**
 * Error class of Ecsec\Eidlogin\Dep\OneLogin PHP Toolkit
 *
 * Defines the Error class
 */
class Error extends Exception
{
    // Errors
    const SETTINGS_FILE_NOT_FOUND = 0;
    const SETTINGS_INVALID_SYNTAX = 1;
    const SETTINGS_INVALID = 2;
    const METADATA_SP_INVALID = 3;
    const SP_CERTS_NOT_FOUND = 4;
    // SP_CERTS_NOT_FOUND is deprecated, use CERT_NOT_FOUND instead
    const CERT_NOT_FOUND = 4;
    const REDIRECT_INVALID_URL = 5;
    const PUBLIC_CERT_FILE_NOT_FOUND = 6;
    const PRIVATE_KEY_FILE_NOT_FOUND = 7;
    const SAML_RESPONSE_NOT_FOUND = 8;
    const SAML_LOGOUTMESSAGE_NOT_FOUND = 9;
    const SAML_LOGOUTREQUEST_INVALID = 10;
    const SAML_LOGOUTRESPONSE_INVALID  = 11;
    const SAML_SINGLE_LOGOUT_NOT_SUPPORTED = 12;
    const PRIVATE_KEY_NOT_FOUND = 13;
    const UNSUPPORTED_SETTINGS_OBJECT = 14;

    /**
     * Constructor
     *
     * @param string     $msg  Describes the error.
     * @param int        $code The code error (defined in the error class).
     * @param array|null $args Arguments used in the message that describes the error.
     */
    public function __construct($msg, $code = 0, $args = array())
    {
        assert(is_string($msg));
        assert(is_int($code));

        if (!isset($args)) {
            $args = array();
        }
        $params = array_merge(array($msg), $args);
        $message = call_user_func_array('sprintf', $params);

        parent::__construct($message, $code);
    }
}
