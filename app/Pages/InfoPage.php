<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Content\PageData;
use Groza\Http\Request;
use Groza\Http\Response;

/**
 * «Информация для родителей» /info/ (спека §7.4): одна страница с оглавлением. Каждый раздел — текстовый блок из админки
 * (texts); списки вещей и документов — те же данные, что в /svedeniya/uslugi/. Пустой раздел показывает
 * «Информация обновляется», а не пропадает: родитель видит, что тема есть и ответ готовится.
 */
final class InfoPage
{
    /** id раздела, заголовок, ключ в texts (richtext | list), вид. Порядок — порядок на странице и в оглавлении. */
    private const SECTIONS = [
        ['prepare', 'Как подготовить ребёнка к первой поездке', 'info.prepare', 'html'],
        ['packing', 'Что взять с собой', 'lists.packing', 'list'],
        ['documents', 'Документы для заезда', 'lists.documents', 'list'],
        ['contraindications', 'Противопоказания', 'info.contraindications', 'html'],
        ['medicines', 'Как передать лекарства', 'info.medicines', 'html'],
        ['contact', 'Связь с ребёнком', 'info.contact', 'html'],
        ['transfer', 'Трансфер: где и когда сбор', 'info.transfer', 'html'],
        ['visits', 'Посещения', 'info.visits', 'html'],
        ['rules', 'Правила пребывания', 'rules', 'rules'],
        ['faq', 'Частые вопросы', '', 'faq'],
    ];

    private const SUBTITLES = [
        'packing' => 'Одежда, обувь и средства гигиены.',
        'documents' => 'Что отдать при заезде.',
    ];

    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params): Response
    {
        $store = $this->app->store();
        $texts = $store->read('texts');
        $faq = PageData::faq(PageData::items($store->read('faq')));

        $sections = [];
        foreach (self::SECTIONS as [$id, $label, $key, $kind]) {
            $section = ['id' => $id, 'label' => $label, 'kind' => $kind, 'subtitle' => self::SUBTITLES[$id] ?? ''];
            switch ($kind) {
                case 'html':
                    $section['html'] = PageData::text($texts, $key);
                    break;
                case 'list':
                    $section['items'] = PageData::lines($texts[$key] ?? []);
                    break;
                case 'rules':
                    $section['html'] = self::rulesLead(PageData::text($texts, $key));
                    break;
                case 'faq':
                    $section['faq'] = $faq;
                    break;
            }
            $sections[] = $section;
        }

        return Response::html($this->app->view()->render('pages/info', [
            'sections' => $sections,
            'toc' => array_map(static fn (array $s): array => ['id' => $s['id'], 'label' => $s['label']], $sections),
        ]));
    }

    /** Вступление правил — всё до первого подзаголовка; сами правила — на /pravila/. */
    public static function rulesLead(string $html): string
    {
        $parts = preg_split('#<h3[\s>]#iu', $html, 2);

        return trim($parts[0] ?? '');
    }
}
