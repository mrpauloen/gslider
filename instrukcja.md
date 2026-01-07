Masz zrobić NOWĄ wtyczkę WordPress o slugu: gslider.

WAŻNE (to jest najczęstszy błąd): 
- NIE używaj globali typu: wp.element, wp.blocks, wp.i18n, wp.components.
- Używaj importów ESM z paczek @wordpress/* (np. import { useEffect } from '@wordpress/element').
- Projekt ma działać z bundlerem @wordpress/scripts (build/start), a zależności WP mają być wykryte automatycznie.

WYMAGANIA (funkcjonalność):
Zbuduj pełnoprawną wtyczkę WP 6.9 z natywnym blokiem Gutenberg:
- Plugin slug: gslider
- Namespace PHP: GSlider\CoverSlider
- Textdomain: gslider
- Blok: gslider/block-cover-slider
- Label bloku: "GSlider: Cover Slider"
- Blok jest “przełącznikiem” cover-slidera i na froncie NIE renderuje HTML:
  - save() zwraca null
  - render_callback (albo render.php) zwraca pusty string
  - blok zostaje tylko jako komentarz w post_content, by wykryć has_block().

Atrybuty bloku:
- enabled (bool, default true)
- intervalMs (number, default 10000)
- fadeMs (number, default 900)
- overlayDim (number 0-100, default 30) — tylko do podglądu/klasy, nie edytuj cover automatycznie
- targetCoverSelector (string, default ".wp-block-cover.WPBlockCoverSlider")
- sourceSelector (string, default ".czik-hero-rotator__src")
- debugHighlight (bool, default true)

UI w edytorze:
- InspectorControls:
  - Toggle: Enabled
  - RangeControl: Interval (ms)
  - RangeControl: Fade (ms)
  - Toggle: Debug Highlight
  - TextControl: Target selector
  - TextControl: Source selector
- W edytorze pokaż placeholder-card:
  - ikonka + tytuł np. “Cover Slider: aktywny/wyłączony”
  - 2-3 linie: Target, Source, Interval
- UX highlight jak w naszych blokach:
  - hover na placeholder -> dodaj klasę np. "czik-coverslider--highlight" na elementy znalezione po selectorach
  - klik -> przypnij highlight (toggle pinned)
  - useEffect sprząta klasy na unmount
  - ma być odporne: jeśli selector nic nie znajdzie -> zero crashy.

Frontend:
- assets/gslider.css (użyj mojego CSS z instrukcji, w tym .text-shadow i warstwy cover)
- assets/gslider.js:
  - działa tylko jeśli target cover istnieje i sourceSelector ma >=2 obrazki
  - crossfade na 2 warstwach: baseImg + doczepiony imgB
  - pierwszy switch dopiero po intervalMs (żadnego mignięcia na starcie)
  - fade sterowany fadeMs
- Konfigurację (enabled/intervalMs/fadeMs/selektory) podaj JS przez wp_add_inline_script albo wp_localize_script:
  - window.GSliderCoverSlider = { enabled, intervalMs, fadeMs, targetCoverSelector, sourceSelector, debugHighlight }

Enqueue:
- PHP rejestruje/enqueuje:
  - frontend CSS/JS tylko gdy blok użyty w treści (has_block) LUB fallback (a JS sam się wyłączy jeśli nie ma targetu)
  - edytor: build/index.js + editor.css
- Wersjonowanie: filemtime
- Użyj register_block_type z block.json

FSE:
- Spróbuj wykryć blok też przy block theme (template/template-part). Jeśli trudne, zrób fallback: enqueue wszędzie, ale JS sam się wyłącza bez targetu.

STRUKTURA plików (wygeneruj WSZYSTKO):
- gslider.php (bootstrap, rejestracja bloku, enqueue)
- src/block-cover-slider/
  - block.json (z atrybutami)
  - index.js (registerBlockType + import Edit)
  - edit.js (UI + highlight)
  - save.js (export default () => null)
  - editor.scss (style highlight w edytorze)
- assets/gslider.css
- assets/gslider.js
- package.json z @wordpress/scripts + skrypty: build, start

Dodatkowo:
- Przeskanuj w workspace istniejący projekt "gutenberg-czik" (blok dane-firmy) i skopiuj styl: InspectorControls, useEffect, odporność na brak danych.
- Kod zgodny z WordPress Coding Standards (PHP) i normalny styl w JS/React.
- Na końcu dopisz krótką instrukcję: npm install, npm run build, aktywacja w WP, jak użyć bloku i jakie klasy muszą być na cover + galerii.
