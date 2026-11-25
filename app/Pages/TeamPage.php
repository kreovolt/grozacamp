<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Content\PageData;
use Groza\Content\Staff;
use Groza\Http\Request;
use Groza\Http\Response;

/**
 * «Команда» /team/ (спека §7.7): карточки по группам — руководство, педагоги, вожатые, остальные.
 * Внутри группы по полю order, при равенстве по имени. Скрытые не показываются.
 */
final class TeamPage
{
    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params): Response
    {
        return Response::html($this->app->view()->render('pages/team', [
            'groups' => Staff::grouped(PageData::items($this->app->store()->read('team'))),
        ]));
    }
}
