<?php

declare(strict_types=1);

/*
 * PaypalServerSdkLib
 *
 * This file was automatically generated by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace PaypalServerSdkLib\Models;

use PaypalServerSdkLib\ApiHelper;
use stdClass;

/**
 * Additional attributes associated with the use of Apple Pay.
 */
class ApplePayAttributesResponse implements \JsonSerializable
{
    /**
     * @var VaultResponse|null
     */
    private $vault;

    /**
     * Returns Vault.
     * The details about a saved payment source.
     */
    public function getVault(): ?VaultResponse
    {
        return $this->vault;
    }

    /**
     * Sets Vault.
     * The details about a saved payment source.
     *
     * @maps vault
     */
    public function setVault(?VaultResponse $vault): void
    {
        $this->vault = $vault;
    }

    /**
     * Converts the ApplePayAttributesResponse object to a human-readable string representation.
     *
     * @return string The string representation of the ApplePayAttributesResponse object.
     */
    public function __toString(): string
    {
        return ApiHelper::stringify('ApplePayAttributesResponse', ['vault' => $this->vault]);
    }

    /**
     * Encode this object to JSON
     *
     * @param bool $asArrayWhenEmpty Whether to serialize this model as an array whenever no fields
     *        are set. (default: false)
     *
     * @return array|stdClass
     */
    #[\ReturnTypeWillChange] // @phan-suppress-current-line PhanUndeclaredClassAttribute for (php < 8.1)
    public function jsonSerialize(bool $asArrayWhenEmpty = false)
    {
        $json = [];
        if (isset($this->vault)) {
            $json['vault'] = $this->vault;
        }

        return (!$asArrayWhenEmpty && empty($json)) ? new stdClass() : $json;
    }
}
