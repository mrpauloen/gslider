# AGENT PLAYBOOK – ekosystem Gildaria

_Data aktualizacji: 2025-11-26_

Ten dokument scala opisuje kompletny zestaw zasad architektonicznych,
workflow agenta AI oraz szablonów dokumentacyjnych dla projektów wtyczek
WordPress tworzonych w ekosystemie Gildaria.

---

## 0. Cel, priorytety i sposób korzystania

- Pracujemy małymi krokami, zachowując spójność architektury i dokumentacji.
- Wytyczne są preferencjami; można je modyfikować, jeśli projekt tego wymaga,
	ale należy zachować ducha dokumentu i udokumentować wyjątek.

### 0.1. Priorytet decyzji (rozwiązywanie konfliktów)

1. Jednoznaczne instrukcje projektowe w repo (README, TODO, pliki konfiguracyjne).
2. Niniejszy playbook.
3. Jeśli brak reguły – wybierz działanie najbezpieczniejsze i eskaluj wątpliwość.

### 0.2. Zakres kompetencji agenta

- Analiza kodu, struktury, dokumentacji.
- Przygotowywanie TODO, DEVLOG, raportów i dokumentów pomocniczych.
- Implementacja kodu i testów zgodnie z ustalonym stylem.
- Agent unika nadpisywania dużych fragmentów bez konsultacji, generowania
	sprzecznych dokumentów oraz działań bez jasnego celu.

### 0.3. Kiedy pytać człowieka

- Zmiany potencjalnie usuwające dane użytkownika lub wymagające migracji.
- Breaking changes o niejasnym wpływie na inne projekty.
- Decyzje organizacyjne (licencje, publikacje, udostępnianie danych).

### 0.4. Workflow (kanoniczny)

1. Przeczytaj zadanie i kontekst repo.
2. Zaktualizuj TODO (`TODO.md` + narzędzie `manage_todo_list`).
3. Oznacz jedno zadanie jako `in-progress` i pracuj tylko nad nim.
4. Edytuj pliki przy pomocy `apply_patch`, respektując lokalny styl.
5. Uruchom odpowiednie testy/linty; popraw błędy maksymalnie trzy razy.
6. Aktualizuj TODO/DEVLOG po zakończeniu paczki.
7. Przekaż raport i proponowane kolejne kroki.

### 0.5. Zapis wyników i repozytorium dokumentów

- Wyniki pracy zapisuj w Markdownie. Dokumenty lądują w `docs/`, `docs/raporty/`,
  `docs/todo/` lub `docs/spec/` tylko wtedy, gdy zadanie tego wymaga.
- Raporty powinny mieć sekcje: Kontekst, Ustalenia, Rekomendacje, TODO,
	Wykonane kroki (szczegóły w sekcji 4).

> **Terminologia:** w dokumentacji i komentarzach używamy polskiego określenia
> „zaczep” (zamiast „hook”), mimo że w API WordPress funkcje nadal nazywają się
> `add_action`/`add_filter` itd.

---

## 1. Architektura wtyczek WordPress

### 1.1. Styl i klasy systemowe

- Kod piszemy obiektowo i modułowo, jedna klasa na plik.
- Klasy systemowe (`Init`, `Activate`, `Deactivate`, moduły domenowe) udostępniają
	statyczne `register()` podpinające zaczepy (`add_action`, `add_filter`).
- Konstruktory (`__construct`) służą tylko do „prawdziwego” konstruowania obiektów
	ze stanem (np. DTO, Value Objects, walidatory), a nie do podpinania zaczepów.
- Trzymamy się WordPress Coding Standards, gdzie to możliwe.

### 1.2. Struktura katalogów

```
<slug-wtyczki>/
	<slug-wtyczki>.php      # główny plik z nagłówkiem WP
	uninstall.php           # opcjonalne sprzątanie
	composer.json
	vendor/                 # Composer (na środowiskach docelowych)
	<src-dir>/              # kod źródłowy (np. src/, inc/, app/)
		Core/
		Admin/
		Domain/
		Update/
		...
```

- `<src-dir>` to jeden główny katalog kodu. Nazwę wybieramy raz na projekt.
- Nazwa pliku = nazwa klasy (`Init.php`, `Activate.php`, ...).

### 1.3. Namespace i porządek w pliku

1. `<?php`
2. Namespace jako pierwsza instrukcja (`namespace <NAMESPACE>\...;`).
3. Guard `if ( ! defined( 'ABSPATH' ) ) { exit; }` po namespace.
4. Stałe namespacowe (`FILE`, `DIR`, `VERSION`) oraz importy `use`.
5. Kod klasy lub funkcji.

Przykładowy główny plik:

```php
<?php

namespace Gildaria;

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}

const FILE    = __FILE__;
const DIR     = __DIR__ . '/';
const VERSION = '0.1.0';

function link( string $path = '' ): string {
		return plugin_dir_url( FILE ) . ltrim( $path, '/' );
}

// Nazwę helpera (`link`, `url`, `plugin_url`) dobieramy do projektu, ale
// zachowujemy spójność w całej wtyczce.

use Gildaria\Core\Init;
use Gildaria\Core\Activate;
use Gildaria\Core\Deactivate;

register_activation_hook( FILE, [ Activate::class, 'activate' ] );
register_deactivation_hook( FILE, [ Deactivate::class, 'deactivate' ] );

add_action( 'plugins_loaded', static function () {
		Init::register();
} );
```

Założenia:

- Nie poprzedzamy funkcji WP backslashami.
- Helpery (`link()`, `url()`) zastępują stałe wymagające funkcji WP.

### 1.4. `Core\Init` i lista serwisów

```php
final class Init {
		public static function register(): void {
				if ( class_exists( Requirements::class ) && ! Requirements::check() ) {
						Requirements::register_admin_notice();
						return;
				}

				$services = [
						CPT\Rzemieslnicy::class,
						CPT\Realizacje::class,
						Capabilities\Capabilities::class,
						// ... kolejne moduły
				];

				foreach ( $services as $service_class ) {
						if ( method_exists( $service_class, 'register' ) ) {
								$service_class::register();
						}
				}
		}
}
```

- Żadnego runtime’owego skanowania katalogów – lista serwisów jest jawna.
- Nowe moduły dopisujemy do `$services` manualnie.

### 1.5. Wymagania środowiskowe (`Requirements` / `Require`)

- Klasa może nazywać się `Require` lub `Requirements` – ważna jest spójność.
- Sprawdza minimalne wersje PHP/WP i rejestruje notice w kokpicie.
- Jeśli `Requirements::check()` zwróci `false`, `Init::register()` kończy pracę po
	pokazaniu komunikatu administratorowi.

#### 1.5.1 Wymagania wersji PHP i WordPress

- Dla każdej wtyczki w ekosystemie **obowiązkowo** ustawiamy w nagłówku:
  - `Requires PHP: X.Y` – minimalna wersja PHP,
  - `Requires at least: A.B` – minimalna wersja WordPressa.
- Wartość `Requires PHP` dobieramy tak, aby **na pewno** obsługiwała:
  - `namespace` i `use`,
  - typy i zwroty, których używamy globalnie (np. `: void`, `?string`, itp.),
  - inne nowe konstrukcje, jeśli są użyte poza „bootloaderem”.
- Zakładamy, że **WordPress blokuje aktywację** wtyczki na starszym PHP tylko na podstawie nagłówka – zanim zacznie wykonywać nasz kod.

Przykładowy nagłówek:

```php
<?php
/**
 * Plugin Name: Gildaria
 * Description: Core ekosystemu Gildaria
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain: gildaria
 */
```
Runtime Requirements::check()

Dodatkowa klasa Requirements jest używana jako drugi poziom ochrony w runtime:

- sprawdzenie wersji WordPressa ($wp_version),
- obecności rozszerzeń PHP (np. extension_loaded( 'intl' )),
- innych warunków środowiskowych.

Init::register() na starcie wywołuje:

```php
if ( class_exists( Requirements::class ) && ! Requirements::check() ) {
    Requirements::register_admin_notice();
    return;
}
```

W aktualnych projektach Gildarii:

- polegamy głównie na nagłówku (Requires PHP),
- Requirements::check() traktujemy jako rozszerzalny „bajer” do dodatkowych wymagań środowiska.
- Wariant „bootloadera” (opcjonalny, tylko dla publicznych wtyczek)

Jeżeli kiedyś będziemy wydawać wtyczkę publicznie (np. na wp.org), dopuszczalny jest wariant z prostym plikiem „bootloadera” bez namespace, który:

- sprawdza PHP_VERSION w starej, kompatybilnej składni, pokazuje notice w adminie,
- dopiero po spełnieniu warunków ładuje właściwy kod (autoload + Init::register()).

Na ten moment nie stosujemy tego wariantu w prywatnych wtyczkach Gildarii, ale traktujemy go jako wzorzec „pancernego” startu na przyszłość.

### 1.6. Aktywacja i dezaktywacja

- `Core\Activate::activate()` – rejestrowanie CPT/taxonomii (zanim `flush_rewrite_rules`),
	nadawanie ról/capabilities, przygotowanie opcji.
- `Core\Deactivate::deactivate()` – lekkie sprzątanie (usuwanie transientów,
	cofnięcie dodatkowych capów); nie kasujemy danych biznesowych.
- Zaczepy aktywacji/dezaktywacji rejestrujemy tylko w głównym pliku.

### 1.7. Composer, PSR-4 i vendor

```jsonc
{
	"name": "mrpauloen/<slug>",
	"description": "Krótki opis roli wtyczki.",
	"type": "wordpress-plugin",
	"license": "GPL-2.0-or-later",
	"keywords": [ "wordpress", "plugin", "gildaria" ],
	"authors": [ { "name": "Pawel Nowak" } ],
	"require": { "php": ">=7.4" },
	"autoload": {
		"psr-4": {
			"Gildaria\\": "src/"
		}
	}
}
```

- `composer dump-autoload` uruchamiamy po każdej zmianie w `composer.json`.
- Prefiks vendorowy można dostosować, lecz domyślnie używamy `mrpauloen/`.

### 1.8. Nagłówek wtyczki i GildUp

```
/**
 * Plugin Name:  <Nazwa>
 * Description:  ...
 * Version:      0.1.0
 * Author:       <Autor>
 * Text Domain:  <text-domain>
 * GildUp Repo:  mrpauloen/<repo>
 */
```

- Linia `GildUp Repo:` pozwala platformie GildUp wykrywać aktualizacje. Usuń ją,
	jeśli wtyczka nie ma być zarządzana centralnie.

### 1.9. Nazwy, skróty, helpery

- Skracamy nazwy tylko wtedy, gdy są czytelne (`CMap`, `Caps`, `Require`).
- Stałe i helpery pozostają krótkie (`FILE`, `DIR`, `VERSION`, `link()`).
- Przechowujemy prefiksy meta/CPT jako `const` w odpowiednich klasach.

### 1.10. `uninstall.php`

- Jeśli projekt wymaga sprzątania przy dezinstalacji, dodaj plik z guardem
	`if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }` i ogranicz się do opcji,
	cache, transientów. Danych biznesowych nie usuwamy bez wymogu.

---

## 2. Dokumentacja, DEVLOG i wersjonowanie

### 2.1. Obowiązkowe pliki pomocnicze

- `TODO.md` – blueprint architektury, lista zadań (do zrobienia / w toku / gotowe).
- `DEVLOG.md` – dziennik zmian (sekcje `## [wersja] – data` z wypunktowanymi krokami).

### 2.2. Blueprint i opisy

- W `TODO.md` (lub `docs/BLUEPRINT.md`) opisz rolę wtyczki, moduły (`Core`, `CPT`,
	`Meta`, `Settings`, itd.), kluczowe typy danych i integracje.
- Aktualizuj blueprint po większych refaktorach i notuj to w `DEVLOG.md`.

### 2.3. Praca z wersjami

- Wszystkie projekty używają SemVer (`MAJOR.MINOR.PATCH`). Startujemy zwykle od
	`0.1.0` (faza rozwojowa).
- `@since` w PHPDoc odpowiada aktualnej wersji z nagłówka/stałej `VERSION`.
- Breaking change → zwiększ MAJOR (lub MINOR w fazie 0.x) i dopisz sekcję
	w `DEVLOG.md`. Drobne poprawki → PATCH.
- Po zmianie wersji zaktualizuj nagłówek wtyczki, `const VERSION` i `DEVLOG.md`.

### 2.4. DevLog vs changelog publiczny

- `DEVLOG.md` to notatnik deweloperski (bardziej techniczny, wewnętrzny).
- Produkcyjny changelog (np. `readme.txt` WordPressa) można utrzymywać osobno,
	gdy pojawią się wydania 1.x.

### 2.5. Instrukcje etapowe (`docs/INSTRUKCJA-YYYY-MM-DD.md`)

- Instrukcje w katalogu `docs/` traktujemy jako specyfikację zadania/refaktoru
	(bez checkboxów). Każda nowa wersja otrzymuje datę w nazwie – nowsza data
	**automatycznie unieważnia** starsze instrukcje (można je traktować jako
	zrealizowane lub archiwalne).
- Workflow: najpierw czytamy bieżącą instrukcję, analizujemy wykonalność,
	dopiero potem tworzymy listę TODO w `TODO.md` (która jest zsynchronizowana z
	`manage_todo_list`).
- Instrukcje mogą mieć rozbudowane opisy etapów; po zakończeniu etapu zaznaczamy
	go w `TODO.md` i dopisujemy wynik w `DEVLOG.md`/`TestLog` (jeśli dotyczy).

---

## 3. Workflow repozytorium i narzędzia

- Zawsze korzystaj z `manage_todo_list` przy zadaniach wieloetapowych i utrzymuj
	tylko jedno `in-progress` naraz.
- Edycji dokonuj przez `apply_patch` (zwięzłe diffy, brak zbędnych zmian).
- Po każdej istotnej edycji kodu uruchamiaj dostępne testy/linty.
- Masz do trzech prób naprawy błędów; potem eskaluj.
- Przygotuj wpis w `DEVLOG.md` po każdej większej paczce.

---

## 4. Raportowanie i formatowanie Markdown

### 4.1. Format raportu

```
# Raport: <Temat>
Data: <YYYY-MM-DD>
Agent: <Nazwa>

1. Kontekst
2. Ustalenia
3. Rekomendacje
4. TODO
5. Wykonane kroki
```

- Sekcje mogą być rozwinięte w Markdown, ale zachowaj powyższy układ.

### 4.2. Listy TODO

```
## TODO

- [ ] zadanie 1
- [ ] zadanie 2

### Wynik: zadanie 1
Opis wykonania.
```

- Lista może się rozwijać wraz z zadaniem. Po zakończeniu dopisz krótkie
	podsumowanie (`Wynik: ...`).

### 4.3. Zasady Markdown

- Puste linie przed/po nagłówkach i listach (MD022, MD032).
- Puste linie przed/po blokach kodu (MD031) i każdorazowo określ język (MD040).
- Bez pogrubienia jako nagłówków (MD036); nagłówki bez interpunkcji na końcu
	(MD026).
- Każdy plik kończy się pojedynczą pustą linią (MD047).
- Tabele mają odstępy i nagłówki (MD058).

### 4.4. Zapis wyników i katalogi

- Dokumenty w repo (`docs/`, `docs/raporty/`, `docs/spec/`, itp.) twórz tylko,
	gdy zadanie tego wymaga. Unikaj mnożenia plików bez potrzeby.

---

## 5. Szablony i przykłady

### 5.1. Nagłówek wtyczki

```
/**
 * Plugin Name:  Example Plugin
 * Description:  Example description.
 * Version:      0.1.0
 * Author:       Example Author
 * Text Domain:  example-plugin
 * GildUp Repo:  mrpauloen/example-plugin
 */
```

### 5.2. Minimalny `Init.php`

```php
<?php

namespace Example\Core;

final class Init {
		public static function register(): void {
				// sprawdź wymagania, podłącz moduły
		}
}
```

### 5.3. Szablon DevLog

```
# DEVLOG – <Nazwa wtyczki>

## [0.1.0] – 2025-11-25

- Inicjalizacja projektu, dodany szkielet.
- Utworzono TODO.md z opisem struktury.
```

### 5.4. Szablon TODO

```
## TODO

- [ ] Zadanie 1 — krótki opis
- [ ] Zadanie 2 — krótki opis

### Wynik: Zadanie 1
Opis wykonania.
```

---

## 6. Bezpieczeństwo, testy i code review

### 6.1. Zasady operacyjne

- Każdą serię wywołań narzędzi poprzedzaj krótkim opisem celu.
- Po edycjach kodu uruchamiaj testy/linty. Raportuj wynik (sukces/błąd).
- Długie procesy (np. serwery) uruchamiaj w tle tylko gdy konieczne.

### 6.2. Bezpieczeństwo

- Nie commituj sekretów, kluczy API ani danych wrażliwych.
- Używaj zmiennych środowiskowych zamiast wpisywania wartości do repo.
- Jeśli sekret pojawi się w patchu, usuń go natychmiast i zgłoś sytuację.

### 6.3. Testy i CI

- Docelowo konfiguruj CI uruchamiające testy jednostkowe, analizy statyczne i
	linty. Lokalnie uruchamiaj te same polecenia.
- Przygotuj skrypty smoke-testów (np. `wp plugin activate`, `wp eval`), gdy
	środowisko na to pozwala.

### 6.4. Code review i PR

- Używaj szablonów PR/issue. W opisie zmian wymień testy, wpływ na API oraz
	aktualizacje dokumentacji.
- Przy recenzji przejdź checklistę: testy, lintery, dokumentacja, kompatybilność.

### 6.5. Polityki twarde vs miękkie

- **Twarde:** brak sekretów w repo, edycje tylko przez `apply_patch`,
	aktualizacja `DEVLOG.md`, zachowanie kolejności priorytetów.
- **Miękkie:** skróty nazw klas, dokładne brzmienie wpisów README/DEVLOG – można
	je dopasować do projektu.

---

## 7. Utrzymanie dokumentu i dalsze kroki

- Ten plik jest źródłem prawdy. Jeśli projekt wymaga wyjątków, opisz je tutaj
	lub w dedykowanej sekcji repo.
- Po każdej zmianie playbooka dopisz wpis w `DEVLOG.md`.
- Zalecane kolejne kroki:
	1. Aktualizuj instrukcje wraz z nowymi praktykami.
	2. Dodawaj gotowe szablony (TODO, DEVLOG, raporty) do nowych repozytoriów.
	3. Konfiguruj CI/testy oraz zasady code review w miarę dojrzewania projektu.
- W razie potrzeby eskalacji utwórz issue z etykietą `agent-decision`, streść
	problem i zaproponuj wariant.

---

## 7.1. Standardy graficzne i zasoby zewnętrzne

### 7.1.1. Placeholder obrazków – placehold.co

W całym ekosystemie Gildeo/Gildaria **obowiązkowo** używamy serwisu
**https://placehold.co** jako standardowego źródła placeholder'ów obrazków.

**Zasady:**

1. **Nigdy nie używaj** losowych URLi obrazków z internetu w przykładach,
   dokumentacji lub kodzie deweloperskim.

2. **Zawsze używaj** placehold.co z wariantem PNG:
   ```
   https://placehold.co/600x400.png?text=Rzemieslnik
   https://placehold.co/400x400/0f172a/f9fafb.png?text=Avatar
   ```

3. **Rozróżniaj dwa typy obrazków:**

   - **Avatar** – mały kwadratowy obrazek (40×40, 64×64, 80×80 px) używany
     przy nazwach, listach, profilach użytkownika.
   - **Content/gallery image** – większy obrazek (600×400, 800×600, 400×400 px)
     używany w kartach, sliderach, hero sections, galeriach.

4. **W kodzie React/PHP/CSS** definiuj stałe dla placeholderów, np.:

   ```ts
   const GILDEO_PLACEHOLDER_IMAGE_LARGE = 
     'https://placehold.co/600x400.png?text=Rzemieslnik';
   
   const GILDEO_PLACEHOLDER_IMAGE_SQUARE = 
     'https://placehold.co/400x400.png?text=Rzemieslnik';
   
   const GILDEO_PLACEHOLDER_AVATAR_SMALL = 
     'https://placehold.co/80x80.png?text=Avatar';
   ```

5. **Placeholder to tylko fallback** – prawdziwe obrazy (avatary, zdjęcia,
   galerie) powinny pochodzić z REST API lub bazy danych. React/frontend używa
   placeholdera **tylko wtedy**, gdy:
   - `marker.avatarUrl` / `marker.photoUrl` / `marker.images` jest puste,
   - jesteśmy w środowisku deweloperskim i testujemy layout.

6. **Nie wymyślaj** avatarów ani obrazków w warstwie frontendowej – to
   odpowiedzialność backendu/REST API. Frontend tylko wyświetla to, co dostanie,
   lub fallback.

**Zastosowanie:**

- Komponenty React (mapy, karty, listy)
- Przykłady w dokumentacji
- Testy layout'u i responsywności
- Szablony PHP (gdy brak realnych danych)

---

## 8. DevLog, TestLog, TODO i znaczniki czasu

Ta sekcja opisuje, jak agent ma:

* prowadzić dziennik zmian (**DevLog**),
* opcjonalnie prowadzić dziennik testów (**TestLog**),
* synchronizować wewnętrzną listę zadań (`manage_todo_list`) z plikiem TODO w repo,
* używać znaczników czasu tak, żeby dało się odtworzyć kolejność zdarzeń.

### 8.1. Znaczniki czasu – ogólne zasady

1. Każdy **nowy wpis w DevLog** musi mieć znacznik czasu w nagłówku:

	 ```plaintext
	 YYYY-MM-DD HH:MM
	 ```

	 Przykład:

	 ```markdown
	 ### 2025-11-30 18:45 – Refaktoryzacja geodanych Gildaria/Gildeo
	 ```

2. Każdy **nowy wpis w TestLog** również ma własny, niezależny znacznik czasu w tym samym formacie:

	 * Testy **zawsze** logicznie dzieją się **po** zmianach,
	 * ale odstęp może być:

		 * minutowy,
		 * godzinny,
		 * albo nawet kilkutygodniowy.

3. **Znaczniki czasu DevLog i TestLog NIE muszą być identyczne.**
	 Powiązanie między wpisami odbywa się:

	 * poprzez opis w nagłówku (np. „Testy: geodata po refaktoryzacji 2025-11-30 18:45”),
	 * oraz poprzez treść (odniesienie do daty / opisu wpisu w DevLog).

4. Znacznik czasu odzwierciedla **moment sporządzenia wpisu**, a nie „idealny” moment samej zmiany / testu.
	 Agent **nie cofa** timestampów wstecz – zawsze używa aktualnej daty i godziny systemowej.

---

### 8.2. DevLog – co i jak zapisywać

**Cel:** odpowiedź na pytanie „co zostało zmienione w kodzie / konfiguracji i kiedy”.

* Plik: np. `DevLog.md`.
* Forma: Markdown, krótkie wpisy.

Każdy wpis ma strukturę:

```markdown
### 2025-11-30 18:45 – Krótkie hasło zmiany

**Kontekst**

- O czym jest ta zmiana (moduł, wtyczka, funkcjonalność).

**Zakres prac**

- Konkretne punkty:
	- jakie pliki,
	- jaki typ zmiany (refaktoryzacja, nowe API, poprawka błędu, itp.).

**Powiązania**

- Jeśli dotyczy, odwołanie do:
	- wcześniejszego wpisu DevLog,
	- istniejącego lub planowanego wpisu TestLog (np. „testy: patrz TestLog wpis z 2025-12-01 10:15”).

**Uwagi**

- Krótkie dodatkowe notatki, np. o kompatybilności, ryzykach.
```

Zasady:

* Nie wklejać całych plików z kodem – tylko opisowo.
* DevLog jest **zawsze obowiązkowy** dla każdej większej zmiany w kodzie.

---

### 8.3. TestLog – opcjonalny, ale mocno zalecany

**Cel:** odpowiedź na pytanie „co dokładnie było testowane, jak i z jakim wynikiem”.

* Plik: np. `TestLog.md`.
* TestLog jest **opcjonalny** – agent ma go tworzyć, gdy:

	* użytkownik poprosi o dokumentowanie testów,
	* albo zmiana jest większa / ryzykowna i wymaga śladu testowego.

Jeśli testy są wykonywane „na szybko” i **nie ma czasu / chęci**, żeby je spisać – to jest akceptowalne.
Agent NIE blokuje prac, jeśli TestLog nie powstanie.

#### 8.3.1. Struktura wpisu TestLog

```markdown
### 2025-12-01 10:15 – Testy: geodata po refaktoryzacji z 2025-11-30 18:45

**Zakres testu**

- Co jest testowane (funkcja, moduł, wtyczka).
- Do jakiego wpisu DevLog się to odnosi:
	- np. „Zmiana opisana w DevLog: 2025-11-30 18:45 – Refaktoryzacja geodanych Gildaria/Gildeo”.

**Lista testów**

- [ ] Scenario 1 – opis scenariusza
- [ ] Scenario 2 – opis scenariusza
- ...

Dla każdego scenariusza:

**Scenario 1 – zapis profilu z poprawnymi adresami**

- Kroki:
	1. ...
	2. ...
- Oczekiwane:
	- ...
- Wynik:
	- ✅ OK / ❌ Błąd
- Komentarz:
	- krótka uwaga (np. „OK”, „brak mapy dla branch_3 – do poprawy”).

**Podsumowanie**

- Ogólny wniosek z testów (co działa, co nie, co wymaga poprawki).
- Jeśli trzeba – informacja, że trzeba dodać/uzupełnić zadania w TODO.
```

#### 8.3.2. Wpisy „testów nie znaleziono”

Jeśli agent widzi w DevLog zmianę, do której **nie istnieje TestLog**, a użytkownik prosi o analizę/testy:

* Agent **NIE dopisuje wstecznego testu z dawną datą**.

* Zamiast tego tworzy **nowy wpis w TestLog** z BIEŻĄCYM timestampem:

	```markdown
	### 2025-12-10 09:30 – Testy zaległe: refaktoryzacja z 2025-11-30 18:45

	**Zakres testu**

	- Test zaległy dla zmiany opisanej w DevLog: 2025-11-30 18:45 – ...

	**Status wcześniejszych testów**

	- 🔸 Nie znaleziono potwierdzonego wpisu testowego z okresu wdrożenia.
	- Niniejszy wpis dokumentuje test wykonany po czasie.

	**Lista testów**

	- [x] Scenario 1 – ...
	- [ ] Scenario 2 – (jeszcze do wykonania)
	```

* Jeśli nie ma czasu na wykonanie scenariuszy, a użytkownik tylko każe „zaznaczyć, że test nie był zrobiony”, agent może dodać wpis TestLog z informacją wprost:

	```markdown
	### 2025-12-10 09:30 – Brak testów dla zmiany z 2025-11-30 18:45

	**Zakres**

	- Zmiana opisana w DevLog: 2025-11-30 18:45 – ...

	**Status**

	- 🔸 Nie przeprowadzono formalnych testów dla tej zmiany / brak danych o ich wyniku.
	- Testy do rozważenia w przyszłości.
	```

---

### 8.4. TODO – synchronizacja z `manage_todo_list`

Agent może używać wewnętrznej listy `manage_todo_list` w interfejsie czatu, ale **nie może traktować jej jako jedynego źródła prawdy**.

Obowiązuje zasada:

1. **Zadania dla projektu muszą być utrwalone w repo** – w pliku TODO, np. `TODO.md`:

	 * struktura np.:

		 ```markdown
		 ## Lista zadań

		 - [ ] 2025-11-30 – Refaktoryzacja geodanych Gildaria/Gildeo (Etap 1)
		 - [ ] 2025-11-30 – Uporządkowanie MiniMap::render() (Etap 2)
		 - [ ] 2025-12-01 – Dopisanie TestLog dla refaktoryzacji geodanych
		 ```

2. Jeśli agent tworzy / modyfikuje `manage_todo_list`:

	 * musi w rozsądnym momencie **zaktualizować odpowiadającą listę w `TODO.md`**,
	 * nazwy zadań powinny być spójne (np. „Etap 1 – ...”) ↔ to samo w `TODO.md`.

3. Jeśli zadanie zostaje wykonane:

	 * agent odznacza je w `manage_todo_list`,
	 * **i** odznacza je w `TODO.md` (zmienia `- [ ]` na `- [x]` + ewentualnie dopisuje krótką informację lub timestamp, kiedy faktycznie wykonano).

4. W przypadku utraty `manage_todo_list` (reset, nowy czat, itp.):

	 * **referencyjnym źródłem prawdy** jest `TODO.md` w repo i ewentualnie DevLog/TestLog,
	 * agent może odbudować nową `manage_todo_list` na bazie `TODO.md`.

**Workflow operacyjny**

1. **Planowanie** – podczas rozpoczynania nowego etapu dopisz/uzupełnij wpis w `TODO.md` oraz utwórz odpowiadający wpis w `manage_todo_list` o tej samej nazwie.
2. **Status `in-progress`** – jednorazowo tylko jedno zadanie w `manage_todo_list` może mieć status `in-progress` i musi wskazywać dokładnie ten etap, nad którym pracujesz.
3. **Zamknięcie zadania** – gdy etap jest skończony, najpierw oznacz go jako `[x]` w `TODO.md`, a następnie natychmiast ustaw status `completed` w `manage_todo_list`.
4. **Reset listy** – jeśli interfejs traci historię `manage_todo_list`, odbuduj ją na podstawie `TODO.md`, które pozostaje źródłem prawdy, i dopiero potem kontynuuj prace.

Przykład wpisu w repo:

```markdown
- [ ] Etap 21 — Refactor Validation
	- Cel: uprościć Processor.
	- Status Copilota: zadanie „Etap 21 – Refactor Validation” w `manage_todo_list`.
```

Po zamknięciu etapu zmieniamy checkbox na `[x]` i aktualizujemy status narzędzia.

---

### 8.5. Relacja DevLog ↔ TestLog ↔ TODO

* **DevLog** – mówi: *„to zostało zrobione”* (zmiana w kodzie).
* **TestLog** – mówi: *„to (nie) zostało przetestowane, w taki sposób, z takim wynikiem”.*
* **TODO** – mówi: *„to jeszcze trzeba zrobić / poprawić / przetestować”.*

Przykładowy przepływ:

1. Agent refaktoryzuje kod → tworzy wpis w DevLog (timestamp A).
2. Agent dodaje zadania w `TODO.md` (np. „przetestować scenariusze 1–3”).
3. Później wykonuje testy → tworzy wpis w TestLog (timestamp B > A).
4. Po wykonaniu testów – aktualizuje `TODO.md` (odhacza odpowiednie zadania).
5. Jeśli testów jeszcze nie było, a pojawi się nowe zadanie / nowy DevLog – agent w TestLog może wprost zaznaczyć, że wcześniejsze testy nie były wykonane / nie znaleziono ich śladu.
