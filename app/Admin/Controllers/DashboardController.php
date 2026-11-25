<?php
declare(strict_types=1);

namespace Groza\Admin\Controllers;

use Groza\App;
use Groza\Domain\Collections;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Csrf;

/** Стартовая страница админки: разделы. (Проверка готовности к проверке появится на ней позже.) */
final class DashboardController
{
    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function index(Request $r, array $params): Response
    {
        $sections = [];
        foreach (Collections::all() as $name => $def) {
            $sections[] = [
                'name' => $name,
                'label' => $def['label'],
                'count' => count($this->app->store()->read($name)['items'] ?? []),
            ];
        }

        return Response::html($this->app->view()->render('admin/dashboard', [
            'user' => $this->app->auth()->user(),
            'csrf' => Csrf::token(),
            'sections' => $sections,
            'textGroups' => TextsController::GROUPS,
        ]));
    }
}
