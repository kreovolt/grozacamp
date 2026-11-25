<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;

/** Страница 404: куда пойти дальше (смены, контакты). Обработчик 404 роутера; ShiftPage зовёт её для неизвестной смены. */
final class NotFoundPage
{
    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params = []): Response
    {
        return Response::html($this->app->view()->render('pages/404'), 404);
    }
}
