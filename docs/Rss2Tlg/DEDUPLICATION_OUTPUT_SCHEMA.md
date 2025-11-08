# OutputSchema для модуля дедупликации

## JSON Schema для AI response

```json
{
  "deduplication_status": "checked",
  "is_duplicate": true,
  "duplicate_of_item_id": 12345,
  "similarity_score": 87.5,
  "confidence": 0.92,
  "similarity_method": "ai",
  "matched_entities": [
    "Elon Musk",
    "Tesla",
    "SEC"
  ],
  "matched_events": "SEC investigation into Tesla CEO's tweets about stock price manipulation",
  "matched_facts": [
    {"type": "date", "value": "2024-03-15", "matched": true},
    {"type": "number", "value": "$420", "matched": true},
    {"type": "location", "value": "USA", "matched": true}
  ],
  "reasoning": "Both articles describe the same SEC investigation event with matching key entities (Elon Musk, Tesla, SEC), same dates, and similar numeric facts. The core event is identical despite different wording."
}
```

## Поля

| Поле | Тип | Описание |
|------|-----|----------|
| `deduplication_status` | string | Статус проверки: "checked", "failed" |
| `is_duplicate` | boolean | Является ли новость дубликатом |
| `duplicate_of_item_id` | int\|null | ID оригинальной новости (если дубликат) |
| `similarity_score` | float | Оценка схожести 0.00-100.00 |
| `confidence` | float | Уверенность AI в результате 0.00-1.00 |
| `similarity_method` | string | Метод: "ai", "hash", "hybrid" |
| `matched_entities` | array | Совпавшие сущности (люди, компании, места) |
| `matched_events` | string | Описание совпавшего события |
| `matched_facts` | array | Совпавшие факты (даты, числа, локации) |
| `reasoning` | string | Объяснение почему дубликат или нет |

## Пример: НЕ дубликат

```json
{
  "deduplication_status": "checked",
  "is_duplicate": false,
  "duplicate_of_item_id": null,
  "similarity_score": 32.5,
  "confidence": 0.88,
  "similarity_method": "ai",
  "matched_entities": ["Apple", "iPhone"],
  "matched_events": null,
  "matched_facts": [],
  "reasoning": "While both articles mention Apple and iPhone, they describe completely different events: one is about iPhone 16 release, another about App Store policy changes. No matching core events or facts."
}
```
