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
 * The JSON patch object to apply partial updates to resources.
 */
class Patch implements \JsonSerializable
{
    /**
     * @var string
     */
    private $op;

    /**
     * @var string|null
     */
    private $path;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var string|null
     */
    private $from;

    /**
     * @param string $op
     */
    public function __construct(string $op)
    {
        $this->op = $op;
    }

    /**
     * Returns Op.
     * The operation.
     */
    public function getOp(): string
    {
        return $this->op;
    }

    /**
     * Sets Op.
     * The operation.
     *
     * @required
     * @maps op
     */
    public function setOp(string $op): void
    {
        $this->op = $op;
    }

    /**
     * Returns Path.
     * The JSON Pointer to the target document location at which to complete the operation.
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Sets Path.
     * The JSON Pointer to the target document location at which to complete the operation.
     *
     * @maps path
     */
    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    /**
     * Returns Value.
     * The value to apply. The remove, copy, and move operations do not require a value. Since JSON Patch
     * allows any type for value, the type property is not specified.
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Sets Value.
     * The value to apply. The remove, copy, and move operations do not require a value. Since JSON Patch
     * allows any type for value, the type property is not specified.
     *
     * @maps value
     *
     * @param mixed $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    /**
     * Returns From.
     * The JSON Pointer to the target document location from which to move the value. Required for the move
     * operation.
     */
    public function getFrom(): ?string
    {
        return $this->from;
    }

    /**
     * Sets From.
     * The JSON Pointer to the target document location from which to move the value. Required for the move
     * operation.
     *
     * @maps from
     */
    public function setFrom(?string $from): void
    {
        $this->from = $from;
    }

    /**
     * Converts the Patch object to a human-readable string representation.
     *
     * @return string The string representation of the Patch object.
     */
    public function __toString(): string
    {
        return ApiHelper::stringify(
            'Patch',
            ['op' => $this->op, 'path' => $this->path, 'value' => $this->value, 'from' => $this->from]
        );
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
        $json['op']        = $this->op;
        if (isset($this->path)) {
            $json['path']  = $this->path;
        }
        if (isset($this->value)) {
            $json['value'] = $this->value;
        }
        if (isset($this->from)) {
            $json['from']  = $this->from;
        }

        return (!$asArrayWhenEmpty && empty($json)) ? new stdClass() : $json;
    }
}
