# GSlider — Cover Slider block

Instalacja i użycie:

- Zainstaluj zależności i zbuduj skrypty (w katalogu wtyczki):

```
npm install
npm run build
```

- Aktywuj wtyczkę w WordPressie.

- W edytorze Gutenberg znajdź blok `GSlider: Cover Slider` i dodaj go.

Wymagane klasy w markupie:

- cover: element z klasami `wp-block-cover WPBlockCoverSlider` oraz wewnętrznym obrazem `.wp-block-cover__image-background`.
- źródło obrazów: element z klasą `czik-hero-rotator__src` zawierający kilka elementów `img` (niewidoczny w layout).

Jeśli używasz Full Site Editing (block themes), wtyczka stosuje fallback i enqueuje zasoby także globalnie; JS sam wyłącza się jeśli nie znajdzie targetów.
# gslider
