<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\View;

use Groza\Content\Nav;
use PHPUnit\Framework\TestCase;

final class NavTest extends TestCase
{
    public function test_main_items_have_exact_labels_and_order(): void
    {
        $this->assertSame(
            ['Купить путёвку', 'Информация для родителей', 'Вакансии', 'Сведения об организации отдыха детей и их оздоровления'],
            array_column(Nav::main(), 'label')
        );
        $this->assertSame(['/booking/', '/info/', '/vacancies/', '/svedeniya/'], array_column(Nav::main(), 'href'));
    }

    public function test_item_kinds(): void
    {
        $this->assertSame(['button', 'link', 'link', 'dropdown'], array_column(Nav::main(), 'kind'));
    }

    public function test_svedeniya_dropdown_sections(): void
    {
        $dd = Nav::main()[3]['children'];

        $this->assertSame(
            ['Об организации отдыха детей и их оздоровления', 'Деятельность', 'Материально-техническое обеспечение и оснащённость', 'Услуги, в том числе платные', 'Доступная среда', 'Иная информация'],
            array_column($dd, 'label')
        );
        $this->assertSame(
            ['/svedeniya/ob-organizacii/', '/svedeniya/deyatelnost/', '/svedeniya/mto/', '/svedeniya/uslugi/', '/svedeniya/dostupnaya-sreda/', '/svedeniya/inaya-informaciya/'],
            array_column($dd, 'href')
        );
    }

    public function test_ob_organizacii_subsections(): void
    {
        $sub = Nav::main()[3]['children'][0]['children'];

        $this->assertSame(
            ['Основные сведения', 'Документы', 'Руководство', 'Педагогический и вожатский состав', 'Контакты'],
            array_column($sub, 'label')
        );
        $this->assertSame(
            ['/svedeniya/ob-organizacii/#osnovnye-svedeniya', '/svedeniya/ob-organizacii/#dokumenty', '/svedeniya/ob-organizacii/#rukovodstvo', '/svedeniya/ob-organizacii/#sostav', '/svedeniya/ob-organizacii/#kontakty'],
            array_column($sub, 'href')
        );
        foreach (array_slice(Nav::main()[3]['children'], 1) as $section) {
            $this->assertArrayNotHasKey('children', $section, $section['label']);
        }
    }

    public function test_footer_camp_column(): void
    {
        $this->assertSame(
            ['Купить путёвку', 'Смены', 'Команда', 'Информация для родителей', 'Вакансии'],
            array_column(Nav::footer(), 'label')
        );
        $this->assertSame(['/booking/', '/booking/#smeny', '/team/', '/info/', '/vacancies/'], array_column(Nav::footer(), 'href'));
    }

    public function test_legal_links(): void
    {
        $this->assertSame(['/policy/', '/pravila/'], array_column(Nav::legal(), 'href'));
    }

    public function test_is_active(): void
    {
        $this->assertTrue(Nav::isActive('/vacancies/', '/vacancies/'));
        $this->assertFalse(Nav::isActive('/vacancies/', '/'));
        $this->assertTrue(Nav::isActive('/svedeniya/', '/svedeniya/mto/'), 'раздел подсвечен на подстранице');
        $this->assertTrue(Nav::isActive('/booking/', '/program/spasatel-2027/'), 'смена — часть «Купить путёвку»');
        $this->assertFalse(Nav::isActive('/', '/info/'), 'главная не активна на остальных страницах');
        $this->assertFalse(Nav::isActive('/svedeniya/ob-organizacii/#dokumenty', '/svedeniya/ob-organizacii/'), 'якорь не текущая страница');
    }
}
