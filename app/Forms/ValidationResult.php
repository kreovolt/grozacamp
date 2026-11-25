<?php
declare(strict_types=1);

namespace Groza\Forms;

final class ValidationResult
{
    /**
     * @param array<string,mixed> $data   очищенные значения (только известные поля)
     * @param array<string,string> $errors поле → сообщение
     */
    public function __construct(public array $data, public array $errors)
    {
    }

    public function ok(): bool
    {
        return $this->errors === [];
    }
}
