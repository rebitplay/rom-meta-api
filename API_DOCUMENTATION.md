# ROM Meta API Documentation

## Base URL
```
http://localhost:8000/api
```

## Endpoints

### 1. Search Games
**GET** `/api/games?search={query}`

Intelligent search endpoint that automatically detects the type of input and searches accordingly.

#### Supported Search Types

| Type | Format | Example |
|------|--------|---------|
| **CRC32** | 8 hex characters | `9A024415` |
| **MD5** | 32 hex characters | `7616285DDCB0A1834770CACD20C2B2FE` |
| **SHA1** | 40 hex characters | `952D154DD2C6189EF4B786AE37BD7887C8CA9037` |
| **Serial** | Format: XXXX-12345 | `SLPS-01204` |
| **Name** | Any text | `Tetris`, `Super Mario` |

#### Request

```bash
GET /api/games?search={query}
```

#### Response (Success - Found)

```json
{
  "found": true,
  "detected_type": "crc",
  "search": "9A024415",
  "count": 1,
  "games": [
    {
      "id": 446914,
      "name": "10-Pin Bowling (Majesco) (USA) (Proto)",
      "description": null,
      "region": "USA",
      "release_year": 1999,
      "system": {
        "id": 96,
        "name": "Nintendo - Game Boy",
        "slug": "nintendo-game-boy"
      },
      "hashes": {
        "crc": "9A024415",
        "md5": "7616285DDCB0A1834770CACD20C2B2FE",
        "sha1": "952D154DD2C6189EF4B786AE37BD7887C8CA9037",
        "serial": null
      },
      "file": {
        "filename": "10-Pin Bowling (1998)(Majesco)(US)(proto).gb",
        "size": 131072
      },
      "developers": [
        {
          "id": 556,
          "name": "Morning Star Multimedia",
          "slug": "morning-star-multimedia"
        }
      ],
      "publishers": [
        {
          "id": 294,
          "name": "Majesco",
          "slug": "majesco"
        }
      ],
      "genres": [
        {
          "id": 2,
          "name": "Sports",
          "slug": "sports"
        }
      ]
    }
  ]
}
```

#### Response (Not Found)

```json
{
  "found": false,
  "detected_type": "crc",
  "search": "12345678",
  "message": "No games found"
}
```

**Status Codes:**
- `200 OK` - Games found
- `404 Not Found` - No games found
- `400 Bad Request` - Missing search parameter

---

### 2. Get Game by ID
**GET** `/api/games/{id}`

Get detailed information about a specific game by its database ID.

#### Request

```bash
GET /api/games/446914
```

#### Response (Success)

```json
{
  "game": {
    "id": 446914,
    "name": "10-Pin Bowling (Majesco) (USA) (Proto)",
    "description": null,
    "region": "USA",
    "release_year": 1999,
    "system": {
      "id": 96,
      "name": "Nintendo - Game Boy",
      "slug": "nintendo-game-boy"
    },
    "hashes": {
      "crc": "9A024415",
      "md5": "7616285DDCB0A1834770CACD20C2B2FE",
      "sha1": "952D154DD2C6189EF4B786AE37BD7887C8CA9037",
      "serial": null
    },
    "file": {
      "filename": "10-Pin Bowling (1998)(Majesco)(US)(proto).gb",
      "size": 131072
    },
    "developers": [...],
    "publishers": [...],
    "genres": [...]
  }
}
```

#### Response (Not Found)

```json
{
  "error": "Game not found",
  "message": "No game found with ID: 999999"
}
```

**Status Codes:**
- `200 OK` - Game found
- `404 Not Found` - Game not found

---

## Usage Examples

### cURL Examples

#### Search by CRC
```bash
curl "http://localhost:8000/api/games?search=9A024415"
```

#### Search by MD5
```bash
curl "http://localhost:8000/api/games?search=7616285DDCB0A1834770CACD20C2B2FE"
```

#### Search by SHA1
```bash
curl "http://localhost:8000/api/games?search=952D154DD2C6189EF4B786AE37BD7887C8CA9037"
```

#### Search by Serial (PlayStation, etc.)
```bash
curl "http://localhost:8000/api/games?search=SLPS-01204"
```

#### Search by Name
```bash
curl "http://localhost:8000/api/games?search=Tetris"
```

#### Get Specific Game
```bash
curl "http://localhost:8000/api/games/446914"
```

### JavaScript/Fetch Example

```javascript
// Search by CRC
const searchByCrc = async (crc) => {
  const response = await fetch(`http://localhost:8000/api/games?search=${crc}`);
  const data = await response.json();

  if (data.found) {
    console.log(`Found ${data.count} game(s)`);
    console.log(`Detected type: ${data.detected_type}`);
    data.games.forEach(game => {
      console.log(`- ${game.name} (${game.system.name})`);
    });
  } else {
    console.log('No games found');
  }
};

searchByCrc('9A024415');
```

### Python Example

```python
import requests

def search_game(query):
    response = requests.get(
        'http://localhost:8000/api/games',
        params={'search': query}
    )

    data = response.json()

    if data['found']:
        print(f"Found {data['count']} game(s)")
        print(f"Detected type: {data['detected_type']}")
        for game in data['games']:
            print(f"- {game['name']} ({game['system']['name']})")
            print(f"  CRC: {game['hashes']['crc']}")
            print(f"  Genres: {', '.join([g['name'] for g in game['genres']])}")
    else:
        print('No games found')

# Search by different types
search_game('9A024415')  # CRC
search_game('7616285DDCB0A1834770CACD20C2B2FE')  # MD5
search_game('Tetris')  # Name
```

---

## Response Fields

### Game Object

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Database ID |
| `name` | string | Game title |
| `description` | string/null | Game description |
| `region` | string/null | Region (USA, Japan, Europe, etc.) |
| `release_year` | integer/null | Year of release |
| `system` | object | Gaming system/platform |
| `hashes` | object | ROM hashes (CRC, MD5, SHA1, Serial) |
| `file` | object | File information |
| `developers` | array | List of developers |
| `publishers` | array | List of publishers |
| `genres` | array | List of genres |

### Hash Detection Logic

The API automatically detects the hash type based on:
1. **Length and format validation**
2. **Pattern matching** (hexadecimal for hashes, alphanumeric for serials)
3. **Fallback to name search** if no hash pattern matches

---

## Notes

- Name searches return up to **50 results** (configurable)
- Hash searches are **case-insensitive**
- Name searches use **partial matching** (LIKE query)
- All related data (developers, publishers, genres) are **eagerly loaded** for performance

---

## Error Handling

All errors return appropriate HTTP status codes and JSON responses:

```json
{
  "error": "Error type",
  "message": "Detailed error message"
}
```

Common error codes:
- `400` - Bad Request (missing parameters)
- `404` - Not Found (no results)
- `500` - Server Error
