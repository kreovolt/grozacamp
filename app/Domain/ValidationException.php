<?php
declare(strict_types=1);

namespace Groza\Domain;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /** @param array<string,string> $errors поле → сообщение */
    public function __construct(private array $errors)
    {
        parent::__construct('Проверьте заполнение полей');
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
