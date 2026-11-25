<?php
declare(strict_types=1);

namespace Groza\Domain;

use RuntimeException;

/** Внутренняя ошибка одного поля; наружу Validator отдаёт ValidationException. */
final class FieldError extends RuntimeException
{
}
