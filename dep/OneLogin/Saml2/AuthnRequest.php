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

use Ecsec\Eidlogin\Dep\RobRichards\XMLSecLibs\XMLSecurityKey;
use Ecsec\Eidlogin\Dep\RobRichards\XMLSecLibs\XMLSecurityDSig;
use Ecsec\Eidlogin\Dep\RobRichards\XMLSecLibs\XMLSecEnc;

/**
 * SAML 2 Authentication Request
 */
class AuthnRequest
{
    /**
     * Object that represents the setting info
     *
     * @var Settings
     */
    protected $_settings;

    /**
     * SAML AuthNRequest string
     *
     * @var string
     */
    private $_authnRequest;

    /**
     * SAML AuthNRequest ID.
     *
     * @var string
     */
    private $_id;

    /**
     * Constructs the AuthnRequest object.
     *
     * @param Settings $settings SAML Toolkit Settings
     * @param bool $forceAuthn When true the AuthNReuqest will set the ForceAuthn='true'
     * @param bool $isPassive When true the AuthNReuqest will set the Ispassive='true'
     * @param bool $setNameIdPolicy When true the AuthNReuqest will set a nameIdPolicy
     * @param string $nameIdValueReq Indicates to the IdP the subject that should be authenticated
     * @param string $id The id of the request to create
     */
    public function __construct(\Ecsec\Eidlogin\Dep\OneLogin\Saml2\Settings $settings, $forceAuthn = false, $isPassive = false, $setNameIdPolicy = true, $nameIdValueReq = null, $id = null)
    {
        $this->_settings = $settings;
        if ($id === null) {
            $id = Utils::generateUniqueID();
        }
        $this->_id = $id;

        $spData = $this->_settings->getSPData();
        $security = $this->_settings->getSecurityData();

        $issueInstant = Utils::parseTime2SAML(time());

        $subjectStr = "";
        if (isset($nameIdValueReq)) {
            $subjectStr = <<<SUBJECT

     <saml:Subject>
        <saml:NameID Format="{$spData['NameIDFormat']}">{$nameIdValueReq}</saml:NameID>
        <saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"></saml:SubjectConfirmation>
    </saml:Subject>
SUBJECT;
        }

        $nameIdPolicyStr = '';
        if ($setNameIdPolicy) {
            $nameIDPolicyFormat = $spData['NameIDFormat'];
            if (isset($security['wantNameIdEncrypted']) && $security['wantNameIdEncrypted']) {
                $nameIDPolicyFormat = Constants::NAMEID_ENCRYPTED;
            }

            $nameIdPolicyStr = <<<NAMEIDPOLICY

    <samlp:NameIDPolicy
        Format="{$nameIDPolicyFormat}"
        AllowCreate="true" />
NAMEIDPOLICY;
        }


        $providerNameStr = '';
        $organizationData = $settings->getOrganization();
        if (!empty($organizationData)) {
            $langs = array_keys($organizationData);
            if (in_array('en-US', $langs)) {
                $lang = 'en-US';
            } else {
                $lang = $langs[0];
            }
            if (isset($organizationData[$lang]['displayname']) && !empty($organizationData[$lang]['displayname'])) {
                $providerNameStr = <<<PROVIDERNAME
    ProviderName="{$organizationData[$lang]['displayname']}"
PROVIDERNAME;
            }
        }

        $forceAuthnStr = '';
        if ($forceAuthn) {
            $forceAuthnStr = <<<FORCEAUTHN

    ForceAuthn="true"
FORCEAUTHN;
        }

        $isPassiveStr = '';
        if ($isPassive) {
            $isPassiveStr = <<<ISPASSIVE

    IsPassive="true"
ISPASSIVE;
        }

        $requestedAuthnStr = '';
        if (isset($security['requestedAuthnContext']) && $security['requestedAuthnContext'] !== false) {
            $authnComparison = 'exact';
            if (isset($security['requestedAuthnContextComparison'])) {
                $authnComparison = $security['requestedAuthnContextComparison'];
            }

            $authnComparisonAttr = '';
            if (!empty($authnComparison)) {
                $authnComparisonAttr = sprintf('Comparison="%s"', $authnComparison);
            }

            if ($security['requestedAuthnContext'] === true) {
                $requestedAuthnStr = <<<REQUESTEDAUTHN

    <samlp:RequestedAuthnContext $authnComparisonAttr>
        <saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef>
    </samlp:RequestedAuthnContext>
REQUESTEDAUTHN;
            } else {
                $requestedAuthnStr .= "    <samlp:RequestedAuthnContext $authnComparisonAttr>\n";
                foreach ($security['requestedAuthnContext'] as $contextValue) {
                    $requestedAuthnStr .= "        <saml:AuthnContextClassRef>".$contextValue."</saml:AuthnContextClassRef>\n";
                }
                $requestedAuthnStr .= '    </samlp:RequestedAuthnContext>';
            }
        }

        // process extensions
        $nsArr = array();
        $extArr = $settings->getAuthnReqExt();
        $extStr = '';
        if (count($extArr) > 0) {
            $extStr .= '    <samlp:Extensions>';
            foreach ($extArr as $extKey => $extVal) {
                if ($extKey === "tr03130" && $extVal != "") {
                    // create dom from given xml
                    $dom = new \DOMDocument();
                    if(!$dom->loadXML($extVal)) {
                        throw new \Exception('could not build tr03031 AuthnRequestExtension, invalid XML given');
                    }
                    // encryption
                    $idpData = $settings->getIdPData();
                    $idpCertEnc = $idpData['x509certMulti']['encryption'][0];
                    if (!openssl_x509_read($idpCertEnc)) {
                        throw new \Exception('could not build tr03031 AuthnRequestExtension, invalid certificate given');
                    } 
                    $objKey = new XMLSecurityKey(XMLSecurityKey::AES256_GCM);
	                $objKey->generateSessionKey();
                    $siteKey = new XMLSecurityKey(XMLSecurityKey::RSA_OAEP_MGF1P, array('type'=>'public'));
                    $siteKey->loadKey($idpCertEnc, false, TRUE);
                    $enc = new XMLSecEnc();
	                $enc->type = XMLSecEnc::Element;
	                $enc->setNode($dom->documentElement);
                    $enc->encryptKey($siteKey, $objKey);
	                $encNode = $enc->encryptNode($objKey);
                    // add digest algo node
                    $digestNode = $dom->createElement('dsig:DigestMethod');
                    $digestNode->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
                    $encNode->childNodes[1]->childNodes[0]->childNodes[0]->appendChild($digestNode);
                    // put it all together
                    $extStr .= '<eid:EncryptedAuthnRequestExtension>';
                    $extStr .= $dom->saveXML($encNode);
                    $extStr .= '</eid:EncryptedAuthnRequestExtension>';
                    // add needed namespaces
                    $nsArr[] = 'xmlns:eid="http://bsi.bund.de/eID/"';
                    // enforce redirect binding
                    $spData['assertionConsumerService']['binding'] = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';
                }
            }
            $extStr .= '    </samlp:Extensions>';
        }
        $nsArr = array_unique($nsArr);
        $nsStr = implode(" ", $nsArr);

        $spEntityId = htmlspecialchars($spData['entityId'], ENT_QUOTES);
        $acsUrl = htmlspecialchars($spData['assertionConsumerService']['url'], ENT_QUOTES);
        $destination = $this->_settings->getIdPSSOUrl();
        $request = <<<AUTHNREQUEST
<samlp:AuthnRequest
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
{$nsStr}
    ID="$id"
    Version="2.0"
{$providerNameStr}{$forceAuthnStr}{$isPassiveStr}
    IssueInstant="{$issueInstant}"
    Destination="{$destination}"
    ProtocolBinding="{$spData['assertionConsumerService']['binding']}"
    AssertionConsumerServiceURL="{$acsUrl}">
    <saml:Issuer>{$spEntityId}</saml:Issuer>{$extStr}{$subjectStr}{$nameIdPolicyStr}{$requestedAuthnStr}
</samlp:AuthnRequest>
AUTHNREQUEST;

        $this->_id = $id;
        $this->_authnRequest = $request;
    }

    /**
     * Returns deflated, base64 encoded, unsigned AuthnRequest.
     *
     * @param bool|null $deflate Whether or not we should 'gzdeflate' the request body before we return it.
     *
     * @return string
     */
    public function getRequest($deflate = null)
    {
        $subject = $this->_authnRequest;

        if (is_null($deflate)) {
            $deflate = $this->_settings->shouldCompressRequests();
        }

        if ($deflate) {
            $subject = gzdeflate($this->_authnRequest);
        }

        $base64Request = base64_encode($subject);
        return $base64Request;
    }

    /**
     * Returns the AuthNRequest ID.
     *
     * @return string
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Returns the XML that will be sent as part of the request
     *
     * @return string
     */
    public function getXML()
    {
        return $this->_authnRequest;
    }
}