<?php

namespace Dreamonkey\CloudFrontUrlSigner;

use Aws\CloudFront\CloudFrontClient;
use DateTime;
use Dreamonkey\CloudFrontUrlSigner\Exceptions\InvalidExpiration;
use Dreamonkey\CloudFrontUrlSigner\Exceptions\InvalidKeyPairId;
use Dreamonkey\CloudFrontUrlSigner\Exceptions\InvalidPrivateKey;
use League\Uri\Http;

class CloudFrontUrlSigner implements UrlSigner
{
    /**
     * CloudFront client object.
     *
     * @var \Aws\CloudFront\CloudFrontClient
     */
    private $cloudFrontClient;

    /**
     * Private key of the trusted signer.
     *
     * @var string
     */
    private $privateKey;

    /**
     * Identifier of the CloudFront Key Pair associated to the trusted signer.
     *
     * @var string
     */
    private $keyPairId;

    /**
     * @param array $cloudFrontParams
     * @param string $privateKey
     * @param string $keyPairId
     *
     * @throws \Dreamonkey\CloudFrontUrlSigner\Exceptions\InvalidPrivateKey
     * @throws \Dreamonkey\CloudFrontUrlSigner\Exceptions\InvalidKeyPairId
     */
    public function __construct(array $cloudFrontParams, string $privateKey, string $keyPairId)
    {
        if ($privateKey == '') {
            throw new InvalidPrivateKey('Private key cannot be empty');
        }

        if ($keyPairId == '') {
            throw new InvalidKeyPairId('Key pair id cannot be empty');
        }

        $this->cloudFrontClient = new CloudFrontClient($cloudFrontParams);
        $this->privateKey = $privateKey;
        $this->keyPairId = $keyPairId;
    }

    /**
     * Get a secure URL to a controller action.
     *
     * @param string $url
     * @param \DateTime|int $expiration
     *
     * @return string
     * @throws \Dreamonkey\CloudFrontUrlSigner\Exceptions\InvalidExpiration
     */
    public function sign($url, $expiration)
    {
        $resourceKey = Http::createFromString($url);

        $expiration = $this->getExpirationTimestamp($expiration);

        return $this->cloudFrontClient->getSignedUrl([
            'url' => $resourceKey,
            'expires' => $expiration,
            'private_key' => $this->privateKey,
            'key_pair_id' => $this->keyPairId,
        ]);
    }

    /**
     * Check if a timestamp is in the future.
     *
     * @param int $timestamp
     *
     * @return bool
     */
    protected function isFuture($timestamp)
    {
        return ((int)$timestamp) >= (new DateTime())->getTimestamp();
    }

    /**
     * Retrieve the expiration timestamp for a link based on an absolute DateTime or a relative number of days.
     *
     * @param \DateTime|int $expiration The expiration date of this link.
     *                                  - DateTime: The value will be used as expiration date
     *                                  - int: The expiration time will be set to X days from now
     *
     * @return string
     * @throws \Dreamonkey\CloudFrontUrlSigner\Exceptions\InvalidExpiration
     */
    protected function getExpirationTimestamp($expiration)
    {
        if (is_int($expiration)) {
            $expiration = (new DateTime())->modify((int)$expiration . ' days');
        }

        if (!$expiration instanceof DateTime) {
            throw new InvalidExpiration('Expiration date must be an instance of DateTime or an integer');
        }

        if (!$this->isFuture($expiration->getTimestamp())) {
            throw new InvalidExpiration('Expiration date must be in the future');
        }

        return (string)$expiration->getTimestamp();
    }
}