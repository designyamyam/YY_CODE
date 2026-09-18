# YamYam Berlin — Claude Code Context

## Projekt
Statische Restaurant-Website für YamYam Berlin (koreanisches Restaurant, Berlin Mitte).
Stack: HTML/CSS/JS, Google Sheets als CMS, Hosting auf HostEurope (FTPS-Deploy via GitHub Actions: Push auf main → Staging, Production manuell per workflow_dispatch).

## Links
- CMS Sheet: https://docs.google.com/spreadsheets/d/1np-pFIEK8PD8OdEOArdllTTmqnhBj2Pf4ELICj1PXMU/
- Tasks Sheet: https://docs.google.com/spreadsheets/d/1KBvNdrkyYfWxCeHYJhRVw0jh2BqtiWQftidxrcTjyBo/
- GitHub: https://github.com/designyamyam/YY_CODE
- Preview: https://designyamyam.github.io/YY_CODE/

## Status (Stand 2026-09-18) — WICHTIG vor jeder Arbeit lesen
- **Die neue Site ist NICHT live.** `yamyam-berlin.de` + `www` zeigen per DNS (GoDaddy) auf Readymag (54.194.41.141), dort läuft die alte Readymag-Site.
- Go-Live war am 2026-05-27 (DNS → HostEurope). Danach gab es Probleme mit der Korrektheit der Menü-Daten (Sheet-basierte Speisekarte), deshalb wurde **alles zurückgefahren**: DNS wieder auf Readymag.
- Reaktion darauf: Speisekarte am 2026-06-22 auf PDF-Embed umgestellt (`menue.html` + `menue.pdf`), Sheet-Version pausiert (`menue-paused.html`, noindex).
- **Go-Live geplant: 2026-09-30.** Speisekarte bleibt vorerst PDF; das Personal tauscht das PDF selbst über `/admin/` (siehe Abschnitt „Speisekarten-PDF-Upload“). Die Sheet-basierte Speisekarte ist abgeschaltet (nicht mehr deployt), bleibt aber im Repo als Basis für einen späteren Neubau.
- Die vollständige neue Site liegt auf **Staging: http://yy.yamyam-berlin.de** (HostEurope, nur http, robots disallow). Deploy bei jedem Push auf `main`.
- Production-Webspace bei HostEurope existiert, Deploy nur manuell (Actions → „Deploy to Production" → Run workflow).
- **Achtung:** Auf dem Production-Webspace liegt noch der Stand von vor dem 2026-06-22 (Sheet-basierte Speisekarte). Vor jedem DNS-Wechsel zuerst den Prod-Deploy auslösen.
- Für einen erneuten Go-Live: (1) Menü-PDF final prüfen, (2) Datenschutz-Tab im CMS-Sheet anpassen (nennt noch Readymag als Hoster), (3) Prod-Deploy auslösen und per `curl --resolve yamyam-berlin.de:443:5.175.14.176 https://yamyam-berlin.de/menue.html` prüfen, dass `pdf-viewer.js` drin ist, (4) A-Records bei GoDaddy für `@` und `www` auf **5.175.14.176** umstellen (HostEurope-Webspace wp654; die 80.237.130.176 aus dem Mai antwortet nicht mehr auf 443). SSL: Starfield-DV-Zertifikat für `yamyam-berlin.de` + `www` liegt dort, gültig bis 2026-11-26 (kostenpflichtig bei HostEurope, Auto-Renewal laut Bestellung — prüfen).
- Übergabe von Aisu.Studio an YamYam am 2026-09-18 (Repo-Transfer nach designyamyam). Zugänge, die nicht im Repo liegen: Google-Cloud-Projekt mit dem Sheets-API-Key, HostEurope-FTP (Prod + Staging), GoDaddy-DNS, GA-Property, Eigentum der beiden Google Sheets.

## File Struktur
```
index.html          ← Homepage (Seoul BG, Flugzeug, alle Sektionen)
menue.html          ← Speisekarte (PDF via PDF.js; lädt uploads/menue.pdf, sonst menue.pdf)
menue.pdf           ← Fallback-Speisekarte (eingecheckt); live zählt uploads/menue.pdf
admin/index.php     ← Upload-Seite fürs Personal (Passwort → uploads/menue.pdf, hält 10 Vorversionen)
admin/.user.ini     ← PHP-Upload-Limits für admin/
admin/config.php    ← Passwort-Hash, schreibt der Deploy aus dem Secret (gitignored, nie committen)
uploads/            ← nur auf dem Server, vom Deploy ausgenommen (Uploads + archive/)
menue-paused.html   ← Alte Sheet-basierte Speisekarte — NICHT deployt, im Repo als Basis behalten
about.html          ← Über uns (live aus Google Sheets)
jobs.html           ← Jobs (live aus Google Sheets)
datenschutz.html    ← Impressum & Datenschutz (live aus Google Sheets)
global.css          ← Tokens, Reset, Nav, Footer — einzige globale CSS Datei
menue.css           ← nur Speisekarte spezifisch
config.js           ← API Key, Sheet ID, Image Mapping
sheets.js           ← Fetch + Parser für ALLE Tabs (Home, About, Jobs, Datenschutz)
menue.js            ← Menue-Rendering, Nav, Scrollspy
images/
  backgrounds/      ← yy_seoul_bg.png, yy_plane.png, yy_team.png,
                       yy_jobs_bg.png, yy_Folks_02.png, yy_about_venue.jpg,
                       yy_gradient_fader.png, yy_gradient_fader_nav.png
  logos/            ← YY_Logo_Red.svg
  icons/            ← ig_icon.svg, burger.png, arrow.svg
  menu/             ← Dish-Bilder (gemappt via config.js IMG_MAP)
fonts/
  oswald_5.2.8/     ← oswald-latin-500-normal.woff2
  PublicSans/       ← Regular, Medium, Bold, Italic
```

## Design Tokens (global.css)
- `--red: #FF1705` — einziges Rot
- `--bg: #EBE7E0` — Hintergrundfarbe überall
- `--page-width: 1400px` — max Content-Breite
- `--gap: 40px` — padding links/rechts
- `--nav-height: 100px`
- `--font-display: 'Oswald'` — 500 weight, uppercase
- `--font-body: 'PublicSans'`
- `--font-accent: Georgia` — für Buttons (italic bold)
- `--tracking-display: -0.05em` — entspricht Illustrator -20
- `--beige-md: #6B6660` — angepasst für WCAG AA Konformität

## Design Regeln
- Alle Links: rot = normal, schwarz = hover/active, kein Übergang
- Logo hover → filter: brightness(0) = schwarz
- IG Icon hover → filter: brightness(0) = schwarz
- Buttons (.btn-outline): transparent BG, roter Rahmen, Georgia italic bold
- Button hover: schwarz (Rahmen + Text + Pfeil), kein Übergang, transparent BG
- Border-radius: 8px auf Buttons, 8px auf Price-Pills

## Mobile vs Desktop — Scope-Regel (WICHTIG)
- **Mobile-Anpassungen leben ausschließlich in `@media (max-width: 900px)` Blocks** (bzw. `max-width: 600px` für kleinere Breakpoints).
- **Desktop-Anpassungen leben ausschließlich in `@media (min-width: 901px)` Blocks** oder in den Basis-Regeln (außerhalb von Media Queries).
- **Basis-Regeln (ohne Media Query) NIEMALS ändern, um Mobile zu fixen** — das beeinflusst zwangsläufig Desktop. Stattdessen Override im passenden Mobile-Block.
- Bei jedem User-Prompt zu Layout/Styling: **vor der Änderung** explizit fragen oder festhalten, welcher Viewport gemeint ist.
- Wenn unklar: vor dem Edit auflisten, welche bestehenden Regeln betroffen sind und was sich an Desktop ändern würde.

## Nav Struktur (alle Seiten identisch)
- position: fixed, background: var(--bg)
- Grid: 1fr auto 1fr — Logo zentriert
- Links links: MENUE · ABOUT · JOBS
- Rechts rechts: RESERVE (öffnet Tebi-Widget, `href="#tebi-reservations"`) · ORDER ONLINE (Wolt) · IG-Icon · Burger (mobile)
- Burger: images/icons/burger.png, nur auf mobile sichtbar

## Google Sheets Tabs & Spaltenstruktur
- **Home:** Kategorie | Bezeichnung/Tag | Details/Wert | Zusatzinfo
- **Menue:** Kategorie | ID | Name | Zusatz | Desc_DE | Desc_EN | Allergene | Preis | Bildname
- **About:** Section Headline (H) | Section Text (P) | CTA
- **Jobs:** Job Titel | Anstellungsart & Zeit | Vollständiger Ausschreibungstext
- **Datenschutz:** H1 | h2 | P Strong | P

## Reservierung — Tebi-Widget
- Snippet steht auf **jeder Seite** direkt vor `</body>` (auch 404 + menue-paused): `<script src="https://live.tebi.co/ecom/widget-manager.js" data-widget-token="…" data-analytics-passive="true" id="tebi" data-no-minify="1">`. Der Token ist ein öffentlicher Widget-Token, gehört ins HTML.
- Das Script hängt eine **Pill unten rechts** ein (`#tebi_rs_01`, fixed, z-index 10011, 54×48 px, Farbe kommt aus dem Tebi-Backend) und bindet alle Links mit `href="#tebi-reservations"` (bzw. `#tebi-takeaway`, `#tebi-giftcards`) so, dass sie das Widget öffnen. Der Nav-Link RESERVE nutzt genau das; das Burger-Script schließt dabei das Mobile-Menü.
- `data-analytics-passive="true"` ist Pflicht: ohne das lädt Tebi bei im Tebi-Backend hinterlegten Tracking-IDs selbst gtag.js/Meta-Pixel — an unserem Cookie-Banner vorbei.
- `cookie-banner.css`: Banner steht mit `bottom: 84px` über der Pill, damit sich beide nicht überlagern.
- Datenschutz-Tab im CMS-Sheet: Absatz „Onlinereservierung" nennt noch resmio → auf Tebi umschreiben (Anbieter, Sitz, Datenschutz-Link von Tebi einholen).
- Reservierung vorher: resmio-Widget (`app.resmio.com/yamyam-berlin/widget`), am 2026-09-18 ersetzt.

## Speisekarten-PDF-Upload (Personal)
- URL: `https://yamyam-berlin.de/admin/` (Staging: `http://yy.yamyam-berlin.de/admin/`). Passwort + PDF wählen + Hochladen.
- `admin/index.php` prüft Passwort (`password_verify` gegen Hash aus `admin/config.php`), Magic-Bytes `%PDF-`, max. 20 MB, schreibt atomar nach `uploads/menue.pdf` und legt die Vorversion unter `uploads/archive/menue-<Zeitstempel>.pdf` ab (10 Stück).
- `pdf-viewer.js` macht ein HEAD auf `uploads/menue.pdf`; existiert es, wird es mit `?v=<Last-Modified>` geladen (Cache-Buster), sonst `menue.pdf` aus dem Repo.
- **Passwort** = GitHub-Secret `MENU_UPLOAD_PASSWORD` (Settings → Secrets and variables → Actions). Der Deploy hasht es (`openssl passwd -6`) und schreibt `admin/config.php`. Passwort ändern = Secret ändern + Deploy auslösen. Ohne Secret ist der Upload deaktiviert.
- Beide Workflows schließen `uploads/` und `menue-paused.html` vom Mirror aus — ein Deploy überschreibt Uploads nie.
- Um wieder auf die Sheet-Speisekarte zu wechseln: `menue-paused.html` → `menue.html` zurückbenennen, Exclude im Workflow entfernen, Menü-Daten im Sheet vorher verifizieren (das war der Grund für den Rollback).

## CMS Workflow
Kundin ändert Google Sheet → Änderungen sofort live (kein Push nötig).
Bilder müssen noch manuell via Git hochgeladen werden (Cloudinary geplant).

## Parallax Flugzeug (index.html)
- `const PARALLAX = 0.18` — perfekter Wert, NICHT ändern
- Größe: 40x12px (images/backgrounds/yy_plane.png)
- Startet unter dem MENUE Nav-Link, fliegt nach rechts

## Sicherheit (vor Go-Live!)
- API Key in config.js → in Google Cloud Console auf yamyam-berlin.de/* einschränken
- Staging Sheet einrichten als Puffer (geplant)

## Claude Code Login Fix
Falls Login hängt/nicht funktioniert:
1. `rm -rf ~/.claude`
2. `claude` starten
3. `/login` eingeben
4. Enter → funktioniert

## Sprint / Task Management
- Tasks: https://docs.google.com/spreadsheets/d/1KBvNdrkyYfWxCeHYJhRVw0jh2BqtiWQftidxrcTjyBo/
- Sprint-Tab lesen vor jeder Session
- Nach Code-Änderung: Fix-Feld im Sprint-Tab ausfüllen
- Dann: git add . → git commit → git push
