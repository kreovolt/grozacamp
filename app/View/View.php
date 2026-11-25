<?php
declare(strict_types=1);

namespace Groza\View;

/**
 * PHP-шаблоны из templates/. Имя шаблона — путь без «.php» («pages/home», «partials/header»).
 * Внутри шаблона доступны глобальные переменные, переданные переменные и $this:
 *   $this->layout('layout', ['title' => '…']) — обернуть результат в макет (получит $content);
 *   $this->partial('partials/footer', [...]) — вставить кусок со своими переменными.
 */
final class View
{
    private const NAME = '#^[a-z0-9_][a-z0-9_-]*(/[a-z0-9_][a-z0-9_-]*)*$#'; // «_имя» — внутренние куски форм

    /** @var array{0:string,1:array<string,mixed>}|null макет, запрошенный текущим шаблоном */
    private ?array $pendingLayout = null;

    /** @param array<string,mixed> $globals */
    public function __construct(private string $templatesDir, private array $globals = [])
    {
    }

    /** @param array<string,mixed> $vars */
    public function render(string $template, array $vars = []): string
    {
        $outer = $this->pendingLayout;
        $this->pendingLayout = null;
        try {
            $html = $this->renderFile($template, $vars);
            while ($this->pendingLayout !== null) {
                [$layout, $layoutVars] = $this->pendingLayout;
                $this->pendingLayout = null;
                $html = $this->renderFile($layout, ['content' => $html] + $layoutVars);
            }

            return $html;
        } finally {
            $this->pendingLayout = $outer;
        }
    }

    /** Вызывается из шаблона: обернуть его вывод в макет $name. @param array<string,mixed> $vars */
    public function layout(string $name, array $vars = []): void
    {
        $this->pendingLayout = [$name, $vars];
    }

    /** Вызывается из шаблона: кусок со своими переменными (переменные вызывающего шаблона не видны). @param array<string,mixed> $vars */
    public function partial(string $name, array $vars = []): string
    {
        $outer = $this->pendingLayout;
        try {
            return $this->renderFile($name, $vars);
        } finally {
            $this->pendingLayout = $outer;
        }
    }

    /** Задать глобальную переменную шаблонов. */
    public function share(string $key, mixed $value): void
    {
        $this->globals[$key] = $value;
    }

    /** @param array<string,mixed> $vars */
    private function renderFile(string $name, array $vars): string
    {
        $file = $this->resolve($name);
        $scope = array_replace($this->globals, $vars);
        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__scope): void {
                extract($__scope, EXTR_SKIP);
                require $__file;
            })->call($this, $file, $scope);

            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    private function resolve(string $name): string
    {
        if (preg_match(self::NAME, $name) !== 1) {
            throw new \InvalidArgumentException("Недопустимое имя шаблона: $name");
        }
        $file = $this->templatesDir . '/' . $name . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Шаблон не найден: $name");
        }

        return $file;
    }
}
