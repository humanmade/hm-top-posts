<?php

/*
 * Copyright 2008 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use Google\Auth\CacheInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;

/**
 * Wrapper around Google Access Tokens which provides convenience functions
 *
 */
class Google_AccessToken_Verify
{
  const FEDERATED_SIGNON_CERT_URL = 'https://www.googleapis.com/oauth2/v3/certs';
  const OAUTH2_ISSUER = 'accounts.google.com';
  const OAUTH2_ISSUER_HTTPS = 'https://accounts.google.com';

  /**
   * @var GuzzleHttp\ClientInterface The http client
   */
  private $http;

  /**
   * @var Google\Auth\CacheInterface cache class
   */
  private $cache;

  /**
   * Instantiates the class, but does not initiate the login flow, leaving it
   * to the discretion of the caller.
   */
  public function __construct(ClientInterface $http = null, CacheInterface $cache = null)
  {
    if (is_null($http)) {
      $http = new Client();
    }

    $this->http = $http;
    $this->cache = $cache;
    $this->jwt = $this->getJwtService();
  }

  /**
   * Verifies an id token and returns the authenticated apiLoginTicket.
   * Throws an exception if the id token is not valid.
   * The audience parameter can be used to control which id tokens are
   * accepted.  By default, the id token must have been issued to this OAuth2 client.
   *
   * @param $audience
   * @return array the token payload, if successful
   */
  public function verifyIdToken($idToken, $audience = null)
  {
    if (empty($idToken)) {
      throw new LogicException('id_token cannot be null');
    }

    // Check signature
    $certs = $this->getFederatedSignOnCerts();
    foreach ($certs as $cert) {
      $modulus = new BigInteger($this->jwt->urlsafeB64Decode($cert['n']), 256);
      $exponent = new BigInteger($this->jwt->urlsafeB64Decode($cert['e']), 256);

      $rsa = new RSA();
      $rsa->loadKey(array('n' => $modulus, 'e' => $exponent));

      try {
        $payload = $this->jwt->decode(
            $idToken,
            $rsa->getPublicKey(),
            array('RS256')
        );

        if (property_exists($payload, 'aud')) {
          if ($audience && $payload->aud != $audience) {
            return false;
          }
        }

        // support HTTP and HTTPS issuers
        // @see https://developers.google.com/identity/sign-in/web/backend-auth
        $issuers = array(self::OAUTH2_ISSUER, self::OAUTH2_ISSUER_HTTPS);
        if (!isset($payload->iss) || !in_array($payload->iss, $issuers)) {
          return false;
        }

        return (array) $payload;
      } catch (ExpiredException $e) {
        return false;
      } catch (DomainException $e) {
        // continue
      }
    }

    return false;
  }

  private function getCache()
  {
    if (!$this->cache) {
      $this->cache = $this->createDefaultCache();
    }

    return $this->cache;
  }

  private function createDefaultCache()
  {
    return new Google_Cache_File(
        sys_get_temp_dir().'/google-api-php-client'
    );
  }

  /**
   * Retrieve and cache a certificates file.
   *
   * @param $url string location
   * @throws Google_Exception
   * @return array certificates
   */
  private function retrieveCertsFromLocation($url)
  {
    // If we're retrieving a local file, just grab it.
    if (0 !== strpos($url, 'http')) {
      if (!$file = file_get_contents($url)) {
        throw new Google_Exception(
            "Failed to retrieve verification certificates: '" .
            $url . "'."
        );
      }

      return json_decode($file, true);
    }

    $response = $this->http->get($url);

    if ($response->getStatusCode() == 200) {
      return json_decode((string) $response->getBody(), true);
    }
    throw new Google_Exception(
        sprintf(
            'Failed to retrieve verification certificates: "%s".',
            $response->getBody()->getContents()
        ),
        $response->getStatusCode()
    );
  }

  // Gets federated sign-on certificates to use for verifying identity tokens.
  // Returns certs as array structure, where keys are key ids, and values
  // are PEM encoded certificates.
  private function getFederatedSignOnCerts()
  {
    $cache = $this->getCache();

    if (!$certs = $cache->get('federated_signon_certs_v3', 3600)) {
      $certs = $this->retrieveCertsFromLocation(
          self::FEDERATED_SIGNON_CERT_URL
      );

      $cache->set('federated_signon_certs_v3', $certs);
    }

    if (!isset($certs['keys'])) {
      throw new InvalidArgumentException(
          'federated sign-on certs expects "keys" to be set'
      );
    }

    return $certs['keys'];
  }

  private function getJwtService()
  {
    if (class_exists('\Firebase\JWT\JWT')) {
      return new \Firebase\JWT\JWT;
    }

    return new \JWT;
  }
}
