<?php
declare(strict_types=1);

/*
 * Учётные записи админки (создаёт разработчик, спека §10.1).
 *
 *   php scripts/user.php add <login> "<Имя>"   создать пользователя (пароль спрашивается без эха)
 *   php scripts/user.php passwd <login>        сменить пароль (сбросить забытый)
 *   php scripts/user.php remove <login>        удалить пользователя
 *   php scripts/user.php list                  показать логины и имена
 *
 * Пароль не передаётся аргументом (попадёт в историю оболочки и список процессов). Для автоматизации:
 *   --password-stdin   прочитать пароль одной строкой со стандартного ввода
 * Данные берутся из storageDir конфигурации (GROZA_STORAGE_DIR переопределяет).
 */

use Groza\Admin\Users;
use Groza\Data\AuditLog;
use Groza\Data\JsonStore;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$config = require $root . '/config/config.php';
date_default_timezone_set($config['timezone']);

$args = array_values(array_filter($argv, static fn (string $a): bool => $a !== '--password-stdin'));
$fromStdin = count($args) !== count($argv);
[, $command, $login, $name] = array_pad($args, 4, null);

$usage = static function (): never {
    fwrite(STDERR, "Usage: php scripts/user.php add <login> \"<Имя>\" | passwd <login> | remove <login> | list  [--password-stdin]\n");
    exit(2);
};

/** Пароль: из stdin по флагу, иначе с терминала без эха (повторный ввод для проверки). */
$askPassword = static function () use ($fromStdin): string {
    if ($fromStdin) {
        return rtrim((string) fgets(STDIN), "\r\n");
    }
    $read = static function (string $prompt): string {
        fwrite(STDERR, $prompt);
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'powershell -NoProfile -Command "$p = Read-Host -AsSecureString; '
                . '[Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';
            $value = rtrim((string) shell_exec($cmd), "\r\n");
        } else {
            shell_exec('stty -echo');
            $value = rtrim((string) fgets(STDIN), "\r\n");
            shell_exec('stty echo');
            fwrite(STDERR, "\n");
        }

        return $value;
    };
    $first = $read('Пароль (не короче ' . Users::MIN_PASSWORD . ' символов): ');
    if ($first !== $read('Пароль ещё раз: ')) {
        fwrite(STDERR, "Пароли не совпадают.\n");
        exit(1);
    }

    return $first;
};

$storage = (string) $config['storageDir'];
$store = new JsonStore($storage . '/data', $storage . '/versions');
$users = new Users($store);
$audit = new AuditLog($storage . '/logs/audit.jsonl');

try {
    switch ($command) {
        case 'add':
            if ($login === null || $name === null) {
                $usage();
            }
            $users->add($login, $name, $askPassword(), 'cli');
            $audit->record('cli', 'user.add', Users::normalize($login));
            fwrite(STDOUT, "Пользователь «" . Users::normalize($login) . "» создан.\n");
            break;
        case 'passwd':
            if ($login === null) {
                $usage();
            }
            $users->setPassword($login, $askPassword(), 'cli');
            $audit->record('cli', 'user.passwd', Users::normalize($login));
            fwrite(STDOUT, "Пароль «" . Users::normalize($login) . "» изменён; прежние сессии закрыты.\n");
            break;
        case 'remove':
            if ($login === null) {
                $usage();
            }
            $users->remove($login, 'cli');
            $audit->record('cli', 'user.remove', Users::normalize($login));
            fwrite(STDOUT, "Пользователь «" . Users::normalize($login) . "» удалён.\n");
            break;
        case 'list':
            foreach ($users->all() as $u) {
                fwrite(STDOUT, $u['login'] . "\t" . $u['name'] . "\n");
            }
            break;
        default:
            $usage();
    }
} catch (\InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
