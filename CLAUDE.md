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
- **Die neue Site ist NICHT live.** `yamyam-berlin.de` + `www` zeigen auf Readymag (54.194.41.141), dort laeuft die alte Readymag-Site.
- **DNS wird im HostEurope-KIS verwaltet** (Domain-Administration: A-Records, MX, TTL). Die Nameserver heissen zwar `ns45/ns46.domaincontrol.com` (GoDaddy-Infrastruktur), aber die Zone wird aus dem KIS bespielt - Aenderungen erscheinen dort mit einigen Minuten Verzoegerung. Es braucht **keinen** GoDaddy- oder Readymag-Zugang. Pruefen mit: `dig @ns45.domaincontrol.com yamyam-berlin.de A +noall +answer` (SOA-Serial steigt bei jeder Aenderung).
- **IP-Wahl ist entscheidend:** Laut KIS ist `80.237.130.176` die Standard-A-Record-IP **ohne SSL**, `5.175.14.176` die **SSL-faehige**. Beim Go-Live im Mai wurde 80.237.130.176 eingetragen - daher lief https nicht. Immer `5.175.14.176` verwenden. AAAA bewusst weglassen: die angebotene IPv6 ist die Standard-Variante, nicht als SSL-faehig ausgewiesen.
- TTL fuer `@` und `www` am 2026-09-29 von 86400 auf **600** gesetzt, damit die Umstellung schnell greift.
- Go-Live war am 2026-05-27 (DNS → HostEurope). Danach gab es Probleme mit der Korrektheit der Menü-Daten (Sheet-basierte Speisekarte), deshalb wurde **alles zurückgefahren**: DNS wieder auf Readymag.
- Reaktion darauf: Speisekarte am 2026-06-22 auf PDF-Embed umgestellt (`menue.html` + `menue.pdf`), Sheet-Version pausiert (`menue-paused.html`, noindex).
- **Go-Live geplant: 2026-09-30.** Speisekarte bleibt vorerst PDF; das Personal tauscht das PDF selbst über `/admin/` (siehe Abschnitt „Speisekarten-PDF-Upload“). Die Sheet-basierte Speisekarte ist abgeschaltet (nicht mehr deployt), bleibt aber im Repo als Basis für einen späteren Neubau.
- Die vollständige neue Site liegt auf **Staging: http://yy.yamyam-berlin.de** (HostEurope, nur http, robots disallow). Deploy bei jedem Push auf `main`.
- Production-Webspace bei HostEurope existiert, Deploy nur manuell (Actions → „Deploy to Production" → Run workflow).
- **Production ist auf aktuellem Stand** (Deploy 2026-09-29, Lauf #3, erster gruener Production-Lauf ueberhaupt - die beiden roten vom Mai lagen am `c19/`-Loeschversuch, seit dem Exclude behoben). Geprueft ueber `curl --resolve yamyam-berlin.de:443:5.175.14.176`: alle Seiten 200, Zertifikat gueltig, http->https greift, eigene 404-Seite, PDF.js und Menue-PDF byteidentisch mit dem Repo, `uploads/` leer (Fallback greift), `robots.txt` = Allow, Sheet-Tab `Menue`, `c19/` unangetastet, `menue-paused.html` entfernt. Es fehlt nur noch die DNS-Umstellung.
- `.htaccess` (neu 2026-09-28): eigene 404-Seite (`ErrorDocument`, vorher zeigte Apache „Object not found!") und http→https-Zwang **nur** für `yamyam-berlin.de` + `www` — Staging bleibt bewusst ausgenommen, weil `yy.` kein Zertifikat hat. Der HostEurope-Webspace leitete vorher nicht auf https um.
- Für einen erneuten Go-Live: (1) Menü-PDF final prüfen, (2) Datenschutz-Tab im CMS-Sheet anpassen (nennt noch Readymag als Hoster), (3) Prod-Deploy auslösen und per `curl --resolve yamyam-berlin.de:443:5.175.14.176 https://yamyam-berlin.de/menue.html` prüfen, dass `pdf-viewer.js` drin ist, (4) A-Records im HostEurope-KIS fuer `@` und `www` auf **5.175.14.176** umstellen (SSL-IP; die 80.237.130.176 aus dem Mai ist die Variante ohne SSL). Danach prüfen: `http://yamyam-berlin.de` leitet auf https um, und das Tebi-Widget öffnet sich mit Inhalt (siehe Abschnitt Reservierung). SSL: Starfield-DV-Zertifikat für `yamyam-berlin.de` + `www` liegt dort, gültig bis 2026-11-26 (kostenpflichtig bei HostEurope, Auto-Renewal laut Bestellung — prüfen).
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
menue/index.html    ← Ziel der QR-Codes im Restaurant: leitet auf menue.html weiter
                      (Query und Anker bleiben erhalten). NICHT loeschen - die Codes sind gedruckt.
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
- Das Script hängt eine **Pill unten rechts** ein (`#tebi_rs_01`, fixed, z-index 10011, 54×48 px, Farbe/Text kommen aus dem Tebi-Backend) und bindet alle Links mit `href="#tebi-reservations"` (bzw. `#tebi-takeaway`, `#tebi-giftcards`) so, dass sie das Widget öffnen. Der Nav-Link RESERVE nutzt genau das; das Burger-Script schließt dabei das Mobile-Menü.
- **Das Widget braucht https.** Ein https-iframe in einer http-Seite ist kein „secure context" — auf Staging (http) öffnet sich das Panel, bleibt aber **weiß**. Über https (getestet auf der GitHub-Pages-Kopie) läuft es vollständig: Gäste/Datum/Zeit, Markenfarbe Rot aus dem Tebi-Konto. **Reservierung lässt sich auf Staging also nicht testen** — auf `https://designyamyam.github.io/YY_CODE/` prüfen oder nach dem Go-Live direkt auf der Live-Domain.
- **Die Pill ist ausgeblendet** (Entscheidung 2026-09-18): `reservations.js` setzt den Rahmen auf `visibility:hidden`, solange seine Höhe ≤ 48px ist, und zeigt ihn erst im aufgeklappten Zustand. Pill wieder einschalten = `reservations.js` aus den Seiten nehmen (dann Cookie-Banner wieder über die Pill heben, `bottom: 84px` / mobil `80px`).
- `data-analytics-passive="true"` ist Pflicht: ohne das lädt Tebi bei im Tebi-Backend hinterlegten Tracking-IDs selbst gtag.js/Meta-Pixel — an unserem Cookie-Banner vorbei.
- Datenschutz-Tab im CMS-Sheet: Absatz „Onlinereservierung" nennt noch resmio → auf Tebi umschreiben (Anbieter, Sitz, Datenschutz-Link von Tebi einholen).
- Reservierung vorher: resmio-Widget (`app.resmio.com/yamyam-berlin/widget`), am 2026-09-18 ersetzt.

## Speisekarten-PDF-Upload (Personal)
- URL: `https://yamyam-berlin.de/admin/` (Staging: `http://yy.yamyam-berlin.de/admin/`). Passwort + PDF wählen + Hochladen.
- `admin/index.php` prüft Passwort (`password_verify` gegen Hash aus `admin/config.php`), Magic-Bytes `%PDF-`, max. 20 MB, schreibt atomar nach `uploads/menue.pdf` und legt die Vorversion unter `uploads/archive/menue-<Zeitstempel>.pdf` ab (10 Stück).
- Das Menü-PDF ist ein **Text-PDF** (eingebettete Schriften, echter Vektortext; die enthaltenen JPEGs sind die Gerichtsfotos). Der Upload verändert die Datei nicht — Prüfsumme vorher/nachher identisch.
- **Rendering:** `pdf-viewer.js` zeichnet jede Seite auf ein `<canvas>`. Ein Canvas hat feste Pixelauflösung, deshalb wird bei **Zoom und Größenänderung neu gezeichnet** (`visualViewport`-Events, Obergrenze 2600 px je Seite) und weit entfernte Seiten werden freigegeben (`canvas.width = 0`), damit 24 hochauflösende Seiten den Speicher nicht sprengen. Ohne das sah die Karte beim Reinzoomen verpixelt aus (Befund 2026-09-28).
- **Nativer PDF-Viewer wurde am 2026-09-28 getestet und verworfen** (Commit f2fc24e, zurückgenommen in 27d470c): `<object type="application/pdf">` ab 901px rendert zwar mit Font-Hinting (gleichmäßige Strichstärken), malt die Seiten aber auf Weiß und setzt auf breiten Schirmen kleine Seiten in eine dunkelgraue Fläche — das Beige der Site und die Flächenwirkung gehen verloren, von außen nicht stylebar. Das war schon im Juni der Grund für den Wechsel auf PDF.js. **Nicht erneut versuchen**, außer die Karte wird mit vollflächig beigem Seitenhintergrund (`#e7e4df`) exportiert.
- Preis dieser Entscheidung: Canvas kennt kein Font-Hinting. Buchstabenstaemme landen je nach Position ganz oder halb auf einem Pixel, einzelne Buchstaben wirken dadurch duenner als ihre Nachbarn (aufgefallen am I in FRIDAY und DOSHIRAK).
- Nachgemessen 2026-09-28: dieselbe Seite bei gleicher Aufloesung (1228 px) mit dem Quartz-Renderer von macOS (`sips`) gerendert -> Striche gleichmaessig. Im PDF.js-Canvas sind die Staemme 4-6 px breit, der duennste traegt rund 18 % weniger Farbe. Liegt also am Renderer, nicht am PDF.
- **Bewusst NICHT nachgerendert beim Zoomen** (Entscheidung 2026-09-28): Eine Version, die bei Zoom in hoeherer Aufloesung neu zeichnet, war zwar schaerfer, legte aber genau diese ungleichen Strichstaerken frei und wirkte dadurch kaputt. Gleichmaessig weich schlaegt scharf-aber-uneben. `pdf-viewer.js` zeichnet deshalb weiterhin einmal in Layoutbreite x Pixeldichte; beim Zoomen werden vorhandene Pixel vergroessert.
- Ebenfalls getestet und verworfen: Supersampling (1,5x / 2x rendern und herunterskalieren) - Downscaling mittelt, ersetzt aber kein Hinting.
- Nicht gelöst: Der Text im Canvas ist **nicht markierbar/durchsuchbar** und für Screenreader unsichtbar. Dafür bräuchte es die PDF.js-Textebene (`renderTextLayer` + `.textLayer`-CSS) über dem Canvas — bewusst vor dem Go-Live nicht mehr angefasst.
- `pdf-viewer.js` macht ein HEAD auf `uploads/menue.pdf`; existiert es, wird es mit `?v=<Last-Modified>` geladen (Cache-Buster), sonst `menue.pdf` aus dem Repo.
- **Passwort** = GitHub-Secrets (Settings → Secrets and variables → Actions): `MENU_UPLOAD_PASSWORD` für Production, `MENU_UPLOAD_PASSWORD_STAGING` für Staging (getrennt, weil Staging kein SSL hat und das Passwort dort im Klartext übertragen wird). Der Deploy hasht es (`openssl passwd -6`) und schreibt `admin/config.php`. Passwort ändern = Secret ändern + Deploy auslösen. Ohne Secret ist der Upload auf dem jeweiligen Webspace deaktiviert.
- Beide Workflows schließen `uploads/` und `menue-paused.html` vom Mirror aus — ein Deploy überschreibt Uploads nie.
- Um wieder auf die Sheet-Speisekarte zu wechseln: `menue-paused.html` → `menue.html` zurückbenennen, Exclude im Workflow entfernen, Menü-Daten im Sheet vorher verifizieren (das war der Grund für den Rollback).

## WICHTIG: Nie direkt auf dem Webspace arbeiten
Beide Deploys spiegeln das Repo mit `mirror -R --delete` auf den Server. **Alles, was nur auf dem
Webspace liegt und nicht im Repo ist, wird beim naechsten Deploy geloescht** - ausgenommen sind
ausschliesslich `uploads/` und `c19/`.

Passiert ist das bereits: Jemand hat den Ordner `menue/` per FTP angelegt, weil die gedruckten
QR-Codes im Restaurant ins Leere liefen, ihn aber nicht ins Repo uebernommen. Der naechste Deploy
haette ihn wieder entfernt. Seit 2026-10-01 liegt er im Repo.

Wer etwas schnell direkt auf dem Server fixt: **danach sofort ins Repo committen**, sonst ist es
beim naechsten Deploy weg. Pruefen laesst sich der Unterschied mit
`curl --resolve yamyam-berlin.de:443:5.175.14.176 https://yamyam-berlin.de/<datei>` gegen die
lokale Datei.

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
