<?php
declare(strict_types=1);

/**
 * Страница раздела сведений: разделы с пунктами приказа (данные собирает Groza\Pages\SvedeniyaPage).
 * @var string $title
 * @var string $description
 * @var list<array{label:string,href?:string}> $crumbs
 * @var list<array{id:string,label:string}> $toc
 * @var string|null $updated
 * @var string $updatedText
 * @var list<array<string,mixed>> $sections
 */
$this->layout('pages/svedeniya/_frame', compact('title', 'description', 'crumbs', 'toc', 'updated', 'updatedText'));
?>
<?= $this->partial('components/sved-sections', ['sections' => $sections]) ?>
