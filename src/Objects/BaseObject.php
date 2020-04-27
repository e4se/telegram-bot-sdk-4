<?php

namespace Telegram\Bot\Objects;

use ArrayAccess;
use Countable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Telegram\Bot\Exceptions\TelegramSDKException;

/**
 * Class BaseObject.
 */
abstract class BaseObject implements ArrayAccess, Countable
{
    /** @var object|array The fields contained in the object. */
    protected $fields;

    /**
     * Create a new object.
     *
     * @param mixed $fields
     */
    public function __construct($fields = [])
    {
        $data = $this->getRawResult($fields);

        if (is_array($data)) {
            $data = json_decode(json_encode($fields));
        }

        $this->fields = $data;
    }

    /**
     * Make object with given data.
     *
     * @param mixed $data
     *
     * @return static
     */
    public static function make($data = []): self
    {
        return new static($data);
    }

    /**
     * Field relations.
     *
     * @return array
     */
    public function relations(): array
    {
        return [];
    }

    /**
     * Make a collection out of the given data.
     *
     * @return Collection
     */
    public function collect(): Collection
    {
        return collect($this->fields);
    }

    /**
     * Get all fields.
     *
     * @return object|array
     */
    public function all()
    {
        return $this->fields;
    }

    /**
     * Returns raw result.
     *
     * @param $data
     *
     * @return mixed
     */
    public function getRawResult($data)
    {
        return data_get($data, 'result', $data);
    }

    /**
     * Get Status of request.
     *
     * @return mixed
     */
    public function getStatus()
    {
        return data_get($this->fields, 'ok', false);
    }

    /**
     * Determine if a field exists.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function has($key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Get field from the object by key.
     *
     * @param mixed $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if ($this->offsetExists($key)) {
            return $this->offsetGet($key);
        }

        return value($default);
    }

    /**
     * Put a field in the object by key.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return static
     */
    public function put($key, $value): self
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    /**
     * Remove a field from the object by key.
     *
     * @param string|array $keys
     *
     * @return $this
     */
    public function forget($keys): self
    {
        foreach ((array)$keys as $key) {
            $this->offsetUnset($key);
        }

        return $this;
    }

    /**
     * Count the number of fields in the object.
     *
     * @return int
     */
    public function count(): int
    {
        return count((array)$this->fields);
    }

    /**
     * Determine if a field exists at an offset.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return isset($this->fields->{$key});
    }

    /**
     * Get a field at a given offset.
     *
     * @param mixed $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return data_get($this->fields, $key);
    }

    /**
     * Set the field at a given offset.
     *
     * @param mixed $key
     * @param mixed $value
     */
    public function offsetSet($key, $value): void
    {
        data_set($this->fields, $key, $value);
    }

    /**
     * Unset the field at a given offset.
     *
     * @param string $key
     */
    public function offsetUnset($key): void
    {
        unset($this->fields->{$key});
    }

    /**
     * Get the object of fields as an associative array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return json_decode($this->toJson(), true);
    }

    /**
     * Get the collection of fields as JSON.
     *
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->all(), $options);
    }

    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Magically access object data.
     *
     * @param $field
     *
     * @return mixed
     */
    public function __get($field)
    {
        if (!$this->offsetExists($field)) {
            return null;
        }

        $value = $this->offsetGet($field);

        $relations = $this->relations();
        if (isset($relations[$field])) {
            if (is_array($value)) {
                return collect($value)->mapInto($relations[$field])->all();
            }

            return $relations[$field]::make($value);
        }

        /** @var BaseObject $class */
        $class = 'Telegram\Bot\Objects\\' . Str::studly($field);

        if (class_exists($class)) {
            return $class::make($value);
        }

        if (is_array($value)) {
            return TelegramObject::make($value);
        }

        return $value;
    }

    /**
     * Set value of a field.
     *
     * @param $field
     * @param $value
     *
     * @throws TelegramSDKException
     */
    public function __set($field, $value)
    {
        if (!$this->offsetExists($field)) {
            throw new TelegramSDKException("Property [{$field}] does not exist on this object instance.");
        }

        $this->offsetSet($field, $value);
    }

    /**
     * Determine if the field exists.
     *
     * @param $field
     *
     * @return bool
     */
    public function __isset($field)
    {
        return $this->offsetExists($field);
    }

    /**
     * Unset field.
     *
     * @param $field
     */
    public function __unset($field)
    {
        $this->offsetUnset($field);
    }

    /**
     * Magic method to get properties dynamically.
     *
     * @param $method
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        $collect = $this->collect();

        if (method_exists($this->collect(), $method)) {
            return $collect->{$method}(...$arguments);
        }

        throw new \BadMethodCallException("Method [$method] does not exist.");
    }
}
