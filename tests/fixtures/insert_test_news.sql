-- Тестовые данные RSS новостей для тестирования AI Pipeline
-- ============================================================

-- Очистка тестовых данных
DELETE FROM rss2tlg_items WHERE feed_id IN (999, 998);

-- Новость 1: Технологии (English)
INSERT INTO rss2tlg_items (
    feed_id, content_hash, guid, title, link, description, content, pub_date,
    extraction_status, is_published, created_at
) VALUES (
    999,
    MD5('test_article_1'),
    'test_guid_1',
    'OpenAI Launches GPT-5 with Revolutionary Capabilities',
    'https://example.com/openai-gpt5',
    'OpenAI unveils GPT-5, featuring unprecedented reasoning abilities...',
    'OpenAI has announced the release of GPT-5, the latest iteration of its large language model. The new model demonstrates significant improvements in reasoning, mathematical problem-solving, and multi-modal understanding. CEO Sam Altman stated that GPT-5 represents "a quantum leap in AI capabilities." The model can now process up to 1 million tokens of context and shows human-level performance on complex reasoning tasks. The release comes after 18 months of development and incorporates novel training techniques including reinforcement learning from human feedback (RLHF) and constitutional AI principles. Industry experts predict GPT-5 will accelerate AI adoption across enterprise applications, with potential market impact estimated at $50 billion by 2025.',
    '2024-11-08 10:00:00',
    'success',
    0,
    NOW()
);

-- Новость 2: Экономика (English)
INSERT INTO rss2tlg_items (
    feed_id, content_hash, guid, title, link, description, content, pub_date,
    extraction_status, is_published, created_at
) VALUES (
    999,
    MD5('test_article_2'),
    'test_guid_2',
    'Federal Reserve Raises Interest Rates to 5.5%',
    'https://example.com/fed-rates',
    'The Federal Reserve announces another interest rate hike...',
    'The Federal Reserve announced today a 25 basis point increase in interest rates, bringing the federal funds rate to 5.5%. This marks the 11th consecutive rate hike since March 2022 as the Fed continues its fight against inflation. Fed Chair Jerome Powell stated that while inflation has moderated from its peak of 9.1% to the current 3.7%, it remains above the Fed''s 2% target. The decision was made unanimously by the Federal Open Market Committee (FOMC). Economic analysts warn that the higher rates could slow economic growth and potentially trigger a recession. Stock markets reacted negatively, with the S&P 500 falling 2.1% and the Dow Jones dropping 450 points. Mortgage rates have climbed to 7.8%, the highest level in 23 years.',
    '2024-11-08 09:30:00',
    'success',
    0,
    NOW()
);

-- Новость 3: Наука (English)
INSERT INTO rss2tlg_items (
    feed_id, content_hash, guid, title, link, description, content, pub_date,
    extraction_status, is_published, created_at
) VALUES (
    999,
    MD5('test_article_3'),
    'test_guid_3',
    'Scientists Discover Potential Cure for Alzheimer''s Disease',
    'https://example.com/alzheimers-cure',
    'Breakthrough research shows promising results in treating Alzheimer''s...',
    'Researchers at Stanford University have announced a groundbreaking discovery that could lead to a cure for Alzheimer''s disease. The study, published in Nature Medicine, demonstrates that a new drug compound called ALZ-2024 can reverse cognitive decline in mice by targeting toxic protein buildup in the brain. Lead researcher Dr. Emily Chen explained that the compound works by enhancing the brain''s natural ability to clear amyloid-beta plaques and tau tangles, the hallmarks of Alzheimer''s pathology. In clinical trials involving 500 patients with early-stage Alzheimer''s, 68% showed significant improvement in memory and cognitive function after 12 months of treatment. The drug is now entering Phase III trials with 5,000 participants across 20 countries. If successful, it could receive FDA approval by 2026, offering hope to the 55 million people worldwide living with dementia.',
    '2024-11-08 08:45:00',
    'success',
    0,
    NOW()
);

-- Новость 4: Политика (Russian)
INSERT INTO rss2tlg_items (
    feed_id, content_hash, guid, title, link, description, content, pub_date,
    extraction_status, is_published, created_at
) VALUES (
    998,
    MD5('test_article_4'),
    'test_guid_4',
    'Саммит БРИКС: Страны обсуждают создание единой валюты',
    'https://example.ru/brics-summit',
    'На саммите БРИКС лидеры стран обсудили перспективы создания общей валюты...',
    'В Йоханнесбурге завершился 15-й саммит БРИКС, на котором лидеры стран-участниц обсудили возможность создания единой валюты для торговых операций между странами блока. Президент ЮАР Сирил Рамапоза заявил, что новая валюта может быть запущена уже в 2025 году и будет обеспечена корзиной национальных валют и золотом. В саммите приняли участие представители Бразилии, России, Индии, Китая, ЮАР, а также новые члены - Аргентина, Египет, Эфиопия, Иран, Саудовская Аравия и ОАЭ. Эксперты МВФ предупреждают, что создание альтернативной мировой резервной валюты может ослабить позиции доллара США. Совокупный ВВП стран БРИКС составляет 28% мировой экономики, что превышает долю G7. Детали технической реализации новой валютной системы будут разработаны специальной рабочей группой к марту 2024 года.',
    '2024-11-08 07:15:00',
    'success',
    0,
    NOW()
);

-- Новость 5: Спорт (English) - низкая важность
INSERT INTO rss2tlg_items (
    feed_id, content_hash, guid, title, link, description, content, pub_date,
    extraction_status, is_published, created_at
) VALUES (
    999,
    MD5('test_article_5'),
    'test_guid_5',
    'Local High School Wins State Basketball Championship',
    'https://example.com/basketball-championship',
    'Springfield High celebrates victory in state finals...',
    'Springfield High School''s basketball team defeated Lincoln High 78-72 to win the state championship on Saturday night. Senior point guard Marcus Johnson scored 28 points and was named tournament MVP. This is Springfield''s first state title in 15 years. The team finished the season with a 24-3 record. Coach Mike Anderson praised the team''s dedication and hard work throughout the season. The victory parade is scheduled for next Friday at 3 PM downtown.',
    '2024-11-08 06:00:00',
    'success',
    0,
    NOW()
);

-- Получаем ID вставленных записей для использования в тестах
SELECT 
    id,
    feed_id,
    title,
    CHAR_LENGTH(content) as content_length,
    pub_date
FROM rss2tlg_items 
WHERE feed_id IN (999, 998)
ORDER BY id DESC
LIMIT 5;
