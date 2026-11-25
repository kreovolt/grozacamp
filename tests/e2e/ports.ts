// Порты тестовых серверов PHP в одном месте: playwright.config.ts запускает их, спеки берут адреса отсюда.
export const PORT = 8099; // основной: storage/test
export const PORT_METRIKA = 8098; // с подключённой Метрикой (счётчик-образец)
export const PORT_ADMIN = 8097; // admin-shifts и admin-docs: storage/test-admin
export const PORT_TEXTS = 8096; // admin-texts: тексты и настройки меняются, редактор грузит много статики
export const PORT_HISTORY = 8095; // admin-history: откат и скрытие записей не мешают другим спекам

export const url = (port: number): string => `http://127.0.0.1:${port}`;
