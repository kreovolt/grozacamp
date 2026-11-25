// Фикстуры для тестов загрузок: node tests/fixtures/make-fixtures.mjs
import sharp from 'sharp';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const out = join(resolve(dirname(fileURLToPath(import.meta.url))), 'uploads');
mkdirSync(out, { recursive: true });

const base = (w, h, rgb) => sharp({ create: { width: w, height: h, channels: 3, background: rgb } });

// Фото с EXIF: геометка (Москва) и производитель камеры
await base(1200, 800, { r: 40, g: 120, b: 200 })
  .withExif({
    IFD0: { Make: 'GrozaTestCam', Model: 'Phone 1' },
    IFD3: {
      GPSLatitudeRef: 'N', GPSLatitude: '55/1 45/1 0/1',
      GPSLongitudeRef: 'E', GPSLongitude: '37/1 37/1 0/1',
    },
  })
  .jpeg().toFile(join(out, 'gps.jpg'));

// 300x200 с Orientation=6 (после поворота должно стать 200x300)
await base(300, 200, { r: 200, g: 80, b: 40 })
  .withMetadata({ orientation: 6 })
  .jpeg().toFile(join(out, 'rotated.jpg'));

await base(400, 300, { r: 60, g: 160, b: 80 }).jpeg().toFile(join(out, 'small.jpg'));

// PNG с прозрачностью
await sharp({ create: { width: 500, height: 400, channels: 4, background: { r: 255, g: 0, b: 0, alpha: 0.5 } } })
  .png().toFile(join(out, 'photo.png'));

writeFileSync(join(out, 'fake.jpg'), '<?php echo 1;');
writeFileSync(join(out, 'real.pdf'),
  '%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n' +
  '3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R/Size 4>>\n%%EOF\n');
writeFileSync(join(out, 'fake.pdf'), 'Это просто текст, а не PDF.');
// Заголовок HEIC: размер бокса + ftypheic
writeFileSync(join(out, 'photo.heic'), Buffer.concat([
  Buffer.from([0, 0, 0, 24]), Buffer.from('ftypheic'), Buffer.alloc(16),
]));
console.log('fixtures ok →', out);

// WebP как входной файл
await base(600, 400, { r: 120, g: 60, b: 160 }).webp().toFile(join(out, 'photo.webp'));

// «Бомба»: крошечный PNG, в заголовке которого заявлено 40000x40000 (раскодировать нельзя — только прочитать заголовок)
{
  const { crc32 } = await import('node:zlib');
  const chunk = (type, data) => {
    const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
    const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
    const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(body) >>> 0);
    return Buffer.concat([len, body, crc]);
  };
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(40000, 0); ihdr.writeUInt32BE(40000, 4); ihdr[8] = 8; ihdr[9] = 2;
  writeFileSync(join(out, 'bomb.png'), Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr), chunk('IEND', Buffer.alloc(0)),
  ]));
}
