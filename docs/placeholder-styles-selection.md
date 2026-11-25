# Stile pro Platzhalter: Auswahl per Klick in der Editor-Vorschau

Wie man in der Editor-Vorschau den Platzhalter (oder ein allgemeines Element wie das Listenelement) auswählt, dessen Stil bearbeitet werden soll. Ein Klick öffnet das passende Panel im Seitenbereich. Übernommen aus der Pro-Version, [#34](https://github.com/DanielFloeter/term-and-category-based-posts-widget/issues/34) (Stile pro Platzhalter im Block). Ein Popover am Element ist vorerst nicht vorgesehen, siehe Abschnitt 6.

Stand: 25.11.2026

**Umgesetzt am 25.11.2026** (Block-Titel, Titel, Datum, Auszug, Mehr-Link, Autor, Kommentarzahl, Kategorien und Schlagwörter; Textfarbe, Schriftgröße, Design (Schriftstärke und -stil wie beim Core-Absatz), Schreibweise, Hover-Farbe, beim Auszug dazu die Zeilenhöhe. Listenelement mit Hintergrund, Innen- und Außenabstand und Rahmen; Mehr-Link als Button und Kategorien/Schlagwörter als Badges mit denselben Box-Stilen; „Mehr laden“, Paginierung und aktuelle Seite):
- Die Stile liegen in einem Attribut `placeholderStyles` (`{ default: { title: { color, fontSize, fontWeight } }, block: { title, loadMore, pagination, paginationCurrent } }`). Anders als in der Pro-Version gibt es nur ein Template.
- Die Panels sind ToolsPanels wie bei Core und damit immer offen. „Aufklappen“ heißt hier: Das Panel wird markiert und in den sichtbaren Bereich gescrollt. Standardmäßig eingeblendet ist nur „Size“, beim Listenelement „Margin“.
- Der Block-Titel trägt im Block immer die Klasse `cpwp-block-title` (`block_title_html()`), bei der Ebene „Text“ in einem eigenen `div`. Die Farbe gilt auch für einen verlinkten Titel.
- Kategorien und Schlagwörter teilen sich das Panel „Terms“, jeder Term trägt `.cat-post-term`. Bei Autor, Kommentarzahl und Terms geht die Farbe auch an den inneren Link.
- Die Block-Supports von WordPress (Farbe, Typografie, Maße, Rahmen) sind entfernt, der Block hat nur noch die Ausrichtung.
- Listenelement mit `box-sizing: border-box`. Ein Klick ins Item neben die Platzhalter wählt es aus.
- „Mehr laden“ (Button), Paginierung (alle Seitenzahlen) und aktuelle Seite: je ein Panel mit Text- und Box-Stilen, nur bei der jeweiligen Art von „More posts“. Dazu „Justification“ und bei der Paginierung „Gap“ zwischen den Seitenzahlen. Der Button trägt immer `wp-element-button` und übernimmt damit die Button-Stile des Themes.
- Code: `src/placeholder-styles.js` (Panels), `src/placeholder-style-targets.js` (Ziele, Klick-Erkennung), `placeholder_style_rules()` in `cat-posts-block.php`.

---

## 1. Ausgangslage

- Die Vorschau im Editor ist ein `ServerSideRender` in `div.cpwp-block-preview` (`src/edit.js`). Klicks fängt sie mit `onClickCapture` ab, damit Links nicht auslösen. An derselben Stelle lässt sich auswerten, worauf geklickt wurde.
- Jeder Platzhalter gibt ein Element mit eigener Klasse aus (`item*()`-Funktionen in `class-widget.php`).
- Jedes Item trägt `cat-post-item`, siehe `itemHTML()` in `class-widget.php`.

## 2. Ziel bestimmen

### Platzhalter

Vom angeklickten Element aus wird mit `event.target.closest(...)` das nächste Element mit einer Platzhalter-Klasse gesucht:

| Klasse | Platzhalter |
|---|---|
| `cat-post-title` | `%title%` |
| `cat-post-date` | `%date%` |
| `cat-post-excerpt-more` | `%more-link%` |
| `cpwp-excerpt-text` | `%excerpt%` |
| `cat-post-author` | `%author%` |
| `cat-post-comment-num` | `%commentnum%` |
| `cat-post-tax-category`, `cat-post-tax-tag` | `%category%`, `%post_tag%` |

Weil `closest()` von innen nach außen sucht, gewinnt der Mehr-Link, der innerhalb des Auszugs steht, vor dem Auszug. Steht ein Platzhalter nicht im Template (z. B. der Mehr-Text des Auszugs ohne `%more-link%`), geht der Klick an den nächsten darum.

### Allgemeine Elemente

Gesucht wird in dieser Reihenfolge, der erste Treffer gewinnt:

1. **Block-Titel:** `.cpwp-block-title`.
2. **Mehr laden:** `.categoryPosts-loadmore`.
3. **Paginierung:** zuerst die aktuelle Seite (`.page-numbers.current`), sonst `nav.categoryPosts-pagination` als Ganzes.
4. ein Platzhalter (siehe oben),
5. **Listenelement:** Klick ins Item, aber neben die Platzhalter (`.cat-post-item`).

## 3. Was nach dem Klick passiert

Die Auswahl wird als Zustand gespeichert, nicht als DOM-Element: `{ target, template }`, z. B. `{ target: 'date', template: 'default' }` oder `{ target: 'loadMore', template: 'block' }`.

- **Panel im Seitenbereich:** Das passende Panel im Tab „Stile“ wird markiert und in den sichtbaren Bereich gescrollt.
- **Markierung in der Vorschau:** Alle Elemente desselben Ziels bekommen einen Rahmen, damit sichtbar ist, dass die Änderung für alle Beiträge gilt, nicht nur für den angeklickten.

**Nur im Tab „Stile“:** Auswählen und Ändern per Klick funktioniert nur, solange der Tab „Stile“ offen ist. In allen anderen Fällen (Tab „Einstellungen“ oder „Inhalt“, Seitenbereich geschlossen) verhält sich die Vorschau wie vorher: Klicks werden geschluckt, es gibt keinen Hover-Rahmen und keine Markierung. Einen Hinweis gibt es nicht.

- **Erkennen, ob der Tab offen ist:** Die Panels liegen in `<InspectorControls group="styles">` und werden nur gerendert, solange der Tab „Stile“ offen ist. `PlaceholderStylePanels` meldet beim Ein- und Aushängen (`useEffect`) an `edit.js`, dass die Auswahl aktiv ist.
- **Wechsel des Tabs:** Beim Verlassen von „Stile“ wird die Auswahl samt Markierung gelöscht.
- **Fokus-Rahmen:** Ein Klick auf einen Platzhalter ohne Link fokussiert den Block, WordPress zeichnet dann einen Rahmen um den ganzen Block. Solange Platzhalter ausgewählt werden, ist er ausgeblendet (`cpwp-is-picking-styles`).

**Warum der Block nicht selbst auf „Stile“ umschaltet:** Die Aktion `requestInspectorTab( tabName, options )` im block-editor-Store ist privat (nur über `unlock()` aus `@wordpress/private-apis`, nur für Core-Pakete). Den Tab-Knopf per DOM anzuklicken ginge, hängt aber an Klassennamen und Markup des Editors. Beides wird nicht genutzt.

## 4. Einschränkungen

- **Erster Klick:** wählt zuerst den Block aus. Erst danach, und nur bei offenem Tab „Stile“, funktioniert die Auswahl eines Ziels.
- **Neuladen der Vorschau:** `ServerSideRender` ersetzt bei jeder Änderung das Markup, auch bei einer Stiländerung, weil die Stile serverseitig über `getCSSRules` kommen. Deshalb hängt die Auswahl am Zustand, und die Markierung ist eine CSS-Regel auf die Vorschau, nicht am einzelnen Element.
- **Nicht anklickbar:** Vorschaubilder, Shortcode-Ausgaben und Platzhalter, die im angeklickten Beitrag leer sind. Ein Klick darauf wählt das Listenelement.

## 5. Panels im Seitenbereich

Reihenfolge wie im Block: Block-Titel, Listenelement und Platzhalter, unten „Mehr laden“ bzw. Paginierung, ganz unten das eingebaute CSS („Styles“). Ein Panel erscheint nur, wenn sein Platzhalter im Template steht.

Entwurf (klickbar, aus der Pro-Version): https://claude.ai/artifact/CCLnG5a3B1NYusiwYWdNzH

## 6. Später: Popover am Element

Vorerst nicht nötig. Falls es kommt, wären das die Möglichkeiten:

| Variante | Umsetzung | Vorteile | Nachteile |
|---|---|---|---|
| **A: Am Element** | `Popover` aus `@wordpress/components` mit `anchor` auf das angeklickte Element, `placement="bottom-start"`, `flip`/`shift` | direkter Bezug | verdeckt Beiträge; Anker geht beim Neuladen der Vorschau verloren |
| **B: In der Block-Werkzeugleiste** | `BlockControls` mit einem Dropdown „Stil: Datum“ | feste, bekannte Position | weiter weg vom Element |
| **C: Am Rand der Vorschau** | `Popover` mit `anchor` auf `.cpwp-block-preview`, `placement="right-start"` | verdeckt keine Beiträge | auf schmalen Bildschirmen kein Platz daneben |
