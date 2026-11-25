<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Data;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Анти-цель «мама пугается»: слов из Global Constraints нет на первом экране,
 * в текстах главной и в описаниях смен. Названия смен («Штурмовик» и др.) не проверяются.
 */
final class CopyLintTest extends TestCase
{
    /**
     * «бой» и его формы — только целым словом (чтобы «не бойся» проходило),
     * остальное — как начало слова; «красная зона» — в любом падеже.
     */
    private const SCARY = '/(?<![\p{L}\p{N}])(?:'
        . '(?:бой|боя|бою|боем|бое|бои|боям|боями|боях)(?![\p{L}\p{N}])'
        . '|(?:оружи|огнев|курсант|противник|боев|боец|бойц)\p{L}*'
        . '|красн\p{L}*\s+зон\p{L}*'
        . ')/iu';

    /** @return list<string> найденные слова */
    public static function scaryWords(string $text): array
    {
        $text = str_replace(['ё', 'Ё'], ['е', 'Е'], (string) preg_replace('/<[^>]*>/', ' ', $text));
        preg_match_all(self::SCARY, $text, $m);

        return $m[0];
    }

    public function test_matcher_whole_word_boi_and_word_start_stems(): void
    {
        $this->assertSame([], self::scaryWords('Не бойся, вожатые рядом'));
        $this->assertSame(['бой'], self::scaryWords('Учебный бой'));
        $this->assertSame(['Бой'], self::scaryWords('Бой подушками'), 'регистр не важен');
        $this->assertSame(['боя'], self::scaryWords('правила рукопашного боя'));
        $this->assertSame(['Огневая'], self::scaryWords('Огневая подготовка'));
        $this->assertSame(['красной зоне'], self::scaryWords('первая помощь в красной зоне'));
        $this->assertSame(['боевых'], self::scaryWords('в боевых условиях'));
        $this->assertSame(['боев'], self::scaryWords('ветераны боёв'), 'ё = е');
        $this->assertSame(['Курсанты'], self::scaryWords('<li>Курсанты</li>'));
        $this->assertSame(['бойцы', 'боец'], self::scaryWords('бойцы, боец'));
        $this->assertSame(['оружие', 'противника'], self::scaryWords('оружие противника'));
        $this->assertSame([], self::scaryWords('Разбойники, бойкие ребята, вооружённые фонариками, и огонёк у костра'));
    }

    /** @return array<string,array{string}> */
    public static function seedSets(): array
    {
        return ['seed/data' => ['data'], 'seed/demo' => ['demo']];
    }

    #[DataProvider('seedSets')]
    public function test_headings_and_leads_avoid_scary_words(string $set): void
    {
        $root = dirname(__DIR__, 4);
        $texts = json_decode((string) file_get_contents("$root/seed/$set/texts.json"), true, 512, JSON_THROW_ON_ERROR);
        $shifts = json_decode((string) file_get_contents("$root/seed/$set/shifts.json"), true, 512, JSON_THROW_ON_ERROR);

        $checked = [];
        foreach ($texts as $key => $value) {
            if (str_starts_with($key, 'home.')) {
                foreach ((array) $value as $i => $line) {
                    $checked["texts {$key}[{$i}]"] = $line;
                }
            }
        }
        foreach ($shifts['items'] as $shift) {
            $checked["{$shift['slug']}.lead"] = $shift['lead'];
            $checked["{$shift['slug']}.body"] = $shift['body'];
            foreach ($shift['activities'] as $i => $line) {
                $checked["{$shift['slug']}.activities[$i]"] = $line;
            }
        }
        $this->assertNotEmpty($checked);

        $found = [];
        foreach ($checked as $where => $text) {
            foreach (self::scaryWords((string) $text) as $word) {
                $found[] = "$where: «$word»";
            }
        }
        $this->assertSame([], $found, "seed/$set");
    }
}
