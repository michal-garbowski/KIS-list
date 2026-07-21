# API prostego systemu bibliotecznego
Projekt zawiera proste JSON API do zarządzania stanem posiadanych przez bibliotekę książek:
- dodawanie
- usuwanie
- lista
- wypożyczanie/zwrot

## Wymagania
* Docker
* Docker Compose

Zalecany sposób instalacji:

```bash
make setup
```

`make setup`:
- kopiuje `.env.example`/`app/.env.example` do `.env` (jeśli jeszcze nie istnieją),
- buduje obrazy i uruchamia kontenery,
- instaluje zależności Composera i migruje bazę - automatycznie, w kontenerze `php`
  (`docker/php/entrypoint.sh`), bez ręcznych kroków,
- wypisuje adres aplikacji na koniec.

Po instalacji API będzie dostępne pod `http://localhost:8080`.

Alternatywnie, bez `make`, ten sam efekt daje:

```bash
cp .env.example .env
docker compose up
```

`make uninstall`: zatrzymuje i usuwa kontenery, wolumeny oraz obrazy - pełne
odinstalowanie środowiska (nie kasuje plików projektu, tylko środowisko).

## Model danych

Książka podsaida:
- **numer seryjny** - unikalny, sześciocyfrowy, nadawany przez pracownika przy dodaniu;
  jest naturalnym kluczem głównym,
- tytuł, autor,
- stan wypożyczenia: czy jest obecnie wypożyczona, kiedy i przez kogo (sześciocyfrowy
  numer karty bibliotecznej).

## Endpointy

| Metoda | Ścieżka | Body | Odpowiedź |
|---|---|---|---|
| `GET` | `/books` | - | `200`, tablica książek (posortowana po numerze seryjnym) |
| `POST` | `/books` | `{serialNumber, title, author}` | `201`, utworzona książka |
| `DELETE` | `/books/{serialNumber}` | - | `204`, bez treści |
| `POST` | `/books/{serialNumber}/borrow` | `{borrowerCardNumber}` | `200`, zaktualizowana książka |
| `POST` | `/books/{serialNumber}/return` | - (ignorowane, jeśli obecne) | `200`, zaktualizowana książka |

Kształt odpowiedzi jest zawsze taki sam, niezależnie od stanu - pola wypożyczenia jako
jawny `null`, nigdy pomijane:

```json
{
  "serialNumber": "000042",
  "title": "Lalka",
  "author": "Bolesław Prus",
  "borrowed": false,
  "borrowedAt": null,
  "borrowerCardNumber": null
}
```

### Przykłady

```bash
# Dodanie książki
curl -X POST http://localhost:8080/books \
  -H "Content-Type: application/json" \
  -d '{"serialNumber":"000042","title":"Lalka","author":"Bolesław Prus"}'

# Lista książek
curl http://localhost:8080/books

# Wypożyczenie
curl -X POST http://localhost:8080/books/000042/borrow \
  -H "Content-Type: application/json" \
  -d '{"borrowerCardNumber":"654321"}'

# Zwrot
curl -X POST http://localhost:8080/books/000042/return

# Usunięcie (działa niezależnie od tego, czy książka jest wypożyczona)
curl -X DELETE http://localhost:8080/books/000042
```

## Pokazywanie błędów

Wszystkie odpowiedzi błędów na trasach `/books*` mają ten sam format:

```json
{"error": {"code": "BOOK_ALREADY_BORROWED", "message": "Book is already borrowed."}}
```

Błędy walidacji dodatkowo zawierają `details` z listą komunikatów na każde pole:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Request validation failed.",
    "details": {"serialNumber": ["This value should contain exactly six digits."]}
  }
}
```

| Sytuacja                                                    | HTTP | Kod |
|-------------------------------------------------------------|---|---|
| niepoprawny JSON w body                                     | `400` | `INVALID_JSON` |
| nieznany URL / niepoprawny numer seryjny w URL              | `404` | `NOT_FOUND` |
| książka o podanym numerze nie istnieje                      | `404` | `BOOK_NOT_FOUND` |
| niedozwolona metoda                                         | `405` | `METHOD_NOT_ALLOWED` |
| nieobsługiwany `Content-Type`                               | `415` | `UNSUPPORTED_MEDIA_TYPE` |
| błąd walidacji inputu (np. nieznane pole w body)            | `422` | `VALIDATION_FAILED` |
| próba wypożyczenia już wypożyczonej książki                 | `409` | `BOOK_ALREADY_BORROWED` |
| próba zwrotu już dostępnej książki                          | `409` | `BOOK_ALREADY_AVAILABLE` |
| próba dodania książki o istniejącym numerze seryjnym        | `409` | `BOOK_ALREADY_EXISTS` |
| równoczesna modyfikacja tej samej książki (konflikt wersji) | `409` | `CONCURRENT_MODIFICATION` |
| nieoczekiwany błąd serwera                                  | `500` | `INTERNAL_SERVER_ERROR` |

## Testy

```bash
make test
```

`make test`:
- tworzy (jeśli nie istnieje) osobną bazę testową,
- migruje ją,
- uruchamia pełny zestaw testów funkcjonalnych PHPUnit,
- nigdy nie dotyka bazy deweloperskiej.

## Uwagi i podjęte decyzje

- **API nie zawiera uwierzytelniania** - (poza zakresem zadania).
- **Numer seryjny jako klucz główny**, nie osobne `id` - z treści zadania wynika, że
  to stabilny, unikalny nadawany jednorazowo identyfikator egzemplarza, i to on używany
  jest w całej domenie; osobny `id` byłby zbędnym, drugim identyfikatorem tej samej rzeczy.
- **Brak osobnej kolumny `isBorrowed`** - stan wypożyczenia to dwa powiązane pola
  (`borrowedAt`, `borrowerCardNumber`): oba `null` (dostępna) albo oba ustawione
  (wypożyczona), wymuszone ograniczeniem `CHECK` w bazie. Redundantna osobna flaga
  mogłaby dopuścić niespójne stany.
- **Dedykowane akcje `borrow`/`return`** zamiast generycznego `PATCH` - jednoznaczne
  wejście, mniej dwuznaczności walidacyjnej.
- **Usuwanie dla uproszczenia działa niezależnie od stanu wypożyczenia** - zadanie tego
  nie rozstrzyga.
- **Brak endpointu do pobrania pojedynczej książki oraz edycji tytułu/autora** - jako, 
  że nie były wymagane, zostały pominiięte.
- **Kontrola współbieżności przez optymistyczne wersjonowanie** (Doctrine
  `#[ORM\Version]`). Dwa równoczesne żądania `borrow` na tej samej książce mogłyby oba
  przejść walidację stanu przed zapisem (race condition); wersjonowanie sprawia, że zapis
  tego z nich, który przegrał wyścig, zostaje odrzucony przez bazę zamiast po cichu
  nadpisać zmianę pierwszego.
