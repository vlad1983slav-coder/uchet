<?php

declare(strict_types=1);

namespace Uchet\Core;

/**
 * Базовая модель-сущность: атрибуты + fillable-защита + доступ как к свойствам.
 * Хранение в БД — задача Repository; Model — только данные.
 */
abstract class Model implements \ArrayAccess, \JsonSerializable
{
    protected string $table = '';

    protected string $primaryKey = 'id';

    /** @var string[] — поля, которые можно массово заполнять через fill() */
    protected array $fillable = [];

    /** @var array<string, mixed> */
    protected array $attributes = [];

    public bool $exists = false;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function fromRow(array $row): static
    {
        $model = new static();
        $model->forceFill($row);
        $model->exists = true;

        return $model;
    }

    /** @param array<string, mixed> $attributes — только fillable-поля */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->fillable === [] || in_array((string) $key, $this->fillable, true)) {
                $this->attributes[(string) $key] = $value;
            }
        }

        return $this;
    }

    /** @param array<string, mixed> $attributes — все поля, без фильтра */
    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[(string) $key] = $value;
        }

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function set(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function table(): string
    {
        return $this->table;
    }

    public function primaryKey(): string
    {
        return $this->primaryKey;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[(string) $offset]);
    }
}
