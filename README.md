# Viagem – parcely (test)

Webová aplikace, která zobrazuje katastrální parcely čtyř katastrálních území v okrese Jičín na mapě. Po kliknutí na parcelu se zobrazí její číslo, výměra a kód katastrálního území.

Vytvořeno jako technický úkol. Data pocházejí z ČÚZK (INSPIRE Cadastral Parcels).

## Zadání a rozsah

- Katastrální území: Jičín (659541), Libáň (681679), Nová Paka (705128), Sobotka (752096). Celkem 26 234 parcel.
- Minimum zadání je Jičín a tři další KÚ v okrese. Celý okres zatím není pokryt.

## Požadavky

- PHP 8.1+ s rozšířeními `pdo_sqlite` a `sqlite3`
- Pro převod dat: GDAL (`ogr2ogr`), např. přes OSGeo4W na Windows nebo `apt install gdal-bin` na Linuxu
- Moderní prohlížeč (Chrome) s přístupem na internet

## Získání dat

Data nejsou v repozitáři. Stáhněte ZIP archivy pro čtyři KÚ z `https://services.cuzk.gov.cz/gml/inspire/cp`, rozbalte je a výsledné XML převeďte do GeoJSON:

```bash
ogr2ogr -f GeoJSON data/659541.geojson 659541.xml CadastralParcel -t_srs EPSG:4326
```

Stejně pro 681679, 705128 a 752096. Výsledné soubory uložte do složky `data/`.

## Spuštění

1. Naimportujte data do SQLite:
```bash
   php scripts/import.php
```
   Vytvoří `data/parcels.sqlite` s bounding boxy a geometrií každé parcely.

2. Spusťte vestavěný server z kořene projektu:
```bash
   php -S localhost:8000
```

3. Otevřete v prohlížeči `http://localhost:8000/public/index.html`.

## Architektura

```
public/
  index.html          Leaflet mapa, panel území, dotazy na API
  api/
    parcels.php       parcely v zadaném bbox jako GeoJSON
    territories.php   seznam KÚ s bbox a počtem parcel
scripts/
  import.php          import GeoJSON → SQLite
data/                 generovaná data, není v gitu
```

Data se čtou z SQLite. Frontend nikdy nedostane celý dataset najednou.

## Rozhodnutí

**Předběžné zpracování místo živých dotazů na ČÚZK.** WFS služba vrací maximálně několik objektů na dotaz, odpovídá pomalu a na velké oblasti vrací chybu. Data jsou proto stažena předem a uložena lokálně.

**SQLite místo PostGIS.** Data se vejdou do jednoho souboru a nevyžadují běžící server. Pro bbox dotaz stačí indexované sloupce `min_lng, max_lng, min_lat, max_lat`. Prostorový index v SpatiaLite by byl přesnější, ale vyžaduje další instalaci.

**Bounding box v tabulce.** Dotaz vybere parcely, jejichž bbox protíná obrazovku. Parcely na okraji obrazovky tak nezmizí.

**Limit počtu objektů v odpovědi.** Server vrací nejvýše `LIMIT` parcel. Pokud jich je víc, nevrací částečný výsledek, ale příznak `truncated: true`. Frontend pak zobrazí upozornění, aby uživatel přiblížil. Částečná data by vypadala jako chyba.

**Zaokrouhlení souřadnic na 6 desetinných míst.** Přesnost přibližně 0,1 m je pro mapu dostatečná. Velikost odpovědi se snížila.

**Canvas místo SVG.** Leaflet ve výchozím nastavení kreslí každý polygon jako prvek SVG v DOM. Při tisících polygonů se mapa zpomalila. Přepnutí na canvas tento problém výrazně zmírnilo.

**Body místo obdélníků území.** Obdélníky podle bbox neodpovídaly tvaru KÚ, proto jsou území na přehledu zobrazena body ve středu s tlačítky v panelu.

**Parcely jen od zoomu 14.** Na menších měřítkách by se načítaly tisíce objektů zbytečně. Při nižším zoomu se zobrazují pouze území.

## Měření

| Stav | Největší odpověď `parcels.php` | Poznámka |
|---|---|---|
| Bez zaokrouhlení, LIMIT 3000 | 2 076 KB | lagy při přiblížení |
| Zaokrouhlení na 6 míst, LIMIT 2000 | 872 KB | odezva serveru 8–33 ms; plynulost subjektivně bez glitchů, parcely se dotáhnou až po ukončení tažení |

`territories.php`: cca 0,7 KB, 50–70 ms.

## Co bych udělal s více časem

- Zjednodušit geometrii podle zoomu, aby se na velkém měřítku posílaly méně detailní polygony.
- Použít přesné hranice území z vrstvy `CadastralZoning` místo bodů.
- Přidat test importu a kontrolu počtu parcel po importu.
- Rozdělit PHP na třídy: připojení k databázi, repozitář parcel, kontrolér.
- Přidat zobrazení celého okresu, nejen čtyř území.
- Sbalit úvodní stav a kopírovat URL s pohledem na mapu.