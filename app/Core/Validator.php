<?php

declare(strict_types=1);

namespace Uchet\Core;

/**
 * Валидатор входных данных.
 * Правила: 'поле' => 'required|string|max:255|unique:table,col,exceptId,exceptCol'.
 * Поддерживаются: required, nullable, string, numeric, integer, decimal, boolean, array,
 * min:n, max:n, email, date, in:a,b,c, exists:table,col[,1], unique:table,col[,except,exceptCol].
 * Сообщения об ошибках — на русском.
 */
final class Validator
{
    /** @var array<string, string[]> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|string[]> $rules
     */
    private function __construct(
        private readonly array $data,
        private readonly array $rules,
    ) {}

    /** @param array<string, mixed> $data @param array<string, string|string[]> $rules */
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function validate(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', (string) $ruleSet);
            $rules = array_values(array_filter(array_map('trim', $rules), static fn ($r) => $r !== ''));
            $present = array_key_exists($field, $this->data)
                && $this->data[$field] !== null
                && $this->data[$field] !== '';

            // поле необязательное и пустое — остальные правила пропускаем
            if (!$present && !in_array('required', $rules, true)) {
                continue;
            }

            $failed = false;
            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', (string) $rule, 2), 2, null);
                $value = $this->data[$field] ?? null;
                $ok = $this->check((string) $name, $param, $value, $present, $field);
                if (!$ok) {
                    $this->errors[$field][] = $this->message((string) $field, (string) $name, $param);
                    $failed = true;
                    break;
                }
            }

            if (!$failed && array_key_exists($field, $this->data)) {
                $this->validated[$field] = $this->data[$field];
            }
        }

        return $this->errors === [];
    }

    private function check(string $name, ?string $param, mixed $value, bool $present, string $field): bool
    {
        return match ($name) {
            'required' => $present,
            'nullable' => true,
            'string' => !$present || is_string($value),
            'numeric', 'decimal' => !$present || is_numeric($value),
            'integer' => !$present || filter_var($value, FILTER_VALIDATE_INT) !== false,
            'boolean' => !$present || in_array($value, [true, false, 0, 1, '0', '1'], true),
            'array' => !$present || is_array($value),
            'email' => !$present || filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'date' => !$present || strtotime((string) $value) !== false,
            'min' => !$present || (is_numeric($value)
                ? (float) $value >= (float) $param
                : mb_strlen((string) $value) >= (int) $param),
            'max' => !$present || (is_numeric($value)
                ? (float) $value <= (float) $param
                : mb_strlen((string) $value) <= (int) $param),
            'in' => !$present || in_array((string) $value, explode(',', (string) $param), true),
            'exists' => !$present || $this->checkExists((string) $param, $value),
            'unique' => !$present || $this->checkUnique((string) $param, $value, $field),
            default => true, // неизвестное правило считаем пройденным
        };
    }

    /** exists:table,col[,1] — 1 = не учитывать soft delete */
    private function checkExists(string $param, mixed $value): bool
    {
        $parts = explode(',', $param);
        $table = Database::quoteIdentifier((string) ($parts[0] ?? ''));
        $col = Database::quoteIdentifier((string) ($parts[1] ?? ''));
        $withDeleted = ($parts[2] ?? '') === '1';
        $sql = "SELECT 1 FROM {$table} WHERE {$col} = ?";
        if (!$withDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$value]);

        return $stmt->fetch() !== false;
    }

    /** unique:table,col[,exceptValue[,exceptCol]] — exceptValue = 'NULL' чтобы не исключать */
    private function checkUnique(string $param, mixed $value, string $field): bool
    {
        $parts = explode(',', $param);
        $table = Database::quoteIdentifier((string) ($parts[0] ?? ''));
        $col = Database::quoteIdentifier((string) ($parts[1] ?? ''));
        $except = $parts[2] ?? 'NULL';
        $exceptCol = Database::quoteIdentifier((string) ($parts[3] ?? 'id'));
        $sql = "SELECT 1 FROM {$table} WHERE {$col} = ? AND deleted_at IS NULL";
        $params = [$value];
        if ($except !== 'NULL' && $except !== '') {
            $sql .= " AND {$exceptCol} <> ?";
            $params[] = $except;
        }
        $sql .= ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() === false;
    }

    private function message(string $field, string $rule, ?string $param): string
    {
        return match ($rule) {
            'required' => "Поле «{$field}» обязательно для заполнения",
            'string' => "Поле «{$field}» должно быть текстом",
            'numeric', 'decimal' => "Поле «{$field}» должно быть числом",
            'integer' => "Поле «{$field}» должно быть целым числом",
            'boolean' => "Поле «{$field}» должно быть флагом (да/нет)",
            'array' => "Поле «{$field}» должно быть списком",
            'email' => "Поле «{$field}» должно быть корректным e-mail",
            'date' => "Поле «{$field}» должно быть датой",
            'min' => "Поле «{$field}» должно быть не меньше {$param}",
            'max' => "Поле «{$field}» должно быть не больше {$param}",
            'in' => "Поле «{$field}» содержит недопустимое значение",
            'exists' => "Выбранное значение «{$field}» не найдено в справочнике",
            'unique' => "Такое значение поля «{$field}» уже существует",
            default => "Ошибка в поле «{$field}»",
        };
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string[]> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> — только проверенные поля */
    public function validated(): array
    {
        return $this->validated;
    }
}
