<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Content\PageData;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Slug;

/**
 * Страница-документ с текстом из админки: /pravila/ (правила пребывания, со скачиваемым PDF, если он загружен)
 * и /policy/ (политика обработки персональных данных). Оглавление строится по подзаголовкам h3;
 * у подзаголовка без id он ставится из текста (id, заданный в тексте, сохраняется: на него ссылаются с других страниц).
 */
final class TextPage
{
    private function __construct(
        private App $app,
        private string $textKey,
        private string $title,
        private string $description,
        private string $documentSection = '',
        /** @var array<string,string> текст подзаголовка → постоянный id (на него ссылаются с других страниц) */
        private array $anchors = [],
    ) {
    }

    public static function rules(App $app): self
    {
        return new self(
            $app,
            'rules',
            'Правила пребывания в лагере',
            'Правила пребывания в детском лагере «Гроза»: что нужно делать, что запрещено и как проходят посещения. Прочитайте их вместе с ребёнком до поездки.',
            'pravila',
            // ссылка из /info/ («Посещения»); id в самом тексте не нужен: редактор админки атрибуты не сохраняет
            ['Посещение ребёнка' => 'poseshchenie'],
        );
    }

    public static function policy(App $app): self
    {
        return new self(
            $app,
            'policy',
            'Политика обработки персональных данных',
            'Как детский лагерь «Гроза» обрабатывает персональные данные, которые вы оставляете в формах сайта.',
        );
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params): Response
    {
        $store = $this->app->store();
        [$html, $toc] = self::withAnchors(PageData::text($store->read('texts'), $this->textKey), $this->anchors);

        $files = [];
        if ($this->documentSection !== '') {
            foreach (PageData::byOrder(PageData::items($store->read('documents'))) as $doc) {
                $file = $doc['file'] ?? null;
                if (($doc['section'] ?? '') === $this->documentSection && is_array($file) && !empty($file['url'])) {
                    $files[] = ['title' => (string) ($doc['title'] ?? ''), 'url' => (string) $file['url'], 'bytes' => (int) ($file['bytes'] ?? 0)];
                }
            }
        }

        return Response::html($this->app->view()->render('pages/text', [
            'title' => $this->title,
            'description' => $this->description,
            'html' => $html,
            'toc' => $toc,
            'files' => $files,
        ]));
    }

    /**
     * Ставит id подзаголовкам h3 (где его нет) и собирает оглавление. Id берётся из $aliases по тексту подзаголовка,
     * иначе — транслитерация текста.
     *
     * @param array<string,string> $aliases @return array{0:string,1:list<array{id:string,label:string}>}
     */
    public static function withAnchors(string $html, array $aliases = []): array
    {
        $toc = [];
        $used = [];
        $out = preg_replace_callback('#<h3([^>]*)>(.*?)</h3>#us', static function (array $m) use (&$toc, &$used, $aliases): string {
            $label = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (preg_match('/\sid="([^"]+)"/u', $m[1], $found) === 1) {
                $id = $found[1];
                $attrs = $m[1];
            } else {
                $base = $aliases[$label] ?? (Slug::fromRu($label) ?: 'razdel');
                $id = $base;
                for ($n = 2; isset($used[$id]); $n++) {
                    $id = $base . '-' . $n;
                }
                $attrs = $m[1] . ' id="' . $id . '"';
            }
            $used[$id] = true;
            if ($label !== '') {
                $toc[] = ['id' => $id, 'label' => $label];
            }

            return '<h3' . $attrs . '>' . $m[2] . '</h3>';
        }, $html);

        return [$out ?? $html, $toc];
    }
}
